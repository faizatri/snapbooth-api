<?php

use App\Models\Event;
use Illuminate\Support\Facades\Broadcast;

/*
 * Private channel: event.{eventId}
 * Only the event owner (authenticated via Sanctum) can subscribe.
 *
 * Frontend connects with:
 *   Echo.private(`event.${eventId}`).listen('PhotoUploaded', ...)
 */
Broadcast::channel('event.{eventId}', function ($user, int $eventId) {
    $event = Event::find($eventId);

    return $event && $user->id === $event->user_id;
});
