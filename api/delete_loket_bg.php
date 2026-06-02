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

// Delete ID-based file
$targetFile = __DIR__ . '/../assets/img/backgrounds/loket_uid_' . $uid . '.jpg';
if (is_file($targetFile)) {
    unlink($targetFile);
}

// Also delete legacy index-based file if it exists
$legacyFile = __DIR__ . '/../assets/img/backgrounds/loket_' . $loket . '.jpg';
if (is_file($legacyFile)) {
    unlink($legacyFile);
}

header("Location: /loket?loket={$loket}");
exit;
