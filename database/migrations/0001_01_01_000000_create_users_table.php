<?php

// Keputusan desain:
// 1. subscription_plan pakai ENUM bukan string — set terbatas, MySQL index lebih efisien
// 2. subscription_expires_at nullable — null = free tier atau lifetime, tidak perlu sentinel value
// 3. softDeletes — user yang "dihapus" tetap bisa dijadikan owner historis event/photo
//    (relasi tidak putus), data bisa dipulihkan oleh admin
// 4. Hapus Laravel web-sessions dari sini — project ini pure API token-based (Sanctum),
//    nama 'sessions' dipakai oleh tabel photo booth di migration terpisah
// 5. Index pada subscription_expires_at — untuk cron job harian yang cek subscription kadaluarsa

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->enum('subscription_plan', ['free', 'basic', 'pro', 'enterprise'])->default('free');
            $table->timestamp('subscription_expires_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();

            $table->index('subscription_expires_at');
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};
