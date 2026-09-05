<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Analysis;
use App\Models\Region;
use App\Services\Analysis\MarketAnalyzer;
use App\Services\Analysis\StatisticsRepository;
use App\Support\Period;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 지도 화면에서 클릭한 지점의 상권을 즉석에서 계산해 돌려준다.
 * 저장하지 않는 미리보기라 analyses 테이블에 남지 않는다.
 */
class MarketPreviewController extends Controller
{
    public function __construct(
        private readonly MarketAnalyzer $analyzer,
        private readonly StatisticsRepository $stats,
    ) {}

    public function preview(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
            'radius_m' => ['required', 'integer', 'min:100', 'max:5000'],
            'period' => ['nullable', 'regex:/^(\d{6}|\d{4}[1-4])$/'],
        ]);

        $period = $validated['period']
            ? Period::parse($validated['period'])
            : ($this->stats->latestPeriod() ?? Period::month(now()->subMonth()->format('Ym')));

        // 저장하지 않는 임시 분석. 컨트롤러 밖으로 나가지 않는다.
        $analysis = new Analysis([
            'title' => '지도 미리보기',
            'mode' => 'radius',
            'center_lat' => $validated['lat'],
            'center_lng' => $validated['lng'],
            'radius_m' => $validated['radius_m'],
            'region_codes' => [],
        ] + $period->columns());

        try {
            $payload = $this->analyzer->analyze($analysis);
        } catch (\Throwable $e) {
            // 수록 범위 밖을 찍은 경우가 대부분이라, 어디까지 수록돼 있고
            // 가장 가까운 수록 지역이 어디인지까지 알려 준다.
            return response()->json([
                'ok' => false,
                'message' => $e->getMessage(),
                'coverage' => $this->coverage(),
                'nearest' => $this->nearest((float) $validated['lat'], (float) $validated['lng']),
            ], 422);
        }

        return response()->json(['ok' => true, 'data' => $payload]);
    }

    /**
     * 지금 수록된 지역 (시도별 행정동 수).
     *
     * @return array<int, array{sido:string, dongs:int}>
     */
    private function coverage(): array
    {
        return Region::query()
            ->selectRaw('sido_name, COUNT(*) AS dongs')
            ->groupBy('sido_name')
            ->orderByDesc('dongs')
            ->get()
            ->map(fn ($row) => ['sido' => $row->sido_name, 'dongs' => (int) $row->dongs])
            ->all();
    }

    /**
     * 찍은 지점에서 가장 가까운 수록 행정동. 반경과 무관하게 전체에서 찾는다.
     *
     * @return array{code:string, name:string, lat:float, lng:float, distance_km:float}|null
     */
    private function nearest(float $lat, float $lng): ?array
    {
        $region = Region::query()
            ->selectRaw(
                'regions.*, (6371 * acos(least(1, cos(radians(?)) * cos(radians(lat))'
                .' * cos(radians(lng) - radians(?)) + sin(radians(?)) * sin(radians(lat))))) AS distance_km',
                [$lat, $lng, $lat]
            )
            ->whereNotNull('lat')
            ->orderBy('distance_km')
            ->first();

        if (! $region) {
            return null;
        }

        return [
            'code' => $region->code,
            'name' => $region->full_name,
            'lat' => (float) $region->lat,
            'lng' => (float) $region->lng,
            'distance_km' => round((float) $region->distance_km, 1),
        ];
    }
}
