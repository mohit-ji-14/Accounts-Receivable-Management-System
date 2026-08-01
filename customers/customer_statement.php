<?php
session_start();

if (!isset($_SESSION["customer_id"])) {
    header("Location: login.php");
    exit();
}

require_once "../config/database.php";
require_once "../config/currency.php";

$customer_id = (int)$_SESSION["customer_id"];

// Default date range: current year to date
$start_date = trim($_GET['start_date'] ?? date('Y-01-01'));
$end_date = trim($_GET['end_date'] ?? date('Y-m-d'));

try {
    // 1. Fetch Customer Details
    $cust_stmt = $conn->prepare("
        SELECT c.*, e.entity_name
        FROM customers c
        LEFT JOIN entities e ON c.entity_id = e.entity_id
        WHERE c.customer_id = ?
        LIMIT 1
    ");
    $cust_stmt->execute([$customer_id]);
    $customer = $cust_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$customer) {
        die("Customer record not found.");
    }

    $currency = $customer['currency_code'] ?? 'INR';

    // 2. Calculate Opening Balance prior to start_date
    $op_inv_stmt = $conn->prepare("
        SELECT COALESCE(SUM(total_amount), 0)
        FROM invoices
        WHERE customer_id = ? AND invoice_date < ?
    ");
    $op_inv_stmt->execute([$customer_id, $start_date]);
    $op_invoiced = (float)$op_inv_stmt->fetchColumn();

    $op_pay_stmt = $conn->prepare("
        SELECT COALESCE(SUM(p.amount), 0)
        FROM payments p
        INNER JOIN invoices i ON p.invoice_id = i.invoice_id
        WHERE i.customer_id = ? AND p.payment_date < ?
    ");
    $op_pay_stmt->execute([$customer_id, $start_date]);
    $op_paid = (float)$op_pay_stmt->fetchColumn();

    $opening_balance = $op_invoiced - $op_paid;

    // 3. Fetch Invoices in Date Range
    $inv_stmt = $conn->prepare("
        SELECT invoice_id, invoice_number as ref_no, invoice_date as txn_date, 'Invoice' as txn_type, notes as description, total_amount as debit, 0.00 as credit
        FROM invoices
        WHERE customer_id = ? AND invoice_date BETWEEN ? AND ?
    ");
    $inv_stmt->execute([$customer_id, $start_date, $end_date]);
    $invoices_txns = $inv_stmt->fetchAll(PDO::FETCH_ASSOC);

    // 4. Fetch Payments in Date Range
    $pay_stmt = $conn->prepare("
        SELECT p.payment_id, i.invoice_number as ref_no, p.payment_date as txn_date, 'Payment' as txn_type, CONCAT('Payment via ', p.payment_method, IF(p.reference_no != '', CONCAT(' (', p.reference_no, ')'), '')) as description, 0.00 as debit, p.amount as credit
        FROM payments p
        INNER JOIN invoices i ON p.invoice_id = i.invoice_id
        WHERE i.customer_id = ? AND p.payment_date BETWEEN ? AND ?
    ");
    $pay_stmt->execute([$customer_id, $start_date, $end_date]);
    $payments_txns = $pay_stmt->fetchAll(PDO::FETCH_ASSOC);

    // 5. Merge and Sort Transactions Chronologically
    $transactions = array_merge($invoices_txns, $payments_txns);
    usort($transactions, function($a, $b) {
        $t1 = strtotime($a['txn_date']);
        $t2 = strtotime($b['txn_date']);
        if ($t1 === $t2) {
            return ($a['txn_type'] === 'Invoice') ? -1 : 1;
        }
        return $t1 - $t2;
    });

    // 6. Calculate Totals & Running Balance
    $period_debit = 0;
    $period_credit = 0;
    $running_balance = $opening_balance;

    foreach ($transactions as &$t) {
        $period_debit += $t['debit'];
        $period_credit += $t['credit'];
        $running_balance += ($t['debit'] - $t['credit']);
        $t['balance'] = $running_balance;
    }
    unset($t);

    $closing_balance = $running_balance;

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

$page_title = "Account Statement";
$page_header = "Statement of Account";

include "../includes/header.php";
include "customers_sidebar.php";
?>

<style>
@media print {
    .sidebar, .top-navbar, .no-print { display: none !important; }
    .content { margin-left: 0 !important; width: 100% !important; padding: 0 !important; }
    .card-custom { background: #fff !important; color: #000 !important; border: none !important; box-shadow: none !important; }
    .text-white { color: #000 !important; }
    .table-custom { color: #000 !important; }
    .table-custom th, .table-custom td { border-color: #ddd !important; color: #000 !important; }
}
</style>

<div class="content">
    <?php include "customer_navbar.php"; ?>

    <div class="container-fluid p-4">

        <!-- Controls Bar -->
        <div class="card-custom mb-4 p-3 no-print">
            <form method="GET" action="customer_statement.php" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label text-muted small fw-semibold">Start Date</label>
                    <input type="date" name="start_date" class="form-control form-control-sm bg-dark text-white border-secondary" value="<?= htmlspecialchars($start_date) ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label text-muted small fw-semibold">End Date</label>
                    <input type="date" name="end_date" class="form-control form-control-sm bg-dark text-white border-secondary" value="<?= htmlspecialchars($end_date) ?>" required>
                </div>
                <div class="col-md-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm px-3 flex-grow-1">
                        <i class="bi bi-filter me-1"></i> Generate Statement
                    </button>
                    <button type="button" onclick="window.print()" class="btn btn-outline-light btn-sm px-3">
                        <i class="bi bi-printer-fill me-1"></i> Print
                    </button>
                </div>
            </form>
        </div>

        <!-- Printable Statement Card -->
        <div class="card-custom p-4 p-md-5">

            <!-- Statement Header -->
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-start border-bottom border-secondary border-opacity-25 pb-4 mb-4 gap-3">
                <div>
                    <h3 class="fw-bold text-white mb-1"><i class="bi bi-file-earmark-spreadsheet-fill text-indigo me-2"></i> STATEMENT OF ACCOUNT</h3>
                    <p class="text-muted small mb-0"><?= htmlspecialchars($customer['entity_name'] ?? 'ARMS Enterprise HQ') ?></p>
                </div>
                <div class="text-md-end">
                    <span class="badge bg-indigo-light text-indigo px-3 py-2 fw-semibold">
                        Statement Period: <?= date('d M Y', strtotime($start_date)) ?> to <?= date('d M Y', strtotime($end_date)) ?>
                    </span>
                </div>
            </div>

            <!-- Customer & Account Info Summary -->
            <div class="row g-4 mb-4">
                <div class="col-md-6">
                    <h6 class="text-muted text-uppercase fw-bold small">Account Holder:</h6>
                    <h5 class="text-white fw-bold mb-1"><?= htmlspecialchars($customer['customer_name']) ?></h5>
                    <p class="text-muted small mb-1"><?= htmlspecialchars($customer['company_name'] ?: 'Individual Account') ?></p>
                    <p class="text-muted small mb-1"><?= htmlspecialchars($customer['address'] ?? '') ?>, <?= htmlspecialchars($customer['city'] ?? '') ?></p>
                    <p class="text-muted small mb-0"><i class="bi bi-envelope me-1"></i> <?= htmlspecialchars($customer['email']) ?></p>
                </div>
                <div class="col-md-6 text-md-end">
                    <div class="bg-dark bg-opacity-50 p-3 rounded border border-secondary border-opacity-25 d-inline-block text-start text-md-end" style="min-width: 260px;">
                        <div class="mb-1">
                            <span class="text-muted small">Account Currency:</span>
                            <strong class="text-white ms-2"><?= htmlspecialchars($currency) ?></strong>
                        </div>
                        <div class="mb-1">
                            <span class="text-muted small">Approved Credit Limit:</span>
                            <strong class="text-emerald ms-2"><?= format_currency($customer['credit_limit'], $currency) ?></strong>
                        </div>
                        <div>
                            <span class="text-muted small">Payment Terms:</span>
                            <strong class="text-white ms-2"><?= htmlspecialchars($customer['payment_terms'] ?? 'Net 30') ?></strong>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Statement Metric Cards -->
            <div class="row g-3 mb-4">
                <div class="col-md-3 col-6">
                    <div class="bg-dark p-3 rounded border border-secondary border-opacity-25">
                        <span class="text-muted small d-block mb-1">Opening Balance</span>
                        <h5 class="fw-bold text-white mb-0"><?= format_currency($opening_balance, $currency) ?></h5>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="bg-dark p-3 rounded border border-secondary border-opacity-25">
                        <span class="text-muted small d-block mb-1">Invoiced (Billed)</span>
                        <h5 class="fw-bold text-indigo mb-0">+ <?= format_currency($period_debit, $currency) ?></h5>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="bg-dark p-3 rounded border border-secondary border-opacity-25">
                        <span class="text-muted small d-block mb-1">Payments Settled</span>
                        <h5 class="fw-bold text-success mb-0">- <?= format_currency($period_credit, $currency) ?></h5>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="bg-dark p-3 rounded border border-secondary border-opacity-25">
                        <span class="text-muted small d-block mb-1">Closing Outstanding</span>
                        <h5 class="fw-bold text-danger mb-0"><?= format_currency($closing_balance, $currency) ?></h5>
                    </div>
                </div>
            </div>

            <!-- Detailed Ledger Table -->
            <div class="table-responsive">
                <table class="table table-custom align-middle mb-0">
                    <thead>
                        <tr>
                            <th width="110">Date</th>
                            <th width="100">Type</th>
                            <th width="140">Ref / Inv No</th>
                            <th>Description / Remarks</th>
                            <th class="text-end" width="130">Billed (+)</th>
                            <th class="text-end" width="130">Paid (-)</th>
                            <th class="text-end" width="140">Balance</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Opening Balance Row -->
                        <tr class="bg-dark bg-opacity-25">
                            <td><?= date('d M Y', strtotime($start_date)) ?></td>
                            <td><span class="badge bg-secondary">System</span></td>
                            <td class="fw-mono text-muted">OPENING</td>
                            <td class="text-muted italic">Opening balance prior to period</td>
                            <td class="text-end">-</td>
                            <td class="text-end">-</td>
                            <td class="text-end fw-bold text-white"><?= format_currency($opening_balance, $currency) ?></td>
                        </tr>

                        <?php if (count($transactions) > 0): ?>
                            <?php foreach ($transactions as $t): ?>
                                <tr>
                                    <td><?= date('d M Y', strtotime($t['txn_date'])) ?></td>
                                    <td>
                                        <?php if ($t['txn_type'] === 'Invoice'): ?>
                                            <span class="badge bg-primary-subtle text-primary border border-primary">Invoice</span>
                                        <?php else: ?>
                                            <span class="badge bg-success-subtle text-success border border-success">Payment</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="fw-bold text-white"><?= htmlspecialchars($t['ref_no']) ?></td>
                                    <td class="text-muted small"><?= htmlspecialchars($t['description'] ?: 'Transaction item') ?></td>
                                    <td class="text-end text-indigo fw-semibold">
                                        <?= ($t['debit'] > 0) ? format_currency($t['debit'], $currency) : '-' ?>
                                    </td>
                                    <td class="text-end text-success fw-semibold">
                                        <?= ($t['credit'] > 0) ? format_currency($t['credit'], $currency) : '-' ?>
                                    </td>
                                    <td class="text-end fw-bold <?= ($t['balance'] > 0) ? 'text-danger' : 'text-white' ?>">
                                        <?= format_currency($t['balance'], $currency) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">No new transactions recorded during this period.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                    <tfoot>
                        <tr class="border-top border-secondary fw-bold">
                            <td colspan="4" class="text-end text-white">Period Totals & Final Outstanding:</td>
                            <td class="text-end text-indigo"><?= format_currency($period_debit, $currency) ?></td>
                            <td class="text-end text-success"><?= format_currency($period_credit, $currency) ?></td>
                            <td class="text-end text-danger fs-6"><?= format_currency($closing_balance, $currency) ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

        </div>

    </div>
</div>

<?php include "../includes/footer.php"; ?>
