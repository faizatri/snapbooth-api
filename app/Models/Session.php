<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Session extends Model
{
    // Tabel ini tidak punya created_at/updated_at — hanya started_at & ended_at
    public $timestamps = false;

    protected $fillable = [
        'event_id',
        'guest_name',
        'guest_email',
        'guest_phone',
        'session_token',
        'share_token',
        'started_at',
        'ended_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at'   => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Session $session) {
            if (empty($session->session_token)) {
                $session->session_token = Str::random(64);
            }

            if (empty($session->started_at)) {
                $session->started_at = now();
            }

            if (empty($session->expires_at)) {
                $session->expires_at = now()->addHours(2);
            }
        });
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    /** Sesi yang masih berjalan (tamu belum selesai). */
    public function scopeActive(Builder $query): void
    {
        $query->whereNull('ended_at');
    }

    /** Sesi yang sudah selesai. */
    public function scopeCompleted(Builder $query): void
    {
        $query->whereNotNull('ended_at');
    }

    /**
     * Sesi yang berlangsung lebih dari N detik.
     * Berguna untuk filter sesi "meaningful" di analitik.
     */
    public function scopeLongerThan(Builder $query, int $seconds): void
    {
        $query->whereNotNull('ended_at')
              ->whereRaw('TIMESTAMPDIFF(SECOND, started_at, ended_at) > ?', [$seconds]);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    public function isActive(): bool
    {
        return $this->ended_at === null;
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /** Tutup sesi dan catat waktu selesai. */
    public function end(): void
    {
        if ($this->isActive()) {
            $this->update(['ended_at' => now()]);
        }
    }

    /**
     * Selesaikan sesi: tandai foto-foto terpilih sebagai final,
     * generate share_token publik, dan set ended_at.
     */
    public function complete(array $selectedPhotoIds): void
    {
        Photo::where('session_id', $this->id)
             ->whereIn('id', $selectedPhotoIds)
             ->update(['is_final' => true]);

        $this->update([
            'ended_at'    => now(),
            'share_token' => Str::random(64),
        ]);
    }

    /** Durasi sesi dalam detik; null jika sesi masih aktif. */
    public function durationInSeconds(): ?int
    {
        if ($this->ended_at === null) {
            return null;
        }

        return (int) $this->started_at->diffInSeconds($this->ended_at);
    }

    /** Durasi dalam format human-readable, misal "3 minutes 42 seconds". */
    public function durationForHumans(): ?string
    {
        if ($this->ended_at === null) {
            return null;
        }

        return $this->started_at->diffForHumans($this->ended_at, true);
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(Photo::class);
    }
}
