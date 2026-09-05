<?php

namespace App\Services\Analysis;

use App\Models\Region;
use App\Models\RegionBoundary;
use Illuminate\Support\Collection;

/**
 * 분석 범위(반경 또는 행정동 선택)를 "행정동 코드 → 가중치" 목록으로 바꾼다.
 *
 * 반경 분석의 원은 행정동 경계를 가로지르므로 행정동 통계를 그대로 더하면 과대집계된다.
 * 각 행정동이 원에 얼마나 걸치는지를 0~1 가중치로 환산해 통계를 안분한다.
 *
 * 계산 방식은 두 가지다.
 *  1) 경계 폴리곤이 있으면 원 안에 격자점을 뿌려 각 점이 어느 행정동에 속하는지 세는 방식(기본)
 *  2) 경계가 없으면 행정동을 "면적이 같은 원"으로 보고 원-원 교집합 면적으로 근사
 */
class RegionResolver
{
    /** 면적 정보가 없는 행정동에 적용할 기본 면적 (km²) */
    private const FALLBACK_AREA_KM2 = 1.6;

    /** 원 한 변을 몇 칸으로 쪼개 표본을 뽑을지 (61×61 → 원 안 약 2,900점) */
    private const GRID_STEPS = 61;

    /** 가중치가 이보다 작으면 스쳐 지나가는 행정동으로 보고 제외한다. */
    private const MIN_WEIGHT = 0.02;

    /**
     * @return Collection<int, array{region: Region, weight: float, distance_km: float}>
     */
    public function fromRadius(float $lat, float $lng, int $radiusM): Collection
    {
        $radiusKm = $radiusM / 1000;

        $candidates = Region::withinRadius($lat, $lng, $radiusM)->get();

        if ($candidates->isEmpty()) {
            return collect();
        }

        $boundaries = RegionBoundary::whereIn('region_code', $candidates->pluck('code'))->get();

        $weights = $boundaries->count() >= 1
            ? $this->weightsByGridSampling($candidates, $boundaries, $lat, $lng, $radiusKm)
            : [];

        $resolved = $candidates
            ->map(function (Region $region) use ($weights, $radiusKm) {
                $distanceKm = (float) ($region->distance_km ?? 0);

                $weight = $weights[$region->code]
                    ?? $this->overlapWeight($distanceKm, $radiusKm, $region->area_km2);

                return [
                    'region' => $region,
                    'weight' => round($weight, 4),
                    'distance_km' => round($distanceKm, 3),
                ];
            })
            ->filter(fn (array $item) => $item['weight'] >= self::MIN_WEIGHT)
            ->sortByDesc('weight')
            ->values();

        return $weights === []
            ? $this->normalizeToCircle($resolved, M_PI * $radiusKm ** 2)
            : $resolved;
    }

    /**
     * @param  array<int, string>  $codes
     * @return Collection<int, array{region: Region, weight: float, distance_km: float}>
     */
    public function fromCodes(array $codes): Collection
    {
        return Region::whereIn('code', $codes)
            ->orderBy('full_name')
            ->get()
            ->map(fn (Region $region) => [
                'region' => $region,
                'weight' => 1.0,
                'distance_km' => 0.0,
            ]);
    }

    /**
     * @param  Collection<int, array{region: Region, weight: float}>  $resolved
     * @return array<string, float> 행정동코드 => 가중치
     */
    public function weightMap(Collection $resolved): array
    {
        return $resolved
            ->mapWithKeys(fn (array $item) => [$item['region']->code => $item['weight']])
            ->all();
    }

