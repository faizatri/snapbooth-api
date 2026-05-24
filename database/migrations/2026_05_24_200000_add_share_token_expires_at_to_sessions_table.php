<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sessions', function (Blueprint $table) {
            // share_token: token publik yang dibagikan ke tamu setelah sesi selesai.
            // Berbeda dari session_token (untuk operasi booth) — share_token hanya untuk
            // halaman lihat foto, tidak punya privilege apapun ke data session.
            $table->string('share_token', 64)->unique()->nullable()->after('session_token');

            // expires_at: batas waktu session_token masih valid untuk upload foto.
            // Setelah lewat, tamu harus mulai sesi baru. Default 2 jam dari started_at.
            $table->timestamp('expires_at')->nullable()->after('started_at');
        });
    }

    public function down(): void
    {
        Schema::table('sessions', function (Blueprint $table) {
            $table->dropUnique(['share_token']);
            $table->dropColumn(['share_token', 'expires_at']);
        });
    }
};
