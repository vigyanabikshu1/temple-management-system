<?php
require_once 'config.php';
requireLogin();

$templeInfo = getCurrentTempleInfo();
$temple_id = getCurrentTempleId();

// Get filter parameters
$report_type = isset($_GET['report_type']) ? $_GET['report_type'] : 'monthly';
$from_date = isset($_GET['from_date']) ? $_GET['from_date'] : date('Y-m-01');
$to_date = isset($_GET['to_date']) ? $_GET['to_date'] : date('Y-m-t');

// Get current month income and expenses
$income_query = "SELECT COALESCE(SUM(amount), 0) as total_income 
                 FROM vazhipadu_bookings 
                 WHERE temple_id = $temple_id 
                 AND DATE(booking_date) BETWEEN '$from_date' AND '$to_date'
                 AND payment_status = 'Completed'";
$income_result = $conn->query($income_query);
$income = $income_result->fetch_assoc()['total_income'];

$expense_query = "SELECT COALESCE(SUM(total_amount), 0) as total_expense 
                  FROM expenses 
                  WHERE temple_id = $temple_id 
                  AND expense_date BETWEEN '$from_date' AND '$to_date'
                  AND payment_status = 'Paid'";
$expense_result = $conn->query($expense_query);
$expense = $expense_result->fetch_assoc()['total_expense'];

$balance = $income - $expense;

// Get category-wise expenses
$category_expenses = "SELECT 
    ec.category_name,
    COUNT(e.id) as count,
    SUM(e.total_amount) as total
    FROM expenses e
    JOIN expense_categories ec ON e.category_id = ec.id
    WHERE e.temple_id = $temple_id 
    AND e.expense_date BETWEEN '$from_date' AND '$to_date'
    GROUP BY ec.id, ec.category_name
    ORDER BY total DESC";
$cat_expenses = $conn->query($category_expenses);

// Get daily trend for charts
$daily_trend = "SELECT 
    DATE(expense_date) as date,
    SUM(total_amount) as daily_expense
    FROM expenses
    WHERE temple_id = $temple_id 
    AND expense_date BETWEEN '$from_date' AND '$to_date'
    GROUP BY DATE(expense_date)
    ORDER BY date";
$daily_data = $conn->query($daily_trend);

// Get top 10 expenses
$top_expenses = "SELECT e.*, ec.category_name, v.vendor_name
    FROM expenses e
    JOIN expense_categories ec ON e.category_id = ec.id
    LEFT JOIN vendors v ON e.vendor_id = v.id
    WHERE e.temple_id = $temple_id 
    AND e.expense_date BETWEEN '$from_date' AND '$to_date'
    ORDER BY e.total_amount DESC
    LIMIT 10";
$top_exp = $conn->query($top_expenses);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Expense Reports - <?php echo htmlspecialchars($templeInfo['temple_name']); ?></title>
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
            background: linear-gradient(135deg, #8e44ad 0%, #3498db 100%);
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
            background: #8e44ad;
            color: white;
        }
        .container {
            max-width: 1400px;
            margin: 20px auto;
            padding: 0 20px;
        }
        .filter-box {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .filter-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            align-items: end;
        }
        .form-group {
            display: flex;
            flex-direction: column;
        }
        label {
            margin-bottom: 5px;
            font-weight: 500;
            color: #333;
            font-size: 14px;
        }
        select, input {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            position: relative;
            overflow: hidden;
        }
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
        }
        .stat-card.income::before {
            background: #27ae60;
        }
        .stat-card.expense::before {
            background: #e74c3c;
        }
        .stat-card.balance::before {
            background: #3498db;
        }
        .stat-card h3 {
            color: #666;
            font-size: 14px;
            margin-bottom: 10px;
        }
        .stat-card .value {
            font-size: 32px;
            font-weight: bold;
        }
        .stat-card.income .value {
            color: #27ae60;
        }
        .stat-card.expense .value {
            color: #e74c3c;
        }
        .stat-card.balance .value {
            color: #3498db;
        }
        .stat-card.balance.negative .value {
            color: #e74c3c;
        }
        .card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .card h2 {
            margin-bottom: 20px;
            color: #333;
            border-bottom: 2px solid #8e44ad;
            padding-bottom: 10px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        th {
            background: #f8f9fa;
            font-weight: 600;
            color: #333;
        }
        .text-right {
            text-align: right;
        }
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            transition: background 0.3s;
        }
        .btn-primary {
            background: #8e44ad;
            color: white;
        }
        .btn-primary:hover {
            background: #7d3c98;
        }
        @media print {
            .no-print {
                display: none;
            }
        }
    </style>
