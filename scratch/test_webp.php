<?php
require_once __DIR__ . '/../config/upload_helper.php';

// Test GD WebP conversion directly
$gdImg = imagecreatetruecolor(100, 100);
$red = imagecolorallocate($gdImg, 255, 0, 0);
imagefill($gdImg, 0, 0, $red);

$testWebpPath = __DIR__ . '/test_output.webp';
if (imagewebp($gdImg, $testWebpPath, 85)) {
    echo "SUCCESS: WebP image generated at {$testWebpPath}\n";
    echo "File size: " . filesize($testWebpPath) . " bytes\n";
    $info = getimagesize($testWebpPath);
    echo "MIME type: " . $info['mime'] . "\n";
    @unlink($testWebpPath);
} else {
    echo "FAILED to generate WebP\n";
}
imagedestroy($gdImg);
