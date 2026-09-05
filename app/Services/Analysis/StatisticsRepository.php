<?php

namespace App\Services\Analysis;

use App\Support\Period;
use App\Support\StoreSectors;
use App\Support\Taxonomy;
use Illuminate\Support\Facades\DB;

/**
 * 행정동 통계에 가중치를 적용해 합산하는 조회 계층.
 *
 * 모든 메서드는 "행정동코드 => 가중치" 맵을 받아
 *   SUM(값 × 가중치)
 * 를 계산한다. 가중치는 RegionResolver 가 만든 면적 겹침 비율이다.
 */
class StatisticsRepository
{
    /**
     * @param  array<string, float>  $weights
     * @return array{matrix: array<string, array<string, int>>, male: int, female: int, total: int}
     */
    public function residentByGenderAge(array $weights, Period $period): array
    {
        $rows = DB::table('resident_populations')
            ->select('region_code', 'gender', 'age_band', DB::raw('SUM(population) AS value'))
            ->whereIn('region_code', array_keys($weights))
            ->where($period->filterColumn(), $period->code)
            ->groupBy('region_code', 'gender', 'age_band')
            ->get();

        return $this->genderAgeMatrix($rows, $weights, Taxonomy::AGE_BANDS);
    }

    /**
     * @param  array<string, float>  $weights
     * @return array{matrix: array<string, array<string, int>>, male: int, female: int, total: int}
     */
    public function workplaceByGenderAge(array $weights, Period $period): array
    {
        $rows = DB::table('workplace_populations')
            ->select('region_code', 'gender', 'age_band', DB::raw('SUM(population) AS value'))
            ->whereIn('region_code', array_keys($weights))
            ->where($period->filterColumn(), $period->code)
            ->groupBy('region_code', 'gender', 'age_band')
            ->get();

        return $this->genderAgeMatrix($rows, $weights, Taxonomy::WORK_AGE_BANDS);
    }

    /**
     * @param  array<string, float>  $weights
     * @return array{by_type: array<string, int>, total: int}
     */
    public function householdsByType(array $weights, Period $period): array
    {
        $rows = DB::table('households')
            ->select('region_code', 'housing_type', DB::raw('SUM(households) AS value'))
            ->whereIn('region_code', array_keys($weights))
            ->where($period->filterColumn(), $period->code)
            ->groupBy('region_code', 'housing_type')
            ->get();

        $byType = array_fill_keys(Taxonomy::HOUSING_TYPES, 0.0);

        foreach ($rows as $row) {
            if (! array_key_exists($row->housing_type, $byType)) {
                continue;
            }

            $byType[$row->housing_type] += $row->value * ($weights[$row->region_code] ?? 0);
        }

        $byType = array_map(fn ($v) => (int) round($v), $byType);

        return ['by_type' => $byType, 'total' => array_sum($byType)];
    }

    /**
     * 요일 × 시간대 유동인구.
     *
     * @param  array<string, float>  $weights
     * @return array<string, array<string, int>>
     */
    public function floatingByDayAndBand(array $weights, Period $period): array
    {
        $rows = DB::table('floating_populations')
            ->select('region_code', 'day_type', 'time_band', DB::raw('SUM(population) AS value'))
            ->whereIn('region_code', array_keys($weights))
            ->where($period->filterColumn(), $period->code)
            ->groupBy('region_code', 'day_type', 'time_band')
            ->get();

        $result = [];

        foreach (Taxonomy::DAY_TYPES as $dayType) {
            $result[$dayType] = array_fill_keys(Taxonomy::TIME_BANDS, 0.0);
        }

        foreach ($rows as $row) {
            if (! isset($result[$row->day_type][$row->time_band])) {
                continue;
            }

            $result[$row->day_type][$row->time_band] += $row->value * ($weights[$row->region_code] ?? 0);
        }

        foreach ($result as $dayType => $bands) {
            $result[$dayType] = array_map(fn ($v) => (int) round($v), $bands);
        }

        return $result;
    }

