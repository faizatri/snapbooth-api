<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Booth\CompleteSessionRequest;
use App\Http\Requests\Booth\StartSessionRequest;
use App\Http\Requests\Booth\UploadPhotoRequest;
use App\Events\PhotoUploaded;
use App\Events\SessionCompleted;
use App\Exceptions\ImageProcessingException;
use App\Models\Event;
use App\Models\Photo;
use App\Models\Session;
use App\Models\Template;
use App\Services\ImageProcessingService;
use App\Services\StorageService;
use Illuminate\Http\JsonResponse;

class BoothController extends Controller
{
    public function __construct(
        private StorageService $storage,
        private ImageProcessingService $imageProcessor,
    ) {}

    // =========================================================================
    // POST /api/v1/booth/start-session
    // Body: { event_slug, guest_name?, guest_email? }
    // =========================================================================

    public function startSession(StartSessionRequest $request): JsonResponse
    {
        $event = Event::where('slug', $request->event_slug)->first();

        if (! $event) {
            return $this->notFound('Event not found');
        }

        if (! $event->is_active) {
            return $this->error('This booth is not currently active', null, 403);
        }

        $session = Session::create([
            'event_id'    => $event->id,
            'guest_name'  => $request->guest_name,
            'guest_email' => $request->guest_email,
        ]);

        return $this->created([
            'session_id'    => $session->id,
            'session_token' => $session->session_token,
            'expires_at'    => $session->expires_at->toISOString(),
            'event_config'  => $event->booth_config ?? [],
        ], 'Session started');
    }

    // =========================================================================
    // POST /api/v1/booth/upload-photo
    // Body: { session_token, photo (file or base64), shot_number? }
    // =========================================================================

    public function uploadPhoto(UploadPhotoRequest $request): JsonResponse
    {
        $session = $this->resolveActiveSession($request->session_token);
        if ($session instanceof JsonResponse) {
            return $session;
        }

        $event = $session->event;

        $existingCount = Photo::where('session_id', $session->id)->count();
        $maxPhotos     = $event->boothConfig('photos_per_session', 10);
        if ($existingCount >= $maxPhotos) {
            return $this->error("Maximum {$maxPhotos} photos per session reached", null, 422);
        }

        $shotNumber = $request->integer('shot_number', $existingCount + 1);

        // Accept either a file upload or a base64 string
        $srcPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'booth_src_' . \Illuminate\Support\Str::uuid() . '.jpg';

        if ($request->hasFile('photo')) {
            $request->file('photo')->move(dirname($srcPath), basename($srcPath));
        } else {
            $raw = $this->decodeBase64((string) $request->input('photo'));
            if ($raw === null) {
                return $this->error('Invalid image data', null, 422);
            }
            file_put_contents($srcPath, $raw);
        }

        try {
            $result = $this->imageProcessor->processPhoto($srcPath, [
                'filter'          => $event->boothConfig('filter'),
                'template_config' => $this->resolveTemplateConfig($event),
                'watermark'       => config('booth.watermark_text'),
            ]);
        } catch (ImageProcessingException $e) {
            @unlink($srcPath);
            return $this->error('Image processing failed: ' . $e->getMessage(), null, 422);
        } finally {
            @unlink($srcPath);
        }

        // Upload hasil proses ke storage dan bersihkan temp files
        try {
            $processedBytes = file_get_contents($result['processed_path']);
            $thumbBytes     = file_get_contents($result['thumbnail_path']);

            ['path' => $path, 'url' => $url]           = $this->storage->putProcessedPhoto($processedBytes, $event->id);
            ['path' => $thumbPath, 'url' => $thumbUrl] = $this->storage->putThumbnail($thumbBytes, $event->id);
        } finally {
            @unlink($result['processed_path']);
            @unlink($result['thumbnail_path']);
        }

        $photo = Photo::create([
            'session_id'     => $session->id,
            'event_id'       => $event->id,
            'template_id'    => $event->boothConfig('template_id'),
            'shot_number'    => $shotNumber,
            'file_path'      => $path,
            'file_url'       => $url,
            'thumbnail_path' => $thumbPath,
            'metadata'       => [
                'original_width'  => $result['original_width'],
                'original_height' => $result['original_height'],
                'filter'          => $event->boothConfig('filter'),
                'size_bytes'      => strlen($processedBytes),
            ],
        ]);

        PhotoUploaded::dispatch($photo);

        return $this->created([
            'id'            => $photo->id,
            'processed_url' => $photo->file_url,
            'thumbnail_url' => $photo->thumbnail_url,
        ], 'Photo uploaded');
    }

