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
];
