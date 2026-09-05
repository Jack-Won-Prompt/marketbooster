<?php

return [
    /*
    |--------------------------------------------------------------------------
    | 지도
    |--------------------------------------------------------------------------
    | 지도는 Leaflet + OpenStreetMap 을 쓴다. API 키가 필요 없다.
    | (위치 상권 현황 /map, 새 상권분석 /analyses/new 모두 동일)
    */

    // 분석 기본값
    'default_center' => ['lat' => 37.5665, 'lng' => 126.9780],
    'default_radius' => 1000,
    'radius_options' => [300, 500, 1000, 1500, 2000, 3000],

    // 지도 타일
    'tile_url' => env('MAP_TILE_URL', 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png'),
    'tile_attribution' => env('MAP_TILE_ATTRIBUTION', '&copy; OpenStreetMap contributors'),

    /*
    | 리포트에 넣는 지도 그림 (PDF·웹 공용)
    |
    | dompdf 는 JavaScript 를 실행하지 못해 Leaflet 지도를 그대로 넣을 수 없다.
    | StaticMapRenderer 가 타일을 받아 이어 붙이고 반경·행정동 경계를 그려 PNG 한 장으로 만든다.
    | 타일은 storage/app/map-tiles 에 캐시된다.
    */
    'static_timeout' => (int) env('MAP_STATIC_TIMEOUT', 6),
    'static_width' => (int) env('MAP_STATIC_WIDTH', 900),
    'static_height' => (int) env('MAP_STATIC_HEIGHT', 560),

    // GD 는 HTML 엔티티를 모르므로 그림에 새길 출처는 따로 둔다.
    'tile_attribution_plain' => env('MAP_TILE_ATTRIBUTION_PLAIN', '(c) OpenStreetMap contributors'),
];
