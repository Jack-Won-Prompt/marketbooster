<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * 행정동 마스터 적재.
 *
 * storage/app/seed 에 있는 두 개의 공개 데이터 파일을 사용한다.
 *   - dong_center.csv      : 행정동코드 · 시도 · 시군구 · 행정동명 · 중심 경위도 (CP949)
 *   - dong_boundary.geojson: 행정동 경계 폴리곤 → 면적(km²) 계산용
 *
 * 면적은 반경 분석에서 행정동별 겹침 가중치를 구할 때 쓰인다.
 */
class RegionSeeder extends Seeder
{
    public function run(): void
    {
        $centerPath = storage_path('app/seed/dong_center.csv');
        $boundaryPath = storage_path('app/seed/dong_boundary.geojson');

        if (! is_readable($centerPath)) {
            $this->command?->warn('storage/app/seed/dong_center.csv 가 없어 행정동 적재를 건너뜁니다.');

            return;
        }

        $geometry = is_readable($boundaryPath) ? $this->geometryFromGeoJson($boundaryPath) : [];
        $areas = array_map(fn (array $g) => $g['area_km2'], $geometry);
        $rows = [];
        $now = now();

        foreach ($this->readCsv($centerPath) as $row) {
            [$code, $sido, $sigungu, $dong, $lng, $lat] = $row;

            $fullName = "{$sido} {$sigungu} {$dong}";
            $areaKm2 = $areas[$this->normalizeName($fullName)] ?? null;

            $rows[] = [
                'code' => $code,
                'sido_code' => substr($code, 0, 2),
                'sido_name' => $sido,
                'sigungu_code' => substr($code, 0, 5),
                'sigungu_name' => $sigungu,
                'dong_name' => $dong,
                'full_name' => $fullName,
                'lat' => (float) $lat,
                'lng' => (float) $lng,
                'area_km2' => $areaKm2 ? round($areaKm2, 4) : null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        // 면적을 못 찾은 행정동은 같은 시군구 평균으로 메운다.
        $this->backfillAreas($rows);

        foreach (array_chunk($rows, 300) as $chunk) {
            DB::table('regions')->upsert(
                $chunk,
                ['code'],
                ['sido_code', 'sido_name', 'sigungu_code', 'sigungu_name', 'dong_name', 'full_name', 'lat', 'lng', 'area_km2', 'updated_at']
            );
        }

        $this->seedBoundaries($rows, $geometry);

        $this->command?->info('행정동 '.count($rows).'건을 적재했습니다.');
    }

    /** 반경 분석에서 실제 겹침 면적을 계산할 수 있도록 경계 폴리곤을 저장한다. */
    private function seedBoundaries(array $rows, array $geometry): void
    {
        if ($geometry === []) {
            return;
        }

        $now = now();
        $payload = [];

        foreach ($rows as $row) {
            $shape = $geometry[$this->normalizeName($row['full_name'])] ?? null;

            if (! $shape) {
                continue;
            }

            $payload[] = [
                'region_code' => $row['code'],
                'min_lat' => $shape['bbox'][1],
                'max_lat' => $shape['bbox'][3],
                'min_lng' => $shape['bbox'][0],
                'max_lng' => $shape['bbox'][2],
                'rings' => json_encode($shape['rings']),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($payload, 50) as $chunk) {
            DB::table('region_boundaries')->upsert(
                $chunk,
                ['region_code'],
                ['min_lat', 'max_lat', 'min_lng', 'max_lng', 'rings', 'updated_at']
            );
        }

        $this->command?->info('행정동 경계 '.count($payload).'건을 적재했습니다.');
    }

    /**
     * @return \Generator<int, array{0:string,1:string,2:string,3:string,4:string,5:string}>
     */
    private function readCsv(string $path): \Generator
    {
        $contents = file_get_contents($path);

        if (! mb_check_encoding($contents, 'UTF-8')) {
            $contents = mb_convert_encoding($contents, 'UTF-8', 'CP949, EUC-KR');
        }

        $lines = preg_split('/\r?\n/', trim($contents));
        array_shift($lines); // 헤더

        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }

            $parts = str_getcsv($line, ',', '"', '\\');

            if (count($parts) < 6) {
                continue;
            }

            yield [trim($parts[0]), trim($parts[1]), trim($parts[2]), trim($parts[3]), trim($parts[4]), trim($parts[5])];
        }
    }

    /**
     * 경계 GeoJSON 에서 행정동별 면적(km²) · 폴리곤 · bounding box 를 뽑는다.
     *
     * @return array<string, array{area_km2: float, rings: array, bbox: array{0:float,1:float,2:float,3:float}}>
     */
    private function geometryFromGeoJson(string $path): array
    {
        $geo = json_decode(file_get_contents($path), true);
        $result = [];

        foreach ($geo['features'] ?? [] as $feature) {
            $name = $feature['properties']['adm_nm'] ?? null;
            $geometry = $feature['geometry'] ?? null;

            if (! $name || ! $geometry) {
                continue;
            }

            $polygons = $geometry['type'] === 'MultiPolygon'
                ? $geometry['coordinates']
                : [$geometry['coordinates']];

            $area = 0.0;
            $bbox = [180.0, 90.0, -180.0, -90.0];

            foreach ($polygons as $polygon) {
                foreach ($polygon as $index => $ring) {
                    // 첫 링은 외곽, 이후는 구멍이므로 뺀다.
                    $area += $index === 0 ? $this->ringAreaKm2($ring) : -$this->ringAreaKm2($ring);

                    if ($index === 0) {
                        foreach ($ring as [$lng, $lat]) {
                            $bbox[0] = min($bbox[0], $lng);
                            $bbox[1] = min($bbox[1], $lat);
                            $bbox[2] = max($bbox[2], $lng);
                            $bbox[3] = max($bbox[3], $lat);
                        }
                    }
                }
            }

            $result[$this->normalizeName($name)] = [
                'area_km2' => $area,
                'rings' => $this->roundCoordinates($polygons),
                'bbox' => $bbox,
            ];
        }

        return $result;
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

    /**
     * 링(닫힌 경위도 좌표열)의 면적을 km² 로 계산한다.
     * 행정동 크기(수 km)에서는 등거리 원통 투영 후 신발끈 공식으로 충분히 정확하다.
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

        $lat0 = deg2rad($latSum / $count);
        $kx = 111.320 * cos($lat0); // 경도 1도당 km
        $ky = 110.574;              // 위도 1도당 km

        $sum = 0.0;

        for ($i = 0; $i < $count; $i++) {
            $j = ($i + 1) % $count;
            $x1 = $ring[$i][0] * $kx;
            $y1 = $ring[$i][1] * $ky;
            $x2 = $ring[$j][0] * $kx;
            $y2 = $ring[$j][1] * $ky;
            $sum += ($x1 * $y2) - ($x2 * $y1);
        }

        return abs($sum) / 2;
    }

    /** "종로1·2·3·4가동" 과 "종로1.2.3.4가동", "창신제1동" 과 "창신1동" 을 같게 본다. */
    private function normalizeName(string $name): string
    {
        $name = str_replace(['·', '.', ' '], '', $name);

        return preg_replace('/제(\d)/u', '$1', $name);
    }

    private function backfillAreas(array &$rows): void
    {
        $bySigungu = [];

        foreach ($rows as $row) {
            if ($row['area_km2'] !== null) {
                $bySigungu[$row['sigungu_code']][] = $row['area_km2'];
            }
        }

        foreach ($rows as &$row) {
            if ($row['area_km2'] !== null) {
                continue;
            }

            $peers = $bySigungu[$row['sigungu_code']] ?? [];
            $row['area_km2'] = $peers !== [] ? round(array_sum($peers) / count($peers), 4) : 1.6;
        }
    }
}
