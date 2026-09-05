<?php

namespace Tests\Unit;

use App\Services\OpenData\RecordNormalizer;
use Tests\TestCase;

class RecordNormalizerTest extends TestCase
{
    private RecordNormalizer $normalizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->normalizer = new RecordNormalizer;
    }

    public function test_기관별로_다른_필드명을_내부_컬럼으로_옮긴다(): void
    {
        $row = [
            'ADMI_CD' => '1150053000',
            'STDR_YM' => '2026-08',
            'SEX_CD' => '1',
            'AGE_CD' => '30',
            'POPLTN_CNT' => '1,234',
        ];

        $result = $this->normalizer->apply($row, [
            'region_code' => ['ADMI_CD', 'admiCd'],
            'base_ym' => ['STDR_YM'],
            'gender' => ['SEX_CD'],
            'age_band' => ['AGE_CD'],
            'population' => ['POPLTN_CNT'],
        ]);

        $this->assertSame('1150053000', $result['region_code']);
        $this->assertSame('202608', $result['base_ym']);
        $this->assertSame('M', $result['gender']);
        $this->assertSame('30s', $result['age_band']);
        $this->assertSame(1234, $result['population']);
    }

    public function test_대소문자가_달라도_필드를_찾는다(): void
    {
        $result = $this->normalizer->apply(
            ['admicd' => '1150053000', 'stdrym' => '202608'],
            ['region_code' => ['ADMI_CD'], 'base_ym' => ['STDR_YM']]
        );

        $this->assertSame('1150053000', $result['region_code']);
        $this->assertSame('202608', $result['base_ym']);
    }

    public function test_한글_코드값도_표준값으로_바꾼다(): void
    {
        $result = $this->normalizer->normalizeCodes([
            'gender' => '여',
            'day_type' => '주말',
            'time_band' => '점심',
            'age_band' => '70대 이상',
        ]);

        $this->assertSame('F', $result['gender']);
        $this->assertSame('weekend', $result['day_type']);
        $this->assertSame('lunch', $result['time_band']);
        $this->assertSame('70s_over', $result['age_band']);
    }

    public function test_시_단위_시간과_실제_나이를_구간으로_접는다(): void
    {
        $result = $this->normalizer->normalizeCodes(['time_band' => '19', 'age_band' => '35']);

        $this->assertSame('evening', $result['time_band']);
        $this->assertSame('30s', $result['age_band']);

        $this->assertSame('night', $this->normalizer->hourToBand(23));
        $this->assertSame('under10', $this->normalizer->ageToBand(7));
        $this->assertSame('70s_over', $this->normalizer->ageToBand(81));
    }
}
