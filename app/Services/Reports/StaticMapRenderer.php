<?php

namespace App\Services\Reports;

use App\Models\RegionBoundary;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * 리포트에 넣을 지도 그림(PNG)을 서버에서 그린다.
 *
 * PDF 는 dompdf 로 만드는데 dompdf 는 canvas·JavaScript 를 실행하지 못해
 * Leaflet 지도가 그대로는 들어가지 않는다. 그래서 타일을 직접 받아 이어 붙이고
 * 그 위에 분석 반경과 행정동 경계를 GD 로 그려 한 장의 이미지로 만든다.
 *
 * 타일은 storage/app/map-tiles 에 캐시한다. 같은 지역 리포트를 여러 번 만들어도
 * 타일 서버를 다시 부르지 않게 하려는 것이고, OSM 타일 이용 정책상으로도 그래야 한다.
 * 타일을 못 받아도 리포트가 깨지면 안 되므로, 그때는 바탕 없이 경계만 그린다.
 */
class StaticMapRenderer
{
    private const TILE_SIZE = 256;

    /** 확대 수준 상한. 더 키워도 종이에서는 차이가 없고 타일만 많아진다. */
    private const MAX_ZOOM = 17;

    private const MIN_ZOOM = 8;

    /** 한 장에 쓸 타일 수 상한 (타일 서버에 대한 예의이자 메모리 보호) */
    private const MAX_TILES = 42;

    public function __construct(
        private readonly string $tileUrl,
        private readonly int $timeout = 6,
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            (string) config('map.tile_url', 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png'),
            (int) config('map.static_timeout', 6),
        );
    }

    /**
     * 분석 payload 의 meta 로 지도 한 장을 그린다.
     *
     * @param  array<string, mixed>  $meta  payload['meta']
     * @return string|null  PNG 바이너리. 그릴 근거가 없으면 null.
     */
    public function render(array $meta, int $width = 900, int $height = 560): ?string
    {
        $bounds = $this->boundsOf($meta);

        if ($bounds === null) {
            return null;
        }

        $zoom = $this->zoomFor($bounds, $width, $height);
        $center = [
            'lat' => ($bounds['min_lat'] + $bounds['max_lat']) / 2,
            'lng' => ($bounds['min_lng'] + $bounds['max_lng']) / 2,
        ];

        // 화면 왼쪽 위 모서리의 세계 좌표(픽셀). 이후 모든 좌표는 여기서 뺀다.
        $centerPx = $this->project($center['lat'], $center['lng'], $zoom);
        $originX = $centerPx[0] - $width / 2;
        $originY = $centerPx[1] - $height / 2;

        $canvas = imagecreatetruecolor($width, $height);
        imagealphablending($canvas, true);
        imagefilledrectangle($canvas, 0, 0, $width, $height, imagecolorallocate($canvas, 241, 243, 246));

        $this->drawTiles($canvas, $zoom, $originX, $originY, $width, $height);
        $this->drawRegions($canvas, $meta, $zoom, $originX, $originY);
        $this->drawScope($canvas, $meta, $zoom, $originX, $originY);
        $this->drawAttribution($canvas, $width, $height);

        // 256색으로 줄이면 지도 그림 품질은 거의 그대로면서 파일이 1/4 이하가 된다.
        // PDF 한 장에 800KB 짜리 이미지를 넣으면 dompdf 메모리가 위험하다.
        imagetruecolortopalette($canvas, true, 255);

        ob_start();
        imagepng($canvas, null, 9);

        return (string) ob_get_clean();
    }

    /** PDF 에 그대로 넣을 수 있는 data URI */
    public function renderDataUri(array $meta, int $width = 900, int $height = 560): ?string
    {
        $png = $this->render($meta, $width, $height);

        return $png === null ? null : 'data:image/png;base64,'.base64_encode($png);
    }

