<?php
$current_script = $_SERVER['SCRIPT_NAME'];
$base_url = '/ARMS/';
?>
<div class="sidebar">
    <div class="logo">
        <i class="bi bi-shield-check fs-3 text-indigo"></i>
        <h3>ARMS Enterprise</h3>
    </div>
    <ul>
        <li>
            <a href="<?= $base_url ?>dashboard.php" class="<?= (strpos($current_script, 'dashboard.php') !== false) ? 'active' : '' ?>">
                <i class="bi bi-speedometer2"></i>
                <span>Executive Dashboard</span>
            </a>
        </li>
        <li>
            <a href="<?= $base_url ?>customers/index.php" class="<?= (strpos($current_script, '/customers/') !== false) ? 'active' : '' ?>">
                <i class="bi bi-people-fill"></i>
                <span>Clients & Risk</span>
            </a>
        </li>
        <li>
            <a href="<?= $base_url ?>invoices/index.php" class="<?= (strpos($current_script, '/invoices/') !== false) ? 'active' : '' ?>">
                <i class="bi bi-file-earmark-text-fill"></i>
                <span>Invoices & FX</span>
            </a>
        </li>
        <li>
            <a href="<?= $base_url ?>payments/index.php" class="<?= (strpos($current_script, '/payments/') !== false) ? 'active' : '' ?>">
                <i class="bi bi-cash-stack"></i>
                <span>Collections & Receipts</span>
            </a>
        </li>
        <li>
            <a href="<?= $base_url ?>reports/index.php" class="<?= (strpos($current_script, '/reports/index.php') !== false) ? 'active' : '' ?>">
                <i class="bi bi-bar-chart-line-fill"></i>
                <span>Aging & Forecasting</span>
            </a>
        </li>
        <li>
            <a href="<?= $base_url ?>reports/audit_logs.php" class="<?= (strpos($current_script, 'audit_logs.php') !== false) ? 'active' : '' ?>">
                <i class="bi bi-journal-check"></i>
                <span>Audit Trail Logs</span>
            </a>
        </li>
        <li class="mt-4 pt-3 border-top border-secondary border-opacity-25">
            <a href="<?= $base_url ?>auth/logout.php" onclick="return confirm('Are you sure you want to logout?')">
                <i class="bi bi-box-arrow-right text-danger"></i>
                <span class="text-danger">Sign Out</span>
            </a>
        </li>
    </ul>
</div>