<?php
session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: index.php");
    exit;
}

include "includes/header.php";
include "includes/sidebar.php";
?>

<div class="content">

<?php include "includes/navbar.php"; ?>

<div class="container-fluid mt-4">

    <h2>Dashboard</h2>

    <div class="row">

    <div class="col-md-3 mb-4">
        <div class="card shadow border-0">
            <div class="card-body">
                <h6 class="text-muted">Total Customers</h6>
                <h2>0</h2>
            </div>
        </div>
    </div>

    <div class="col-md-3 mb-4">
        <div class="card shadow border-0">
            <div class="card-body">
                <h6 class="text-muted">Total Invoices</h6>
                <h2>0</h2>
            </div>
        </div>
    </div>

    <div class="col-md-3 mb-4">
        <div class="card shadow border-0">
            <div class="card-body">
                <h6 class="text-muted">Payments</h6>
                <h2>0</h2>
            </div>
        </div>
    </div>

    <div class="col-md-3 mb-4">
        <div class="card shadow border-0">
            <div class="card-body">
                <h6 class="text-muted">Outstanding</h6>
                <h2>0</h2>
            </div>
        </div>
    </div>

</div>
</div>

</div>

</body>
</html>