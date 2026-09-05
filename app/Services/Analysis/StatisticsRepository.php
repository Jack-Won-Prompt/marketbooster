<?php

namespace App\Services\Analysis;

use App\Support\Period;
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
