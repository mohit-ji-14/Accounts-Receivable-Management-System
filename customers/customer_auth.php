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
        // Find Customer User by Email (case-insensitive role check)
        $stmt = $conn->prepare("
            SELECT *
            FROM users
            WHERE LOWER(email) = LOWER(?)
            AND LOWER(role) = 'customer'
            LIMIT 1
        ");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // Fallback: If not found in users table, check if email exists directly in customers table
        if (!$user) {
            $c_stmt = $conn->prepare("SELECT * FROM customers WHERE LOWER(email) = LOWER(?) LIMIT 1");
            $c_stmt->execute([$email]);
            $cust_direct = $c_stmt->fetch(PDO::FETCH_ASSOC);

            if ($cust_direct) {
                // Temporary user structure for direct customer match if legacy account
                $user = [
                    'id' => null,
                    'full_name' => $cust_direct['customer_name'],
                    'email' => $cust_direct['email'],
                    'password' => 'customer123', // default demo fallback
                    'role' => 'Customer',
                    'customer_id' => $cust_direct['customer_id']
                ];
            }
        }

        // Check Customer Exists
        if (!$user) {
            header("Location: login.php?error=" . urlencode("Customer account not found."));
            exit();
        }

        // Verify Password (supports hashed & legacy plain-text passwords)
        $password_matches = ($password === $user["password"]) || password_verify($password, $user["password"]);
        if (!$password_matches) {
            header("Location: login.php?error=" . urlencode("Invalid password entered."));
            exit();
        }

        // Resolve customer_id if NULL in user table
        $cid = $user["customer_id"];
        if (empty($cid)) {
            $c_find = $conn->prepare("SELECT customer_id FROM customers WHERE LOWER(email) = LOWER(?) LIMIT 1");
            $c_find->execute([$email]);
            $found_cid = $c_find->fetchColumn();
            $cid = $found_cid ? $found_cid : 1;
        }

        // Create New Session ID
        session_regenerate_id(true);

        // Store Customer Data in Session
        $_SESSION["customer_user_id"] = $user["id"] ?? null;
        $_SESSION["customer_id"] = (int)$cid;
        $_SESSION["customer_name"] = $user["full_name"];
        $_SESSION["customer_email"] = $user["email"];
        $_SESSION["customer_role"] = "Customer";

        // Log Activity if user ID exists
        if (!empty($user['id'])) {
            $log_stmt = $conn->prepare("INSERT INTO activity_logs (user_id, user_name, activity) VALUES (?, ?, ?)");
            $log_stmt->execute([$user["id"], $user["full_name"], "Customer logged into portal"]);
        }

        header("Location: customer_dashboard.php");
        exit();

    } catch (PDOException $e) {
        header("Location: login.php?error=" . urlencode("Database Error: " . $e->getMessage()));
        exit();
    }

} else {
    header("Location: login.php");
    exit();
}
