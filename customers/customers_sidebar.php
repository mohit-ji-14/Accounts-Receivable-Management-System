<?php
$current_page = basename($_SERVER['PHP_SELF']);
?>
<div class="sidebar">
    <div class="logo">
        <i class="bi bi-shield-check fs-3 text-emerald me-2"></i>
        <h3 class="mb-0">ARMS Portal</h3>
    </div>
    <div class="px-3 mb-3">
        <span class="badge bg-emerald-light text-emerald w-100 py-2 fw-semibold">
            <i class="bi bi-building me-1"></i> Customer Self-Service
        </span>
    </div>

    <ul class="sidebar-menu">
        <li>
            <a href="customer_dashboard.php" class="<?= ($current_page == 'customer_dashboard.php') ? 'active' : '' ?>">
                <i class="bi bi-grid-fill"></i>
                Dashboard
            </a>
        </li>
        <li>
            <a href="customer_invoice.php" class="<?= in_array($current_page, ['customer_invoice.php', 'view_invoice.php']) ? 'active' : '' ?>">
                <i class="bi bi-receipt-cutoff"></i>
                My Invoices
            </a>
        </li>
        <li>
            <a href="customer_payments.php" class="<?= ($current_page == 'customer_payments.php') ? 'active' : '' ?>">
                <i class="bi bi-credit-card-2-front-fill"></i>
                My Payments
            </a>
        </li>
        <li>
            <a href="customer_statement.php" class="<?= ($current_page == 'customer_statement.php') ? 'active' : '' ?>">
                <i class="bi bi-file-earmark-spreadsheet-fill"></i>
                Account Statement
            </a>
        </li>
        <li>
            <a href="customer_profile.php" class="<?= ($current_page == 'customer_profile.php') ? 'active' : '' ?>">
                <i class="bi bi-person-bounding-box"></i>
                My Profile
            </a>
        </li>
        <li class="mt-4 border-top border-secondary border-opacity-25 pt-2">
            <a href="customer_logout.php" class="text-danger">
                <i class="bi bi-box-arrow-right text-danger"></i>
                Sign Out
            </a>
        </li>
    </ul>
</div>