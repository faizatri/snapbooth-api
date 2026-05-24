<?php

// Keputusan desain:
// 1. user_id NULLABLE — null berarti template bawaan sistem (built-in), bukan milik user mana pun
//    Hindari membuat user "system" dummy hanya untuk keperluan ini
// 2. is_public BOOLEAN — template bisa dibagikan lintas user; false = private milik creator saja
// 3. config JSON — simpan layer, posisi, efek, font, warna dalam satu kolom; schema-less
//    memungkinkan penambahan fitur template tanpa ALTER TABLE
// 4. preview_url TEXT bukan VARCHAR(255) — URL CDN + query string bisa melebihi 255 karakter
//    (signed URL Cloudflare R2 cukup panjang)
// 5. Index [user_id, is_public] — query paling umum: "tampilkan template milik user ini + semua yang public"
//    Composite index lebih efisien dari dua index terpisah untuk query WHERE user_id=? OR is_public=1

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                  ->nullable()
                  ->constrained()
                  ->nullOnDelete();
            $table->string('name');
            $table->text('preview_url')->nullable();
            $table->json('config');
            $table->boolean('is_public')->default(false);
            $table->timestamps();

            // Query: "template available untuk user X" = milik sendiri ATAU public
            $table->index(['user_id', 'is_public']);
            // Query standalone: tampilkan galeri template publik
            $table->index('is_public');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('templates');
    }
};
