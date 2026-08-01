<?php
session_start();

if (!isset($_SESSION["customer_id"])) {
    header("Location: login.php");
    exit();
}

require_once "../config/database.php";

$customer_id = $_SESSION["customer_id"];

try {

    $stmt = $conn->prepare("
        SELECT *
        FROM customers
        WHERE customer_id = ?
        LIMIT 1
    ");

    $stmt->execute([$customer_id]);

    $customer = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$customer) {

        session_destroy();

        header("Location: login.php");

        exit();
    }

} catch(PDOException $e){

    die("Database Error : ".$e->getMessage());

}

$page_title = "Customer Dashboard";
$page_header = "Customer Dashboard";

include "../includes/header.php";
include "customers_sidebar.php";
?>

<div class="content">

    <?php include "customer_navbar.php"; ?>

    <?php

    $stmt = $conn->prepare("
        SELECT
COALESCE(SUM(total_amount-paid_amount),0)
FROM invoices
WHERE customer_id=?
AND status!='Paid'
");

$stmt->execute([$customer_id]);

$outstanding = $stmt->fetchColumn();
/*total invoic*/
$stmt = $conn->prepare("
SELECT COUNT(*)
FROM invoices
WHERE customer_id=?
");

$stmt->execute([$customer_id]);

$total_invoices = $stmt->fetchColumn();
/*paid*/
$stmt = $conn->prepare("
SELECT COUNT(*)
FROM invoices
WHERE customer_id=?
AND status='Paid'
");

$stmt->execute([$customer_id]);

$paid_invoices = $stmt->fetchColumn();
/*overdue*/

$stmt = $conn->prepare("
SELECT COUNT(*)
FROM invoices
WHERE customer_id=?
AND status='Overdue'
");

$stmt->execute([$customer_id]);

$overdue = $stmt->fetchColumn();
/* Recent Invoices */
$stmt = $conn->prepare("
    SELECT invoice_id, invoice_number, invoice_date, due_date, total_amount, paid_amount, currency_code, status
    FROM invoices
    WHERE customer_id = ?
    ORDER BY invoice_date DESC
    LIMIT 5
");
$stmt->execute([$customer_id]);
$recent_invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* Recent Payments */

$stmt = $conn->prepare("
SELECT
p.payment_id,
p.payment_date,
p.amount,
p.currency_code,
p.payment_method,
i.invoice_number
FROM payments p
INNER JOIN invoices i
ON p.invoice_id = i.invoice_id
WHERE i.customer_id = ?
ORDER BY p.payment_date DESC
LIMIT 5");

$stmt->execute([$customer_id]);

$recent_payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

                <div class="container-fluid p-4">

                    <div class="row g-4">

                        <div class="col-xl-3 col-md-6">

                            <div class="stat-card">

                                <div class="d-flex justify-content-between align-items-start">

                                    <div>

                                        <div class="stat-title">

                                            Outstanding Amount

                                        </div>

                                        <div class="stat-value text-danger">

                                            <?= format_currency($outstanding, $customer['currency_code'] ?? 'INR') ?>

                                        </div>

                                    </div>

                                <div class="stat-icon bg-rose-light">

                                    <i class="bi bi-wallet2"></i>

                                </div>

                            </div>

                            <div class="small text-muted mt-2">

                                Pending Payment

                            </div>

                        </div>
                        

                    </div>
                    <div class="col-xl-3 col-md-6">

    <div class="stat-card">

        <div class="d-flex justify-content-between align-items-start">

            <div>

                <div class="stat-title">
                    Total Invoices
                </div>

                <div class="stat-value text-indigo">
                    <?= $total_invoices ?>
                </div>

            </div>

            <div class="stat-icon bg-indigo-light">
                <i class="bi bi-file-earmark-text"></i>
            </div>

        </div>

        <div class="small text-muted mt-2">
            Generated Invoices
        </div>

    </div>

</div>
<div class="col-xl-3 col-md-6">

    <div class="stat-card">

        <div class="d-flex justify-content-between align-items-start">

            <div>

                <div class="stat-title">
                    Paid Invoices
                </div>

                <div class="stat-value text-success">
                    <?= $paid_invoices ?>
                </div>

            </div>

            <div class="stat-icon bg-emerald-light">
                <i class="bi bi-check-circle-fill"></i>
            </div>

        </div>

        <div class="small text-muted mt-2">
            Successfully Paid
        </div>

    </div>

</div>
<div class="col-xl-3 col-md-6">

    <div class="stat-card">

        <div class="d-flex justify-content-between align-items-start">

            <div>

                <div class="stat-title">
                    Overdue Invoices
                </div>

                <div class="stat-value text-warning">
                    <?= $overdue ?>
                </div>

            </div>

            <div class="stat-icon bg-amber-light">
                <i class="bi bi-exclamation-triangle-fill"></i>
            </div>

        </div>

        <div class="small text-muted mt-2">
            Immediate Attention
        </div>

    </div>

</div>
<div class="card-custom mt-4">

    <div class="p-3 border-bottom border-secondary border-opacity-25">

        <h5 class="fw-bold text-white mb-0">
            <i class="bi bi-file-earmark-text text-indigo me-2"></i>
            Recent Invoices
        </h5>

    </div>

    <div class="table-responsive">

        <table class="table table-custom mb-0">

            <thead>

                <tr>
                    <th>Invoice No</th>
                    <th>Date</th>
                    <th>Due Date</th>
                    <th>Amount</th>
                    <th>Status</th>
                    <th class="text-end">Action</th>
                </tr>

            </thead>

            <tbody>

            <?php if(!empty($recent_invoices)): ?>

                <?php foreach($recent_invoices as $inv): 
                    $badge_class = 'bg-primary';
                    if ($inv['status'] === 'Paid') $badge_class = 'bg-success';
                    elseif ($inv['status'] === 'Overdue') $badge_class = 'bg-danger';
                    elseif ($inv['status'] === 'Partially Paid') $badge_class = 'bg-warning text-dark';
                ?>

                <tr>

                    <td class="fw-bold text-white"><?= htmlspecialchars($inv['invoice_number']) ?></td>

                    <td><?= date('d M Y',strtotime($inv['invoice_date'])) ?></td>

                    <td><?= date('d M Y',strtotime($inv['due_date'])) ?></td>

                    <td>
                        <?= format_currency($inv['total_amount'], $inv['currency_code']) ?>
                    </td>

                    <td>
                        <span class="badge <?= $badge_class ?> px-2 py-1">
                            <?= htmlspecialchars($inv['status']) ?>
                        </span>
                    </td>

                    <td class="text-end">
                        <a href="view_invoice.php?id=<?= $inv['invoice_id'] ?>" class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-eye-fill me-1"></i> View
                        </a>
                    </td>

                </tr>

                <?php endforeach; ?>

            <?php else: ?>

                <tr>

                    <td colspan="5" class="text-center py-4">

                        No Invoice Found

                    </td>

                </tr>

            <?php endif; ?>

            </tbody>

        </table>

    </div>

</div>
<div class="card-custom mt-4">

    <div class="p-3 border-bottom border-secondary border-opacity-25">

        <h5 class="fw-bold text-white mb-0">
            <i class="bi bi-credit-card text-success me-2"></i>
            Recent Payments
        </h5>

    </div>

    <div class="table-responsive">

        <table class="table table-custom mb-0">

            <thead>

                <tr>

                    <th>Date</th>

                    <th>Invoice</th>

                    <th>Amount</th>

                    <th>Method</th>

                </tr>

            </thead>

            <tbody>

            <?php if(!empty($recent_payments)): ?>

            <?php foreach($recent_payments as $pay): ?>

            <tr>

                <td><?= date('d M Y',strtotime($pay['payment_date'])) ?></td>

                <td><?= htmlspecialchars($pay['invoice_number']) ?></td>

                <td>
                    <?= number_format($pay['amount'],2)." ".$pay['currency_code'] ?>
                </td>

                <td><?= htmlspecialchars($pay['payment_method']) ?></td>

            </tr>

            <?php endforeach; ?>

            <?php else: ?>

            <tr>

                <td colspan="4" class="text-center py-4">

                    No Payments Found

                </td>

            </tr>

            <?php endif; ?>

            </tbody>

        </table>

    </div>

</div>


                </div>

            </div>

</div>

<?php include "../includes/footer.php"; ?>