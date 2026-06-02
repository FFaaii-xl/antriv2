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
                header('Location: ' . antrian_base_url() . '/admin');
                exit;
            }

            $loketNumber = antrian_loket_number_for_user_id((int) $user['id']) ?? 1;

            header('Location: ' . antrian_base_url() . '/loket?loket=' . $loketNumber);
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
    <link href="<?= antrian_base_url() ?>/assets/vendor/bootstrap/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= antrian_base_url() ?>/assets/css/style.css">
    <style>
        body.auth-shell {
            background: linear-gradient(135deg, #fdfaff 0%, #f4f0fa 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
        }
        .auth-card-premium {
            background: #ffffff;
            border: 1px solid rgba(124, 58, 237, 0.08);
            box-shadow: 0 18px 45px rgba(124, 58, 237, 0.05), 0 4px 10px rgba(0, 0, 0, 0.02);
            border-radius: 24px;
            padding: 48px;
            width: 100%;
            max-width: 440px;
            text-align: center;
        }
        .auth-logo {
            width: 64px;
            height: 64px;
            margin: 0 auto 24px;
            background: rgba(124, 58, 237, 0.05);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--accent);
        }
        .auth-title {
            font-size: 1.8rem;
            font-weight: 800;
            color: var(--text);
            margin-bottom: 8px;
            letter-spacing: -0.02em;
        }
        .auth-subtitle {
            font-size: 0.95rem;
            color: var(--muted);
            margin-bottom: 32px;
            line-height: 1.5;
        }
        .auth-form label {
            text-align: left;
            display: block;
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--text);
            margin-bottom: 6px;
        }
        .auth-form input {
            width: 100%;
            padding: 14px 16px;
            border-radius: 12px;
            border: 1px solid rgba(15, 23, 42, 0.1);
            background: #fcfcfd;
            font-size: 0.95rem;
            margin-bottom: 20px;
            transition: all 0.2s ease;
        }
        .auth-form input:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 4px rgba(124, 58, 237, 0.1);
            background: #ffffff;
        }
        .auth-submit-btn {
            width: 100%;
            padding: 14px;
            border-radius: 12px;
            background: var(--accent);
            color: white;
            font-weight: 600;
            font-size: 1rem;
            border: none;
            cursor: pointer;
            transition: transform 0.1s, box-shadow 0.2s;
            margin-bottom: 24px;
        }
        .auth-submit-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 8px 20px rgba(124, 58, 237, 0.2);
        }
        .auth-submit-btn:active {
            transform: translateY(1px);
        }
        .auth-alert-error {
            background: rgba(239, 68, 68, 0.05);
            color: var(--danger);
            border: 1px solid rgba(239, 68, 68, 0.1);
            padding: 12px 16px;
            border-radius: 12px;
            margin-bottom: 24px;
            font-size: 0.9rem;
            text-align: left;
        }
        .auth-alert-error p {
            margin: 0;
        }
    </style>
</head>
<body class="auth-shell">
    <div class="auth-card-premium">
        <div class="auth-logo" style="background: transparent;">
            <img src="<?= antrian_base_url() ?>/assets/img/logosmk4.png" alt="Logo SMKN 4 Surakarta" style="width: 56px; height: 56px; object-fit: contain;">
        </div>
        <h1 class="auth-title">Antrian SPMB 2026</h1>
        <p class="auth-subtitle">
            Sistem Antrian Cerdas<br>
            By SMK N 4 Surakarta<br>
            <span style="font-style: italic; font-weight: 600; font-size: 0.85rem; color: var(--accent);">"Smart, Beauty & Good Character"</span>
        </p>

        <?php if ($errors): ?>
            <div class="auth-alert-error">
                <?php foreach ($errors as $error): ?>
                    <p><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="post" class="auth-form">
            <label>Username</label>
            <input type="text" name="username" value="<?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8') ?>" placeholder="Masukkan username..." required>

            <label>Password</label>
            <input type="password" name="password" placeholder="Masukkan password..." required>

            <button type="submit" class="auth-submit-btn">Masuk Sistem</button>
        </form>

        <p class="auth-links" style="font-size: 0.85rem; color: var(--muted); margin: 0;">
            <a href="<?= antrian_base_url() ?>/layar" style="color: var(--accent); text-decoration: none; font-weight: 500;">&larr; Lihat Layar Publik</a>
        </p>
    </div>
</body>
</html>


