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
        $conn->beginTransaction();

        // Fetch payment details
        $stmt = $conn->prepare("SELECT invoice_id, amount FROM payments WHERE payment_id = ?");
        $stmt->execute([$id]);
        $pay = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($pay) {
            $invoice_id = $pay['invoice_id'];
            $amount = floatval($pay['amount']);

            // Delete payment
            $del = $conn->prepare("DELETE FROM payments WHERE payment_id = ?");
            $del->execute([$id]);

            // Re-calculate invoice paid_amount & status
            $sum_stmt = $conn->prepare("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE invoice_id = ?");
            $sum_stmt->execute([$invoice_id]);
            $new_paid = floatval($sum_stmt->fetchColumn());

            $inv_stmt = $conn->prepare("SELECT total_amount, due_date FROM invoices WHERE invoice_id = ?");
            $inv_stmt->execute([$invoice_id]);
            $inv = $inv_stmt->fetch(PDO::FETCH_ASSOC);

            if ($inv) {
                $total_amt = floatval($inv['total_amount']);
                $due_date  = $inv['due_date'];

                $new_status = 'Pending';
                if ($new_paid >= ($total_amt - 0.01)) {
                    $new_status = 'Paid';
                } elseif ($new_paid > 0) {
                    $new_status = 'Partially Paid';
                } elseif ($due_date < date('Y-m-d')) {
                    $new_status = 'Overdue';
                }

                $upd = $conn->prepare("UPDATE invoices SET paid_amount = ?, status = ? WHERE invoice_id = ?");
                $upd->execute([$new_paid, $new_status, $invoice_id]);
            }
        }

        $conn->commit();
        header("Location: index.php?msg=" . urlencode("Payment record deleted and invoice updated."));
        exit();

    } catch (Exception $e) {
        $conn->rollBack();
        die("Error deleting payment: " . $e->getMessage());
    }
} else {
    header("Location: index.php");
    exit();
}
