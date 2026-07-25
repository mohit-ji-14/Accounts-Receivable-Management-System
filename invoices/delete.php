<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

require_once "../config/database.php";

$id = intval($_GET['id'] ?? 0);
if ($id > 0) {
    try {
        $stmt = $conn->prepare("DELETE FROM invoices WHERE invoice_id = ?");
        $stmt->execute([$id]);
        header("Location: index.php?msg=" . urlencode("Invoice deleted successfully."));
        exit();
    } catch (PDOException $e) {
        die("Error deleting invoice: " . $e->getMessage());
    }
} else {
    header("Location: index.php");
    exit();
}
