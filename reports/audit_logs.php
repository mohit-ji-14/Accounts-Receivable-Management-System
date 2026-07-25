<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

require_once "../config/database.php";

$page_title = "Audit Trail Logs";
$page_header = "Enterprise Audit Trail & Security Logs";

include "../includes/header.php";
include "../includes/sidebar.php";

try {
    $stmt = $conn->query("
        SELECT * FROM activity_logs 
        ORDER BY log_id DESC 
        LIMIT 100
    ");
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}
?>

<div class="content">
    <?php include "../includes/navbar.php"; ?>

    <div class="container-fluid p-4">

        <!-- Top Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="fw-bold mb-1"><i class="bi bi-journal-check text-indigo me-2"></i> Audit Trail & Compliance Log</h4>
                <p class="text-muted small mb-0">Immutable records of all financial transactions, logins, and system events</p>
            </div>
            <button onclick="window.print()" class="btn btn-outline-dark btn-sm"><i class="bi bi-printer me-1"></i> Print Audit Log</button>
        </div>

        <!-- Audit Table -->
        <div class="card-custom">
            <div class="table-responsive">
                <table class="table table-custom mb-0">
                    <thead>
                        <tr class="table-dark">
                            <th>Log ID</th>
                            <th>Timestamp</th>
                            <th>User Name</th>
                            <th>IP Address</th>
                            <th>Activity Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($logs) > 0): ?>
                            <?php foreach ($logs as $l): ?>
                                <tr>
                                    <td class="fw-bold font-monospace">#LOG-<?= str_pad($l['log_id'], 5, '0', STR_PAD_LEFT) ?></td>
                                    <td class="small"><?= date('d M Y, H:i:s', strtotime($l['created_at'])) ?></td>
                                    <td class="fw-semibold text-dark">
                                        <i class="bi bi-person-circle me-1 text-primary"></i> <?= htmlspecialchars($l['user_name'] ?: 'System Admin') ?>
                                    </td>
                                    <td class="small text-muted font-monospace"><?= htmlspecialchars($l['ip_address'] ?: '127.0.0.1') ?></td>
                                    <td>
                                        <span class="badge bg-light text-dark border px-2 py-1"><?= htmlspecialchars($l['activity']) ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center text-muted py-5">
                                    <i class="bi bi-shield-x fs-1 text-muted d-block mb-2"></i>
                                    No activity logs recorded yet.
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
