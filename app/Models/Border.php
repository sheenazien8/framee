<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Border extends Model
{
    protected $fillable = [
        'slug',
        'name',
        'category_id',
        'aspect_ratio',
        'preview_path',
        'file_path',
        'manifest',
        'is_active',
    ];

    protected $casts = [
        'manifest' => 'array',
        'is_active' => 'boolean',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(BorderCategory::class, 'category_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