    /**
     * @param  array<string, float>  $weights
     * @return array{matrix: array<string, array<string, int>>, male: int, female: int, total: int}
     */
    public function floatingByGenderAge(array $weights, Period $period, string $dayType = 'weekday'): array
    {
        $rows = DB::table('floating_populations')
            ->select('region_code', 'gender', 'age_band', DB::raw('SUM(population) AS value'))
            ->whereIn('region_code', array_keys($weights))
            ->where($period->filterColumn(), $period->code)
            ->where('day_type', $dayType)
            ->groupBy('region_code', 'gender', 'age_band')
            ->get();

        return $this->genderAgeMatrix($rows, $weights, Taxonomy::AGE_BANDS);
    }

    /**
     * 업종별 카드매출.
     *
     * @param  array<string, float>  $weights
     * @return array<int, array{code:string, name:string, group:string, amount:int, count:int}>
     */
    public function salesByIndustry(array $weights, Period $period): array
    {
        $rows = DB::table('card_sales')
            ->leftJoin('industries', 'industries.code', '=', 'card_sales.industry_code')
            ->select(
                'card_sales.region_code',
                'card_sales.industry_code',
                'card_sales.industry_name',
                'card_sales.day_type',
                'industries.group_name',
                DB::raw('SUM(card_sales.sales_amount) AS amount'),
                DB::raw('SUM(card_sales.sales_count) AS cnt')
            )
            ->whereIn('card_sales.region_code', array_keys($weights))
            ->where('card_sales.'.$period->filterColumn(), $period->code)
            ->groupBy('card_sales.region_code', 'card_sales.industry_code', 'card_sales.industry_name', 'card_sales.day_type', 'industries.group_name')
            ->get();

        /*
         * 저장값은 "그 요일 구분의 하루 평균" 이다.
         * 평일 하루치와 주말 하루치를 그냥 더하면 이틀치가 되므로,
         * 기간 안의 평일·주말 일수로 가중평균해 하루치로 만든다.
         */
        $dayCounts = $period->dayCounts();
        $totalDays = max(1, $dayCounts['weekday'] + $dayCounts['weekend']);

        $acc = [];

        foreach ($rows as $row) {
            $dayWeight = ($dayCounts[$row->day_type] ?? 0) / $totalDays;
            $weight = ($weights[$row->region_code] ?? 0) * $dayWeight;
            $code = $row->industry_code;

            $acc[$code] ??= [
                'code' => $code,
                'name' => $row->industry_name,
                'group' => $row->group_name ?? '기타',
                'amount' => 0.0,
                'count' => 0.0,
            ];

            $acc[$code]['amount'] += $row->amount * $weight;
            $acc[$code]['count'] += $row->cnt * $weight;
        }

        $result = array_map(function (array $item) {
            $item['amount'] = (int) round($item['amount']);
            $item['count'] = (int) round($item['count']);

            return $item;
        }, array_values($acc));

        usort($result, fn ($a, $b) => $b['amount'] <=> $a['amount']);

        return $result;
    }

    /**
     * 요일 × 시간대 카드매출.
     *
     * @param  array<string, float>  $weights
     * @return array<string, array<string, int>>
     */
    public function salesByDayAndBand(array $weights, Period $period): array
    {
        $rows = DB::table('card_sales')
            ->select('region_code', 'day_type', 'time_band', DB::raw('SUM(sales_amount) AS value'))
            ->whereIn('region_code', array_keys($weights))
            ->where($period->filterColumn(), $period->code)
            ->groupBy('region_code', 'day_type', 'time_band')
            ->get();

        $result = [];

        foreach (Taxonomy::DAY_TYPES as $dayType) {
            $result[$dayType] = array_fill_keys(Taxonomy::TIME_BANDS, 0.0);
        }

        foreach ($rows as $row) {
            if (! isset($result[$row->day_type][$row->time_band])) {
                continue;
            }

            $result[$row->day_type][$row->time_band] += $row->value * ($weights[$row->region_code] ?? 0);
        }

        foreach ($result as $dayType => $bands) {
            $result[$dayType] = array_map(fn ($v) => (int) round($v), $bands);
        }

        return $result;
    }

