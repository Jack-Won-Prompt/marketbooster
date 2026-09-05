<?php

namespace Tests\Unit;

use App\Support\Franchises;
use App\Support\StoreSectors;
use PHPUnit\Framework\TestCase;

class StoreClassificationTest extends TestCase
{
    public function test_같은_중분류라도_소분류로_분야가_갈린다(): void
    {
        // "기타 간이" 안에 빵집과 치킨집이 같이 들어 있다.
        $this->assertSame('cafe_dessert', StoreSectors::resolve('I2', 'I210', 'I21001')); // 빵/도넛
        $this->assertSame('cafe_dessert', StoreSectors::resolve('I2', 'I210', 'I21008')); // 아이스크림/빙수
        $this->assertSame('fastfood', StoreSectors::resolve('I2', 'I210', 'I21006'));     // 치킨
        $this->assertSame('fastfood', StoreSectors::resolve('I2', 'I210', 'I21007'));     // 김밥/분식
    }

    public function test_중분류와_대분류로_차례로_넘어간다(): void
    {
        $this->assertSame('cafe_dessert', StoreSectors::resolve('I2', 'I212', 'I21201')); // 카페
        $this->assertSame('restaurant', StoreSectors::resolve('I2', 'I201', 'I20101'));   // 한식
        $this->assertSame('pub', StoreSectors::resolve('I2', 'I211', 'I21104'));          // 요리 주점
        $this->assertSame('convenience', StoreSectors::resolve('G2', 'G204', 'G20405'));  // 편의점

        // 중분류가 사전에 없으면 대분류로 떨어진다.
        $this->assertSame('professional', StoreSectors::resolve('M1', 'M199', 'M19901'));

        // 아무것도 못 찾으면 기타.
        $this->assertSame(StoreSectors::UNKNOWN, StoreSectors::resolve('Z9', null, null));
    }

    public function test_상호에_지점이_붙어_있어도_브랜드를_찾는다(): void
    {
        // 상가정보의 상호에는 지역명이 붙어 오는 경우가 많다.
        $this->assertSame('GS25', Franchises::match('지에스25마곡')[0]);
        $this->assertSame('GS25', Franchises::match('지에스25발산파크점')[0]);
        $this->assertSame('이디야커피', Franchises::match('이디야마곡')[0]);
        $this->assertSame('스타벅스', Franchises::match('스타벅스 마곡나루역점')[0]);
        $this->assertSame('CU', Franchises::match('씨유의정부역점')[0]);
    }

    public function test_표기가_달라도_같은_브랜드로_묶는다(): void
    {
        $this->assertSame('파리바게뜨', Franchises::match('파리바게뜨')[0]);
        $this->assertSame('파리바게뜨', Franchises::match('파리바게트')[0]);
        $this->assertSame('배스킨라빈스', Franchises::match('베스킨라빈스')[0]);
        $this->assertSame('배스킨라빈스', Franchises::match('배스킨라빈스')[0]);
        $this->assertSame('BBQ치킨', Franchises::match('비비큐')[0]);
        $this->assertSame('BHC치킨', Franchises::match('비에이치씨')[0]);
        $this->assertSame('메가MGC커피', Franchises::match('메가엠지씨커피')[0]);
    }

    public function test_긴_패턴이_먼저_걸린다(): void
    {
        // "피자나라치킨공주" 가 "피자스쿨"·"교촌치킨" 같은 짧은 패턴에 잘못 잡히면 안 된다.
        $this->assertSame('피자나라치킨공주', Franchises::match('피자나라치킨공주 의정부점')[0]);
        $this->assertSame('후라이드참잘하는집', Franchises::match('후라이드참잘하는집')[0]);
    }

    public function test_브랜드에는_분야가_함께_붙는다(): void
    {
        [$brand, $sector] = Franchises::match('스타벅스');

        $this->assertSame('스타벅스', $brand);
        $this->assertSame('cafe_dessert', $sector);
        $this->assertSame('카페·디저트', StoreSectors::label($sector));
    }

    public function test_상호가_아닌_값은_브랜드로_보지_않는다(): void
    {
        $this->assertNull(Franchises::match('업소명없음'));
        $this->assertNull(Franchises::match(''));
        $this->assertNull(Franchises::match(null));
        $this->assertFalse(Franchises::isUsableName('업 소 명 없 음'));

        // 사전에 없는 동네 가게는 그냥 못 찾는다.
        $this->assertNull(Franchises::match('카카두카페'));
    }

    public function test_모든_브랜드의_분야가_실제로_있는_코드다(): void
    {
        foreach (Franchises::BRANDS as $brand => [$sector, $patterns]) {
            $this->assertArrayHasKey($sector, StoreSectors::LABELS, "{$brand} 의 분야 코드가 사전에 없습니다.");
            $this->assertNotEmpty($patterns, "{$brand} 에 패턴이 없습니다.");
        }
    }
}
