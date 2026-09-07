<?php

namespace App\Services\Analysis;

use App\Support\Geometry;
use App\Support\Period;
use App\Support\StoreSectors;
use App\Support\Taxonomy;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * 상권 보고서(지도에 그린 상권)의 세 탭 데이터를 만든다.
 *
 * 리포트(MarketAnalyzer)와 목적이 다르다. 리포트는 A4 로 뽑는 문서라
 * 모든 섹션을 한 번에 만들지만, 이 화면은 탭마다 따로 불러 쓰고
 * 매장 목록처럼 페이지를 넘기는 것이 있어 조회 단위가 잘게 나뉜다.
 */
class DistrictReporter
{
    public function __construct(
        private readonly RegionResolver $resolver,
        private readonly StatisticsRepository $stats,
    ) {}

    /**
     * 상권 탭.
     *
     * @param  array<string, float>  $weights
     */
    public function overview(array $weights, Period $period, array $ring): array
    {
        $coverage = $this->stats->coverage($weights, $period);
        $byDayBand = $this->stats->salesByDayAndBand($weights, $period);
        $byGenderAge = $this->stats->salesByGenderAge($weights, $period);
        $byIndustry = $this->stats->salesByIndustry($weights, $period);

        $dayCounts = $period->dayCounts();
        $totalDays = max(1, $dayCounts['weekday'] + $dayCounts['weekend']);

        // 화면은 "월 추정 매출" 로 보여 준다. 저장값은 하루 평균이라 기간 일수를 곱한다.
        $dailyAmount = array_sum(array_column($byIndustry, 'amount'));
        $dailyCount = array_sum(array_column($byIndustry, 'count'));

        return [
            'coverage' => $coverage,
            'coverage_ratio' => $this->stats->coverageRatio($weights, $period),
            'sales' => [
                'daily_amount' => (int) round($dailyAmount),
                'daily_count' => (int) round($dailyCount),
                'monthly_amount' => (int) round($dailyAmount * 30),
                'monthly_count' => (int) round($dailyCount * 30),
                'period_amount' => (int) round($dailyAmount * $totalDays),
                'days' => $totalDays,
                'avg_ticket' => $dailyCount > 0 ? (int) round($dailyAmount / $dailyCount) : 0,
            ],
            'trend' => $this->salesTrend($weights, $period),
            'stores' => $this->storesByGroup($ring, $byIndustry) + ['covered' => $coverage['stores']],
            'payment_habits' => $this->paymentHabits($byDayBand, $byGenderAge, $coverage['sales']),
        ];
    }

    /**
     * 최근 분기별 매출 변화. 수록된 기간이 하나뿐이면 점 하나만 나온다.
     *
     * @param  array<string, float>  $weights
     * @return array<int, array{code:string, label:string, amount:int, count:int}>
     */
    public function salesTrend(array $weights, Period $period, int $limit = 8): array
    {
        $column = $period->filterColumn();

        $codes = DB::table('card_sales')
            ->whereIn('region_code', array_keys($weights))
            ->where($column, '!=', '')
            ->distinct()
            ->orderByDesc($column)
            ->limit($limit)
            ->pluck($column)
            ->sort()
            ->values();

        $trend = [];

        foreach ($codes as $code) {
            $point = $period->isQuarter() ? Period::quarter($code) : Period::month($code);
            $rows = $this->stats->salesByIndustry($weights, $point);

            $trend[] = [
                'code' => $code,
                'label' => $point->label(),
                'amount' => (int) round(array_sum(array_column($rows, 'amount'))),
                'count' => (int) round(array_sum(array_column($rows, 'count'))),
            ];
        }

        return $trend;
    }

    /**
     * 대분류 6묶음별 매장 수.
     *
     * 점포는 좌표가 있어 그린 상권 안에 있는지 정확히 판정할 수 있다.
     * 카드매출처럼 행정동 면적으로 안분하지 않는다.
     *
     * @param  array<int, array{0: float, 1: float}>  $ring
     */
    public function storesByGroup(array $ring, array $salesByIndustry = []): array
    {
        $rows = $this->storesInRing($ring, ['large_code']);
        $counts = [];

        foreach ($rows as $row) {
            $group = StoreSectors::groupOf($row->large_code);

            if ($group !== null) {
                $counts[$group] = ($counts[$group] ?? 0) + 1;
            }
        }

        // 매출은 카드매출 업종 체계라 매장 수와 근거가 다르다. 같은 칸에 나란히 놓되
        // 매장 수는 실제 좌표, 매출은 행정동 안분값이라는 점을 화면에서 밝힌다.
        $sales = [];

        foreach ($salesByIndustry as $item) {
            $group = StoreSectors::SALES_GROUPS[$item['group'] ?? ''] ?? null;

            if ($group !== null) {
                $sales[$group] = ($sales[$group] ?? 0) + $item['amount'];
            }
        }

        $groups = [];

        foreach (StoreSectors::GROUPS as $key => [$label]) {
            $groups[] = [
                'code' => $key,
                'name' => $label,
                'stores' => $counts[$key] ?? 0,
                'daily_amount' => (int) round($sales[$key] ?? 0),
                'monthly_amount' => (int) round(($sales[$key] ?? 0) * 30),
            ];
        }

        usort($groups, fn ($a, $b) => $b['stores'] <=> $a['stores']);

        return ['total' => $rows->count(), 'groups' => $groups];
    }

