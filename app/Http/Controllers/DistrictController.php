<?php

namespace App\Http\Controllers;

use App\Services\Analysis\DistrictReporter;
use App\Services\Analysis\StatisticsRepository;
use App\Support\Geometry;
use App\Support\Period;
use App\Support\StoreSectors;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * 상권 보고서 화면.
 *
 * 지도에 원·사각형·다각형으로 상권을 그리면 그 안의 통계를 탭으로 나눠 보여 준다.
 * 화면은 하나이고, 탭마다 필요한 데이터만 따로 불러온다.
 */
class DistrictController extends Controller
{
    /** 상권 하나에 허용하는 최대 면적 (m²) */
    public const MAX_AREA_M2 = 500_000;

    public function __construct(
        private readonly DistrictReporter $reporter,
        private readonly StatisticsRepository $stats,
    ) {}

    public function index(Request $request): View
    {
        $periods = $this->stats->availablePeriods();

        return view('districts.index', [
            'defaultCenter' => config('map.default_center'),
            'defaultRadius' => config('map.default_radius'),
            'radiusOptions' => config('map.radius_options'),
            'periods' => $periods,
            'defaultPeriod' => $periods[0] ?? Period::month(now()->subMonth()->format('Ym')),
            'maxAreaM2' => self::MAX_AREA_M2,
            'groups' => collect(StoreSectors::GROUPS)
                ->map(fn (array $g, string $key) => ['code' => $key, 'name' => $g[0]])
                ->values()
                ->all(),
        ]);
    }

    /** 상권 탭 */
    public function overview(Request $request): JsonResponse
    {
        return $this->withDistrict($request, function (array $context) {
            return [
                'district' => $context['district'],
                'overview' => $this->reporter->overview($context['weights'], $context['period'], $context['ring']),
            ];
        });
    }

    /** 매장 탭 */
    public function stores(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'group' => ['nullable', 'string', 'max:20'],
            'q' => ['nullable', 'string', 'max:60'],
            'page' => ['nullable', 'integer', 'min:1', 'max:5000'],
        ]);

        return $this->withDistrict($request, function (array $context) use ($validated) {
            return [
                'district' => $context['district'],
                'stores' => $this->reporter->stores(
                    $context['ring'],
                    $validated['group'] ?? null,
                    trim((string) ($validated['q'] ?? '')),
                    (int) ($validated['page'] ?? 1),
                ),
            ];
        });
    }

    /** 주거인구 탭 */
    public function residence(Request $request): JsonResponse
    {
        return $this->withDistrict($request, function (array $context) {
            return [
                'district' => $context['district'],
                'residence' => $this->reporter->residence($context['weights'], $context['period']),
            ];
        });
    }

    /**
     * 세 탭이 똑같이 하는 일 — 그린 모양을 받아 행정동 가중치로 바꾸고 상권 머리말을 만든다.
     */
    private function withDistrict(Request $request, callable $build): JsonResponse
    {
        $validated = $request->validate([
            'shape' => ['required', 'in:circle,rectangle,polygon'],
            'ring' => ['required', 'array', 'min:3', 'max:400'],
            'ring.*' => ['required', 'array', 'size:2'],
            'ring.*.*' => ['required', 'numeric'],
            'radius_m' => ['nullable', 'integer', 'min:50', 'max:5000'],
            'period' => ['nullable', 'regex:/^(\d{6}|\d{4}[1-4])$/'],
        ]);

        $ring = Geometry::normalizeRing($validated['ring']);

        if (count($ring) < 3) {
            return response()->json(['ok' => false, 'message' => '상권 모양을 만들 수 없습니다.'], 422);
        }

        $areaM2 = $this->reporter->areaM2($ring);

        if ($areaM2 > self::MAX_AREA_M2) {
            return response()->json([
                'ok' => false,
                'message' => sprintf('면적 %s㎡ 이하로 만들 수 있습니다.', number_format(self::MAX_AREA_M2)),
                'area_m2' => $areaM2,
            ], 422);
        }

        $period = isset($validated['period'])
            ? Period::parse($validated['period'])
            : ($this->stats->latestPeriod() ?? Period::month(now()->subMonth()->format('Ym')));

        ['resolved' => $resolved, 'weights' => $weights] = $this->reporter->resolve($ring);

        if ($resolved->isEmpty()) {
            return response()->json([
                'ok' => false,
                'message' => '그린 범위 안에 수록된 행정동이 없습니다.',
                'area_m2' => $areaM2,
            ], 422);
        }

        [$centerLng, $centerLat] = Geometry::centroid($ring);
        $first = $resolved->first()['region'];

        $district = [
            'name' => $this->reporter->nameFor($resolved),
            'address' => $first->full_name,
            'shape' => $validated['shape'],
            'shape_label' => \App\Models\Analysis::SHAPE_LABELS[$validated['shape']] ?? '다각형 상권',
            'radius_m' => $validated['radius_m'] ?? null,
            'area_m2' => $areaM2,
            'center' => ['lat' => round($centerLat, 6), 'lng' => round($centerLng, 6)],
            'period' => ['code' => $period->code, 'label' => $period->label()],
            'sido_name' => $first->sido_name,
            'regions' => $resolved->map(fn (array $item) => [
                'code' => $item['region']->code,
                'name' => $item['region']->full_name,
                'weight' => round($item['weight'], 3),
            ])->all(),
        ];

        return response()->json(['ok' => true] + $build([
            'district' => $district,
            'weights' => $weights,
            'period' => $period,
            'ring' => $ring,
        ]));
    }
}
