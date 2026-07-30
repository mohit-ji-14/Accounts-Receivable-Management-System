<?php
session_start();
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}
$error = $_GET['error'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | ARMS Accounts Receivable System</title>
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Bootstrap 5.3 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 50%, #312e81 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .login-card {
            background: rgba(255, 255, 255, 0.98);
            border-radius: 16px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.35);
            max-width: 440px;
            width: 100%;
            padding: 40px 36px;
        }
        .brand-logo {
            width: 60px;
            height: 60px;
            border-radius: 14px;
            background: linear-gradient(135deg, #4f46e5, #9333ea);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            margin: 0 auto 16px auto;
            box-shadow: 0 8px 20px rgba(79, 70, 229, 0.4);
        }
        .btn-primary-custom {
            background: linear-gradient(135deg, #4f46e5, #4338ca);
            border: none;
            padding: 12px;
            font-weight: 600;
            border-radius: 8px;
            transition: all 0.2s ease;
        }
        .btn-primary-custom:hover {
            opacity: 0.95;
            transform: translateY(-1px);
            box-shadow: 0 6px 16px rgba(79, 70, 229, 0.35);
        }
        .demo-box {
            background-color: #f1f5f9;
            border: 1px dashed #cbd5e1;
            border-radius: 8px;
            padding: 12px 16px;
            font-size: 0.85rem;
            margin-top: 24px;
        }
    </style>
</head>
<body>

<div class="login-card text-center">
    <div class="brand-logo">
        <i class="bi bi-wallet2"></i>
    </div>
    <h3 class="fw-bold text-dark mb-1">ARMS Sign In</h3>
    <p class="text-muted small mb-4">Accounts Receivable Management System</p>

    <?php if ($error): ?>
        <div class="alert alert-danger py-2 small border-0 mb-3 text-start">
            <i class="bi bi-exclamation-triangle-fill me-1"></i> <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <form action="auth/login.php" method="POST" autocomplete="off" class="text-start">
        <div class="mb-3">
            <label class="form-label small fw-semibold text-secondary">Email Address</label>
            <div class="input-group">
                <span class="input-group-text bg-light text-secondary"><i class="bi bi-envelope"></i></span>
                <input type="email" autocomplete="off" name="email" class="form-control" placeholder="name@company.com"  required>
            </div>
        </div>

        <div class="mb-4">
            <label class="form-label small fw-semibold text-secondary">Password</label>
            <div class="input-group">
                <span class="input-group-text bg-light text-secondary"><i class="bi bi-lock"></i></span>
                <input type="password" name="password" class="form-control" placeholder="••••••••" required>
            </div>
        </div>

        <button type="submit" class="btn btn-primary btn-primary-custom w-100 mb-3">
            Sign In to System <i class="bi bi-arrow-right ms-1"></i>
        </button>
    </form>
    <div class="text-center mt-3">
        <a href="customers/login.php" class="text-decoration-none fw-semibold">
        <i class="bi bi-person-badge"></i>
        Customer Portal Login
        </a>
    </div>
</div>


</body>
</html>