    // =========================================================================
    // POST /api/v1/booth/complete-session
    // Body: { session_token, selected_photo_ids[] }
    // =========================================================================

    public function completeSession(CompleteSessionRequest $request): JsonResponse
    {
        $session = $this->resolveActiveSession($request->session_token);
        if ($session instanceof JsonResponse) {
            return $session;
        }

        $selectedIds = $request->selected_photo_ids;

        // All selected IDs must belong to this session
        $found = Photo::where('session_id', $session->id)
            ->whereIn('id', $selectedIds)
            ->count();

        if ($found !== count($selectedIds)) {
            return $this->error(
                'One or more selected photos do not belong to this session',
                null,
                422
            );
        }

        $session->complete($selectedIds);

        $finalPhotos = Photo::where('session_id', $session->id)
            ->where('is_final', true)
            ->orderBy('shot_number')
            ->get()
            ->map(fn (Photo $p) => [
                'id'            => $p->id,
                'shot_number'   => $p->shot_number,
                'processed_url' => $p->file_url,
                'thumbnail_url' => $p->thumbnail_url,
            ]);

        SessionCompleted::dispatch($session->fresh());

        return $this->success([
            'session_id'   => $session->id,
            'share_token'  => $session->share_token,
            'final_photos' => $finalPhotos,
        ], 'Session completed');
    }

    // =========================================================================
    // GET /api/v1/booth/session/{shareToken}   [public]
    // =========================================================================

    public function showSession(string $shareToken): JsonResponse
    {
        $session = Session::with([
            'event',
            'photos' => fn ($q) => $q->where('is_final', true)->orderBy('shot_number'),
        ])->where('share_token', $shareToken)->first();

        if (! $session) {
            return $this->notFound('Session not found');
        }

        return $this->success([
            'session' => [
                'id'           => $session->id,
                'guest_name'   => $session->guest_name,
                'completed_at' => $session->ended_at?->toISOString(),
                'event'        => [
                    'name'     => $session->event->name,
                    'date'     => $session->event->date->toDateString(),
                    'location' => $session->event->location,
                ],
            ],
            'photos' => $session->photos->map(fn (Photo $p) => [
                'id'            => $p->id,
                'shot_number'   => $p->shot_number,
                'processed_url' => $p->file_url,
                'thumbnail_url' => $p->thumbnail_url,
            ]),
        ]);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Find and validate that a session_token points to an active, non-expired session.
     * Returns the Session with 'event' loaded, or a JsonResponse with the appropriate error.
     */
    private function resolveActiveSession(string $token): Session|JsonResponse
    {
        $session = Session::with('event')
            ->where('session_token', $token)
            ->first();

        if (! $session) {
            return $this->error('Invalid session token', null, 401);
        }

        if ($session->isExpired()) {
            return $this->error('Session has expired', null, 401);
        }

        if (! $session->isActive()) {
            return $this->error('Session has already been completed', null, 422);
        }

        return $session;
    }

    /**
     * Decode a base64 string, stripping the data URI prefix if present.
     * Returns null on invalid input.
     */
    private function decodeBase64(string $data): ?string
    {
        // Strip "data:image/jpeg;base64," prefix
        if (str_contains($data, ',')) {
            $data = substr($data, strpos($data, ',') + 1);
        }

        $decoded = base64_decode($data, strict: true);

        return $decoded !== false ? $decoded : null;
    }

    /**
     * Kembalikan config template lengkap (array) untuk diteruskan ke applyFrame().
     * Return null jika event tidak punya template yang dikonfigurasi.
     */
    private function resolveTemplateConfig(Event $event): ?array
    {
        $templateId = $event->boothConfig('template_id');
        if (! $templateId) {
            return null;
        }

        $template = Template::find($templateId);

        return $template?->config;
    }
}
