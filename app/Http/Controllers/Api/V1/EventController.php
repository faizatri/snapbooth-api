<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Event\StoreEventRequest;
use App\Http\Requests\Event\UpdateEventRequest;
use App\Http\Resources\EventResource;
use App\Http\Resources\SessionResource;
use App\Models\Event;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventController extends Controller
{
    /**
     * GET /api/v1/events
     *
     * Query params:
     *   filter=active|inactive|upcoming|past
     *   per_page=N (default 15, max 50)
     */
    public function index(Request $request): JsonResponse
    {
        if (! $request->user()->can('viewAny', Event::class)) {
            return $this->forbidden();
        }

        $perPage = min((int) ($request->per_page ?? 15), 50);

        $events = Event::forUser($request->user()->id)
            ->when($request->filter === 'active',   fn ($q) => $q->active())
            ->when($request->filter === 'inactive', fn ($q) => $q->inactive())
            ->when($request->filter === 'upcoming', fn ($q) => $q->upcoming())
            ->when($request->filter === 'past',     fn ($q) => $q->past())
            ->latest()
            ->paginate($perPage);

        return $this->success(EventResource::collection($events)->response()->getData(true));
    }

    /**
     * POST /api/v1/events
     */
    public function store(StoreEventRequest $request): JsonResponse
    {
        if (! $request->user()->can('create', Event::class)) {
            return $this->forbidden();
        }

        $event = $request->user()->events()->create($request->validated());

        return $this->created(new EventResource($event), 'Event created successfully');
    }

    /**
     * GET /api/v1/events/{identifier}
     * Accepts either a numeric ID or a slug string.
     */
    public function show(Request $request, string $identifier): JsonResponse
    {
        $event = is_numeric($identifier)
            ? Event::find((int) $identifier)
            : Event::where('slug', $identifier)->first();

        if (! $event) {
            return $this->notFound('Event not found');
        }

        if (! $request->user()->can('view', $event)) {
            return $this->forbidden();
        }

        return $this->success(new EventResource($event));
    }

    /**
     * PUT /api/v1/events/{event}
     */
    public function update(UpdateEventRequest $request, Event $event): JsonResponse
    {
        if (! $request->user()->can('update', $event)) {
            return $this->forbidden();
        }

        $event->update($request->validated());

        return $this->success(new EventResource($event->fresh()), 'Event updated successfully');
    }

    /**
     * DELETE /api/v1/events/{event}
     */
    public function destroy(Request $request, Event $event): JsonResponse
    {
        if (! $request->user()->can('delete', $event)) {
            return $this->forbidden();
        }

        $event->delete();

        return $this->success(null, 'Event deleted successfully');
    }

    /**
     * GET /api/v1/events/{event}/sessions
     * Returns all sessions with their photos for the given event.
     */
    public function sessions(Request $request, Event $event): JsonResponse
    {
        if (! $request->user()->can('view', $event)) {
            return $this->forbidden();
        }

        $sessions = $event->sessions()
            ->with('photos')
            ->latest('started_at')
            ->get();

        $sessions->each(fn ($session) => $session->setRelation('event', $event));

        return $this->success(SessionResource::collection($sessions));
    }

    /**
     * POST /api/v1/events/{event}/activate
     * Toggles is_active between true and false.
     */
    public function activate(Request $request, Event $event): JsonResponse
    {
        if (! $request->user()->can('activate', $event)) {
            return $this->forbidden();
        }

        $event->update(['is_active' => ! $event->is_active]);

        $event->refresh();

        $message = $event->is_active ? 'Event activated' : 'Event deactivated';

        return $this->success(new EventResource($event), $message);
    }
}
