<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRegion;
use Illuminate\Database\Eloquent\Model;

class Academy extends Model
{
    use BelongsToRegion;

    protected $table = 'academies';

    protected $fillable = ['region_code', 'base_ym', 'base_yq', 'category', 'industry_name', 'academy_count'];

    protected function casts(): array
    {
        return ['academy_count' => 'integer'];
    }
}
