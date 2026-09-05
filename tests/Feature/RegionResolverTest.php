<?php

namespace Tests\Feature;

use App\Services\Analysis\RegionResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\MakesMarketData;
use Tests\TestCase;

class RegionResolverTest extends TestCase
{
    use MakesMarketData, RefreshDatabase;

    public function test_원이_행정동_안에_완전히_들어가면_면적_비율만큼만_잡는다(): void
    {
        $region = $this->makeRegion('1150000001', '가양제1동', 37.50, 127.00);

        $resolved = app(RegionResolver::class)->fromRadius(37.50, 127.00, 500);

        $this->assertCount(1, $resolved);

        // 반경 500m 원 면적(약 0.785km²) ÷ 행정동 면적
        $expected = (M_PI * 0.5 ** 2) / $region->area_km2;

        $this->assertEqualsWithDelta($expected, $resolved[0]['weight'], 0.02);
    }

    public function test_경계에_걸치면_두_행정동에_나누어_잡는다(): void
    {
        // 경도 방향으로 맞붙은 두 정사각형 행정동
        $this->makeRegion('1150000001', '가양제1동', 37.50, 126.99);
        $this->makeRegion('1150000002', '가양제2동', 37.50, 127.01);

        // 두 동의 경계선(126.00 + 0.01) 위에 중심을 둔다
        $resolved = app(RegionResolver::class)->fromRadius(37.50, 127.00, 500);

        $this->assertCount(2, $resolved);

        $weights = $resolved->pluck('weight')->all();

        // 절반씩 걸치므로 두 가중치가 비슷해야 한다
        $this->assertEqualsWithDelta($weights[0], $weights[1], 0.02);
        $this->assertGreaterThan(0, $weights[0]);
    }

    public function test_행정동을_직접_고르면_가중치는_1이다(): void
    {
        $this->makeRegion('1150000001', '가양제1동', 37.50, 127.00);
        $this->makeRegion('1150000002', '가양제2동', 37.52, 127.00);

        $resolved = app(RegionResolver::class)->fromCodes(['1150000001', '1150000002']);

        $this->assertCount(2, $resolved);
        $this->assertSame([1.0, 1.0], $resolved->pluck('weight')->all());
    }

    public function test_멀리_떨어진_행정동은_제외된다(): void
    {
        $this->makeRegion('1150000001', '가양제1동', 37.50, 127.00);
        $this->makeRegion('1150000009', '먼동', 37.90, 127.60);

        $resolved = app(RegionResolver::class)->fromRadius(37.50, 127.00, 500);

        $this->assertSame(['1150000001'], $resolved->pluck('region.code')->all());
    }
}
