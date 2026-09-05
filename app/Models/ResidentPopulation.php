<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRegion;
use Illuminate\Database\Eloquent\Model;

class ResidentPopulation extends Model
{
    use BelongsToRegion;

    protected $fillable = ['region_code', 'base_ym', 'gender', 'age_band', 'population'];

    protected function casts(): array
    {
        return ['population' => 'integer'];
    }
}
