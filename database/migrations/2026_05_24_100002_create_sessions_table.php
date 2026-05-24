<?php

// Keputusan desain:
// 1. Nama tabel 'sessions' aman dipakai karena Laravel web-sessions sudah dihapus dari
//    users migration — project ini pure API token-based (Sanctum), tidak butuh session store
//    Jika suatu saat butuh web sessions, ubah SESSION_DRIVER=file di .env
// 2. guest_email dan guest_phone keduanya NULLABLE — banyak event photo booth tidak
//    mensyaratkan registrasi; tamu hanya scan QR dan langsung pakai booth
// 3. session_token VARCHAR(64) UNIQUE — token acak untuk URL share atau akses ulang
//    tanpa login; lebih aman dari ID incremental yang bisa ditebak
// 4. started_at TIMESTAMP useCurrent() — dicatat saat guest pertama kali buka booth.
//    Wajib pakai useCurrent() agar MySQL tidak menambahkan ON UPDATE CURRENT_TIMESTAMP
//    secara implisit (perilaku default MySQL untuk kolom TIMESTAMP pertama yang NOT NULL).
//    Tanpa ini, setiap UPDATE row (misal: set ended_at) akan overwrite started_at ke NOW().
// 5. ended_at TIMESTAMP NULLABLE — null = sesi masih aktif; filled = sesi selesai
//    Durasi sesi = ended_at - started_at (berguna untuk analitik)
// 6. cascadeOnDelete pada event_id — sesi tidak punya makna tanpa eventnya
// 7. Index [event_id, started_at] — query timeline: "semua sesi event X urut waktu"
// 8. Index guest_email — untuk lookup "semua sesi tamu dengan email ini" (re-engagement)
// 9. Tidak ada softDeletes — sesi adalah rekaman faktual, tidak perlu recovery;
//    hapus sesi = hapus semua foto terkait (lewat cascade di photos)

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')
                  ->constrained()
                  ->cascadeOnDelete();
            $table->string('guest_name')->nullable();
            $table->string('guest_email')->nullable();
            $table->string('guest_phone', 20)->nullable();
            $table->string('session_token', 64)->unique();
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('ended_at')->nullable();

            // Query: daftar sesi di suatu event, diurutkan kronologis
            $table->index(['event_id', 'started_at']);
            // Query: cari histori sesi berdasarkan email tamu (re-marketing, support)
            $table->index('guest_email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
    }
};
