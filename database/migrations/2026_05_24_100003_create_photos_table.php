<?php

// Keputusan desain:
// 1. Dual FK: session_id DAN event_id (denormalisasi disengaja) —
//    event_id bisa didapat dari session_id lewat JOIN, tapi:
//    - Query "semua foto di event X" tanpa JOIN = O(1) index lookup
//    - Bulk analytics per event tidak perlu join sessions setiap kali
//    - Trade-off: satu kolom redundan vs. performa query yang signifikan lebih cepat
// 2. session_id NULLABLE — memungkinkan foto diunggah langsung ke event
//    tanpa melalui sesi (misal: foto preview operator atau foto sample)
// 3. file_path VARCHAR — path relatif di storage/R2 (pendek, untuk internal reference)
// 4. file_url TEXT — public URL CDN bisa sangat panjang (signed URL R2 ~500+ char)
// 5. template_id NULLABLE + nullOnDelete — jika template dihapus, foto tetap ada
//    tapi tanpa referensi template (tampilkan foto polos tanpa overlay)
// 6. is_shared BOOLEAN dengan index — flag denormalisasi; lebih cepat dari
//    COUNT(shares) untuk keperluan filter/badge "foto sudah dibagikan"
// 7. metadata JSON — simpan: dimensi, ukuran file, EXIF, status processing
//    (compressed/watermarked), tanpa perlu ALTER TABLE saat format berubah
// 8. Tidak ada softDeletes — penghapusan foto harus benar-benar hapus file di R2;
//    soft delete akan menimbulkan ambiguitas "file ada tapi record 'dihapus'"
// 9. Composite index [event_id, is_shared] — query analitik paling umum:
//    "berapa/mana foto yang sudah dibagikan di event X"
// 10. Index [session_id] — untuk load foto per sesi (halaman hasil foto tamu)

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')
                  ->nullable()
                  ->constrained()
                  ->nullOnDelete();
            $table->foreignId('event_id')
                  ->constrained()
                  ->cascadeOnDelete();
            $table->foreignId('template_id')
                  ->nullable()
                  ->constrained()
                  ->nullOnDelete();
            $table->string('file_path');
            $table->text('file_url');
            $table->boolean('is_shared')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamps();

            // Analitik: "foto yang sudah/belum dibagikan di event ini"
            $table->index(['event_id', 'is_shared']);
            // Load foto per sesi tamu
            $table->index('session_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('photos');
    }
};
