<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

require_once "../config/database.php";
require_once "../config/currency.php";

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) {
    header("Location: index.php");
    exit();
}

try {
    $stmt = $conn->prepare("
        SELECT i.*, c.customer_name, c.company_name, c.email, c.phone, c.address, c.city, c.state, c.country 
        FROM invoices i
        JOIN customers c ON i.customer_id = c.customer_id
        WHERE i.invoice_id = ?
    ");
    $stmt->execute([$id]);
    $invoice = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$invoice) {
        die("Invoice not found.");
    }

    $page_title = "Invoice " . $invoice['invoice_number'];
    $page_header = "Enterprise Invoice Document";

    $items_stmt = $conn->prepare("SELECT * FROM invoice_items WHERE invoice_id = ?");
    $items_stmt->execute([$id]);
    $items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);

    $pay_stmt = $conn->prepare("SELECT * FROM payments WHERE invoice_id = ? ORDER BY payment_id DESC");
    $pay_stmt->execute([$id]);
    $payments = $pay_stmt->fetchAll(PDO::FETCH_ASSOC);

    $balance_due = max(0, $invoice['total_amount'] - $invoice['paid_amount']);
    $is_overdue = ($invoice['status'] === 'Overdue') || ($invoice['due_date'] < date('Y-m-d') && $invoice['status'] !== 'Paid');
    $curr = $invoice['currency_code'] ?? 'INR';

    // QR Code API Generator for Instant Gateway Simulation
    $qr_data = "pay?inv=" . urlencode($invoice['invoice_number']) . "&amt=" . $balance_due . "&curr=" . $curr;
    $qr_url = "https://api.qrserver.com/v1/create-qr-code/?size=140x140&data=" . urlencode($qr_data);

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

include "../includes/header.php";
include "../includes/sidebar.php";
?>

