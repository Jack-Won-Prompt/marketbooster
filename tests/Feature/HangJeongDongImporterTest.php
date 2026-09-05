<?php

namespace Tests\Feature;

use App\Models\Region;
use App\Models\RegionBoundary;
use App\Services\Regions\HangJeongDongImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HangJeongDongImporterTest extends TestCase
{
    use RefreshDatabase;

    /** 실제 파일과 같은 "피처 한 줄" 형식으로 임시 GeoJSON 을 만든다. */
    private function fixture(): string
    {
        // 위도 37.5 에서 한 변 0.02° 인 정사각형 두 개 (경기도 · 서울특별시)
        $square = function (float $lat, float $lng) {
            $h = 0.01;

            return json_encode([
                [[[$lng - $h, $lat - $h], [$lng + $h, $lat - $h], [$lng + $h, $lat + $h], [$lng - $h, $lat + $h], [$lng - $h, $lat - $h]]],
            ]);
        };

        $lines = [
            '{',
            '"type": "FeatureCollection",',
            '"name": "HangJeongDong_test",',
            '"features": [',
            '{ "type": "Feature", "properties": { "adm_nm": "경기도 의정부시 의정부1동", "adm_cd2": "4115051000", "sgg": "41150", "sido": "41", "sidonm": "경기도", "sggnm": "의정부시", "adm_cd": "31030690" }, "geometry": { "type": "MultiPolygon", "coordinates": '.$square(37.50, 127.00).' } },',
            '{ "type": "Feature", "properties": { "adm_nm": "서울특별시 강서구 가양1동", "adm_cd2": "1150060300", "sgg": "11500", "sido": "11", "sidonm": "서울특별시", "sggnm": "강서구", "adm_cd": "11160751" }, "geometry": { "type": "MultiPolygon", "coordinates": '.$square(37.55, 126.85).' } }',
            ']',
            '}',
        ];

        $path = tempnam(sys_get_temp_dir(), 'hjd').'.geojson';
        file_put_contents($path, implode("\n", $lines));

        return $path;
    }

    public function test_시도를_골라_행정동과_경계를_적재한다(): void
    {
        $path = $this->fixture();

        $result = (new HangJeongDongImporter($path))->import(['경기도']);

        unlink($path);

        $this->assertSame(1, $result['regions']);
        $this->assertSame(1, $result['boundaries']);
        $this->assertSame(['경기도' => 1], $result['sidos']);

        // 서울은 요청하지 않았으므로 들어오면 안 된다.
        $this->assertSame(0, Region::where('sido_name', '서울특별시')->count());
    }

    public function test_행정동코드는_adm_cd2_앞_8자리다(): void
    {
        $path = $this->fixture();

        (new HangJeongDongImporter($path))->import();

        unlink($path);

        // 4115051000 → 41150510, 1150060300 → 11500603 (통계 테이블의 region_code 와 같은 체계)
        $this->assertNotNull(Region::where('code', '41150510')->first());
        $this->assertSame('경기도 의정부시 의정부1동', Region::where('code', '41150510')->value('full_name'));
        $this->assertSame('가양1동', Region::where('code', '11500603')->value('dong_name'));
        $this->assertSame('11500', Region::where('code', '11500603')->value('sigungu_code'));
    }

    public function test_면적과_중심점을_폴리곤에서_계산한다(): void
    {
        $path = $this->fixture();

        (new HangJeongDongImporter($path))->import(['경기도']);

        unlink($path);

        $region = Region::where('code', '41150510')->first();

        // 0.02° × 0.02° 정사각형의 실제 면적
        $expected = (0.02 * 110.574) * (0.02 * 111.320 * cos(deg2rad(37.50)));

        $this->assertEqualsWithDelta($expected, (float) $region->area_km2, 0.01);
        $this->assertEqualsWithDelta(37.50, (float) $region->lat, 0.0001);
        $this->assertEqualsWithDelta(127.00, (float) $region->lng, 0.0001);

        $boundary = RegionBoundary::where('region_code', '41150510')->first();

        $this->assertEqualsWithDelta(37.49, (float) $boundary->min_lat, 0.0001);
        $this->assertEqualsWithDelta(127.01, (float) $boundary->max_lng, 0.0001);
    }

    public function test_다시_적재해도_행정동이_늘지_않는다(): void
    {
        $path = $this->fixture();

        (new HangJeongDongImporter($path))->import();
        (new HangJeongDongImporter($path))->import();

        unlink($path);

        $this->assertSame(2, Region::count());
        $this->assertSame(2, RegionBoundary::count());
    }
}
