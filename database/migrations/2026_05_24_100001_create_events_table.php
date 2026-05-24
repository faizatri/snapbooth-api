<?php

// Keputusan desain:
// 1. date pakai DATE bukan DATETIME — event photo booth biasanya seharian penuh;
//    waktu spesifik booth aktif disimpan di booth_config agar lebih fleksibel
// 2. slug UNIQUE — identifier URL yang human-readable; auto-generate dari name di model
// 3. booth_config JSON — konfigurasi seperti countdown_seconds, max_photos_per_session,
//    overlay_enabled, filter_list, dll. Hindari banyak kolom boolean/integer yang jarang terpakai
// 4. is_active BOOLEAN bukan status ENUM — event hanya punya dua state operasional
//    (aktif = booth bisa dipakai, nonaktif = booth ditutup); state lifecycle (draft/published)
//    cukup ditangani di aplikasi layer atau kolom is_active ini
// 5. softDeletes — event yang dihapus tetap jadi parent sah untuk photos/sessions historis;
//    hard delete akan memutus FK constraint
// 6. Composite index [user_id, is_active] — query paling sering: "event aktif milik user X"
// 7. Index [date] — untuk filter kalender atau event mendatang
// 8. cascadeOnDelete di user_id — jika user dihapus permanen (force delete),
//    eventnya ikut dihapus (tidak ada orphan records)

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                  ->constrained()
                  ->cascadeOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->date('date');
            $table->string('location')->nullable();
            $table->json('booth_config')->nullable();
            $table->boolean('is_active')->default(false);
            $table->timestamps();
            $table->softDeletes();

            // Query: dashboard user — event aktif/nonaktif milik saya
            $table->index(['user_id', 'is_active']);
            // Query: filter event berdasarkan tanggal (kalender, laporan)
            $table->index('date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
