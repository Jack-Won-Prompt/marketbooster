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
            'large_code' => substr($small, 0, 2), 'large_name' => '테스트',
            'middle_code' => substr($small, 0, 4), 'middle_name' => '테스트',
            'small_code' => $small, 'small_name' => '테스트',
            'collected_at' => $now, 'created_at' => $now, 'updated_at' => $now,
        ]);
    }

    /** 행정동 $dongs 곳에 각각 $perDong 개씩 같은 상호를 심는다. */
    private function chain(string $prefix, string $name, int $dongs, int $perDong): void
    {
        for ($d = 0; $d < $dongs; $d++) {
            $code = '4115'.str_pad((string) (510 + $d * 10), 4, '0', STR_PAD_LEFT);

            for ($i = 0; $i < $perDong; $i++) {
                $this->store("{$prefix}-{$d}-{$i}", $name, $code);
            }
        }
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
        $this->store('b3', '이디야마곡', '41150510', 'I21201');

        app(StoreClassifier::class)->classify();

        $this->assertSame('GS25', DB::table('stores')->where('store_id', 'b1')->value('brand'));
        $this->assertSame('GS25', DB::table('stores')->where('store_id', 'b2')->value('brand'));
        $this->assertSame('이디야커피', DB::table('stores')->where('store_id', 'b3')->value('brand'));
        $this->assertSame('dictionary', DB::table('stores')->where('store_id', 'b1')->value('brand_source'));
        $this->assertSame(3, DB::table('stores')->where('is_franchise', true)->count());
    }

    public function test_사전_브랜드는_업종이_맞을_때만_붙는다(): void
    {
        // "씨유" 는 CU 편의점의 표기지만 "씨유헤어" 같은 상호에도 들어 있다.
        $this->store('g1', '씨유마곡', '41150510', 'G20405');   // 종합 소매 → 편의점
        $this->store('g2', '씨유헤어', '41150510', 'S20701');   // 이용·미용

        app(StoreClassifier::class)->classify();

        $this->assertSame('CU', DB::table('stores')->where('store_id', 'g1')->value('brand'));
        $this->assertNull(DB::table('stores')->where('store_id', 'g2')->value('brand'));
    }

    public function test_여러_행정동에_반복되는_상호는_체인으로_본다(): void
    {
        // 사전에 없는 이름이지만 행정동 · 점포 기준을 모두 넘긴다.
        $this->chain('c', '동네커피클럽', StoreClassifier::CHAIN_MIN_DONGS, 2);

        // 한 동네에만 몰려 있는 상호는 아무리 많아도 체인이 아니다.
        $this->chain('d', '한동네카페', 1, StoreClassifier::CHAIN_MIN_STORES + 2);

        app(StoreClassifier::class)->classify();

        $this->assertSame('동네커피클럽', DB::table('stores')->where('store_id', 'c-0-0')->value('brand'));
        $this->assertSame('chain', DB::table('stores')->where('store_id', 'c-0-0')->value('brand_source'));

        // 체인은 이름까지 확인된 프랜차이즈가 아니므로 따로 센다.
        $this->assertFalse((bool) DB::table('stores')->where('store_id', 'c-0-0')->value('is_franchise'));
        $this->assertNull(DB::table('stores')->where('store_id', 'd-0-0')->value('brand'));
    }

    public function test_점포_수가_적으면_체인으로_보지_않는다(): void
    {
        // 행정동은 넉넉히 퍼져 있지만 점포가 기준보다 적다.
        $this->chain('e', '드문상호', StoreClassifier::CHAIN_MIN_DONGS, 1);

        app(StoreClassifier::class)->classify();

        $this->assertNull(DB::table('stores')->where('store_id', 'e-0-0')->value('brand'));
    }

    public function test_업종_이름을_그대로_쓴_상호는_체인으로_보지_않는다(): void
    {
        // "컴퓨터수리" 는 업종 분류표에 있는 말이지 브랜드가 아니다.
        for ($d = 0; $d < StoreClassifier::CHAIN_MIN_DONGS; $d++) {
            $code = '4115'.str_pad((string) (510 + $d * 10), 4, '0', STR_PAD_LEFT);

            for ($i = 0; $i < 3; $i++) {
                $now = now();

                DB::table('stores')->insert([
                    'store_id' => "i-{$d}-{$i}",
                    'name' => '컴퓨터수리',
                    'region_code' => $code,
                    'sido_name' => '경기도', 'sigungu_name' => '의정부시', 'dong_name' => '테스트동',
                    'large_code' => 'S2', 'large_name' => '수리·개인',
                    'middle_code' => 'S201', 'middle_name' => '컴퓨터 수리',
                    'small_code' => 'S20101', 'small_name' => '컴퓨터 수리',
                    'collected_at' => $now, 'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }

        app(StoreClassifier::class)->classify();

        $this->assertNull(DB::table('stores')->where('store_id', 'i-0-0')->value('brand'));
    }

    public function test_상호가_없는_행은_브랜드를_붙이지_않는다(): void
    {
        $this->chain('h', '업소명없음', StoreClassifier::CHAIN_MIN_DONGS, 3);

        app(StoreClassifier::class)->classify();

        $this->assertNull(DB::table('stores')->where('store_id', 'h-0-0')->value('brand'));
        $this->assertSame(0, DB::table('stores')->where('is_franchise', true)->count());
    }

    public function test_두_번_돌려도_결과가_같다(): void
    {
        $this->store('f1', '스타벅스마곡점', '41150510', 'I21201');
        $this->chain('f', '동네커피클럽', StoreClassifier::CHAIN_MIN_DONGS, 2);

        $classifier = app(StoreClassifier::class);
        $classifier->classify();
        $first = DB::table('stores')->orderBy('store_id')->pluck('brand', 'store_id')->all();

        $classifier->classify();
        $second = DB::table('stores')->orderBy('store_id')->pluck('brand', 'store_id')->all();

        $this->assertSame($first, $second);
        $this->assertSame('스타벅스', $first['f1']);
        $this->assertSame('동네커피클럽', $first['f-0-0']);
    }
}
