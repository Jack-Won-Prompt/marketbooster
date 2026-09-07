<?php

namespace Tests\Feature;

use App\Services\Analysis\StatisticsRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\MakesMarketData;
use Tests\TestCase;

/**
 * 통계 조회가 인덱스를 타는지 지킨다.
 *
 * region_code 는 varchar 인데 PHP 는 "11500603" 같은 숫자 문자열을 배열 키로 쓰면
 * 정수로 바꿔 버린다. 그대로 whereIn 에 넘기면 MySQL 이 컬럼을 숫자로 변환하며
 * 비교해 인덱스를 못 타고 매번 풀스캔이 된다.
 * 실제로 card_sales 63만 행에서 질의 하나가 3초까지 걸렸다.
 */
class StatisticsQueryPlanTest extends TestCase
{
    use MakesMarketData, RefreshDatabase;

    /** @return array<int, array{query: string, bindings: array}> */
    private function record(callable $run): array
    {
        $log = [];

        DB::listen(function ($query) use (&$log) {
            $log[] = ['query' => $query->sql, 'bindings' => $query->bindings];
        });

        $run();

        return $log;
    }

    public function test_행정동코드는_문자열로_바인딩된다(): void
    {
        $this->makeRegion('11500603', '가양1동', 37.50, 127.00);
        $this->seedStatistics('11500603');

        $repo = app(StatisticsRepository::class);
        $weights = ['11500603' => 1.0];
        $period = $this->period();

        $log = $this->record(function () use ($repo, $weights, $period) {
            $repo->residentByGenderAge($weights, $period);
            $repo->salesByIndustry($weights, $period);
            $repo->salesByDayAndBand($weights, $period);
            $repo->salesByGenderAge($weights, $period);
            $repo->floatingByDayAndBand($weights, $period);
            $repo->householdsByType($weights, $period);
            $repo->workplaceByGenderAge($weights, $period);
            $repo->coverage($weights, $period);
            $repo->coverageRatio($weights, $period);
        });

        $this->assertNotEmpty($log);

        foreach ($log as $entry) {
            foreach ($entry['bindings'] as $binding) {
                if ($binding === '11500603' || $binding === 11500603) {
                    $this->assertIsString(
                        $binding,
                        '행정동코드가 정수로 바인딩되면 varchar 인덱스를 타지 못합니다: '.$entry['query']
                    );
                }
            }
        }
    }

    public function test_가중치_맵의_숫자_키를_그대로_넘기지_않는다(): void
    {
        $this->makeRegion('11500603', '가양1동', 37.50, 127.00);
        $this->seedStatistics('11500603');

        // PHP 가 키를 정수로 바꿔 놓은 상태 — 실제 RegionResolver 가 만드는 모양이다.
        $weights = ['11500603' => 1.0];

        $this->assertSame([11500603], array_keys($weights), 'PHP 가 숫자 키를 정수로 바꾸지 않았습니다.');

        $log = $this->record(fn () => app(StatisticsRepository::class)->salesByIndustry($weights, $this->period()));

        $codeBindings = collect($log)
            ->flatMap(fn (array $e) => $e['bindings'])
            ->filter(fn ($b) => (string) $b === '11500603');

        $this->assertNotEmpty($codeBindings, '행정동코드 바인딩을 찾지 못했습니다.');
        $codeBindings->each(fn ($b) => $this->assertIsString($b));
    }
}
