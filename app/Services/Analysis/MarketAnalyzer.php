<?php

namespace App\Services\Analysis;

use App\Models\Analysis;
use App\Models\DataSource;
use App\Support\Korean;
use App\Support\Period;
use App\Support\Taxonomy;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * 상권분석 리포트 본체를 만든다.
 *
 * 결과는 배열 하나(payload)로 반환되어 analyses.payload 에 저장되고,
 * 웹 리포트 화면과 PDF 가 같은 배열을 그대로 렌더링한다.
 */
class MarketAnalyzer
{
    public function __construct(
        private readonly RegionResolver $resolver,
        private readonly StatisticsRepository $stats,
        private readonly BenchmarkService $benchmarks,
        private readonly InsightWriter $insights,
    ) {}

    public function analyze(Analysis $analysis): array
    {
        $resolved = $this->resolveRegions($analysis);

        if ($resolved->isEmpty()) {
            throw new \RuntimeException('선택한 범위 안에 수록된 행정동이 없습니다.');
        }

        $weights = $this->resolver->weightMap($resolved);
        $period = $analysis->period();
        $reportDate = Carbon::now()->format('Y/m/d');
        $coverage = $this->stats->coverage($weights, $period);
        $coverageRatio = $this->stats->coverageRatio($weights, $period);

        if (! in_array(true, $coverage, true)) {
            $sidoName = $resolved->first()['region']->sido_name;

            throw new \RuntimeException(sprintf(
                '%s 행정동 경계만 수록돼 있고 %s 통계가 아직 없습니다.',
                Korean::withJosa($sidoName, '은/는'),
                $period->label()
            ));
        }

        $resident = $this->stats->residentByGenderAge($weights, $period);
        $households = $this->stats->householdsByType($weights, $period);
        $workplace = $this->stats->workplaceByGenderAge($weights, $period);
        $floatingByDay = $this->stats->floatingByDayAndBand($weights, $period);
        $floatingByGenderAge = $this->stats->floatingByGenderAge($weights, $period);

        $summary = $this->buildSummary($resolved, $period, $resident, $households, $workplace, $floatingByDay, $coverage);
        $sales = $this->buildSales($weights, $period);
        $stores = $this->stats->storeProfile($weights);
        $education = $this->buildEducation($weights, $period);

        return [
            'meta' => $this->buildMeta($analysis, $resolved, $period) + [
                'coverage' => $coverage,
                'coverage_ratio' => $coverageRatio,
            ],
            'summary' => $summary + ['insights' => $this->insights->population($summary, $reportDate)],
            'resident' => $resident,
            'households' => $households + [
                'move_ins' => $this->stats->upcomingMoveIns($weights),
            ],
            'workplace' => $workplace,
            'floating' => [
                'by_day_band' => $floatingByDay,
                'by_gender_age' => $floatingByGenderAge,
                'peak' => $this->peakOf($floatingByDay),
            ],
            'sales' => $sales + [
                'insights' => $coverage['sales'] ? $this->insights->sales($sales, $period->label()) : [],
            ],
            'stores' => $stores + ['insights' => $this->insights->stores($stores)],
            'education' => $education + [
                'insights' => ($coverage['students'] || $coverage['academies'])
                    ? $this->insights->education($education, $summary, $reportDate)
                    : [],
            ],
            'sources' => $this->buildSources(),
        ];
    }

    /**
     * @return Collection<int, array{region: \App\Models\Region, weight: float, distance_km: float}>
     */
    public function resolveRegions(Analysis $analysis): Collection
    {
        // 지도에 직접 그린 상권. 원·사각형·다각형 모두 폴리곤 하나로 다룬다.
        if ($analysis->mode === 'polygon' && $analysis->shape_ring) {
            return $this->resolver->fromPolygon($analysis->shape_ring);
        }

        if ($analysis->mode === 'radius' && $analysis->center_lat && $analysis->center_lng) {
            return $this->resolver->fromRadius(
                (float) $analysis->center_lat,
                (float) $analysis->center_lng,
                (int) $analysis->radius_m
            );
        }

        return $this->resolver->fromCodes($analysis->region_codes ?? []);
    }

    private function buildMeta(Analysis $analysis, Collection $resolved, Period $period): array
    {
        $first = $resolved->first()['region'];

        return [
            'title' => $analysis->title,
            'generated_at' => Carbon::now()->format('Y. m. d'),
            'generated_at_full' => Carbon::now()->format('Y-m-d H:i'),
            'period_type' => $period->type,
            'period_code' => $period->code,
            'base_ym' => $period->isQuarter() ? '' : $period->code,
            'base_yq' => $period->isQuarter() ? $period->code : '',
            'base_label' => $period->label(),
            'mode' => $analysis->mode,
            'scope_label' => $analysis->rangeLabel(),
            'address' => $analysis->address,
            'center' => [
                'lat' => $analysis->center_lat ? (float) $analysis->center_lat : (float) $first->lat,
                'lng' => $analysis->center_lng ? (float) $analysis->center_lng : (float) $first->lng,
            ],
            'radius_m' => $analysis->radius_m,
            'sido_name' => $first->sido_name,
            'sigungu_name' => $first->sigungu_name,
            'regions' => $resolved->map(fn (array $item) => [
                'code' => $item['region']->code,
                'name' => $item['region']->full_name,
                'lat' => (float) $item['region']->lat,
                'lng' => (float) $item['region']->lng,
                'weight' => round($item['weight'], 3),
                'distance_km' => $item['distance_km'],
            ])->all(),
        ];
    }

