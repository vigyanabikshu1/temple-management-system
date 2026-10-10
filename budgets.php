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

// Get selected year and month
$budget_year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
$budget_month = isset($_GET['month']) ? intval($_GET['month']) : date('n');

// Handle Actions
if (isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'set_budget') {
        $year = intval($_POST['budget_year']);
        $month = intval($_POST['budget_month']);
        $category_id = intval($_POST['category_id']);
        $subcategory_id = !empty($_POST['subcategory_id']) ? intval($_POST['subcategory_id']) : null;
        $budgeted_amount = floatval($_POST['budgeted_amount']);
        $remarks = sanitizeInput($_POST['remarks']);
        
        $query = "INSERT INTO budgets (temple_id, budget_year, budget_month, category_id, subcategory_id, budgeted_amount, remarks, created_by)
                  VALUES ($temple_id, $year, $month, $category_id, " . ($subcategory_id ?: 'NULL') . ", $budgeted_amount, '$remarks', {$_SESSION['user_id']})
                  ON DUPLICATE KEY UPDATE budgeted_amount = $budgeted_amount, remarks = '$remarks'";
        
        if ($conn->query($query)) {
            logAudit('Budget Set', 'budgets', null);
            $message = "Budget set successfully!";
            $messageType = 'success';
        } else {
            $message = "Error: " . $conn->error;
            $messageType = 'error';
        }
    }
    
    elseif ($action === 'delete_budget') {
        $id = intval($_POST['id']);
        $query = "DELETE FROM budgets WHERE id=$id AND temple_id=$temple_id";
        
        if ($conn->query($query)) {
            logAudit('Budget Deleted', 'budgets', $id);
            $message = "Budget deleted successfully!";
            $messageType = 'success';
        }
    }
}

// Update spent amounts for current period
$updateSpent = "UPDATE budgets b SET spent_amount = (
    SELECT COALESCE(SUM(e.total_amount), 0)
    FROM expenses e
    WHERE e.temple_id = b.temple_id
    AND e.category_id = b.category_id
    AND (b.subcategory_id IS NULL OR e.subcategory_id = b.subcategory_id)
    AND YEAR(e.expense_date) = b.budget_year
    AND MONTH(e.expense_date) = b.budget_month
    AND e.payment_status = 'Paid'
)
WHERE b.temple_id = $temple_id 
AND b.budget_year = $budget_year 
AND b.budget_month = $budget_month";
$conn->query($updateSpent);

// Get budget summary
$summaryQuery = "SELECT 
    COUNT(*) as budget_count,
    SUM(budgeted_amount) as total_budget,
    SUM(spent_amount) as total_spent,
    SUM(budgeted_amount - spent_amount) as remaining
    FROM budgets
    WHERE temple_id = $temple_id
    AND budget_year = $budget_year
    AND budget_month = $budget_month";
$summaryResult = $conn->query($summaryQuery);
$summary = $summaryResult->fetch_assoc();

// Get budgets with category details
$budgetsQuery = "SELECT b.*, 
    ec.category_name,
    es.subcategory_name
    FROM budgets b
    JOIN expense_categories ec ON b.category_id = ec.id
    LEFT JOIN expense_subcategories es ON b.subcategory_id = es.id
    WHERE b.temple_id = $temple_id
    AND b.budget_year = $budget_year
    AND b.budget_month = $budget_month
    ORDER BY ec.category_name, es.subcategory_name";
$budgets = $conn->query($budgetsQuery);

// Get categories
$categoriesQuery = "SELECT * FROM expense_categories WHERE temple_id = $temple_id AND is_active = 1 ORDER BY category_name";
$categories = $conn->query($categoriesQuery);

