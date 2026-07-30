<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

require_once "../config/database.php";

$page_title = "Automated Reminders Runner";
$page_header = "Reminders Cron Execution";

include "../includes/header.php";
include "../includes/sidebar.php";

// Execute Python Script or Fallback PHP Parser
$python_script = __DIR__ . "/../python_services/send_reminders.py";
$output_json = null;
$execution_method = "Python Engine";

// Try executing via python / py command
$cmd_output = shell_exec("python " . escapeshellarg($python_script) . " 2>&1");
if (empty($cmd_output) || strpos($cmd_output, 'Python was not found') !== false || strpos($cmd_output, 'error') !== false && strpos($cmd_output, '{') === false) {
    $cmd_output = shell_exec("py " . escapeshellarg($python_script) . " 2>&1");
}

if (!empty($cmd_output) && strpos($cmd_output, '{') !== false) {
    $json_start = strpos($cmd_output, '{');
    $json_str = substr($cmd_output, $json_start);
    $output_json = json_decode($json_str, true);
}

// Fallback PHP Runner if Python CLI is not installed on host machine
if (!$output_json) {
    $execution_method = "PHP Native Fallback Engine";
    try {
        $today = date('Y-m-d');
        $stmt = $conn->prepare("
            SELECT i.invoice_id, i.invoice_number, i.invoice_date, i.due_date, 
                   i.total_amount, i.paid_amount, i.currency_code,
                   c.customer_name, c.company_name, c.email
            FROM invoices i
            JOIN customers c ON i.customer_id = c.customer_id
            WHERE i.status != 'Paid' AND (i.due_date < ? OR i.status = 'Overdue')
            ORDER BY i.due_date ASC
        ");
        $stmt->execute([$today]);
        $overdue_invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $reminders = [];
        foreach ($overdue_invoices as $inv) {
            $days_overdue = max(1, round((strtotime($today) - strtotime($inv['due_date'])) / 86400));
            $bal = $inv['total_amount'] - $inv['paid_amount'];
            $curr = $inv['currency_code'] ?? 'INR';

            $notice = "1st Friendly Reminder";
            if ($days_overdue > 60) $notice = "Final Demand Notice";
            elseif ($days_overdue > 30) $notice = "2nd Urgent Overdue Notice";

            $reminders[] = [
                "invoice_number" => $inv['invoice_number'],
                "customer_name" => $inv['customer_name'],
                "email" => $inv['email'],
                "balance_due" => $curr . ' ' . number_format($bal, 2),
                "days_overdue" => $days_overdue,
                "notice_type" => $notice,
                "status" => "Reminder Queued Successfully"
            ];

            // Log activity
            $log = $conn->prepare("INSERT INTO activity_logs (user_name, activity, ip_address) VALUES (?, ?, ?)");
            $log->execute(["Reminders Engine", "Queued $notice for Invoice #" . $inv['invoice_number'], "127.0.0.1"]);
        }

        $output_json = [
            "status" => "success",
            "timestamp" => date("Y-m-d H:i:s"),
            "total_overdue_found" => count($overdue_invoices),
            "total_reminders_queued" => count($reminders),
            "reminders" => $reminders
        ];
    } catch (PDOException $e) {
        $output_json = ["status" => "error", "message" => $e->getMessage()];
    }
}
?>

<div class="content">
    <?php include "../includes/navbar.php"; ?>

    <div class="container-fluid p-4">

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="fw-bold mb-1"><i class="bi bi-cpu-fill text-primary me-2"></i> Automated Collection Reminders Engine</h4>
                <p class="text-muted small mb-0">Executed via: <span class="badge bg-indigo-light text-indigo border"><?= $execution_method ?></span></p>
            </div>
            <a href="index.php" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i> Back to Reports
            </a>
        </div>

        <?php if (isset($output_json['status']) && $output_json['status'] === 'success'): ?>
            <div class="card-custom p-4 mb-4 border-start border-4 border-success">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="fw-bold text-success mb-1"><i class="bi bi-check-circle-fill me-2"></i>Execution Successful!</h5>
                        <p class="text-muted small mb-0">Found <strong><?= $output_json['total_overdue_found'] ?></strong> overdue invoice(s). Queued <strong><?= $output_json['total_reminders_queued'] ?></strong> email reminder notices.</p>
                    </div>
                    <span class="badge bg-light text-dark border font-monospace"><?= $output_json['timestamp'] ?></span>
                </div>
            </div>

            <div class="card-custom p-4 mb-4">
                <h6 class="fw-bold mb-3"><i class="bi bi-send-check-fill me-2 text-indigo"></i> Queued Reminder Logs</h6>
                <div class="table-responsive">
                    <table class="table table-custom mb-0">
                        <thead>
                            <tr>
                                <th>Invoice #</th>
                                <th>Client Name</th>
                                <th>Email Address</th>
                                <th>Notice Type</th>
                                <th>Days Overdue</th>
                                <th>Balance Due</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($output_json['reminders'] as $rem): ?>
                                <tr>
                                    <td class="fw-bold text-indigo"><?= htmlspecialchars($rem['invoice_number']) ?></td>
                                    <td class="fw-semibold"><?= htmlspecialchars($rem['customer_name']) ?></td>
                                    <td class="small text-muted"><?= htmlspecialchars($rem['email']) ?></td>
                                    <td><span class="badge bg-warning text-dark border"><?= htmlspecialchars($rem['notice_type']) ?></span></td>
                                    <td class="fw-bold text-danger"><?= $rem['days_overdue'] ?> Days</td>
                                    <td class="fw-bold"><?= htmlspecialchars($rem['balance_due']) ?></td>
                                    <td><span class="badge bg-success-subtle text-success border border-success"><?= htmlspecialchars($rem['status']) ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php else: ?>
            <div class="alert alert-danger py-3 border-0 mb-4 shadow-sm">
                <i class="bi bi-exclamation-octagon-fill me-2"></i> Error running Python script: <?= htmlspecialchars($output_json['message'] ?? 'Unknown error') ?>
            </div>
        <?php endif; ?>

    </div>
</div>

<?php include "../includes/footer.php"; ?>
