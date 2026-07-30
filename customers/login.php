<?php
session_start();

if (isset($_SESSION['customer_id'])) {
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
    <title>Customer Login | ARMS</title>

    <!-- Google Font -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', sans-serif;
        }

        body {
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;

            background: linear-gradient(
            135deg,
            #022c22 0%,
            #065f46 45%,
            #059669 100%
        );
}

        .login-card {
            width: 100%;
            max-width: 460px;
            background: rgba(255, 255, 255, 0.98);
            border-radius: 18px;
            padding: 40px;
            box-shadow: 0 25px 60px rgba(0, 0, 0, 0.35);
        }

        .logo {
            width: 70px;
            height: 70px;
            margin: 0 auto 18px;
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg,#10b981,#047857);
            color: #fff;
            font-size: 30px;
            box-shadow: 0 10px 25px rgba(79, 70, 229, .35);
        }

        h2 {
            text-align: center;
            font-weight: 700;
            margin-bottom: 6px;
        }

        .subtitle {
            text-align: center;
            color: #6b7280;
            margin-bottom: 30px;
        }

        .form-label {
            font-weight: 600;
            color: #475569;
        }

        .input-group-text {
            background: #f8fafc;
        }

        .btn-login{
        background:linear-gradient(135deg,#10b981,#047857);
        border:none;
        color:white;
        padding:12px;
        font-weight:600;
        border-radius:8px;
        transition:.3s;
        }
        .btn-login:hover{
        background:linear-gradient(135deg,#059669,#065f46);
        transform:translateY(-2px);
        box-shadow:0 12px 25px rgba(16,185,129,.35);
        }

        .footer-link {
            text-align: center;
            margin-top: 20px;
        }

        .footer-link a {
            text-decoration: none;
            font-weight: 500;
        }
    </style>
</head>

<body>

    <div class="login-card">

        <div class="logo">
            <i class="bi bi-person-badge-fill"></i>
        </div>

        <h2 style="color:#047857";>Customer Portal </h2>

        <p class="subtitle">
            Accounts Receivable Management System
        </p>

        <?php if ($error): ?>
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-circle-fill me-2"></i>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form action="customer_auth.php" method="POST">

            <div class="mb-3">
                <label class="form-label">Email Address</label>

                <div class="input-group">
                    <span class="input-group-text">
                        <i class="bi bi-envelope"></i>
                    </span>

                    <input
                        type="email"
                        name="email"
                        class="form-control"
                        placeholder="customer@email.com"
                        required>
                </div>
            </div>

            <div class="mb-4">
                <label class="form-label">Password</label>

                <div class="input-group">
                    <span class="input-group-text">
                        <i class="bi bi-lock"></i>
                    </span>

                    <input
                        type="password"
                        name="password"
                        class="form-control"
                        placeholder="Enter Password"
                        required>
                </div>
            </div>

            <button type="submit" class="btn btn-primary btn-login w-100">
                Customer Sign In
                <i class="bi bi-arrow-right ms-2"></i>
            </button>

        </form>

        <div class="footer-link">
            <a href="../index.php"class="text-success fw-semibold">
                <i class="bi bi-arrow-left"></i>
                Back to Admin Login
            </a>
        </div>

    </div>

</body>

</html>