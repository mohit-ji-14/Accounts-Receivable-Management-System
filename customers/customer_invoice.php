<?php
session_start();

if (!isset($_SESSION["customer_id"])) {
    header("Location: login.php");
    exit();
}

require_once "../config/database.php";
require_once "../config/currency.php";

$customer_id = (int)$_SESSION["customer_id"];

$status_filter = trim($_GET['status'] ?? 'all');
$search = trim($_GET['search'] ?? '');

// Build query dynamically based on filter and search
$sql = "
SELECT invoice_id, invoice_number, invoice_date, due_date, total_amount, paid_amount, currency_code, status
FROM invoices
WHERE customer_id = ?
";
$params = [$customer_id];

if ($status_filter !== 'all' && !empty($status_filter)) {
    $sql .= " AND LOWER(status) = LOWER(?)";
    $params[] = $status_filter;
}

if (!empty($search)) {
    $sql .= " AND (invoice_number LIKE ? OR notes LIKE ?)";
    $term = '%' . $search . '%';
    $params[] = $term;
    $params[] = $term;
}

$sql .= " ORDER BY invoice_date DESC";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate totals
$total_billed = 0;
$total_paid = 0;
$total_outstanding = 0;

foreach ($invoices as $inv) {
    $total_billed += $inv['total_amount'];
    $total_paid += $inv['paid_amount'];
    $total_outstanding += ($inv['total_amount'] - $inv['paid_amount']);
}

$page_title = "My Invoices";
$page_header = "My Invoices & Statements";

include "../includes/header.php";
include "customers_sidebar.php";
?>

<div class="content">
    <?php include "customer_navbar.php"; ?>

    <div class="container-fluid p-4">
        <!-- Summary Cards -->
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stat-title">Total Billed</div>
                            <div class="stat-value text-indigo"><?= format_currency($total_billed, $invoices[0]['currency_code'] ?? 'INR') ?></div>
                        </div>
                        <div class="stat-icon bg-indigo-light text-indigo">
                            <i class="bi bi-file-earmark-text-fill fs-4"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stat-title">Total Paid</div>
                            <div class="stat-value text-success"><?= format_currency($total_paid, $invoices[0]['currency_code'] ?? 'INR') ?></div>
                        </div>
                        <div class="stat-icon bg-emerald-light text-success">
                            <i class="bi bi-check-circle-fill fs-4"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stat-title">Outstanding Balance</div>
                            <div class="stat-value text-danger"><?= format_currency($total_outstanding, $invoices[0]['currency_code'] ?? 'INR') ?></div>
                        </div>
                        <div class="stat-icon bg-rose-light text-danger">
                            <i class="bi bi-exclamation-triangle-fill fs-4"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter & Search Controls -->
        <div class="card-custom mb-4 p-3">
            <form method="GET" action="customer_invoice.php" class="row g-3 align-items-center">
                <div class="col-md-6">
                    <div class="btn-group w-100" role="group">
                        <a href="customer_invoice.php?status=all" class="btn btn-sm <?= ($status_filter === 'all') ? 'btn-primary' : 'btn-outline-secondary' ?>">All Invoices</a>
                        <a href="customer_invoice.php?status=pending" class="btn btn-sm <?= ($status_filter === 'pending') ? 'btn-primary' : 'btn-outline-secondary' ?>">Pending</a>
                        <a href="customer_invoice.php?status=overdue" class="btn btn-sm <?= ($status_filter === 'overdue') ? 'btn-primary' : 'btn-outline-secondary' ?>">Overdue</a>
                        <a href="customer_invoice.php?status=paid" class="btn btn-sm <?= ($status_filter === 'paid') ? 'btn-primary' : 'btn-outline-secondary' ?>">Paid</a>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="input-group input-group-sm">
                        <input type="text" name="search" class="form-control" placeholder="Search invoice number..." value="<?= htmlspecialchars($search) ?>">
                        <button class="btn btn-primary" type="submit"><i class="bi bi-search"></i> Search</button>
                        <?php if (!empty($search) || $status_filter !== 'all'): ?>
                            <a href="customer_invoice.php" class="btn btn-outline-secondary" title="Reset Filters"><i class="bi bi-x-circle"></i></a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>

        <!-- Invoices List Table -->
        <div class="card-custom">
            <div class="p-3 border-bottom border-secondary border-opacity-25 d-flex justify-content-between align-items-center">
                <h5 class="fw-bold text-white mb-0">
                    <i class="bi bi-receipt text-indigo me-2"></i> Invoice Records
                </h5>
                <span class="badge bg-light text-dark"><?= count($invoices) ?> Records Found</span>
            </div>

            <div class="table-responsive">
                <table class="table table-custom mb-0">
                    <thead>
                        <tr>
                            <th>Invoice No</th>
                            <th>Invoice Date</th>
                            <th>Due Date</th>
                            <th>Total Amount</th>
                            <th>Amount Paid</th>
                            <th>Balance Due</th>
                            <th>Status</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($invoices) > 0): ?>
                            <?php foreach ($invoices as $row): 
                                $balance = $row['total_amount'] - $row['paid_amount'];
                                $badge_class = 'bg-primary';
                                if ($row['status'] === 'Paid') $badge_class = 'bg-success';
                                elseif ($row['status'] === 'Overdue') $badge_class = 'bg-danger';
                                elseif ($row['status'] === 'Partially Paid') $badge_class = 'bg-warning text-dark';
                            ?>
                                <tr>
                                    <td class="fw-bold text-white"><?= htmlspecialchars($row['invoice_number']) ?></td>
                                    <td><?= date('d M Y', strtotime($row['invoice_date'])) ?></td>
                                    <td><?= date('d M Y', strtotime($row['due_date'])) ?></td>
                                    <td><?= format_currency($row['total_amount'], $row['currency_code']) ?></td>
                                    <td class="text-success"><?= format_currency($row['paid_amount'], $row['currency_code']) ?></td>
                                    <td class="fw-semibold <?= ($balance > 0) ? 'text-danger' : 'text-muted' ?>">
                                        <?= format_currency($balance, $row['currency_code']) ?>
                                    </td>
                                    <td>
                                        <span class="badge <?= $badge_class ?> px-2 py-1">
                                            <?= htmlspecialchars($row['status']) ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <div class="d-flex justify-content-center gap-1">
                                            <a href="view_invoice.php?id=<?= $row['invoice_id'] ?>" class="btn btn-sm btn-outline-info" title="View Details">
                                                <i class="bi bi-eye-fill me-1"></i> View
                                            </a>
                                            <?php if ($balance > 0): ?>
                                                <a href="customer_payments.php?invoice_id=<?= $row['invoice_id'] ?>" class="btn btn-sm btn-success" title="Make Payment">
                                                    <i class="bi bi-credit-card-fill me-1"></i> Pay
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center py-5 text-muted">
                                    <i class="bi bi-inbox fs-1 d-block mb-2 text-secondary"></i>
                                    No invoices found matching your criteria.
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