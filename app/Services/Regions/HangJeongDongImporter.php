<?php

namespace App\Services\Regions;

use Illuminate\Support\Facades\DB;

/**
 * 전국 행정동 경계 GeoJSON(vuski/admdongkor) 을 읽어 regions · region_boundaries 를 채운다.
 *
 * 파일은 피처 하나가 한 줄인 형식이라 줄 단위로 흘려 읽는다. 34MB 를 통째로
 * json_decode 하면 수백 MB 를 먹기 때문에 반드시 스트리밍으로 처리한다.
 *
 * 행정동코드는 adm_cd2(10자리) 의 앞 8자리가 행정안전부 행정동코드와 같다.
 *   예) 서울 강서구 가양1동 adm_cd2=1150060300 → 11500603
 * 통계 테이블이 쓰는 region_code 가 바로 이 8자리다.
 */
class HangJeongDongImporter
{
    /** 피처 한 줄에서 속성만 먼저 훑어보는 정규식 (지오메트리 파싱 전 필터용) */
    private const PROPS = '/"adm_nm":\s*"([^"]+)",\s*"adm_cd2":\s*"(\d+)".*?"sidonm":\s*"([^"]*)",\s*"sggnm":\s*"([^"]*)"/u';

    public function __construct(private readonly string $path) {}

