<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Your SnapBooth Photos</title>
<style>
  body { margin: 0; padding: 0; background: #f4f4f5; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; }
  .wrapper { max-width: 600px; margin: 32px auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,.08); }
  .header { background: #18181b; padding: 32px; text-align: center; }
  .header h1 { margin: 0; color: #ffffff; font-size: 24px; letter-spacing: -.5px; }
  .header p { margin: 8px 0 0; color: #a1a1aa; font-size: 14px; }
  .body { padding: 32px; }
  .body p { color: #3f3f46; font-size: 15px; line-height: 1.6; margin: 0 0 16px; }
  .photo-wrap { text-align: center; margin: 24px 0; }
  .photo-wrap img { max-width: 100%; border-radius: 8px; box-shadow: 0 4px 16px rgba(0,0,0,.12); }
  .btn-wrap { text-align: center; margin: 32px 0 24px; }
  .btn { display: inline-block; background: #18181b; color: #ffffff; text-decoration: none; padding: 14px 32px; border-radius: 8px; font-size: 15px; font-weight: 600; }
  .footer { border-top: 1px solid #f4f4f5; padding: 24px 32px; text-align: center; }
  .footer p { margin: 0; color: #a1a1aa; font-size: 12px; }
</style>
</head>
<body>
<div class="wrapper">
  <div class="header">
    <h1>📸 SnapBooth</h1>
    <p>{{ $session->event->name ?? 'Your Event' }}</p>
  </div>
  <div class="body">
    <p>Hi{{ $session->guest_name ? ', ' . $session->guest_name : '' }}!</p>
    <p>Your photo from the booth is ready. Download it below or visit your personal gallery to see all your shots.</p>

    <div class="photo-wrap">
      <img src="{{ $photo->file_url }}" alt="Your SnapBooth photo">
    </div>

    <div class="btn-wrap">
      <a href="{{ $galleryUrl }}" class="btn">View Your Gallery</a>
    </div>

    <p style="font-size:13px;color:#71717a;">
      Can't see the photo above? <a href="{{ $photo->file_url }}" style="color:#18181b;">Click here to download</a>.
    </p>
  </div>
  <div class="footer">
    <p>Powered by SnapBooth &mdash; {{ config('app.name') }}</p>
  </div>
</div>
</body>
</html>
