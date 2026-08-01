<?php
session_start();

if (!isset($_SESSION["customer_id"])) {
    header("Location: login.php");
    exit();
}

require_once "../config/database.php";
require_once "../config/currency.php";

$customer_id = (int)$_SESSION["customer_id"];
$preselect_invoice = isset($_GET['invoice_id']) ? (int)$_GET['invoice_id'] : 0;
$msg = $_GET['msg'] ?? '';
$error = $_GET['error'] ?? '';

// Handle Payment Submission
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['submit_payment'])) {
    $inv_id = (int)($_POST['invoice_id'] ?? 0);
    $amount = (float)($_POST['amount'] ?? 0);
    $payment_method = trim($_POST['payment_method'] ?? 'Wire Transfer');
    $reference_no = trim($_POST['reference_no'] ?? '');
    $payment_date = trim($_POST['payment_date'] ?? date('Y-m-d'));
    $remarks = trim($_POST['remarks'] ?? '');

    if ($inv_id <= 0 || $amount <= 0) {
        header("Location: customer_payments.php?error=" . urlencode("Please select a valid invoice and enter an amount greater than zero."));
        exit();
    }

    try {
        // Verify invoice belongs to customer
        $chk_stmt = $conn->prepare("SELECT * FROM invoices WHERE invoice_id = ? AND customer_id = ? LIMIT 1");
        $chk_stmt->execute([$inv_id, $customer_id]);
        $inv_data = $chk_stmt->fetch(PDO::FETCH_ASSOC);

        if (!$inv_data) {
            header("Location: customer_payments.php?error=" . urlencode("Invalid invoice selected."));
            exit();
        }

        $conn->beginTransaction();

        // 1. Insert Payment Record
        $ins_stmt = $conn->prepare("
            INSERT INTO payments (invoice_id, payment_date, amount, currency_code, fx_rate, payment_method, reference_no, remarks)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $ins_stmt->execute([
            $inv_id,
            $payment_date,
            $amount,
            $inv_data['currency_code'],
            $inv_data['fx_rate'] ?? 1.0,
            $payment_method,
            $reference_no,
            $remarks
        ]);

        // 2. Update Invoice Paid Amount and Status
        $new_paid = $inv_data['paid_amount'] + $amount;
        $new_status = $inv_data['status'];

        if ($new_paid >= $inv_data['total_amount']) {
            $new_status = 'Paid';
        } else if ($new_paid > 0) {
            $new_status = 'Partially Paid';
        }

        $upd_stmt = $conn->prepare("UPDATE invoices SET paid_amount = ?, status = ? WHERE invoice_id = ?");
        $upd_stmt->execute([$new_paid, $new_status, $inv_id]);

        // 3. Log Activity
        $log_stmt = $conn->prepare("INSERT INTO activity_logs (user_name, activity) VALUES (?, ?)");
        $log_stmt->execute([
            $_SESSION['customer_name'] ?? 'Customer',
            "Submitted payment of " . format_currency($amount, $inv_data['currency_code']) . " for " . $inv_data['invoice_number']
        ]);

        $conn->commit();

        header("Location: customer_payments.php?msg=" . urlencode("Payment submitted successfully!"));
        exit();

    } catch (PDOException $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        header("Location: customer_payments.php?error=" . urlencode("Database Error: " . $e->getMessage()));
        exit();
    }
}

// Fetch Unpaid Invoices for Payment Dropdown
$unpaid_stmt = $conn->prepare("
    SELECT invoice_id, invoice_number, total_amount, paid_amount, currency_code
    FROM invoices
    WHERE customer_id = ? AND status != 'Paid'
    ORDER BY invoice_date ASC
");
$unpaid_stmt->execute([$customer_id]);
$unpaid_invoices = $unpaid_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch All Payment History for Customer
$stmt = $conn->prepare("
    SELECT p.*, i.invoice_number, i.total_amount as invoice_total
    FROM payments p
    INNER JOIN invoices i ON p.invoice_id = i.invoice_id
    WHERE i.customer_id = ?
    ORDER BY p.payment_date DESC, p.payment_id DESC
");
$stmt->execute([$customer_id]);
$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = "My Payments";
$page_header = "My Payment History & Online Submission";

include "../includes/header.php";
include "customers_sidebar.php";
?>

<div class="content">
    <?php include "customer_navbar.php"; ?>

    <div class="container-fluid p-4">

        <!-- Top Header & Submit Action Button -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="fw-bold mb-1"><i class="bi bi-credit-card-2-front-fill text-success me-2"></i> My Payments Ledger</h4>
                <p class="text-muted small mb-0">Track all settled payments and record new remittances</p>
            </div>
            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#paymentModal">
                <i class="bi bi-plus-circle-fill me-1"></i> Make / Record Payment
            </button>
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

        <!-- Payments Table -->
        <div class="card-custom">
            <div class="p-3 border-bottom border-secondary border-opacity-25 d-flex justify-content-between align-items-center">
                <h5 class="fw-bold text-white mb-0">
                    <i class="bi bi-receipt-cutoff text-success me-2"></i> Payment Records
                </h5>
                <span class="badge bg-light text-dark"><?= count($payments) ?> Payments</span>
            </div>

            <div class="table-responsive">
                <table class="table table-custom mb-0 align-middle">
                    <thead>
                        <tr>
                            <th>Payment ID</th>
                            <th>Date</th>
                            <th>Invoice Number</th>
                            <th>Payment Method</th>
                            <th>Transaction / Ref No</th>
                            <th>Remarks</th>
                            <th class="text-end">Amount Paid</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($payments) > 0): ?>
                            <?php foreach ($payments as $pay): ?>
                                <tr>
                                    <td><span class="badge bg-dark border border-secondary">#PAY-<?= $pay['payment_id'] ?></span></td>
                                    <td><?= date('d M Y', strtotime($pay['payment_date'])) ?></td>
                                    <td>
                                        <a href="view_invoice.php?id=<?= $pay['invoice_id'] ?>" class="text-info fw-bold text-decoration-none">
                                            <?= htmlspecialchars($pay['invoice_number']) ?>
                                        </a>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary-subtle text-light border">
                                            <?= htmlspecialchars($pay['payment_method']) ?>
                                        </span>
                                    </td>
                                    <td class="font-monospace text-warning"><?= htmlspecialchars($pay['reference_no'] ?: 'N/A') ?></td>
                                    <td class="text-muted small"><?= htmlspecialchars($pay['remarks'] ?: '-') ?></td>
                                    <td class="text-end text-success fw-bold fs-6">
                                        <?= format_currency($pay['amount'], $pay['currency_code']) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center py-5 text-muted">
                                    <i class="bi bi-credit-card fs-1 d-block mb-2 text-secondary"></i>
                                    No payment records found.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<!-- Modal: Make / Record Payment -->
<div class="modal fade" id="paymentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-dark text-white border border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title fw-bold text-emerald">
                    <i class="bi bi-credit-card-fill me-2"></i> Submit Invoice Payment
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="customer_payments.php" method="POST">
                <div class="modal-body">

                    <div class="mb-3">
                        <label class="form-label text-muted small fw-semibold">Select Invoice</label>
                        <select name="invoice_id" class="form-select bg-dark text-white border-secondary" id="invoiceSelect" required onchange="updateDueAmount(this)">
                            <option value="">-- Choose Unpaid Invoice --</option>
                            <?php foreach ($unpaid_invoices as $u_inv): 
                                $rem = $u_inv['total_amount'] - $u_inv['paid_amount'];
                            ?>
                                <option value="<?= $u_inv['invoice_id'] ?>" data-due="<?= $rem ?>" <?= ($preselect_invoice === $u_inv['invoice_id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($u_inv['invoice_number']) ?> (Balance: <?= format_currency($rem, $u_inv['currency_code']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label text-muted small fw-semibold">Payment Amount</label>
                        <div class="input-group">
                            <span class="input-group-text bg-secondary border-secondary text-white"><i class="bi bi-currency-dollar"></i></span>
                            <input type="number" step="0.01" name="amount" id="amountInput" class="form-control bg-dark text-white border-secondary" placeholder="0.00" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label text-muted small fw-semibold">Payment Method</label>
                        <select name="payment_method" class="form-select bg-dark text-white border-secondary" required>
                            <option value="Wire Transfer">Wire Transfer (NEFT/RTGS/IMPS)</option>
                            <option value="UPI">UPI / Instant Pay</option>
                            <option value="Credit Card">Credit Card</option>
                            <option value="ACH">ACH Transfer</option>
                            <option value="Cheque">Cheque</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label text-muted small fw-semibold">Transaction / Reference Number</label>
                        <input type="text" name="reference_no" class="form-control bg-dark text-white border-secondary" placeholder="e.g. UTR-9982310, TXN-8849" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label text-muted small fw-semibold">Payment Date</label>
                        <input type="date" name="payment_date" class="form-control bg-dark text-white border-secondary" value="<?= date('Y-m-d') ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label text-muted small fw-semibold">Remarks / Notes (Optional)</label>
                        <textarea name="remarks" class="form-control bg-dark text-white border-secondary" rows="2" placeholder="Any payment notes or reference detail..."></textarea>
                    </div>

                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="submit_payment" class="btn btn-success px-4">
                        <i class="bi bi-check-circle-fill me-1"></i> Submit Payment
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function updateDueAmount(selectElem) {
    const selectedOption = selectElem.options[selectElem.selectedIndex];
    const dueAmount = selectedOption.getAttribute('data-due');
    if (dueAmount) {
        document.getElementById('amountInput').value = parseFloat(dueAmount).toFixed(2);
    }
}

// Auto open modal if preselect_invoice parameter is set
document.addEventListener("DOMContentLoaded", function() {
    <?php if ($preselect_invoice > 0): ?>
        var pModal = new bootstrap.Modal(document.getElementById('paymentModal'));
        pModal.show();
        var sel = document.getElementById('invoiceSelect');
        if (sel) updateDueAmount(sel);
    <?php endif; ?>
});
</script>

<?php include "../includes/footer.php"; ?>
