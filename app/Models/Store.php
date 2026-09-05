<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Store extends Model
{
    protected $fillable = [
        'store_id', 'name', 'branch_name', 'region_code', 'sido_name', 'sigungu_name', 'dong_name',
        'large_code', 'large_name', 'middle_code', 'middle_name', 'small_code', 'small_name',
        'road_address', 'lot_address', 'lat', 'lng', 'collected_at',
    ];

    protected function casts(): array
    {
        return [
            'lat' => 'float',
            'lng' => 'float',
            'collected_at' => 'datetime',
        ];
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class, 'region_code', 'code');
    }

    public function scopeForRegions(Builder $query, array $regionCodes): Builder
    {
        return $query->whereIn('region_code', $regionCodes);
    }
}
