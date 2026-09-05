<?php

namespace App\Support;

/**
 * 플랫폼 전역에서 쓰는 코드값과 한글 라벨 사전.
 * 수집(정규화) · 집계 · 리포트 출력이 모두 이 순서와 라벨을 공유한다.
 */
class Taxonomy
{
    public const AGE_BANDS = ['under10', '10s', '20s', '30s', '40s', '50s', '60s', '70s_over'];

    public const AGE_LABELS = [
        'under10' => '10대 미만',
        '10s' => '10대',
        '20s' => '20대',
        '30s' => '30대',
        '40s' => '40대',
        '50s' => '50대',
        '60s' => '60대',
        '70s_over' => '70대 이상',
    ];

    /** 직장인구는 20~60대만 집계된다. */
    public const WORK_AGE_BANDS = ['20s', '30s', '40s', '50s', '60s'];

    /** 서울시 상권분석서비스가 제공하는 연령 구간 (10대 미만 · 70대 이상은 제공하지 않음) */
    public const SEOUL_AGE_BANDS = ['10s', '20s', '30s', '40s', '50s', '60s'];

    public const GENDERS = ['M', 'F'];

    public const GENDER_LABELS = ['M' => '남성', 'F' => '여성'];

    public const DAY_TYPES = ['weekday', 'weekend'];

    public const DAY_TYPE_LABELS = ['weekday' => '평일', 'weekend' => '주말'];

    public const TIME_BANDS = ['morning', 'lunch', 'afternoon', 'evening', 'night'];

    public const TIME_BAND_LABELS = [
        'morning' => '오전',
        'lunch' => '점심',
        'afternoon' => '오후',
        'evening' => '저녁',
        'night' => '밤',
    ];

    public const TIME_BAND_RANGES = [
        'morning' => '6:00 - 10:59',
        'lunch' => '11:00 - 14:59',
        'afternoon' => '15:00 - 17:59',
        'evening' => '18:00 - 20:59',
        'night' => '21:00 - 05:59',
    ];

    public const HOUSING_TYPES = ['apartment', 'officetel', 'villa', 'detached', 'non_apartment'];

    public const HOUSING_LABELS = [
        'apartment' => '아파트',
        'officetel' => '오피스텔',
        'villa' => '빌라',
        'detached' => '단독주택',
        // 서울시 상권분석서비스는 아파트/비아파트로만 구분해 제공한다.
        'non_apartment' => '비아파트',
    ];

    public const SCHOOL_TYPES = ['daycare', 'kindergarten', 'elementary', 'middle', 'high', 'university'];

    public const SCHOOL_LABELS = [
        'daycare' => '어린이집',
        'kindergarten' => '유치원',
        'elementary' => '초등학생',
        'middle' => '중학생',
        'high' => '고등학생',
        'university' => '대학생',
    ];

    public const ACADEMY_CATEGORIES = ['education' => '학원(교육/입시)', 'arts_sports' => '학원(예체능)'];

    public const INDUSTRY_GROUPS = ['요식', '소매', '서비스', '의료', '교육', '여가'];

    /** 상대 수준 판정 구간: 지역값 / 비교대상 평균 비율 → 등급 라벨 */
    public const LEVEL_THRESHOLDS = [
        [2.0, '매우 높음'],
        [1.3, '높음'],
        [0.8, '보통'],
        [0.5, '낮음'],
        [0.0, '매우 낮음'],
    ];

    public static function label(string $dictionary, ?string $key, string $fallback = '-'): string
    {
        $map = match ($dictionary) {
            'age' => self::AGE_LABELS,
            'gender' => self::GENDER_LABELS,
            'day_type' => self::DAY_TYPE_LABELS,
            'time_band' => self::TIME_BAND_LABELS,
            'housing' => self::HOUSING_LABELS,
            'school' => self::SCHOOL_LABELS,
            'academy' => self::ACADEMY_CATEGORIES,
            default => [],
        };

        return $map[$key] ?? $fallback;
    }

    /** 선택지역 값이 비교 평균 대비 어느 수준인지 판정한다. */
    public static function level(float $value, float $average): string
    {
        if ($average <= 0) {
            return $value > 0 ? '매우 높음' : '보통';
        }

        $ratio = $value / $average;

        foreach (self::LEVEL_THRESHOLDS as [$floor, $label]) {
            if ($ratio >= $floor) {
                return $label;
            }
        }

        return '매우 낮음';
    }
}