    /**
     * 그림에 담을 범위. 반경 원과 포함 행정동을 모두 감싸고 여백을 조금 둔다.
     *
     * @return array{min_lat:float, max_lat:float, min_lng:float, max_lng:float}|null
     */
    private function boundsOf(array $meta): ?array
    {
        $lats = [];
        $lngs = [];

        $center = $meta['center'] ?? null;
        $radiusM = (int) ($meta['radius_m'] ?? 0);

        if ($center && $radiusM > 0) {
            $latDelta = $radiusM / 110574;
            $lngDelta = $radiusM / max(1e-6, 111320 * cos(deg2rad((float) $center['lat'])));

            $lats[] = (float) $center['lat'] - $latDelta;
            $lats[] = (float) $center['lat'] + $latDelta;
            $lngs[] = (float) $center['lng'] - $lngDelta;
            $lngs[] = (float) $center['lng'] + $lngDelta;
        }

        foreach ($this->boundaries($meta) as $boundary) {
            $lats[] = $boundary->min_lat;
            $lats[] = $boundary->max_lat;
            $lngs[] = $boundary->min_lng;
            $lngs[] = $boundary->max_lng;
        }

        if ($lats === [] && $center) {
            // 경계도 반경도 없으면 중심점 둘레만 보여 준다.
            $lats = [(float) $center['lat'] - 0.01, (float) $center['lat'] + 0.01];
            $lngs = [(float) $center['lng'] - 0.014, (float) $center['lng'] + 0.014];
        }

        if ($lats === []) {
            return null;
        }

        $minLat = min($lats);
        $maxLat = max($lats);
        $minLng = min($lngs);
        $maxLng = max($lngs);

        $padLat = max(0.0008, ($maxLat - $minLat) * 0.08);
        $padLng = max(0.001, ($maxLng - $minLng) * 0.08);

        return [
            'min_lat' => $minLat - $padLat,
            'max_lat' => $maxLat + $padLat,
            'min_lng' => $minLng - $padLng,
            'max_lng' => $maxLng + $padLng,
        ];
    }

    /**
     * 범위가 그림 안에 들어가는 가장 큰 확대 수준.
     *
     * @return int
     */
    private function zoomFor(array $bounds, int $width, int $height): int
    {
        for ($zoom = self::MAX_ZOOM; $zoom > self::MIN_ZOOM; $zoom--) {
            $topLeft = $this->project($bounds['max_lat'], $bounds['min_lng'], $zoom);
            $bottomRight = $this->project($bounds['min_lat'], $bounds['max_lng'], $zoom);

            $spanX = $bottomRight[0] - $topLeft[0];
            $spanY = $bottomRight[1] - $topLeft[1];

            if ($spanX <= $width && $spanY <= $height) {
                return $zoom;
            }
        }

        return self::MIN_ZOOM;
    }

    /** 경위도 → 세계 픽셀 좌표 (웹 메르카토르) */
    private function project(float $lat, float $lng, int $zoom): array
    {
        $worldSize = self::TILE_SIZE * (2 ** $zoom);
        $sinLat = sin(deg2rad(max(-85.05112878, min(85.05112878, $lat))));

        return [
            ($lng + 180) / 360 * $worldSize,
            (0.5 - log((1 + $sinLat) / (1 - $sinLat)) / (4 * M_PI)) * $worldSize,
        ];
    }

    private function drawTiles(\GdImage $canvas, int $zoom, float $originX, float $originY, int $width, int $height): void
    {
        $first = (int) floor($originX / self::TILE_SIZE);
        $last = (int) floor(($originX + $width) / self::TILE_SIZE);
        $firstY = (int) floor($originY / self::TILE_SIZE);
        $lastY = (int) floor(($originY + $height) / self::TILE_SIZE);

        $count = ($last - $first + 1) * ($lastY - $firstY + 1);

        if ($count > self::MAX_TILES) {
            return;
        }

        $max = 2 ** $zoom;

        for ($x = $first; $x <= $last; $x++) {
            for ($y = $firstY; $y <= $lastY; $y++) {
                if ($y < 0 || $y >= $max) {
                    continue;
                }

                $tile = $this->tile($zoom, (($x % $max) + $max) % $max, $y);

                if ($tile === null) {
                    continue;
                }

                imagecopy(
                    $canvas,
                    $tile,
                    (int) round($x * self::TILE_SIZE - $originX),
                    (int) round($y * self::TILE_SIZE - $originY),
                    0,
                    0,
                    self::TILE_SIZE,
                    self::TILE_SIZE
                );

            }
        }
    }

    /** 캐시에 있으면 꺼내 쓰고, 없으면 받아서 저장한다. 실패하면 null. */
    private function tile(int $zoom, int $x, int $y): ?\GdImage
    {
        $path = storage_path("app/map-tiles/{$zoom}/{$x}/{$y}.png");

        if (is_readable($path)) {
            $image = @imagecreatefrompng($path);

            return $image ?: null;
        }

        $url = str_replace(
            ['{s}', '{z}', '{x}', '{y}'],
            [['a', 'b', 'c'][($x + $y) % 3], (string) $zoom, (string) $x, (string) $y],
            $this->tileUrl
        );

        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders(['User-Agent' => $this->userAgent()])
                ->get($url);

            if (! $response->successful()) {
                return null;
            }

            $body = $response->body();
        } catch (\Throwable $e) {
            Log::debug('지도 타일을 받지 못했습니다: '.$e->getMessage());

            return null;
        }

