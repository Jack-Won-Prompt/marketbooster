<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRegion;
use Illuminate\Database\Eloquent\Model;

class Household extends Model
{
    use BelongsToRegion;

    protected $table = 'households';

    protected $fillable = ['region_code', 'base_ym', 'housing_type', 'households'];

    protected function casts(): array
    {
        return ['households' => 'integer'];
    }
}
