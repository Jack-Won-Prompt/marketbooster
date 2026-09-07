<?php

namespace App\Models;

use App\Support\Period;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Analysis extends Model
{
    protected $table = 'analyses';

    protected $fillable = [
        'uuid', 'user_id', 'title', 'mode', 'center_lat', 'center_lng', 'radius_m',
        'shape_kind', 'shape_ring', 'area_m2',
        'address', 'region_codes', 'base_ym', 'base_yq', 'status', 'payload', 'error_message', 'completed_at',
    ];

    /** 그린 상권의 모양 이름 */
    public const SHAPE_LABELS = [
        'circle' => '원형 상권',
        'rectangle' => '사각형 상권',
        'polygon' => '다각형 상권',
    ];

    protected function casts(): array
    {
        return [
            'region_codes' => 'array',
            'shape_ring' => 'array',
            'area_m2' => 'integer',
            'payload' => 'array',
            'center_lat' => 'float',
            'center_lng' => 'float',
            'radius_m' => 'integer',
            'completed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $analysis) {
            $analysis->uuid ??= (string) Str::uuid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /** 이 분석이 어느 기간을 대상으로 하는지 (월 또는 분기) */
    public function period(): Period
    {
        return Period::fromColumns($this->base_ym, $this->base_yq);
    }

    public function setPeriod(Period $period): void
    {
        $this->fill($period->columns());
    }

    /** 분석 범위 설명 문구 (예: "원형 상권 / 반경 300m / 282,743㎡") */
    public function rangeLabel(): string
    {
        if ($this->mode === 'polygon') {
            $parts = [self::SHAPE_LABELS[$this->shape_kind] ?? '다각형 상권'];

            if ($this->shape_kind === 'circle' && $this->radius_m) {
                $parts[] = '반경 '.number_format((int) $this->radius_m).'m';
            }

            if ($this->area_m2) {
                $parts[] = number_format((int) $this->area_m2).'㎡';
            }

            return implode(' / ', $parts);
        }

        return $this->mode === 'radius'
            ? '반경 '.number_format((int) $this->radius_m).'m'
            : '행정동 '.count($this->region_codes ?? []).'곳';
    }

    public function regions()
    {
        return Region::whereIn('code', $this->region_codes ?? [])->orderBy('full_name')->get();
    }
}
