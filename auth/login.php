<?php
session_start();
require_once "../config/database.php";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $email = trim($_POST["email"]);
    $password = trim($_POST["password"]);

    if (empty($email) || empty($password)) {
        die("Please fill all fields.");
    }

    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {

        // Temporary login (abhi plain password)
        if ($password === $user["password"]) {

            $_SESSION["user_id"] = $user["id"];
            $_SESSION["name"] = $user["full_name"];
            $_SESSION["role"] = $user["role"];

            header("Location: ../dashboard.php");
            exit;

        } else {
            echo "Invalid Password";
        }

    } else {
        echo "User Not Found";
    }

}
?>