// Calculate percentage
$budget_used_pct = $summary['total_budget'] > 0 ? ($summary['total_spent'] / $summary['total_budget']) * 100 : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Budget Management - <?php echo htmlspecialchars($templeInfo['temple_name']); ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f5f5f5; }
        .header { background: linear-gradient(135deg, #9b59b6 0%, #8e44ad 100%); color: white; padding: 20px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .header h1 { font-size: 24px; }
        .nav { background: white; padding: 0; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .nav ul { list-style: none; display: flex; }
        .nav li { flex: 1; }
        .nav a { display: block; padding: 15px 20px; text-decoration: none; color: #333; text-align: center; border-right: 1px solid #eee; transition: background 0.3s; }
        .nav a:hover, .nav a.active { background: #9b59b6; color: white; }
        .container { max-width: 1200px; margin: 20px auto; padding: 0 20px; }
        .filter-box { background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .filter-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; align-items: end; }
        .form-group { display: flex; flex-direction: column; }
        label { margin-bottom: 5px; font-weight: 500; color: #333; font-size: 14px; }
        select, input { padding: 10px; border: 1px solid #ddd; border-radius: 5px; font-size: 14px; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 20px; }
        .stat-card { background: white; padding: 25px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); position: relative; overflow: hidden; }
        .stat-card::before { content: ''; position: absolute; top: 0; left: 0; width: 4px; height: 100%; }
        .stat-card.budget::before { background: #3498db; }
        .stat-card.spent::before { background: #e74c3c; }
        .stat-card.remaining::before { background: #27ae60; }
        .stat-card.used::before { background: #f39c12; }
        .stat-card h3 { color: #666; font-size: 14px; margin-bottom: 10px; }
        .stat-card .value { font-size: 32px; font-weight: bold; color: #9b59b6; }
        .card { background: white; border-radius: 10px; padding: 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .card h2 { margin-bottom: 20px; color: #333; border-bottom: 2px solid #9b59b6; padding-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #eee; font-size: 14px; }
        th { background: #f8f9fa; font-weight: 600; color: #333; }
        .btn { padding: 8px 16px; border: none; border-radius: 5px; cursor: pointer; font-size: 13px; transition: background 0.3s; text-decoration: none; display: inline-block; }
        .btn-primary { background: #9b59b6; color: white; }
        .btn-success { background: #27ae60; color: white; }
        .btn-secondary { background: #6c757d; color: white; }
        .btn-danger { background: #dc3545; color: white; }
        .message { padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        .message.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .message.error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; }
        .modal.active { display: flex; align-items: center; justify-content: center; }
        .modal-content { background: white; padding: 30px; border-radius: 10px; max-width: 600px; width: 90%; }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .close-modal { cursor: pointer; font-size: 24px; color: #666; }
        .progress-bar { height: 20px; background: #ecf0f1; border-radius: 10px; overflow: hidden; margin-top: 10px; }
        .progress-fill { height: 100%; transition: width 0.3s; }
        .progress-success { background: #27ae60; }
        .progress-warning { background: #f39c12; }
        .progress-danger { background: #e74c3c; }
        .text-right { text-align: right; }
        .status-badge { padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: 500; }
        .status-under { background: #d4edda; color: #155724; }
        .status-near { background: #fff3cd; color: #856404; }
        .status-over { background: #f8d7da; color: #721c24; }
        textarea { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; font-size: 14px; font-family: inherit; }
    </style>
</head>
<body>
    <div class="header">
        <h1><?php echo htmlspecialchars($templeInfo['temple_name']); ?> - Budget Management</h1>
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
        
        <div class="filter-box">
            <form method="GET" class="filter-row">
                <div class="form-group">
                    <label>Budget Year</label>
                    <select name="year" onchange="this.form.submit()">
                        <?php for ($y = date('Y') - 2; $y <= date('Y') + 1; $y++): ?>
                        <option value="<?php echo $y; ?>" <?php echo $y == $budget_year ? 'selected' : ''; ?>><?php echo $y; ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Budget Month</label>
                    <select name="month" onchange="this.form.submit()">
                        <?php 
                        $months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
                        for ($m = 1; $m <= 12; $m++): 
                        ?>
                        <option value="<?php echo $m; ?>" <?php echo $m == $budget_month ? 'selected' : ''; ?>>
                            <?php echo $months[$m-1]; ?>
                        </option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="form-group">
                    <button type="button" class="btn btn-primary" onclick="openModal('setBudgetModal')">+ Set Budget</button>
                </div>
            </form>
        </div>
        
        <h2 style="margin-bottom: 20px;">
            Budget Overview - <?php echo date('F Y', strtotime("$budget_year-$budget_month-01")); ?>
        </h2>
        
        <div class="stats-grid">
            <div class="stat-card budget">
                <h3>Total Budget</h3>
                <div class="value"><?php echo formatCurrency($summary['total_budget'] ?: 0); ?></div>
            </div>
            <div class="stat-card spent">
                <h3>Total Spent</h3>
                <div class="value"><?php echo formatCurrency($summary['total_spent'] ?: 0); ?></div>
            </div>
            <div class="stat-card remaining">
                <h3>Remaining</h3>
                <div class="value"><?php echo formatCurrency($summary['remaining'] ?: 0); ?></div>
            </div>
            <div class="stat-card used">
                <h3>Budget Used</h3>
                <div class="value"><?php echo number_format($budget_used_pct, 1); ?>%</div>
                <div class="progress-bar">
                    <div class="progress-fill <?php 
                        echo $budget_used_pct < 80 ? 'progress-success' : 
                             ($budget_used_pct < 100 ? 'progress-warning' : 'progress-danger'); 
                    ?>" style="width: <?php echo min($budget_used_pct, 100); ?>%"></div>
                </div>
            </div>
        </div>
        
        <div class="card">
            <h2>Category-wise Budgets</h2>
            
            <?php if ($budgets->num_rows > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Category</th>
                        <th>Sub-Category</th>
                        <th class="text-right">Budgeted</th>
                        <th class="text-right">Spent</th>
                        <th class="text-right">Remaining</th>
                        <th>Usage</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($budget = $budgets->fetch_assoc()): 
                        $remaining = $budget['budgeted_amount'] - $budget['spent_amount'];
                        $usage_pct = $budget['budgeted_amount'] > 0 ? ($budget['spent_amount'] / $budget['budgeted_amount']) * 100 : 0;
                        $status = $usage_pct < 80 ? 'under' : ($usage_pct < 100 ? 'near' : 'over');
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($budget['category_name']); ?></td>
                        <td><?php echo htmlspecialchars($budget['subcategory_name'] ?: '-'); ?></td>
                        <td class="text-right"><?php echo formatCurrency($budget['budgeted_amount']); ?></td>
                        <td class="text-right"><?php echo formatCurrency($budget['spent_amount']); ?></td>
                        <td class="text-right"><?php echo formatCurrency($remaining); ?></td>
                        <td>
                            <div class="progress-bar">
                                <div class="progress-fill <?php 
                                    echo $usage_pct < 80 ? 'progress-success' : 
                                         ($usage_pct < 100 ? 'progress-warning' : 'progress-danger'); 
                                ?>" style="width: <?php echo min($usage_pct, 100); ?>%"></div>
                            </div>
                            <small><?php echo number_format($usage_pct, 1); ?>%</small>
                        </td>
                        <td>
                            <span class="status-badge status-<?php echo $status; ?>">
                                <?php 
                                echo $status === 'under' ? 'Under Budget' : 
                                     ($status === 'near' ? 'Near Limit' : 'Over Budget'); 
                                ?>
                            </span>
                        </td>
                        <td>
                            <button class="btn btn-danger" onclick="deleteBudget(<?php echo $budget['id']; ?>)">Delete</button>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            <?php else: ?>
            <p style="text-align: center; padding: 30px; color: #666;">
                No budgets set for this period. Click "Set Budget" to add budgets.
            </p>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Set Budget Modal -->
    <div id="setBudgetModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Set Budget</h2>
                <span class="close-modal" onclick="closeModal('setBudgetModal')">&times;</span>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="set_budget">
                
                <div class="form-group">
                    <label>Budget Year *</label>
                    <select name="budget_year" required>
                        <?php for ($y = date('Y'); $y <= date('Y') + 1; $y++): ?>
                        <option value="<?php echo $y; ?>" <?php echo $y == $budget_year ? 'selected' : ''; ?>><?php echo $y; ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Budget Month *</label>
                    <select name="budget_month" required>
                        <?php for ($m = 1; $m <= 12; $m++): ?>
                        <option value="<?php echo $m; ?>" <?php echo $m == $budget_month ? 'selected' : ''; ?>>
                            <?php echo $months[$m-1]; ?>
                        </option>
                        <?php endfor; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Category *</label>
                    <select name="category_id" id="budget_category_id" required onchange="loadBudgetSubcategories()">
                        <option value="">Select Category</option>
                        <?php while ($cat = $categories->fetch_assoc()): ?>
                        <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['category_name']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Sub-Category (Optional)</label>
                    <select name="subcategory_id" id="budget_subcategory_id">
                        <option value="">Select Sub-Category</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Budgeted Amount (₹) *</label>
                    <input type="number" name="budgeted_amount" step="0.01" min="0" required>
                </div>
                
                <div class="form-group">
                    <label>Remarks</label>
                    <textarea name="remarks" rows="2"></textarea>
                </div>
                
                <div style="text-align: right; margin-top: 20px;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('setBudgetModal')">Cancel</button>
                    <button type="submit" class="btn btn-success">Set Budget</button>
                </div>
            </form>
        </div>
    </div>
    
    <form id="deleteForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="delete_budget">
        <input type="hidden" name="id" id="delete_id">
    </form>
    
    <script>
        function openModal(modalId) {
            document.getElementById(modalId).classList.add('active');
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
        }
        
        function loadBudgetSubcategories() {
            const categoryId = document.getElementById('budget_category_id').value;
            const subcategorySelect = document.getElementById('budget_subcategory_id');
            
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
        
        function deleteBudget(id) {
            if (confirm('Delete this budget?')) {
                document.getElementById('delete_id').value = id;
                document.getElementById('deleteForm').submit();
            }
        }
        
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.classList.remove('active');
            }
        }
    </script>
</body>
</html>
