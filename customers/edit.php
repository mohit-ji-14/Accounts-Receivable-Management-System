<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

require_once "../config/database.php";

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) {
    header("Location: index.php");
    exit();
}

$page_title = "Edit Customer";
$page_header = "Update Client Details";

$error = '';
try {
    $stmt = $conn->prepare("SELECT * FROM customers WHERE customer_id = ?");
    $stmt->execute([$id]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$customer) {
        die("Customer not found.");
    }
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customer_name = trim($_POST['customer_name'] ?? '');
    $company_name  = trim($_POST['company_name'] ?? '');
    $email         = trim($_POST['email'] ?? '');
    $phone         = trim($_POST['phone'] ?? '');
    $address       = trim($_POST['address'] ?? '');
    $city          = trim($_POST['city'] ?? '');
    $state         = trim($_POST['state'] ?? '');
    $country       = trim($_POST['country'] ?? '');
    $credit_limit  = floatval($_POST['credit_limit'] ?? 50000);
    $payment_terms = trim($_POST['payment_terms'] ?? 'Net 30');

    if (empty($customer_name)) {
        $error = "Client Name is required.";
    } else {
        try {
            $upd = $conn->prepare("
                UPDATE customers 
                SET customer_name = ?, company_name = ?, email = ?, phone = ?, address = ?, city = ?, state = ?, country = ?, credit_limit = ?, payment_terms = ? 
                WHERE customer_id = ?
            ");
            $upd->execute([$customer_name, $company_name, $email, $phone, $address, $city, $state, $country, $credit_limit, $payment_terms, $id]);

            header("Location: index.php?msg=" . urlencode("Customer updated successfully!"));
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
                <h4 class="fw-bold mb-1"><i class="bi bi-pencil-square text-warning me-2"></i> Update Client Profile</h4>
                <p class="text-muted small mb-0">Modify information for <?= htmlspecialchars($customer['customer_name']) ?></p>
            </div>
            <a href="index.php" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i> Back to Directory
            </a>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger py-2 small border-0 mb-4"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="card-custom p-4">
            <form action="edit.php?id=<?= $id ?>" method="POST">
                <div class="row g-3">
                    
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Client Name <span class="text-danger">*</span></label>
                        <input type="text" name="customer_name" class="form-control" value="<?= htmlspecialchars($customer['customer_name']) ?>" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Company Name</label>
                        <input type="text" name="company_name" class="form-control" value="<?= htmlspecialchars($customer['company_name']) ?>">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Email Address</label>
                        <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($customer['email']) ?>">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Phone Number</label>
                        <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($customer['phone']) ?>">
                    </div>

                    <div class="col-12">
                        <label class="form-label small fw-semibold">Billing Address</label>
                        <textarea name="address" class="form-control" rows="2"><?= htmlspecialchars($customer['address']) ?></textarea>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">City</label>
                        <input type="text" name="city" class="form-control" value="<?= htmlspecialchars($customer['city']) ?>">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">State / Region</label>
                        <input type="text" name="state" class="form-control" value="<?= htmlspecialchars($customer['state']) ?>">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Country</label>
                        <input type="text" name="country" class="form-control" value="<?= htmlspecialchars($customer['country']) ?>">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Credit Limit (₹)</label>
                        <input type="number" step="0.01" name="credit_limit" class="form-control" value="<?= htmlspecialchars($customer['credit_limit'] ?? 50000) ?>">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Payment Terms</label>
                        <select name="payment_terms" class="form-select">
                            <option value="Net 15" <?= ($customer['payment_terms'] ?? '') === 'Net 15' ? 'selected' : '' ?>>Net 15 (Due in 15 days)</option>
                            <option value="Net 30" <?= ($customer['payment_terms'] ?? '') === 'Net 30' ? 'selected' : '' ?>>Net 30 (Due in 30 days)</option>
                            <option value="Net 60" <?= ($customer['payment_terms'] ?? '') === 'Net 60' ? 'selected' : '' ?>>Net 60 (Due in 60 days)</option>
                            <option value="Due on Receipt" <?= ($customer['payment_terms'] ?? '') === 'Due on Receipt' ? 'selected' : '' ?>>Due on Receipt</option>
                        </select>
                    </div>

                    <div class="col-12 mt-4 pt-3 border-top d-flex justify-content-end gap-2">
                        <a href="index.php" class="btn btn-light px-4">Cancel</a>
                        <button type="submit" class="btn btn-warning px-4">Update Changes</button>
                    </div>

                </div>
            </form>
        </div>

    </div>
</div>

<?php include "../includes/footer.php"; ?>