    /**
     * 그린 상권 안에 실제로 들어 있는 점포.
     *
     * bounding box 로 한 번 걸러 인덱스를 태운 뒤, 폴리곤 안인지는 PHP 에서 본다.
     * 상권은 최대 0.5km² 라 이 범위의 점포 수는 많아야 수천 건이다.
     *
     * @param  array<int, array{0: float, 1: float}>  $ring
     * @param  array<int, string>  $columns
     * @return Collection<int, object>
     */
    private function storesInRing(array $ring, array $columns): Collection
    {
        [$minLng, $minLat, $maxLng, $maxLat] = Geometry::bbox($ring);

        $needed = array_values(array_unique(array_merge($columns, ['lat', 'lng'])));

        return DB::table('stores')
            ->whereBetween('lat', [$minLat, $maxLat])
            ->whereBetween('lng', [$minLng, $maxLng])
            ->get($needed)
            ->filter(fn ($row) => Geometry::contains($ring, (float) $row->lng, (float) $row->lat))
            ->values();
    }

    /**
     * 결제 경향. 어느 시간대·요일·성별·연령대에 결제가 가장 활발한지.
     *
     * openub 은 "결제 발생 일별(휴일)" 과 "결제 세대(유자녀)" 도 보여 주지만
     * 서울시 상권분석서비스에는 그 두 축이 없어 여기서는 만들지 않는다.
     */
    public function paymentHabits(array $byDayBand, array $byGenderAge, bool $covered): array
    {
        if (! $covered) {
            return ['covered' => false, 'items' => []];
        }

        $bands = [];

        foreach (Taxonomy::TIME_BANDS as $band) {
            $bands[$band] = ($byDayBand['weekday'][$band] ?? 0) + ($byDayBand['weekend'][$band] ?? 0);
        }

        $days = [];

        foreach (Taxonomy::DAY_TYPES as $dayType) {
            $days[$dayType] = array_sum($byDayBand[$dayType] ?? []);
        }

        $genders = [
            'M' => array_sum($byGenderAge['matrix']['M'] ?? []),
            'F' => array_sum($byGenderAge['matrix']['F'] ?? []),
        ];

        $segments = [];

        foreach ($byGenderAge['matrix'] ?? [] as $gender => $ages) {
            foreach ($ages as $age => $value) {
                $segments[$gender.'|'.$age] = $value;
            }
        }

        $topSegment = $this->topKey($segments);
        [$topGender, $topAge] = $topSegment ? explode('|', $topSegment) : [null, null];

        return [
            'covered' => true,
            'items' => array_values(array_filter([
                $this->habit('time_band', '상권 결제 발생 시간대', $bands, Taxonomy::TIME_BAND_LABELS),
                $this->habit('day_type', '상권 결제 발생 요일', $days, Taxonomy::DAY_TYPE_LABELS),
                $this->habit('gender', '상권 결제 성별', $genders, Taxonomy::GENDER_LABELS),
                $topGender ? [
                    'key' => 'gender_age',
                    'title' => '상권 결제 남녀 연령대별',
                    'top' => (Taxonomy::AGE_LABELS[$topAge] ?? $topAge).' '.(Taxonomy::GENDER_LABELS[$topGender] ?? ''),
                    'breakdown' => $this->breakdown($segments, function (string $key) {
                        [$g, $a] = explode('|', $key);

                        return (Taxonomy::AGE_LABELS[$a] ?? $a).' '.(Taxonomy::GENDER_LABELS[$g] ?? '');
                    }),
                ] : null,
            ])),
        ];
    }

    /**
     * 매장 탭. 그린 상권 안의 점포를 대분류 묶음으로 거르고 페이지를 넘긴다.
     *
     * @param  array<int, array{0: float, 1: float}>  $ring
     */
    public function stores(array $ring, ?string $group = null, string $keyword = '', int $page = 1, int $perPage = 20): array
    {
        $rows = $this->storesInRing($ring, [
            'store_id', 'name', 'branch_name', 'small_name', 'middle_name', 'large_name',
            'large_code', 'brand', 'brand_source', 'road_address',
        ]);

        $counts = ['all' => $rows->count()];

        foreach (StoreSectors::GROUPS as $key => [, $largeCodes]) {
            $counts[$key] = $rows->whereIn('large_code', $largeCodes)->count();
        }

        $filtered = $rows;

        if ($group && isset(StoreSectors::GROUPS[$group])) {
            $filtered = $filtered->whereIn('large_code', StoreSectors::GROUPS[$group][1]);
        }

        if ($keyword !== '') {
            $filtered = $filtered->filter(
                fn ($row) => mb_stripos((string) $row->name, $keyword) !== false
            );
        }

        $filtered = $filtered->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)->values();

