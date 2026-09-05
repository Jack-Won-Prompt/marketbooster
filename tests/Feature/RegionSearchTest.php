<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\MakesMarketData;
use Tests\TestCase;

class RegionSearchTest extends TestCase
{
    use MakesMarketData, RefreshDatabase;

    private function user(): User
    {
        return User::factory()->create();
    }

    public function test_행정동을_이름으로_찾는다(): void
    {
        $this->makeRegion('41150510', '의정부1동', 37.74, 127.04, sigungu: '의정부시', sido: '경기도');
        $this->makeRegion('11500603', '가양1동', 37.56, 126.85);

        $response = $this->actingAs($this->user())
            ->getJson(route('api.regions.search', ['q' => '의정부']));

        $response->assertOk();

        $data = $response->json('data');

        $this->assertCount(1, $data);
        $this->assertSame('경기도 의정부시 의정부1동', $data[0]['full_name']);

        // 지도를 옮기려면 좌표가 함께 와야 한다.
        $this->assertEqualsWithDelta(37.74, (float) $data[0]['lat'], 0.001);
        $this->assertEqualsWithDelta(127.04, (float) $data[0]['lng'], 0.001);
    }

    public function test_시군구_이름으로도_찾는다(): void
    {
        $this->makeRegion('41150510', '의정부1동', 37.74, 127.04, sigungu: '의정부시', sido: '경기도');
        $this->makeRegion('41150520', '의정부2동', 37.75, 127.05, sigungu: '의정부시', sido: '경기도');

        $data = $this->actingAs($this->user())
            ->getJson(route('api.regions.search', ['q' => '의정부시']))
            ->json('data');

        $this->assertCount(2, $data);
    }

    public function test_로그인하지_않으면_검색할_수_없다(): void
    {
        $this->getJson(route('api.regions.search', ['q' => '가양']))->assertUnauthorized();
    }

    public function test_새_상권분석_화면에_검색_버튼과_입력창이_있다(): void
    {
        $this->makeRegion('11500603', '가양1동', 37.56, 126.85);

        $response = $this->actingAs($this->user())->get(route('analyses.create'));

        $response->assertOk();
        $response->assertSee('submitSearch()', false);
        $response->assertSee('행정동 검색', false);
    }
}
