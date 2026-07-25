<?php
session_start();
require_once "../config/database.php";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email = trim($_POST["email"] ?? '');
    $password = trim($_POST["password"] ?? '');

    if (empty($email) || empty($password)) {
        header("Location: ../index.php?error=Please fill in all fields.");
        exit;
    }

    try {
        $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            // Support both plain text legacy & hashed passwords
            if ($password === $user["password"] || password_verify($password, $user["password"])) {

                $_SESSION["user_id"] = $user["id"];
                $_SESSION["name"] = $user["full_name"];
                $_SESSION["role"] = $user["role"];
                $_SESSION["email"] = $user["email"];

                // Log Activity
                $log_stmt = $conn->prepare("INSERT INTO activity_logs (user_id, activity) VALUES (?, ?)");
                $log_stmt->execute([$user["id"], "User logged in"]);

                header("Location: ../dashboard.php");
                exit;

            } else {
                header("Location: ../index.php?error=Invalid password entered.");
                exit;
            }
        } else {
            header("Location: ../index.php?error=User account not found.");
            exit;
        }
    } catch (PDOException $e) {
        header("Location: ../index.php?error=Database connection error: " . urlencode($e->getMessage()));
        exit;
    }
} else {
    header("Location: ../index.php");
    exit;
}