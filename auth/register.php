<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

antrian_session_bootstrap();

$errors = [];
$username = '';
$role = 'loket';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $confirmPassword = (string) ($_POST['confirm_password'] ?? '');
    $role = (string) ($_POST['role'] ?? 'loket');
    $role = in_array($role, ['admin', 'loket'], true) ? $role : 'loket';

    if ($username === '') {
        $errors[] = 'Username wajib diisi.';
    }

    if (strlen($password) < 6) {
        $errors[] = 'Password minimal 6 karakter.';
    }

    if ($password !== $confirmPassword) {
        $errors[] = 'Konfirmasi password tidak cocok.';
    }

    if (antrian_find_user_by_username($username)) {
        $errors[] = 'Username sudah digunakan.';
    }

    if (!$errors) {
        $user = antrian_create_user($username, $password, $role);
        antrian_login_user($user);

        if ($user['role'] === 'admin') {
            header('Location: /index.php?page=admin');
            exit;
        }

        header('Location: /index.php?page=loket&loket=1');
        exit;
    }
}
?><!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Akun Antrian</title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body class="app-shell auth-shell">
    <main class="auth-page">
        <section class="auth-card">
            <p class="eyebrow">Buat Akun</p>
            <h1>Daftar</h1>
            <p class="lead">Buat akun admin atau loket untuk masuk ke panel yang sesuai.</p>

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

                <label>
                    Konfirmasi Password
                    <input type="password" name="confirm_password" required>
                </label>

                <fieldset class="auth-role-group">
                    <legend>Pilih tipe akun</legend>
                    <label>
                        <input type="radio" name="role" value="loket" <?= $role === 'loket' ? 'checked' : '' ?>>
                        Loket
                    </label>
                    <label>
                        <input type="radio" name="role" value="admin" <?= $role === 'admin' ? 'checked' : '' ?>>
                        Admin
                    </label>
                </fieldset>

                <button type="submit" class="button button-primary">Daftar</button>
            </form>

            <p class="auth-links">Sudah punya akun? <a href="/index.php?page=login">Login</a></p>
            <p class="auth-links"><a href="/index.php">Kembali ke menu</a></p>
        </section>
    </main>
</body>
</html>
