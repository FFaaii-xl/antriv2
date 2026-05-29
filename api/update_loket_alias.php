<?php

declare(strict_types=1);

require __DIR__ . '/../config/database.php';
require __DIR__ . '/../auth/helpers.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Metode tidak diperbolehkan.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$loket = filter_var($input['loket'] ?? $_POST['loket'] ?? null, FILTER_VALIDATE_INT);
$alias = trim((string) ($input['alias'] ?? $_POST['alias'] ?? ''));

if (!$loket || $loket <= 0) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Nomor loket tidak valid.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($alias === '') {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Nama alias tidak boleh kosong.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $loketAccounts = antrian_loket_accounts();
    if (!isset($loketAccounts[$loket - 1])) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Akun loket tidak ditemukan.',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $loketUser = $loketAccounts[$loket - 1];
    $userId = (int) $loketUser['id'];

    antrian_update_user_profile($userId, $loketUser['username'], $alias);

    echo json_encode([
        'success' => true,
        'message' => 'Alias loket berhasil diperbarui.',
        'data' => [
            'loket' => $loket,
            'alias' => $alias,
        ],
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $throwable) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $throwable->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
