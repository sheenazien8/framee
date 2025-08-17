<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BorderCategory extends Model
{
    protected $fillable = [
        'name',
        'slug',
    ];

    public function borders(): HasMany
    {
        return $this->hasMany(Border::class, 'category_id');
    }
}
