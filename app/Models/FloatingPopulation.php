<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRegion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class FloatingPopulation extends Model
{
    use BelongsToRegion;

    protected $fillable = [
        'region_code', 'base_ym', 'base_yq', 'day_type', 'time_band', 'gender', 'age_band', 'population',
    ];

    protected function casts(): array
    {
        return ['population' => 'integer'];
    }

    public function scopeDayType(Builder $query, string $dayType): Builder
    {
        return $query->where('day_type', $dayType);
    }

    public function scopeTimeBand(Builder $query, string $timeBand): Builder
    {
        return $query->where('time_band', $timeBand);
    }
}
