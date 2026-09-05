<?php

namespace Tests\Feature;

use App\Models\Analysis;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\MakesMarketData;
use Tests\TestCase;

class AnalysisFlowTest extends TestCase
{
    use MakesMarketData, RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->makeRegion('1150000001', '가양제1동', 37.50, 127.00);
        $this->seedStatistics('1150000001');
    }

    public function test_로그인하지_않으면_분석_화면에_들어갈_수_없다(): void
    {
        $this->get(route('analyses.create'))->assertRedirect(route('login'));
    }

    public function test_반경_분석을_만들면_리포트가_완성된다(): void
    {
        $response = $this->actingAs($this->user)->post(route('analyses.store'), [
            'title' => '가양 반경 1km',
            'mode' => 'radius',
            'center_lat' => 37.50,
            'center_lng' => 127.00,
            'radius_m' => 1000,
            'base_ym' => $this->baseYm,
        ]);

        $analysis = Analysis::firstOrFail();

        $response->assertRedirect(route('analyses.show', $analysis));
        $this->assertSame('completed', $analysis->status);
        $this->assertSame(['1150000001'], $analysis->region_codes);

        $payload = $analysis->payload;

        foreach (['meta', 'summary', 'resident', 'households', 'workplace', 'floating', 'sales', 'education', 'sources'] as $section) {
            $this->assertArrayHasKey($section, $payload, "리포트에 {$section} 섹션이 없습니다.");
        }

        $this->assertGreaterThan(0, $payload['summary']['selected']['resident']);
        $this->assertGreaterThan(0, $payload['sales']['total_amount']);
        $this->assertNotEmpty($payload['summary']['insights']);
    }

    public function test_반경_가중치만큼_통계가_안분된다(): void
    {
        $this->actingAs($this->user)->post(route('analyses.store'), [
            'title' => '가양 반경 500m',
            'mode' => 'radius',
            'center_lat' => 37.50,
            'center_lng' => 127.00,
            'radius_m' => 500,
            'base_ym' => $this->baseYm,
        ]);

        $payload = Analysis::firstOrFail()->payload;
        $weight = $payload['meta']['regions'][0]['weight'];

        // 행정동 전체 거주인구는 2(성별) × 8(연령) × 100 = 1,600명
        $this->assertEqualsWithDelta(1600 * $weight, $payload['summary']['selected']['resident'], 5);
        $this->assertLessThan(1.0, $weight);
    }

    public function test_행정동을_직접_고르면_통계를_그대로_합산한다(): void
    {
        $this->actingAs($this->user)->post(route('analyses.store'), [
            'title' => '가양제1동',
            'mode' => 'region',
            'region_codes' => ['1150000001'],
            'base_ym' => $this->baseYm,
        ]);

        $payload = Analysis::firstOrFail()->payload;

        $this->assertSame(1600, $payload['summary']['selected']['resident']);
        $this->assertSame(4000, $payload['households']['total']);
    }

    public function test_다른_회원의_리포트는_볼_수_없다(): void
    {
        $analysis = Analysis::create([
            'user_id' => $this->user->id,
            'title' => '남의 분석',
            'mode' => 'region',
            'region_codes' => ['1150000001'],
            'base_ym' => $this->baseYm,
            'status' => 'completed',
        ]);

        $this->actingAs(User::factory()->create())
            ->get(route('analyses.show', $analysis))
            ->assertForbidden();
    }

    public function test_완료된_분석은_PDF_로_내려받을_수_있다(): void
    {
        $this->actingAs($this->user)->post(route('analyses.store'), [
            'title' => '가양 반경 1km',
            'mode' => 'radius',
            'center_lat' => 37.50,
            'center_lng' => 127.00,
            'radius_m' => 1000,
            'base_ym' => $this->baseYm,
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('analyses.pdf', Analysis::firstOrFail()));

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
    }

    public function test_범위가_비어_있으면_검증에서_막는다(): void
    {
        $this->actingAs($this->user)
            ->post(route('analyses.store'), [
                'title' => '빈 분석',
                'mode' => 'region',
                'base_ym' => $this->baseYm,
            ])
            ->assertSessionHasErrors('region_codes');

        $this->assertSame(0, Analysis::count());
    }
}
