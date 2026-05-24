<?php
$img = imagecreatetruecolor(300, 400);
$bg  = imagecolorallocate($img, 255, 165, 0);
$txt = imagecolorallocate($img, 255, 255, 255);
imagefill($img, 0, 0, $bg);
imagestring($img, 5, 70, 185, 'SnapBooth Test', $txt);
imagepng($img, __DIR__ . '/test.png');
imagedestroy($img);
echo 'test.png generated: ' . filesize(__DIR__ . '/test.png') . " bytes\n";
