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

    /**
     * 숫자 값을 읽는다.
     * 서울 API 는 큰 금액을 지수 표기(3.609852542E9)로 내려보내므로
     * 기호를 먼저 지우면 안 된다. 숫자로 해석되면 그대로 캐스팅한다.
     */
    protected function num(array $row, string $key): float
    {
        $value = $row[$key] ?? null;

        if ($value === null || $value === '') {
            return 0.0;
        }

        if (is_int($value) || is_float($value) || is_numeric($value)) {
            return (float) $value;
        }

        return (float) preg_replace('/[^0-9.\-]/', '', (string) $value);
    }

    /**
     * 기간 동안 누적된 값을 일평균으로 환산한다.
     *
     * 서울시 상권분석서비스의 유동인구·매출은 분기 내내 쌓은 합계다.
     * (가양1동 2026년 2분기 총 유동인구 3,024,152명 → 하루 약 33,000명)
     * 그대로 리포트에 실으면 하루 수치처럼 읽히므로 적재 시점에 일평균으로 맞춘다.
     *
     * 평일·주말은 반드시 각자의 일수로 나눠야 한다.
     * 한 분기는 평일 약 65일 / 주말 약 26일이라, 평일 누적을 전체 91일로 나누면
     * 평일 하루 값이 3분의 1 수준으로 깎인다.
     *
     * 상주인구·직장인구·가구 수는 누적이 아니라 그 시점의 규모이므로 나누지 않는다.
     */
    protected function perDay(float $value, Period $period, ?string $dayType = null): float
    {
        $counts = $period->dayCounts();

        $divisor = match ($dayType) {
            'weekday' => $counts['weekday'],
            'weekend' => $counts['weekend'],
            default => $counts['weekday'] + $counts['weekend'],
        };

        return $value / max(1, $divisor);
    }

    /**
     * 이 행이 실제로 어느 분기의 값인지 읽는다.
     *
     * 서울 API 는 서비스마다 분기 필터(STDR_YYQU_CD)를 지키기도 하고 무시하기도 한다.
     * (2026-09 확인: 추정매출·직장인구는 적용, 상주인구·길단위인구는 무시하고 전 분기를 준다)
     * 요청 분기를 그대로 찍으면 다른 분기 값이 요청 분기로 둔갑하므로,
     * 반드시 행에 실린 값을 기준으로 삼는다.
     */
    public function periodOf(array $row, Period $fallback): Period
    {
        $code = preg_replace('/\D/', '', (string) ($row['STDR_YYQU_CD'] ?? ''));

        return preg_match('/^\d{4}[1-4]$/', $code) ? Period::quarter($code) : $fallback;
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
