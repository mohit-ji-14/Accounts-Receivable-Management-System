<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

require_once "../config/database.php";

$page_title = "Register Corporate Client";
$page_header = "Register Corporate Account";

// Fetch entities
$entities = $conn->query("SELECT * FROM entities ORDER BY entity_id ASC")->fetchAll(PDO::FETCH_ASSOC);

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $entity_id     = intval($_POST['entity_id'] ?? 1);
    $customer_name = trim($_POST['customer_name'] ?? '');
    $company_name  = trim($_POST['company_name'] ?? '');
    $email         = trim($_POST['email'] ?? '');
    $phone         = trim($_POST['phone'] ?? '');
    $address       = trim($_POST['address'] ?? '');
    $city          = trim($_POST['city'] ?? '');
    $state         = trim($_POST['state'] ?? '');
    $country       = trim($_POST['country'] ?? '');
    $currency_code = trim($_POST['currency_code'] ?? 'INR');
    $credit_limit  = floatval($_POST['credit_limit'] ?? 100000);
    $payment_terms = trim($_POST['payment_terms'] ?? 'Net 30');
    $risk_level    = trim($_POST['risk_level'] ?? 'Low');
    $credit_hold   = isset($_POST['credit_hold']) ? 1 : 0;

    if (empty($customer_name)) {
        $error = "Client Account Name is required.";
    } else {
        try {
            $stmt = $conn->prepare("
                INSERT INTO customers 
                (entity_id, customer_name, company_name, email, phone, address, city, state, country, currency_code, credit_limit, payment_terms, risk_level, credit_hold) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$entity_id, $customer_name, $company_name, $email, $phone, $address, $city, $state, $country, $currency_code, $credit_limit, $payment_terms, $risk_level, $credit_hold]);

            header("Location: index.php?msg=" . urlencode("Corporate account registered successfully!"));
            exit();
        } catch (PDOException $e) {
            $error = "Database Error: " . $e->getMessage();
        }
    }
}

include "../includes/header.php";
include "../includes/sidebar.php";
?>

<div class="content">
    <?php include "../includes/navbar.php"; ?>

    <div class="container-fluid p-4">

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="fw-bold mb-1"><i class="bi bi-building-add text-primary me-2"></i> Register Corporate Account</h4>
                <p class="text-muted small mb-0">Set up enterprise client parameters, currency defaults, and credit risk thresholds</p>
            </div>
            <a href="index.php" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i> Back to Directory
            </a>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger py-2 small border-0 mb-4"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="card-custom p-4">
            <form action="add.php" method="POST">
                <div class="row g-3">
                    
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Subsidiary Entity <span class="text-danger">*</span></label>
                        <select name="entity_id" class="form-select" required>
                            <?php foreach ($entities as $ent): ?>
                                <option value="<?= $ent['entity_id'] ?>"><?= htmlspecialchars($ent['entity_name']) ?> (<?= $ent['country'] ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Client Primary Contact <span class="text-danger">*</span></label>
                        <input type="text" name="customer_name" class="form-control" placeholder="e.g. Rahul Sharma" required>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Company / Enterprise Name</label>
                        <input type="text" name="company_name" class="form-control" placeholder="e.g. Acme Tech Global Inc">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Email Address</label>
                        <input type="email" name="email" class="form-control" placeholder="client@company.com">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Phone Number</label>
                        <input type="text" name="phone" class="form-control" placeholder="+91 98765 43210">
                    </div>

                    <div class="col-12">
                        <label class="form-label small fw-semibold">Corporate Billing Address</label>
                        <textarea name="address" class="form-control" rows="2" placeholder="HQ Address..."></textarea>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">City</label>
                        <input type="text" name="city" class="form-control">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">State / Region</label>
                        <input type="text" name="state" class="form-control">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Country</label>
                        <input type="text" name="country" class="form-control" value="India">
                    </div>

                    <hr class="my-3">
                    <h6 class="fw-bold text-indigo"><i class="bi bi-shield-check me-1"></i> Financial & Credit Risk Parameters</h6>

                    <div class="col-md-3">
                        <label class="form-label small fw-semibold">Billing Currency</label>
                        <select name="currency_code" class="form-select">
                            <option value="INR" selected>INR (₹) - Indian Rupee</option>
                            <option value="USD">USD ($) - US Dollar</option>
                            <option value="EUR">EUR (€) - Euro</option>
                            <option value="GBP">GBP (£) - British Pound</option>
                            <option value="AED">AED (د.إ) - UAE Dirham</option>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label small fw-semibold">Credit Limit</label>
                        <input type="number" step="0.01" name="credit_limit" class="form-control" value="100000.00">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label small fw-semibold">Payment Terms</label>
                        <select name="payment_terms" class="form-select">
                            <option value="Net 15">Net 15 (Due in 15 days)</option>
                            <option value="Net 30" selected>Net 30 (Due in 30 days)</option>
                            <option value="Net 60">Net 60 (Due in 60 days)</option>
                            <option value="Due on Receipt">Due on Receipt</option>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label small fw-semibold">Risk Rating Grade</label>
                        <select name="risk_level" class="form-select">
                            <option value="Low" selected>Low Risk 🟢</option>
                            <option value="Medium">Medium Risk 🟡</option>
                            <option value="High">High Risk 🔴</option>
                        </select>
                    </div>

                    <div class="col-12">
                        <div class="form-check form-switch mt-2">
                            <input class="form-check-input" type="checkbox" name="credit_hold" id="creditHoldSwitch">
                            <label class="form-check-input-label text-danger fw-bold" for="creditHoldSwitch">
                                Place Account on Credit Hold (Block all future invoice generation)
                            </label>
                        </div>
                    </div>

                    <div class="col-12 mt-4 pt-3 border-top d-flex justify-content-end gap-2">
                        <a href="index.php" class="btn btn-light px-4">Cancel</a>
                        <button type="submit" class="btn btn-primary px-4">Save Corporate Account</button>
                    </div>

                </div>
            </form>
        </div>

    </div>
</div>

<?php include "../includes/footer.php"; ?>