    /**
     * 성 × 연령 카드매출.
     *
     * @param  array<string, float>  $weights
     * @return array{matrix: array<string, array<string, int>>, male: int, female: int, total: int}
     */
    public function salesByGenderAge(array $weights, Period $period): array
    {
        $rows = DB::table('card_sales_demographics')
            ->select('region_code', 'gender', 'age_band', DB::raw('SUM(sales_amount) AS value'))
            ->whereIn('region_code', array_keys($weights))
            ->where($period->filterColumn(), $period->code)
            ->groupBy('region_code', 'gender', 'age_band')
            ->get();

        return $this->genderAgeMatrix($rows, $weights, Taxonomy::AGE_BANDS);
    }

    /**
     * @param  array<string, float>  $weights
     * @return array{by_type: array<string, int>, total: int}
     */
    public function studentsByType(array $weights, Period $period): array
    {
        $rows = DB::table('students')
            ->select('region_code', 'school_type', DB::raw('SUM(student_count) AS value'))
            ->whereIn('region_code', array_keys($weights))
            ->where($period->filterColumn(), $period->code)
            ->groupBy('region_code', 'school_type')
            ->get();

        $byType = array_fill_keys(Taxonomy::SCHOOL_TYPES, 0.0);

        foreach ($rows as $row) {
            if (! array_key_exists($row->school_type, $byType)) {
                continue;
            }

            $byType[$row->school_type] += $row->value * ($weights[$row->region_code] ?? 0);
        }

        $byType = array_map(fn ($v) => (int) round($v), $byType);

        return ['by_type' => $byType, 'total' => array_sum($byType)];
    }

    /**
     * @param  array<string, float>  $weights
     * @return array{by_category: array<string, int>, by_industry: array<int, array{name:string, category:string, count:int}>, total: int}
     */
    public function academies(array $weights, Period $period): array
    {
        $rows = DB::table('academies')
            ->select('region_code', 'category', 'industry_name', DB::raw('SUM(academy_count) AS value'))
            ->whereIn('region_code', array_keys($weights))
            ->where($period->filterColumn(), $period->code)
            ->groupBy('region_code', 'category', 'industry_name')
            ->get();

        $byCategory = array_fill_keys(array_keys(Taxonomy::ACADEMY_CATEGORIES), 0.0);
        $byIndustry = [];

        foreach ($rows as $row) {
            $weighted = $row->value * ($weights[$row->region_code] ?? 0);

            if (array_key_exists($row->category, $byCategory)) {
                $byCategory[$row->category] += $weighted;
            }

            $key = $row->industry_name;
            $byIndustry[$key] ??= ['name' => $key, 'category' => $row->category, 'count' => 0.0];
            $byIndustry[$key]['count'] += $weighted;
        }

        $byCategory = array_map(fn ($v) => (int) round($v), $byCategory);

        $byIndustry = array_map(function (array $item) {
            $item['count'] = (int) round($item['count']);

            return $item;
        }, array_values($byIndustry));

        $byIndustry = array_values(array_filter($byIndustry, fn ($i) => $i['count'] > 0));
        usort($byIndustry, fn ($a, $b) => $b['count'] <=> $a['count']);

        return [
            'by_category' => $byCategory,
            'by_industry' => $byIndustry,
            'total' => array_sum($byCategory),
        ];
    }

    /**
     * 3년 이내 입주예정 아파트.
     *
     * @param  array<string, float>  $weights
     * @return array<int, array{complex_name:string, households:int, move_in_ym:string}>
     */
    public function upcomingMoveIns(array $weights, int $limit = 10): array
    {
        $until = now()->addYears(3)->format('Ym');
        $from = now()->format('Ym');

        return DB::table('apartment_move_ins')
            ->select('complex_name', 'households', 'move_in_ym')
            ->whereIn('region_code', array_keys($weights))
            ->whereBetween('move_in_ym', [$from, $until])
            ->orderByDesc('move_in_ym')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();
    }

