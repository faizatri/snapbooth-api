<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Event extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'name',
        'slug',
        'date',
        'location',
        'booth_config',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'date'        => 'date',
            'booth_config' => 'array',
            'is_active'   => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Event $event) {
            if (empty($event->slug)) {
                $event->slug = Str::slug($event->name) . '-' . Str::random(6);
            }
        });
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    /** Hanya event yang booth-nya sedang aktif/bisa dipakai. */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function scopeInactive(Builder $query): void
    {
        $query->where('is_active', false);
    }

    /** Event yang tanggalnya hari ini atau ke depan. */
    public function scopeUpcoming(Builder $query): void
    {
        $query->where('date', '>=', now()->toDateString());
    }

    /** Event yang tanggalnya sudah lewat. */
    public function scopePast(Builder $query): void
    {
        $query->where('date', '<', now()->toDateString());
    }

    /** Batasi ke event milik user tertentu. */
    public function scopeForUser(Builder $query, int $userId): void
    {
        $query->where('user_id', $userId);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    public function isUpcoming(): bool
    {
        return $this->date->isFuture() || $this->date->isToday();
    }

    public function isPast(): bool
    {
        return $this->date->isPast() && ! $this->date->isToday();
    }

    /**
     * Ambil nilai konfigurasi booth dengan dot-notation.
     * Contoh: $event->boothConfig('countdown_seconds', 3)
     */
    public function boothConfig(string $key, mixed $default = null): mixed
    {
        return data_get($this->booth_config, $key, $default);
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(Session::class);
    }

    /** Sesi yang sedang aktif (belum ended). */
    public function activeSessions(): HasMany
    {
        return $this->hasMany(Session::class)->whereNull('ended_at');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(Photo::class);
    }

    public function sharedPhotos(): HasMany
    {
        return $this->hasMany(Photo::class)->where('is_shared', true);
    }
}
