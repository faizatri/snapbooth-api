<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'password',
        'subscription_plan',
        'subscription_expires_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at'       => 'datetime',
            'password'                => 'hashed',
            'subscription_expires_at' => 'datetime',
        ];
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    /** Filter berdasarkan paket: User::onPlan('pro')->get() */
    public function scopeOnPlan(Builder $query, string $plan): void
    {
        $query->where('subscription_plan', $plan);
    }

    /**
     * Subscription yang akan kadaluarsa dalam N hari ke depan.
     * Berguna untuk cron job kirim notifikasi "langganan hampir habis".
     */
    public function scopeSubscriptionExpiringSoon(Builder $query, int $days = 7): void
    {
        $query->whereNotNull('subscription_expires_at')
              ->whereBetween('subscription_expires_at', [now(), now()->addDays($days)]);
    }

    /** User dengan subscription aktif (non-free yang belum expired). */
    public function scopeWithActiveSubscription(Builder $query): void
    {
        $query->where(function (Builder $q) {
            $q->where('subscription_plan', 'free')
              ->orWhere(function (Builder $inner) {
                  $inner->where('subscription_plan', '!=', 'free')
                        ->where('subscription_expires_at', '>', now());
              });
        });
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    public function hasActiveSubscription(): bool
    {
        if ($this->subscription_plan === 'free') {
            return true;
        }

        return $this->subscription_expires_at?->isFuture() ?? false;
    }

    public function isOnPlan(string $plan): bool
    {
        return $this->subscription_plan === $plan;
    }

    /** Sisa hari subscription; null untuk free tier. */
    public function subscriptionDaysLeft(): ?int
    {
        if ($this->subscription_plan === 'free' || $this->subscription_expires_at === null) {
            return null;
        }

        return max(0, (int) now()->diffInDays($this->subscription_expires_at, false));
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    public function templates(): HasMany
    {
        return $this->hasMany(Template::class);
    }
}
