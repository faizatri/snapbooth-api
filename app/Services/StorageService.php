<?php

namespace App\Services;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class StorageService
{
    private string $diskName;

    public function __construct()
    {
        $this->diskName = config('filesystems.storage_disk', 'r2');
    }

    // =========================================================================
    // Photos
    // =========================================================================

    /**
     * Upload a photo for an event and return the public URL.
     *
     * The returned URL is ready to store in the database.
     * Path structure: events/{eventId}/photos/{uuid}.{ext}
     *
     * @throws RuntimeException on upload failure
     */
    public function uploadPhoto(UploadedFile $file, int $eventId): string
    {
        $path = $this->putFile($file, "events/{$eventId}/photos");

        return $this->url($path);
    }

    /**
     * Delete a photo from storage.
     *
     * @param  string $path  Relative storage path, e.g. "events/1/photos/uuid.jpg"
     * @throws RuntimeException on deletion failure
     */
    public function deletePhoto(string $path): bool
    {
        try {
            return $this->disk()->delete($path);
        } catch (Throwable $e) {
            throw new RuntimeException(
                "Failed to delete photo at [{$path}]: {$e->getMessage()}", 0, $e
            );
        }
    }

    /**
     * Generate a time-limited presigned URL for private/temporary file access.
     *
     * Use this when files are NOT publicly accessible (e.g., original hi-res
     * photos behind a paywall). Falls back to public URL on local disk.
     *
     * @param  string $path     Relative storage path
     * @param  int    $minutes  URL lifetime in minutes (default 60)
     * @throws RuntimeException if the file does not exist or signing fails
     */
    public function generateSignedUrl(string $path, int $minutes = 60): string
    {
        if (! $this->disk()->exists($path)) {
            throw new RuntimeException(
                "Cannot sign URL: file not found at [{$path}]."
            );
        }

        // Local disk has no signing capability — return public URL instead.
        // This is intentional for dev/test environments.
        if ($this->diskName !== 'r2') {
            return $this->url($path);
        }

        if (empty(config('filesystems.disks.r2.endpoint'))) {
            throw new RuntimeException(
                'R2_ENDPOINT is not configured. Cannot generate a presigned URL.'
            );
        }

        try {
            return $this->disk()->temporaryUrl($path, now()->addMinutes($minutes));
        } catch (Throwable $e) {
            throw new RuntimeException(
                "Failed to generate signed URL for [{$path}]: {$e->getMessage()}", 0, $e
            );
        }
    }

    /**
     * Store raw binary content at an explicit path.
     * Used for processed images and QR codes that come as byte strings.
     *
     * @throws RuntimeException
     */
    public function putRaw(string $path, string $content): void
    {
        try {
            $result = $this->disk()->put($path, $content);
            if ($result === false) {
                throw new RuntimeException(
                    "Disk returned false when writing [{$path}]."
                );
            }
        } catch (RuntimeException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new RuntimeException(
                "Failed to write [{$path}] to disk [{$this->diskName}]: {$e->getMessage()}", 0, $e
            );
        }
    }

    /**
     * Upload a processed photo (raw JPEG/PNG bytes) for an event.
     * Returns [path, url] tuple — path for DB storage, url ready to display.
     *
     * @return array{path: string, url: string}
     * @throws RuntimeException
     */
    public function putProcessedPhoto(string $content, int $eventId, string $ext = 'jpg'): array
    {
        $filename = Str::uuid() . '.' . $ext;
        $path     = "events/{$eventId}/photos/{$filename}";

        $this->putRaw($path, $content);

        return ['path' => $path, 'url' => $this->url($path)];
    }

    /**
     * Upload a thumbnail image for an event.
     * Returns [path, url] tuple — path for DB storage, url ready to display.
     * Path structure: events/{eventId}/thumbnails/{uuid}.jpg
     *
     * @return array{path: string, url: string}
     * @throws RuntimeException
     */
    public function putThumbnail(string $content, int $eventId, string $ext = 'jpg'): array
    {
        $filename = Str::uuid() . '.' . $ext;
        $path     = "events/{$eventId}/thumbnails/{$filename}";

        $this->putRaw($path, $content);

        return ['path' => $path, 'url' => $this->url($path)];
    }

    /**
     * Upload a QR code PNG and return its public URL.
     *
     * @throws RuntimeException
     */
    public function putQrCode(string $content, string $token): string
    {
        $path = "qr/{$token}.png";
        $this->putRaw($path, $content);
        return $this->url($path);
    }

    /**
     * Delete a file at a generic path (for non-photo files like QR codes).
     */
    public function delete(string $path): bool
    {
        try {
            return $this->disk()->delete($path);
        } catch (Throwable $e) {
            return false;
        }
    }

    // =========================================================================
    // Templates
    // =========================================================================

    /**
     * Upload a template preview image.
     * Returns the relative path; convert to URL with url() before storing.
     *
     * @throws RuntimeException on upload failure
     */
    public function uploadPreview(UploadedFile $file): string
    {
        return $this->putFile($file, 'templates/previews');
    }

    // =========================================================================
    // URL & existence helpers
    // =========================================================================

    /**
     * Build the public URL for a stored path.
     *
     * Priority:
     *   1. R2_URL (custom CDN / public domain) — recommended for production
     *   2. Disk-level URL (e.g. APP_URL/storage/snapbooth for local_public)
     */
    public function url(string $path): string
    {
        $baseUrl = config("filesystems.disks.{$this->diskName}.url");

        if ($baseUrl) {
            return rtrim($baseUrl, '/') . '/' . ltrim($path, '/');
        }

        return $this->disk()->url($path);
    }

    /**
     * Check whether a file exists on the configured disk.
     */
    public function exists(string $path): bool
    {
        return $this->disk()->exists($path);
    }

    // =========================================================================
    // Internal
    // =========================================================================

    /**
     * Stream a file onto the disk and return its relative path.
     *
     * Uses putFileAs() which the Flysystem S3 adapter handles as a streaming
     * multipart upload — no full file load into PHP memory.
     *
     * Note: no ACL/visibility option is passed to R2 because R2 does NOT
     * support per-object ACLs; access is controlled at the bucket level via
     * the R2 dashboard. For the local_public disk the driver-level default
     * visibility = 'public' already applies.
     *
     * @throws RuntimeException
     */
    private function putFile(UploadedFile $file, string $directory): string
    {
        $filename = Str::uuid() . '.' . strtolower($file->getClientOriginalExtension());

        try {
            $path = $this->disk()->putFileAs($directory, $file, $filename);

            if ($path === false) {
                throw new RuntimeException(
                    'Disk returned false — check credentials, bucket name, and endpoint.'
                );
            }

            return $path;

        } catch (RuntimeException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new RuntimeException(
                "Upload to disk [{$this->diskName}] directory [{$directory}] failed: "
                . $e->getMessage(),
                0,
                $e
            );
        }
    }

    private function disk(): Filesystem
    {
        return Storage::disk($this->diskName);
    }
}
