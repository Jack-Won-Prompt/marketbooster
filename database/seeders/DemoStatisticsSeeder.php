<?php

namespace Database\Seeders;

use App\Support\Taxonomy;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * 데모용 통계 생성기.
 *
 * 공공데이터포털 인증키를 받기 전에도 플랫폼 전체 흐름(가입 → 지역선택 → 리포트)을
 * 확인할 수 있도록, 행정동마다 재현 가능한 난수로 그럴듯한 통계를 만들어 넣는다.
 * 난수 시드는 행정동코드라 몇 번을 돌려도 같은 값이 나온다.
 *
 * 실제 데이터가 들어오면(opendata:sync / opendata:import) 같은 기준연월의 행이
 * upsert 로 덮어써지므로 이 데이터는 자연스럽게 교체된다.
 */
class DemoStatisticsSeeder extends Seeder
{
    /** 업무지구 성격이 강해 직장인구·유동인구가 크게 잡히는 행정동 */
    private const BUSINESS_DISTRICTS = [
        '여의동', '역삼1동', '역삼2동', '삼성1동', '삼성2동', '서초3동', '서초4동',
        '가양1동', '가양2동', '발산1동', '공항동', '을지로동', '명동', '소공동',
        '태평로', '광화문', '종로1.2.3.4가동', '구로3동', '가산동', '문정2동',
        '상암동', '서교동', '성수1가1동', '성수2가3동', '양재1동', '논현1동',
    ];

    /** 거주인구 연령 구성비 (서울 평균 근사) */
    private const RESIDENT_AGE_MIX = [
        'under10' => 0.045, '10s' => 0.065, '20s' => 0.150, '30s' => 0.170,
        '40s' => 0.150, '50s' => 0.160, '60s' => 0.140, '70s_over' => 0.120,
    ];

    private const WORKPLACE_AGE_MIX = [
        '20s' => 0.16, '30s' => 0.25, '40s' => 0.27, '50s' => 0.21, '60s' => 0.11,
    ];

    private const FLOATING_AGE_MIX = [
        'under10' => 0.04, '10s' => 0.07, '20s' => 0.17, '30s' => 0.20,
        '40s' => 0.19, '50s' => 0.16, '60s' => 0.11, '70s_over' => 0.06,
    ];

    /** 요일별 시간대 분포 (합 1.0 아님 — 점심 대비 상대값) */
    private const BAND_PROFILE = [
        'weekday' => ['morning' => 0.78, 'lunch' => 1.00, 'afternoon' => 0.75, 'evening' => 0.62, 'night' => 0.32],
        'weekend' => ['morning' => 0.50, 'lunch' => 0.95, 'afternoon' => 0.80, 'evening' => 0.68, 'night' => 0.42],
    ];

    /** 업종 대분류별 매출 시간대 분포 */
    private const SALES_BAND_PROFILE = [
        '요식' => ['morning' => 0.10, 'lunch' => 0.32, 'afternoon' => 0.14, 'evening' => 0.30, 'night' => 0.14],
        '소매' => ['morning' => 0.14, 'lunch' => 0.24, 'afternoon' => 0.22, 'evening' => 0.26, 'night' => 0.14],
        '서비스' => ['morning' => 0.16, 'lunch' => 0.24, 'afternoon' => 0.28, 'evening' => 0.24, 'night' => 0.08],
        '의료' => ['morning' => 0.30, 'lunch' => 0.26, 'afternoon' => 0.30, 'evening' => 0.12, 'night' => 0.02],
        '교육' => ['morning' => 0.12, 'lunch' => 0.18, 'afternoon' => 0.30, 'evening' => 0.34, 'night' => 0.06],
        '여가' => ['morning' => 0.18, 'lunch' => 0.14, 'afternoon' => 0.20, 'evening' => 0.36, 'night' => 0.12],
    ];

    /** 업종별 매출 구성비와 건당 평균 단가(원) */
    private const INDUSTRY_MIX = [
        'CS100001' => [0.200, 13000],  // 한식음식점
        'CS100002' => [0.040, 8000],   // 분식전문점
        'CS100003' => [0.090, 5500],   // 커피·음료
        'CS100004' => [0.030, 9000],   // 제과점
        'CS100005' => [0.060, 24000],  // 호프·간이주점
        'CS200001' => [0.100, 6500],   // 편의점
        'CS200002' => [0.080, 21000],  // 슈퍼마켓
        'CS200003' => [0.070, 48000],  // 의류·잡화
        'CS300001' => [0.050, 27000],  // 미용실
        'CS300002' => [0.015, 12000],  // 세탁·수선
        'CS400001' => [0.120, 16000],  // 일반의원
        'CS400002' => [0.060, 11000],  // 약국
        'CS500001' => [0.065, 230000], // 일반교습학원
        'CS600001' => [0.020, 85000],  // 헬스·필라테스
    ];

