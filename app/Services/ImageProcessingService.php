<?php

namespace App\Services;

use App\Exceptions\ImageProcessingException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\ImageManager;
use Intervention\Image\Typography\FontFactory;
use Throwable;

class ImageProcessingService
{
    private ImageManager $manager;
    private string $tempDir;

    public function __construct()
    {
        $this->manager = new ImageManager(GdDriver::class);
        $this->tempDir = sys_get_temp_dir();
    }

    // =========================================================================
    // 1. applyFilter — modifikasi foto (copy) dengan colour filter
    // =========================================================================

    /**
     * Terapkan colour filter ke sebuah file gambar secara in-place.
     *
     * Panggil ini HANYA pada working copy, bukan file asli.
     *
     * @param  string $imagePath Path absolut ke file gambar
     * @param  string $filter    'normal' | 'grayscale' | 'sepia' | 'vivid'
     * @throws ImageProcessingException jika filter tidak dikenal atau file tidak terbaca
     */
    public function applyFilter(string $imagePath, string $filter): void
    {
        $this->assertFileExists($imagePath);

        if ($filter === 'normal' || $filter === '') {
            return;
        }

        try {
            $image = $this->manager->read($imagePath);

            match ($filter) {
                'grayscale' => $image->greyscale(),

                // Sepia: grayscale → warm tint (+red, +green, -blue)
                'sepia' => $image->greyscale()->colorize(25, 8, -18),

                // Vivid: boost contrast dan brightness agar warna lebih "pop"
                'vivid' => $image->contrast(25)->brightness(5),

                default => throw new ImageProcessingException(
                    "Unknown filter [{$filter}]. Supported: normal, grayscale, sepia, vivid."
                ),
            };

            $image->toJpeg(quality: config('booth.output_quality', 85))->save($imagePath);

        } catch (ImageProcessingException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new ImageProcessingException(
                "applyFilter failed on [{$imagePath}]: {$e->getMessage()}", 0, $e
            );
        }
    }

    // =========================================================================
    // 2. applyFrame — overlay frame PNG dari templateConfig di atas foto
    // =========================================================================

    /**
     * Overlay frame/border template di atas foto.
     *
     * Frame diambil dari `overlay_url` (HTTP) atau `overlay_path` (lokal) dalam $templateConfig.
     * Jika frame tidak bisa dimuat, peringatan dicatat dan proses dilanjutkan tanpa frame
     * (frame bersifat opsional — tidak boleh memblokir upload foto).
     *
     * @param  string $imagePath      Path absolut ke working copy foto
     * @param  array  $templateConfig Konfigurasi template, minimal berisi key:
     *                                - 'overlay_url'  (string) URL ke PNG frame, atau
     *                                - 'overlay_path' (string) path lokal ke PNG frame
     *                                Opsional: 'opacity' (int 0–100, default 100)
     * @throws ImageProcessingException jika file gambar tidak terbaca
     */
    public function applyFrame(string $imagePath, array $templateConfig): void
    {
        $this->assertFileExists($imagePath);

        $source  = $templateConfig['overlay_url'] ?? $templateConfig['overlay_path'] ?? null;
        $opacity = (int) ($templateConfig['opacity'] ?? 100);

        if (! $source) {
            return;
        }

        $overlayData = $this->fetchOverlay($source);

        if ($overlayData === null) {
            Log::warning('ImageProcessingService: Could not load frame overlay, skipping.', [
                'source' => $source,
            ]);
            return;
        }

        try {
            $image   = $this->manager->read($imagePath);
            $overlay = $this->manager->read($overlayData);

            // Frame di-resize agar pas dengan dimensi foto
            $overlay->resize($image->width(), $image->height());

            // Place overlay on top; opacity 0-100 controls frame transparency
            $image->place($overlay, 'top-left', 0, 0, $opacity);

            $image->toJpeg(quality: config('booth.output_quality', 85))->save($imagePath);

        } catch (Throwable $e) {
            throw new ImageProcessingException(
                "applyFrame failed on [{$imagePath}]: {$e->getMessage()}", 0, $e
            );
        }
    }

    // =========================================================================
    // 3. addWatermark — teks kecil di pojok kanan bawah
    // =========================================================================

