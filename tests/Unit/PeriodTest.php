<?php

namespace Tests\Unit;

use App\Support\Period;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class PeriodTest extends TestCase
{
    public function test_길이로_월과_분기를_구분한다(): void
    {
        $this->assertFalse(Period::parse('202608')->isQuarter());
        $this->assertTrue(Period::parse('20242')->isQuarter());
    }

    public function test_저장할_두_칸을_알맞게_채운다(): void
    {
        $this->assertSame(['base_ym' => '202608', 'base_yq' => ''], Period::month('202608')->columns());
        $this->assertSame(['base_ym' => '', 'base_yq' => '20242'], Period::quarter('20242')->columns());
    }

    public function test_조회할_칸을_알려_준다(): void
    {
        $this->assertSame('base_ym', Period::month('202608')->filterColumn());
        $this->assertSame('base_yq', Period::quarter('20242')->filterColumn());
    }

    public function test_저장된_두_칸에서_기간을_복원한다(): void
    {
        $this->assertTrue(Period::fromColumns('', '20242')->isQuarter());
        $this->assertSame('202608', Period::fromColumns('202608', '')->code);
        $this->assertSame('202608', Period::fromColumns('202608', null)->code);
    }

    public function test_사람이_읽을_라벨을_만든다(): void
    {
        $this->assertSame('2026년 8월', Period::month('202608')->label());
        $this->assertSame('2024년 2분기', Period::quarter('20242')->label());
    }

    public function test_분기의_마지막_달을_구한다(): void
    {
        $this->assertSame('202403', Period::quarter('20241')->approximateMonth());
        $this->assertSame('202412', Period::quarter('20244')->approximateMonth());
    }

    public function test_형식이_틀리면_거부한다(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Period::quarter('20245');
    }
}
