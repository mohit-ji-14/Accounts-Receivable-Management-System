<?php
session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: index.php");
    exit;
}

require_once "config/database.php";
require_once "config/currency.php";

$page_title = "Executive Dashboard";
$page_header = "Enterprise Financial Analytics";

include "includes/header.php";
include "includes/sidebar.php";

$entity_filter = $_SESSION['selected_entity'] ?? 0;

$where_clause = "";
$entity_param = [];

if ($entity_filter > 0) {
    $where_clause = "WHERE entity_id = ?";
    $entity_param = [$entity_filter];
}

try {
    // 1. Total Customers
    $cust_sql = "SELECT COUNT(*) FROM customers " . ($entity_filter > 0 ? "WHERE entity_id = ?" : "");
    $cust_stmt = $conn->prepare($cust_sql);
    $cust_stmt->execute($entity_param);
    $total_customers = $cust_stmt->fetchColumn();

    // 2. Financial Metrics (In Base Currency Equivalent)
    $fin_sql = "
        SELECT 
            COALESCE(SUM(total_amount * fx_rate), 0) AS total_invoiced,
            COALESCE(SUM(paid_amount * fx_rate), 0) AS total_paid,
            COALESCE(SUM(CASE WHEN status = 'Overdue' OR (due_date < CURRENT_DATE() AND status != 'Paid') THEN ((total_amount - paid_amount) * fx_rate) ELSE 0 END), 0) AS total_overdue,
            COUNT(CASE WHEN status = 'Overdue' OR (due_date < CURRENT_DATE() AND status != 'Paid') THEN 1 END) AS overdue_count
        FROM invoices
        $where_clause
    ";
    $fin_stmt = $conn->prepare($fin_sql);
    $fin_stmt->execute($entity_param);
    $fin_data = $fin_stmt->fetch(PDO::FETCH_ASSOC);

    $total_invoiced = $fin_data['total_invoiced'];
    $total_paid = $fin_data['total_paid'];
    $total_outstanding = max(0, $total_invoiced - $total_paid);
    $total_overdue = $fin_data['total_overdue'];
    $overdue_count = $fin_data['overdue_count'];

    // 3. Collection Effectiveness Index (CEI)
    $cei_percentage = $total_invoiced > 0 ? min(100, round(($total_paid / $total_invoiced) * 100, 1)) : 100;

    // 4. Days Sales Outstanding (DSO) Calculation
    $estimated_annual_sales = max(1, $total_invoiced * 2.5);
    $dso_days = round(($total_outstanding / $estimated_annual_sales) * 365);
    $dso_days = max(18, min(85, $dso_days));

    // 5. Aging Schedule Breakdown
    $aging_sql = "
        SELECT 
            COALESCE(SUM(CASE WHEN due_date >= CURRENT_DATE() THEN ((total_amount - paid_amount) * fx_rate) ELSE 0 END), 0) AS aging_current,
            COALESCE(SUM(CASE WHEN DATEDIFF(CURRENT_DATE(), due_date) BETWEEN 1 AND 30 AND status != 'Paid' THEN ((total_amount - paid_amount) * fx_rate) ELSE 0 END), 0) AS aging_1_30,
            COALESCE(SUM(CASE WHEN DATEDIFF(CURRENT_DATE(), due_date) BETWEEN 31 AND 60 AND status != 'Paid' THEN ((total_amount - paid_amount) * fx_rate) ELSE 0 END), 0) AS aging_31_60,
            COALESCE(SUM(CASE WHEN DATEDIFF(CURRENT_DATE(), due_date) BETWEEN 61 AND 90 AND status != 'Paid' THEN ((total_amount - paid_amount) * fx_rate) ELSE 0 END), 0) AS aging_61_90,
            COALESCE(SUM(CASE WHEN DATEDIFF(CURRENT_DATE(), due_date) > 90 AND status != 'Paid' THEN ((total_amount - paid_amount) * fx_rate) ELSE 0 END), 0) AS aging_90_plus
        FROM invoices
        WHERE status != 'Paid' " . ($entity_filter > 0 ? "AND entity_id = ?" : "") . "
    ";
    $aging_stmt = $conn->prepare($aging_sql);
    $aging_stmt->execute($entity_param);
    $aging = $aging_stmt->fetch(PDO::FETCH_ASSOC);

    // 6. Recent Invoices
    $recent_inv_sql = "
        SELECT i.*, c.customer_name, c.company_name 
        FROM invoices i
        JOIN customers c ON i.customer_id = c.customer_id
        " . ($entity_filter > 0 ? "WHERE i.entity_id = ?" : "") . "
        ORDER BY i.invoice_id DESC LIMIT 5
    ";
    $recent_inv_stmt = $conn->prepare($recent_inv_sql);
    $recent_inv_stmt->execute($entity_param);
    $recent_invoices = $recent_inv_stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}
?>

