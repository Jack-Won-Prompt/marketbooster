<?php

namespace App\Support;

/**
 * 상호명에서 프랜차이즈 브랜드를 알아낸다.
 *
 * 소상공인 상가정보의 상호(bizesNm)에는 지점이 붙어 오는 경우가 많다.
 *   "지에스25마곡" + 지점 "힐스테이트점", "이디야마곡" + 지점 "중앙점"
 * 그래서 상호를 그대로 묶으면 같은 브랜드가 수백 개로 쪼개진다.
 * 아래 사전은 실제 수집 데이터에서 확인한 표기(별칭 포함)를 패턴으로 갖는다.
 *
 * 사전에 없는 브랜드는 StoreClassifier 가 데이터로 찾아낸다.
 * (같은 상호가 여러 행정동에 걸쳐 반복되면 체인으로 본다)
 */
class Franchises
{
    /**
     * 대표 브랜드명 => [분야, 상호에서 찾을 패턴들]
     *
     * 패턴은 공백·기호를 없애고 영문을 대문자로 바꾼 상호와 대조한다.
     * 긴 패턴이 먼저 걸리도록 매칭 시 길이순으로 정렬한다.
     *
     * @var array<string, array{0: string, 1: array<int, string>}>
     */
    public const BRANDS = [
        // ── 카페 · 디저트 ──────────────────────────────────────
        '스타벅스' => ['cafe_dessert', ['스타벅스', 'STARBUCKS']],
        '이디야커피' => ['cafe_dessert', ['이디야', 'EDIYA']],
        '메가MGC커피' => ['cafe_dessert', ['메가엠지씨', '메가MGC', '메가커피']],
        '투썸플레이스' => ['cafe_dessert', ['투썸플레이스', '투썸', 'TWOSOME']],
        '빽다방' => ['cafe_dessert', ['빽다방']],
        '컴포즈커피' => ['cafe_dessert', ['컴포즈커피', '컴포즈']],
        '더벤티' => ['cafe_dessert', ['더벤티']],
        '커피에반하다' => ['cafe_dessert', ['커피에반하다']],
        '텐퍼센트커피' => ['cafe_dessert', ['텐퍼센트커피']],
        '커피빈' => ['cafe_dessert', ['커피빈', 'COFFEEBEAN']],
        '할리스' => ['cafe_dessert', ['할리스', 'HOLLYS']],
        '탐앤탐스' => ['cafe_dessert', ['탐앤탐스', 'TOMNTOMS']],
        '파스쿠찌' => ['cafe_dessert', ['파스쿠찌']],
        '엔제리너스' => ['cafe_dessert', ['엔제리너스']],
        '파리바게뜨' => ['cafe_dessert', ['파리바게뜨', '파리바게트', 'PARISBAGUETTE']],
        '뚜레쥬르' => ['cafe_dessert', ['뚜레쥬르', 'TOUSLESJOURS']],
        '배스킨라빈스' => ['cafe_dessert', ['배스킨라빈스', '베스킨라빈스', '배스킨', 'BASKINROBBINS']],
        '설빙' => ['cafe_dessert', ['설빙']],
        '요거트월드' => ['cafe_dessert', ['요거트월드']],
        '던킨' => ['cafe_dessert', ['던킨', 'DUNKIN']],
        '공차' => ['cafe_dessert', ['공차', 'GONGCHA']],

        // ── 치킨 ──────────────────────────────────────────────
        'BBQ치킨' => ['fastfood', ['비비큐', 'BBQ']],
        'BHC치킨' => ['fastfood', ['비에이치씨', 'BHC']],
        '교촌치킨' => ['fastfood', ['교촌']],
        '굽네치킨' => ['fastfood', ['굽네']],
        '네네치킨' => ['fastfood', ['네네치킨']],
        '처갓집양념치킨' => ['fastfood', ['처갓집']],
        '페리카나' => ['fastfood', ['페리카나']],
        '호식이두마리치킨' => ['fastfood', ['호식이']],
        '자담치킨' => ['fastfood', ['자담치킨']],
        '보드람치킨' => ['fastfood', ['보드람']],
        '또래오래' => ['fastfood', ['또래오래']],
        '노랑통닭' => ['fastfood', ['노랑통닭']],
        '가마치통닭' => ['fastfood', ['가마치']],
        '또봉이통닭' => ['fastfood', ['또봉이']],
        '멕시카나' => ['fastfood', ['멕시카나', '멕시칸']],
        '지코바' => ['fastfood', ['지코바']],
        '부어치킨' => ['fastfood', ['부어치킨']],
        '푸라닭' => ['fastfood', ['푸라닭']],
        '깐부치킨' => ['fastfood', ['깐부치킨']],
        '후라이드참잘하는집' => ['fastfood', ['후라이드참잘하는집']],

        // ── 버거 · 분식 · 간편식 ──────────────────────────────
        '맘스터치' => ['fastfood', ['맘스터치']],
        '롯데리아' => ['fastfood', ['롯데리아', 'LOTTERIA']],
        '맥도날드' => ['fastfood', ['맥도날드', 'MCDONALD']],
        '버거킹' => ['fastfood', ['버거킹', 'BURGERKING']],
        'KFC' => ['fastfood', ['KFC']],
        '노브랜드버거' => ['fastfood', ['노브랜드버거']],
        '서브웨이' => ['fastfood', ['서브웨이', 'SUBWAY']],
        '이삭토스트' => ['fastfood', ['이삭토스트']],
        '봉구스밥버거' => ['fastfood', ['봉구스']],
        '김밥천국' => ['fastfood', ['김밥천국']],
        '김가네' => ['fastfood', ['김가네']],
        '바르다김선생' => ['fastfood', ['바르다김선생']],
        '한솥도시락' => ['fastfood', ['한솥']],
        '본도시락' => ['fastfood', ['본도시락']],
        '신전떡볶이' => ['fastfood', ['신전떡볶이']],
        '엽기떡볶이' => ['fastfood', ['엽기떡볶이']],
        '배떡' => ['fastfood', ['배떡']],
        '죠스떡볶이' => ['fastfood', ['죠스떡볶이']],

        // ── 피자 ──────────────────────────────────────────────
        '도미노피자' => ['fastfood', ['도미노피자', 'DOMINO']],
        '피자헛' => ['fastfood', ['피자헛', 'PIZZAHUT']],
        '미스터피자' => ['fastfood', ['미스터피자']],
        '파파존스' => ['fastfood', ['파파존스', 'PAPAJOHN']],
        '피자스쿨' => ['fastfood', ['피자스쿨']],
        '피자마루' => ['fastfood', ['피자마루']],
        '피자알볼로' => ['fastfood', ['피자알볼로']],
        '피자나라치킨공주' => ['fastfood', ['피자나라']],
        '반올림피자' => ['fastfood', ['반올림피자']],

        // ── 식당 ──────────────────────────────────────────────
        '본죽' => ['restaurant', ['본죽']],
        '한촌설렁탕' => ['restaurant', ['한촌설렁탕']],
        '신선설농탕' => ['restaurant', ['신선설농탕']],
        '새마을식당' => ['restaurant', ['새마을식당']],
        '홍콩반점0410' => ['restaurant', ['홍콩반점']],
        '역전우동' => ['restaurant', ['역전우동']],
        '유가네닭갈비' => ['restaurant', ['유가네']],
        'original불닭' => ['restaurant', ['오리지널불닭']],
        '두찜' => ['restaurant', ['두찜']],
        '명륜진사갈비' => ['restaurant', ['명륜진사갈비']],
        '하남돼지집' => ['restaurant', ['하남돼지집']],
        '연안식당' => ['restaurant', ['연안식당']],
        '아웃백' => ['restaurant', ['아웃백', 'OUTBACK']],
        '빕스' => ['restaurant', ['빕스', 'VIPS']],

        // ── 주점 ──────────────────────────────────────────────
        '투다리' => ['pub', ['투다리']],
        '역전할머니맥주' => ['pub', ['역전할머니맥주']],
        '금별맥주' => ['pub', ['금별맥주']],
        '펀비어킹' => ['pub', ['펀비어킹']],
        'compose청담동말자싸롱' => ['pub', ['말자싸롱']],
        '치어스' => ['pub', ['치어스']],
        '와이키키브라더스' => ['pub', ['와이키키브라더스']],

        // ── 편의점 · 마트 ─────────────────────────────────────
        'GS25' => ['convenience', ['지에스25', 'GS25']],
        'CU' => ['convenience', ['씨유', 'CU편의점']],
        '세븐일레븐' => ['convenience', ['세븐일레븐', '７ELEVEN', '7ELEVEN']],
        '이마트24' => ['convenience', ['이마트24', 'EMART24']],
        '노브랜드' => ['convenience', ['노브랜드']],

        // ── 뷰티 · 헬스 ───────────────────────────────────────
        '올리브영' => ['beauty', ['올리브영', 'OLIVEYOUNG']],
        '아리따움' => ['beauty', ['아리따움']],
        '이니스프리' => ['beauty', ['이니스프리', 'INNISFREE']],
        '더페이스샵' => ['beauty', ['더페이스샵']],
        '박승철헤어스투디오' => ['beauty', ['박승철']],
        '준오헤어' => ['beauty', ['준오헤어']],
        '리안헤어' => ['beauty', ['리안헤어']],
        '블루클럽' => ['beauty', ['블루클럽']],
        '이철헤어커커' => ['beauty', ['이철헤어']],

        // ── 생활 · 소매 ───────────────────────────────────────
        '다이소' => ['retail_etc', ['다이소', 'DAISO']],
        '무신사' => ['fashion', ['무신사']],
        '유니클로' => ['fashion', ['유니클로', 'UNIQLO']],
        '탑텐' => ['fashion', ['탑텐', 'TOPTEN']],
        '스파오' => ['fashion', ['스파오', 'SPAO']],
        '크린토피아' => ['life_service', ['크린토피아']],
        '워시엔조이' => ['life_service', ['워시엔조이']],

        // ── 교육 ──────────────────────────────────────────────
        '눈높이' => ['education', ['눈높이']],
        '구몬' => ['education', ['구몬']],
        '윤선생' => ['education', ['윤선생']],
        '재능교육' => ['education', ['재능교육']],
        '메가스터디' => ['education', ['메가스터디']],
    ];

