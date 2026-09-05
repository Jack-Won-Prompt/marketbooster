<?php

namespace App\Services\OpenData\Seoul\Transformers;

use App\Support\Period;

/**
 * VwsmAdstrdSelngW — 서울시 상권분석서비스(추정매출-행정동)
 *
 * 행정동 × 서비스업종 한 행에 요일별 · 시간대별 · 성별 · 연령대별 합계가 함께 들어 있다.
 * card_sales(요일 × 시간대)와 card_sales_demographics(성별 × 연령) 두 테이블로 나눠 담는다.
 */
class CardSalesTransformer extends SeoulRowTransformer
{
    public function transform(array $row, Period $period): array
    {
        $regionCode = $this->regionCode($row);
        $industryCode = trim((string) ($row['SVC_INDUTY_CD'] ?? ''));

        if ($regionCode === null || $industryCode === '') {
            return [];
        }

        $industryName = trim((string) ($row['SVC_INDUTY_CD_NM'] ?? $industryCode));
        $columns = $period->columns();

        return [
            'card_sales' => $this->dayAndBandRows($row, $regionCode, $industryCode, $industryName, $columns),
            'card_sales_demographics' => $this->demographicRows($row, $regionCode, $industryCode, $columns),
            'industries' => [[
                'code' => $industryCode,
                'name' => $industryName,
            ]],
        ];
    }

    /** 요일 × 시간대 매출. 시간대 합계에 주중/주말 비율을 곱해 나눈다. */
    private function dayAndBandRows(array $row, string $regionCode, string $industryCode, string $industryName, array $columns): array
    {
        $amountBands = $this->timeBandValues($row, 'TMZON_%s_SELNG_AMT');
        $countBands = $this->timeBandValues($row, 'TMZON_%s_SELNG_CO');

        // 주중/주말은 원본이 직접 제공한다.
        $dayShares = $this->shares([
            'weekday' => $this->num($row, 'MDWK_SELNG_AMT'),
            'weekend' => $this->num($row, 'WKEND_SELNG_AMT'),
        ]);

        $rows = [];

        foreach ($amountBands as $band => $amount) {
            $count = $countBands[$band] ?? 0.0;

            if ($amount <= 0 && $count <= 0) {
                continue;
            }

            foreach ($dayShares as $dayType => $share) {
                $rows[] = [
                    'region_code' => $regionCode,
                    'industry_code' => $industryCode,
                    'industry_name' => $industryName,
                    'day_type' => $dayType,
                    'time_band' => $band,
                    'sales_amount' => (int) round($amount * $share),
                    'sales_count' => (int) round($count * $share),
                ] + $columns;
            }
        }

        return $rows;
    }

    /** 성별 × 연령 매출. 연령대 합계에 성별 비율을 곱해 나눈다. */
    private function demographicRows(array $row, string $regionCode, string $industryCode, array $columns): array
    {
        $ageAmounts = $this->ageValues($row, 'AGRDE_%s_SELNG_AMT');
        $ageCounts = $this->ageValues($row, 'AGRDE_%s_SELNG_CO');

        $genderShares = $this->genderShares(
            $this->num($row, 'ML_SELNG_AMT'),
            $this->num($row, 'FML_SELNG_AMT')
        );

        $rows = [];

        foreach ($ageAmounts as $ageBand => $amount) {
            $count = $ageCounts[$ageBand] ?? 0.0;

            if ($amount <= 0 && $count <= 0) {
                continue;
            }

            foreach ($genderShares as $gender => $share) {
                $rows[] = [
                    'region_code' => $regionCode,
                    'industry_code' => $industryCode,
                    'gender' => $gender,
                    'age_band' => $ageBand,
                    'sales_amount' => (int) round($amount * $share),
                    'sales_count' => (int) round($count * $share),
                ] + $columns;
            }
        }

        return $rows;
    }
}
