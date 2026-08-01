<?php
session_start();

// Unset customer specific session values
unset($_SESSION['customer_id']);
unset($_SESSION['customer_user_id']);
unset($_SESSION['customer_name']);
unset($_SESSION['customer_email']);
unset($_SESSION['customer_role']);

header("Location: login.php?msg=" . urlencode("Logged out successfully."));
exit();