    private const ACADEMY_MIX = [
        'education' => ['종합학원', '수학학원', '영어학원', '국어/논술학원', '어린이영어학원'],
        'arts_sports' => ['피아노/음악학원', '서예/미술학원', '무용/댄스학원', '태권도장', '기타유아교육'],
    ];

    private string $baseYm;

    private array $industryGroups = [];

    public function run(): void
    {
        $this->baseYm = now()->subMonth()->format('Ym');
        $this->industryGroups = collect(IndustrySeeder::INDUSTRIES)
            ->mapWithKeys(fn (array $i) => [$i[0] => $i[2]])
            ->all();

        $regions = DB::table('regions')->select('code', 'dong_name', 'sigungu_name')->get();

        if ($regions->isEmpty()) {
            $this->command?->warn('행정동이 없습니다. RegionSeeder 를 먼저 실행하세요.');

            return;
        }

        $this->command?->info("데모 통계를 생성합니다 (기준연월 {$this->baseYm}, 행정동 {$regions->count()}곳)…");
        $this->purge();

        $buffers = $this->emptyBuffers();

        foreach ($regions as $region) {
            $profile = $this->profileFor($region->code, $region->dong_name);

            $this->buildResident($region->code, $profile, $buffers);
            $this->buildHouseholds($region->code, $profile, $buffers);
            $this->buildWorkplace($region->code, $profile, $buffers);
            $this->buildFloating($region->code, $profile, $buffers);
            $this->buildSales($region->code, $profile, $buffers);
            $this->buildEducation($region->code, $profile, $buffers);
            $this->buildMoveIns($region->code, $profile, $buffers);

            $this->flushIfLarge($buffers);
        }

        $this->flush($buffers, force: true);

        $this->command?->info('데모 통계 생성을 완료했습니다.');
    }

    /** 같은 기준연월의 기존 행을 지우고 새로 만든다. */
    private function purge(): void
    {
        foreach (['resident_populations', 'households', 'workplace_populations', 'floating_populations',
            'card_sales', 'card_sales_demographics', 'students', 'academies'] as $table) {
            DB::table($table)->where('base_ym', $this->baseYm)->delete();
        }

        DB::table('apartment_move_ins')->delete();
    }

    /** 행정동코드를 시드로 삼아 언제 돌려도 같은 값이 나오는 지역 프로파일을 만든다. */
    private function profileFor(string $code, string $dongName): array
    {
        mt_srand(crc32($code));

        $r = fn (float $min = 0, float $max = 1) => $min + (mt_rand(0, 1_000_000) / 1_000_000) * ($max - $min);

        $isBusiness = in_array($dongName, self::BUSINESS_DISTRICTS, true);
        $businessBoost = $isBusiness ? $r(2.8, 5.5) : $r(0.6, 2.2);

        $resident = (int) round($r(8_000, 38_000));
        $workplace = (int) round($resident * 0.30 * $businessBoost);
        $households = (int) round($resident / $r(2.0, 2.6));
        $lunchFloating = (int) round(($resident * 1.1 + $workplace * 2.4) * $r(0.75, 1.25));

        // 주거유형 구성비
        $mix = [
            'apartment' => $r(0.20, 0.65),
            'officetel' => $isBusiness ? $r(0.15, 0.60) : $r(0.02, 0.22),
            'villa' => $r(0.12, 0.45),
            'detached' => $r(0.02, 0.16),
        ];
        $mixSum = array_sum($mix);
        $mix = array_map(fn ($v) => $v / $mixSum, $mix);

        return [
            'rand' => $r,
            'is_business' => $isBusiness,
            'resident' => $resident,
            'workplace' => $workplace,
            'households' => $households,
            'lunch_floating' => $lunchFloating,
            'housing_mix' => $mix,
            'weekend_factor' => $r(0.55, 0.92),
            'male_share' => $r(0.465, 0.515),
            'sales_total' => (int) round(($lunchFloating * 30_000 + $resident * 50_000) * $r(0.7, 1.4)),
            'has_high_school' => $r() < 0.45,
            'has_university' => $r() < 0.08,
        ];
    }