    /**
     * 지금 선택한 범위 · 기간에 실제로 수록된 통계가 무엇인지 확인한다.
     *
     * 수치가 0인 것과 데이터가 아예 없는 것은 전혀 다른 이야기라,
     * 리포트가 "0명" 대신 "미수록" 이라고 말할 수 있게 이 결과를 쓴다.
     * (예: 경기도는 행정동 · 점포는 있지만 유동인구 · 카드매출 공개 출처가 없다.)
     *
     * @param  array<string, float>  $weights
     * @return array<string, bool>
     */
    public function coverage(array $weights, Period $period): array
    {
        $codes = array_keys($weights);

        $tables = [
            'resident' => 'resident_populations',
            'households' => 'households',
            'workplace' => 'workplace_populations',
            'floating' => 'floating_populations',
            'sales' => 'card_sales',
            'students' => 'students',
            'academies' => 'academies',
        ];

        $coverage = [];

        foreach ($tables as $key => $table) {
            $coverage[$key] = DB::table($table)
                ->whereIn('region_code', $codes)
                ->where($period->filterColumn(), $period->code)
                ->exists();
        }

        // 점포는 시점 개념이 없는 스냅샷이라 기간으로 거르지 않는다.
        $coverage['stores'] = DB::table('stores')->whereIn('region_code', $codes)->exists();

        return $coverage;
    }

    /**
     * 점포 프로필 — 분야 · 업종 · 프랜차이즈를 한 번에 집계한다.
     *
     * 카드매출이 없는 지역에서 상권의 성격을 읽을 수 있는 거의 유일한 실측 자료라,
     * 분야(식당·카페/디저트 …)와 프랜차이즈 브랜드까지 함께 낸다.
     * 반경 분석에서는 행정동 겹침 비율만큼 안분한다.
     *
     * @param  array<string, float>  $weights
     */
    public function storeProfile(array $weights, int $limit = 15): array
    {
        $rows = DB::table('stores')
            ->select(
                'region_code', 'sector', 'large_name', 'middle_name', 'brand',
                DB::raw('COUNT(*) AS cnt')
            )
            ->whereIn('region_code', array_keys($weights))
            ->groupBy('region_code', 'sector', 'large_name', 'middle_name', 'brand')
            ->get();

        $bySector = [];
        $byLarge = [];
        $byMiddle = [];
        $brands = [];
        $total = 0.0;
        $franchiseTotal = 0.0;

        foreach ($rows as $row) {
            $weighted = $row->cnt * ($weights[$row->region_code] ?? 0);
            $total += $weighted;

            $sector = $row->sector ?: StoreSectors::UNKNOWN;
            $large = $row->large_name ?: '기타';
            $middle = $row->middle_name ?: $large;

            $bySector[$sector] ??= ['code' => $sector, 'name' => StoreSectors::label($sector), 'count' => 0.0, 'franchises' => 0.0];
            $bySector[$sector]['count'] += $weighted;

            $byLarge[$large] ??= ['name' => $large, 'count' => 0.0];
            $byLarge[$large]['count'] += $weighted;

            $byMiddle[$middle] ??= ['name' => $middle, 'large' => $large, 'count' => 0.0];
            $byMiddle[$middle]['count'] += $weighted;

            if ($row->brand) {
                $franchiseTotal += $weighted;
                $bySector[$sector]['franchises'] += $weighted;

                $key = $row->brand;
                $brands[$key] ??= [
                    'name' => $key,
                    'sector' => $sector,
                    'sector_name' => StoreSectors::label($sector),
                    'count' => 0.0,
                ];
                $brands[$key]['count'] += $weighted;
            }
        }

        $rank = function (array $items, ?int $take) use ($total) {
            $items = array_map(function (array $item) use ($total) {
                $item['count'] = (int) round($item['count']);
                $item['share'] = $total > 0 ? round($item['count'] / $total * 100, 1) : 0.0;

                if (isset($item['franchises'])) {
                    $item['franchises'] = (int) round($item['franchises']);
                    $item['franchise_share'] = $item['count'] > 0
                        ? round($item['franchises'] / $item['count'] * 100, 1)
                        : 0.0;
                }

                return $item;
            }, array_values($items));

            $items = array_values(array_filter($items, fn ($i) => $i['count'] > 0));
            usort($items, fn ($a, $b) => $b['count'] <=> $a['count']);

            return $take ? array_slice($items, 0, $take) : $items;
        };

        $sectors = $rank($bySector, null);

        // 분야별 대표 브랜드 (분석서에서 "디저트는 어떤 브랜드가 많은가" 를 보게)
        $rankedBrands = $rank($brands, null);
        $brandsBySector = [];

        foreach ($rankedBrands as $brand) {
            $brandsBySector[$brand['sector']][] = $brand;
        }

        return [
            'total' => (int) round($total),
            'franchise_total' => (int) round($franchiseTotal),
            'franchise_share' => $total > 0 ? round($franchiseTotal / $total * 100, 1) : 0.0,
            'by_sector' => $sectors,
            'by_large' => $rank($byLarge, $limit),
            'by_middle' => $rank($byMiddle, $limit),
            'brands' => array_slice($rankedBrands, 0, 40),
            'brands_by_sector' => array_map(
                fn (array $list) => array_slice($list, 0, 10),
                $brandsBySector
            ),
        ];
    }

