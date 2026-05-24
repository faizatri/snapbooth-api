<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('photos', function (Blueprint $table) {
            // shot_number: urutan pengambilan foto dalam sesi (1, 2, 3, ...).
            // Berguna untuk menampilkan foto dalam urutan kronologis dengan benar
            // bahkan jika upload tidak selesai secara berurutan.
            $table->tinyInteger('shot_number')->unsigned()->nullable()->after('template_id');

            // thumbnail_path: path ke versi kecil foto (400px) untuk preview.
            // Disimpan terpisah dari file_path agar UI bisa load thumbnail dulu
            // sebelum download full-res.
            $table->string('thumbnail_path')->nullable()->after('file_url');

            // is_final: menandai foto yang dipilih tamu sebagai "foto final" saat complete-session.
            // Foto yang tidak dipilih tetap ada di DB (untuk backup) tapi tidak ditampilkan
            // di halaman share publik.
            $table->boolean('is_final')->default(false)->after('is_shared');
        });
    }

    public function down(): void
    {
        Schema::table('photos', function (Blueprint $table) {
            $table->dropColumn(['shot_number', 'thumbnail_path', 'is_final']);
        });
    }
};
