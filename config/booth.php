<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Watermark Font
    |--------------------------------------------------------------------------
    | Path absolut ke file TTF yang dipakai untuk watermark teks.
    | Jika null, service mencari font sistem secara otomatis.
    | Jika tidak ditemukan sama sekali, watermark dilewati (non-fatal).
    |
    | Contoh: WATERMARK_FONT_PATH=/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf
    */
    'watermark_font' => env('WATERMARK_FONT_PATH'),

    /*
    |--------------------------------------------------------------------------
    | Watermark Text
    |--------------------------------------------------------------------------
    | Teks yang ditampilkan sebagai watermark di pojok kanan bawah foto.
    | Set kosong ('') untuk menonaktifkan watermark dari env tanpa ubah kode.
    */
    'watermark_text' => env('WATERMARK_TEXT', 'SnapBooth'),

    /*
    |--------------------------------------------------------------------------
    | Output Quality
    |--------------------------------------------------------------------------
    | JPEG quality untuk foto yang sudah diproses (1–100).
    */
    'output_quality' => (int) env('BOOTH_OUTPUT_QUALITY', 85),

    /*
    |--------------------------------------------------------------------------
    | Thumbnail Size
    |--------------------------------------------------------------------------
    | Dimensi default thumbnail. Bisa di-override per-request lewat options.
    */
    'thumb_width'  => (int) env('BOOTH_THUMB_WIDTH', 400),
    'thumb_height' => (int) env('BOOTH_THUMB_HEIGHT', 300),

    /*
    |--------------------------------------------------------------------------
    | Max Output Width
    |--------------------------------------------------------------------------
    | Foto yang lebih lebar dari ini akan di-scale down untuk menghemat storage.
    */
    'max_width' => (int) env('BOOTH_MAX_WIDTH', 2400),

    /*
    |--------------------------------------------------------------------------
    | Share / Download Page URL
    |--------------------------------------------------------------------------
    | URL halaman frontend tempat tamu bisa lihat dan download foto mereka.
    | QR code akan mengarah ke: {share_url}/{share_token}
    |
    | Contoh production: SHARE_URL=https://app.snapbooth.id/share
    | Dev (frontend di port 3000): SHARE_URL=http://localhost:3000/share
    */
    'share_url' => env('SHARE_URL', env('APP_URL', 'http://localhost:8000') . '/share'),
];
