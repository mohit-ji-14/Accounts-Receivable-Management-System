<?php
$user_name = $_SESSION['customer_name'] ?? 'Customer';
$user_role = $_SESSION['customer_role'] ?? 'Customer';

$initial = strtoupper(substr($user_name, 0, 1));

date_default_timezone_set("Asia/Kolkata");
$hour = date("H");

if ($hour < 12) {
    $greeting = "Good Morning";
} elseif ($hour < 17) {
    $greeting = "Good Afternoon";
} else {
    $greeting = "Good Evening";
}
?>

<div class="top-navbar">
    <div class="d-flex align-items-center gap-3">
        <div>
            <h1 class="page-title mb-1">
                <?= htmlspecialchars($page_header ?? 'Customer Portal') ?>
            </h1>
            <div class="text-muted small">
                <?= $greeting ?>,
                <strong class="text-white">
                    <?= htmlspecialchars($user_name) ?>
                </strong> 👋
            </div>
        </div>
        <span class="badge bg-primary-subtle text-primary border border-primary px-3 py-2 fw-semibold d-none d-md-inline-flex align-items-center gap-2">
            <i class="bi bi-person-badge-fill"></i>
            Customer Self-Service Portal
        </span>
    </div>

    <div class="user-profile">
        <div class="text-end me-3 d-none d-md-block">
            <div class="fw-bold text-white">
                <?= htmlspecialchars($user_name) ?>
            </div>
            <span class="badge bg-indigo-light text-indigo mt-1" style="font-size:0.7rem;">
                <?= htmlspecialchars($user_role) ?>
            </span>
        </div>
        <div class="user-avatar" title="<?= htmlspecialchars($user_name) ?>">
            <?= $initial ?>
        </div>
    </div>
</div>
