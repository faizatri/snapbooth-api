<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
            'is_shared' => 'boolean',
            'metadata'  => 'array',
        ];
    }

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
}
