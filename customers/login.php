<?php
session_start();

if (isset($_SESSION['customer_id'])) {
    header("Location: customer_dashboard.php");
    exit();
}

$error = $_GET['error'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#102b27">
    <title>Customer sign in | ARMS</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/login.css">
</head>
<body class="auth-page auth-page--customer">
    <main class="auth-layout">
        <section class="auth-showcase" aria-label="ARMS customer portal information">
            <a class="brand" href="../index.php" aria-label="ARMS home">
                <span class="brand-mark"><i class="bi bi-layers-half" aria-hidden="true"></i></span>
                <span>ARMS<span class="brand-period">.</span></span>
            </a>

            <div class="showcase-copy">
                <span class="eyebrow"><span class="eyebrow-dot"></span> YOUR CUSTOMER PORTAL</span>
                <h1>Your business, <span>in good balance.</span></h1>
                <p>View invoices, follow payments, and stay connected with your account — whenever you need.</p>
            </div>

            <div class="preview-card customer-preview" aria-hidden="true">
                <div class="preview-heading">
                    <div>
                        <span class="preview-label">Your account</span>
                        <strong>Everything in one view</strong>
                    </div>
                    <span class="preview-icon"><i class="bi bi-person-check"></i></span>
                </div>
                <div class="portal-feature">
                    <span class="feature-icon"><i class="bi bi-receipt"></i></span>
                    <span><strong>Invoices</strong><small>Review current and past invoices</small></span>
                    <i class="bi bi-chevron-right feature-chevron"></i>
                </div>
                <div class="portal-feature">
                    <span class="feature-icon"><i class="bi bi-credit-card"></i></span>
                    <span><strong>Payments</strong><small>Keep track of your payment history</small></span>
                    <i class="bi bi-chevron-right feature-chevron"></i>
                </div>
                <div class="portal-feature">
                    <span class="feature-icon"><i class="bi bi-file-earmark-text"></i></span>
                    <span><strong>Statements</strong><small>Access your account statements</small></span>
                    <i class="bi bi-chevron-right feature-chevron"></i>
                </div>
            </div>

            <div class="showcase-footer">
                <span><i class="bi bi-shield-check" aria-hidden="true"></i> Your account information stays protected</span>
                <span>ARMS · Customer portal</span>
            </div>
        </section>

        <section class="auth-panel" aria-labelledby="login-title">
            <div class="auth-form-wrap">
                <div class="mobile-brand brand">
                    <span class="brand-mark"><i class="bi bi-layers-half" aria-hidden="true"></i></span>
                    <span>ARMS<span class="brand-period">.</span></span>
                </div>
                <div class="form-heading">
                    <span class="form-kicker">CUSTOMER PORTAL</span>
                    <h2 id="login-title">Welcome back</h2>
                    <p>Sign in to manage your account.</p>
                </div>

                <?php if ($error): ?>
                    <div class="auth-alert" role="alert">
                        <i class="bi bi-exclamation-circle-fill" aria-hidden="true"></i>
                        <span><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                <?php endif; ?>

                <form action="customer_auth.php" method="POST">
                    <div class="field">
                        <label for="email">Email address</label>
                        <div class="input-wrap">
                            <i class="bi bi-envelope" aria-hidden="true"></i>
                            <input id="email" type="email" name="email" placeholder="you@example.com" autocomplete="username" required autofocus>
                        </div>
                    </div>

                    <div class="field">
                        <div class="field-label-row">
                            <label for="password">Password</label>
                        </div>
                        <div class="input-wrap">
                            <i class="bi bi-lock" aria-hidden="true"></i>
                            <input id="password" type="password" name="password" placeholder="Enter your password" autocomplete="current-password" required>
                            <button class="password-toggle" type="button" data-password-toggle aria-label="Show password" aria-pressed="false">
                                <i class="bi bi-eye" aria-hidden="true"></i>
                            </button>
                        </div>
                    </div>

                    <button class="submit-button" type="submit">
                        <span>Sign in to customer portal</span>
                        <i class="bi bi-arrow-right" aria-hidden="true"></i>
                    </button>
                </form>

                <div class="portal-switch">
                    <span>Part of the ARMS team?</span>
                    <a href="../index.php">Go to staff sign in <i class="bi bi-arrow-up-right" aria-hidden="true"></i></a>
                </div>

                <p class="privacy-note"><i class="bi bi-shield-lock" aria-hidden="true"></i> Your account is protected with secure sign-in.</p>
            </div>
        </section>
    </main>
    <script src="../assets/js/login.js"></script>
</body>
</html>