        $total = $filtered->count();
        $pages = max(1, (int) ceil($total / $perPage));
        $page = max(1, min($page, $pages));

        $items = $filtered
            ->slice(($page - 1) * $perPage, $perPage)
            ->map(fn ($row) => [
                'id' => $row->store_id,
                'name' => trim($row->name.' '.($row->branch_name ?? '')),
                'industry' => $row->small_name ?: ($row->middle_name ?: $row->large_name),
                'large' => $row->large_name,
                'brand' => $row->brand_source === 'dictionary' ? $row->brand : null,
                'address' => $row->road_address,
                'lat' => $row->lat !== null ? (float) $row->lat : null,
                'lng' => $row->lng !== null ? (float) $row->lng : null,
            ])
            ->values()
            ->all();

        return [
            'counts' => $counts,
            'items' => $items,
            'page' => $page,
            'pages' => $pages,
            'total' => $total,
        ];
    }

    /**
     * 주거인구 탭.
     *
     * @param  array<string, float>  $weights
     */
    public function residence(array $weights, Period $period): array
    {
        $resident = $this->stats->residentByGenderAge($weights, $period);
        $households = $this->stats->householdsByType($weights, $period);
        $coverage = $this->stats->coverage($weights, $period);

        $segments = [];

        foreach ($resident['matrix'] as $gender => $ages) {
            foreach ($ages as $age => $value) {
                $segments[$gender.'|'.$age] = $value;
            }
        }

        $top = $this->topKey($segments);
        [$topGender, $topAge] = $top ? explode('|', $top) : [null, null];

        $apartment = $households['by_type']['apartment'] ?? 0;

        return [
            'coverage' => $coverage,
            'resident' => $resident + [
                'top_label' => $topGender
                    ? (Taxonomy::AGE_LABELS[$topAge] ?? $topAge).' '.(Taxonomy::GENDER_LABELS[$topGender] ?? '')
                    : null,
            ],
            'households' => $households + [
                'apartment' => $apartment,
                'apartment_share' => $households['total'] > 0
                    ? round($apartment / $households['total'] * 100, 1)
                    : 0.0,
                'move_ins' => $this->stats->upcomingMoveIns($weights),
            ],
        ];
    }

    /**
     * 그린 상권을 "행정동 코드 → 가중치" 로 바꾼다.
     *
     * @param  array<int, array{0: float, 1: float}>  $ring
     * @return array{resolved: Collection, weights: array<string, float>}
     */
    public function resolve(array $ring): array
    {
        $resolved = $this->resolver->fromPolygon($ring);

        return ['resolved' => $resolved, 'weights' => $this->resolver->weightMap($resolved)];
    }

    /** 상권 이름을 가장 크게 걸치는 행정동에서 짓는다. */
    public function nameFor(Collection $resolved): string
    {
        $first = $resolved->first();

        if (! $first) {
            return '새 상권';
        }

        $region = $first['region'];

        return "{$region->sigungu_name} {$region->dong_name} 중심 상권";
    }

    /** @param array<string, float|int> $values */
    private function habit(string $key, string $title, array $values, array $labels): ?array
    {
        $top = $this->topKey($values);

        if ($top === null) {
            return null;
        }

        return [
            'key' => $key,
            'title' => $title,
            'top' => $labels[$top] ?? $top,
            'breakdown' => $this->breakdown($values, fn (string $k) => $labels[$k] ?? $k),
        ];
    }

    /**
     * @param  array<string, float|int>  $values
     * @return array<int, array{label:string, value:int, share:float}>
     */
    private function breakdown(array $values, callable $label): array
    {
        $total = array_sum($values);

        $rows = [];

        foreach ($values as $key => $value) {
            $rows[] = [
                'label' => $label((string) $key),
                'value' => (int) round($value),
                'share' => $total > 0 ? round($value / $total * 100, 1) : 0.0,
            ];
        }

        usort($rows, fn ($a, $b) => $b['value'] <=> $a['value']);

        return $rows;
    }

    /** @param array<string, float|int> $values */
    private function topKey(array $values): ?string
    {
        $best = null;
        $bestValue = 0;

        foreach ($values as $key => $value) {
            if ($value > $bestValue) {
                $best = (string) $key;
                $bestValue = $value;
            }
        }

        return $best;
    }

    /** 그린 상권의 면적 (m²) */
    public function areaM2(array $ring): int
    {
        return (int) round(Geometry::areaM2($ring));
    }
}