<div class="content">
    <?php include "../includes/navbar.php"; ?>

    <div class="container-fluid p-4">

        <!-- Top Actions Bar -->
        <div class="d-flex justify-content-between align-items-center mb-4 d-print-none">
            <div>
                <h4 class="fw-bold mb-1"><i class="bi bi-file-earmark-text text-primary me-2"></i> Invoice <?= htmlspecialchars($invoice['invoice_number']) ?></h4>
                <p class="text-muted small mb-0">Currency: <strong><?= $curr ?></strong> (FX Rate: <?= $invoice['fx_rate'] ?>)</p>
            </div>

            <div class="d-flex gap-2">
                <button onclick="window.print()" class="btn btn-outline-dark btn-sm"><i class="bi bi-printer me-1"></i> Print Invoice</button>
                <?php if ($balance_due > 0): ?>
                    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#payOnlineModal">
                        <i class="bi bi-qr-code-scan me-1"></i> Pay Online QR
                    </button>
                    <a href="../payments/add.php?invoice_id=<?= $id ?>" class="btn btn-success btn-sm"><i class="bi bi-cash-stack me-1"></i> Record Manual Payment</a>
                <?php endif; ?>
                <a href="index.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i> Back</a>
            </div>
        </div>

        <?php if (isset($_GET['msg'])): ?>
            <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-4 d-print-none" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i> <?= htmlspecialchars($_GET['msg']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Printable Invoice Container -->
        <div class="card-custom p-5 bg-white shadow-sm" id="printableInvoice">
            
            <!-- Invoice Header -->
            <div class="row pb-4 mb-4 border-bottom align-items-center">
                <div class="col-6">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <i class="bi bi-globe-americas fs-2 text-indigo"></i>
                        <h3 class="fw-bold m-0 text-indigo">ARMS Enterprise Corp</h3>
                    </div>
                    <div class="text-dark  fw-bold small">Global Accounts Receivable & Treasury Operations</div>
                    <div class="text-dark fw-bold small">SWIFT / IBAN Billing Support: treasury@arms-global.com</div>
                </div>

                <div class="col-6 text-end">
                    <h2 class="fw-bold text-dark text-uppercase tracking-wide mb-1">INVOICE</h2>
                    <div class="fs-5 fw-bold text-indigo mb-2">#<?= htmlspecialchars($invoice['invoice_number']) ?></div>
                    
                    <div>
                        <?php if ($invoice['status'] === 'Paid'): ?>
                            <span class="badge bg-success px-3 py-2 fs-6">PAID IN FULL</span>
                        <?php elseif ($is_overdue): ?>
                            <span class="badge bg-danger px-3 py-2 fs-6">OVERDUE</span>
                        <?php elseif ($invoice['status'] === 'Partially Paid'): ?>
                            <span class="badge bg-primary px-3 py-2 fs-6">PARTIALLY PAID</span>
                        <?php else: ?>
                            <span class="badge bg-warning text-dark px-3 py-2 fs-6">PENDING PAYMENT</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Client & Dates Meta -->
            <div class="row mb-4">
                <div class="col-6">
                    <div class="text-uppercase small fw-bold text-dark mb-2">BILLED TO:</div>
                    <h5 class="fw-bold mb-1"><?= htmlspecialchars($invoice['customer_name']) ?></h5>
                    <?php if ($invoice['company_name']): ?>
                        <div class="fw-semibold text-secondary mb-1"><?= htmlspecialchars($invoice['company_name']) ?></div>
                    <?php endif; ?>
                    <div class="text-dark small mb-1"><?= nl2br(htmlspecialchars($invoice['address'] ?: '')) ?></div>
                    <div class="text-dark small"><?= htmlspecialchars($invoice['city']) ?> <?= htmlspecialchars($invoice['state']) ?>, <?= htmlspecialchars($invoice['country']) ?></div>
                    <div class="text-dark small">Email: <?= htmlspecialchars($invoice['email'] ?: 'N/A') ?> | Phone: <?= htmlspecialchars($invoice['phone'] ?: 'N/A') ?></div>
                </div>

                <div class="col-6 text-end">
                    <div class="bg-light p-3 rounded d-inline-block text-start border" style="min-width: 260px;">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-dark small">Invoice Date:</span>
                            <span class="fw-semibold small"><?= date('d M Y', strtotime($invoice['invoice_date'])) ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-dark small">Due Date:</span>
                            <span class="fw-bold small <?= $is_overdue ? 'text-danger' : '' ?>"><?= date('d M Y', strtotime($invoice['due_date'])) ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-dark small">Currency:</span>
                            <span class="fw-bold small"><?= $curr ?></span>
                        </div>
                        <div class="d-flex justify-content-between border-top pt-1 mt-1">
                            <span class="text-dark small">Balance Due:</span>
                            <span class="fw-bold text-danger"><?= format_currency($balance_due, $curr) ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Line Items Table -->
            <div class="table-responsive mb-4">
                <table class="table table-bordered align-middle">
                    <thead class="table-dark">
                        <tr>
                            <th>#</th>
                            <th>Description</th>
                            <th width="100" class="text-center">Qty</th>
                            <th width="140" class="text-end">Unit Price</th>
                            <th width="160" class="text-end">Total (<?= $curr ?>)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $idx => $item): ?>
                            <tr>
                                <td><?= $idx + 1 ?></td>
                                <td class="fw-semibold"><?= htmlspecialchars($item['item_name']) ?></td>
                                <td class="text-center"><?= $item['quantity'] ?></td>
                                <td class="text-end"><?= format_currency($item['unit_price'], $curr) ?></td>
                                <td class="text-end fw-bold"><?= format_currency($item['total'], $curr) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Totals & Payment QR Code Section -->
            <div class="row align-items-center mb-4">
                <div class="col-md-6">
                    <?php if ($balance_due > 0): ?>
                        <div class="p-3 bg-light rounded border d-flex align-items-center gap-3">
                            <img src="<?= $qr_url ?>" alt="Payment QR" class="img-thumbnail" style="width: 100px; height: 100px;">
                            <div>
                                <div class="fw-bold text-dark small"><i class="bi bi-qr-code me-1"></i> Scan & Pay Online</div>
                                <div class="text-dark small">Scan with any UPI / Banking App for instant settlement of <?= format_currency($balance_due, $curr) ?>.</div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="col-md-6">
                    <div class="bg-light p-3 rounded border">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Total Invoice Billed:</span>
                            <span class="fw-bold fs-5"><?= format_currency($invoice['total_amount'], $curr) ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-2 text-success">
                            <span>Amount Paid:</span>
                            <span class="fw-bold">- <?= format_currency($invoice['paid_amount'], $curr) ?></span>
                        </div>
                        <div class="d-flex justify-content-between border-top pt-2 mt-2">
                            <span class="fw-bold fs-6">Remaining Balance Due:</span>
                            <span class="fw-bold fs-5 text-danger"><?= format_currency($balance_due, $curr) ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($invoice['notes']): ?>
                <div class="mb-4 p-3 bg-light rounded border">
                    <div class="fw-semibold text-dark small mb-1"><i class="bi bi-info-circle me-1"></i> Wire Transfer Notes & SWIFT Details:</div>
                    <div class="text-muted small"><?= nl2br(htmlspecialchars($invoice['notes'])) ?></div>
                </div>
            <?php endif; ?>

            <!-- Payment Receipts -->
            <?php if (count($payments) > 0): ?>
                <div class="mt-4 pt-3 border-top">
                    <h6 class="fw-bold mb-3"><i class="bi bi-receipt text-success me-2"></i> Payment Receipts Applied</h6>
                    <table class="table table-sm table-bordered">
                        <thead class="table-light">
                            <tr>
                                <th>Receipt Date</th>
                                <th>Method</th>
                                <th>Reference / UTR #</th>
                                <th>Amount Paid</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($payments as $p): ?>
                                <tr>
                                    <td><?= date('d M Y', strtotime($p['payment_date'])) ?></td>
                                    <td><?= htmlspecialchars($p['payment_method']) ?></td>
                                    <td><?= htmlspecialchars($p['reference_no'] ?: 'N/A') ?></td>
                                    <td class="fw-bold text-success"><?= format_currency($p['amount'], $p['currency_code'] ?? $curr) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

        </div>

    </div>
</div>

<!-- Pay Online Gateway Simulator Modal -->
<div class="modal fade" id="payOnlineModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="bi bi-credit-card-2-front text-primary me-2"></i> Simulated Payment Gateway</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center p-4">
                <img src="<?= $qr_url ?>" alt="QR Code" class="img-fluid mb-3 rounded border p-2" style="max-width: 180px;">
                <h5 class="fw-bold text-dark mb-1">Invoice #<?= htmlspecialchars($invoice['invoice_number']) ?></h5>
                <div class="fs-4 fw-bold text-success mb-3"><?= format_currency($balance_due, $curr) ?></div>
                <p class="text-muted small mb-4">Scan QR code or click below to simulate instant online gateway clearance.</p>
                <a href="../payments/add.php?invoice_id=<?= $id ?>" class="btn btn-success btn-lg w-100 fw-bold">
                    <i class="bi bi-check-circle-fill me-2"></i> Confirm Instant Online Payment
                </a>
            </div>
        </div>
    </div>
</div>

<style>
@media print {
    body * { visibility: hidden; }
    #printableInvoice, #printableInvoice * { visibility: visible; }
    #printableInvoice { position: absolute; left: 0; top: 0; width: 100%; }
    .sidebar, .top-navbar, .d-print-none { display: none !important; }
}
</style>

<?php include "../includes/footer.php"; ?>
