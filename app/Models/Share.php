<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Share extends Model
{
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

    public function photo(): BelongsTo
    {
        return $this->belongsTo(Photo::class);
    }

    public function markAsSent(): void
    {
        $this->update(['status' => 'sent']);
        $this->photo->update(['is_shared' => true]);
    }

    public function markAsFailed(): void
    {
        $this->update(['status' => 'failed']);
    }
}
