<?php

use App\Services\OpenData\Seoul\Transformers\CardSalesTransformer;
use App\Services\OpenData\Seoul\Transformers\FloatingPopulationTransformer;
use App\Services\OpenData\Seoul\Transformers\ResidentPopulationTransformer;
use App\Services\OpenData\Seoul\Transformers\WorkplacePopulationTransformer;

return [
    /*
    |--------------------------------------------------------------------------
    | 서울 열린데이터광장 오픈 API
    |--------------------------------------------------------------------------
    | data.go.kr 과 달리 API 별 활용신청이 없다. "일반 인증키" 하나를 발급받으면
    | 아래 서비스를 모두 호출할 수 있다.
    |   발급: https://data.seoul.go.kr/together/mypage/actkeyMain.do
    |
    | 요청 형식 (인증키가 쿼리스트링이 아니라 경로에 들어간다)
    |   {base}/{KEY}/json/{SERVICE}/{START_INDEX}/{END_INDEX}/{STDR_YYQU_CD}
    */
    'api_key' => env('SEOUL_OPENAPI_KEY'),
    'base_url' => env('SEOUL_OPENAPI_URL', 'http://openapi.seoul.go.kr:8088'),
    'timeout' => (int) env('SEOUL_OPENAPI_TIMEOUT', 30),

    // 한 번에 최대 1,000건까지 요청할 수 있다.
    'page_size' => 1000,

    /*
    |--------------------------------------------------------------------------
    | 서울시 상권분석서비스 — 행정동 단위
    |--------------------------------------------------------------------------
    | 행정동 코드는 행정안전부 "주민등록 행정기관코드"라 regions.code 와 그대로 맞는다.
    | 집계 주기는 분기(STDR_YYQU_CD, 예: 20242)다.
    */
    'datasets' => [
        'floating_population' => [
            'label' => '길단위인구(유동인구)',
            'service' => 'VwsmAdstrdFlpopW',
            'dataset_id' => 'OA-22178',
            'provider' => '서울시 상권분석서비스(서울신용보증재단)',
            'transformer' => FloatingPopulationTransformer::class,
        ],
        'card_sales' => [
            'label' => '추정매출',
            'service' => 'VwsmAdstrdSelngW',
            'dataset_id' => 'OA-22175',
            'provider' => '서울시 상권분석서비스(서울신용보증재단)',
            'transformer' => CardSalesTransformer::class,
        ],
        'resident_population' => [
            'label' => '상주인구 · 가구',
            'service' => 'VwsmAdstrdRepopW',
            'dataset_id' => 'OA-22183',
            'provider' => '서울시 상권분석서비스(서울신용보증재단)',
            'transformer' => ResidentPopulationTransformer::class,
        ],
        'workplace_population' => [
            'label' => '직장인구',
            'service' => 'VwsmAdstrdWrcPopltnW',
            'dataset_id' => 'OA-22184',
            'provider' => '서울시 상권분석서비스(서울신용보증재단)',
            'transformer' => WorkplacePopulationTransformer::class,
        ],
    ],
];
