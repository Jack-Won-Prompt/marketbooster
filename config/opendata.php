<?php

return [
    /*
    |--------------------------------------------------------------------------
    | 공공데이터포털 (data.go.kr) 공통 설정
    |--------------------------------------------------------------------------
    | 인증키는 "Decoding" 키를 .env 의 OPENDATA_SERVICE_KEY 에 넣습니다.
    | 키가 비어 있으면 수집 커맨드는 실행되지 않고 안내 메시지를 남깁니다.
    */
    'service_key' => env('OPENDATA_SERVICE_KEY'),
    'timeout' => (int) env('OPENDATA_TIMEOUT', 30),
    'verify_ssl' => filter_var(env('OPENDATA_VERIFY_SSL', false), FILTER_VALIDATE_BOOL),
    'retry' => ['times' => 3, 'sleep_ms' => 400],
    'page_size' => 1000,

    /*
    |--------------------------------------------------------------------------
    | 데이터셋 엔드포인트
    |--------------------------------------------------------------------------
    | data.go.kr 오픈 API 는 제공기관마다 경로/파라미터가 다릅니다.
    | 아래 정의를 실제 신청한 활용 API 에 맞춰 수정하면 수집 커맨드는 그대로 동작합니다.
    |   url        : 엔드포인트 (getXxx 까지)
    |   params     : 고정 파라미터
    |   items_path : 응답 JSON 에서 목록이 위치한 경로 (점 표기)
    |   map        : 응답 필드 후보 → 내부 컬럼 매핑 (앞에서부터 존재하는 키를 사용)
    */
    'datasets' => [

        'floating_population' => [
            'label' => '지역별 유동인구 통계',
            'url' => env('OPENDATA_FLOATING_URL', 'https://apis.data.go.kr/B552015/FloatingPopulationService/getFloatingPopulation'),
            'params' => ['type' => 'json'],
            'items_path' => 'response.body.items.item',
            'map' => [
                'region_code' => ['ADMI_CD', 'admiCd', 'HDONG_CD', 'adstrd_code'],
                'base_ym' => ['STDR_YM', 'stdrYm', 'BASE_YM', 'STD_YM'],
                'day_type' => ['DAY_TP', 'dayTp', 'WEEK_TP'],
                'time_band' => ['TIME_ZONE', 'timeZone', 'TMZON_PD_SE'],
                'gender' => ['SEX_CD', 'sexCd', 'GENDER'],
                'age_band' => ['AGE_CD', 'ageCd', 'AGRDE_SE_CD'],
                'population' => ['POPLTN_CNT', 'popltnCnt', 'FLOW_POP_CNT', 'VALUE'],
            ],
        ],

        'card_sales' => [
            'label' => '지역별 카드 매출 통계',
            'url' => env('OPENDATA_CARD_SALES_URL', 'https://apis.data.go.kr/B190001/CardSalesService/getCardSales'),
            'params' => ['type' => 'json'],
            'items_path' => 'response.body.items.item',
            'map' => [
                'region_code' => ['ADMI_CD', 'admiCd', 'HDONG_CD', 'adstrd_code'],
                'base_ym' => ['STDR_YM', 'stdrYm', 'BASE_YM', 'TA_YMD'],
                'industry_code' => ['UPJONG_CD', 'upjongCd', 'MCT_CAT_CD', 'SVC_INDUTY_CD'],
                'industry_name' => ['UPJONG_NM', 'upjongNm', 'MCT_CAT_NM', 'SVC_INDUTY_CD_NM'],
                'day_type' => ['DAY_TP', 'dayTp', 'WEEK_TP'],
                'time_band' => ['TIME_ZONE', 'timeZone', 'TMZON_PD_SE'],
                'gender' => ['SEX_CD', 'sexCd', 'GENDER'],
                'age_band' => ['AGE_CD', 'ageCd', 'AGRDE_SE_CD'],
                'sales_amount' => ['AMT', 'amt', 'SALE_AMT', 'THSMON_SELNG_AMT'],
                'sales_count' => ['CNT', 'cnt', 'SALE_CNT', 'THSMON_SELNG_CO'],
            ],
        ],

        'resident_population' => [
            'label' => '행정동별 주민등록 인구',
            'url' => env('OPENDATA_RESIDENT_URL'),
            'params' => ['type' => 'json'],
            'items_path' => 'response.body.items.item',
            'map' => [
                'region_code' => ['ADMI_CD', 'admiCd', 'HDONG_CD'],
                'base_ym' => ['STDR_YM', 'stdrYm', 'BASE_YM'],
                'gender' => ['SEX_CD', 'sexCd'],
                'age_band' => ['AGE_CD', 'ageCd'],
                'population' => ['POPLTN_CNT', 'popltnCnt'],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | 코드 정규화 사전
    |--------------------------------------------------------------------------
    | 기관마다 성별/연령/시간대 코드 표기가 달라 내부 표준값으로 변환합니다.
    */
    'normalize' => [
        'gender' => [
            'M' => 'M', '1' => 'M', '남' => 'M', '남성' => 'M', 'MALE' => 'M',
            'F' => 'F', '2' => 'F', '여' => 'F', '여성' => 'F', 'FEMALE' => 'F',
        ],
        'day_type' => [
            'WEEKDAY' => 'weekday', '1' => 'weekday', '평일' => 'weekday', 'WD' => 'weekday',
            'WEEKEND' => 'weekend', '2' => 'weekend', '주말' => 'weekend', 'WE' => 'weekend',
        ],
        'time_band' => [
            '00' => 'morning', '06' => 'morning', '오전' => 'morning', 'MORNING' => 'morning',
            '11' => 'lunch', '점심' => 'lunch', 'LUNCH' => 'lunch',
            '15' => 'afternoon', '오후' => 'afternoon', 'AFTERNOON' => 'afternoon',
            '18' => 'evening', '저녁' => 'evening', 'EVENING' => 'evening',
            '21' => 'night', '밤' => 'night', 'NIGHT' => 'night',
        ],
        'age_band' => [
            '00' => 'under10', '0' => 'under10', '10대미만' => 'under10', 'UNDER10' => 'under10',
            '10' => '10s', '10대' => '10s',
            '20' => '20s', '20대' => '20s',
            '30' => '30s', '30대' => '30s',
            '40' => '40s', '40대' => '40s',
            '50' => '50s', '50대' => '50s',
            '60' => '60s', '60대' => '60s',
            '70' => '70s_over', '70대이상' => '70s_over', '70대 이상' => '70s_over',
        ],
        'housing_type' => [
            '아파트' => 'apartment', 'APT' => 'apartment',
            '오피스텔' => 'officetel', 'OFFICETEL' => 'officetel',
            '빌라' => 'villa', '연립다세대' => 'villa', '연립' => 'villa', '다세대' => 'villa',
            '단독주택' => 'detached', '단독' => 'detached', '다가구' => 'detached',
        ],
        'school_type' => [
            '어린이집' => 'daycare', '보육시설' => 'daycare',
            '유치원' => 'kindergarten',
            '초등학교' => 'elementary', '초등학생' => 'elementary', '초등' => 'elementary',
            '중학교' => 'middle', '중학생' => 'middle', '중등' => 'middle',
            '고등학교' => 'high', '고등학생' => 'high', '고등' => 'high',
            '대학교' => 'university', '대학생' => 'university', '대학' => 'university',
        ],
        'category' => [
            '학원(교육/입시)' => 'education', '교육/입시' => 'education', '교육' => 'education', '입시' => 'education',
            '학원(예체능)' => 'arts_sports', '예체능' => 'arts_sports',
        ],
    ],
];
