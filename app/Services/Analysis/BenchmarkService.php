<?php

namespace App\Services\Analysis;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * "서울특별시 평균", "강서구 평균" 처럼 상위 행정구역의 행정동 1곳당 평균값을 계산한다.
 * 선택지역 합계와 나란히 놓아 상권 수준을 가늠하는 기준선이 된다.
 */
class BenchmarkService
{
    private const TTL_MINUTES = 720;

    /**
     * @return array{resident:float, households:float, lunch_floating:float, evening_floating:float, workplace:float, sales_amount:float}
     */
    public function averagesForSido(string $sidoName, string $baseYm): array
    {
        return Cache::remember(
            "benchmark:sido:{$sidoName}:{$baseYm}",
            now()->addMinutes(self::TTL_MINUTES),
            fn () => $this->averages('sido_name', $sidoName, $baseYm)
        );
    }

    /**
     * @return array{resident:float, households:float, lunch_floating:float, evening_floating:float, workplace:float, sales_amount:float}
     */
    public function averagesForSigungu(string $sidoName, string $sigunguName, string $baseYm): array
    {
        return Cache::remember(
            "benchmark:sigungu:{$sidoName}:{$sigunguName}:{$baseYm}",
            now()->addMinutes(self::TTL_MINUTES),
            fn () => $this->averages('sigungu_name', $sigunguName, $baseYm, $sidoName)
        );
    }

    private function averages(string $column, string $value, string $baseYm, ?string $sidoName = null): array
    {
        $regionQuery = DB::table('regions')->where($column, $value);

        if ($sidoName !== null) {
            $regionQuery->where('sido_name', $sidoName);
        }

        $codes = $regionQuery->pluck('code')->all();
        $dongCount = count($codes);

        if ($dongCount === 0) {
            return $this->emptyAverages();
        }

        $divide = fn ($total) => round(((float) $total) / $dongCount, 1);

        return [
            'dong_count' => $dongCount,
            'resident' => $divide($this->sum('resident_populations', 'population', $codes, $baseYm)),
            'households' => $divide($this->sum('households', 'households', $codes, $baseYm)),
            'lunch_floating' => $divide($this->floatingSum($codes, $baseYm, 'lunch')),
            'evening_floating' => $divide($this->floatingSum($codes, $baseYm, 'evening')),
            'workplace' => $divide($this->sum('workplace_populations', 'population', $codes, $baseYm)),
            'sales_amount' => $divide($this->sum('card_sales', 'sales_amount', $codes, $baseYm)),
            'students' => $divide($this->sum('students', 'student_count', $codes, $baseYm)),
        ];
    }

    private function sum(string $table, string $column, array $codes, string $baseYm): float
    {
        return (float) DB::table($table)
            ->whereIn('region_code', $codes)
            ->where('base_ym', $baseYm)
            ->sum($column);
    }

    private function floatingSum(array $codes, string $baseYm, string $timeBand): float
    {
        return (float) DB::table('floating_populations')
            ->whereIn('region_code', $codes)
            ->where('base_ym', $baseYm)
            ->where('day_type', 'weekday')
            ->where('time_band', $timeBand)
            ->sum('population');
    }

    private function emptyAverages(): array
    {
        return [
            'dong_count' => 0,
            'resident' => 0.0,
            'households' => 0.0,
            'lunch_floating' => 0.0,
            'evening_floating' => 0.0,
            'workplace' => 0.0,
            'sales_amount' => 0.0,
            'students' => 0.0,
        ];
    }

    public function flush(): void
    {
        Cache::flush();
    }
}