</head>
<body>
<div id="google_translate_element"></div>
    <div class="header no-print">
        <h1><?php echo htmlspecialchars($templeInfo['temple_name']); ?> - Income vs Expense Report</h1>
    </div>
    
    <div class="nav no-print">
        <ul>
            <li><a href="index.php">Dashboard</a></li>
            <li><a href="expenses.php">Expenses</a></li>
            <li><a href="expense_reports.php" class="active">Financial Reports</a></li>
            <?php if (isTempleAdmin()): ?>
            <li><a href="admin.php">Admin</a></li>
            <?php endif; ?>
        </ul>
    </div>
    
    <div class="container">
        <div class="filter-box no-print">
            <form method="GET" class="filter-row">
                <div class="form-group">
                    <label>Report Type</label>
                    <select name="report_type" id="report_type" onchange="updateDateFields()">
                        <option value="monthly" <?php echo $report_type === 'monthly' ? 'selected' : ''; ?>>Monthly</option>
                        <option value="range" <?php echo $report_type === 'range' ? 'selected' : ''; ?>>Date Range</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>From Date</label>
                    <input type="date" name="from_date" value="<?php echo $from_date; ?>">
                </div>
                <div class="form-group">
                    <label>To Date</label>
                    <input type="date" name="to_date" value="<?php echo $to_date; ?>">
                </div>
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">Generate Report</button>
                </div>
            </form>
        </div>
        
        <h2 style="margin-bottom: 20px;">
            Financial Summary - <?php echo date('d M Y', strtotime($from_date)); ?> to <?php echo date('d M Y', strtotime($to_date)); ?>
        </h2>
        
        <div class="stats-grid">
            <div class="stat-card income">
                <h3>Total Income (Vazhipadu)</h3>
                <div class="value"><?php echo formatCurrency($income); ?></div>
            </div>
            <div class="stat-card expense">
                <h3>Total Expenses</h3>
                <div class="value"><?php echo formatCurrency($expense); ?></div>
            </div>
            <div class="stat-card balance <?php echo $balance < 0 ? 'negative' : ''; ?>">
                <h3>Net Balance</h3>
                <div class="value"><?php echo formatCurrency($balance); ?></div>
                <small><?php echo $balance >= 0 ? 'Surplus' : 'Deficit'; ?></small>
            </div>
            <div class="stat-card">
                <h3>Expense Ratio</h3>
                <div class="value"><?php echo $income > 0 ? number_format(($expense / $income) * 100, 1) : 0; ?>%</div>
                <small>of total income</small>
            </div>
        </div>
        
        <div class="card">
            <h2>Category-wise Expense Breakdown</h2>
            <table>
                <thead>
                    <tr>
                        <th>Category</th>
                        <th class="text-right">Count</th>
                        <th class="text-right">Amount</th>
                        <th class="text-right">% of Total</th>
                        <th class="text-right">% of Income</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $total_cat = 0;
                    while ($cat = $cat_expenses->fetch_assoc()): 
                        $total_cat += $cat['total'];
                        $pct_expense = $expense > 0 ? ($cat['total'] / $expense) * 100 : 0;
                        $pct_income = $income > 0 ? ($cat['total'] / $income) * 100 : 0;
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($cat['category_name']); ?></td>
                        <td class="text-right"><?php echo $cat['count']; ?></td>
                        <td class="text-right"><?php echo formatCurrency($cat['total']); ?></td>
                        <td class="text-right"><?php echo number_format($pct_expense, 1); ?>%</td>
                        <td class="text-right"><?php echo number_format($pct_income, 1); ?>%</td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        
        <div class="card">
            <h2>Top 10 Expenses</h2>
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Expense No</th>
                        <th>Category</th>
                        <th>Description</th>
                        <th>Vendor</th>
                        <th class="text-right">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($exp = $top_exp->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo date('d/m/Y', strtotime($exp['expense_date'])); ?></td>
                        <td><?php echo $exp['expense_no']; ?></td>
                        <td><?php echo htmlspecialchars($exp['category_name']); ?></td>
                        <td><?php echo htmlspecialchars(substr($exp['description'], 0, 40)); ?></td>
                        <td><?php echo htmlspecialchars($exp['vendor_name'] ?: '-'); ?></td>
                        <td class="text-right"><?php echo formatCurrency($exp['total_amount']); ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        
        <div style="text-align: center; margin: 30px 0;" class="no-print">
            <button onclick="window.print()" class="btn btn-primary">Print Report</button>
        </div>
    </div>
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
