<?php

namespace App\Services\OpenData\Seoul\Transformers;

use App\Support\Period;
use App\Support\Taxonomy;

/**
 * 서울시 상권분석서비스의 가로형(wide) 한 행을 내부 세로형(long) 통계 행들로 편다.
 *
 * ─ 왜 추정이 섞이는가 ────────────────────────────────────────────────────────
 * 서울 API 는 교차표가 아니라 주변분포(marginal)만 준다.
 * 예를 들어 유동인구는 "시간대별 합계", "요일별 합계", "성별 합계", "연령대별 합계"를
 * 각각 줄 뿐 "평일 점심 30대 여성" 같은 칸은 주지 않는다.
 *
 * 우리 스키마는 교차표라서, 각 축의 비율을 곱해 칸을 채운다.
 *      칸 = 시간대합계 × 요일비율 × 성별비율 × 연령비율
 *
 * 이렇게 채우면 어느 축으로 합산하든 원본 주변분포가 그대로 복원된다.
 * 리포트가 보여 주는 값(시간대별 합계, 성·연령 합계 등)은 모두 주변합이므로 정확하고,
 * 개별 교차 칸만 독립 가정에 따른 추정치다.
 */
abstract class SeoulRowTransformer
{
    /** 서울 시간대 구간 => 내부 시간대. 00~06 은 새벽이라 '밤'에 합친다. */
    protected const TIME_BANDS = [
        '00_06' => 'night',
        '06_11' => 'morning',
        '11_14' => 'lunch',
        '14_17' => 'afternoon',
        '17_21' => 'evening',
        '21_24' => 'night',
    ];

    /** 서울 연령 구간 접미사 => 내부 연령대 */
    protected const AGE_BANDS = [
        '10' => '10s',
        '20' => '20s',
        '30' => '30s',
        '40' => '40s',
        '50' => '50s',
        '60_ABOVE' => '60s',
    ];

    protected const WEEKDAYS = ['MON', 'TUES', 'WED', 'THUR', 'FRI'];

    protected const WEEKEND_DAYS = ['SAT', 'SUN'];

    /**
     * @return array<string, array<int, array<string, mixed>>> 내부 데이터 종류 => 저장할 행 목록
     */
    abstract public function transform(array $row, Period $period): array;

    protected function num(array $row, string $key): float
    {
        $value = $row[$key] ?? null;

        if ($value === null || $value === '') {
            return 0.0;
        }

        return (float) preg_replace('/[^0-9.\-]/', '', (string) $value);
    }

    protected function regionCode(array $row): ?string
    {
        $code = preg_replace('/\D/', '', (string) ($row['ADSTRD_CD'] ?? ''));

        return $code !== '' ? $code : null;
    }

    /**
     * 값 배열을 비율로 바꾼다. 합이 0이면 균등 분배한다.
     *
     * @param  array<string, float>  $values
     * @return array<string, float>
     */
    protected function shares(array $values): array
    {
        $total = array_sum($values);

        if ($total <= 0) {
            $count = max(1, count($values));

            return array_map(fn () => 1 / $count, $values);
        }

        return array_map(fn ($v) => $v / $total, $values);
    }

    /**
     * 성별 합계로부터 비율을 구한다.
     *
     * @return array<string, float>
     */
    protected function genderShares(float $male, float $female): array
    {
        return $this->shares(['M' => $male, 'F' => $female]);
    }

    /**
     * 접두사 규칙으로 연령대별 값을 모은다. (예: AGRDE_%s_FLPOP_CO)
     *
     * @return array<string, float>
     */
    protected function ageValues(array $row, string $template): array
    {
        $values = [];

        foreach (self::AGE_BANDS as $suffix => $band) {
            $values[$band] = $this->num($row, sprintf($template, $suffix));
        }

        return $values;
    }

    /**
     * 시간대별 값을 내부 구간으로 접어 모은다. (00~06 은 밤에 합산)
     *
     * @return array<string, float>
     */
    protected function timeBandValues(array $row, string $template): array
    {
        $values = array_fill_keys(Taxonomy::TIME_BANDS, 0.0);

        foreach (self::TIME_BANDS as $suffix => $band) {
            $values[$band] += $this->num($row, sprintf($template, $suffix));
        }

        return $values;
    }

    /**
     * 요일별 값을 평일/주말로 접는다.
     *
     * @return array<string, float>
     */
    protected function dayTypeValues(array $row, string $template): array
    {
        $weekday = 0.0;
        $weekend = 0.0;

        foreach (self::WEEKDAYS as $day) {
            $weekday += $this->num($row, sprintf($template, $day));
        }

        foreach (self::WEEKEND_DAYS as $day) {
            $weekend += $this->num($row, sprintf($template, $day));
        }

        return ['weekday' => $weekday, 'weekend' => $weekend];
    }
}
