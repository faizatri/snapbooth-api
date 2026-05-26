<?php

namespace App\Events;

use App\Models\Session;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SessionCompleted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly Session $session) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('event.' . $this->session->event_id)];
    }

    public function broadcastAs(): string
    {
        return 'session.completed';
    }

    public function broadcastWith(): array
    {
        return [
            'session_id'   => $this->session->id,
            'event_id'     => $this->session->event_id,
            'share_token'  => $this->session->share_token,
            'guest_name'   => $this->session->guest_name,
            'started_at'   => $this->session->started_at?->toISOString(),
            'ended_at'     => $this->session->ended_at?->toISOString(),
            'photos_count' => $this->session->photos()->count(),
        ];
    }
}
