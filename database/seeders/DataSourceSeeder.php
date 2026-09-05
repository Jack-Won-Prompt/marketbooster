<?php

namespace Database\Seeders;

use App\Models\DataSource;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * 리포트 마지막 장 "데이터 출처" 표의 기본 행.
 * 실제 수집이 일어나면 DatasetSynchronizer 가 base_ym / base_label 을 갱신한다.
 */
class DataSourceSeeder extends Seeder
{
    public function run(): void
    {
        $baseYm = now()->subMonth()->format('Ym');
        $label = Carbon::createFromFormat('Ym', $baseYm)->format('Y년 n월');

        $rows = [
            ['resident_population', 'population', '거주 인구(추정)', '행정안전부 주민등록인구', 1],
            ['households', 'population', '배후세대', '국토교통부 건축물대장', 2],
            ['workplace_population', 'population', '직장인구', '통계청 전국사업체조사', 3],
            ['floating_population', 'population', '유동인구', '공공데이터포털 지역별 유동인구 통계', 4],
            ['card_sales', 'sales', '카드매출', '공공데이터포털 지역별 카드매출 통계', 5],
            ['students', 'education', '학생 수', '한국교육학술정보원 학교알리미', 6],
            ['academies', 'education', '학원 수', '지방행정인허가데이터 학원교습소', 7],
        ];

        foreach ($rows as [$key, $category, $labelName, $provider, $order]) {
            DataSource::updateOrCreate(
                ['key' => $key],
                [
                    'category' => $category,
                    'label' => $labelName,
                    'provider' => $provider,
                    'base_ym' => $baseYm,
                    'base_label' => $label,
                    'sort_order' => $order,
                ]
            );
        }

        $this->command?->info('데이터 출처 '.count($rows).'건을 적재했습니다.');
    }
}