    /** 상호로 볼 수 없는 값 (상가정보에 실제로 들어 있다) */
    private const NOT_A_NAME = ['업소명없음', '상호명없음', '무상호', ''];

    /**
     * 비교하기 좋게 상호를 정규화한다.
     * 공백·기호를 없애고 영문은 대문자로 맞춘다.
     */
    public static function normalize(?string $name): string
    {
        $name = (string) $name;
        $name = preg_replace('/[\s\p{P}\p{S}]+/u', '', $name) ?? '';

        return mb_strtoupper($name);
    }

    public static function isUsableName(?string $name): bool
    {
        $normalized = self::normalize($name);

        if ($normalized === '' || mb_strlen($normalized) < 2) {
            return false;
        }

        foreach (self::NOT_A_NAME as $bad) {
            if ($normalized === self::normalize($bad)) {
                return false;
            }
        }

        return true;
    }

    /**
     * 상호에서 사전에 등록된 브랜드를 찾는다.
     *
     * @return array{0: string, 1: string}|null  [대표 브랜드명, 분야]
     */
    public static function match(?string $name): ?array
    {
        if (! self::isUsableName($name)) {
            return null;
        }

        $normalized = self::normalize($name);

        foreach (self::patterns() as [$pattern, $brand, $sector]) {
            if (str_contains($normalized, $pattern)) {
                return [$brand, $sector];
            }
        }

        return null;
    }

    /**
     * 패턴을 긴 것부터 늘어놓는다.
     * "피자나라치킨공주" 가 "피자" 보다 먼저 걸려야 한다.
     *
     * @return array<int, array{0:string, 1:string, 2:string}>
     */
    public static function patterns(): array
    {
        static $sorted = null;

        if ($sorted !== null) {
            return $sorted;
        }

        $rows = [];

        foreach (self::BRANDS as $brand => [$sector, $patterns]) {
            foreach ($patterns as $pattern) {
                $rows[] = [self::normalize($pattern), $brand, $sector];
            }
        }

        usort($rows, fn ($a, $b) => mb_strlen($b[0]) <=> mb_strlen($a[0]));

        return $sorted = $rows;
    }
}