    /**
     * 원 안에 격자점을 뿌리고 각 점이 속한 행정동을 세어 겹침 비율을 구한다.
     *
     *   가중치 = (그 행정동에 떨어진 점 수 / 원 안 전체 점 수) × 원 면적 / 행정동 면적
     *
     * @param  Collection<int, Region>  $candidates
     * @param  Collection<int, RegionBoundary>  $boundaries
     * @return array<string, float>
     */
    private function weightsByGridSampling(
        Collection $candidates,
        Collection $boundaries,
        float $lat,
        float $lng,
        float $radiusKm
    ): array {
        $byCode = $boundaries->keyBy('region_code');
        $latPerKm = 1 / 110.574;
        $lngPerKm = 1 / max(0.000001, 111.320 * cos(deg2rad($lat)));

        $step = (2 * $radiusKm) / (self::GRID_STEPS - 1);
        $hits = [];
        $inside = 0;

        for ($i = 0; $i < self::GRID_STEPS; $i++) {
            $dy = -$radiusKm + $i * $step;

            for ($j = 0; $j < self::GRID_STEPS; $j++) {
                $dx = -$radiusKm + $j * $step;

                if ($dx * $dx + $dy * $dy > $radiusKm * $radiusKm) {
                    continue;
                }

                $inside++;
                $pointLat = $lat + $dy * $latPerKm;
                $pointLng = $lng + $dx * $lngPerKm;

                foreach ($candidates as $region) {
                    $boundary = $byCode->get($region->code);

                    if ($boundary && $boundary->contains($pointLng, $pointLat)) {
                        $hits[$region->code] = ($hits[$region->code] ?? 0) + 1;

                        break;
                    }
                }
            }
        }

        if ($inside === 0) {
            return [];
        }

        $circleArea = M_PI * $radiusKm ** 2;
        $weights = [];

        foreach ($candidates as $region) {
            $count = $hits[$region->code] ?? 0;

            if ($count === 0) {
                continue;
            }

            $area = $region->area_km2 ?: self::FALLBACK_AREA_KM2;
            $weights[$region->code] = min(1.0, ($count / $inside) * $circleArea / $area);
        }

        return $weights;
    }

    /**
     * 근사원 방식은 실제 경계와 달라 겹침 면적의 합이 분석 원의 면적과 어긋난다.
     * 가중치를 비례 조정해 "분석 원 넓이만큼의 땅"을 정확히 집계하도록 맞춘다.
     *
     * @param  Collection<int, array{region: Region, weight: float, distance_km: float}>  $resolved
     * @return Collection<int, array{region: Region, weight: float, distance_km: float}>
     */
    private function normalizeToCircle(Collection $resolved, float $circleAreaKm2): Collection
    {
        $covered = $resolved->sum(
            fn (array $item) => $item['weight'] * ($item['region']->area_km2 ?: self::FALLBACK_AREA_KM2)
        );

        if ($covered <= 0) {
            return $resolved;
        }

        $scale = $circleAreaKm2 / $covered;

        return $resolved->map(function (array $item) use ($scale) {
            $item['weight'] = min(1.0, round($item['weight'] * $scale, 4));

            return $item;
        });
    }

    /**
     * 반지름 R(분석원)과 반지름 r(행정동 근사원)이 중심거리 d 만큼 떨어져 있을 때
     * 교집합 면적을 행정동 면적으로 나눈 값.
     */
    private function overlapWeight(float $distanceKm, float $radiusKm, ?float $areaKm2): float
    {
        $area = $areaKm2 && $areaKm2 > 0 ? $areaKm2 : self::FALLBACK_AREA_KM2;
        $r = sqrt($area / M_PI);
        $R = $radiusKm;
        $d = max(0.0, $distanceKm);

        if ($d >= $r + $R) {
            return 0.0;
        }

        if ($d <= abs($R - $r)) {
            // 한쪽이 다른 쪽에 완전히 포함된다.
            return min(1.0, (M_PI * min($r, $R) ** 2) / $area);
        }

        $a = $r ** 2 * acos(max(-1.0, min(1.0, ($d ** 2 + $r ** 2 - $R ** 2) / (2 * $d * $r))));
        $b = $R ** 2 * acos(max(-1.0, min(1.0, ($d ** 2 + $R ** 2 - $r ** 2) / (2 * $d * $R))));
        $c = 0.5 * sqrt(max(0.0, (-$d + $r + $R) * ($d + $r - $R) * ($d - $r + $R) * ($d + $r + $R)));

        return min(1.0, ($a + $b - $c) / $area);
    }
}
