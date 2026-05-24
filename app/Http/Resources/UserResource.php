<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                       => $this->id,
            'name'                     => $this->name,
            'email'                    => $this->email,
            'subscription_plan'        => $this->subscription_plan,
            'subscription_expires_at'  => $this->subscription_expires_at?->toISOString(),
            'has_active_subscription'  => $this->hasActiveSubscription(),
            'created_at'               => $this->created_at?->toISOString(),
        ];
    }
}
