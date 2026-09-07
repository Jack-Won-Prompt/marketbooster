<?php

namespace App\Support;

/**
 * 경위도 폴리곤 계산.
 *
 * 상권을 원·사각형·다각형 아무 모양으로나 그릴 수 있어야 해서,
 * 반경 전용이던 계산을 폴리곤 하나로 일반화한다.
 * 상권 크기(수 km)에서는 등거리 원통 투영이면 충분히 정확하다.
 */
class Geometry
{
    /** 위도 1도당 km */
    public const KM_PER_LAT = 110.574;

    /** 적도에서 경도 1도당 km */
    public const KM_PER_LNG = 111.320;

    /**
     * 링(닫히지 않아도 됨)의 면적을 m² 로.
     *
     * @param  array<int, array{0: float, 1: float}>  $ring  [[lng, lat], ...]
     */
    public static function areaM2(array $ring): float
    {
        $count = count($ring);

        if ($count < 3) {
            return 0.0;
        }

        $latSum = 0.0;

        foreach ($ring as $point) {
            $latSum += $point[1];
        }

        $kx = self::KM_PER_LNG * cos(deg2rad($latSum / $count));
        $ky = self::KM_PER_LAT;
        $sum = 0.0;

        for ($i = 0; $i < $count; $i++) {
            $j = ($i + 1) % $count;
            $sum += ($ring[$i][0] * $kx) * ($ring[$j][1] * $ky) - ($ring[$j][0] * $kx) * ($ring[$i][1] * $ky);
        }

        return abs($sum) / 2 * 1_000_000;
    }

    /**
     * 점이 링 안에 있는지 (ray casting).
     *
     * @param  array<int, array{0: float, 1: float}>  $ring
     */
    public static function contains(array $ring, float $lng, float $lat): bool
    {
        $inside = false;
        $count = count($ring);

        for ($i = 0, $j = $count - 1; $i < $count; $j = $i++) {
            $xi = $ring[$i][0];
            $yi = $ring[$i][1];
            $xj = $ring[$j][0];
            $yj = $ring[$j][1];

            if (($yi > $lat) !== ($yj > $lat)
                && $lng < ($xj - $xi) * ($lat - $yi) / (($yj - $yi) ?: 1e-12) + $xi) {
                $inside = ! $inside;
            }
        }

        return $inside;
    }

    /**
     * @param  array<int, array{0: float, 1: float}>  $ring
     * @return array{0: float, 1: float, 2: float, 3: float}  [minLng, minLat, maxLng, maxLat]
     */
    public static function bbox(array $ring): array
    {
        $bbox = [180.0, 90.0, -180.0, -90.0];

        foreach ($ring as [$lng, $lat]) {
            $bbox[0] = min($bbox[0], $lng);
            $bbox[1] = min($bbox[1], $lat);
            $bbox[2] = max($bbox[2], $lng);
            $bbox[3] = max($bbox[3], $lat);
        }

        return $bbox;
    }

    /**
     * 면적 가중 중심점.
     *
     * @param  array<int, array{0: float, 1: float}>  $ring
     * @return array{0: float, 1: float}  [lng, lat]
     */
    public static function centroid(array $ring): array
    {
        $count = count($ring);
        $twiceArea = 0.0;
        $x = 0.0;
        $y = 0.0;

        for ($i = 0; $i < $count; $i++) {
            $j = ($i + 1) % $count;
            $cross = ($ring[$i][0] * $ring[$j][1]) - ($ring[$j][0] * $ring[$i][1]);
            $twiceArea += $cross;
            $x += ($ring[$i][0] + $ring[$j][0]) * $cross;
            $y += ($ring[$i][1] + $ring[$j][1]) * $cross;
        }

        if (abs($twiceArea) < 1e-12) {
            $sx = 0.0;
            $sy = 0.0;

            foreach ($ring as $point) {
                $sx += $point[0];
                $sy += $point[1];
            }

            return [$sx / max(1, $count), $sy / max(1, $count)];
        }

        return [$x / (3 * $twiceArea), $y / (3 * $twiceArea)];
    }

    /**
     * 원을 폴리곤으로 근사한다. 상권을 모두 폴리곤 하나로 다루기 위해서다.
     *
     * @return array<int, array{0: float, 1: float}>
     */
    public static function circleRing(float $lat, float $lng, int $radiusM, int $segments = 64): array
    {
        $radiusKm = $radiusM / 1000;
        $latPerKm = 1 / self::KM_PER_LAT;
        $lngPerKm = 1 / max(1e-6, self::KM_PER_LNG * cos(deg2rad($lat)));
        $ring = [];

        for ($i = 0; $i < $segments; $i++) {
            $angle = 2 * M_PI * $i / $segments;
            $ring[] = [
                round($lng + cos($angle) * $radiusKm * $lngPerKm, 6),
                round($lat + sin($angle) * $radiusKm * $latPerKm, 6),
            ];
        }

        return $ring;
    }

    /**
     * 두 점 사이 거리 (km). 하버사인.
     */
    public static function distanceKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return 6371 * 2 * asin(min(1.0, sqrt($a)));
    }

    /**
     * 좌표 배열을 [[lng, lat], ...] 로 정규화한다. 잘못된 점은 버린다.
     *
     * @param  array<int, mixed>  $points
     * @return array<int, array{0: float, 1: float}>
     */
    public static function normalizeRing(array $points): array
    {
        $ring = [];

        foreach ($points as $point) {
            $lng = null;
            $lat = null;

            if (is_array($point) && array_key_exists('lng', $point) && array_key_exists('lat', $point)) {
                $lng = $point['lng'];
                $lat = $point['lat'];
            } elseif (is_array($point) && count($point) >= 2) {
                [$lng, $lat] = array_values($point);
            }

            if (! is_numeric($lng) || ! is_numeric($lat)) {
                continue;
            }

            $ring[] = [round((float) $lng, 6), round((float) $lat, 6)];
        }

        // 마지막 점이 첫 점과 같으면 닫힌 링이므로 하나를 뺀다.
        $last = count($ring) - 1;

        if ($last > 0 && $ring[0] === $ring[$last]) {
            array_pop($ring);
        }

        return $ring;
    }
}
