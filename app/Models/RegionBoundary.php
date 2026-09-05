<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RegionBoundary extends Model
{
    protected $fillable = ['region_code', 'min_lat', 'max_lat', 'min_lng', 'max_lng', 'rings'];

    protected function casts(): array
    {
        return [
            'min_lat' => 'float',
            'max_lat' => 'float',
            'min_lng' => 'float',
            'max_lng' => 'float',
            'rings' => 'array',
        ];
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class, 'region_code', 'code');
    }

    /**
     * 점이 이 행정동 안에 있는지 판정한다 (ray casting).
     * rings 는 MultiPolygon 형태 [ [외곽링, 구멍링...], ... ] 로 저장된다.
     */
    public function contains(float $lng, float $lat): bool
    {
        if ($lat < $this->min_lat || $lat > $this->max_lat || $lng < $this->min_lng || $lng > $this->max_lng) {
            return false;
        }

        foreach ($this->rings as $polygon) {
            if (! self::inRing($polygon[0] ?? [], $lng, $lat)) {
                continue;
            }

            $inHole = false;

            foreach (array_slice($polygon, 1) as $hole) {
                if (self::inRing($hole, $lng, $lat)) {
                    $inHole = true;

                    break;
                }
            }

            if (! $inHole) {
                return true;
            }
        }

        return false;
    }

    /** @param  array<int, array{0: float, 1: float}>  $ring */
    private static function inRing(array $ring, float $x, float $y): bool
    {
        $inside = false;
        $count = count($ring);

        for ($i = 0, $j = $count - 1; $i < $count; $j = $i++) {
            $xi = $ring[$i][0];
            $yi = $ring[$i][1];
            $xj = $ring[$j][0];
            $yj = $ring[$j][1];

            if (($yi > $y) !== ($yj > $y) && $x < ($xj - $xi) * ($y - $yi) / (($yj - $yi) ?: 1e-12) + $xi) {
                $inside = ! $inside;
            }
        }

        return $inside;
    }
}
