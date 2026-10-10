<?php
require_once 'config.php';
requireLogin();

if (!isTempleAdmin()) {
    header('Location: expenses.php');
    exit();
}

$templeInfo = getCurrentTempleInfo();
$temple_id = getCurrentTempleId();

$message = '';
$messageType = '';
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Get expense details
$query = "SELECT e.* 
          FROM expenses e
          WHERE e.id = $id AND e.temple_id = $temple_id";
$result = $conn->query($query);

if ($result->num_rows === 0) {
    die("Expense not found or you don't have permission to edit it.");
}

$expense = $result->fetch_assoc();

// Get categories
$categoriesQuery = "SELECT * FROM expense_categories WHERE temple_id = $temple_id AND is_active = 1 ORDER BY category_name";
$categories = $conn->query($categoriesQuery);

// Get subcategories for the current category
$subcategoriesQuery = "SELECT * FROM expense_subcategories WHERE temple_id = $temple_id AND category_id = {$expense['category_id']} AND is_active = 1 ORDER BY subcategory_name";
$subcategories = $conn->query($subcategoriesQuery);

// Get vendors
$vendorsQuery = "SELECT * FROM vendors WHERE temple_id = $temple_id AND is_active = 1 ORDER BY vendor_name";
$vendors = $conn->query($vendorsQuery);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $expense_date = sanitizeInput($_POST['expense_date']);
    $category_id = intval($_POST['category_id']);
    $subcategory_id = !empty($_POST['subcategory_id']) ? intval($_POST['subcategory_id']) : null;
    $vendor_id = !empty($_POST['vendor_id']) ? intval($_POST['vendor_id']) : null;
    $description = sanitizeInput($_POST['description']);
    $amount = floatval($_POST['amount']);
    $gst_amount = floatval($_POST['gst_amount']);
    $total_amount = $amount + $gst_amount;
    $payment_mode = sanitizeInput($_POST['payment_mode']);
    $payment_reference = sanitizeInput($_POST['payment_reference']);
    $bill_number = sanitizeInput($_POST['bill_number']);
    $bill_date = !empty($_POST['bill_date']) ? sanitizeInput($_POST['bill_date']) : null;
    $payment_status = sanitizeInput($_POST['payment_status']);
    $paid_amount = floatval($_POST['paid_amount']);
    $remarks = sanitizeInput($_POST['remarks']);
    
    // Store old values for audit
    $old_values = $expense;
    
    // Update expense
    $updateQuery = "UPDATE expenses SET 
        expense_date = '$expense_date',
        category_id = $category_id,
        subcategory_id = " . ($subcategory_id ?: 'NULL') . ",
        vendor_id = " . ($vendor_id ?: 'NULL') . ",
        description = '$description',
        amount = $amount,
        gst_amount = $gst_amount,
        total_amount = $total_amount,
        payment_mode = '$payment_mode',
        payment_reference = '$payment_reference',
        bill_number = '$bill_number',
        bill_date = " . ($bill_date ? "'$bill_date'" : 'NULL') . ",
        payment_status = '$payment_status',
        paid_amount = $paid_amount,
        remarks = '$remarks',
        updated_at = NOW()
        WHERE id = $id AND temple_id = $temple_id";
    
    if ($conn->query($updateQuery)) {
        // Log the change
        logAudit('Expense Updated', 'expenses', $id, $old_values, [
            'expense_date' => $expense_date,
            'category_id' => $category_id,
            'amount' => $amount,
            'total_amount' => $total_amount,
            'payment_status' => $payment_status
        ]);
        
        $message = "Expense updated successfully!";
        $messageType = 'success';
        
        // Refresh expense data
        $result = $conn->query($query);
        $expense = $result->fetch_assoc();
        
        echo "<script>
            alert('Expense updated successfully!');
            window.location.href = 'view_expense.php?id=$id';
        </script>";
    } else {
        $message = "Error: " . $conn->error;
        $messageType = 'error';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Expense - <?php echo htmlspecialchars($templeInfo['temple_name']); ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f5f5;
        }
        .header {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            color: white;
            padding: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .header h1 {
            font-size: 24px;
        }
        .header .expense-no {
            font-size: 14px;
            opacity: 0.9;
            margin-top: 5px;
        }
        .nav {
            background: white;
            padding: 0;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .nav ul {
            list-style: none;
            display: flex;
        }
        .nav li {
            flex: 1;
        }
        .nav a {
            display: block;
            padding: 15px 20px;
            text-decoration: none;
            color: #333;
            text-align: center;
            border-right: 1px solid #eee;
            transition: background 0.3s;
        }
        .nav a:hover, .nav a.active {
            background: #e74c3c;
            color: white;
        }
        .container {
            max-width: 900px;
            margin: 30px auto;
            padding: 0 20px;
        }
        .card {
            background: white;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .card h2 {
            margin-bottom: 25px;
            color: #333;
            border-bottom: 2px solid #e74c3c;
            padding-bottom: 10px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #333;
        }
        input, select, textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            font-family: inherit;
        }
        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: #e74c3c;
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        .btn {
            padding: 12px 30px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            transition: background 0.3s;
            text-decoration: none;
            display: inline-block;
        }
        .btn-primary {
            background: #e74c3c;
            color: white;
        }
        .btn-primary:hover {
            background: #c0392b;
        }
        .btn-secondary {
            background: #6c757d;
            color: white;
            margin-left: 10px;
        }
        .btn-danger {
            background: #dc3545;
            color: white;
            margin-left: 10px;
        }
        .message {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .amount-display {
            font-size: 24px;
            font-weight: bold;
            color: #e74c3c;
            text-align: center;
            padding: 15px;
            background: #fff3f0;
            border-radius: 5px;
            margin-top: 10px;
        }
        .help-text {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
        .warning-box {
            background: #fff3cd;
            border: 1px solid #ffc107;
            color: #856404;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .warning-box strong {
            display: block;
            margin-bottom: 5px;
        }
        .info-box {
            background: #d1ecf1;
            border: 1px solid #0c5460;
            color: #0c5460;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .section-divider {
            border-top: 2px solid #ecf0f1;
            margin: 30px 0;
            padding-top: 20px;
        }
        .section-title {
            font-size: 18px;
            color: #e74c3c;
            margin-bottom: 15px;
            font-weight: 600;
        }
    </style>
</head>
<body>
<div id="google_translate_element"></div>
    <div class="header">
        <h1><?php echo htmlspecialchars($templeInfo['temple_name']); ?> - Edit Expense</h1>
        <div class="expense-no">Expense No: <?php echo $expense['expense_no']; ?></div>
    </div>
    
    <div class="nav">
        <ul>
            <li><a href="index.php">Dashboard</a></li>
            <li><a href="expenses.php" class="active">Expenses</a></li>
            <li><a href="reports.php">Reports</a></li>
            <li><a href="admin.php">Admin</a></li>
        </ul>
    </div>
    
    <div class="container">
        <div class="card">
            <h2>Edit Expense Details</h2>
            
            <?php if ($message): ?>
            <div class="message <?php echo $messageType; ?>">
                <?php echo $message; ?>
            </div>
            <?php endif; ?>
            
            <div class="warning-box">
                <strong>⚠️ Important:</strong>
                You are editing an existing expense. All changes will be logged in the audit trail. Make sure you have permission to modify this expense.
            </div>
            
            <div class="info-box">
                <strong>ℹ️ Original Entry:</strong>
                Created on <?php echo date('d/m/Y h:i A', strtotime($expense['created_at'])); ?>
                <?php if ($expense['updated_at'] != $expense['created_at']): ?>
                | Last updated: <?php echo date('d/m/Y h:i A', strtotime($expense['updated_at'])); ?>
                <?php endif; ?>
            </div>
            
            <form method="POST" id="expenseForm">
                <div class="section-title">Basic Information</div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="expense_date">Expense Date *</label>
                        <input type="date" id="expense_date" name="expense_date" value="<?php echo $expense['expense_date']; ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="bill_number">Bill/Invoice Number</label>
                        <input type="text" id="bill_number" name="bill_number" value="<?php echo htmlspecialchars($expense['bill_number']); ?>">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="category_id">Category *</label>
                        <select id="category_id" name="category_id" required onchange="loadSubcategories()">
                            <option value="">Select Category</option>
                            <?php while ($cat = $categories->fetch_assoc()): ?>
                            <option value="<?php echo $cat['id']; ?>" <?php echo $cat['id'] == $expense['category_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['category_name']); ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="subcategory_id">Sub-Category</label>
                        <select id="subcategory_id" name="subcategory_id">
                            <option value="">Select Sub-Category</option>
                            <?php while ($sub = $subcategories->fetch_assoc()): ?>
                            <option value="<?php echo $sub['id']; ?>" <?php echo $sub['id'] == $expense['subcategory_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($sub['subcategory_name']); ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="vendor_id">Vendor/Supplier</label>
                    <select id="vendor_id" name="vendor_id">
                        <option value="">Select Vendor (Optional)</option>
                        <?php while ($vendor = $vendors->fetch_assoc()): ?>
                        <option value="<?php echo $vendor['id']; ?>" <?php echo $vendor['id'] == $expense['vendor_id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($vendor['vendor_name']); ?>
                        </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="description">Description *</label>
                    <textarea id="description" name="description" rows="3" required><?php echo htmlspecialchars($expense['description']); ?></textarea>
                </div>
                
                <div class="section-divider"></div>
                <div class="section-title">Amount Details</div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="amount">Base Amount (₹) *</label>
                        <input type="number" id="amount" name="amount" step="0.01" min="0" value="<?php echo $expense['amount']; ?>" required onchange="calculateTotal()">
                    </div>
                    
                    <div class="form-group">
                        <label for="gst_amount">GST Amount (₹)</label>
                        <input type="number" id="gst_amount" name="gst_amount" step="0.01" min="0" value="<?php echo $expense['gst_amount']; ?>" onchange="calculateTotal()">
                    </div>
                </div>
                
                <div id="totalDisplay" class="amount-display">
                    Total Amount: ₹<span id="total_amount"><?php echo number_format($expense['total_amount'], 2); ?></span>
                </div>
                
                <div class="section-divider"></div>
                <div class="section-title">Payment Information</div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="payment_mode">Payment Mode *</label>
                        <select id="payment_mode" name="payment_mode" required>
                            <option value="Cash" <?php echo $expense['payment_mode'] == 'Cash' ? 'selected' : ''; ?>>Cash</option>
                            <option value="Bank Transfer" <?php echo $expense['payment_mode'] == 'Bank Transfer' ? 'selected' : ''; ?>>Bank Transfer</option>
                            <option value="Cheque" <?php echo $expense['payment_mode'] == 'Cheque' ? 'selected' : ''; ?>>Cheque</option>
                            <option value="UPI" <?php echo $expense['payment_mode'] == 'UPI' ? 'selected' : ''; ?>>UPI</option>
                            <option value="Card" <?php echo $expense['payment_mode'] == 'Card' ? 'selected' : ''; ?>>Card</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="payment_reference">Payment Reference</label>
                        <input type="text" id="payment_reference" name="payment_reference" value="<?php echo htmlspecialchars($expense['payment_reference']); ?>" placeholder="Transaction ID, Cheque No, etc.">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="payment_status">Payment Status *</label>
                        <select id="payment_status" name="payment_status" required onchange="togglePaidAmount()">
                            <option value="Paid" <?php echo $expense['payment_status'] == 'Paid' ? 'selected' : ''; ?>>Paid</option>
                            <option value="Pending" <?php echo $expense['payment_status'] == 'Pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="Partial" <?php echo $expense['payment_status'] == 'Partial' ? 'selected' : ''; ?>>Partial</option>
                            <option value="Cancelled" <?php echo $expense['payment_status'] == 'Cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                    </div>
                    
                    <div class="form-group" id="paid_amount_group" style="<?php echo $expense['payment_status'] == 'Partial' ? '' : 'display: none;'; ?>">
                        <label for="paid_amount">Paid Amount (₹)</label>
                        <input type="number" id="paid_amount" name="paid_amount" step="0.01" min="0" value="<?php echo $expense['paid_amount']; ?>">
                        <div class="help-text">For partial payments</div>
                    </div>
                    
                    <div class="form-group" id="bill_date_group" style="<?php echo $expense['payment_status'] == 'Partial' ? 'display: none;' : ''; ?>">
                        <label for="bill_date">Bill Date</label>
                        <input type="date" id="bill_date" name="bill_date" value="<?php echo $expense['bill_date']; ?>">
                    </div>
                </div>
                
                <div class="section-divider"></div>
                <div class="section-title">Additional Information</div>
                
                <div class="form-group">
                    <label for="remarks">Remarks</label>
                    <textarea id="remarks" name="remarks" rows="2"><?php echo htmlspecialchars($expense['remarks']); ?></textarea>
                </div>
                
                <div style="text-align: center; margin-top: 30px; padding-top: 30px; border-top: 2px solid #ecf0f1;">
                    <button type="submit" class="btn btn-primary">💾 Update Expense</button>
                    <a href="view_expense.php?id=<?php echo $id; ?>" class="btn btn-secondary">Cancel</a>
                    <?php if ($expense['payment_status'] != 'Cancelled'): ?>
                    <button type="button" class="btn btn-danger" onclick="if(confirm('Are you sure you want to cancel this expense?')) { document.getElementById('payment_status').value='Cancelled'; document.getElementById('expenseForm').submit(); }">Cancel Expense</button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function calculateTotal() {
            const amount = parseFloat(document.getElementById('amount').value) || 0;
            const gst = parseFloat(document.getElementById('gst_amount').value) || 0;
            const total = amount + gst;
            
            document.getElementById('total_amount').textContent = total.toFixed(2);
            
            // Auto-set paid amount if status is Paid
            if (document.getElementById('payment_status').value === 'Paid') {
                document.getElementById('paid_amount').value = total.toFixed(2);
            }
        }
        
        function togglePaidAmount() {
            const status = document.getElementById('payment_status').value;
            const paidGroup = document.getElementById('paid_amount_group');
            const billGroup = document.getElementById('bill_date_group');
            
            if (status === 'Partial') {
                paidGroup.style.display = 'block';
                billGroup.style.display = 'none';
            } else if (status === 'Paid') {
                paidGroup.style.display = 'none';
                billGroup.style.display = 'block';
                calculateTotal(); // Set paid amount
            } else {
                paidGroup.style.display = 'none';
                billGroup.style.display = 'block';
                document.getElementById('paid_amount').value = '0';
            }
        }
        
        function loadSubcategories() {
            const categoryId = document.getElementById('category_id').value;
            const subcategorySelect = document.getElementById('subcategory_id');
            const currentSubcategoryId = '<?php echo $expense['subcategory_id']; ?>';
            
            subcategorySelect.innerHTML = '<option value="">Select Sub-Category</option>';
            
            if (categoryId) {
                fetch(`get_subcategories.php?category_id=${categoryId}`)
                    .then(response => response.json())
                    .then(data => {
                        data.forEach(sub => {
                            const option = document.createElement('option');
                            option.value = sub.id;
                            option.textContent = sub.subcategory_name;
                            if (sub.id == currentSubcategoryId) {
                                option.selected = true;
                            }
                            subcategorySelect.appendChild(option);
                        });
                    });
            }
        }
        
        // Confirm before leaving if form is modified
        let formModified = false;
        document.getElementById('expenseForm').addEventListener('change', function() {
            formModified = true;
        });
        
        window.addEventListener('beforeunload', function (e) {
            if (formModified) {
                e.preventDefault();
                e.returnValue = '';
            }
        });
        
        document.getElementById('expenseForm').addEventListener('submit', function() {
            formModified = false;
        });
        
        // Initialize
        togglePaidAmount();
    </script>

    <script type="text/javascript">
function googleTranslateElementInit() {
  new google.translate.TranslateElement(
    {pageLanguage: 'en', includedLanguages: 'ml,ta,te,kn,hi', layout: google.translate.TranslateElement.InlineLayout.SIMPLE},
    'google_translate_element'
  );
}
</script>

<script type="text/javascript" 
src="//translate.google.com/translate_a/element.js?cb=googleTranslateElementInit">
</script>
</body>
</html>
