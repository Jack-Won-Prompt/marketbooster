<?php

namespace Tests\Feature;

use App\Services\Stores\StoreClassifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\MakesMarketData;
use Tests\TestCase;

class StoreClassifierTest extends TestCase
{
    use MakesMarketData, RefreshDatabase;

    private function store(string $id, string $name, string $regionCode, string $small = 'I21201'): void
    {
        $now = now();

        DB::table('stores')->insert([
            'store_id' => $id,
            'name' => $name,
            'region_code' => $regionCode,
            'sido_name' => '경기도',
            'sigungu_name' => '의정부시',
            'dong_name' => '테스트동',
            'large_code' => 'I2', 'large_name' => '음식',
            'middle_code' => substr($small, 0, 4), 'middle_name' => '비알코올',
            'small_code' => $small, 'small_name' => '카페',
            'collected_at' => $now, 'created_at' => $now, 'updated_at' => $now,
        ]);
    }

    public function test_업종코드로_분야를_채운다(): void
    {
        $this->store('a1', '동네카페', '41150510', 'I21201');       // 카페
        $this->store('a2', '동네빵집', '41150510', 'I21001');       // 빵/도넛 → 디저트
        $this->store('a3', '동네치킨', '41150510', 'I21006');       // 치킨 → 패스트푸드

        app(StoreClassifier::class)->classify();

        $this->assertSame('cafe_dessert', DB::table('stores')->where('store_id', 'a1')->value('sector'));
        $this->assertSame('cafe_dessert', DB::table('stores')->where('store_id', 'a2')->value('sector'));
        $this->assertSame('fastfood', DB::table('stores')->where('store_id', 'a3')->value('sector'));
    }

    public function test_사전에_있는_브랜드를_한_이름으로_묶는다(): void
    {
        $this->store('b1', '지에스25마곡', '41150510', 'G20405');
        $this->store('b2', '지에스25발산파크점', '41150520', 'G20405');
        $this->store('b3', '이디야마곡', '41150510');

        app(StoreClassifier::class)->classify();

        $this->assertSame('GS25', DB::table('stores')->where('store_id', 'b1')->value('brand'));
        $this->assertSame('GS25', DB::table('stores')->where('store_id', 'b2')->value('brand'));
        $this->assertSame('이디야커피', DB::table('stores')->where('store_id', 'b3')->value('brand'));
        $this->assertSame(3, DB::table('stores')->where('is_franchise', true)->count());
    }

    public function test_여러_행정동에_반복되는_상호는_체인으로_본다(): void
    {
        // 사전에 없는 이름이지만 행정동 3곳에 걸쳐 있다.
        $this->store('c1', '동네커피클럽', '41150510');
        $this->store('c2', '동네커피클럽', '41150520');
        $this->store('c3', '동네커피클럽', '41150530');

        // 같은 동네에만 두 곳 있는 이름은 체인이 아니다.
        $this->store('d1', '한동네카페', '41150510');
        $this->store('d2', '한동네카페', '41150510');

        app(StoreClassifier::class)->classify();

        $this->assertSame('동네커피클럽', DB::table('stores')->where('store_id', 'c1')->value('brand'));
        $this->assertNull(DB::table('stores')->where('store_id', 'd1')->value('brand'));
    }

    public function test_상호가_없는_행은_브랜드를_붙이지_않는다(): void
    {
        $this->store('e1', '업소명없음', '41150510');
        $this->store('e2', '업소명없음', '41150520');
        $this->store('e3', '업소명없음', '41150530');

        app(StoreClassifier::class)->classify();

        $this->assertNull(DB::table('stores')->where('store_id', 'e1')->value('brand'));
        $this->assertSame(0, DB::table('stores')->where('is_franchise', true)->count());
    }

    public function test_두_번_돌려도_결과가_같다(): void
    {
        $this->store('f1', '스타벅스마곡점', '41150510');
        $this->store('f2', '동네커피클럽', '41150510');
        $this->store('f3', '동네커피클럽', '41150520');
        $this->store('f4', '동네커피클럽', '41150530');

        $classifier = app(StoreClassifier::class);
        $classifier->classify();
        $first = DB::table('stores')->orderBy('store_id')->pluck('brand', 'store_id')->all();

        $classifier->classify();
        $second = DB::table('stores')->orderBy('store_id')->pluck('brand', 'store_id')->all();

        $this->assertSame($first, $second);
        $this->assertSame('스타벅스', $first['f1']);
        $this->assertSame('동네커피클럽', $first['f2']);
    }
}
