<?php

namespace Tests\Support;

use App\Models\Region;
use App\Models\RegionBoundary;
use App\Support\Period;
use App\Support\Taxonomy;
use Illuminate\Support\Facades\DB;

/**
 * 테스트용 최소 데이터 세트.
 * 정사각형 행정동을 만들어 반경 겹침 계산을 예측 가능하게 한다.
 */
trait MakesMarketData
{
    protected string $baseYm = '202608';

    /** seedStores 를 여러 번 불러도 store_id 가 겹치지 않게 하는 호출 번호 */
    private int $storeBatch = 0;

    protected function period(): Period
    {
        return Period::month($this->baseYm);
    }

    /**
     * 중심 (lat, lng) 을 기준으로 한 변이 $sizeDeg 도인 정사각형 행정동을 만든다.
     */
    protected function makeRegion(
        string $code,
        string $dongName,
        float $lat,
        float $lng,
        float $sizeDeg = 0.02,
        string $sigungu = '강서구',
        string $sido = '서울특별시',
    ): Region {
        $half = $sizeDeg / 2;

        $region = Region::create([
            'code' => $code,
            'sido_code' => substr($code, 0, 2),
            'sido_name' => $sido,
            'sigungu_code' => substr($code, 0, 5),
            'sigungu_name' => $sigungu,
            'dong_name' => $dongName,
            'full_name' => "{$sido} {$sigungu} {$dongName}",
            'lat' => $lat,
            'lng' => $lng,
            'area_km2' => round(($sizeDeg * 110.574) * ($sizeDeg * 111.320 * cos(deg2rad($lat))), 4),
        ]);

        RegionBoundary::create([
            'region_code' => $code,
            'min_lat' => $lat - $half,
            'max_lat' => $lat + $half,
            'min_lng' => $lng - $half,
            'max_lng' => $lng + $half,
            'rings' => [[[
                [$lng - $half, $lat - $half],
                [$lng + $half, $lat - $half],
                [$lng + $half, $lat + $half],
                [$lng - $half, $lat + $half],
                [$lng - $half, $lat - $half],
            ]]],
        ]);

        return $region;
    }

    /** 리포트의 모든 섹션이 채워지도록 행정동 하나에 통계를 심는다. */
    protected function seedStatistics(string $regionCode, int $perCell = 100): void
    {
        $now = now();
        $rows = ['resident_populations' => [], 'workplace_populations' => [], 'floating_populations' => []];

        foreach (Taxonomy::GENDERS as $gender) {
            foreach (Taxonomy::AGE_BANDS as $age) {
                $rows['resident_populations'][] = [
                    'region_code' => $regionCode, 'base_ym' => $this->baseYm, 'base_yq' => '',
                    'gender' => $gender, 'age_band' => $age, 'population' => $perCell,
                    'created_at' => $now, 'updated_at' => $now,
                ];
            }

            foreach (Taxonomy::WORK_AGE_BANDS as $age) {
                $rows['workplace_populations'][] = [
                    'region_code' => $regionCode, 'base_ym' => $this->baseYm, 'base_yq' => '',
                    'gender' => $gender, 'age_band' => $age, 'population' => $perCell,
                    'created_at' => $now, 'updated_at' => $now,
                ];
            }

            foreach (Taxonomy::DAY_TYPES as $dayType) {
                foreach (Taxonomy::TIME_BANDS as $band) {
                    foreach (Taxonomy::AGE_BANDS as $age) {
                        $rows['floating_populations'][] = [
                            'region_code' => $regionCode, 'base_ym' => $this->baseYm, 'base_yq' => '',
                            'day_type' => $dayType, 'time_band' => $band,
                            'gender' => $gender, 'age_band' => $age, 'population' => $perCell,
                            'created_at' => $now, 'updated_at' => $now,
                        ];
                    }
                }
            }
        }

        foreach ($rows as $table => $payload) {
            foreach (array_chunk($payload, 300) as $chunk) {
                DB::table($table)->insert($chunk);
            }
        }

        foreach (Taxonomy::HOUSING_TYPES as $type) {
            DB::table('households')->insert([
                'region_code' => $regionCode, 'base_ym' => $this->baseYm, 'base_yq' => '',
                'housing_type' => $type, 'households' => $perCell * 10,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        foreach (Taxonomy::SCHOOL_TYPES as $type) {
            DB::table('students')->insert([
                'region_code' => $regionCode, 'base_ym' => $this->baseYm, 'base_yq' => '',
                'school_type' => $type, 'student_count' => $perCell,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        DB::table('academies')->insert([
            ['region_code' => $regionCode, 'base_ym' => $this->baseYm, 'base_yq' => '', 'category' => 'education',
                'industry_name' => '수학학원', 'academy_count' => 12, 'created_at' => $now, 'updated_at' => $now],
            ['region_code' => $regionCode, 'base_ym' => $this->baseYm, 'base_yq' => '', 'category' => 'arts_sports',
                'industry_name' => '피아노/음악학원', 'academy_count' => 8, 'created_at' => $now, 'updated_at' => $now],
        ]);

        DB::table('industries')->insert([
            'code' => 'CS100001', 'name' => '한식음식점', 'group_name' => '요식',
            'sort_order' => 1, 'created_at' => $now, 'updated_at' => $now,
        ]);

        foreach (Taxonomy::DAY_TYPES as $dayType) {
            foreach (Taxonomy::TIME_BANDS as $band) {
                DB::table('card_sales')->insert([
                    'region_code' => $regionCode, 'base_ym' => $this->baseYm, 'base_yq' => '',
                    'industry_code' => 'CS100001', 'industry_name' => '한식음식점',
                    'day_type' => $dayType, 'time_band' => $band,
                    'sales_amount' => 10_000_000, 'sales_count' => 800,
                    'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }

        foreach (Taxonomy::GENDERS as $gender) {
            foreach (Taxonomy::AGE_BANDS as $age) {
                DB::table('card_sales_demographics')->insert([
                    'region_code' => $regionCode, 'base_ym' => $this->baseYm, 'base_yq' => '',
                    'industry_code' => 'CS100001', 'gender' => $gender, 'age_band' => $age,
                    'sales_amount' => 6_250_000, 'sales_count' => 500,
                    'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }
    }

    /**
     * 점포만 있는 지역(예: 경기도)을 만들기 위한 상가 데이터.
     * 업종코드를 넘기면 분야가 갈리는 경우까지 만들 수 있다.
     */
    protected function seedStores(
        string $regionCode,
        int $count = 5,
        string $middleName = '한식',
        string $largeCode = 'I2',
        string $middleCode = 'I201',
        string $smallCode = 'I20101',
        ?string $brandName = null,
    ): void {
        $now = now();
        $rows = [];
        $batch = ++$this->storeBatch;

        for ($i = 0; $i < $count; $i++) {
            $name = $brandName ?? "테스트점포{$regionCode}{$batch}{$i}";
            $classification = \App\Services\Stores\StoreClassifier::forRow($name, $largeCode, $middleCode, $smallCode);

            $rows[] = $classification + [
                'store_id' => "{$regionCode}-{$batch}-{$i}",
                'name' => $name,
                'region_code' => $regionCode,
                'sido_name' => '경기도',
                'sigungu_name' => '의정부시',
                'dong_name' => '테스트동',
                'large_code' => $largeCode, 'large_name' => '음식',
                'middle_code' => $middleCode, 'middle_name' => $middleName,
                'small_code' => $smallCode, 'small_name' => $middleName,
                'collected_at' => $now, 'created_at' => $now, 'updated_at' => $now,
            ];
        }

        DB::table('stores')->insert($rows);
    }
}
