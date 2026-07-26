<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

require_once "../config/database.php";
require_once "../config/currency.php";

$page_title = "Invoices Directory";
$page_header = "Invoices Directory";

include "../includes/header.php";
include "../includes/sidebar.php";

$status_filter = trim($_GET['status'] ?? '');
$search = trim($_GET['search'] ?? '');

try {
    $where = [];
    $params = [];

    if ($status_filter !== '') {
        if ($status_filter === 'Overdue') {
            $where[] = "(i.status = 'Overdue' OR (i.due_date < CURRENT_DATE() AND i.status != 'Paid'))";
        } else {
            $where[] = "i.status = ?";
            $params[] = $status_filter;
        }
    }

    if ($search !== '') {
        $where[] = "(i.invoice_number LIKE ? OR c.customer_name LIKE ? OR c.company_name LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }

    $where_sql = count($where) > 0 ? "WHERE " . implode(" AND ", $where) : "";

    $sql = "
        SELECT i.*, c.customer_name, c.company_name 
        FROM invoices i
        JOIN customers c ON i.customer_id = c.customer_id
        $where_sql
        ORDER BY i.invoice_id DESC
    ";

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}
?>

<div class="content">
    <?php include "../includes/navbar.php"; ?>

    <div class="container-fluid p-4">

        <!-- Top Header & Search Actions -->
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
            <div>
                <h4 class="fw-bold mb-1"><i class="bi bi-file-earmark-text-fill text-primary me-2"></i> Invoices Directory</h4>
                <p class="text-muted small mb-0">Manage customer billing, payment terms, and status tracking</p>
            </div>
            
            <div class="d-flex align-items-center gap-2">
                <a href="upload_ocr.php" class="btn btn-outline-danger btn-sm px-3">
                    <i class="bi bi-file-earmark-pdf-fill me-1"></i> Python PDF OCR Scanner
                </a>
                <a href="add.php" class="btn btn-primary btn-sm px-3">
                    <i class="bi bi-plus-circle me-1"></i> Create New Invoice
                </a>
            </div>
        </div>

        <?php if (isset($_GET['msg'])): ?>
            <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i> <?= htmlspecialchars($_GET['msg']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Filter Pills Bar -->
        <div class="card-custom p-3 mb-4">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                
                <div class="btn-group btn-group-sm" role="group">
                    <a href="index.php" class="btn btn-outline-secondary <?= $status_filter === '' ? 'active fw-bold' : '' ?>">All Statuses</a>
                    <a href="index.php?status=Pending" class="btn btn-outline-warning <?= $status_filter === 'Pending' ? 'active fw-bold' : '' ?>">Pending</a>
                    <a href="index.php?status=Partially Paid" class="btn btn-outline-primary <?= $status_filter === 'Partially Paid' ? 'active fw-bold' : '' ?>">Partially Paid</a>
                    <a href="index.php?status=Overdue" class="btn btn-outline-danger <?= $status_filter === 'Overdue' ? 'active fw-bold' : '' ?>">Overdue</a>
                    <a href="index.php?status=Paid" class="btn btn-outline-success <?= $status_filter === 'Paid' ? 'active fw-bold' : '' ?>">Paid</a>
                </div>

                <form method="GET" action="index.php" class="d-flex align-items-center gap-2">
                    <?php if ($status_filter): ?>
                        <input type="hidden" name="status" value="<?= htmlspecialchars($status_filter) ?>">
                    <?php endif; ?>
                    <div class="input-group input-group-sm" style="width: 250px;">
                        <input type="text" name="search" class="form-control" placeholder="Search invoice # or client..." value="<?= htmlspecialchars($search) ?>">
                        <button class="btn btn-outline-secondary" type="submit"><i class="bi bi-search"></i></button>
                    </div>
                </form>

            </div>
        </div>

        <!-- Invoices Table -->
        <div class="card-custom">
            <div class="table-responsive">
                <table class="table table-custom mb-0">
                    <thead>
                        <tr>
                            <th>Invoice #</th>
                            <th>Customer / Company</th>
                            <th>Issue Date</th>
                            <th>Due Date</th>
                            <th>Total Amount</th>
                            <th>Balance Due</th>
                            <th>Status</th>
                            <th width="140" class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($invoices) > 0): ?>
                            <?php foreach ($invoices as $inv): 
                                $balance = max(0, $inv['total_amount'] - $inv['paid_amount']);
                                $is_overdue = ($inv['status'] === 'Overdue') || ($inv['due_date'] < date('Y-m-d') && $inv['status'] !== 'Paid');
                                $curr = $inv['currency_code'] ?? 'INR';

                                $badge_class = 'badge-pending';
                                $status_text = $inv['status'];
                                if ($inv['status'] === 'Paid') {
                                    $badge_class = 'badge-paid';
                                } elseif ($inv['status'] === 'Partially Paid') {
                                    $badge_class = 'badge-partial';
                                } elseif ($is_overdue) {
                                    $badge_class = 'badge-overdue';
                                    $status_text = 'Overdue';
                                }
                            ?>
                                <tr>
                                    <td class="fw-bold text-indigo"><?= htmlspecialchars($inv['invoice_number']) ?></td>
                                    <td>
                                        <div class="fw-semibold text-white"><?= htmlspecialchars($inv['customer_name']) ?></div>
                                        <div class="text-muted small"><?= htmlspecialchars($inv['company_name'] ?: 'Individual Client') ?></div>
                                    </td>
                                    <td class="small"><?= date('d M Y', strtotime($inv['invoice_date'])) ?></td>
                                    <td class="small <?= $is_overdue ? 'text-danger fw-bold' : '' ?>"><?= date('d M Y', strtotime($inv['due_date'])) ?></td>
                                    <td class="fw-bold"><?= format_currency($inv['total_amount'], $curr) ?></td>
                                    <td class="fw-bold <?= $balance > 0 ? 'text-danger' : 'text-success' ?>"><?= format_currency($balance, $curr) ?></td>
                                    <td><span class="badge-status <?= $badge_class ?>"><?= htmlspecialchars($status_text) ?></span></td>
                                    <td class="text-center">
                                        <div class="d-flex justify-content-center gap-1">
                                            <a href="view.php?id=<?= $inv['invoice_id'] ?>" class="btn btn-action btn-light text-primary" title="View / Print Invoice">
                                                <i class="bi bi-printer-fill"></i>
                                            </a>
                                            <?php if ($balance > 0): ?>
                                                <a href="../payments/add.php?invoice_id=<?= $inv['invoice_id'] ?>" class="btn btn-action btn-light text-success" title="Record Payment">
                                                    <i class="bi bi-cash-stack"></i>
                                                </a>
                                            <?php endif; ?>
                                            <a href="delete.php?id=<?= $inv['invoice_id'] ?>" class="btn btn-action btn-light text-danger" title="Delete Invoice" onclick="return confirm('Are you sure you want to delete this invoice?')">
                                                <i class="bi bi-trash-fill"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center text-muted py-5">
                                    <i class="bi bi-file-earmark-x fs-1 text-muted d-block mb-2"></i>
                                    No invoices matching your criteria.
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