    /**
     * Tambahkan watermark teks kecil di pojok kanan bawah foto.
     *
     * Membutuhkan font TTF yang dikonfigurasi via WATERMARK_FONT_PATH atau
     * terdeteksi otomatis dari sistem. Jika font tidak tersedia, operasi
     * dilewati (non-fatal) dengan peringatan di log.
     *
     * @param  string $imagePath Path absolut ke working copy foto
     * @param  string $text      Teks watermark (contoh: "SnapBooth • @weddingname")
     * @throws ImageProcessingException jika file gambar tidak terbaca
     */
    public function addWatermark(string $imagePath, string $text): void
    {
        $this->assertFileExists($imagePath);

        if ($text === '') {
            return;
        }

        $fontPath = $this->findFontPath();

        if ($fontPath === null) {
            Log::warning('ImageProcessingService: No font file found, watermark skipped. '
                . 'Set WATERMARK_FONT_PATH in .env to enable watermarks.');
            return;
        }

        try {
            $image = $this->manager->read($imagePath);

            $x = $image->width()  - 16;
            $y = $image->height() - 16;

            // Shadow layer — sedikit offset ke kanan-bawah agar teks terbaca di background terang
            $image->text($text, $x + 1, $y + 1, function (FontFactory $font) use ($fontPath) {
                $font->filename($fontPath);
                $font->size(14);
                $font->color('rgba(0, 0, 0, 0.65)');
                $font->align('right');
                $font->valign('bottom');
            });

            // Teks utama putih
            $image->text($text, $x, $y, function (FontFactory $font) use ($fontPath) {
                $font->filename($fontPath);
                $font->size(14);
                $font->color('rgba(255, 255, 255, 0.90)');
                $font->align('right');
                $font->valign('bottom');
            });

            $image->toJpeg(quality: config('booth.output_quality', 85))->save($imagePath);

        } catch (Throwable $e) {
            throw new ImageProcessingException(
                "addWatermark failed on [{$imagePath}]: {$e->getMessage()}", 0, $e
            );
        }
    }

    // =========================================================================
    // 4. generateThumbnail — buat versi kecil, return path file thumbnail
    // =========================================================================

    /**
     * Buat thumbnail dari sebuah file gambar.
     *
     * Thumbnail disimpan ke temp directory. Pemanggil bertanggung jawab
     * menghapus file setelah di-upload ke storage.
     *
     * cover() dipakai agar thumbnail selalu persis $width × $height px
     * (crop proporsional dari tengah jika aspect ratio berbeda).
     *
     * @param  string $imagePath Path absolut ke sumber gambar
     * @param  int    $width     Lebar thumbnail (px)
     * @param  int    $height    Tinggi thumbnail (px)
     * @return string            Path absolut ke file thumbnail yang dibuat
     * @throws ImageProcessingException
     */
    public function generateThumbnail(string $imagePath, int $width, int $height): string
    {
        $this->assertFileExists($imagePath);

        $thumbPath = $this->makeTempPath('booth_thumb_');

        try {
            $image = $this->manager->read($imagePath);

            // cover() = scale + center crop ke dimensi persis
            $image->cover($width, $height);

            $image->toJpeg(quality: 70)->save($thumbPath);

        } catch (Throwable $e) {
            @unlink($thumbPath);
            throw new ImageProcessingException(
                "generateThumbnail failed: {$e->getMessage()}", 0, $e
            );
        }

        return $thumbPath;
    }

    // =========================================================================
    // 5. processPhoto — pipeline utama: copy → filter → frame → watermark → thumbnail
    // =========================================================================

