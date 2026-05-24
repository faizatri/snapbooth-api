<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Session extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'event_id',
        'guest_name',
        'guest_email',
        'guest_phone',
        'session_token',
        'started_at',
        'ended_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at'   => 'datetime',
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
        });
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(Photo::class);
    }

    public function isActive(): bool
    {
        return $this->ended_at === null;
    }

    public function durationInSeconds(): ?int
    {
        if ($this->ended_at === null) {
            return null;
        }

        return (int) $this->started_at->diffInSeconds($this->ended_at);
    }
}
