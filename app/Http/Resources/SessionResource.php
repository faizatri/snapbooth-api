<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'session_token' => $this->session_token,
            'guest_name'    => $this->guest_name,
            'guest_email'   => $this->guest_email,
            'guest_phone'   => $this->guest_phone,
            'is_active'     => $this->isActive(),
            'created_at'    => $this->started_at?->toISOString(),
            'started_at'    => $this->started_at?->toISOString(),
            'ended_at'      => $this->ended_at?->toISOString(),
            'duration'      => $this->durationForHumans(),
            'event'         => [
                'id'          => $this->event->id,
                'name'        => $this->event->name,
                'slug'        => $this->event->slug,
                'booth_config' => $this->event->booth_config,
            ],
            'photos_count'  => $this->whenLoaded('photos', fn () => $this->photos->count()),
            'photos'        => PhotoResource::collection($this->whenLoaded('photos')),
        ];
    }
}
