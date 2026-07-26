<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

require_once "../config/database.php";

$page_title = "Payment Receipts";
$page_header = "Collections & Payment Receipts";

include "../includes/header.php";
include "../includes/sidebar.php";

try {
    $stmt = $conn->query("
        SELECT p.*, i.invoice_number, i.total_amount, c.customer_name, c.company_name 
        FROM payments p
        JOIN invoices i ON p.invoice_id = i.invoice_id
        JOIN customers c ON i.customer_id = c.customer_id
        ORDER BY p.payment_id DESC
    ");
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $tot_stmt = $conn->query("SELECT COALESCE(SUM(amount), 0) FROM payments");
    $total_collections = $tot_stmt->fetchColumn();

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}
?>

<div class="content">
    <?php include "../includes/navbar.php"; ?>

    <div class="container-fluid p-4">

        <!-- Top Header & Action -->
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
            <div>
                <h4 class="fw-bold mb-1"><i class="bi bi-cash-stack text-success me-2"></i> Cash Receipts & Collections</h4>
                <p class="text-muted small mb-0">Record and track payments received against outstanding invoices</p>
            </div>
            
            <a href="add.php" class="btn btn-success btn-sm px-3">
                <i class="bi bi-plus-circle me-1"></i> Record New Payment
            </a>
        </div>

        <?php if (isset($_GET['msg'])): ?>
            <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i> <?= htmlspecialchars($_GET['msg']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Total Collections Banner -->
        <div class="card-custom p-4 mb-4 bg-blue border-start border-4 border-success">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="text-muted small fw-semibold">TOTAL COLLECTIONS RECORDED</div>
                    <div class="fs-3 fw-bold text-success mt-1">₹<?= number_format($total_collections, 2) ?></div>
                </div>
                <div class="stat-icon bg-emerald-light">
                    <i class="bi bi-shield-check fs-2 text-success"></i>
                </div>
            </div>
        </div>

        <!-- Payments Table -->
        <div class="card-custom">
            <div class="table-responsive">
                <table class="table table-custom mb-0">
                    <thead>
                        <tr>
                            <th>Receipt ID</th>
                            <th>Payment Date</th>
                            <th>Invoice #</th>
                            <th>Customer Name</th>
                            <th>Payment Method</th>
                            <th>Reference / Cheque #</th>
                            <th>Amount Collected</th>
                            <th width="100" class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($payments) > 0): ?>
                            <?php foreach ($payments as $p): ?>
                                <tr>
                                    <td class="fw-bold">#REC-<?= str_pad($p['payment_id'], 4, '0', STR_PAD_LEFT) ?></td>
                                    <td class="small"><?= date('d M Y', strtotime($p['payment_date'])) ?></td>
                                    <td>
                                        <a href="../invoices/view.php?id=<?= $p['invoice_id'] ?>" class="fw-bold text-indigo text-decoration-none">
                                            <?= htmlspecialchars($p['invoice_number']) ?>
                                        </a>
                                    </td>
                                    <td>
                                        <div class="fw-semibold text-white"><?= htmlspecialchars($p['customer_name']) ?></div>
                                        <div class="text-muted small"><?= htmlspecialchars($p['company_name'] ?: 'Individual Client') ?></div>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark border"><?= htmlspecialchars($p['payment_method']) ?></span>
                                    </td>
                                    <td class="small font-monospace">
                                        <?= htmlspecialchars($p['reference_no'] ?: 'N/A') ?>
                                    </td>
                                    <td class="fw-bold text-success">
                                        +₹<?= number_format($p['amount'], 2) ?>
                                    </td>
                                    <td class="text-center">
                                        <a href="delete.php?id=<?= $p['payment_id'] ?>" class="btn btn-action btn-light text-danger" title="Void Payment" onclick="return confirm('Are you sure you want to delete/void this payment record?')">
                                            <i class="bi bi-trash-fill"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center text-muted py-5">
                                    <i class="bi bi-cash fs-1 text-muted d-block mb-2"></i>
                                    No payment receipts recorded yet.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<?php include "../includes/footer.php"; ?>
