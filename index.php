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
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root { --brand:#e63946; --brand-dark:#c1121f; }
        * { font-family: 'Hind Siliguri','Segoe UI',sans-serif; }
        body {
            background: linear-gradient(135deg, #161a27 0%, #1f2433 55%, #2a1820 100%);
            min-height: 100vh;
            display: flex; align-items: center; justify-content: center;
            position: relative; overflow: hidden;
        }
        /* Animated floating blobs */
        body::before, body::after {
            content: ''; position: absolute; border-radius: 50%; filter: blur(60px); opacity: .4;
            animation: float 14s ease-in-out infinite;
        }
        body::before { width: 360px; height: 360px; background: #e63946; top: -80px; left: -60px; }
        body::after  { width: 420px; height: 420px; background: #457b9d; bottom: -120px; right: -80px; animation-delay: -7s; }
        @keyframes float { 0%,100% { transform: translate(0,0) scale(1); } 50% { transform: translate(30px,-30px) scale(1.1); } }

        .login-card {
            position: relative; z-index: 1;
            background: rgba(255,255,255,.97);
            backdrop-filter: blur(20px);
            border-radius: 22px;
            box-shadow: 0 30px 80px rgba(0,0,0,0.5);
            padding: 2.75rem 2.5rem;
            width: 100%; max-width: 420px;
            animation: cardIn .55s cubic-bezier(.2,.8,.2,1) both;
        }
        @keyframes cardIn { from { opacity: 0; transform: translateY(24px) scale(.97); } to { opacity: 1; transform: none; } }

        .brand-icon {
            width: 76px; height: 76px;
            background: linear-gradient(135deg, #e63946, #c1121f);
            border-radius: 22px;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 1.1rem; font-size: 2.1rem; color: #fff;
            box-shadow: 0 12px 30px rgba(230,57,70,.45);
            transform: rotate(-6deg);
        }
        .login-card h4 { color: #1e2235; }
        .form-label { font-weight: 600; color: #5b6070; font-size: .9rem; }
        .form-control { border-radius: 11px; padding: .6rem .85rem; border-color: #eceef3; }
        .form-control:focus { border-color: #e63946; box-shadow: 0 0 0 .2rem rgba(230,57,70,.18); }
        .input-group-text { border-radius: 11px; background: #f7f8fc; border-color: #eceef3; color: #8a8fa3; }
        .btn-login {
            background: linear-gradient(135deg, #e63946, #c1121f);
            border: none; color: #fff; font-weight: 600; letter-spacing: .5px;
            border-radius: 12px; box-shadow: 0 8px 22px rgba(230,57,70,.4);
            transition: .2s;
        }
        .btn-login:hover { color: #fff; transform: translateY(-2px); box-shadow: 0 12px 30px rgba(230,57,70,.5); filter: brightness(1.05); }
        .alert { border: none; border-radius: 11px; border-left: 4px solid #e63946; background: #fdecee; color: #a01622; }
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
