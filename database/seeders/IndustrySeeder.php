<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * 카드매출 분석에 쓰는 업종 마스터.
 * 코드 체계는 서울시 상권분석 서비스의 서비스업종 코드(CS…) 를 따른다.
 */
class IndustrySeeder extends Seeder
{
    public const INDUSTRIES = [
        ['CS100001', '한식음식점', '요식', 1],
        ['CS100002', '분식전문점', '요식', 2],
        ['CS100003', '커피·음료', '요식', 3],
        ['CS100004', '제과점', '요식', 4],
        ['CS100005', '호프·간이주점', '요식', 5],
        ['CS200001', '편의점', '소매', 6],
        ['CS200002', '슈퍼마켓', '소매', 7],
        ['CS200003', '의류·잡화', '소매', 8],
        ['CS300001', '미용실', '서비스', 9],
        ['CS300002', '세탁·수선', '서비스', 10],
        ['CS400001', '일반의원', '의료', 11],
        ['CS400002', '약국', '의료', 12],
        ['CS500001', '일반교습학원', '교육', 13],
        ['CS600001', '헬스·필라테스', '여가', 14],
    ];

    public function run(): void
    {
        $now = now();

        $rows = array_map(fn (array $item) => [
            'code' => $item[0],
            'name' => $item[1],
            'group_name' => $item[2],
            'sort_order' => $item[3],
            'created_at' => $now,
            'updated_at' => $now,
        ], self::INDUSTRIES);

        DB::table('industries')->upsert($rows, ['code'], ['name', 'group_name', 'sort_order', 'updated_at']);

        $this->command?->info('업종 '.count($rows).'건을 적재했습니다.');
    }
}
