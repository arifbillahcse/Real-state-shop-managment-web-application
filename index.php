<?php
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/classes/User.php';

// Already logged in → go to dashboard
if (isLoggedIn()) {
    redirect(BASE_URL . '/pages/dashboard.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'ইউজারনেম ও পাসওয়ার্ড দিন।';
    } else {
        $user = User::login($username, $password);
        if ($user) {
            redirect(BASE_URL . '/pages/dashboard.php');
        } else {
            $error = 'ইউজারনেম বা পাসওয়ার্ড ভুল!';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login — <?= e(APP_NAME) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', sans-serif;
        }
        .login-card {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.4);
            padding: 2.5rem;
            width: 100%;
            max-width: 420px;
        }
        .brand-icon {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, #e63946, #457b9d);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 2rem;
            color: #fff;
        }
        .btn-login {
            background: linear-gradient(135deg, #e63946, #c1121f);
            border: none;
            color: #fff;
            font-weight: 600;
            letter-spacing: .5px;
        }
        .btn-login:hover { opacity: .9; color: #fff; }
        .form-control:focus { border-color: #e63946; box-shadow: 0 0 0 .2rem rgba(230,57,70,.2); }
    </style>
</head>
<body>
<div class="login-card">
    <div class="brand-icon"><i class="bi bi-shop"></i></div>
    <h4 class="text-center fw-bold mb-1"><?= e(APP_NAME) ?></h4>
    <p class="text-center text-muted small mb-4">আপনার অ্যাকাউন্টে লগইন করুন</p>

    <?php if ($error): ?>
    <div class="alert alert-danger py-2"><i class="bi bi-exclamation-triangle-fill me-1"></i><?= e($error) ?></div>
    <?php endif; ?>

    <form method="POST" novalidate>
        <div class="mb-3">
            <label class="form-label fw-semibold">ইউজারনেম</label>
            <div class="input-group">
                <span class="input-group-text"><i class="bi bi-person"></i></span>
                <input type="text" name="username" class="form-control"
                       value="<?= e($_POST['username'] ?? '') ?>"
                       placeholder="username" required autofocus>
            </div>
        </div>
        <div class="mb-4">
            <label class="form-label fw-semibold">পাসওয়ার্ড</label>
            <div class="input-group">
                <span class="input-group-text"><i class="bi bi-lock"></i></span>
                <input type="password" name="password" class="form-control" placeholder="••••••••" required>
            </div>
        </div>
        <button type="submit" class="btn btn-login w-100 py-2">
            <i class="bi bi-box-arrow-in-right me-1"></i> লগইন করুন
        </button>
    </form>
    <p class="text-center text-muted small mt-4 mb-0">© <?= date('Y') ?> <?= e(APP_NAME) ?></p>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
