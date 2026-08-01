<?php
session_start();

if (!isset($_SESSION["customer_id"])) {
    header("Location: login.php");
    exit();
}

require_once "../config/database.php";
require_once "../config/currency.php";

$customer_id = (int)$_SESSION["customer_id"];
$user_id = $_SESSION["customer_user_id"] ?? null;
$msg = $_GET['msg'] ?? '';
$error = $_GET['error'] ?? '';

// Handle Profile Updates
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['update_profile'])) {
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $state = trim($_POST['state'] ?? '');
    $country = trim($_POST['country'] ?? '');
    $new_password = trim($_POST['new_password'] ?? '');

    try {
        $conn->beginTransaction();

        // 1. Update customer contact details
        $upd_c = $conn->prepare("
            UPDATE customers
            SET phone = ?, address = ?, city = ?, state = ?, country = ?
            WHERE customer_id = ?
        ");
        $upd_c->execute([$phone, $address, $city, $state, $country, $customer_id]);

        // 2. Update password in users table if provided
        if (!empty($new_password)) {
            $hashed = password_hash($new_password, PASSWORD_BCRYPT);

            // Update user table matching customer_id or email
            $upd_u = $conn->prepare("
                UPDATE users
                SET password = ?
                WHERE customer_id = ? OR LOWER(email) = LOWER(?)
            ");
            $upd_u->execute([$hashed, $customer_id, $_SESSION['customer_email']]);
        }

        // 3. Audit log
        $log_stmt = $conn->prepare("INSERT INTO activity_logs (user_name, activity) VALUES (?, ?)");
        $log_stmt->execute([
            $_SESSION['customer_name'] ?? 'Customer',
            "Updated customer profile contact & security settings"
        ]);

        $conn->commit();
        header("Location: customer_profile.php?msg=" . urlencode("Profile updated successfully!"));
        exit();

    } catch (PDOException $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        header("Location: customer_profile.php?error=" . urlencode("Database Error: " . $e->getMessage()));
        exit();
    }
}

// Fetch Customer Data
try {
    $stmt = $conn->prepare("
        SELECT c.*, e.entity_name
        FROM customers c
        LEFT JOIN entities e ON c.entity_id = e.entity_id
        WHERE c.customer_id = ?
        LIMIT 1
    ");
    $stmt->execute([$customer_id]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$customer) {
        die("Customer profile not found.");
    }
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

$page_title = "My Profile";
$page_header = "My Account & Security Profile";

include "../includes/header.php";
include "customers_sidebar.php";
?>

<div class="content">
    <?php include "customer_navbar.php"; ?>

    <div class="container-fluid p-4">

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="fw-bold mb-1"><i class="bi bi-person-bounding-box text-emerald me-2"></i> Client Profile & Terms</h4>
                <p class="text-muted small mb-0">View account credit limits, multi-currency settings, and update security credentials</p>
            </div>
        </div>

        <?php if ($msg): ?>
            <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i> <?= htmlspecialchars($msg) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= htmlspecialchars($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row g-4">

            <!-- Read-Only Account Terms & Credit Status -->
            <div class="col-lg-4">
                <div class="card-custom p-4 h-100">
                    <div class="text-center mb-4">
                        <div class="user-avatar mx-auto mb-3" style="width: 70px; height: 70px; font-size: 1.8rem; background: linear-gradient(135deg,#10b981,#047857);">
                            <?= strtoupper(substr($customer['customer_name'], 0, 1)) ?>
                        </div>
                        <h5 class="fw-bold text-white mb-1"><?= htmlspecialchars($customer['customer_name']) ?></h5>
                        <p class="text-muted small mb-2"><?= htmlspecialchars($customer['company_name'] ?: 'Individual Client') ?></p>
                        <span class="badge bg-emerald-light text-emerald px-3 py-1">
                            <i class="bi bi-building me-1"></i> <?= htmlspecialchars($customer['entity_name'] ?? 'Global HQ') ?>
                        </span>
                    </div>

                    <div class="border-top border-secondary border-opacity-25 pt-3">
                        <h6 class="fw-bold text-white mb-3 small text-uppercase"><i class="bi bi-shield-check text-emerald me-1"></i> ARMS Risk & Credit Terms</h6>

                        <div class="mb-3">
                            <span class="text-muted small d-block">Approved Credit Limit:</span>
                            <strong class="text-emerald fs-5"><?= format_currency($customer['credit_limit'], $customer['currency_code'] ?? 'INR') ?></strong>
                        </div>

                        <div class="mb-3">
                            <span class="text-muted small d-block">Payment Terms:</span>
                            <strong class="text-white"><?= htmlspecialchars($customer['payment_terms'] ?? 'Net 30') ?></strong>
                        </div>

                        <div class="mb-3">
                            <span class="text-muted small d-block">Assigned Currency:</span>
                            <span class="badge bg-light text-dark"><?= htmlspecialchars($customer['currency_code'] ?? 'INR') ?></span>
                        </div>

                        <div class="mb-3">
                            <span class="text-muted small d-block">Credit Risk Grade:</span>
                            <?php
                            $risk_badge = 'bg-success';
                            if ($customer['risk_level'] === 'Medium') $risk_badge = 'bg-warning text-dark';
                            elseif ($customer['risk_level'] === 'High') $risk_badge = 'bg-danger';
                            ?>
                            <span class="badge <?= $risk_badge ?> px-2 py-1"><?= htmlspecialchars($customer['risk_level'] ?? 'Low') ?> Risk</span>
                        </div>

                        <div>
                            <span class="text-muted small d-block">Credit Status:</span>
                            <?php if ($customer['credit_hold']): ?>
                                <span class="badge bg-danger-subtle text-danger border border-danger px-2 py-1"><i class="bi bi-lock-fill me-1"></i> CREDIT HOLD</span>
                            <?php else: ?>
                                <span class="badge bg-success-subtle text-success border border-success px-2 py-1"><i class="bi bi-check-circle-fill me-1"></i> Account Active</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Profile Edit Form -->
            <div class="col-lg-8">
                <div class="card-custom p-4">
                    <h5 class="fw-bold text-white mb-4 border-bottom border-secondary border-opacity-25 pb-3">
                        <i class="bi bi-gear-fill text-primary me-2"></i> Update Contact & Security Information
                    </h5>

                    <form action="customer_profile.php" method="POST">
                        <div class="row g-3">

                            <div class="col-md-6">
                                <label class="form-label text-muted small fw-semibold">Account Name</label>
                                <input type="text" class="form-control bg-dark text-white border-secondary" value="<?= htmlspecialchars($customer['customer_name']) ?>" disabled>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label text-muted small fw-semibold">Email Address (Login)</label>
                                <input type="email" class="form-control bg-dark text-white border-secondary" value="<?= htmlspecialchars($customer['email']) ?>" disabled>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label text-muted small fw-semibold">Phone Number</label>
                                <input type="text" name="phone" class="form-control bg-dark text-white border-secondary" value="<?= htmlspecialchars($customer['phone'] ?? '') ?>" placeholder="+91 98765 43210">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label text-muted small fw-semibold">City</label>
                                <input type="text" name="city" class="form-control bg-dark text-white border-secondary" value="<?= htmlspecialchars($customer['city'] ?? '') ?>">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label text-muted small fw-semibold">State / Province</label>
                                <input type="text" name="state" class="form-control bg-dark text-white border-secondary" value="<?= htmlspecialchars($customer['state'] ?? '') ?>">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label text-muted small fw-semibold">Country</label>
                                <input type="text" name="country" class="form-control bg-dark text-white border-secondary" value="<?= htmlspecialchars($customer['country'] ?? '') ?>">
                            </div>

                            <div class="col-12">
                                <label class="form-label text-muted small fw-semibold">Billing Address</label>
                                <textarea name="address" class="form-control bg-dark text-white border-secondary" rows="2"><?= htmlspecialchars($customer['address'] ?? '') ?></textarea>
                            </div>

                            <div class="col-12 pt-3 border-top border-secondary border-opacity-25">
                                <h6 class="fw-bold text-warning mb-3"><i class="bi bi-key-fill me-1"></i> Change Account Password</h6>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label text-muted small fw-semibold">New Password</label>
                                <input type="password" name="new_password" class="form-control bg-dark text-white border-secondary" placeholder="Leave blank to keep current password">
                                <div class="form-text text-muted small">Minimum 6 characters recommended.</div>
                            </div>

                            <div class="col-12 pt-3 text-end">
                                <button type="submit" name="update_profile" class="btn btn-success px-4">
                                    <i class="bi bi-save-fill me-1"></i> Save Changes
                                </button>
                            </div>

                        </div>
                    </form>
                </div>
            </div>

        </div>

    </div>
</div>

<?php include "../includes/footer.php"; ?>
