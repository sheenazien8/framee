<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Photo extends Model
{
    protected $fillable = [
        'session_id',
        'original_path',
        'processed_path',
        'width',
        'height',
        'meta',
    ];

    protected $casts = [
        'width' => 'integer',
        'height' => 'integer',
        'meta' => 'array',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(PhotoSession::class, 'session_id');
    }

    public function isProcessed(): bool
    {
        return !empty($this->processed_path);
    }

    public function getOriginalUrl(): ?string
    {
        return $this->original_path ? asset('storage/' . $this->original_path) : null;
    }

    public function getProcessedUrl(): ?string
    {
        return $this->processed_path ? asset('storage/' . $this->processed_path) : null;
    }
}
