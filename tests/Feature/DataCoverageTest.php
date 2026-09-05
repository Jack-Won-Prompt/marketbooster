<?php

namespace Tests\Feature;

use App\Models\Analysis;
use App\Services\Analysis\MarketAnalyzer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\MakesMarketData;
use Tests\TestCase;

/**
 * 시도마다 확보한 출처가 달라서, 리포트는 "값이 0" 과 "수록 안 됨" 을 구분해야 한다.
 * (서울은 유동인구·카드매출까지, 경기도는 지금 점포만 있다.)
 */
class DataCoverageTest extends TestCase
{
    use MakesMarketData, RefreshDatabase;

    private function analysisAt(float $lat, float $lng, int $radius = 500): Analysis
    {
        return new Analysis([
            'title' => '테스트',
            'mode' => 'radius',
            'center_lat' => $lat,
            'center_lng' => $lng,
            'radius_m' => $radius,
            'region_codes' => [],
        ] + $this->period()->columns());
    }

    public function test_점포만_있는_지역도_리포트가_만들어진다(): void
    {
        $this->makeRegion('41150510', '의정부1동', 37.50, 127.00, sigungu: '의정부시', sido: '경기도');
        $this->seedStores('41150510', 12);

        $report = app(MarketAnalyzer::class)->analyze($this->analysisAt(37.50, 127.00));

        $coverage = $report['meta']['coverage'];

        $this->assertTrue($coverage['stores']);
        $this->assertFalse($coverage['floating']);
        $this->assertFalse($coverage['sales']);
        $this->assertFalse($coverage['resident']);

        $this->assertGreaterThan(0, $report['stores']['total']);
        $this->assertSame('한식', $report['stores']['by_middle'][0]['name']);
    }

    public function test_미수록_항목은_분석_문장을_쓰지_않는다(): void
    {
        $this->makeRegion('41150510', '의정부1동', 37.50, 127.00, sigungu: '의정부시', sido: '경기도');
        $this->seedStores('41150510');

        $report = app(MarketAnalyzer::class)->analyze($this->analysisAt(37.50, 127.00));

        // 거주인구·유동인구·매출이 전부 없으므로 문장이 하나도 나오면 안 된다.
        $this->assertSame([], $report['summary']['insights']);
        $this->assertSame([], $report['sales']['insights']);

        // 등급도 매기지 않는다. 0명을 "매우 낮음" 이라고 부르면 거짓말이 된다.
        $this->assertNull($report['summary']['levels']['resident']);
        $this->assertNull($report['summary']['levels']['lunch_floating']);
    }

    public function test_통계가_하나도_없으면_분석을_거부한다(): void
    {
        $this->makeRegion('41150510', '의정부1동', 37.50, 127.00, sigungu: '의정부시', sido: '경기도');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('경기도는 행정동 경계만 수록돼 있고');

        app(MarketAnalyzer::class)->analyze($this->analysisAt(37.50, 127.00));
    }

    public function test_일부_행정동만_수록되면_비중을_알려_준다(): void
    {
        // 두 동에 걸치는 반경인데 통계는 한 동에만 있다.
        // (서울과 경기가 함께 걸리는 반경에서 실제로 일어나는 상황)
        $this->makeRegion('11500603', '가양1동', 37.50, 126.99);
        $this->makeRegion('41150510', '의정부1동', 37.50, 127.01, sigungu: '의정부시', sido: '경기도');
        $this->seedStatistics('11500603');

        $report = app(MarketAnalyzer::class)->analyze($this->analysisAt(37.50, 127.00));

        $this->assertCount(2, $report['meta']['regions']);
        $this->assertTrue($report['meta']['coverage']['floating']);

        // 절반만 수록됐으므로 비중이 1 보다 뚜렷하게 작아야 한다.
        $ratio = $report['meta']['coverage_ratio']['floating'];

        $this->assertGreaterThan(0.0, $ratio);
        $this->assertLessThan(0.9, $ratio);
    }

    public function test_전부_수록된_범위는_비중이_1이다(): void
    {
        $this->makeRegion('11500603', '가양1동', 37.50, 127.00);
        $this->seedStatistics('11500603');

        $report = app(MarketAnalyzer::class)->analyze($this->analysisAt(37.50, 127.00));

        $this->assertSame(1.0, (float) $report['meta']['coverage_ratio']['floating']);
    }

    public function test_통계가_모두_있는_지역은_전부_수록으로_표시된다(): void
    {
        $this->makeRegion('11500603', '가양1동', 37.50, 127.00);
        $this->seedStatistics('11500603');

        $report = app(MarketAnalyzer::class)->analyze($this->analysisAt(37.50, 127.00));

        $coverage = $report['meta']['coverage'];

        foreach (['resident', 'households', 'workplace', 'floating', 'sales', 'students', 'academies'] as $key) {
            $this->assertTrue($coverage[$key], "{$key} 가 수록으로 잡히지 않았습니다.");
        }

        $this->assertNotEmpty($report['summary']['insights']);
        $this->assertNotNull($report['summary']['levels']['resident']);
    }
}
