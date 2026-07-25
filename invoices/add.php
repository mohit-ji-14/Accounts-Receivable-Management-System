<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

require_once "../config/database.php";
require_once "../config/currency.php";

$page_title = "Generate Multi-Currency Invoice";
$page_header = "Create Enterprise Invoice";

$pre_customer = intval($_GET['customer_id'] ?? 0);

// Fetch Customers List for dropdown
try {
    $cust_stmt = $conn->query("SELECT customer_id, entity_id, customer_name, company_name, currency_code, credit_limit, credit_hold, risk_level FROM customers ORDER BY customer_name ASC");
    $customers = $cust_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Auto-generate invoice number
    $num_stmt = $conn->query("SELECT MAX(invoice_id) FROM invoices");
    $next_id = intval($num_stmt->fetchColumn()) + 1;
    $auto_inv_num = "INV-" . date('Y') . "-" . str_pad($next_id, 3, "0", STR_PAD_LEFT);
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customer_id    = intval($_POST['customer_id'] ?? 0);
    $invoice_number = trim($_POST['invoice_number'] ?? $auto_inv_num);
    $invoice_date   = $_POST['invoice_date'] ?? date('Y-m-d');
    $due_date       = $_POST['due_date'] ?? date('Y-m-d', strtotime('+30 days'));
    $currency_code  = trim($_POST['currency_code'] ?? 'INR');
    $fx_rate        = floatval($_POST['fx_rate'] ?? 1.0000);
    $notes          = trim($_POST['notes'] ?? '');

    $item_names  = $_POST['item_name'] ?? [];
    $quantities  = $_POST['quantity'] ?? [];
    $unit_prices = $_POST['unit_price'] ?? [];

    // Verify Credit Hold on Customer
    $hold_check = $conn->prepare("SELECT credit_hold, customer_name FROM customers WHERE customer_id = ?");
    $hold_check->execute([$customer_id]);
    $cust_info = $hold_check->fetch(PDO::FETCH_ASSOC);

    if ($cust_info && $cust_info['credit_hold'] == 1) {
        $error = "CRITICAL CREDIT BLOCK: " . htmlspecialchars($cust_info['customer_name']) . " is currently placed on CREDIT HOLD. New invoice generation is restricted!";
    } elseif ($customer_id <= 0) {
        $error = "Please select a client account.";
    } elseif (empty($item_names)) {
        $error = "Please add at least one line item to the invoice.";
    } else {
        try {
            $conn->beginTransaction();

            $grand_total = 0;
            $items_data = [];
            for ($i = 0; $i < count($item_names); $i++) {
                $name = trim($item_names[$i]);
                $qty  = max(1, intval($quantities[$i] ?? 1));
                $price = max(0, floatval($unit_prices[$i] ?? 0));
                $line_total = $qty * $price;
                
                if (!empty($name)) {
                    $grand_total += $line_total;
                    $items_data[] = [
                        'name' => $name,
                        'qty' => $qty,
                        'price' => $price,
                        'total' => $line_total
                    ];
                }
            }

            if (empty($items_data)) {
                throw new Exception("No valid item details entered.");
            }

            $status = ($due_date < date('Y-m-d')) ? 'Overdue' : 'Pending';

            // Insert Invoice Header
            $inv_ins = $conn->prepare("
                INSERT INTO invoices (entity_id, customer_id, invoice_number, invoice_date, due_date, currency_code, fx_rate, subtotal, tax_amount, total_amount, paid_amount, status, notes) 
                VALUES (1, ?, ?, ?, ?, ?, ?, ?, 0.00, ?, 0.00, ?, ?)
            ");
            $inv_ins->execute([$customer_id, $invoice_number, $invoice_date, $due_date, $currency_code, $fx_rate, $grand_total, $grand_total, $status, $notes]);
            $invoice_id = $conn->lastInsertId();

            // Insert Invoice Items
            $item_ins = $conn->prepare("
                INSERT INTO invoice_items (invoice_id, item_name, quantity, unit_price, total) 
                VALUES (?, ?, ?, ?, ?)
            ");
            foreach ($items_data as $item) {
                $item_ins->execute([$invoice_id, $item['name'], $item['qty'], $item['price'], $item['total']]);
            }

            // Log activity
            $log = $conn->prepare("INSERT INTO activity_logs (user_id, user_name, activity) VALUES (?, ?, ?)");
            $log->execute([$_SESSION['user_id'], $_SESSION['name'], "Created Invoice #$invoice_number for $currency_code " . number_format($grand_total, 2)]);

            $conn->commit();

            header("Location: view.php?id=" . $invoice_id . "&msg=" . urlencode("Invoice generated successfully!"));
            exit();

        } catch (Exception $e) {
            $conn->rollBack();
            $error = "Error saving invoice: " . $e->getMessage();
        }
    }
}

include "../includes/header.php";
include "../includes/sidebar.php";
?>

<div class="content">
    <?php include "../includes/navbar.php"; ?>

    <div class="container-fluid p-4">

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="fw-bold mb-1"><i class="bi bi-file-earmark-plus-fill text-primary me-2"></i> Create Multi-Currency Invoice</h4>
                <p class="text-muted small mb-0">Generate a billing invoice with currency FX rates and credit validation</p>
            </div>
            <a href="index.php" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i> Back to Invoices
            </a>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger py-3 border-0 mb-4 shadow-sm fw-semibold">
                <i class="bi bi-exclamation-octagon-fill me-2 fs-5"></i> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form action="add.php" method="POST" id="invoiceForm">
            <div class="row g-4">
                
                <div class="col-lg-12">
                    <div class="card-custom p-4">
                        <div class="row g-3">
                            
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold">Select Client Account <span class="text-danger">*</span></label>
                                <select name="customer_id" id="customerSelect" class="form-select" onchange="onCustomerChange()" required>
                                    <option value="" data-currency="INR" data-hold="0">-- Choose Account --</option>
                                    <?php foreach ($customers as $c): ?>
                                        <option value="<?= $c['customer_id'] ?>" 
                                                data-currency="<?= $c['currency_code'] ?? 'INR' ?>" 
                                                data-hold="<?= $c['credit_hold'] ?>"
                                                <?= ($pre_customer === $c['customer_id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($c['customer_name']) ?> (<?= htmlspecialchars($c['company_name'] ?: 'Individual') ?>) <?= $c['credit_hold'] ? '🛑 [CREDIT HOLD]' : '' ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label small fw-semibold">Invoice Number <span class="text-danger">*</span></label>
                                <input type="text" name="invoice_number" class="form-control fw-bold text-indigo" value="<?= htmlspecialchars($auto_inv_num) ?>" required>
                            </div>

                            <div class="col-md-2">
                                <label class="form-label small fw-semibold">Billing Currency</label>
                                <select name="currency_code" id="currencySelect" class="form-select" onchange="updateFXRate()">
                                    <option value="INR">INR (₹)</option>
                                    <option value="USD">USD ($)</option>
                                    <option value="EUR">EUR (€)</option>
                                    <option value="GBP">GBP (£)</option>
                                    <option value="AED">AED (د.إ)</option>
                                </select>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label small fw-semibold">FX Rate to Base (INR)</label>
                                <input type="number" step="0.0001" name="fx_rate" id="fxRateInput" class="form-control" value="1.0000" required>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Invoice Date</label>
                                <input type="date" name="invoice_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Payment Due Date</label>
                                <input type="date" name="due_date" class="form-control" value="<?= date('Y-m-d', strtotime('+30 days')) ?>" required>
                            </div>

                        </div>
                    </div>
                </div>

                <!-- Line Items -->
                <div class="col-lg-12">
                    <div class="card-custom p-4">
                        <h6 class="fw-bold mb-3"><i class="bi bi-list-task text-primary me-2"></i> Line Items</h6>
                        
                        <table class="table table-bordered align-middle" id="itemsTable">
                            <thead class="table-light">
                                <tr>
                                    <th>Item Description</th>
                                    <th width="120">Qty</th>
                                    <th width="160">Unit Price</th>
                                    <th width="180">Total Amount</th>
                                    <th width="60" class="text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody id="itemsBody">
                                <tr>
                                    <td>
                                        <input type="text" name="item_name[]" class="form-control" placeholder="Service or product description" required>
                                    </td>
                                    <td>
                                        <input type="number" name="quantity[]" class="form-control qty-input" value="1" min="1" oninput="calculateTotal()" required>
                                    </td>
                                    <td>
                                        <input type="number" step="0.01" name="unit_price[]" class="form-control price-input" placeholder="0.00" oninput="calculateTotal()" required>
                                    </td>
                                    <td>
                                        <input type="text" class="form-control line-total bg-light fw-bold" value="0.00" readonly>
                                    </td>
                                    <td class="text-center">
                                        <button type="button" class="btn btn-outline-danger btn-sm" onclick="removeRow(this)"><i class="bi bi-trash"></i></button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>

                        <div class="d-flex justify-content-between align-items-center mt-3">
                            <button type="button" class="btn btn-outline-primary btn-sm" onclick="addRow()">
                                <i class="bi bi-plus-circle me-1"></i> Add Line Entry
                            </button>

                            <div class="text-end">
                                <div class="fs-5 fw-bold text-dark">Grand Total: <span class="text-indigo" id="grandTotalLabel">0.00</span></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Notes & Submit -->
                <div class="col-lg-12">
                    <div class="card-custom p-4">
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Payment Terms & Wire Instructions</label>
                            <textarea name="notes" class="form-control" rows="2" placeholder="Bank SWIFT/IBAN or payment instructions..."></textarea>
                        </div>

                        <div class="d-flex justify-content-end gap-2">
                            <a href="index.php" class="btn btn-light px-4">Cancel</a>
                            <button type="submit" class="btn btn-primary px-4">Generate & Save Invoice</button>
                        </div>
                    </div>
                </div>

            </div>
        </form>

    </div>
</div>

<script>
const fxRates = {
    'INR': 1.0000,
    'USD': 85.0000,
    'EUR': 92.5000,
    'GBP': 108.2000,
    'AED': 23.1500
};

function onCustomerChange() {
    const select = document.getElementById('customerSelect');
    const option = select.options[select.selectedIndex];
    const curr = option.getAttribute('data-currency') || 'INR';
    const isHold = option.getAttribute('data-hold') === '1';

    if (isHold) {
        alert("CRITICAL WARNING: This client account is on CREDIT HOLD. Invoices will be blocked upon submission.");
    }

    document.getElementById('currencySelect').value = curr;
    updateFXRate();
}

function updateFXRate() {
    const curr = document.getElementById('currencySelect').value;
    document.getElementById('fxRateInput').value = (fxRates[curr] || 1.0).toFixed(4);
    calculateTotal();
}

function addRow() {
    const tbody = document.getElementById('itemsBody');
    const tr = document.createElement('tr');
    tr.innerHTML = `
        <td>
            <input type="text" name="item_name[]" class="form-control" placeholder="Service or product description" required>
        </td>
        <td>
            <input type="number" name="quantity[]" class="form-control qty-input" value="1" min="1" oninput="calculateTotal()" required>
        </td>
        <td>
            <input type="number" step="0.01" name="unit_price[]" class="form-control price-input" placeholder="0.00" oninput="calculateTotal()" required>
        </td>
        <td>
            <input type="text" class="form-control line-total bg-light fw-bold" value="0.00" readonly>
        </td>
        <td class="text-center">
            <button type="button" class="btn btn-outline-danger btn-sm" onclick="removeRow(this)"><i class="bi bi-trash"></i></button>
        </td>
    `;
    tbody.appendChild(tr);
}

function removeRow(btn) {
    const tbody = document.getElementById('itemsBody');
    if (tbody.children.length > 1) {
        btn.closest('tr').remove();
        calculateTotal();
    }
}

function calculateTotal() {
    let grandTotal = 0;
    const curr = document.getElementById('currencySelect').value;
    const rows = document.querySelectorAll('#itemsBody tr');
    
    rows.forEach(row => {
        const qty = parseFloat(row.querySelector('.qty-input').value) || 0;
        const price = parseFloat(row.querySelector('.price-input').value) || 0;
        const total = qty * price;
        
        row.querySelector('.line-total').value = total.toFixed(2);
        grandTotal += total;
    });

    document.getElementById('grandTotalLabel').innerText = curr + ' ' + grandTotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
</script>

<?php include "../includes/footer.php"; ?>
