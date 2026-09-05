<?php

namespace Tests\Unit;

use App\Support\Korean;
use PHPUnit\Framework\TestCase;

class KoreanJosaTest extends TestCase
{
    public function test_받침_있는_단어에는_이_은_을_을_붙인다(): void
    {
        $this->assertSame('한식음식점이', Korean::withJosa('한식음식점', '이/가'));
        $this->assertSame('상권은', Korean::withJosa('상권', '은/는'));
        $this->assertSame('매출을', Korean::withJosa('매출', '을/를'));
    }

    public function test_받침_없는_단어에는_가_는_를_를_붙인다(): void
    {
        $this->assertSame('커피·음료가', Korean::withJosa('커피·음료', '이/가'));
        $this->assertSame('학원가는', Korean::withJosa('학원가', '은/는'));
        $this->assertSame('지도를', Korean::withJosa('지도', '을/를'));
    }

    public function test_으로_로는_ㄹ받침을_예외로_처리한다(): void
    {
        $this->assertSame('여성으로', Korean::withJosa('여성', '으로/로'));
        $this->assertSame('서울로', Korean::withJosa('서울', '으로/로'));
        $this->assertSame('반경으로', Korean::withJosa('반경', '으로/로'));
    }

    public function test_한글이_아니면_받침_없는_형태를_쓴다(): void
    {
        $this->assertSame('가', Korean::josa('CS100001', '이/가'));
        $this->assertSame('로', Korean::josa('2026', '으로/로'));
    }
}
