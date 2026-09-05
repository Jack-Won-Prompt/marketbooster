<?php

namespace Tests\Unit;

use App\Services\OpenData\Seoul\Transformers\CardSalesTransformer;
use App\Services\OpenData\Seoul\Transformers\FloatingPopulationTransformer;
use App\Services\OpenData\Seoul\Transformers\ResidentPopulationTransformer;
use App\Services\OpenData\Seoul\Transformers\WorkplacePopulationTransformer;
use App\Support\Period;
use Tests\TestCase;

/**
 * 서울시 상권분석서비스는 교차표가 아니라 주변분포만 준다.
 * 변환기가 교차표를 채운 뒤에도 원본 주변분포가 그대로 복원되는지 확인한다.
 * (리포트가 보여 주는 값은 모두 주변합이므로 이 성질이 곧 정확성이다.)
 */
class SeoulTransformerTest extends TestCase
{
    private Period $period;

    protected function setUp(): void
    {
        parent::setUp();
        $this->period = Period::quarter('20242');
    }

    private function sum(array $rows, string $column): int
    {
        return (int) array_sum(array_column($rows, $column));
    }

    public function test_유동인구_교차표가_원본_주변분포를_보존한다(): void
    {
        $row = [
            'STDR_YYQU_CD' => '20242',
            'ADSTRD_CD' => '11500603',
            // 시간대 (00~06 은 밤으로 접힌다)
            'TMZON_00_06_FLPOP_CO' => 1000,
            'TMZON_06_11_FLPOP_CO' => 4000,
            'TMZON_11_14_FLPOP_CO' => 5000,
            'TMZON_14_17_FLPOP_CO' => 3000,
            'TMZON_17_21_FLPOP_CO' => 4500,
            'TMZON_21_24_FLPOP_CO' => 2500,
            // 성별
            'ML_FLPOP_CO' => 12000,
            'FML_FLPOP_CO' => 8000,
            // 연령대
            'AGRDE_10_FLPOP_CO' => 1000,
            'AGRDE_20_FLPOP_CO' => 4000,
            'AGRDE_30_FLPOP_CO' => 5000,
            'AGRDE_40_FLPOP_CO' => 4000,
            'AGRDE_50_FLPOP_CO' => 3000,
            'AGRDE_60_ABOVE_FLPOP_CO' => 3000,
            // 요일
            'MON_FLPOP_CO' => 3000, 'TUES_FLPOP_CO' => 3000, 'WED_FLPOP_CO' => 3000,
            'THUR_FLPOP_CO' => 3000, 'FRI_FLPOP_CO' => 3000,
            'SAT_FLPOP_CO' => 2500, 'SUN_FLPOP_CO' => 2500,
        ];

        $rows = (new FloatingPopulationTransformer)->transform($row, $this->period)['floating_population'];

        $this->assertNotEmpty($rows);

        // 전체 합 = 시간대 합계의 총합 (20,000)
        $this->assertEqualsWithDelta(20000, $this->sum($rows, 'population'), 20);

        // 시간대별 주변합
        $byBand = [];
        foreach ($rows as $r) {
            $byBand[$r['time_band']] = ($byBand[$r['time_band']] ?? 0) + $r['population'];
        }
        $this->assertEqualsWithDelta(4000, $byBand['morning'], 10);
        $this->assertEqualsWithDelta(5000, $byBand['lunch'], 10);
        $this->assertEqualsWithDelta(3000, $byBand['afternoon'], 10);
        $this->assertEqualsWithDelta(4500, $byBand['evening'], 10);
        // 밤 = 21~24 + 00~06
        $this->assertEqualsWithDelta(3500, $byBand['night'], 10);

        // 성별 주변합 (남 60% / 여 40%)
        $byGender = [];
        foreach ($rows as $r) {
            $byGender[$r['gender']] = ($byGender[$r['gender']] ?? 0) + $r['population'];
        }
        $this->assertEqualsWithDelta(12000, $byGender['M'], 20);
        $this->assertEqualsWithDelta(8000, $byGender['F'], 20);

        // 요일 주변합 (평일 15,000 / 주말 5,000 → 75% / 25%)
        $byDay = [];
        foreach ($rows as $r) {
            $byDay[$r['day_type']] = ($byDay[$r['day_type']] ?? 0) + $r['population'];
        }
        $this->assertEqualsWithDelta(15000, $byDay['weekday'], 20);
        $this->assertEqualsWithDelta(5000, $byDay['weekend'], 20);

        // 연령 주변합
        $byAge = [];
        foreach ($rows as $r) {
            $byAge[$r['age_band']] = ($byAge[$r['age_band']] ?? 0) + $r['population'];
        }
        $this->assertEqualsWithDelta(5000, $byAge['30s'], 15);
        $this->assertEqualsWithDelta(3000, $byAge['60s'], 15);

        // 서울은 10대 미만·70대 이상을 제공하지 않는다
        $this->assertArrayNotHasKey('under10', $byAge);
        $this->assertArrayNotHasKey('70s_over', $byAge);

        // 분기 칸에 저장된다
        $this->assertSame('20242', $rows[0]['base_yq']);
        $this->assertSame('', $rows[0]['base_ym']);
    }

