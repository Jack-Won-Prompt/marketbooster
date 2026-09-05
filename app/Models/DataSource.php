<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DataSource extends Model
{
    protected $fillable = [
        'key', 'category', 'label', 'provider', 'base_label', 'base_ym', 'base_yq', 'sort_order',
    ];
}
