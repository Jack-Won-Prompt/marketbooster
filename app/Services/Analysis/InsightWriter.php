<?php

namespace App\Services\Analysis;

use App\Support\Korean;
use App\Support\Taxonomy;

/**
 * 집계 결과를 리포트의 "분석 결과" 문단으로 옮겨 쓴다.
 * 업로드된 인구분석 보고서와 같은 어투/구성을 따른다.
 */
class InsightWriter
{
    /**
     * @return array<int, string>
     */
    public function population(array $summary, string $reportDate): array
    {
        $selected = $summary['selected'];
        $sido = $summary['sido'];
        $sigungu = $summary['sigungu'];
        $sidoName = $summary['sido_name'];
        $sigunguName = $summary['sigungu_name'];
        $levels = $summary['levels'];
        $lines = [];

        $lines[] = sprintf(
            '선택지역의 %s(보고서추출일) 기준 총 거주인구 수(추정치)는 %s명 으로 %s 평균 대비 %s 수준입니다.',
            $reportDate,
            number_format($selected['resident']),
            $sidoName,
            $levels['resident']
        );

        $lines[] = sprintf(
            '배후세대는 %s 세대로 %s 평균보다 %s세대 %s %s 수준입니다.',
            number_format($selected['households']),
            $sidoName,
            number_format(abs($selected['households'] - (int) round($sido['households']))),
            $selected['households'] >= $sido['households'] ? '많은' : '적은',
            $levels['households']
        );

        $lines[] = sprintf(
            '선택지역의 점심시간 총 유동인구는 %s명으로 %s 평균 대비 %s 수준이며, 저녁시간 총 유동인구는 %s명으로 %s 평균 대비 %s 수준입니다.',
            number_format($selected['lunch_floating']),
            $sidoName,
            $levels['lunch_floating'],
            number_format($selected['evening_floating']),
            $sidoName,
            $levels['evening_floating']
        );

        $lines[] = sprintf(
            '해당 지역의 직장인구는 %s명으로 %s 평균 대비 %s명 %s, %s 평균 대비 %s명 %s 상권으로 볼 수 있습니다.',
            number_format($selected['workplace']),
            $sidoName,
            number_format(abs($selected['workplace'] - (int) round($sido['workplace']))),
            $selected['workplace'] >= $sido['workplace'] ? '많고' : '적고',
            $sigunguName,
            number_format(abs($selected['workplace'] - (int) round($sigungu['workplace']))),
            $selected['workplace'] >= $sigungu['workplace'] ? '많은' : '적은'
        );

        return $lines;
    }

    /**
     * @return array<int, string>
     */
    public function sales(array $sales, string $reportDate): array
    {
        if (($sales['total_amount'] ?? 0) <= 0) {
            return ['선택지역의 카드매출 데이터가 아직 수집되지 않았습니다. 관리자 화면에서 카드매출 데이터를 수집한 뒤 다시 분석해 주세요.'];
        }

        $lines = [];
        $top = $sales['by_industry'][0] ?? null;
        $topGroup = $sales['by_group'][0] ?? null;

        $lines[] = sprintf(
            '선택지역의 %s 기준 월 카드매출 추정 총액은 %s원이며, 총 %s건이 결제되어 건당 평균 %s원입니다.',
            $reportDate,
            $this->money($sales['total_amount']),
            number_format($sales['total_count']),
            number_format($sales['avg_ticket'])
        );

        if ($top) {
            $lines[] = sprintf(
                '업종별로는 %s %s원으로 전체 매출의 %s%%를 차지해 가장 큽니다.',
                Korean::withJosa($top['name'], '이/가'),
                $this->money($top['amount']),
                number_format($top['share'], 1)
            );
        }

        if ($topGroup) {
            $lines[] = sprintf(
                '업종 대분류 기준으로는 %s 업종의 비중이 %s%%로 가장 높습니다.',
                $topGroup['name'],
                number_format($topGroup['share'], 1)
            );
        }

        $peak = $sales['peak'] ?? null;

        if ($peak) {
            $lines[] = sprintf(
                '시간대별로는 %s %s 시간대(%s)의 매출이 가장 높아 이 시간대를 중심으로 한 운영 전략이 유효합니다.',
                Taxonomy::DAY_TYPE_LABELS[$peak['day_type']] ?? '',
                Taxonomy::TIME_BAND_LABELS[$peak['time_band']] ?? '',
                Taxonomy::TIME_BAND_RANGES[$peak['time_band']] ?? ''
            );
        }

        $topSegment = $sales['top_segment'] ?? null;

        if ($topSegment) {
            $segmentLabel = trim(sprintf(
                '%s %s',
                Taxonomy::AGE_LABELS[$topSegment['age_band']] ?? '',
                Taxonomy::GENDER_LABELS[$topSegment['gender']] ?? ''
            ));

            $lines[] = sprintf(
                '주요 소비층은 %s, 해당 구간이 전체 매출의 %s%%를 차지합니다.',
                Korean::withJosa($segmentLabel, '으로/로'),
                number_format($topSegment['share'], 1)
            );
        }

        return $lines;
    }

    /**
     * @return array<int, string>
     */
    public function education(array $education, array $summary, string $reportDate): array
    {
        $lines = [];
        $total = $education['students']['total'];

        $lines[] = sprintf(
            '선택지역의 %s(보고서추출일) 기준 총 학생 수는 %s명으로 %s 평균 대비 %s 수준입니다.',
            $reportDate,
            number_format($total),
            $summary['sido_name'],
            Taxonomy::level($total, (float) ($summary['sido']['students'] ?? 0))
        );

        $ranked = $education['students']['by_type'];
        arsort($ranked);
        $topTypes = array_slice(array_keys(array_filter($ranked, fn ($v) => $v > 0)), 0, 3);

        if ($topTypes !== []) {
            $lines[] = sprintf(
                '학생 유형 별로 분석한 결과, 해당 지역은 %s 순으로 학생 수가 높은 편입니다.',
                implode(' > ', array_map(fn ($t) => Taxonomy::SCHOOL_LABELS[$t] ?? $t, $topTypes))
            );
        }

        $byCategory = $education['academies']['by_category'];
        $edu = $byCategory['education'] ?? 0;
        $arts = $byCategory['arts_sports'] ?? 0;

        if ($edu + $arts > 0) {
            $lines[] = $edu >= $arts
                ? '선택지역의 학원 수를 분석한 결과, 해당지역은 학원(예체능) 학원보다 학원(교육/입시) 학원이 더 많은 지역입니다.'
                : '선택지역의 학원 수를 분석한 결과, 해당지역은 학원(교육/입시) 학원보다 학원(예체능) 학원이 더 많은 지역입니다.';
        }

        $topAcademy = $education['academies']['by_industry'][0] ?? null;

        if ($topAcademy) {
            $lines[] = sprintf(
                '상세 학원 업종으로 분석한 결과 %s 선택지역 내 %d개로 가장 많은 비율을 차지합니다.',
                Korean::withJosa($topAcademy['name'], '이/가'),
                $topAcademy['count']
            );
        }

        return $lines;
    }

    /** 억/만 단위로 읽기 쉽게 축약한다. */
    private function money(int $amount): string
    {
        if ($amount >= 100_000_000) {
            return number_format($amount / 100_000_000, 1).'억';
        }

        if ($amount >= 10_000) {
            return number_format($amount / 10_000).'만';
        }

        return number_format($amount);
    }
}
