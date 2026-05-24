<?php

// Keputusan desain:
// 1. channel ENUM bukan string — saluran berbagi adalah set terbatas yang diketahui;
//    ENUM lebih efisien storage (1 byte) dan mencegah nilai arbitrary masuk DB
// 2. recipient NULLABLE — beberapa channel tidak punya penerima spesifik:
//    - 'link': hanya generate URL publik, tidak kirim ke siapa pun
//    - 'qr':   dicetak/ditampilkan, tidak ada alamat penerima
//    - 'email'/'whatsapp': wajib ada recipient
// 3. sent_at TIMESTAMP — waktu pengiriman/pembuatan share (bukan created_at);
//    lebih eksplisit untuk laporan "kapan foto ini dibagikan"
// 4. status ENUM ('pending','sent','failed') — share dikirim via queue (async);
//    pending = job di antrian, sent = berhasil, failed = gagal setelah retry
//    Tanpa status ini, tidak ada cara lacak apakah WhatsApp/email benar-benar terkirim
// 5. Tidak ada updated_at (hanya sent_at) — share adalah immutable event;
//    jika kirim ulang = buat record baru, bukan update yang lama
//    Ini mempertahankan audit trail lengkap semua percobaan pengiriman
// 6. cascadeOnDelete pada photo_id — shares tidak bermakna jika fotonya dihapus
// 7. Composite index [photo_id, channel] — query: "foto ini sudah dibagikan via apa saja?"
// 8. Composite index [channel, status] — analitik: "berapa WhatsApp gagal minggu ini?"
// 9. Index sent_at — laporan time-series: grafik sharing per hari/minggu

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('photo_id')
                  ->constrained()
                  ->cascadeOnDelete();
            $table->enum('channel', ['email', 'whatsapp', 'instagram', 'qr', 'link']);
            $table->string('recipient')->nullable();
            $table->timestamp('sent_at');
            $table->enum('status', ['pending', 'sent', 'failed'])->default('pending');

            // Query: "foto X sudah dibagikan ke channel apa saja?"
            $table->index(['photo_id', 'channel']);
            // Analitik: success rate per channel
            $table->index(['channel', 'status']);
            // Laporan time-series (grafik harian/mingguan)
            $table->index('sent_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shares');
    }
};