    /**
     * 데이터가 존재하는 가장 최근 기준 기간.
     * 분기 데이터가 있으면 그쪽을 우선한다 (서울시 상권분석서비스가 분기 단위라 더 정밀하다).
     */
    public function latestPeriod(string $table = 'floating_populations'): ?Period
    {
        $quarter = DB::table($table)->where('base_yq', '!=', '')->max('base_yq');

        if ($quarter) {
            return Period::quarter($quarter);
        }

        $month = DB::table($table)->where('base_ym', '!=', '')->max('base_ym');

        return $month ? Period::month($month) : null;
    }

    /** 선택 가능한 기간 목록 (최신순) */
    public function availablePeriods(string $table = 'floating_populations'): array
    {
        $quarters = DB::table($table)->where('base_yq', '!=', '')->distinct()->orderByDesc('base_yq')->pluck('base_yq');
        $months = DB::table($table)->where('base_ym', '!=', '')->distinct()->orderByDesc('base_ym')->pluck('base_ym');

        return array_merge(
            $quarters->map(fn ($c) => Period::quarter($c))->all(),
            $months->map(fn ($c) => Period::month($c))->all(),
        );
    }

    /**
     * 성 × 연령 행렬 공통 조립.
     *
     * @param  \Illuminate\Support\Collection<int, object>  $rows
     * @param  array<string, float>  $weights
     * @param  array<int, string>  $ageBands
     */
    private function genderAgeMatrix($rows, array $weights, array $ageBands): array
    {
        $matrix = [];

        foreach (Taxonomy::GENDERS as $gender) {
            $matrix[$gender] = array_fill_keys($ageBands, 0.0);
        }

        foreach ($rows as $row) {
            if (! isset($matrix[$row->gender][$row->age_band])) {
                continue;
            }

            $matrix[$row->gender][$row->age_band] += $row->value * ($weights[$row->region_code] ?? 0);
        }

        foreach ($matrix as $gender => $bands) {
            $matrix[$gender] = array_map(fn ($v) => (int) round($v), $bands);
        }

        $male = array_sum($matrix['M']);
        $female = array_sum($matrix['F']);

        return [
            'matrix' => $matrix,
            'male' => $male,
            'female' => $female,
            'total' => $male + $female,
        ];
    }
}
