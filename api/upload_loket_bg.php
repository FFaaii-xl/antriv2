<?php

declare(strict_types=1);

require __DIR__ . '/../config/database.php';
require __DIR__ . '/../auth/helpers.php';

$loket = filter_input(INPUT_POST, 'loket', FILTER_VALIDATE_INT);

if (!$loket || $loket <= 0) {
    http_response_code(400);
    echo "Nomor loket tidak valid.";
    exit;
}

antrian_require_csrf();

// Resolve loket number to user ID
$loketAccount = antrian_loket_user_by_number($loket);
if (!$loketAccount) {
    http_response_code(404);
    echo "Akun loket tidak ditemukan.";
    exit;
}
$uid = (int) $loketAccount['id'];

if (!isset($_FILES['background']) || $_FILES['background']['error'] !== UPLOAD_ERR_OK) {
    header("Location: /loket?loket={$loket}");
    exit;
}

$file = $_FILES['background'];
$allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp', 'image/gif'];

if (!in_array(strtolower($file['type']), $allowedTypes, true) && !in_array(strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
    http_response_code(400);
    echo "Format file harus berupa gambar (JPG, PNG, WEBP, GIF).";
    exit;
}

$targetDir = __DIR__ . '/../assets/img/backgrounds';
if (!is_dir($targetDir)) {
    mkdir($targetDir, 0777, true);
}

$destination = $targetDir . '/loket_uid_' . $uid . '.jpg';

try {
    $info = getimagesize($file['tmp_name']);
    if ($info === false) {
        throw new Exception('File bukan gambar yang valid.');
    }

    $mime = $info['mime'];
    $srcImage = null;

    if ($mime === 'image/jpeg' || $mime === 'image/jpg') {
        $srcImage = imagecreatefromjpeg($file['tmp_name']);
    } elseif ($mime === 'image/png') {
        $srcImage = imagecreatefrompng($file['tmp_name']);
    } elseif ($mime === 'image/webp') {
        $srcImage = imagecreatefromwebp($file['tmp_name']);
    } elseif ($mime === 'image/gif') {
        $srcImage = imagecreatefromgif($file['tmp_name']);
    }

    if ($srcImage) {
        $origWidth = imagesx($srcImage);
        $origHeight = imagesy($srcImage);

        // Auto compress / resize: limit max width to 800px to optimize size
        $maxWidth = 800;
        if ($origWidth > $maxWidth) {
            $newWidth = $maxWidth;
            $newHeight = (int) (($origHeight / $origWidth) * $maxWidth);

            $dstImage = imagecreatetruecolor($newWidth, $newHeight);
            
            // Preserve transparency (convert PNG/GIF/WEBP alpha to white background for premium light mode)
            $white = imagecolorallocate($dstImage, 255, 255, 255);
            imagefill($dstImage, 0, 0, $white);

            imagecopyresampled($dstImage, $srcImage, 0, 0, 0, 0, $newWidth, $newHeight, $origWidth, $origHeight);
            imagejpeg($dstImage, $destination, 80); // Save as JPG with 80% quality (auto-compress!)
            imagedestroy($dstImage);
        } else {
            // No resize, just convert and compress
            $dstImage = imagecreatetruecolor($origWidth, $origHeight);
            $white = imagecolorallocate($dstImage, 255, 255, 255);
            imagefill($dstImage, 0, 0, $white);
            imagecopy($dstImage, $srcImage, 0, 0, 0, 0, $origWidth, $origHeight);
            imagejpeg($dstImage, $destination, 80);
            imagedestroy($dstImage);
        }
        imagedestroy($srcImage);
    } else {
        move_uploaded_file($file['tmp_name'], $destination);
    }
} catch (Throwable $e) {
    move_uploaded_file($file['tmp_name'], $destination);
}

header("Location: /loket?loket={$loket}");
exit;
