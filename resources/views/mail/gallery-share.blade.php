<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Foto Booth Kamu Sudah Siap!</title>
<style>
  /* Reset & base */
  * { box-sizing: border-box; }
  body { margin: 0; padding: 0; background: #f5f5f5; -webkit-font-smoothing: antialiased; }
  img { display: block; border: 0; max-width: 100%; height: auto; }
  a { text-decoration: none; }

  /* Layout */
  .email-wrapper { width: 100%; background: #f5f5f5; padding: 32px 16px; }
  .email-card    { max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 24px rgba(0,0,0,.08); }

  /* Header */
  .header { background: linear-gradient(135deg, #0f0f0f 0%, #1c1c1c 100%); padding: 40px 32px; text-align: center; }
  .header-logo { font-size: 13px; font-weight: 700; letter-spacing: 4px; text-transform: uppercase; color: #737373; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; margin: 0 0 12px; }
  .header-title { font-size: 28px; font-weight: 800; color: #ffffff; margin: 0 0 8px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; letter-spacing: -0.5px; }
  .header-event { font-size: 14px; color: #a3a3a3; margin: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; }

  /* Body */
  .body { padding: 40px 32px 32px; }
  .greeting { font-size: 18px; font-weight: 600; color: #0a0a0a; margin: 0 0 12px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; }
  .copy { font-size: 15px; color: #525252; line-height: 1.65; margin: 0 0 32px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; }

  /* Photo grid */
  .photos-label { font-size: 11px; font-weight: 700; letter-spacing: 2px; text-transform: uppercase; color: #a3a3a3; margin: 0 0 16px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; }
  .photo-grid { display: flex; flex-wrap: wrap; gap: 8px; margin: 0 0 32px; }
  .photo-cell { flex: 1 1 calc(33.333% - 6px); min-width: 140px; max-width: calc(33.333% - 6px); }
  .photo-cell img { width: 100%; height: 120px; object-fit: cover; border-radius: 8px; background: #f5f5f5; }

  /* Single photo fallback */
  .photo-single { margin: 0 0 32px; border-radius: 12px; overflow: hidden; }
  .photo-single img { width: 100%; max-height: 320px; object-fit: cover; }

  /* CTA button */
  .btn-wrap { text-align: center; margin: 0 0 32px; }
  .btn {
    display: inline-block;
    background: #0a0a0a;
    color: #ffffff !important;
    font-size: 15px;
    font-weight: 700;
    padding: 16px 40px;
    border-radius: 10px;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    letter-spacing: -0.2px;
  }
  .btn:hover { background: #262626; }

  /* Fallback text */
  .fallback { font-size: 13px; color: #a3a3a3; text-align: center; margin: 0 0 8px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; }
  .fallback a { color: #0a0a0a; text-decoration: underline; word-break: break-all; }

  /* Divider */
  .divider { border: none; border-top: 1px solid #f0f0f0; margin: 32px 0 0; }

  /* Footer */
  .footer { padding: 24px 32px 32px; text-align: center; }
  .footer p { font-size: 12px; color: #a3a3a3; margin: 0 0 4px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; line-height: 1.6; }
  .footer-brand { font-weight: 700; color: #737373; }

  /* Mobile */
  @media (max-width: 480px) {
    .body { padding: 32px 20px 24px; }
    .header { padding: 32px 20px; }
    .header-title { font-size: 22px; }
    .photo-cell { flex: 1 1 calc(50% - 4px); max-width: calc(50% - 4px); }
    .btn { padding: 14px 28px; font-size: 14px; }
  }
</style>
</head>
<body>
<div class="email-wrapper">
  <div class="email-card">

    {{-- Header --}}
    <div class="header">
      <p class="header-logo">SnapBooth</p>
      <h1 class="header-title">Foto kamu siap! 🎉</h1>
      <p class="header-event">{{ $session->event->name ?? 'Photo Booth' }}</p>
    </div>

    {{-- Body --}}
    <div class="body">
      <p class="greeting">
        Hai{{ $session->guest_name ? ', ' . $session->guest_name : '' }}!
      </p>
      <p class="copy">
        Foto booth kamu dari
        <strong>{{ $session->event->name ?? 'acara' }}</strong>
        sudah siap untuk didownload.
        Klik tombol di bawah untuk melihat dan menyimpan semua fotomu.
      </p>

      {{-- Photo grid --}}
      @if($session->photos->isNotEmpty())
        <p class="photos-label">Foto kamu ({{ $session->photos->count() }})</p>

        @if($session->photos->count() === 1)
          {{-- Single photo — tampilkan besar --}}
          <div class="photo-single">
            <img src="{{ $session->photos->first()->file_url }}"
                 alt="Foto booth"
                 width="536">
          </div>
        @else
          {{-- Multiple photos — grid --}}
          <div class="photo-grid">
            @foreach($session->photos->take(6) as $photo)
              <div class="photo-cell">
                <img src="{{ $photo->thumbnail_url ?? $photo->file_url }}"
                     alt="Foto {{ $loop->iteration }}"
                     width="176"
                     height="120">
              </div>
            @endforeach
          </div>
          @if($session->photos->count() > 6)
            <p class="fallback" style="margin-top: -20px; margin-bottom: 28px;">
              + {{ $session->photos->count() - 6 }} foto lainnya tersedia di galeri
            </p>
          @endif
        @endif
      @endif

      {{-- CTA --}}
      <div class="btn-wrap">
        <a href="{{ $shareUrl }}" class="btn">⬇&nbsp; Download Semua Foto</a>
      </div>

      {{-- Fallback link --}}
      <p class="fallback">
        Tombol tidak berfungsi?
        <a href="{{ $shareUrl }}">{{ $shareUrl }}</a>
      </p>
    </div>

    <hr class="divider">

    {{-- Footer --}}
    <div class="footer">
      <p>Link ini tidak akan kadaluarsa — simpan sesuai kebutuhan.</p>
      <p>
        <span class="footer-brand">SnapBooth</span> &mdash;
        Powered by {{ config('app.name') }}
      </p>
    </div>

  </div>{{-- /.email-card --}}
</div>{{-- /.email-wrapper --}}
</body>
</html>
