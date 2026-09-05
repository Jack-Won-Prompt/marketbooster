<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Region extends Model
{
    protected $fillable = [
        'code', 'sido_code', 'sido_name', 'sigungu_code', 'sigungu_name',
        'dong_name', 'full_name', 'lat', 'lng', 'area_km2',
    ];

    protected function casts(): array
    {
        return [
            'lat' => 'float',
            'lng' => 'float',
            'area_km2' => 'float',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'code';
    }

    /** 명칭 부분 검색 (자동완성용) */
    public function scopeSearch(Builder $query, ?string $keyword): Builder
    {
        $keyword = trim((string) $keyword);

        if ($keyword === '') {
            return $query;
        }

        return $query->where(function (Builder $q) use ($keyword) {
            $q->where('full_name', 'like', "%{$keyword}%")
                ->orWhere('dong_name', 'like', "%{$keyword}%")
                ->orWhere('sigungu_name', 'like', "%{$keyword}%")
                ->orWhere('code', 'like', "{$keyword}%");
        });
    }

    /** 행정동 근사원의 반지름 상한(km). bounding box 를 얼마나 넓힐지 정한다. */
    private const MAX_DONG_RADIUS_KM = 4.0;

    /**
     * 분석 원과 겹치는 행정동을 찾는다.
     *
     * 중심점이 원 안에 있는 행정동만 고르면 면적이 큰 동에 가려 인접 동이 통째로 빠진다.
     * 그래서 행정동을 "면적이 같은 원"으로 보고 두 원이 닿기만 해도(중심거리 ≤ R + r) 후보에 넣는다.
     * 실제로 얼마나 겹치는지는 RegionResolver 가 면적 비율로 환산한다.
     *
     * 사각 bounding box 로 1차 필터링해 (lat, lng) 인덱스를 태운 뒤 Haversine 거리로 좁힌다.
     */
    public function scopeWithinRadius(Builder $query, float $lat, float $lng, int $radiusM): Builder
    {
        $radiusKm = $radiusM / 1000;
        $searchKm = $radiusKm + self::MAX_DONG_RADIUS_KM;
        $latDelta = $searchKm / 111.0;
        $lngDelta = $searchKm / max(0.000001, 111.0 * cos(deg2rad($lat)));

        return $query
            ->whereBetween('lat', [$lat - $latDelta, $lat + $latDelta])
            ->whereBetween('lng', [$lng - $lngDelta, $lng + $lngDelta])
            ->selectRaw(
                'regions.*, SQRT(COALESCE(area_km2, 1.6) / PI()) AS dong_radius_km,'
                .' (6371 * acos(least(1, cos(radians(?)) * cos(radians(lat))'
                .' * cos(radians(lng) - radians(?)) + sin(radians(?)) * sin(radians(lat))))) AS distance_km',
                [$lat, $lng, $lat]
            )
            ->havingRaw('distance_km <= ? + dong_radius_km', [$radiusKm])
            ->orderBy('distance_km');
    }
}
