<?php

namespace App\Support;

/**
 * 점포를 "분야" 로 묶는다.
 *
 * 소상공인 상가정보의 표준 업종코드(대/중/소분류)는 상권을 볼 때 너무 잘게 쪼개져 있다.
 * (예: 카페 · 빵/도넛 · 아이스크림이 서로 다른 중분류에 흩어져 있다)
 * 그래서 창업·프랜차이즈 관점에서 의미가 맞는 단위로 다시 묶는다.
 *
 * 판별 순서는 소분류 → 중분류 → 대분류. 좁은 코드가 항상 이긴다.
 */
class StoreSectors
{
    public const UNKNOWN = 'etc';

    /** 분야 코드 => 화면에 쓰는 이름 */
    public const LABELS = [
        'restaurant' => '식당',
        'cafe_dessert' => '카페·디저트',
        'fastfood' => '패스트푸드·분식',
        'pub' => '주점',
        'convenience' => '편의점·마트',
        'food_retail' => '식품 소매',
        'fashion' => '패션·잡화',
        'beauty' => '뷰티·미용',
        'health' => '의료·건강',
        'education' => '교육·학원',
        'sports' => '스포츠·여가',
        'lodging' => '숙박',
        'life_service' => '생활 서비스',
        'professional' => '전문 서비스',
        'retail_etc' => '기타 소매',
        self::UNKNOWN => '기타',
    ];

    /**
     * 소분류코드 => 분야. 같은 중분류 안에서 갈라져야 하는 것만 적는다.
     * (빵/도넛·떡·아이스크림은 "기타 간이" 지만 디저트로 봐야 한다)
     */
    public const BY_SMALL = [
        'I21001' => 'cafe_dessert',   // 빵/도넛
        'I21002' => 'cafe_dessert',   // 떡/한과
        'I21008' => 'cafe_dessert',   // 아이스크림/빙수
        'I21003' => 'fastfood',       // 피자
        'I21004' => 'fastfood',       // 버거
        'I21005' => 'fastfood',       // 토스트/샌드위치/샐러드
        'I21006' => 'fastfood',       // 치킨
        'I21007' => 'fastfood',       // 김밥/만두/분식
    ];

    /** 중분류코드 => 분야 */
    public const BY_MIDDLE = [
        'I201' => 'restaurant', 'I202' => 'restaurant', 'I203' => 'restaurant',
        'I204' => 'restaurant', 'I205' => 'restaurant', 'I206' => 'restaurant',
        'I207' => 'restaurant',
        'I210' => 'fastfood',
        'I211' => 'pub',
        'I212' => 'cafe_dessert',
        'I101' => 'lodging', 'I102' => 'lodging',
        'G204' => 'convenience',
        'G205' => 'food_retail', 'G206' => 'food_retail', 'G207' => 'food_retail',
        'G209' => 'fashion', 'G211' => 'fashion', 'G212' => 'fashion',
        'G216' => 'fashion', 'G217' => 'fashion', 'G218' => 'fashion',
        'G215' => 'beauty',
        'S207' => 'beauty', 'S208' => 'beauty',
        'Q101' => 'health', 'Q102' => 'health', 'Q104' => 'health',
        'P105' => 'education', 'P106' => 'education', 'P107' => 'education',
        'R102' => 'sports', 'R103' => 'sports', 'R104' => 'sports',
    ];

    /** 대분류코드 => 분야 (위에서 안 걸린 나머지) */
    public const BY_LARGE = [
        'G2' => 'retail_etc',
        'S2' => 'life_service',
        'N1' => 'life_service',
        'M1' => 'professional',
        'L1' => 'professional',
        'I1' => 'lodging',
        'I2' => 'restaurant',
        'P1' => 'education',
        'Q1' => 'health',
        'R1' => 'sports',
    ];

    /** 화면에 늘어놓을 순서 */
    public const ORDER = [
        'restaurant', 'cafe_dessert', 'fastfood', 'pub', 'convenience', 'food_retail',
        'beauty', 'health', 'education', 'fashion', 'sports', 'lodging',
        'life_service', 'professional', 'retail_etc', self::UNKNOWN,
    ];

    /**
     * 대분류 6묶음. 상권 보고서에서 "음식 425개 매장" 처럼 크게 세는 단위다.
     *
     * 소상공인 표준 대분류는 10개로 쪼개져 있어 그대로 늘어놓으면 읽기 어렵다.
     * 서비스 성격의 대분류들을 하나로 묶어 6개로 줄인다.
     *
     * @var array<string, array{0: string, 1: array<int, string>}>  묶음 => [이름, 대분류코드들]
     */
    public const GROUPS = [
        'food' => ['음식', ['I2']],
        'retail' => ['소매', ['G2']],
        'service' => ['서비스', ['S2', 'M1', 'N1', 'L1', 'Q1']],
        'leisure' => ['오락', ['R1']],
        'education' => ['교육', ['P1']],
        'lodging' => ['숙박', ['I1']],
    ];

    /**
     * 카드매출 업종 대분류(industries.group_name) => 위 6묶음.
     *
     * 카드매출은 서울시 상권분석서비스의 업종 체계라 상가정보 업종코드와 다르다.
     * 같은 화면에서 "음식 매장 수 / 음식 매출" 을 나란히 보여 주려면 둘을 같은 칸에 넣어야 한다.
     *
     * @var array<string, string>
     */
    public const SALES_GROUPS = [
        '요식' => 'food',
        '소매' => 'retail',
        '서비스' => 'service',
        '의료' => 'service',
        '여가' => 'leisure',
        '교육' => 'education',
        '숙박' => 'lodging',
    ];

    /** 대분류코드 => 묶음 코드 */
    public static function groupOf(?string $largeCode): ?string
    {
        foreach (self::GROUPS as $key => [, $codes]) {
            if (in_array((string) $largeCode, $codes, true)) {
                return $key;
            }
        }

        return null;
    }

    public static function groupLabel(string $group): string
    {
        return self::GROUPS[$group][0] ?? '기타';
    }

    public static function resolve(?string $large, ?string $middle, ?string $small): string
    {
        // 코드가 비어 오는 행이 있어 null 을 그대로 첨자로 쓰지 않는다.
        return self::BY_SMALL[(string) $small]
            ?? self::BY_MIDDLE[(string) $middle]
            ?? self::BY_LARGE[(string) $large]
            ?? self::UNKNOWN;
    }

    public static function label(string $sector): string
    {
        return self::LABELS[$sector] ?? self::LABELS[self::UNKNOWN];
    }

    /** @return array<int, string> */
    public static function codes(): array
    {
        return self::ORDER;
    }
}
