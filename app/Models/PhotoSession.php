<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class PhotoSession extends Model
{
    public const STATUS_IDLE = 'idle';
    public const STATUS_CAPTURING = 'capturing';
    public const STATUS_REVIEW = 'review';
    public const STATUS_CHECKOUT = 'checkout';
    public const STATUS_PAID = 'paid';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'code',
        'status',
        'total_price',
        'currency',
        'kiosk_label',
        'meta',
        'expires_at',
    ];

    protected $casts = [
        'total_price' => 'integer',
        'meta' => 'array',
        'expires_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($session) {
            if (!$session->code) {
                $session->code = self::generateUniqueCode();
            }
        });
    }

    public function photos(): HasMany
    {
        return $this->hasMany(Photo::class, 'session_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'session_id');
    }

    public function printJobs(): HasMany
    {
        return $this->hasMany(PrintJob::class, 'session_id');
    }

    public function latestPayment(): HasOne
    {
        return $this->hasOne(Payment::class, 'session_id')->latest();
    }

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    public function isExpired(): bool
    {
        return $this->status === self::STATUS_EXPIRED || 
               ($this->expires_at && $this->expires_at->isPast());
    }

    public static function generateUniqueCode(): string
    {
        do {
            $code = strtoupper(Str::random(8));
        } while (self::where('code', $code)->exists());

        return $code;
    }

    public function scopeActive($query)
    {
        return $query->whereNotIn('status', [self::STATUS_COMPLETED, self::STATUS_EXPIRED]);
    }
}