    public function test_카드매출이_요일과_성연령_두_테이블로_나뉜다(): void
    {
        $row = [
            'ADSTRD_CD' => '11500603',
            'SVC_INDUTY_CD' => 'CS100001',
            'SVC_INDUTY_CD_NM' => '한식음식점',
            'THSMON_SELNG_AMT' => 10000000,
            'MDWK_SELNG_AMT' => 7000000,
            'WKEND_SELNG_AMT' => 3000000,
            'TMZON_00_06_SELNG_AMT' => 500000,
            'TMZON_06_11_SELNG_AMT' => 1500000,
            'TMZON_11_14_SELNG_AMT' => 3500000,
            'TMZON_14_17_SELNG_AMT' => 1000000,
            'TMZON_17_21_SELNG_AMT' => 2500000,
            'TMZON_21_24_SELNG_AMT' => 1000000,
            'TMZON_00_06_SELNG_CO' => 40,
            'TMZON_06_11_SELNG_CO' => 120,
            'TMZON_11_14_SELNG_CO' => 280,
            'TMZON_14_17_SELNG_CO' => 80,
            'TMZON_17_21_SELNG_CO' => 200,
            'TMZON_21_24_SELNG_CO' => 80,
            'ML_SELNG_AMT' => 6000000,
            'FML_SELNG_AMT' => 4000000,
            'AGRDE_10_SELNG_AMT' => 500000,
            'AGRDE_20_SELNG_AMT' => 2000000,
            'AGRDE_30_SELNG_AMT' => 3000000,
            'AGRDE_40_SELNG_AMT' => 2500000,
            'AGRDE_50_SELNG_AMT' => 1500000,
            'AGRDE_60_ABOVE_SELNG_AMT' => 500000,
        ];

        $result = (new CardSalesTransformer)->transform($row, $this->period);

        // 요일 × 시간대 — 시간대 합계가 그대로 보존된다 (00~06 은 밤에 합산)
        $sales = $result['card_sales'];
        $this->assertEqualsWithDelta(10000000, $this->sum($sales, 'sales_amount'), 100);

        $byBand = [];
        foreach ($sales as $r) {
            $byBand[$r['time_band']] = ($byBand[$r['time_band']] ?? 0) + $r['sales_amount'];
        }
        $this->assertEqualsWithDelta(3500000, $byBand['lunch'], 50);
        $this->assertEqualsWithDelta(1500000, $byBand['night'], 50);

        // 주중/주말 비율 (70% / 30%)
        $byDay = [];
        foreach ($sales as $r) {
            $byDay[$r['day_type']] = ($byDay[$r['day_type']] ?? 0) + $r['sales_amount'];
        }
        $this->assertEqualsWithDelta(7000000, $byDay['weekday'], 100);
        $this->assertEqualsWithDelta(3000000, $byDay['weekend'], 100);

        // 성 × 연령 — 연령 합계 보존, 성별 비율 60/40
        $demo = $result['card_sales_demographics'];
        $this->assertEqualsWithDelta(10000000, $this->sum($demo, 'sales_amount'), 100);

        $byGender = [];
        foreach ($demo as $r) {
            $byGender[$r['gender']] = ($byGender[$r['gender']] ?? 0) + $r['sales_amount'];
        }
        $this->assertEqualsWithDelta(6000000, $byGender['M'], 100);
        $this->assertEqualsWithDelta(4000000, $byGender['F'], 100);

        // 업종 마스터에 반영할 정보도 함께 나온다
        $this->assertSame([['code' => 'CS100001', 'name' => '한식음식점']], $result['industries']);
    }

    public function test_상주인구는_성연령_교차값을_그대로_쓰고_가구수도_담는다(): void
    {
        $row = [
            'ADSTRD_CD' => '11500603',
            'MAG_10_REPOP_CO' => 100, 'FAG_10_REPOP_CO' => 90,
            'MAG_20_REPOP_CO' => 200, 'FAG_20_REPOP_CO' => 210,
            'MAG_30_REPOP_CO' => 300, 'FAG_30_REPOP_CO' => 310,
            'MAG_40_REPOP_CO' => 250, 'FAG_40_REPOP_CO' => 240,
            'MAG_50_REPOP_CO' => 220, 'FAG_50_REPOP_CO' => 230,
            'MAG_60_ABOVE_REPOP_CO' => 180, 'FAG_60_ABOVE_REPOP_CO' => 200,
            'TOT_HSHLD_CO' => 1200,
            'APT_HSHLD_CO' => 800,
            'NON_APT_HSHLD_CO' => 400,
        ];

        $result = (new ResidentPopulationTransformer)->transform($row, $this->period);

        // 추정 없이 원본 교차값 그대로
        $population = $result['resident_population'];
        $this->assertCount(12, $population);
        $this->assertSame(2530, $this->sum($population, 'population'));

        $male30 = collect($population)->first(fn ($r) => $r['gender'] === 'M' && $r['age_band'] === '30s');
        $this->assertSame(300, $male30['population']);

        // 아파트 / 비아파트 가구
        $households = collect($result['households'])->keyBy('housing_type');
        $this->assertSame(800, $households['apartment']['households']);
        $this->assertSame(400, $households['non_apartment']['households']);
    }

    public function test_직장인구도_성연령_교차값을_그대로_쓴다(): void
    {
        $row = [
            'ADSTRD_CD' => '11500603',
            'MAG_20_WRC_POPLTN_CO' => 500, 'FAG_20_WRC_POPLTN_CO' => 450,
            'MAG_30_WRC_POPLTN_CO' => 700, 'FAG_30_WRC_POPLTN_CO' => 650,
            'MAG_40_WRC_POPLTN_CO' => 600, 'FAG_40_WRC_POPLTN_CO' => 550,
        ];

        $rows = (new WorkplacePopulationTransformer)->transform($row, $this->period)['workplace_population'];

        $this->assertCount(6, $rows);
        $this->assertSame(3450, $this->sum($rows, 'population'));
    }

    public function test_행정동코드가_없으면_아무것도_만들지_않는다(): void
    {
        $this->assertSame([], (new FloatingPopulationTransformer)->transform([], $this->period));
        $this->assertSame([], (new ResidentPopulationTransformer)->transform([], $this->period));
    }
}
