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
    <meta name="theme-color" content="#101d35">
    <title>Sign in | ARMS</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/login.css">
</head>
<body class="auth-page auth-page--staff">
    <main class="auth-layout">
        <section class="auth-showcase" aria-label="ARMS workspace information">
            <a class="brand" href="index.php" aria-label="ARMS home">
                <span class="brand-mark"><i class="bi bi-layers-half" aria-hidden="true"></i></span>
                <span>ARMS<span class="brand-period">.</span></span>
            </a>

            <div class="showcase-copy">
                <span class="eyebrow"><span class="eyebrow-dot"></span> ACCOUNTS RECEIVABLE MANAGEMENT</span>
                <h1>Keep your cash flow <span>moving forward.</span></h1>
                <p>A clearer view of every invoice, customer, and payment — all in one place.</p>
            </div>

            <div class="preview-card" aria-hidden="true">
                <div class="preview-heading">
                    <div>
                        <span class="preview-label">Receivables overview</span>
                        <strong>Good morning</strong>
                    </div>
                    <span class="preview-icon"><i class="bi bi-arrow-up-right"></i></span>
                </div>
                <div class="preview-total">
                    <strong>₹ 8,42,650</strong>
                    <span><i class="bi bi-graph-up-arrow"></i> Stay on top of what’s due</span>
                </div>
                <div class="preview-chart">
                    <span style="--bar-height: 35%"></span>
                    <span style="--bar-height: 52%"></span>
                    <span style="--bar-height: 43%"></span>
                    <span style="--bar-height: 70%"></span>
                    <span style="--bar-height: 56%"></span>
                    <span style="--bar-height: 84%"></span>
                    <span style="--bar-height: 66%"></span>
                    <span style="--bar-height: 100%"></span>
                    <span style="--bar-height: 78%"></span>
                    <span style="--bar-height: 91%"></span>
                    <span style="--bar-height: 73%"></span>
                    <span style="--bar-height: 96%"></span>
                </div>
                <div class="preview-footer">
                    <span><i class="status-dot status-dot--green"></i> Paid</span>
                    <span><i class="status-dot status-dot--blue"></i> Outstanding</span>
                    <span><i class="status-dot status-dot--amber"></i> Overdue</span>
                </div>
            </div>

            <div class="showcase-footer">
                <span><i class="bi bi-shield-check" aria-hidden="true"></i> Secure access for your team</span>
                <span>ARMS · Finance workspace</span>
            </div>
        </section>

        <section class="auth-panel" aria-labelledby="login-title">
            <div class="auth-form-wrap">
                <div class="mobile-brand brand">
                    <span class="brand-mark"><i class="bi bi-layers-half" aria-hidden="true"></i></span>
                    <span>ARMS<span class="brand-period">.</span></span>
                </div>
                <div class="form-heading">
                    <span class="form-kicker">STAFF WORKSPACE</span>
                    <h2 id="login-title">Welcome back</h2>
                    <p>Sign in to continue to your account.</p>
                </div>

                <?php if ($error): ?>
                    <div class="auth-alert" role="alert">
                        <i class="bi bi-exclamation-circle-fill" aria-hidden="true"></i>
                        <span><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                <?php endif; ?>

                <form action="auth/login.php" method="POST">
                    <div class="field">
                        <label for="email">Work email</label>
                        <div class="input-wrap">
                            <i class="bi bi-envelope" aria-hidden="true"></i>
                            <input id="email" type="email" name="email" placeholder="you@company.com" autocomplete="username" required autofocus>
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
                        <span>Sign in to workspace</span>
                        <i class="bi bi-arrow-right" aria-hidden="true"></i>
                    </button>
                </form>

                <div class="portal-switch">
                    <span>Signing in as a customer?</span>
                    <a href="customers/login.php">Go to customer portal <i class="bi bi-arrow-up-right" aria-hidden="true"></i></a>
                </div>

                <p class="privacy-note"><i class="bi bi-shield-lock" aria-hidden="true"></i> Your account is protected with secure sign-in.</p>
            </div>
        </section>
    </main>
    <script src="assets/js/login.js"></script>
</body>
</html>
