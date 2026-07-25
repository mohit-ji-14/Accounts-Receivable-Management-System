<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

require_once "../config/database.php";

$page_title = "Record Payment";
$page_header = "Record Cash Receipt";

$pre_invoice = intval($_GET['invoice_id'] ?? 0);

// Fetch unpaid/partially paid invoices
try {
    $stmt = $conn->query("
        SELECT i.invoice_id, i.invoice_number, i.total_amount, i.paid_amount, (i.total_amount - i.paid_amount) AS balance_due, c.customer_name, c.company_name
        FROM invoices i
        JOIN customers c ON i.customer_id = c.customer_id
        WHERE i.status != 'Paid' AND (i.total_amount - i.paid_amount) > 0
        ORDER BY i.invoice_id DESC
    ");
    $pending_invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // If pre_invoice is specified but paid in full, fetch it anyway for dropdown
    if ($pre_invoice > 0) {
        $check_stmt = $conn->prepare("
            SELECT i.invoice_id, i.invoice_number, i.total_amount, i.paid_amount, (i.total_amount - i.paid_amount) AS balance_due, c.customer_name, c.company_name
            FROM invoices i
            JOIN customers c ON i.customer_id = c.customer_id
            WHERE i.invoice_id = ?
        ");
        $check_stmt->execute([$pre_invoice]);
        $single_inv = $check_stmt->fetch(PDO::FETCH_ASSOC);
        
        $already_in = false;
        foreach ($pending_invoices as $pi) {
            if ($pi['invoice_id'] == $pre_invoice) {
                $already_in = true;
                break;
            }
        }
        if (!$already_in && $single_inv) {
            $pending_invoices[] = $single_inv;
        }
    }

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $invoice_id     = intval($_POST['invoice_id'] ?? 0);
    $payment_date   = $_POST['payment_date'] ?? date('Y-m-d');
    $amount         = floatval($_POST['amount'] ?? 0);
    $payment_method = $_POST['payment_method'] ?? 'Bank Transfer';
    $reference_no   = trim($_POST['reference_no'] ?? '');
    $remarks        = trim($_POST['remarks'] ?? '');

    if ($invoice_id <= 0) {
        $error = "Please select an invoice.";
    } elseif ($amount <= 0) {
        $error = "Payment amount must be greater than zero.";
    } else {
        try {
            $conn->beginTransaction();

            // Fetch invoice balance
            $inv_stmt = $conn->prepare("SELECT total_amount, paid_amount FROM invoices WHERE invoice_id = ? FOR UPDATE");
            $inv_stmt->execute([$invoice_id]);
            $inv = $inv_stmt->fetch(PDO::FETCH_ASSOC);

            if (!$inv) {
                throw new Exception("Invoice not found.");
            }

            $current_paid = floatval($inv['paid_amount']);
            $total_amt    = floatval($inv['total_amount']);
            $balance_due  = max(0, $total_amt - $current_paid);

            if ($amount > ($balance_due + 0.01)) { // 1 cent tolerance for rounding
                throw new Exception("Payment amount (₹" . number_format($amount, 2) . ") exceeds remaining balance due (₹" . number_format($balance_due, 2) . ").");
            }

            // Insert payment receipt
            $pay_ins = $conn->prepare("
                INSERT INTO payments (invoice_id, payment_date, amount, payment_method, reference_no, remarks) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $pay_ins->execute([$invoice_id, $payment_date, $amount, $payment_method, $reference_no, $remarks]);

            // Update Invoice Paid Amount & Status
            $new_paid = $current_paid + $amount;
            $new_status = 'Partially Paid';
            if ($new_paid >= ($total_amt - 0.01)) {
                $new_status = 'Paid';
                $new_paid = $total_amt; // snap to total
            }

            $upd_inv = $conn->prepare("UPDATE invoices SET paid_amount = ?, status = ? WHERE invoice_id = ?");
            $upd_inv->execute([$new_paid, $new_status, $invoice_id]);

            $conn->commit();

            header("Location: index.php?msg=" . urlencode("Payment recorded successfully!"));
            exit();

        } catch (Exception $e) {
            $conn->rollBack();
            $error = $e->getMessage();
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
                <h4 class="fw-bold mb-1"><i class="bi bi-cash-stack text-success me-2"></i> Record Payment</h4>
                <p class="text-muted small mb-0">Apply a collection receipt to an outstanding client invoice</p>
            </div>
            <a href="index.php" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i> Back to Payments
            </a>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger py-2 small border-0 mb-4"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="card-custom p-4" style="max-width: 720px;">
            <form action="add.php" method="POST" id="paymentForm">
                <div class="row g-3">
                    
                    <div class="col-12">
                        <label class="form-label small fw-semibold">Select Invoice <span class="text-danger">*</span></label>
                        <select name="invoice_id" id="invoiceSelect" class="form-select" onchange="updateBalance()" required>
                            <option value="" data-balance="0">-- Select Pending Invoice --</option>
                            <?php foreach ($pending_invoices as $pi): ?>
                                <option value="<?= $pi['invoice_id'] ?>" data-balance="<?= $pi['balance_due'] ?>" <?= ($pre_invoice === $pi['invoice_id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($pi['invoice_number']) ?> &bull; <?= htmlspecialchars($pi['customer_name']) ?> (Balance Due: ₹<?= number_format($pi['balance_due'], 2) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Payment Date <span class="text-danger">*</span></label>
                        <input type="date" name="payment_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Amount Received (₹) <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" name="amount" id="amountInput" class="form-control fw-bold text-success" placeholder="0.00" required>
                        <div class="form-text small text-muted">Remaining Balance: <span id="balanceHint" class="fw-bold text-danger">₹0.00</span></div>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Payment Method</label>
                        <select name="payment_method" class="form-select">
                            <option value="Bank Transfer" selected>Bank Transfer (NEFT / RTGS / IMPS)</option>
                            <option value="UPI">UPI / GPay / PhonePe</option>
                            <option value="Cheque">Cheque / Demand Draft</option>
                            <option value="Cash">Cash</option>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Reference / UTR / Cheque No.</label>
                        <input type="text" name="reference_no" class="form-control" placeholder="e.g. UTR-9988221100">
                    </div>

                    <div class="col-12">
                        <label class="form-label small fw-semibold">Remarks / Internal Notes</label>
                        <textarea name="remarks" class="form-control" rows="2" placeholder="Optional notes..."></textarea>
                    </div>

                    <div class="col-12 mt-4 pt-3 border-top d-flex justify-content-end gap-2">
                        <a href="index.php" class="btn btn-light px-4">Cancel</a>
                        <button type="submit" class="btn btn-success px-4">Save Payment Receipt</button>
                    </div>

                </div>
            </form>
        </div>

    </div>
</div>

<script>
function updateBalance() {
    const select = document.getElementById('invoiceSelect');
    const selectedOption = select.options[select.selectedIndex];
    const balance = parseFloat(selectedOption.getAttribute('data-balance')) || 0;
    
    document.getElementById('balanceHint').innerText = '₹' + balance.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    
    if (balance > 0) {
        document.getElementById('amountInput').value = balance.toFixed(2);
    }
}

// Trigger on load if pre-selected
document.addEventListener('DOMContentLoaded', function() {
    updateBalance();
});
</script>

<?php include "../includes/footer.php"; ?>
