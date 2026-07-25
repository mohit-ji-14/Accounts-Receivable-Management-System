<?php
$user_name = $_SESSION['name'] ?? 'Administrator';
$user_role = $_SESSION['role'] ?? 'System Admin';
$initial = strtoupper(substr($user_name, 0, 1));
$selected_entity = $_SESSION['selected_entity'] ?? 0;
?>
<div class="top-navbar">
    <div class="d-flex align-items-center gap-3">
        <h1 class="page-title me-3"><?= $page_header ?? 'Executive Dashboard' ?></h1>
        
        <!-- Live System Badge -->
        <span class="badge bg-success-subtle text-success border border-success px-3 py-2 fw-semibold d-none d-md-inline-flex align-items-center gap-2">
            <span class="spinner-grow spinner-grow-sm text-success" style="width: 8px; height: 8px;" role="status"></span>
            System Live
        </span>

        <!-- Entity / Subsidiary Switcher -->
        <form method="GET" action="" class="d-none d-lg-block">
            <select name="entity_id" class="form-select form-select-sm" onchange="this.form.submit()" style="min-width: 220px;">
                <option value="0" <?= $selected_entity == 0 ? 'selected' : '' ?>>🌐 Global Consolidated View</option>
                <option value="1" <?= $selected_entity == 1 ? 'selected' : '' ?>>🇮🇳 ARMS Global HQ (India)</option>
                <option value="2" <?= $selected_entity == 2 ? 'selected' : '' ?>>🇺🇸 ARMS North America Inc.</option>
                <option value="3" <?= $selected_entity == 3 ? 'selected' : '' ?>>🇩🇪 ARMS Europe GmbH</option>
            </select>
        </form>
    </div>
    
    <div class="user-profile">
        <div class="text-end me-3 d-none d-md-block">
            <div class="fw-bold text-white leading-tight" style="font-size: 0.95rem;"><?= htmlspecialchars($user_name) ?></div>
            <span class="badge bg-indigo-light text-indigo mt-1" style="font-size: 0.7rem; letter-spacing: 0.5px;"><?= htmlspecialchars($user_role) ?></span>
        </div>
        <div class="user-avatar">
            <?= $initial ?>
        </div>
    </div>
</div>
