<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Share\EmailShareRequest;
use App\Mail\GalleryShareMail;
use App\Models\Photo;
use App\Models\Session;
use App\Models\Share;
use App\Services\StorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Throwable;

class ShareController extends Controller
{
    public function __construct(private StorageService $storage) {}

    // =========================================================================
    // GET /api/v1/share/{shareToken}/qr
    // Return: PNG binary (Content-Type: image/png)
    // =========================================================================

    public function qr(string $shareToken): Response|JsonResponse
    {
        $session = $this->resolveSharedSession($shareToken);
        if ($session === null) {
            return $this->notFound('Gallery not found');
        }

        $shareUrl = $this->buildShareUrl($shareToken);

        $qrCode = QrCode::create($shareUrl)
            ->setEncoding(new Encoding('UTF-8'))
            ->setErrorCorrectionLevel(ErrorCorrectionLevel::Medium)
            ->setSize(500)
            ->setMargin(10)
            ->setForegroundColor(new Color(0, 0, 0))
            ->setBackgroundColor(new Color(255, 255, 255));

        $result = (new PngWriter())->write($qrCode);
        $png    = $result->getString();

        $this->logShares($session, 'qr');

        return response($png, 200, [
            'Content-Type'        => 'image/png',
            'Content-Disposition' => "inline; filename=\"qr-{$shareToken}.png\"",
            'Cache-Control'       => 'public, max-age=3600',
        ]);
    }

    // =========================================================================
    // POST /api/v1/share/{shareToken}/email
    // Body: { email }
    // =========================================================================

    public function email(EmailShareRequest $request, string $shareToken): JsonResponse
    {
        $session = $this->resolveSharedSession($shareToken);
        if ($session === null) {
            return $this->notFound('Gallery not found');
        }

        $recipient = $request->input('email');
        $shareUrl  = $this->buildShareUrl($shareToken);

        // Buat semua share record (pending) sebelum kirim
        $shares = $session->photos->map(fn (Photo $p) => Share::create([
            'photo_id'  => $p->id,
            'channel'   => 'email',
            'recipient' => $recipient,
            'status'    => 'pending',
        ]));

        try {
            Mail::to($recipient)->send(new GalleryShareMail($session, $shareUrl));
            $shares->each->markAsSent();
        } catch (Throwable $e) {
            $shares->each->markAsFailed();
            Log::error('ShareController: email send failed', [
                'share_token' => $shareToken,
                'recipient'   => $recipient,
                'error'       => $e->getMessage(),
            ]);
            return $this->error('Failed to send email. Please try again.', null, 500);
        }

        return $this->success([
            'channel'     => 'email',
            'recipient'   => $recipient,
            'photos_sent' => $shares->count(),
        ], 'Email sent successfully');
    }

    // =========================================================================
    // POST /api/v1/share/{shareToken}/whatsapp
    // Return: { whatsapp_url, message, share_url }
    // =========================================================================

    public function whatsapp(string $shareToken): JsonResponse
    {
        $session = $this->resolveSharedSession($shareToken);
        if ($session === null) {
            return $this->notFound('Gallery not found');
        }

        $shareUrl  = $this->buildShareUrl($shareToken);
        $eventName = $session->event->name ?? 'acara';

        $message = "Foto booth kamu dari *{$eventName}* sudah siap! 📸\n\n"
                 . "Lihat dan download semua foto di sini:\n"
                 . $shareUrl;

        $whatsappUrl = 'https://wa.me/?text=' . urlencode($message);

        $this->logShares($session, 'whatsapp');

        return $this->success([
            'channel'      => 'whatsapp',
            'whatsapp_url' => $whatsappUrl,
            'message'      => $message,
            'share_url'    => $shareUrl,
        ], 'WhatsApp share link generated');
    }

    // =========================================================================
    // GET /api/v1/download/{photoId}
    // Return: { download_url (signed, 1 jam), expires_at }
    // =========================================================================

    public function download(string $photoId): JsonResponse
    {
        $photo = Photo::with('session')->find((int) $photoId);

        if (! $photo || ! $photo->is_final) {
            return $this->notFound('Photo not found');
        }

        // Foto harus milik sesi yang sudah diselesaikan (punya share_token)
        if (! $photo->session?->share_token) {
            return $this->notFound('Photo is not available for download');
        }

        $expiresAt = now()->addMinutes(60);
        $signedUrl = $this->storage->generateSignedUrl($photo->file_path, 60);

        $share = Share::create([
            'photo_id' => $photo->id,
            'channel'  => 'link',
            'status'   => 'pending',
        ]);
        $share->markAsSent();

        return $this->success([
            'download_url' => $signedUrl,
            'expires_at'   => $expiresAt->toISOString(),
            'photo_id'     => $photo->id,
        ], 'Download URL generated');
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    /**
     * Find session by share_token dengan eager load event + final photos.
     * Returns null jika share_token tidak valid.
     */
    private function resolveSharedSession(string $shareToken): ?Session
    {
        return Session::with([
            'event',
            'photos' => fn ($q) => $q->where('is_final', true)->orderBy('shot_number'),
        ])->where('share_token', $shareToken)->first();
    }

    /**
     * URL halaman download tamu di frontend.
     * Format: {SHARE_URL}/{share_token}
     */
    private function buildShareUrl(string $shareToken): string
    {
        return rtrim(config('booth.share_url', url('/share')), '/') . '/' . $shareToken;
    }

    /**
     * Log aktivitas share ke tabel shares untuk setiap foto final dalam sesi.
     * Satu record per foto. Foto non-final tidak dicatat.
     */
    private function logShares(Session $session, string $channel, ?string $recipient = null): void
    {
        foreach ($session->photos as $photo) {
            $share = Share::create([
                'photo_id'  => $photo->id,
                'channel'   => $channel,
                'recipient' => $recipient,
                'status'    => 'pending',
            ]);
            $share->markAsSent();
        }
    }
}
