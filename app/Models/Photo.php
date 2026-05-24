<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

class Photo extends Model
{
    protected $fillable = [
        'session_id',
        'event_id',
        'template_id',
        'file_path',
        'file_url',
        'is_shared',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'is_shared'  => 'boolean',
            'metadata'   => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    // -------------------------------------------------------------------------
    // Accessors
    // -------------------------------------------------------------------------

    /**
     * Accessor untuk file_url — selalu kembalikan URL yang valid dari R2.
     *
     * Strategi (prioritas berurutan):
     *
     * 1. Jika R2_URL (public CDN) dikonfigurasi → gabungkan dengan file_path.
     *    Cocok untuk bucket yang dikonfigurasi sebagai public domain / Cloudflare CDN.
     *    Tidak butuh signing, URL statis dan bisa di-cache di sisi klien.
     *
     * 2. Jika tidak ada CDN → generate signed URL sementara via S3 API.
     *    Default expired 60 menit. Cocok untuk bucket private.
     *
     * Nilai kolom DB ($value) di-ignore karena bisa kadaluarsa (signed URL).
     * Simpan $file_path sebagai single source of truth, file_url hanya cache.
     */
    protected function fileUrl(): Attribute
    {
        return Attribute::make(
            get: function (?string $value): string {
                $r2CdnUrl = config('filesystems.disks.r2.url');

                if ($r2CdnUrl) {
                    return rtrim($r2CdnUrl, '/') . '/' . ltrim($this->file_path, '/');
                }

                return Storage::disk('r2')->temporaryUrl(
                    $this->file_path,
                    now()->addMinutes(60)
                );
            }
        );
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    /** Foto yang sudah dibagikan minimal satu kali. */
    public function scopeShared(Builder $query): void
    {
        $query->where('is_shared', true);
    }

    /** Foto yang belum pernah dibagikan. */
    public function scopeUnshared(Builder $query): void
    {
        $query->where('is_shared', false);
    }

    /** Foto dalam satu event tertentu. */
    public function scopeForEvent(Builder $query, int $eventId): void
    {
        $query->where('event_id', $eventId);
    }

    /** Foto dalam satu sesi tertentu. */
    public function scopeForSession(Builder $query, int $sessionId): void
    {
        $query->where('session_id', $sessionId);
    }

    /** Foto yang menggunakan template tertentu. */
    public function scopeWithTemplate(Builder $query, int $templateId): void
    {
        $query->where('template_id', $templateId);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Generate signed URL dengan durasi kustom.
     * Gunakan ini ketika butuh URL dengan expiry berbeda dari default accessor.
     *
     * Contoh: $photo->signedUrl(minutes: 10) — untuk QR code
     */
    public function signedUrl(int $minutes = 60): string
    {
        return Storage::disk('r2')->temporaryUrl(
            $this->file_path,
            now()->addMinutes($minutes)
        );
    }

    /**
     * Ambil nilai metadata dengan dot-notation.
     * Contoh: $photo->meta('dimensions.width', 0)
     */
    public function meta(string $key, mixed $default = null): mixed
    {
        return data_get($this->metadata, $key, $default);
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function session(): BelongsTo
    {
        return $this->belongsTo(Session::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }

    public function shares(): HasMany
    {
        return $this->hasMany(Share::class);
    }

    /** Hanya share yang berhasil terkirim. */
    public function sentShares(): HasMany
    {
        return $this->hasMany(Share::class)->where('status', 'sent');
    }
}
