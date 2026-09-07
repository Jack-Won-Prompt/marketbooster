<?php

namespace Tests\Feature;

use App\Http\Controllers\DistrictController;
use App\Models\User;
use App\Support\Geometry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\MakesMarketData;
use Tests\TestCase;

/**
 * 상권 보고서 화면 — 지도에 그린 상권(원 · 사각형 · 다각형)의 세 탭.
 */
class DistrictReportTest extends TestCase
{
    use MakesMarketData, RefreshDatabase;

    private function user(): User
    {
        return User::factory()->create();
    }

    /** @return array<int, array{0: float, 1: float}> */
    private function ring(int $radiusM = 300): array
    {
        return Geometry::circleRing(37.50, 127.00, $radiusM);
    }

    private function ask(string $route, array $extra = [], ?User $user = null)
    {
        return $this->actingAs($user ?? $this->user())->postJson(route($route), [
            'shape' => 'circle',
            'ring' => $this->ring(),
            'radius_m' => 300,
            'period' => $this->baseYm,
        ] + $extra);
    }

    public function test_화면이_열린다(): void
    {
        $response = $this->actingAs($this->user())->get(route('districts.index'));

        $response->assertOk();
        // 상권 만들기 도구 세 가지가 모두 있어야 한다.
        $response->assertSee('원형', false);
        $response->assertSee('사각형', false);
        $response->assertSee('다각형', false);
        $response->assertSee('500,000㎡', false);
    }

    public function test_로그인하지_않으면_쓸_수_없다(): void
    {
        $this->postJson(route('api.districts.overview'), [
            'shape' => 'circle',
            'ring' => $this->ring(),
        ])->assertUnauthorized();
    }

    public function test_면적_상한을_넘으면_거절한다(): void
    {
        $this->makeRegion('11500603', '가양1동', 37.50, 127.00);
        $this->seedStatistics('11500603');

        // 반경 500m = 약 785,000㎡ > 500,000㎡
        $response = $this->actingAs($this->user())->postJson(route('api.districts.overview'), [
            'shape' => 'circle',
            'ring' => Geometry::circleRing(37.50, 127.00, 500),
            'radius_m' => 500,
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('ok', false);
        $this->assertStringContainsString('500,000㎡', $response->json('message'));
    }

    public function test_상권_탭이_머리말과_결제_경향을_돌려준다(): void
    {
        $this->makeRegion('11500603', '가양1동', 37.50, 127.00);
        $this->seedStatistics('11500603');

        $response = $this->ask('api.districts.overview');

        $response->assertOk();

        $district = $response->json('district');

        $this->assertSame('강서구 가양1동 중심 상권', $district['name']);
        $this->assertSame('원형 상권', $district['shape_label']);
        $this->assertSame(300, $district['radius_m']);
        $this->assertGreaterThan(280_000, $district['area_m2']);
        $this->assertLessThan(290_000, $district['area_m2']);

        $overview = $response->json('overview');

        // 하루 평균 매출에 30을 곱한 월 환산값
        $this->assertSame($overview['sales']['daily_amount'] * 30, $overview['sales']['monthly_amount']);
        $this->assertTrue($overview['payment_habits']['covered']);

        $titles = array_column($overview['payment_habits']['items'], 'title');

        $this->assertContains('상권 결제 발생 시간대', $titles);
        $this->assertContains('상권 결제 발생 요일', $titles);
        $this->assertContains('상권 결제 성별', $titles);
        $this->assertContains('상권 결제 남녀 연령대별', $titles);
    }

    public function test_매장은_그린_범위_안에_있는_것만_센다(): void
    {
        $this->makeRegion('11500603', '가양1동', 37.50, 127.00);
        $this->seedStatistics('11500603');

        // 원 안(중심)과 원 밖(약 1km 떨어짐)에 각각 매장을 둔다.
        $this->storeAt('in-1', '안쪽식당', 37.5000, 127.0000, 'I2', 'I201', 'I20101');
        $this->storeAt('in-2', '안쪽카페', 37.5005, 127.0005, 'I2', 'I212', 'I21201');
        $this->storeAt('out-1', '바깥식당', 37.5100, 127.0100, 'I2', 'I201', 'I20101');

        $overview = $this->ask('api.districts.overview')->json('overview');

        $this->assertSame(2, $overview['stores']['total']);

        $stores = $this->ask('api.districts.stores')->json('stores');

        $this->assertSame(2, $stores['total']);
        $this->assertSame(2, $stores['counts']['all']);
        $this->assertSame(2, $stores['counts']['food']);
        $this->assertSame(['안쪽식당', '안쪽카페'], array_column($stores['items'], 'name'));
    }

    public function test_매장_목록을_업종으로_거르고_검색한다(): void
    {
        $this->makeRegion('11500603', '가양1동', 37.50, 127.00);
        $this->seedStatistics('11500603');

        $this->storeAt('a', '가양식당', 37.5000, 127.0000, 'I2', 'I201', 'I20101');
        $this->storeAt('b', '가양미용실', 37.5001, 127.0001, 'S2', 'S207', 'S20701');

        $filtered = $this->ask('api.districts.stores', ['group' => 'food'])->json('stores');

        $this->assertSame(1, $filtered['total']);
        $this->assertSame('가양식당', $filtered['items'][0]['name']);

        $searched = $this->ask('api.districts.stores', ['q' => '미용'])->json('stores');

        $this->assertSame(1, $searched['total']);
        $this->assertSame('가양미용실', $searched['items'][0]['name']);
    }

    public function test_주거인구_탭이_성연령_최다_구간을_알려준다(): void
    {
        $this->makeRegion('11500603', '가양1동', 37.50, 127.00);
        $this->seedStatistics('11500603');

        $residence = $this->ask('api.districts.residence')->json('residence');

        $this->assertGreaterThan(0, $residence['resident']['total']);
        $this->assertNotNull($residence['resident']['top_label']);
        $this->assertGreaterThan(0, $residence['households']['total']);
        $this->assertArrayHasKey('apartment_share', $residence['households']);
    }

    public function test_수록된_행정동이_없으면_알려준다(): void
    {
        // 행정동을 하나도 만들지 않았다.
        $response = $this->ask('api.districts.overview');

        $response->assertStatus(422);
        $this->assertStringContainsString('수록된 행정동이 없습니다', $response->json('message'));
    }
}
