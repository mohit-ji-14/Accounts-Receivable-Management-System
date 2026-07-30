<?php
session_start();
require_once "../config/database.php";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // Form Data
    $email = trim($_POST["email"] ?? '');
    $password = trim($_POST["password"] ?? '');

    // Validate Empty Fields
    if (empty($email) || empty($password)) {
        header("Location: login.php?error=" . urlencode("Please enter email and password."));
        exit();
    }
    try {

    // Find Customer by Email
    $stmt = $conn->prepare("
        SELECT *
        FROM users
        WHERE email = ?
        AND role = 'customer'
        LIMIT 1
    ");

    $stmt->execute([$email]);

    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    // Check Customer Exists
if (!$user) {
    header("Location: login.php?error=" . urlencode("Customer account not found."));
    exit();
}
// Verify Password
if (!password_verify($password, $user["password"])) {
    header("Location: login.php?error=" . urlencode("Invalid password."));
    exit();
}
// Create New Session ID
session_regenerate_id(true);
// Store Customer Data in Session
$_SESSION["customer_id"] = $user["customer_id"];
$_SESSION["customer_name"] = $user["full_name"];
$_SESSION["customer_email"] = $user["email"];
$_SESSION["customer_role"] = $user["role"];
header("Location: dashboard.php");
exit();

} catch (PDOException $e) {

    header("Location: login.php?error=" . urlencode("Database Error"));
    exit();

}

} else {
    header("Location: login.php");
    exit();
}
