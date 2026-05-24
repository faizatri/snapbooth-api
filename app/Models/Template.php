<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Template extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'preview_url',
        'config',
        'is_public',
    ];

    protected function casts(): array
    {
        return [
            'config'    => 'array',
            'is_public' => 'boolean',
        ];
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    /** Hanya template yang bisa dipakai semua user. */
    public function scopePublic(Builder $query): void
    {
        $query->where('is_public', true);
    }

    /** Template bawaan sistem (bukan milik user). */
    public function scopeSystem(Builder $query): void
    {
        $query->whereNull('user_id');
    }

    /**
     * Template yang tersedia untuk user tertentu:
     * milik sendiri + semua template publik + template sistem.
     *
     * Contoh: Template::availableFor(auth()->id())->get()
     */
    public function scopeAvailableFor(Builder $query, int $userId): void
    {
        $query->where(function (Builder $q) use ($userId) {
            $q->where('user_id', $userId)
              ->orWhere('is_public', true)
              ->orWhereNull('user_id');
        });
    }

    /** Hanya template milik user tertentu (tidak termasuk publik/sistem). */
    public function scopeOwnedBy(Builder $query, int $userId): void
    {
        $query->where('user_id', $userId);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    public function isSystemTemplate(): bool
    {
        return $this->user_id === null;
    }

    public function isOwnedBy(int $userId): bool
    {
        return $this->user_id === $userId;
    }

    /**
     * Ambil nilai konfigurasi template dengan dot-notation.
     * Contoh: $template->config('layers.0.type', 'image')
     */
    public function config(string $key, mixed $default = null): mixed
    {
        return data_get($this->config, $key, $default);
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(Photo::class);
    }
}