    private function buildResident(string $code, array $profile, array &$buffers): void
    {
        $r = $profile['rand'];

        foreach (self::RESIDENT_AGE_MIX as $age => $share) {
            $bandTotal = $profile['resident'] * $share * $r(0.75, 1.3);
            // 20~30대는 여성, 60대 이상은 여성 비중이 조금 더 높게
            $maleShare = $profile['male_share'] * (in_array($age, ['60s', '70s_over'], true) ? 0.85 : 1.0);

            $buffers['resident_populations'][] = $this->row($code, [
                'gender' => 'M', 'age_band' => $age, 'population' => (int) round($bandTotal * $maleShare),
            ]);
            $buffers['resident_populations'][] = $this->row($code, [
                'gender' => 'F', 'age_band' => $age, 'population' => (int) round($bandTotal * (1 - $maleShare)),
            ]);
        }
    }

    private function buildHouseholds(string $code, array $profile, array &$buffers): void
    {
        foreach ($profile['housing_mix'] as $type => $share) {
            $buffers['households'][] = $this->row($code, [
                'housing_type' => $type,
                'households' => (int) round($profile['households'] * $share),
            ]);
        }
    }

    private function buildWorkplace(string $code, array $profile, array &$buffers): void
    {
        $r = $profile['rand'];

        foreach (self::WORKPLACE_AGE_MIX as $age => $share) {
            $bandTotal = $profile['workplace'] * $share * $r(0.85, 1.15);
            $maleShare = $r(0.46, 0.56);

            $buffers['workplace_populations'][] = $this->row($code, [
                'gender' => 'M', 'age_band' => $age, 'population' => (int) round($bandTotal * $maleShare),
            ]);
            $buffers['workplace_populations'][] = $this->row($code, [
                'gender' => 'F', 'age_band' => $age, 'population' => (int) round($bandTotal * (1 - $maleShare)),
            ]);
        }
    }

    private function buildFloating(string $code, array $profile, array &$buffers): void
    {
        $r = $profile['rand'];

        foreach (Taxonomy::DAY_TYPES as $dayType) {
            $dayFactor = $dayType === 'weekday' ? 1.0 : $profile['weekend_factor'];

            foreach (self::BAND_PROFILE[$dayType] as $band => $bandFactor) {
                $bandTotal = $profile['lunch_floating'] * $bandFactor * $dayFactor;

                foreach (self::FLOATING_AGE_MIX as $age => $ageShare) {
                    $cell = $bandTotal * $ageShare * $r(0.85, 1.15);
                    $maleShare = $r(0.47, 0.55);

                    $buffers['floating_populations'][] = $this->row($code, [
                        'day_type' => $dayType, 'time_band' => $band, 'gender' => 'M',
                        'age_band' => $age, 'population' => (int) round($cell * $maleShare),
                    ]);
                    $buffers['floating_populations'][] = $this->row($code, [
                        'day_type' => $dayType, 'time_band' => $band, 'gender' => 'F',
                        'age_band' => $age, 'population' => (int) round($cell * (1 - $maleShare)),
                    ]);
                }
            }
        }
    }

    private function buildSales(string $code, array $profile, array &$buffers): void
    {
        $r = $profile['rand'];

        // 업종 구성비에 지역별 편차를 준 뒤 다시 정규화한다.
        $mix = [];

        foreach (self::INDUSTRY_MIX as $industryCode => [$share, $ticket]) {
            $mix[$industryCode] = $share * $r(0.55, 1.6);
        }

        $mixSum = array_sum($mix);
        $weekdayShare = $r(0.66, 0.78);

        foreach ($mix as $industryCode => $weight) {
            $group = $this->industryGroups[$industryCode] ?? '기타';
            $ticket = self::INDUSTRY_MIX[$industryCode][1];
            $industryAmount = $profile['sales_total'] * ($weight / $mixSum);
            $industryName = $this->industryName($industryCode);

            foreach (Taxonomy::DAY_TYPES as $dayType) {
                $dayAmount = $industryAmount * ($dayType === 'weekday' ? $weekdayShare : 1 - $weekdayShare);

                foreach (self::SALES_BAND_PROFILE[$group] ?? self::SALES_BAND_PROFILE['소매'] as $band => $bandShare) {
                    $amount = (int) round($dayAmount * $bandShare);

                    $buffers['card_sales'][] = $this->row($code, [
                        'industry_code' => $industryCode,
                        'industry_name' => $industryName,
                        'day_type' => $dayType,
                        'time_band' => $band,
                        'sales_amount' => $amount,
                        'sales_count' => (int) round($amount / $ticket),
                    ]);
                }
            }

            // 성 × 연령 매출 (소비 성향을 반영해 30~50대 비중을 높게)
            foreach (self::FLOATING_AGE_MIX as $age => $ageShare) {
                $consumption = match ($age) {
                    'under10' => 0.1, '10s' => 0.35, '20s' => 0.9,
                    '30s' => 1.35, '40s' => 1.35, '50s' => 1.15, '60s' => 0.8, default => 0.5,
                };

                $cell = $industryAmount * $ageShare * $consumption * $r(0.85, 1.15);
                $maleShare = $group === '요식' ? $r(0.48, 0.58) : $r(0.38, 0.52);

                $buffers['card_sales_demographics'][] = $this->row($code, [
                    'industry_code' => $industryCode, 'gender' => 'M', 'age_band' => $age,
                    'sales_amount' => (int) round($cell * $maleShare),
                    'sales_count' => (int) round($cell * $maleShare / $ticket),
                ]);
                $buffers['card_sales_demographics'][] = $this->row($code, [
                    'industry_code' => $industryCode, 'gender' => 'F', 'age_band' => $age,
                    'sales_amount' => (int) round($cell * (1 - $maleShare)),
                    'sales_count' => (int) round($cell * (1 - $maleShare) / $ticket),
                ]);
            }
        }
    }

