<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Share extends Model
{
    // Share adalah immutable event — tidak ada updated_at.
    // Kirim ulang = buat record baru agar audit trail tetap lengkap.
    public $timestamps = false;

    protected $fillable = [
        'photo_id',
        'channel',
        'recipient',
        'sent_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Share $share) {
            if (empty($share->sent_at)) {
                $share->sent_at = now();
            }
        });
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopePending(Builder $query): void
    {
        $query->where('status', 'pending');
    }

    public function scopeSent(Builder $query): void
    {
        $query->where('status', 'sent');
    }

    public function scopeFailed(Builder $query): void
    {
        $query->where('status', 'failed');
    }

    /** Filter berdasarkan saluran: Share::viaChannel('whatsapp')->pending()->get() */
    public function scopeViaChannel(Builder $query, string $channel): void
    {
        $query->where('channel', $channel);
    }

    /**
     * Share yang dikirim dalam rentang waktu tertentu.
     * Berguna untuk laporan harian/mingguan.
     */
    public function scopeSentBetween(Builder $query, string $from, string $until): void
    {
        $query->whereBetween('sent_at', [$from, $until]);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Tandai share berhasil dan update flag is_shared di foto.
     * Dua operasi ini selalu berjalan bersamaan — jangan panggil terpisah.
     */
    public function markAsSent(): void
    {
        $this->update(['status' => 'sent']);

        // Sinkronisasi flag denormalisasi di photos
        $this->photo()->update(['is_shared' => true]);
    }

    public function markAsFailed(): void
    {
        $this->update(['status' => 'failed']);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isSent(): bool
    {
        return $this->status === 'sent';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function photo(): BelongsTo
    {
        return $this->belongsTo(Photo::class);
    }
}