        $image = @imagecreatefromstring($body);

        if (! $image) {
            return null;
        }

        @mkdir(dirname($path), 0775, true);
        @imagepng($image, $path, 6);

        return $image;
    }

    /**
     * OSM 타일 이용 정책은 애플리케이션을 알아볼 수 있는 User-Agent 를 요구한다.
     */
    private function userAgent(): string
    {
        return sprintf('%s/1.0 (+%s)', config('app.name', 'MarketScope'), config('app.url', 'http://localhost'));
    }

    /** 포함된 행정동 경계를 채워 그린다. */
    private function drawRegions(\GdImage $canvas, array $meta, int $zoom, float $originX, float $originY): void
    {
        $fill = imagecolorallocatealpha($canvas, 5, 147, 255, 100);
        $line = imagecolorallocatealpha($canvas, 0, 89, 157, 30);

        imagesetthickness($canvas, 2);

        foreach ($this->boundaries($meta) as $boundary) {
            foreach ($boundary->rings as $polygon) {
                $ring = $polygon[0] ?? [];

                if (count($ring) < 3) {
                    continue;
                }

                $points = [];

                foreach ($ring as [$lng, $lat]) {
                    $pixel = $this->project((float) $lat, (float) $lng, $zoom);
                    $points[] = (int) round($pixel[0] - $originX);
                    $points[] = (int) round($pixel[1] - $originY);
                }

                imagefilledpolygon($canvas, $points, $fill);
                imagepolygon($canvas, $points, $line);
            }
        }

        imagesetthickness($canvas, 1);
    }

    /** 분석 반경(원)과 중심점. 반경 분석이 아니면 그리지 않는다. */
    private function drawScope(\GdImage $canvas, array $meta, int $zoom, float $originX, float $originY): void
    {
        $center = $meta['center'] ?? null;
        $radiusM = (int) ($meta['radius_m'] ?? 0);

        if (! $center) {
            return;
        }

        $pixel = $this->project((float) $center['lat'], (float) $center['lng'], $zoom);
        $cx = (int) round($pixel[0] - $originX);
        $cy = (int) round($pixel[1] - $originY);

        if ($radiusM > 0) {
            // 위도 방향 거리로 반지름을 픽셀로 환산한다.
            $edge = $this->project(
                (float) $center['lat'] + $radiusM / 110574,
                (float) $center['lng'],
                $zoom
            );
            $radiusPx = (int) round(abs($pixel[1] - $edge[1]));

            $ring = imagecolorallocatealpha($canvas, 0, 89, 157, 20);
            imagesetthickness($canvas, 3);

            for ($i = 0; $i < 3; $i++) {
                imageellipse($canvas, $cx, $cy, ($radiusPx - $i) * 2, ($radiusPx - $i) * 2, $ring);
            }

            imagesetthickness($canvas, 1);
        }

        $white = imagecolorallocate($canvas, 255, 255, 255);
        $brand = imagecolorallocate($canvas, 5, 147, 255);

        imagefilledellipse($canvas, $cx, $cy, 20, 20, $white);
        imagefilledellipse($canvas, $cx, $cy, 13, 13, $brand);
    }

    /** 타일 출처 표기. OSM 이용 조건이다. */
    private function drawAttribution(\GdImage $canvas, int $width, int $height): void
    {
        $text = (string) config('map.tile_attribution_plain', '(c) OpenStreetMap contributors');
        $boxWidth = imagefontwidth(2) * strlen($text) + 10;

        $background = imagecolorallocatealpha($canvas, 255, 255, 255, 40);
        imagefilledrectangle($canvas, $width - $boxWidth, $height - 18, $width, $height, $background);
        imagestring($canvas, 2, $width - $boxWidth + 5, $height - 16, $text, imagecolorallocate($canvas, 90, 98, 116));
    }

    /**
     * 리포트에 담긴 행정동의 경계. 한 번만 읽어 두고 다시 쓴다.
     *
     * @return \Illuminate\Support\Collection<int, RegionBoundary>
     */
    private function boundaries(array $meta): \Illuminate\Support\Collection
    {
        static $cache = [];

        $codes = array_column($meta['regions'] ?? [], 'code');

        if ($codes === []) {
            return collect();
        }

        $key = implode(',', $codes);

        return $cache[$key] ??= RegionBoundary::whereIn('region_code', $codes)->get();
    }
}
