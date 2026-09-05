<?php

return [
    /*
    |--------------------------------------------------------------------------
    | 소상공인시장진흥공단_상가(상권)정보_API
    |--------------------------------------------------------------------------
    | 공공데이터포털 활용신청이 필요하다 (자동승인).
    |   https://www.data.go.kr/data/15012005/openapi.do
    |
    | 승인되면 OPENDATA_SERVICE_KEY 를 그대로 쓴다. 별도 키를 쓰려면 SBIZ_SERVICE_KEY 를 채운다.
    */
    'service_key' => env('SBIZ_SERVICE_KEY'),
    'base_url' => env('SBIZ_BASE_URL', 'https://apis.data.go.kr/B553077/api/open/sdsc2'),
    'timeout' => (int) env('SBIZ_TIMEOUT', 30),
    'page_size' => 1000,

    /*
    | 제공 오퍼레이션 (참고)
    |   storeListInDong      행정동 단위 상가업소 조회   divId=adongCd & key=행정동코드
    |   storeListInRadius    반경내 상가업소 조회        radius, cx, cy
    |   storeListInRectangle 사각형내 상가업소 조회      minx, miny, maxx, maxy
    |   storeZoneInRadius    반경내 상권 조회
    */
    'operations' => [
        'dong' => 'storeListInDong',
        'radius' => 'storeListInRadius',
        'rectangle' => 'storeListInRectangle',
    ],
];