    /**
     * Proses sebuah foto melalui pipeline lengkap.
     *
     * File ASLI tidak pernah dimodifikasi — semua operasi dilakukan pada
     * working copy yang dibuat di temp directory.
     *
     * Pipeline: copy → scale down → filter → frame overlay → watermark → thumbnail
     *
     * Pemanggil wajib membersihkan temp file setelah di-upload ke storage:
     *   @unlink($result['processed_path']);
     *   @unlink($result['thumbnail_path']);
     *
     * @param  string $imagePath Path absolut ke file ASLI (tidak akan diubah)
     * @param  array  $options   {
     *   filter?:          string        // 'normal'|'grayscale'|'sepia'|'vivid'
     *   template_config?: array         // untuk applyFrame()
     *   watermark?:       string        // teks watermark; null/'' = skip
     *   thumb_width?:     int           // default dari config booth.thumb_width
     *   thumb_height?:    int           // default dari config booth.thumb_height
     *   max_width?:       int           // default dari config booth.max_width
     *   quality?:         int           // JPEG quality 1-100, default booth.output_quality
     * }
     * @return array{
     *   processed_path: string,   // temp path ke foto hasil proses (JPEG)
     *   thumbnail_path: string,   // temp path ke thumbnail (JPEG)
     *   original_width: int,
     *   original_height: int,
     * }
     * @throws ImageProcessingException jika file tidak bisa dibaca atau pipeline gagal
     */
    public function processPhoto(string $imagePath, array $options = []): array
    {
        $this->assertFileExists($imagePath);

        $filter         = $options['filter']          ?? null;
        $templateConfig = $options['template_config'] ?? null;
        $watermark      = $options['watermark']       ?? null;
        $thumbWidth     = (int) ($options['thumb_width']  ?? config('booth.thumb_width',  400));
        $thumbHeight    = (int) ($options['thumb_height'] ?? config('booth.thumb_height', 300));
        $maxWidth       = (int) ($options['max_width']    ?? config('booth.max_width',   2400));
        $quality        = (int) ($options['quality']      ?? config('booth.output_quality', 85));

        // Baca dimensi asli sebelum diproses
        $originalDims = $this->readDimensions($imagePath);

        // Buat working copy — file asli terlindungi mulai dari sini
        $processedPath = $this->createWorkingCopy($imagePath, $maxWidth, $quality);

        $thumbnailPath = null;

        try {
            // Step 1: Filter warna
            if ($filter && $filter !== 'normal') {
                $this->applyFilter($processedPath, $filter);
            }

            // Step 2: Frame template (opsional — error dilewati, bukan fatal)
            if ($templateConfig) {
                try {
                    $this->applyFrame($processedPath, $templateConfig);
                } catch (ImageProcessingException $e) {
                    Log::warning('Frame overlay skipped due to error: ' . $e->getMessage());
                }
            }

            // Step 3: Watermark (opsional — error dilewati, bukan fatal)
            if ($watermark) {
                try {
                    $this->addWatermark($processedPath, $watermark);
                } catch (ImageProcessingException $e) {
                    Log::warning('Watermark skipped due to error: ' . $e->getMessage());
                }
            }

            // Step 4: Thumbnail dibuat dari working copy yang sudah diproses
            $thumbnailPath = $this->generateThumbnail($processedPath, $thumbWidth, $thumbHeight);

        } catch (Throwable $e) {
            @unlink($processedPath);
            if ($thumbnailPath) {
                @unlink($thumbnailPath);
            }

            throw new ImageProcessingException(
                "processPhoto pipeline failed: {$e->getMessage()}", 0, $e
            );
        }

        return [
            'processed_path'  => $processedPath,
            'thumbnail_path'  => $thumbnailPath,
            'original_width'  => $originalDims['width'],
            'original_height' => $originalDims['height'],
        ];
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    /**
     * Buat working copy dari file asli: baca → scale down jika perlu → simpan ke temp.
     * Input apapun (PNG, WebP, dll.) dikonversi ke JPEG standar.
     */
    private function createWorkingCopy(string $sourcePath, int $maxWidth, int $quality): string
    {
        $destPath = $this->makeTempPath('booth_proc_');

        try {
            $image = $this->manager->read($sourcePath);

            if ($image->width() > $maxWidth) {
                $image->scaleDown(width: $maxWidth);
            }

            $image->toJpeg(quality: $quality)->save($destPath);

        } catch (Throwable $e) {
            @unlink($destPath);
            throw new ImageProcessingException(
                "Cannot create working copy from [{$sourcePath}]: {$e->getMessage()}", 0, $e
            );
        }

        return $destPath;
    }

    /**
     * Fetch overlay dari URL (HTTP) atau local path.
     * Return raw binary string atau null jika gagal.
     */
    private function fetchOverlay(string $source): ?string
    {
        if (filter_var($source, FILTER_VALIDATE_URL)) {
            try {
                $response = Http::timeout(5)->get($source);

                return $response->successful() ? $response->body() : null;

            } catch (Throwable $e) {
                Log::warning("Failed to fetch overlay from URL [{$source}]: {$e->getMessage()}");
                return null;
            }
        }

        if (file_exists($source)) {
            return file_get_contents($source) ?: null;
        }

        return null;
    }

    /**
     * Cari font TTF yang tersedia untuk watermark.
     * Prioritas: config > common Linux paths > macOS > Windows.
     */
    private function findFontPath(): ?string
    {
        $configured = config('booth.watermark_font');
        if ($configured && file_exists($configured)) {
            return $configured;
        }

        $candidates = [
            // Linux (Debian/Ubuntu)
            '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
            '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf',
            '/usr/share/fonts/truetype/freefont/FreeSans.ttf',
            // Linux (Arch/Fedora)
            '/usr/share/fonts/TTF/DejaVuSans.ttf',
            '/usr/share/fonts/liberation-sans/LiberationSans-Regular.ttf',
            // macOS
            '/System/Library/Fonts/Supplemental/Arial.ttf',
            '/Library/Fonts/Arial.ttf',
            // Windows
            'C:/Windows/Fonts/arial.ttf',
            'C:/Windows/Fonts/calibri.ttf',
            'C:/Windows/Fonts/segoeui.ttf',
        ];

        foreach ($candidates as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        return null;
    }

    /** Buat path unik di temp directory. */
    private function makeTempPath(string $prefix = 'booth_', string $ext = 'jpg'): string
    {
        return $this->tempDir . DIRECTORY_SEPARATOR . $prefix . Str::uuid() . '.' . $ext;
    }

    /** Baca dimensi gambar tanpa memproses seluruh pipeline. */
    private function readDimensions(string $imagePath): array
    {
        try {
            $image = $this->manager->read($imagePath);
            return ['width' => $image->width(), 'height' => $image->height()];
        } catch (Throwable $e) {
            throw new ImageProcessingException(
                "Cannot read dimensions from [{$imagePath}]: {$e->getMessage()}", 0, $e
            );
        }
    }

    /** Guard: pastikan file ada sebelum diproses. */
    private function assertFileExists(string $path): void
    {
        if (! file_exists($path)) {
            throw new ImageProcessingException("Image file not found: [{$path}]");
        }
    }
}
