<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRegion;
use Illuminate\Database\Eloquent\Model;

class ApartmentMoveIn extends Model
{
    use BelongsToRegion;

    protected $fillable = ['region_code', 'complex_name', 'households', 'move_in_ym'];

    protected function casts(): array
    {
        return ['households' => 'integer'];
    }
}
