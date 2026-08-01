<?php
session_start();

if (!isset($_SESSION["customer_id"])) {
    header("Location: login.php");
    exit();
}

require_once "../config/database.php";
require_once "../config/currency.php";

$customer_id = (int)$_SESSION["customer_id"];
$invoice_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($invoice_id <= 0) {
    header("Location: customer_invoice.php?error=Invalid+Invoice+ID");
    exit();
}

try {
    // Fetch Invoice & Customer & Entity Details
    $stmt = $conn->prepare("
        SELECT i.*, 
               c.customer_name, c.company_name, c.email as customer_email, c.phone as customer_phone, c.address as customer_address, c.city as customer_city, c.state as customer_state, c.country as customer_country,
               e.entity_name, e.country as entity_country, e.tax_id as entity_tax_id
        FROM invoices i
        INNER JOIN customers c ON i.customer_id = c.customer_id
        LEFT JOIN entities e ON i.entity_id = e.entity_id
        WHERE i.invoice_id = ? AND i.customer_id = ?
        LIMIT 1
    ");
    $stmt->execute([$invoice_id, $customer_id]);
    $invoice = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$invoice) {
        header("Location: customer_invoice.php?error=Invoice+not+found+or+access+denied");
        exit();
    }

    // Fetch Line Items
    $items_stmt = $conn->prepare("SELECT * FROM invoice_items WHERE invoice_id = ?");
    $items_stmt->execute([$invoice_id]);
    $line_items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch Payment History for this invoice
    $pay_stmt = $conn->prepare("SELECT * FROM payments WHERE invoice_id = ? ORDER BY payment_date DESC");
    $pay_stmt->execute([$invoice_id]);
    $payments = $pay_stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

$balance_due = $invoice['total_amount'] - $invoice['paid_amount'];
$page_title = "Invoice " . htmlspecialchars($invoice['invoice_number']);
$page_header = "Invoice Details";

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

        <!-- Top Action Controls (Hidden on Print) -->
        <div class="d-flex justify-content-between align-items-center mb-4 no-print">
            <a href="customer_invoice.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Back to Invoices
            </a>

            <div class="d-flex gap-2">
                <button onclick="window.print()" class="btn btn-outline-light">
                    <i class="bi bi-printer-fill me-1"></i> Print Invoice
                </button>
                <?php if ($balance_due > 0): ?>
                    <a href="customer_payments.php?invoice_id=<?= $invoice['invoice_id'] ?>" class="btn btn-success">
                        <i class="bi bi-credit-card-fill me-1"></i> Make Payment (<?= format_currency($balance_due, $invoice['currency_code']) ?>)
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Invoice Printable Card -->
        <div class="card-custom p-4 p-md-5">

            <!-- Invoice Header -->
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-start border-bottom border-secondary border-opacity-25 pb-4 mb-4 gap-3">
                <div>
                    <h3 class="fw-bold text-white mb-1"><i class="bi bi-shield-check text-emerald me-2"></i> <?= htmlspecialchars($invoice['entity_name'] ?? 'ARMS Enterprise System') ?></h3>
                    <p class="text-muted small mb-0"><?= htmlspecialchars($invoice['entity_country'] ?? '') ?> &bull; Tax ID: <?= htmlspecialchars($invoice['entity_tax_id'] ?? 'N/A') ?></p>
                </div>
                <div class="text-md-end">
                    <h2 class="text-emerald fw-bold mb-1"><?= htmlspecialchars($invoice['invoice_number']) ?></h2>
                    <?php
                    $status_badge = 'bg-primary';
                    if ($invoice['status'] === 'Paid') $status_badge = 'bg-success';
                    elseif ($invoice['status'] === 'Overdue') $status_badge = 'bg-danger';
                    elseif ($invoice['status'] === 'Partially Paid') $status_badge = 'bg-warning text-dark';
                    ?>
                    <span class="badge <?= $status_badge ?> fs-6 px-3 py-2">
                        <?= htmlspecialchars($invoice['status']) ?>
                    </span>
                </div>
            </div>

            <!-- Billing Addresses & Dates -->
            <div class="row g-4 mb-4">
                <div class="col-md-6">
                    <h6 class="text-muted text-uppercase fw-bold small">Billed To (Customer):</h6>
                    <h5 class="text-white fw-bold mb-1"><?= htmlspecialchars($invoice['customer_name']) ?></h5>
                    <p class="text-muted small mb-1"><?= htmlspecialchars($invoice['company_name'] ?: 'Individual Client') ?></p>
                    <p class="text-muted small mb-1"><?= htmlspecialchars($invoice['customer_address'] ?? '') ?></p>
                    <p class="text-muted small mb-1">
                        <?= htmlspecialchars($invoice['customer_city'] ?? '') ?><?= !empty($invoice['customer_state']) ? ', ' . htmlspecialchars($invoice['customer_state']) : '' ?> <?= htmlspecialchars($invoice['customer_country'] ?? '') ?>
                    </p>
                    <p class="text-muted small mb-0"><i class="bi bi-envelope me-1"></i> <?= htmlspecialchars($invoice['customer_email'] ?? '') ?></p>
                </div>
                <div class="col-md-6 text-md-end">
                    <div class="bg-dark bg-opacity-50 p-3 rounded border border-secondary border-opacity-25 d-inline-block text-start text-md-end" style="min-width: 250px;">
                        <div class="mb-2">
                            <span class="text-muted small d-block">Invoice Date:</span>
                            <strong class="text-white"><?= date('d M Y', strtotime($invoice['invoice_date'])) ?></strong>
                        </div>
                        <div class="mb-2">
                            <span class="text-muted small d-block">Payment Due Date:</span>
                            <strong class="text-white"><?= date('d M Y', strtotime($invoice['due_date'])) ?></strong>
                        </div>
                        <div>
                            <span class="text-muted small d-block">Billing Currency:</span>
                            <strong class="text-emerald"><?= htmlspecialchars($invoice['currency_code']) ?></strong>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Line Items Table -->
            <div class="table-responsive mb-4">
                <table class="table table-custom align-middle mb-0">
                    <thead>
                        <tr>
                            <th width="50">#</th>
                            <th>Description / Service</th>
                            <th class="text-center" width="100">Qty</th>
                            <th class="text-end" width="150">Unit Price</th>
                            <th class="text-end" width="160">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($line_items) > 0): ?>
                            <?php foreach ($line_items as $index => $item): ?>
                                <tr>
                                    <td><?= $index + 1 ?></td>
                                    <td class="fw-semibold text-white"><?= htmlspecialchars($item['item_name']) ?></td>
                                    <td class="text-center"><?= (int)$item['quantity'] ?></td>
                                    <td class="text-end"><?= format_currency($item['unit_price'], $invoice['currency_code']) ?></td>
                                    <td class="text-end fw-bold text-white"><?= format_currency($item['total'], $invoice['currency_code']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td>1</td>
                                <td class="fw-semibold text-white"><?= htmlspecialchars($invoice['notes'] ?: 'Services Rendered') ?></td>
                                <td class="text-center">1</td>
                                <td class="text-end"><?= format_currency($invoice['subtotal'] > 0 ? $invoice['subtotal'] : $invoice['total_amount'], $invoice['currency_code']) ?></td>
                                <td class="text-end fw-bold text-white"><?= format_currency($invoice['total_amount'], $invoice['currency_code']) ?></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Invoice Totals Summary -->
            <div class="row justify-content-end mb-4">
                <div class="col-md-5 col-lg-4">
                    <div class="bg-dark bg-opacity-50 p-3 rounded border border-secondary border-opacity-25">
                        <div class="d-flex justify-content-between py-1">
                            <span class="text-muted small">Subtotal:</span>
                            <span class="text-white fw-semibold"><?= format_currency($invoice['subtotal'] > 0 ? $invoice['subtotal'] : $invoice['total_amount'], $invoice['currency_code']) ?></span>
                        </div>
                        <?php if ($invoice['tax_amount'] > 0): ?>
                            <div class="d-flex justify-content-between py-1">
                                <span class="text-muted small">Tax Amount:</span>
                                <span class="text-white fw-semibold"><?= format_currency($invoice['tax_amount'], $invoice['currency_code']) ?></span>
                            </div>
                        <?php endif; ?>
                        <?php if ($invoice['late_fee'] > 0): ?>
                            <div class="d-flex justify-content-between py-1 text-danger">
                                <span class="small">Late Fee Charges:</span>
                                <span class="fw-semibold"><?= format_currency($invoice['late_fee'], $invoice['currency_code']) ?></span>
                            </div>
                        <?php endif; ?>
                        <div class="d-flex justify-content-between py-2 border-top border-secondary border-opacity-25 mt-2">
                            <span class="fw-bold text-white fs-6">Grand Total:</span>
                            <span class="fw-bold text-white fs-6"><?= format_currency($invoice['total_amount'], $invoice['currency_code']) ?></span>
                        </div>
                        <div class="d-flex justify-content-between py-1 text-success">
                            <span class="small">Amount Paid:</span>
                            <span class="fw-bold">- <?= format_currency($invoice['paid_amount'], $invoice['currency_code']) ?></span>
                        </div>
                        <div class="d-flex justify-content-between py-2 border-top border-secondary border-opacity-25 mt-1">
                            <span class="fw-bold text-danger fs-5">Balance Due:</span>
                            <span class="fw-bold text-danger fs-5"><?= format_currency($balance_due, $invoice['currency_code']) ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Payment History Section -->
            <?php if (count($payments) > 0): ?>
                <div class="mt-4 pt-3 border-top border-secondary border-opacity-25">
                    <h6 class="fw-bold text-white mb-3"><i class="bi bi-credit-card-2-back-fill text-success me-2"></i> Payment Transactions</h6>
                    <div class="table-responsive">
                        <table class="table table-sm table-custom mb-0">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Method</th>
                                    <th>Reference / Transaction No</th>
                                    <th>Remarks</th>
                                    <th class="text-end">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($payments as $pay): ?>
                                    <tr>
                                        <td><?= date('d M Y', strtotime($pay['payment_date'])) ?></td>
                                        <td><?= htmlspecialchars($pay['payment_method']) ?></td>
                                        <td class="fw-mono"><?= htmlspecialchars($pay['reference_no'] ?: 'N/A') ?></td>
                                        <td class="text-muted small"><?= htmlspecialchars($pay['remarks'] ?: '-') ?></td>
                                        <td class="text-end text-success fw-bold"><?= format_currency($pay['amount'], $pay['currency_code']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Notes & Footer Terms -->
            <?php if (!empty($invoice['notes'])): ?>
                <div class="mt-4 p-3 bg-dark bg-opacity-25 rounded border border-secondary border-opacity-25">
                    <h6 class="fw-bold text-white small mb-1"><i class="bi bi-info-circle me-1 text-primary"></i> Notes / Payment Terms:</h6>
                    <p class="text-muted small mb-0"><?= nl2br(htmlspecialchars($invoice['notes'])) ?></p>
                </div>
            <?php endif; ?>

        </div>
    </div>
</div>

<?php include "../includes/footer.php"; ?>
