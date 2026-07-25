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

try {
    // Fetch Customer Profile
    $stmt = $conn->prepare("SELECT * FROM customers WHERE customer_id = ?");
    $stmt->execute([$id]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$customer) {
        die("Customer not found.");
    }

    $page_title = $customer['customer_name'] . " - Ledger";
    $page_header = "Client Account Ledger";

    // Fetch Invoices for this customer
    $inv_stmt = $conn->prepare("
        SELECT * FROM invoices 
        WHERE customer_id = ? 
        ORDER BY invoice_id DESC
    ");
    $inv_stmt->execute([$id]);
    $invoices = $inv_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch Payments for this customer
    $pay_stmt = $conn->prepare("
        SELECT p.*, i.invoice_number 
        FROM payments p
        JOIN invoices i ON p.invoice_id = i.invoice_id
        WHERE i.customer_id = ?
        ORDER BY p.payment_id DESC
    ");
    $pay_stmt->execute([$id]);
    $payments = $pay_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate totals
    $total_billed = 0;
    $total_paid = 0;
    foreach ($invoices as $inv) {
        $total_billed += $inv['total_amount'];
        $total_paid += $inv['paid_amount'];
    }
    $balance_due = max(0, $total_billed - $total_paid);

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

include "../includes/header.php";
include "../includes/sidebar.php";
?>

<div class="content">
    <?php include "../includes/navbar.php"; ?>

    <div class="container-fluid p-4">

        <!-- Top Actions -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="fw-bold mb-1"><i class="bi bi-journal-text text-primary me-2"></i> Client Ledger: <?= htmlspecialchars($customer['customer_name']) ?></h4>
                <p class="text-muted small mb-0"><?= htmlspecialchars($customer['company_name'] ?: 'Individual Client') ?> &bull; Added on <?= date('d M Y', strtotime($customer['created_at'])) ?></p>
            </div>
            <div class="d-flex gap-2">
                <a href="edit.php?id=<?= $id ?>" class="btn btn-warning btn-sm px-3"><i class="bi bi-pencil me-1"></i> Edit Client</a>
                <a href="index.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i> Back</a>
            </div>
        </div>

        <!-- Client Summary Header Cards -->
        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="card-custom p-3 text-center">
                    <div class="small text-muted fw-semibold">TOTAL BILLED</div>
                    <div class="fs-4 fw-bold text-dark mt-1">₹<?= number_format($total_billed, 2) ?></div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card-custom p-3 text-center">
                    <div class="small text-muted fw-semibold">TOTAL COLLECTED</div>
                    <div class="fs-4 fw-bold text-success mt-1">₹<?= number_format($total_paid, 2) ?></div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card-custom p-3 text-center">
                    <div class="small text-muted fw-semibold">CURRENT OUTSTANDING</div>
                    <div class="fs-4 fw-bold <?= $balance_due > 0 ? 'text-danger' : 'text-success' ?> mt-1">₹<?= number_format($balance_due, 2) ?></div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card-custom p-3 text-center">
                    <div class="small text-muted fw-semibold">CREDIT LIMIT</div>
                    <div class="fs-4 fw-bold text-indigo mt-1">₹<?= number_format($customer['credit_limit'] ?? 50000, 2) ?></div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <!-- Client Details Sidebar -->
            <div class="col-lg-4">
                <div class="card-custom p-4">
                    <h6 class="fw-bold border-bottom pb-2 mb-3"><i class="bi bi-info-circle text-primary me-2"></i> Contact Details</h6>
                    <div class="mb-2"><strong>Email:</strong> <?= htmlspecialchars($customer['email'] ?: 'N/A') ?></div>
                    <div class="mb-2"><strong>Phone:</strong> <?= htmlspecialchars($customer['phone'] ?: 'N/A') ?></div>
                    <div class="mb-2"><strong>Payment Terms:</strong> <span class="badge bg-light text-dark border"><?= htmlspecialchars($customer['payment_terms'] ?? 'Net 30') ?></span></div>
                    <div class="mb-2"><strong>Address:</strong></div>
                    <div class="text-muted small bg-light p-2 rounded">
                        <?= nl2br(htmlspecialchars($customer['address'] ?: 'No address specified')) ?><br>
                        <?= htmlspecialchars($customer['city']) ?> <?= htmlspecialchars($customer['state']) ?>, <?= htmlspecialchars($customer['country']) ?>
                    </div>
                </div>
            </div>

            <!-- Invoices & Payments Tabs -->
            <div class="col-lg-8">
                <div class="card-custom">
                    <div class="p-3 border-bottom d-flex justify-content-between align-items-center">
                        <h6 class="fw-bold mb-0"><i class="bi bi-file-earmark-text me-2"></i> Invoices History (<?= count($invoices) ?>)</h6>
                        <a href="../invoices/add.php?customer_id=<?= $id ?>" class="btn btn-primary btn-sm"><i class="bi bi-plus-circle me-1"></i> New Invoice</a>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-custom mb-0">
                            <thead>
                                <tr>
                                    <th>Invoice #</th>
                                    <th>Date</th>
                                    <th>Due Date</th>
                                    <th>Total</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($invoices) > 0): ?>
                                    <?php foreach ($invoices as $inv): 
                                        $badge_class = 'badge-pending';
                                        if ($inv['status'] === 'Paid') $badge_class = 'badge-paid';
                                        elseif ($inv['status'] === 'Partially Paid') $badge_class = 'badge-partial';
                                        elseif ($inv['status'] === 'Overdue' || ($inv['due_date'] < date('Y-m-d') && $inv['status'] !== 'Paid')) $badge_class = 'badge-overdue';
                                    ?>
                                        <tr>
                                            <td class="fw-bold text-indigo"><?= htmlspecialchars($inv['invoice_number']) ?></td>
                                            <td class="small"><?= date('d M Y', strtotime($inv['invoice_date'])) ?></td>
                                            <td class="small"><?= date('d M Y', strtotime($inv['due_date'])) ?></td>
                                            <td class="fw-bold">₹<?= number_format($inv['total_amount'], 2) ?></td>
                                            <td><span class="badge-status <?= $badge_class ?>"><?= htmlspecialchars($inv['status']) ?></span></td>
                                            <td>
                                                <a href="../invoices/view.php?id=<?= $inv['invoice_id'] ?>" class="btn btn-action btn-light text-primary"><i class="bi bi-eye"></i></a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center text-muted py-4">No invoices billed to this client yet.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Payments Received Table -->
                <div class="card-custom mt-4">
                    <div class="p-3 border-bottom">
                        <h6 class="fw-bold mb-0"><i class="bi bi-cash-stack text-success me-2"></i> Payment History (<?= count($payments) ?>)</h6>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-custom mb-0">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Invoice Ref</th>
                                    <th>Method</th>
                                    <th>Ref #</th>
                                    <th>Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($payments) > 0): ?>
                                    <?php foreach ($payments as $p): ?>
                                        <tr>
                                            <td class="small"><?= date('d M Y', strtotime($p['payment_date'])) ?></td>
                                            <td class="fw-semibold text-indigo"><?= htmlspecialchars($p['invoice_number']) ?></td>
                                            <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($p['payment_method']) ?></span></td>
                                            <td class="small"><?= htmlspecialchars($p['reference_no'] ?: 'N/A') ?></td>
                                            <td class="fw-bold text-success">+₹<?= number_format($p['amount'], 2) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="text-center text-muted py-4">No payments recorded yet.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>

    </div>
</div>

<?php include "../includes/footer.php"; ?>
