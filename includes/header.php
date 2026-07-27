<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Generate CSRF Token for form submissions
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// 2. Helper function to validate entity ID against whitelist
if (!function_exists('validate_entity_id')) {
    function validate_entity_id($raw_id) {
        $allowed = [0, 1, 2, 3];
        $val = filter_var($raw_id, FILTER_VALIDATE_INT);
        return ($val !== false && in_array($val, $allowed, true)) ? $val : 0;
    }
}

// 3. Process entity_id with CSRF protection for POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['entity_id'])) {
    $submitted_token = $_POST['csrf_token'] ?? '';
    if (!empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $submitted_token)) {
        $_SESSION['selected_entity'] = validate_entity_id($_POST['entity_id']);
    }
} elseif (isset($_GET['entity_id'])) {
    $_SESSION['selected_entity'] = validate_entity_id($_GET['entity_id']);
    if (!headers_sent()) {
        $clean_uri = preg_replace('/([?&])entity_id=[^&]*(&|$)/', '$1', $_SERVER['REQUEST_URI']);
        $clean_uri = rtrim($clean_uri, '?&');
        if (empty($clean_uri)) {
            $clean_uri = 'index.php';
        }
        $clean_uri = filter_var($clean_uri, FILTER_SANITIZE_URL);
        header("Location: " . $clean_uri);
        exit();
    }
}

$base_url = '/ARMS/';
require_once __DIR__ . '/../config/currency.php';
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($page_title) ? htmlspecialchars($page_title) . ' | ARMS Enterprise Suite' : 'ARMS Enterprise MNC System' ?></title>
    
    <!-- Google Fonts: Plus Jakarta Sans & Outfit -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Bootstrap 5.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    
    <!-- Chart.js CDN for Enterprise Financial Charts -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

    <!-- Custom Glassmorphic Dashboard Styles -->
    <link rel="stylesheet" href="<?= $base_url ?>assets/css/dashboard.css">
</head>
<body>