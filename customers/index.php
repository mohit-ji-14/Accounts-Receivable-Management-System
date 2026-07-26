<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

require_once "../config/database.php";
require_once "../config/currency.php";

$page_title = "Client Directory & Credit Risk";
$page_header = "Client & Credit Risk Management";

include "../includes/header.php";
include "../includes/sidebar.php";

$search = trim($_GET['search'] ?? '');

try {
    if ($search !== '') {
        $stmt = $conn->prepare("
            SELECT c.*, e.entity_name,
                   COALESCE(SUM((i.total_amount - i.paid_amount) * i.fx_rate), 0) AS current_balance
            FROM customers c
            LEFT JOIN entities e ON c.entity_id = e.entity_id
            LEFT JOIN invoices i ON c.customer_id = i.customer_id AND i.status != 'Paid'
            WHERE c.customer_name LIKE ? OR c.company_name LIKE ? OR c.email LIKE ?
            GROUP BY c.customer_id
            ORDER BY c.customer_id DESC
        ");
        $term = "%$search%";
        $stmt->execute([$term, $term, $term]);
    } else {
        $stmt = $conn->query("
            SELECT c.*, e.entity_name,
                   COALESCE(SUM((i.total_amount - i.paid_amount) * i.fx_rate), 0) AS current_balance
            FROM customers c
            LEFT JOIN entities e ON c.entity_id = e.entity_id
            LEFT JOIN invoices i ON c.customer_id = i.customer_id AND i.status != 'Paid'
            GROUP BY c.customer_id
            ORDER BY c.customer_id DESC
        ");
    }
    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}
?>

<div class="content">
    <?php include "../includes/navbar.php"; ?>

    <div class="container-fluid p-4">

        <!-- Top Header & Actions -->
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
            <div>
                <h4 class="fw-bold mb-1"><i class="bi bi-shield-lock-fill text-primary me-2"></i> MNC Client Directory & Risk Controls</h4>
                <p class="text-muted small mb-0">Manage global corporate accounts, currency assignments, and credit hold statuses</p>
            </div>
            
            <div class="d-flex align-items-center gap-2">
                <form method="GET" action="index.php" class="d-flex align-items-center gap-2">
                    <div class="input-group input-group-sm" style="width: 260px;">
                        <input type="text" name="search" class="form-control" placeholder="Search account or email..." value="<?= htmlspecialchars($search) ?>">
                        <button class="btn btn-outline-secondary" type="submit"><i class="bi bi-search"></i></button>
                    </div>
                </form>
                <a href="add.php" class="btn btn-primary btn-sm px-3">
                    <i class="bi bi-person-plus-fill me-1"></i> Register New Client
                </a>
            </div>
        </div>

        <?php if (isset($_GET['msg'])): ?>
            <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i> <?= htmlspecialchars($_GET['msg']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Customer Table -->
        <div class="card-custom">
            <div class="table-responsive">
                <table class="table table-custom mb-0">
                    <thead>
                        <tr>
                            <th>Account Name / Company</th>
                            <th>Currency</th>
                            <th>Risk Grade</th>
                            <th>Credit Limit</th>
                            <th>Credit Status</th>
                            <th>Outstanding AR</th>
                            <th width="140" class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($customers) > 0): ?>
                            <?php foreach ($customers as $c): 
                                $risk_badge = 'bg-success';
                                if ($c['risk_level'] === 'Medium') $risk_badge = 'bg-warning text-dark';
                                elseif ($c['risk_level'] === 'High') $risk_badge = 'bg-danger';
                            ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold text-white"><?= htmlspecialchars($c['customer_name']) ?></div>
                                        <div class="text-muted small"><?= htmlspecialchars($c['company_name'] ?: 'Individual Client') ?> &bull; <span class="text-secondary"><?= htmlspecialchars($c['entity_name'] ?: 'Global') ?></span></div>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark border"><?= htmlspecialchars($c['currency_code'] ?? 'INR') ?></span>
                                    </td>
                                    <td>
                                        <span class="badge <?= $risk_badge ?> px-2 py-1"><?= htmlspecialchars($c['risk_level'] ?? 'Low') ?> Risk</span>
                                    </td>
                                    <td class="fw-semibold">
                                        <?= format_currency($c['credit_limit'], $c['currency_code'] ?? 'INR') ?>
                                    </td>
                                    <td>
                                        <?php if ($c['credit_hold']): ?>
                                            <span class="badge bg-danger-subtle text-danger border border-danger px-2 py-1"><i class="bi bi-lock-fill me-1"></i> CREDIT HOLD</span>
                                        <?php else: ?>
                                            <span class="badge bg-success-subtle text-success border border-success px-2 py-1"><i class="bi bi-check-circle-fill me-1"></i> Active</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($c['current_balance'] > 0): ?>
                                            <span class="fw-bold text-danger">₹<?= number_format($c['current_balance'], 2) ?></span>
                                        <?php else: ?>
                                            <span class="fw-bold text-success">₹0.00</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <div class="d-flex justify-content-center gap-1">
                                            <a href="view.php?id=<?= $c['customer_id'] ?>" class="btn btn-action btn-light text-primary" title="View Customer Ledger">
                                                <i class="bi bi-eye-fill"></i>
                                            </a>
                                            <a href="edit.php?id=<?= $c['customer_id'] ?>" class="btn btn-action btn-light text-warning" title="Edit Risk & Credit">
                                                <i class="bi bi-pencil-square"></i>
                                            </a>
                                            <a href="delete.php?id=<?= $c['customer_id'] ?>" class="btn btn-action btn-light text-danger" title="Delete" onclick="return confirm('Are you sure you want to delete this account?')">
                                                <i class="bi bi-trash-fill"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted py-5">
                                    <i class="bi bi-people fs-1 text-muted d-block mb-2"></i>
                                    No client accounts found.
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