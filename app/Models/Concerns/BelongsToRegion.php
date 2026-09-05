<?php

namespace App\Models\Concerns;

use App\Models\Region;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * region_code / base_ym 을 가진 통계 테이블 공통 동작.
 */
trait BelongsToRegion
{
    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class, 'region_code', 'code');
    }

    public function scopeForRegions(Builder $query, array $regionCodes): Builder
    {
        return $query->whereIn('region_code', $regionCodes);
    }

    public function scopeForMonth(Builder $query, ?string $baseYm): Builder
    {
        return $baseYm ? $query->where('base_ym', $baseYm) : $query;
    }
}
