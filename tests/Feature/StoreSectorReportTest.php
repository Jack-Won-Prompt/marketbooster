<?php

namespace Tests\Feature;

use App\Models\Analysis;
use App\Models\User;
use App\Services\Analysis\MarketAnalyzer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\MakesMarketData;
use Tests\TestCase;

/**
 * 리포트가 점포를 "분야(식당·카페/디저트 …)" 로 나누고
 * 프랜차이즈 브랜드를 이름까지 보여 주는지 확인한다.
 */
class StoreSectorReportTest extends TestCase
{
    use MakesMarketData, RefreshDatabase;

    private function analysis(): Analysis
    {
        return new Analysis([
            'title' => '테스트',
            'mode' => 'radius',
            'center_lat' => 37.50,
            'center_lng' => 127.00,
            'radius_m' => 1500,
            'region_codes' => [],
        ] + $this->period()->columns());
    }

    private function seedMixedStores(string $code): void
    {
        // 식당 3 · 카페 4(스타벅스 2 포함) · 디저트(빵집) 2 · 치킨 3(교촌 3)
        $this->seedStores($code, 3, '한식', 'I2', 'I201', 'I20101');
        $this->seedStores($code, 2, '비알코올', 'I2', 'I212', 'I21201');
        $this->seedStores($code, 2, '비알코올', 'I2', 'I212', 'I21201', '스타벅스 테스트점');
        $this->seedStores($code, 2, '기타 간이', 'I2', 'I210', 'I21001');
        $this->seedStores($code, 3, '기타 간이', 'I2', 'I210', 'I21006', '교촌치킨 테스트점');
    }

    public function test_분야별로_점포를_나눈다(): void
    {
        $this->makeRegion('41150510', '의정부1동', 37.50, 127.00, sigungu: '의정부시', sido: '경기도');
        $this->seedMixedStores('41150510');

        $stores = app(MarketAnalyzer::class)->analyze($this->analysis())['stores'];

        $bySector = collect($stores['by_sector'])->keyBy('code');

        // 카페 2 + 스타벅스 2 + 빵집 2 = 6 이 모두 카페·디저트로 묶여야 한다.
        $this->assertSame('카페·디저트', $bySector['cafe_dessert']['name']);
        $this->assertGreaterThan(0, $bySector['cafe_dessert']['count']);
        $this->assertArrayHasKey('restaurant', $bySector->all());
        $this->assertArrayHasKey('fastfood', $bySector->all());
    }

    public function test_프랜차이즈_브랜드를_이름과_함께_센다(): void
    {
        $this->makeRegion('41150510', '의정부1동', 37.50, 127.00, sigungu: '의정부시', sido: '경기도');
        $this->seedMixedStores('41150510');

        $stores = app(MarketAnalyzer::class)->analyze($this->analysis())['stores'];

        $brands = collect($stores['brands'])->keyBy('name');

        $this->assertArrayHasKey('스타벅스', $brands->all());
        $this->assertArrayHasKey('교촌치킨', $brands->all());
        $this->assertSame('카페·디저트', $brands['스타벅스']['sector_name']);
        $this->assertSame('패스트푸드·분식', $brands['교촌치킨']['sector_name']);

        $this->assertGreaterThan(0, $stores['franchise_total']);
        $this->assertGreaterThan(0, $stores['franchise_share']);

        // 분야별로도 묶여 있어야 화면에서 "디저트 브랜드" 를 따로 보여 줄 수 있다.
        $this->assertSame('스타벅스', $stores['brands_by_sector']['cafe_dessert'][0]['name']);
    }

    public function test_프랜차이즈_목록을_CSV_로_내려받는다(): void
    {
        $this->makeRegion('41150510', '의정부1동', 37.50, 127.00, sigungu: '의정부시', sido: '경기도');
        $this->seedMixedStores('41150510');

        $user = User::factory()->create();
        $analysis = $this->analysis();
        $analysis->user_id = $user->id;
        $analysis->status = 'completed';
        $analysis->payload = app(MarketAnalyzer::class)->analyze($analysis);
        $analysis->save();

        $response = $this->actingAs($user)->get(route('analyses.franchises', $analysis));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $csv = $response->streamedContent();

        $this->assertStringContainsString('스타벅스', $csv);
        $this->assertStringContainsString('교촌치킨', $csv);
        $this->assertStringContainsString('카페·디저트', $csv);
    }

    public function test_남의_분석은_내려받을_수_없다(): void
    {
        $this->makeRegion('41150510', '의정부1동', 37.50, 127.00, sigungu: '의정부시', sido: '경기도');
        $this->seedStores('41150510', 3);

        $owner = User::factory()->create();
        $analysis = $this->analysis();
        $analysis->user_id = $owner->id;
        $analysis->status = 'completed';
        $analysis->payload = app(MarketAnalyzer::class)->analyze($analysis);
        $analysis->save();

        $this->actingAs(User::factory()->create())
            ->get(route('analyses.franchises', $analysis))
            ->assertForbidden();
    }
}