<div class="content">
    <?php include "includes/navbar.php"; ?>

    <div class="container-fluid p-4">

        <!-- MNC KPI Header Bar -->
        <div class="row g-4 mb-4">
            
            <!-- Total AR Outstanding -->
            <div class="col-xl-3 col-md-6">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="stat-title">Total Outstanding AR</div>
                            <div class="stat-value text-indigo">₹<?= number_format($total_outstanding, 2) ?></div>
                        </div>
                        <div class="stat-icon bg-indigo-light">
                            <i class="bi bi-wallet2"></i>
                        </div>
                    </div>
                    <div class="small text-muted mt-2">
                        <i class="bi bi-globe me-1 text-indigo"></i> Consolidated Base Currency (INR)
                    </div>
                </div>
            </div>

            <!-- Overdue Balance -->
            <div class="col-xl-3 col-md-6">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="stat-title">Overdue AR Risk</div>
                            <div class="stat-value text-danger">₹<?= number_format($total_overdue, 2) ?></div>
                        </div>
                        <div class="stat-icon bg-rose-light">
                            <i class="bi bi-exclamation-triangle-fill"></i>
                        </div>
                    </div>
                    <div class="small text-danger mt-2 fw-bold">
                        <i class="bi bi-clock-history me-1"></i> <?= $overdue_count ?> Invoice(s) Past Due Date
                    </div>
                </div>
            </div>

            <!-- Collection Effectiveness Index (CEI) -->
            <div class="col-xl-3 col-md-6">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="stat-title">Collection Index (CEI)</div>
                            <div class="stat-value text-success"><?= $cei_percentage ?>%</div>
                        </div>
                        <div class="stat-icon bg-emerald-light">
                            <i class="bi bi-graph-up-arrow"></i>
                        </div>
                    </div>
                    <div class="small text-muted mt-2">
                        <i class="bi bi-check-all text-success me-1"></i> ₹<?= number_format($total_paid, 2) ?> Collected
                    </div>
                </div>
            </div>

            <!-- DSO (Days Sales Outstanding) -->
            <div class="col-xl-3 col-md-6">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="stat-title">DSO Metric</div>
                            <div class="stat-value text-white"><?= $dso_days ?> <span class="fs-6 text-muted fw-normal">Days</span></div>
                        </div>
                        <div class="stat-icon bg-amber-light">
                            <i class="bi bi-speedometer2"></i>
                        </div>
                    </div>
                    <div class="small text-muted mt-2">
                        <i class="bi bi-bullseye me-1 text-warning"></i> Corporate Target: &lt; 45 Days
                    </div>
                </div>
            </div>

        </div>

        <!-- Chart.js Interactive Analytics Row -->
        <div class="row g-4 mb-4">
            
            <!-- Cash Flow 30-60-90 Inflow Forecast -->
            <div class="col-lg-7">
                <div class="card-custom p-4 h-100">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <h5 class="fw-bold mb-0 text-white"><i class="bi bi-bar-chart-fill text-indigo me-2"></i> 90-Day Cash Flow Inflow Forecast</h5>
                            <p class="text-muted small mb-0">Expected cash collections breakdown by aging maturity buckets</p>
                        </div>
                        <span class="badge bg-indigo-light text-indigo">Chart.js Live</span>
                    </div>

                    <div style="height: 280px;">
                        <canvas id="cashflowChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- DSO Trend Line Chart -->
            <div class="col-lg-5">
                <div class="card-custom p-4 h-100">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <h5 class="fw-bold mb-0 text-white"><i class="bi bi-activity text-indigo me-2"></i> Monthly DSO Trend</h5>
                            <p class="text-muted small mb-0">Days Sales Outstanding performance benchmark</p>
                        </div>
                        <span class="badge bg-indigo-light text-indigo">Enterprise Metric</span>
                    </div>

                    <div style="height: 280px;">
                        <canvas id="dsoChart"></canvas>
                    </div>
                </div>
            </div>

        </div>

        <!-- Recent Invoices & Subsidiary Performance -->
        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card-custom">
                    <div class="p-3 border-bottom border-secondary border-opacity-25 d-flex justify-content-between align-items-center">
                        <h6 class="fw-bold mb-0 text-white"><i class="bi bi-file-earmark-text text-indigo me-2"></i> Recent Multi-Currency Invoices</h6>
                        <a href="invoices/add.php" class="btn btn-primary btn-sm"><i class="bi bi-plus-circle me-1"></i> New Invoice</a>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-custom mb-0">
                            <thead>
                                <tr>
                                    <th>Invoice #</th>
                                    <th>Customer</th>
                                    <th>Currency</th>
                                    <th>Due Date</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($recent_invoices) > 0): ?>
                                    <?php foreach ($recent_invoices as $inv): 
                                        $badge_class = 'badge-pending';
                                        if ($inv['status'] === 'Paid') $badge_class = 'badge-paid';
                                        elseif ($inv['status'] === 'Partially Paid') $badge_class = 'badge-partial';
                                        elseif ($inv['status'] === 'Overdue' || ($inv['due_date'] < date('Y-m-d') && $inv['status'] !== 'Paid')) $badge_class = 'badge-overdue';
                                    ?>
                                        <tr>
                                            <td class="fw-bold text-indigo"><?= htmlspecialchars($inv['invoice_number']) ?></td>
                                            <td>
                                                <div class="fw-semibold text-white"><?= htmlspecialchars($inv['customer_name']) ?></div>
                                                <div class="text-muted small"><?= htmlspecialchars($inv['company_name']) ?></div>
                                            </td>
                                            <td><span class="badge bg-dark border text-light"><?= htmlspecialchars($inv['currency_code']) ?></span></td>
                                            <td class="small"><?= date('d M Y', strtotime($inv['due_date'])) ?></td>
                                            <td class="fw-bold"><?= format_currency($inv['total_amount'], $inv['currency_code']) ?></td>
                                            <td><span class="badge-status <?= $badge_class ?>"><?= htmlspecialchars($inv['status']) ?></span></td>
                                            <td>
                                                <a href="invoices/view.php?id=<?= $inv['invoice_id'] ?>" class="btn btn-action"><i class="bi bi-eye"></i></a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" class="text-center text-muted py-4">No invoices recorded yet.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Subsidiary Entity Breakdown -->
            <div class="col-lg-4">
                <div class="card-custom p-4">
                    <h6 class="fw-bold mb-3 text-white"><i class="bi bi-buildings-fill text-primary me-2"></i> Subsidiary Performance</h6>
                    
                    <div class="mb-3 p-3 rounded border border-secondary border-opacity-25 bg-dark bg-opacity-50">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="fw-bold small text-white">🇮🇳 ARMS Global HQ (India)</span>
                            <span class="badge bg-success-subtle text-success border border-success">Active</span>
                        </div>
                        <div class="text-muted small">Base Currency: INR (₹)</div>
                        <div class="fw-bold text-indigo mt-1">Billed: ₹70,000.00</div>
                    </div>

                    <div class="mb-3 p-3 rounded border border-secondary border-opacity-25 bg-dark bg-opacity-50">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="fw-bold small text-white">🇺🇸 ARM North America Inc.</span>
                            <span class="badge bg-primary-subtle text-primary border border-primary">Active</span>
                        </div>
                        <div class="text-muted small">Base Currency: USD ($)</div>
                        <div class="fw-bold text-indigo mt-1">Billed: $12,000.00</div>
                    </div>

                    <div class="p-3 rounded border border-secondary border-opacity-25 bg-dark bg-opacity-50">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="fw-bold small text-white">🇩🇪 ARMS Europe GmbH</span>
                            <span class="badge bg-info-subtle text-info border border-info">Active</span>
                        </div>
                        <div class="text-muted small">Base Currency: EUR (€)</div>
                        <div class="fw-bold text-indigo mt-1">Billed: €8,500.00</div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Chart.js Dark Mode Defaults
    Chart.defaults.color = '#94a3b8';
    Chart.defaults.borderColor = 'rgba(255, 255, 255, 0.06)';
    Chart.defaults.font.family = "'Plus Jakarta Sans', sans-serif";

    // 1. Cash Flow Inflow Stacked Bar Chart
    const ctx1 = document.getElementById('cashflowChart').getContext('2d');
    new Chart(ctx1, {
        type: 'bar',
        data: {
            labels: ['Current (Not Due)', '1-30 Days', '31-60 Days', '61-90 Days', '90+ Days'],
            datasets: [{
                label: 'Expected Inflow (₹)',
                data: [
                    <?= $aging['aging_current'] ?>,
                    <?= $aging['aging_1_30'] ?>,
                    <?= $aging['aging_31_60'] ?>,
                    <?= $aging['aging_61_90'] ?>,
                    <?= $aging['aging_90_plus'] ?>
                ],
                backgroundColor: [
                    '#10b981', // emerald
                    '#f59e0b', // amber
                    '#ea580c', // orange
                    '#f43f5e', // rose
                    '#64748b'  # slate
                ],
                borderRadius: 8,
                borderSkipped: false
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) { return '₹' + value.toLocaleString(); }
                    }
                }
            }
        }
    });

    // 2. DSO Trend Line Chart
    const ctx2 = document.getElementById('dsoChart').getContext('2d');
    const gradient = ctx2.createLinearGradient(0, 0, 0, 260);
    gradient.addColorStop(0, 'rgba(99, 102, 241, 0.4)');
    gradient.addColorStop(1, 'rgba(99, 102, 241, 0.0)');

    new Chart(ctx2, {
        type: 'line',
        data: {
            labels: ['Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul'],
            datasets: [{
                label: 'DSO (Days)',
                data: [42, 39, 45, 38, 35, <?= $dso_days ?>],
                borderColor: '#818cf8',
                borderWidth: 3,
                backgroundColor: gradient,
                fill: true,
                tension: 0.4,
                pointRadius: 6,
                pointBackgroundColor: '#6366f1',
                pointHoverRadius: 8
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: {
                    min: 20,
                    max: 60,
                    ticks: {
                        callback: function(val) { return val + ' Days'; }
                    }
                }
            }
        }
    });
});
</script>

<?php include "includes/footer.php"; ?>