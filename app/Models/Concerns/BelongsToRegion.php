<?php

namespace App\Models\Concerns;

use App\Models\Region;
use App\Support\Period;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * region_code 와 기준 기간(base_ym / base_yq)을 가진 통계 테이블 공통 동작.
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

    /** 월 또는 분기 어느 쪽이든 알맞은 칸으로 걸러 준다. */
    public function scopeForPeriod(Builder $query, ?Period $period): Builder
    {
        return $period ? $query->where($period->filterColumn(), $period->code) : $query;
    }
}
