<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

antrian_session_bootstrap();

$errors = [];
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $errors[] = 'Username dan password wajib diisi.';
    } else {
        $user = antrian_find_user_by_username($username);

        if (!$user || !password_verify($password, $user['password_hash'])) {
            $errors[] = 'Username atau password salah.';
        } else {
            antrian_login_user($user);

            if ($user['role'] === 'admin') {
                header('Location: /index.php?page=admin');
                exit;
            }

            header('Location: /index.php?page=loket&loket=1');
            exit;
        }
    }
}
?><!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Antrian SPMB 2026 | Login</title>
    <script src="https://cdn.tailwindcss.com"></script>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body class="app-shell auth-shell">
    <main class="auth-page">
        <section class="auth-card">
            <p class="eyebrow">Masuk Sistem</p>
            <h1>Antrian SPMB 2026</h1>
            <p class="lead">By SMK N 4 Surakarta</p>
            <p class="lead">Gunakan akun admin untuk panel kontrol atau akun loket untuk akses loket.</p>

            <?php if ($errors): ?>
                <div class="auth-alert auth-alert-error">
                    <?php foreach ($errors as $error): ?>
                        <p><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form method="post" class="auth-form">
                <label>
                    Username
                    <input type="text" name="username" value="<?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8') ?>" required>
                </label>

                <label>
                    Password
                    <input type="password" name="password" required>
                </label>

                <button type="submit" class="button button-primary">Login</button>
            </form>

            <p class="auth-links">Belum punya akun? <a href="/index.php?page=register">Daftar di sini</a></p>
            <p class="auth-links"><a href="/index.php">Kembali ke menu</a></p>
        </section>
    </main>
</body>
</html>
