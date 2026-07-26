<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

require_once "../config/database.php";
require_once "../config/currency.php";

$page_title = "Reports & Collections";
$page_header = "Accounts Receivable Reports";

include "../includes/header.php";
include "../includes/sidebar.php";

try {
    $aging_stmt = $conn->query("
        SELECT 
            c.customer_id,
            c.customer_name,
            c.company_name,
            c.email,
            c.phone,
            COALESCE(SUM((i.total_amount - i.paid_amount) * i.fx_rate), 0) AS total_ar,
            COALESCE(SUM(CASE WHEN i.due_date >= CURRENT_DATE() THEN ((i.total_amount - i.paid_amount) * i.fx_rate) ELSE 0 END), 0) AS current_amt,
            COALESCE(SUM(CASE WHEN DATEDIFF(CURRENT_DATE(), i.due_date) BETWEEN 1 AND 30 AND i.status != 'Paid' THEN ((i.total_amount - i.paid_amount) * i.fx_rate) ELSE 0 END), 0) AS days_1_30,
            COALESCE(SUM(CASE WHEN DATEDIFF(CURRENT_DATE(), i.due_date) BETWEEN 31 AND 60 AND i.status != 'Paid' THEN ((i.total_amount - i.paid_amount) * i.fx_rate) ELSE 0 END), 0) AS days_31_60,
            COALESCE(SUM(CASE WHEN DATEDIFF(CURRENT_DATE(), i.due_date) BETWEEN 61 AND 90 AND i.status != 'Paid' THEN ((i.total_amount - i.paid_amount) * i.fx_rate) ELSE 0 END), 0) AS days_61_90,
            COALESCE(SUM(CASE WHEN DATEDIFF(CURRENT_DATE(), i.due_date) > 90 AND i.status != 'Paid' THEN ((i.total_amount - i.paid_amount) * i.fx_rate) ELSE 0 END), 0) AS days_90_plus
        FROM customers c
        JOIN invoices i ON c.customer_id = i.customer_id
        WHERE i.status != 'Paid'
        GROUP BY c.customer_id
        HAVING total_ar > 0
        ORDER BY total_ar DESC
    ");
    $aging_report = $aging_stmt->fetchAll(PDO::FETCH_ASSOC);

    $overdue_stmt = $conn->query("
        SELECT i.*, c.customer_name, c.company_name, c.email 
        FROM invoices i
        JOIN customers c ON i.customer_id = c.customer_id
        WHERE i.status != 'Paid' AND (i.due_date < CURRENT_DATE() OR i.status = 'Overdue')
        ORDER BY i.due_date ASC
    ");
    $overdue_invoices = $overdue_stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}
?>

<div class="content">
    <?php include "../includes/navbar.php"; ?>

    <div class="container-fluid p-4">

        <div class="d-flex justify-content-between align-items-center mb-4 d-print-none">
            <div>
                <h4 class="fw-bold mb-1"><i class="bi bi-bar-chart-line-fill text-primary me-2"></i> Aging Reports & Collection Tools</h4>
                <p class="text-muted small mb-0">Monitor overdue receivables and run Python collection automation</p>
            </div>
            <div class="d-flex gap-2">
                <a href="run_python_reminders.php" class="btn btn-indigo text-white btn-sm px-3" style="background-color: #4f46e5;">
                    <i class="bi bi-cpu-fill me-1"></i>  Auto-Collection Reminders
                </a>
                <button onclick="window.print()" class="btn btn-outline-dark btn-sm"><i class="bi bi-printer me-1"></i> Print Aging Report</button>
            </div>
        </div>

        <!-- Aging Table Card -->
        <div class="card-custom mb-4 p-4">
            <h5 class="fw-bold mb-3"><i class="bi bi-journal-text text-indigo me-2"></i> Accounts Receivable Aging Summary by Client</h5>
            
            <div class="table-responsive">
                <table class="table table-custom table-bordered mb-0">
                    <thead>
                        <tr class="table-dark">
                            <th>Client / Company Name</th>
                            <th class="text-end">Current (Not Due)</th>
                            <th class="text-end">1–30 Days</th>
                            <th class="text-end">31–60 Days</th>
                            <th class="text-end">61–90 Days</th>
                            <th class="text-end">90+ Days</th>
                            <th class="text-end">Total AR Balance (INR)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($aging_report) > 0): 
                            $tot_current = 0; $tot_1_30 = 0; $tot_31_60 = 0; $tot_61_90 = 0; $tot_90_plus = 0; $tot_ar = 0;
                            foreach ($aging_report as $r):
                                $tot_current += $r['current_amt'];
                                $tot_1_30 += $r['days_1_30'];
                                $tot_31_60 += $r['days_31_60'];
                                $tot_61_90 += $r['days_61_90'];
                                $tot_90_plus += $r['days_90_plus'];
                                $tot_ar += $r['total_ar'];
                        ?>
                            <tr>
                                <td>
                                    <div class="fw-bold"><?= htmlspecialchars($r['customer_name']) ?></div>
                                    <div class="text-muted small"><?= htmlspecialchars($r['company_name'] ?: 'Individual Client') ?></div>
                                </td>
                                <td class="text-end text-success fw-semibold">₹<?= number_format($r['current_amt'], 2) ?></td>
                                <td class="text-end text-warning fw-semibold">₹<?= number_format($r['days_1_30'], 2) ?></td>
                                <td class="text-end fw-semibold" style="color: #ea580c;">₹<?= number_format($r['days_31_60'], 2) ?></td>
                                <td class="text-end text-danger fw-semibold">₹<?= number_format($r['days_61_90'], 2) ?></td>
                                <td class="text-end text-dark fw-bold">₹<?= number_format($r['days_90_plus'], 2) ?></td>
                                <td class="text-end fw-bold fs-6 text-indigo">₹<?= number_format($r['total_ar'], 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                            <tr class="table-light fw-bold">
                                <td>TOTAL RECEIVABLES</td>
                                <td class="text-end text-success">₹<?= number_format($tot_current, 2) ?></td>
                                <td class="text-end text-warning">₹<?= number_format($tot_1_30, 2) ?></td>
                                <td class="text-end" style="color: #ea580c;">₹<?= number_format($tot_31_60, 2) ?></td>
                                <td class="text-end text-danger">₹<?= number_format($tot_61_90, 2) ?></td>
                                <td class="text-end text-dark">₹<?= number_format($tot_90_plus, 2) ?></td>
                                <td class="text-end text-indigo fs-5">₹<?= number_format($tot_ar, 2) ?></td>
                            </tr>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">No outstanding balances currently pending. Excellent collection rate!</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Overdue Collection Reminder Generator Tool -->
        <div class="card-custom p-4 d-print-none">
            <h5 class="fw-bold mb-1"><i class="bi bi-envelope-at-fill text-danger me-2"></i> Automated Collection Email Reminder Generator</h5>
            <p class="text-muted small mb-3">Select an overdue invoice to pre-populate a customized reminder email template</p>

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label small fw-semibold">Select Overdue Invoice</label>
                    <select id="reminderSelect" class="form-select" onchange="generateEmail()">
                        <option value="">-- Choose Overdue Invoice --</option>
                        <?php foreach ($overdue_invoices as $oi): 
                            $bal = $oi['total_amount'] - $oi['paid_amount'];
                            $days_overdue = (strtotime(date('Y-m-d')) - strtotime($oi['due_date'])) / (60 * 60 * 24);
                            $curr = $oi['currency_code'] ?? 'INR';
                        ?>
                            <option value="<?= $oi['invoice_id'] ?>" 
                                    data-customer="<?= htmlspecialchars($oi['customer_name']) ?>"
                                    data-company="<?= htmlspecialchars($oi['company_name']) ?>"
                                    data-email="<?= htmlspecialchars($oi['email']) ?>"
                                    data-invnum="<?= htmlspecialchars($oi['invoice_number']) ?>"
                                    data-due="<?= date('d M Y', strtotime($oi['due_date'])) ?>"
                                    data-bal="<?= $curr . ' ' . number_format($bal, 2) ?>"
                                    data-days="<?= max(1, round($days_overdue)) ?>">
                                <?= htmlspecialchars($oi['invoice_number']) ?> &bull; <?= htmlspecialchars($oi['customer_name']) ?> (<?= $curr ?> <?= number_format($bal, 2) ?> - <?= max(1, round($days_overdue)) ?> Days Overdue)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label small fw-semibold">Notice Level</label>
                    <select id="noticeType" class="form-select" onchange="generateEmail()">
                        <option value="friendly">1st Notice - Friendly Reminder</option>
                        <option value="second">2nd Notice - Urgent Overdue Alert</option>
                        <option value="final">Final Notice - Pre-legal Demand</option>
                    </select>
                </div>

                <div class="col-12">
                    <label class="form-label small fw-semibold">Generated Email Text</label>
                    <textarea id="emailOutput" class="form-control font-monospace text-dark bg-light" rows="8" placeholder="Select an overdue invoice above to generate email..."></textarea>
                </div>

                <div class="col-12 text-end">
                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="copyEmail()"><i class="bi bi-clipboard me-1"></i> Copy Email Text</button>
                    <a id="mailtoBtn" href="#" class="btn btn-primary btn-sm"><i class="bi bi-send me-1"></i> Open Mail App</a>
                </div>
            </div>
        </div>

    </div>
</div>

<script>
function generateEmail() {
    const select = document.getElementById('reminderSelect');
    const notice = document.getElementById('noticeType').value;
    const selected = select.options[select.selectedIndex];

    if (!selected.value) {
        document.getElementById('emailOutput').value = '';
        return;
    }

    const name = selected.getAttribute('data-customer');
    const company = selected.getAttribute('data-company');
    const email = selected.getAttribute('data-email');
    const invNum = selected.getAttribute('data-invnum');
    const due = selected.getAttribute('data-due');
    const bal = selected.getAttribute('data-bal');
    const days = selected.getAttribute('data-days');

    let subject = '';
    let body = '';

    if (notice === 'friendly') {
        subject = `Friendly Reminder: Payment for Invoice ${invNum} is Overdue`;
        body = `Dear ${name},\n\nWe hope this email finds you well.\n\nThis is a friendly reminder that payment for Invoice ${invNum} (Amount: ${bal}), which was due on ${due}, is currently unpaid (${days} days overdue).\n\nPlease let us know when we can expect payment, or if you have any questions regarding this invoice.\n\nBank Transfer Details:\nAccount Name: ARMS Enterprise\nBank: HDFC Bank / Chase Bank\nSWIFT: ARMSUS33XXX\n\nThank you for your prompt attention.\n\nBest regards,\nAccounts Receivable Team`;
    } else if (notice === 'second') {
        subject = `URGENT: Overdue Notice for Invoice ${invNum}`;
        body = `Dear ${name},\n\nOur records indicate that Invoice ${invNum} for ${bal} is now ${days} days past due.\n\nWe kindly request that payment be processed immediately to prevent any interruption to ongoing services.\n\nIf payment has already been sent, please reply with the payment reference details so we can update your ledger.\n\nThank you,\nAccounts Receivable Team`;
    } else {
        subject = `FINAL NOTICE: Immediate Payment Required - Invoice ${invNum}`;
        body = `Dear ${name} (${company}),\n\nFINAL PAYMENT DEMAND\n\nDespite previous reminders, your account remains past due by ${days} days for Invoice ${invNum} in the amount of ${bal}.\n\nPlease remit full payment within 48 hours to avoid potential credit holds or administrative action.\n\nContact us immediately at billing@arms-system.com if you require assistance.\n\nSincerely,\nFinance & Collections Dept`;
    }

    document.getElementById('emailOutput').value = `To: ${email}\nSubject: ${subject}\n\n${body}`;
    document.getElementById('mailtoBtn').href = `mailto:${email}?subject=${encodeURIComponent(subject)}&body=${encodeURIComponent(body)}`;
}

function copyEmail() {
    const text = document.getElementById('emailOutput');
    text.select();
    document.execCommand('copy');
    alert('Email text copied to clipboard!');
}
</script>

<?php include "../includes/footer.php"; ?>
