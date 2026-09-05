<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Analysis;
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
            return response()->json([
                'ok' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json(['ok' => true, 'data' => $payload]);
    }
}
