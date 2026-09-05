<?php

return [
    // 카카오 지도 JavaScript 키 (미설정 시 지도 대신 정적 플레이스홀더가 표시됩니다)
    'kakao_js_key' => env('KAKAO_MAP_JS_KEY'),
    'kakao_rest_key' => env('KAKAO_REST_API_KEY'),

    // 분석 기본값
    'default_center' => ['lat' => 37.5665, 'lng' => 126.9780],
    'default_radius' => 1000,
    'radius_options' => [300, 500, 1000, 1500, 2000, 3000],
];