    private function buildSummary(
        Collection $resolved,
        Period $period,
        array $resident,
        array $households,
        array $workplace,
        array $floatingByDay,
        array $coverage
    ): array {
        $first = $resolved->first()['region'];
        $sido = $this->benchmarks->averagesForSido($first->sido_name, $period);
        $sigungu = $this->benchmarks->averagesForSigungu($first->sido_name, $first->sigungu_name, $period);

        $selected = [
            'resident' => $resident['total'],
            'households' => $households['total'],
            'lunch_floating' => $floatingByDay['weekday']['lunch'] ?? 0,
            'evening_floating' => $floatingByDay['weekday']['evening'] ?? 0,
            'workplace' => $workplace['total'],
        ];

        // 요약 항목별로 어떤 원천 데이터에 기대는지. 미수록이면 등급을 매기지 않는다.
        $sources = [
            'resident' => 'resident',
            'households' => 'households',
            'lunch_floating' => 'floating',
            'evening_floating' => 'floating',
            'workplace' => 'workplace',
        ];

        $covered = [];
        $levels = [];

        foreach ($selected as $key => $value) {
            $covered[$key] = (bool) ($coverage[$sources[$key]] ?? false);
            $levels[$key] = $covered[$key]
                ? Taxonomy::level((float) $value, (float) ($sido[$key] ?? 0))
                : null;
        }

        return [
            'selected' => $selected,
            'sido' => $sido,
            'sigungu' => $sigungu,
            'sido_name' => $first->sido_name,
            'sigungu_name' => $first->sigungu_name,
            'levels' => $levels,
            'coverage' => $covered,
        ];
    }

    private function buildSales(array $weights, Period $period): array
    {
        $byIndustry = $this->stats->salesByIndustry($weights, $period);
        $totalAmount = array_sum(array_column($byIndustry, 'amount'));
        $totalCount = array_sum(array_column($byIndustry, 'count'));

        $byIndustry = array_map(function (array $item) use ($totalAmount) {
            $item['share'] = $totalAmount > 0 ? round($item['amount'] / $totalAmount * 100, 1) : 0.0;

            return $item;
        }, $byIndustry);

        $groups = [];

        foreach ($byIndustry as $item) {
            $group = $item['group'] ?: '기타';
            $groups[$group] ??= ['name' => $group, 'amount' => 0, 'count' => 0];
            $groups[$group]['amount'] += $item['amount'];
            $groups[$group]['count'] += $item['count'];
        }

        $byGroup = array_map(function (array $item) use ($totalAmount) {
            $item['share'] = $totalAmount > 0 ? round($item['amount'] / $totalAmount * 100, 1) : 0.0;

            return $item;
        }, array_values($groups));

        usort($byGroup, fn ($a, $b) => $b['amount'] <=> $a['amount']);

        $byDayBand = $this->stats->salesByDayAndBand($weights, $period);
        $byGenderAge = $this->stats->salesByGenderAge($weights, $period);

        return [
            'total_amount' => $totalAmount,
            'total_count' => $totalCount,
            'avg_ticket' => $totalCount > 0 ? (int) round($totalAmount / $totalCount) : 0,
            'by_industry' => array_slice($byIndustry, 0, 15),
            'by_group' => $byGroup,
            'by_day_band' => $byDayBand,
            'by_gender_age' => $byGenderAge,
            'peak' => $this->peakOf($byDayBand),
            'top_segment' => $this->topSegment($byGenderAge),
        ];
    }

    private function buildEducation(array $weights, Period $period): array
    {
        return [
            'students' => $this->stats->studentsByType($weights, $period),
            'academies' => $this->stats->academies($weights, $period),
        ];
    }

    /**
     * @return array<int, array{label:string, provider:string, base_label:string, category:string}>
     */
    private function buildSources(): array
    {
        return DataSource::orderBy('sort_order')->orderBy('id')->get()
            ->map(fn (DataSource $source) => [
                'label' => $source->label,
                'provider' => $source->provider,
                'base_label' => $source->base_label ?? '-',
                'category' => $source->category,
            ])->all();
    }

    /** 요일 × 시간대 표에서 가장 값이 큰 칸 */
    private function peakOf(array $byDayBand): ?array
    {
        $best = null;

        foreach ($byDayBand as $dayType => $bands) {
            foreach ($bands as $band => $value) {
                if ($best === null || $value > $best['value']) {
                    $best = ['day_type' => $dayType, 'time_band' => $band, 'value' => $value];
                }
            }
        }

        return ($best && $best['value'] > 0) ? $best : null;
    }

    /** 성 × 연령 행렬에서 비중이 가장 큰 구간 */
    private function topSegment(array $matrixData): ?array
    {
        $total = $matrixData['total'] ?? 0;

        if ($total <= 0) {
            return null;
        }

        $best = null;

        foreach ($matrixData['matrix'] as $gender => $bands) {
            foreach ($bands as $age => $value) {
                if ($best === null || $value > $best['value']) {
                    $best = ['gender' => $gender, 'age_band' => $age, 'value' => $value];
                }
            }
        }

        if ($best === null) {
            return null;
        }

        $best['share'] = round($best['value'] / $total * 100, 1);

        return $best;
    }
}
