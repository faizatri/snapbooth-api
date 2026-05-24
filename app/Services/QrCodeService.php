<?php

namespace App\Services;

use SimpleSoftwareIO\QrCode\Facades\QrCode;

class QrCodeService
{
    public function __construct(private StorageService $storage) {}

    /**
     * Generate a QR code PNG for the given URL, persist it, and return the public URL.
     *
     * The PNG is stored under qr/{token}.png. Calling this again with the same
     * token overwrites the previous file (idempotent).
     *
     * @param  string $url    The URL the QR code should encode
     * @param  string $token  Used as the filename key (session_token)
     * @return string         Public URL of the stored QR PNG
     */
    public function generate(string $url, string $token): string
    {
        $png = QrCode::format('png')
            ->size(400)
            ->margin(2)
            ->errorCorrection('M')
            ->generate($url);

        return $this->storage->putQrCode($png, $token);
    }

    /**
     * Build the gallery URL that the QR code should point to.
     * Configurable via GALLERY_URL env; falls back to the API's own endpoint.
     */
    public function galleryUrl(string $sessionToken): string
    {
        $base = config('app.gallery_url', config('app.url') . '/api/v1/booth');
        return rtrim($base, '/') . '/' . $sessionToken;
    }
}
