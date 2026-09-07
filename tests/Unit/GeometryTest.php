<?php

namespace Tests\Unit;

use App\Support\Geometry;
use PHPUnit\Framework\TestCase;

class GeometryTest extends TestCase
{
    public function test_사각형_면적을_구한다(): void
    {
        // 위도 37.5 에서 0.01° × 0.01° 사각형
        $ring = [
            [127.00, 37.50],
            [127.01, 37.50],
            [127.01, 37.51],
            [127.00, 37.51],
        ];

        $expected = (0.01 * Geometry::KM_PER_LAT) * (0.01 * Geometry::KM_PER_LNG * cos(deg2rad(37.505))) * 1_000_000;

        $this->assertEqualsWithDelta($expected, Geometry::areaM2($ring), $expected * 0.01);
    }

    public function test_원의_면적은_파이알제곱에_가깝다(): void
    {
        // openub 은 반경 300m 원을 282,743㎡ 로 적는다. (= π × 300²)
        $ring = Geometry::circleRing(37.50, 127.00, 300);
        $area = Geometry::areaM2($ring);

        // 64각형 근사라 실제 원보다 아주 조금 작다.
        $this->assertEqualsWithDelta(M_PI * 300 ** 2, $area, M_PI * 300 ** 2 * 0.01);
        $this->assertLessThan(M_PI * 300 ** 2, $area);
    }

    public function test_점이_폴리곤_안에_있는지_안다(): void
    {
        $ring = [[127.00, 37.50], [127.02, 37.50], [127.02, 37.52], [127.00, 37.52]];

        $this->assertTrue(Geometry::contains($ring, 127.01, 37.51));
        $this->assertFalse(Geometry::contains($ring, 127.03, 37.51));
        $this->assertFalse(Geometry::contains($ring, 127.01, 37.49));
    }

    public function test_중심점을_구한다(): void
    {
        $ring = [[127.00, 37.50], [127.02, 37.50], [127.02, 37.52], [127.00, 37.52]];

        [$lng, $lat] = Geometry::centroid($ring);

        $this->assertEqualsWithDelta(127.01, $lng, 0.0001);
        $this->assertEqualsWithDelta(37.51, $lat, 0.0001);
    }

    public function test_거리를_구한다(): void
    {
        // 위도 1도는 약 111km
        $this->assertEqualsWithDelta(111.0, Geometry::distanceKm(37.0, 127.0, 38.0, 127.0), 1.0);
        $this->assertSame(0.0, Geometry::distanceKm(37.5, 127.0, 37.5, 127.0));
    }

    public function test_여러_형태의_좌표를_받아들인다(): void
    {
        $ring = Geometry::normalizeRing([
            [127.0, 37.5],
            ['lng' => 127.01, 'lat' => 37.5],
            [127.01, 37.51],
            ['bad'],
            [127.0, 37.5],   // 닫는 점은 지운다
        ]);

        $this->assertCount(3, $ring);
        $this->assertSame([127.0, 37.5], $ring[0]);
        $this->assertSame([127.01, 37.5], $ring[1]);
    }
}
