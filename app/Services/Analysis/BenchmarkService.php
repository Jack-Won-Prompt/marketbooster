<?php

namespace App\Services\Analysis;

use App\Support\Period;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * "서울특별시 평균", "강서구 평균" 처럼 상위 행정구역의 행정동 1곳당 평균값을 계산한다.
 * 선택지역 합계와 나란히 놓아 상권 수준을 가늠하는 기준선이 된다.
 */
class BenchmarkService
{
    private const TTL_MINUTES = 720;

    private const VERSION_KEY = 'benchmark:version';

    /**
     * 통계가 새로 적재되면 이전 평균은 더 이상 맞지 않는다.
     * (예: 분기 누적을 일평균으로 다시 넣으면 평균이 수십 배 달라진다)
     * 캐시 키에 버전을 붙이고 적재 때마다 버전을 올려 통째로 무효화한다.
     */
    public static function invalidate(): void
    {
        Cache::forever(self::VERSION_KEY, (int) Cache::get(self::VERSION_KEY, 0) + 1);
    }

    private function version(): int
    {
        return (int) Cache::get(self::VERSION_KEY, 0);
    }

    /**
     * @return array{dong_count:int, resident:float, households:float, lunch_floating:float, evening_floating:float, workplace:float, sales_amount:float, students:float}
     */
    public function averagesForSido(string $sidoName, Period $period): array
    {
        return Cache::remember(
            "benchmark:v{$this->version()}:sido:{$sidoName}:{$period->key()}",
            now()->addMinutes(self::TTL_MINUTES),
            fn () => $this->averages('sido_name', $sidoName, $period)
        );
    }

    /**
     * @return array{dong_count:int, resident:float, households:float, lunch_floating:float, evening_floating:float, workplace:float, sales_amount:float, students:float}
     */
    public function averagesForSigungu(string $sidoName, string $sigunguName, Period $period): array
    {
        return Cache::remember(
            "benchmark:v{$this->version()}:sigungu:{$sidoName}:{$sigunguName}:{$period->key()}",
            now()->addMinutes(self::TTL_MINUTES),
            fn () => $this->averages('sigungu_name', $sigunguName, $period, $sidoName)
        );
    }

    private function averages(string $column, string $value, Period $period, ?string $sidoName = null): array
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
            'resident' => $divide($this->sum('resident_populations', 'population', $codes, $period)),
            'households' => $divide($this->sum('households', 'households', $codes, $period)),
            'lunch_floating' => $divide($this->floatingSum($codes, $period, 'lunch')),
            'evening_floating' => $divide($this->floatingSum($codes, $period, 'evening')),
            'workplace' => $divide($this->sum('workplace_populations', 'population', $codes, $period)),
            'sales_amount' => $divide($this->sum('card_sales', 'sales_amount', $codes, $period)),
            'students' => $divide($this->sum('students', 'student_count', $codes, $period)),
        ];
    }

    private function sum(string $table, string $column, array $codes, Period $period): float
    {
        return (float) DB::table($table)
            ->whereIn('region_code', $codes)
            ->where($period->filterColumn(), $period->code)
            ->sum($column);
    }

    private function floatingSum(array $codes, Period $period, string $timeBand): float
    {
        return (float) DB::table('floating_populations')
            ->whereIn('region_code', $codes)
            ->where($period->filterColumn(), $period->code)
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
        self::invalidate();
    }
}
