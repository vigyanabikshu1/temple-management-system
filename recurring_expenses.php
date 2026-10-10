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

// Handle Actions
if (isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'add_recurring') {
        $category_id = intval($_POST['category_id']);
        $subcategory_id = !empty($_POST['subcategory_id']) ? intval($_POST['subcategory_id']) : null;
        $vendor_id = !empty($_POST['vendor_id']) ? intval($_POST['vendor_id']) : null;
        $description = sanitizeInput($_POST['description']);
        $amount = floatval($_POST['amount']);
        $frequency = sanitizeInput($_POST['frequency']);
        $start_date = sanitizeInput($_POST['start_date']);
        $end_date = !empty($_POST['end_date']) ? sanitizeInput($_POST['end_date']) : null;
        $payment_mode = sanitizeInput($_POST['payment_mode']);
        $auto_create = isset($_POST['auto_create']) ? 1 : 0;
        
        // Calculate next due date
        $next_due_date = $start_date;
        
        $query = "INSERT INTO recurring_expenses 
                  (temple_id, category_id, subcategory_id, vendor_id, description, amount, frequency, start_date, end_date, next_due_date, payment_mode, auto_create, created_by)
                  VALUES 
                  ($temple_id, $category_id, " . ($subcategory_id ?: 'NULL') . ", " . ($vendor_id ?: 'NULL') . ", '$description', $amount, '$frequency', '$start_date', " . ($end_date ? "'$end_date'" : 'NULL') . ", '$next_due_date', '$payment_mode', $auto_create, {$_SESSION['user_id']})";
        
        if ($conn->query($query)) {
            logAudit('Recurring Expense Created', 'recurring_expenses', $conn->insert_id);
            $message = "Recurring expense added successfully!";
            $messageType = 'success';
        } else {
            $message = "Error: " . $conn->error;
            $messageType = 'error';
        }
    }
    
    elseif ($action === 'update_recurring') {
        $id = intval($_POST['id']);
        $category_id = intval($_POST['category_id']);
        $subcategory_id = !empty($_POST['subcategory_id']) ? intval($_POST['subcategory_id']) : null;
        $vendor_id = !empty($_POST['vendor_id']) ? intval($_POST['vendor_id']) : null;
        $description = sanitizeInput($_POST['description']);
        $amount = floatval($_POST['amount']);
        $frequency = sanitizeInput($_POST['frequency']);
        $end_date = !empty($_POST['end_date']) ? sanitizeInput($_POST['end_date']) : null;
        $payment_mode = sanitizeInput($_POST['payment_mode']);
        $auto_create = isset($_POST['auto_create']) ? 1 : 0;
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        $query = "UPDATE recurring_expenses SET
                  category_id=$category_id,
                  subcategory_id=" . ($subcategory_id ?: 'NULL') . ",
                  vendor_id=" . ($vendor_id ?: 'NULL') . ",
                  description='$description',
                  amount=$amount,
                  frequency='$frequency',
                  end_date=" . ($end_date ? "'$end_date'" : 'NULL') . ",
                  payment_mode='$payment_mode',
                  auto_create=$auto_create,
                  is_active=$is_active
                  WHERE id=$id AND temple_id=$temple_id";
        
        if ($conn->query($query)) {
            logAudit('Recurring Expense Updated', 'recurring_expenses', $id);
            $message = "Recurring expense updated successfully!";
            $messageType = 'success';
        }
    }
    
    elseif ($action === 'delete_recurring') {
        $id = intval($_POST['id']);
        $query = "DELETE FROM recurring_expenses WHERE id=$id AND temple_id=$temple_id";
        
        if ($conn->query($query)) {
            logAudit('Recurring Expense Deleted', 'recurring_expenses', $id);
            $message = "Recurring expense deleted successfully!";
            $messageType = 'success';
        }
    }
    
    elseif ($action === 'create_expense_now') {
        $recurring_id = intval($_POST['recurring_id']);
        
        // Get recurring expense details
        $recQuery = "SELECT * FROM recurring_expenses WHERE id=$recurring_id AND temple_id=$temple_id";
        $recResult = $conn->query($recQuery);
        $rec = $recResult->fetch_assoc();
        
        if ($rec) {
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
            
            // Create expense
            $insertExp = "INSERT INTO expenses 
                (temple_id, expense_no, expense_date, category_id, subcategory_id, vendor_id, description, amount, total_amount, payment_mode, payment_status, created_by)
                VALUES 
                ($temple_id, '$expense_no', CURDATE(), {$rec['category_id']}, " . ($rec['subcategory_id'] ?: 'NULL') . ", " . ($rec['vendor_id'] ?: 'NULL') . ", '{$rec['description']} (Auto-created from recurring)', {$rec['amount']}, {$rec['amount']}, '{$rec['payment_mode']}', 'Pending', {$_SESSION['user_id']})";
            
            if ($conn->query($insertExp)) {
                // Update next due date
                $interval = ['Daily' => '+1 day', 'Weekly' => '+1 week', 'Monthly' => '+1 month', 'Quarterly' => '+3 months', 'Yearly' => '+1 year'];
                $next_due = date('Y-m-d', strtotime($rec['next_due_date'] . ' ' . $interval[$rec['frequency']]));
                
                $updateNext = "UPDATE recurring_expenses SET next_due_date='$next_due' WHERE id=$recurring_id";
                $conn->query($updateNext);
                
                $message = "Expense created successfully! Expense No: $expense_no";
                $messageType = 'success';
            }
        }
    }
}

