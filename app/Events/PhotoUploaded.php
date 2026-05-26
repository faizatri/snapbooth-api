<?php

namespace App\Events;

use App\Models\Photo;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PhotoUploaded implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly Photo $photo) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('event.' . $this->photo->event_id)];
    }

    public function broadcastAs(): string
    {
        return 'photo.uploaded';
    }

    public function broadcastWith(): array
    {
        return [
            'photo_id'      => $this->photo->id,
            'session_id'    => $this->photo->session_id,
            'event_id'      => $this->photo->event_id,
            'processed_url' => $this->photo->file_url,
            'thumbnail_url' => $this->photo->thumbnail_url,
            'shot_number'   => $this->photo->shot_number,
        ];
    }
}
