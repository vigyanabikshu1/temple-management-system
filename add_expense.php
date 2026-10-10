<?php
require_once 'config.php';
requireLogin();

$templeInfo = getCurrentTempleInfo();
$temple_id = getCurrentTempleId();

$message = '';
$messageType = '';

// Get categories
$categoriesQuery = "SELECT * FROM expense_categories WHERE temple_id = $temple_id AND is_active = 1 ORDER BY category_name";
$categories = $conn->query($categoriesQuery);

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
    
    // Generate expense number
    $prefix = $templeInfo['temple_code'] . 'EXP' . date('Ymd');
    $expNoQuery = "SELECT expense_no FROM expenses WHERE temple_id = $temple_id AND expense_no LIKE '$prefix%' ORDER BY id DESC LIMIT 1";
    $expNoResult = $conn->query($expNoQuery);
    
    if ($expNoResult && $expNoResult->num_rows > 0) {
        $row = $expNoResult->fetch_assoc();
        $lastNo = intval(substr($row['expense_no'], -4));
        $newNo = $lastNo + 1;
    } else {
        $newNo = 1;
    }
    $expense_no = $prefix . str_pad($newNo, 4, '0', STR_PAD_LEFT);
    
    // Insert expense
    $insertQuery = "INSERT INTO expenses (
        temple_id, expense_no, expense_date, category_id, subcategory_id, vendor_id,
        description, amount, gst_amount, total_amount, payment_mode, payment_reference,
        bill_number, bill_date, payment_status, paid_amount, remarks, created_by
    ) VALUES (
        $temple_id, '$expense_no', '$expense_date', $category_id, " . ($subcategory_id ?: 'NULL') . ", " . ($vendor_id ?: 'NULL') . ",
        '$description', $amount, $gst_amount, $total_amount, '$payment_mode', '$payment_reference',
        '$bill_number', " . ($bill_date ? "'$bill_date'" : 'NULL') . ", '$payment_status', $paid_amount, '$remarks', {$_SESSION['user_id']}
    )";
    
    if ($conn->query($insertQuery)) {
        $expense_id = $conn->insert_id;
        logAudit('Expense Created', 'expenses', $expense_id);
        
        $message = "Expense added successfully! Expense No: $expense_no";
        $messageType = 'success';
        
        echo "<script>
            alert('Expense added successfully!');
            window.location.href = 'expenses.php';
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
    <title>Add Expense - <?php echo htmlspecialchars($templeInfo['temple_name']); ?></title>
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
    </style>
</head>
<body>
<div id="google_translate_element"></div>
    <div class="header">
        <h1><?php echo htmlspecialchars($templeInfo['temple_name']); ?> - Add Expense</h1>
    </div>
    
    <div class="nav">
        <ul>
            <li><a href="index.php">Dashboard</a></li>
            <li><a href="expenses.php" class="active">Expenses</a></li>
            <li><a href="reports.php">Reports</a></li>
            <?php if (isTempleAdmin()): ?>
            <li><a href="admin.php">Admin</a></li>
            <?php endif; ?>
        </ul>
    </div>
    
    <div class="container">
        <div class="card">
            <h2>Add New Expense</h2>
            
            <?php if ($message): ?>
            <div class="message <?php echo $messageType; ?>">
                <?php echo $message; ?>
            </div>
            <?php endif; ?>
            
            <form method="POST" id="expenseForm">
                <div class="form-row">
                    <div class="form-group">
                        <label for="expense_date">Expense Date *</label>
                        <input type="date" id="expense_date" name="expense_date" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="bill_number">Bill/Invoice Number</label>
                        <input type="text" id="bill_number" name="bill_number">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="category_id">Category *</label>
                        <select id="category_id" name="category_id" required onchange="loadSubcategories()">
                            <option value="">Select Category</option>
                            <?php while ($cat = $categories->fetch_assoc()): ?>
                            <option value="<?php echo $cat['id']; ?>">
                                <?php echo htmlspecialchars($cat['category_name']); ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="subcategory_id">Sub-Category</label>
                        <select id="subcategory_id" name="subcategory_id">
                            <option value="">Select Sub-Category</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="vendor_id">Vendor/Supplier</label>
                    <select id="vendor_id" name="vendor_id">
                        <option value="">Select Vendor (Optional)</option>
                        <?php while ($vendor = $vendors->fetch_assoc()): ?>
                        <option value="<?php echo $vendor['id']; ?>">
                            <?php echo htmlspecialchars($vendor['vendor_name']); ?>
                        </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="description">Description *</label>
                    <textarea id="description" name="description" rows="3" required></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="amount">Amount (₹) *</label>
                        <input type="number" id="amount" name="amount" step="0.01" min="0" required onchange="calculateTotal()">
                    </div>
                    
                    <div class="form-group">
                        <label for="gst_amount">GST Amount (₹)</label>
                        <input type="number" id="gst_amount" name="gst_amount" step="0.01" min="0" value="0" onchange="calculateTotal()">
                    </div>
                </div>
                
                <div id="totalDisplay" class="amount-display" style="display: none;">
                    Total Amount: ₹<span id="total_amount">0.00</span>
                </div>
                
                <div class="form-row" style="margin-top: 20px;">
                    <div class="form-group">
                        <label for="payment_mode">Payment Mode *</label>
                        <select id="payment_mode" name="payment_mode" required>
                            <option value="Cash">Cash</option>
                            <option value="Bank Transfer">Bank Transfer</option>
                            <option value="Cheque">Cheque</option>
                            <option value="UPI">UPI</option>
                            <option value="Card">Card</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="payment_reference">Payment Reference</label>
                        <input type="text" id="payment_reference" name="payment_reference" placeholder="Transaction ID, Cheque No, etc.">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="payment_status">Payment Status *</label>
                        <select id="payment_status" name="payment_status" required onchange="togglePaidAmount()">
                            <option value="Paid">Paid</option>
                            <option value="Pending">Pending</option>
                            <option value="Partial">Partial</option>
                        </select>
                    </div>
                    
                    <div class="form-group" id="paid_amount_group" style="display: none;">
                        <label for="paid_amount">Paid Amount (₹)</label>
                        <input type="number" id="paid_amount" name="paid_amount" step="0.01" min="0" value="0">
                        <div class="help-text">For partial payments</div>
                    </div>
                    
                    <div class="form-group" id="bill_date_group">
                        <label for="bill_date">Bill Date</label>
                        <input type="date" id="bill_date" name="bill_date">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="remarks">Remarks</label>
                    <textarea id="remarks" name="remarks" rows="2"></textarea>
                </div>
                
                <div style="text-align: center; margin-top: 30px;">
                    <button type="submit" class="btn btn-primary">Add Expense</button>
                    <button type="button" class="btn btn-secondary" onclick="window.location.href='expenses.php'">Cancel</button>
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
            document.getElementById('totalDisplay').style.display = total > 0 ? 'block' : 'none';
            
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
            
            subcategorySelect.innerHTML = '<option value="">Select Sub-Category</option>';
            
            if (categoryId) {
                fetch(`get_subcategories.php?category_id=${categoryId}`)
                    .then(response => response.json())
                    .then(data => {
                        data.forEach(sub => {
                            const option = document.createElement('option');
                            option.value = sub.id;
                            option.textContent = sub.subcategory_name;
                            subcategorySelect.appendChild(option);
                        });
                    });
            }
        }
        
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