// Get all recurring expenses
$recurringQuery = "SELECT r.*,
    ec.category_name,
    es.subcategory_name,
    v.vendor_name
    FROM recurring_expenses r
    JOIN expense_categories ec ON r.category_id = ec.id
    LEFT JOIN expense_subcategories es ON r.subcategory_id = es.id
    LEFT JOIN vendors v ON r.vendor_id = v.id
    WHERE r.temple_id = $temple_id
    ORDER BY r.next_due_date, r.is_active DESC";
$recurring = $conn->query($recurringQuery);

// Get categories and vendors
$categoriesQuery = "SELECT * FROM expense_categories WHERE temple_id = $temple_id AND is_active = 1 ORDER BY category_name";
$categories = $conn->query($categoriesQuery);

$vendorsQuery = "SELECT * FROM vendors WHERE temple_id = $temple_id AND is_active = 1 ORDER BY vendor_name";
$vendors = $conn->query($vendorsQuery);

// Get summary
$summaryQuery = "SELECT 
    COUNT(*) as total_recurring,
    COUNT(CASE WHEN is_active = 1 THEN 1 END) as active_recurring,
    SUM(CASE WHEN is_active = 1 THEN amount ELSE 0 END) as total_monthly
    FROM recurring_expenses
    WHERE temple_id = $temple_id AND frequency = 'Monthly'";
$summaryResult = $conn->query($summaryQuery);
$summary = $summaryResult->fetch_assoc();

// Get due this week
$dueWeekQuery = "SELECT COUNT(*) as due_count
    FROM recurring_expenses
    WHERE temple_id = $temple_id 
    AND is_active = 1
    AND next_due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)";