    private function buildEducation(string $code, array $profile, array &$buffers): void
    {
        $r = $profile['rand'];
        $under10 = $profile['resident'] * self::RESIDENT_AGE_MIX['under10'];
        $teens = $profile['resident'] * self::RESIDENT_AGE_MIX['10s'];

        $counts = [
            'daycare' => (int) round($under10 * $r(0.35, 0.75)),
            'kindergarten' => (int) round($under10 * $r(0.05, 0.20)),
            'elementary' => (int) round(($under10 * 0.30 + $teens * 0.35) * $r(0.7, 1.4)),
            'middle' => (int) round($teens * $r(0.20, 0.45)),
            'high' => $profile['has_high_school'] ? (int) round($teens * $r(0.25, 0.55)) : 0,
            'university' => $profile['has_university'] ? (int) round($profile['resident'] * $r(0.10, 0.45)) : 0,
        ];

        foreach ($counts as $type => $count) {
            $buffers['students'][] = $this->row($code, ['school_type' => $type, 'student_count' => $count]);
        }

        $studentScale = max(0.3, ($counts['elementary'] + $counts['middle']) / 1500);

        foreach (self::ACADEMY_MIX as $category => $names) {
            foreach ($names as $name) {
                $buffers['academies'][] = $this->row($code, [
                    'category' => $category,
                    'industry_name' => $name,
                    'academy_count' => (int) round($studentScale * $r(1, 22)),
                ]);
            }
        }
    }

    private function buildMoveIns(string $code, array $profile, array &$buffers): void
    {
        $r = $profile['rand'];

        if ($r() > 0.12) {
            return;
        }

        $count = mt_rand(1, 2);

        for ($i = 1; $i <= $count; $i++) {
            $buffers['apartment_move_ins'][] = [
                'region_code' => $code,
                'complex_name' => sprintf('%s 신축단지 %d차', substr($code, -3), $i),
                'households' => (int) round($r(180, 1600)),
                'move_in_ym' => now()->addMonths(mt_rand(3, 34))->format('Ym'),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
    }

    private function industryName(string $code): string
    {
        foreach (IndustrySeeder::INDUSTRIES as $industry) {
            if ($industry[0] === $code) {
                return $industry[1];
            }
        }

        return $code;
    }

    private function row(string $code, array $values): array
    {
        return array_merge([
            'region_code' => $code,
            'base_ym' => $this->baseYm,
            'created_at' => now(),
            'updated_at' => now(),
        ], $values);
    }

    private function emptyBuffers(): array
    {
        return [
            'resident_populations' => [],
            'households' => [],
            'workplace_populations' => [],
            'floating_populations' => [],
            'card_sales' => [],
            'card_sales_demographics' => [],
            'students' => [],
            'academies' => [],
            'apartment_move_ins' => [],
        ];
    }

    private function flushIfLarge(array &$buffers): void
    {
        foreach ($buffers as $table => $rows) {
            if (count($rows) >= 2000) {
                $this->insertChunks($table, $rows);
                $buffers[$table] = [];
            }
        }
    }

    private function flush(array &$buffers, bool $force = false): void
    {
        foreach ($buffers as $table => $rows) {
            if ($rows !== []) {
                $this->insertChunks($table, $rows);
                $buffers[$table] = [];
            }
        }
    }

    private function insertChunks(string $table, array $rows): void
    {
        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table($table)->insert($chunk);
        }
    }
}
