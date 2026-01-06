<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Brand extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'logo_url',
        'description',
        'is_active',
        'sort_order',
    ];
}