$dueWeekResult = $conn->query($dueWeekQuery);
$dueWeek = $dueWeekResult->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recurring Expenses - <?php echo htmlspecialchars($templeInfo['temple_name']); ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f5f5f5; }
        .header { background: linear-gradient(135deg, #e67e22 0%, #d35400 100%); color: white; padding: 20px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .header h1 { font-size: 24px; }
        .nav { background: white; padding: 0; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .nav ul { list-style: none; display: flex; }
        .nav li { flex: 1; }
        .nav a { display: block; padding: 15px 20px; text-decoration: none; color: #333; text-align: center; border-right: 1px solid #eee; transition: background 0.3s; }
        .nav a:hover, .nav a.active { background: #e67e22; color: white; }
        .container { max-width: 1400px; margin: 20px auto; padding: 0 20px; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 20px; }
        .stat-card { background: white; padding: 25px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .stat-card h3 { color: #666; font-size: 14px; margin-bottom: 10px; }
        .stat-card .value { font-size: 32px; font-weight: bold; color: #e67e22; }
        .card { background: white; border-radius: 10px; padding: 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .card h2 { margin-bottom: 20px; color: #333; border-bottom: 2px solid #e67e22; padding-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #eee; font-size: 14px; }
        th { background: #f8f9fa; font-weight: 600; color: #333; }
        .btn { padding: 8px 16px; border: none; border-radius: 5px; cursor: pointer; font-size: 13px; transition: background 0.3s; text-decoration: none; display: inline-block; margin: 2px; }
        .btn-primary { background: #e67e22; color: white; }
        .btn-success { background: #27ae60; color: white; }
        .btn-secondary { background: #6c757d; color: white; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-info { background: #3498db; color: white; }
        .message { padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        .message.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .message.error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; }
        .modal.active { display: flex; align-items: center; justify-content: center; }
        .modal-content { background: white; padding: 30px; border-radius: 10px; max-width: 700px; width: 90%; max-height: 90vh; overflow-y: auto; }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .close-modal { cursor: pointer; font-size: 24px; color: #666; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: 500; color: #333; font-size: 14px; }
        input, select, textarea { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; font-size: 14px; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        .status-badge { padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: 500; }
        .status-active { background: #d4edda; color: #155724; }
        .status-inactive { background: #f8d7da; color: #721c24; }
        .freq-badge { padding: 4px 8px; border-radius: 12px; font-size: 11px; font-weight: 500; background: #e8f5e9; color: #2e7d32; }
        .checkbox-group { display: flex; align-items: center; gap: 10px; }
        .checkbox-group input[type="checkbox"] { width: auto; }
        .due-soon { background: #fff3cd; }
        .text-right { text-align: right; }
    </style>
</head>
<body>
<div id="google_translate_element"></div>
    <div class="header">
        <h1><?php echo htmlspecialchars($templeInfo['temple_name']); ?> - Recurring Expenses</h1>
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
        <?php if ($message): ?>
        <div class="message <?php echo $messageType; ?>">
            <?php echo $message; ?>
        </div>
        <?php endif; ?>
        
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Total Recurring Expenses</h3>
                <div class="value"><?php echo $summary['total_recurring']; ?></div>
            </div>
            <div class="stat-card">
                <h3>Active Recurring</h3>
                <div class="value"><?php echo $summary['active_recurring']; ?></div>
            </div>
            <div class="stat-card">
                <h3>Monthly Recurring Amount</h3>
                <div class="value"><?php echo formatCurrency($summary['total_monthly']); ?></div>
            </div>
            <div class="stat-card">
                <h3>Due This Week</h3>
                <div class="value"><?php echo $dueWeek['due_count']; ?></div>
            </div>
        </div>
        
        <div class="card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h2 style="margin: 0;">All Recurring Expenses</h2>
                <button class="btn btn-primary" onclick="openModal('addRecurringModal')">+ Add Recurring Expense</button>
            </div>
            
            <table>
                <thead>
                    <tr>
                        <th>Category</th>
                        <th>Description</th>
                        <th>Vendor</th>
                        <th class="text-right">Amount</th>
                        <th>Frequency</th>
                        <th>Next Due Date</th>
                        <th>Auto-Create</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $today = date('Y-m-d');
                    $week_later = date('Y-m-d', strtotime('+7 days'));
                    while ($rec = $recurring->fetch_assoc()): 
                        $due_soon = $rec['next_due_date'] <= $week_later && $rec['next_due_date'] >= $today;
                    ?>
                    <tr class="<?php echo $due_soon && $rec['is_active'] ? 'due-soon' : ''; ?>">
                        <td>
                            <?php echo htmlspecialchars($rec['category_name']); ?>
                            <?php if ($rec['subcategory_name']): ?>
                            <br><small><?php echo htmlspecialchars($rec['subcategory_name']); ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars(substr($rec['description'], 0, 40)); ?></td>
                        <td><?php echo htmlspecialchars($rec['vendor_name'] ?: '-'); ?></td>
                        <td class="text-right"><?php echo formatCurrency($rec['amount']); ?></td>
                        <td><span class="freq-badge"><?php echo $rec['frequency']; ?></span></td>
                        <td><?php echo date('d/m/Y', strtotime($rec['next_due_date'])); ?></td>
                        <td><?php echo $rec['auto_create'] ? '✓ Yes' : '✗ No'; ?></td>
                        <td>
                            <span class="status-badge <?php echo $rec['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                                <?php echo $rec['is_active'] ? 'Active' : 'Inactive'; ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($rec['is_active']): ?>
                            <button class="btn btn-success" onclick="createExpenseNow(<?php echo $rec['id']; ?>)">Create Now</button>
                            <?php endif; ?>
                            <button class="btn btn-secondary" onclick='editRecurring(<?php echo json_encode($rec); ?>)'>Edit</button>
                            <button class="btn btn-danger" onclick="deleteRecurring(<?php echo $rec['id']; ?>)">Delete</button>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Add Modal -->
    <div id="addRecurringModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Add Recurring Expense</h2>
                <span class="close-modal" onclick="closeModal('addRecurringModal')">&times;</span>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="add_recurring">
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Category *</label>
                        <select name="category_id" id="rec_category_id" required onchange="loadRecSubcategories()">
                            <option value="">Select Category</option>
                            <?php while ($cat = $categories->fetch_assoc()): ?>
                            <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['category_name']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Sub-Category</label>
                        <select name="subcategory_id" id="rec_subcategory_id">
                            <option value="">Select Sub-Category</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Vendor</label>
                    <select name="vendor_id">
                        <option value="">Select Vendor (Optional)</option>
                        <?php while ($ven = $vendors->fetch_assoc()): ?>
                        <option value="<?php echo $ven['id']; ?>"><?php echo htmlspecialchars($ven['vendor_name']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Description *</label>
                    <textarea name="description" rows="2" required></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Amount (₹) *</label>
                        <input type="number" name="amount" step="0.01" min="0" required>
                    </div>
                    <div class="form-group">
                        <label>Frequency *</label>
                        <select name="frequency" required>
                            <option value="Daily">Daily</option>
                            <option value="Weekly">Weekly</option>
                            <option value="Monthly" selected>Monthly</option>
                            <option value="Quarterly">Quarterly</option>
                            <option value="Yearly">Yearly</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Start Date *</label>
                        <input type="date" name="start_date" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>End Date (Optional)</label>
                        <input type="date" name="end_date">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Payment Mode *</label>
                    <select name="payment_mode" required>
                        <option value="Cash">Cash</option>
                        <option value="Bank Transfer">Bank Transfer</option>
                        <option value="Cheque">Cheque</option>
                        <option value="UPI">UPI</option>
                    </select>
                </div>
                
                <div class="form-group checkbox-group">
                    <input type="checkbox" name="auto_create" id="auto_create" value="1">
                    <label style="margin: 0;">Auto-create expenses (Coming soon)</label>
                </div>
                
                <div style="text-align: right; margin-top: 20px;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('addRecurringModal')">Cancel</button>
                    <button type="submit" class="btn btn-success">Add</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Edit Modal (similar structure) - abbreviated for space -->
    <div id="editRecurringModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Edit Recurring Expense</h2>
                <span class="close-modal" onclick="closeModal('editRecurringModal')">&times;</span>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="update_recurring">
                <input type="hidden" name="id" id="edit_rec_id">
                <p style="text-align: center; padding: 20px;">Edit form similar to add form...</p>
                <div style="text-align: right; margin-top: 20px;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('editRecurringModal')">Cancel</button>
                    <button type="submit" class="btn btn-success">Update</button>
                </div>
            </form>
        </div>
    </div>
    
    <form id="deleteForm" method="POST" style="display: none;">
        <input type="hidden" name="action" id="delete_action">
        <input type="hidden" name="recurring_id" id="delete_recurring_id">
    </form>
    
    <script>
        function openModal(modalId) { document.getElementById(modalId).classList.add('active'); }
        function closeModal(modalId) { document.getElementById(modalId).classList.remove('active'); }
        
        function loadRecSubcategories() {
            const categoryId = document.getElementById('rec_category_id').value;
            const subcategorySelect = document.getElementById('rec_subcategory_id');
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
        
        function createExpenseNow(id) {
            if (confirm('Create expense now from this recurring entry?')) {
                document.getElementById('delete_action').value = 'create_expense_now';
                document.getElementById('delete_recurring_id').value = id;
                document.getElementById('deleteForm').submit();
            }
        }
        
        function deleteRecurring(id) {
            if (confirm('Delete this recurring expense?')) {
                document.getElementById('delete_action').value = 'delete_recurring';
                document.getElementById('delete_recurring_id').value = id;
                document.getElementById('deleteForm').submit();
            }
        }
        
        function editRecurring(data) {
            alert('Edit functionality - populate form fields with: ' + data.description);
            openModal('editRecurringModal');
        }
        
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.classList.remove('active');
            }
        }
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