    /**
     * @param  array<int, string>  $sidoNames  적재할 시도명. 비우면 전국.
     * @return array{regions:int, boundaries:int, sidos:array<string,int>}
     */
    public function import(array $sidoNames = [], ?callable $progress = null): array
    {
        $handle = @fopen($this->path, 'r');

        if (! $handle) {
            throw new \RuntimeException("행정동 경계 파일을 열 수 없습니다: {$this->path}");
        }

        $wanted = array_flip(array_map(fn (string $n) => str_replace(' ', '', $n), $sidoNames));
        $regions = [];
        $boundaries = [];
        $counts = ['regions' => 0, 'boundaries' => 0, 'sidos' => []];
        $now = now();

        while (($line = fgets($handle)) !== false) {
            if (! preg_match(self::PROPS, $line, $m)) {
                continue;
            }

            [, $admName, $admCd2, $sido, $sigungu] = $m;

            if ($wanted !== [] && ! isset($wanted[str_replace(' ', '', $sido)])) {
                continue;
            }

            $feature = json_decode(rtrim(trim($line), ','), true);

            if (! is_array($feature) || ! isset($feature['geometry'])) {
                continue;
            }

            $code = substr($admCd2, 0, 8);
            $shape = $this->shapeOf($feature['geometry']);

            $regions[] = [
                'code' => $code,
                'sido_code' => substr($code, 0, 2),
                'sido_name' => $sido,
                'sigungu_code' => substr($code, 0, 5),
                'sigungu_name' => $sigungu,
                'dong_name' => $this->dongNameOf($admName, $sido, $sigungu),
                'full_name' => $admName,
                'lat' => $shape ? round($shape['lat'], 7) : null,
                'lng' => $shape ? round($shape['lng'], 7) : null,
                'area_km2' => $shape ? round($shape['area_km2'], 4) : null,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if ($shape) {
                $boundaries[] = [
                    'region_code' => $code,
                    'min_lng' => $shape['bbox'][0],
                    'min_lat' => $shape['bbox'][1],
                    'max_lng' => $shape['bbox'][2],
                    'max_lat' => $shape['bbox'][3],
                    'rings' => json_encode($shape['rings']),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            $counts['sidos'][$sido] = ($counts['sidos'][$sido] ?? 0) + 1;

            if (count($regions) >= 100) {
                $counts['regions'] += $this->flushRegions($regions);
                $counts['boundaries'] += $this->flushBoundaries($boundaries);
                $progress && $progress($counts['regions']);
            }
        }

        fclose($handle);

        $counts['regions'] += $this->flushRegions($regions);
        $counts['boundaries'] += $this->flushBoundaries($boundaries);
        $progress && $progress($counts['regions']);

        return $counts;
    }

    /** 전체 명칭에서 시도 · 시군구를 떼어낸 행정동 이름 */
    private function dongNameOf(string $admName, string $sido, string $sigungu): string
    {
        $prefix = trim($sido.' '.$sigungu).' ';

        return str_starts_with($admName, $prefix)
            ? substr($admName, strlen($prefix))
            : (string) preg_replace('/^.*\s/u', '', $admName);
    }

    /** @param  array<int, array<string, mixed>>  $rows */
    private function flushRegions(array &$rows): int
    {
        if ($rows === []) {
            return 0;
        }

        DB::table('regions')->upsert($rows, ['code'], [
            'sido_code', 'sido_name', 'sigungu_code', 'sigungu_name',
            'dong_name', 'full_name', 'lat', 'lng', 'area_km2', 'updated_at',
        ]);

        $count = count($rows);
        $rows = [];

        return $count;
    }

    /** @param  array<int, array<string, mixed>>  $rows */
    private function flushBoundaries(array &$rows): int
    {
        $count = 0;

        // rings 가 행정동 하나에 수백 KB 라 작게 끊어 넣는다.
        foreach (array_chunk($rows, 20) as $chunk) {
            DB::table('region_boundaries')->upsert($chunk, ['region_code'], [
                'min_lat', 'max_lat', 'min_lng', 'max_lng', 'rings', 'updated_at',
            ]);
            $count += count($chunk);
        }

        $rows = [];

        return $count;
    }

    /**
     * 폴리곤에서 면적 · 중심점 · bounding box · 저장용 링을 한 번에 뽑는다.
     *
     * @return array{area_km2:float, lat:float, lng:float, bbox:array{0:float,1:float,2:float,3:float}, rings:array}|null
     */
    private function shapeOf(array $geometry): ?array
    {
        $polygons = ($geometry['type'] ?? '') === 'MultiPolygon'
            ? ($geometry['coordinates'] ?? [])
            : [$geometry['coordinates'] ?? []];

        $area = 0.0;
        $cxSum = 0.0;
        $cySum = 0.0;
        $bbox = [180.0, 90.0, -180.0, -90.0];
        $hasOuter = false;

        foreach ($polygons as $polygon) {
            foreach ($polygon as $index => $ring) {
                $ringArea = $this->ringAreaKm2($ring);

                // 첫 링은 외곽, 나머지는 구멍이므로 면적에서 뺀다.
                $area += $index === 0 ? $ringArea : -$ringArea;

                if ($index !== 0) {
                    continue;
                }

                $hasOuter = true;
                $centroid = $this->ringCentroid($ring);
                $cxSum += $centroid[0] * $ringArea;
                $cySum += $centroid[1] * $ringArea;

                foreach ($ring as [$lng, $lat]) {
                    $bbox[0] = min($bbox[0], $lng);
                    $bbox[1] = min($bbox[1], $lat);
                    $bbox[2] = max($bbox[2], $lng);
                    $bbox[3] = max($bbox[3], $lat);
                }
            }
        }

        if (! $hasOuter || $area <= 0) {
            return null;
        }

        return [
            'area_km2' => $area,
            'lng' => $cxSum / $area,
            'lat' => $cySum / $area,
            'bbox' => $bbox,
            'rings' => $this->roundCoordinates($polygons),
        ];
    }

    /** 링(닫힌 경위도 좌표열)의 무게중심. 면적 가중 평균에 쓰인다. */
    private function ringCentroid(array $ring): array
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

        // 면적이 0에 가까운 퇴화 폴리곤은 좌표 평균으로 대체한다.
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
     * 링 면적(km²). 행정동 크기에서는 등거리 원통 투영 후 신발끈 공식으로 충분히 정확하다.
     */
    private function ringAreaKm2(array $ring): float
    {
        $count = count($ring);

        if ($count < 3) {
            return 0.0;
        }

        $latSum = 0.0;

        foreach ($ring as $point) {
            $latSum += $point[1];
        }

        $kx = 111.320 * cos(deg2rad($latSum / $count));
        $ky = 110.574;
        $sum = 0.0;

        for ($i = 0; $i < $count; $i++) {
            $j = ($i + 1) % $count;
            $sum += ($ring[$i][0] * $kx * $ring[$j][1] * $ky) - ($ring[$j][0] * $kx * $ring[$i][1] * $ky);
        }

        return abs($sum) / 2;
    }

    /** 좌표 소수점을 6자리로 줄여 저장 용량을 낮춘다 (약 11cm 정밀도). */
    private function roundCoordinates(array $polygons): array
    {
        foreach ($polygons as $p => $polygon) {
            foreach ($polygon as $r => $ring) {
                foreach ($ring as $i => $point) {
                    $polygons[$p][$r][$i] = [round($point[0], 6), round($point[1], 6)];
                }
            }
        }

        return $polygons;
    }
}
