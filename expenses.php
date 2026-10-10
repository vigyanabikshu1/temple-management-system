<?php
require_once 'config.php';
requireLogin();

$templeInfo = getCurrentTempleInfo();
$temple_id = getCurrentTempleId();

$message = '';
$messageType = '';

// Get filter parameters
$from_date = isset($_GET['from_date']) ? $_GET['from_date'] : date('Y-m-01');
$to_date = isset($_GET['to_date']) ? $_GET['to_date'] : date('Y-m-t');
$category_filter = isset($_GET['category']) ? intval($_GET['category']) : 0;
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

// Build where clause
$whereConditions = ["e.temple_id = $temple_id"];
$whereConditions[] = "e.expense_date BETWEEN '$from_date' AND '$to_date'";

if ($category_filter > 0) {
    $whereConditions[] = "e.category_id = $category_filter";
}
if ($status_filter !== '') {
    $whereConditions[] = "e.payment_status = '$status_filter'";
}

$whereClause = implode(' AND ', $whereConditions);

// Get summary statistics
$summaryQuery = "SELECT 
    COUNT(*) as total_expenses,
    COALESCE(SUM(total_amount), 0) as total_amount,
    COALESCE(SUM(CASE WHEN payment_status = 'Paid' THEN total_amount ELSE 0 END), 0) as paid_amount,
    COALESCE(SUM(CASE WHEN payment_status = 'Pending' THEN total_amount ELSE 0 END), 0) as pending_amount
    FROM expenses e
    WHERE $whereClause";
$summaryResult = $conn->query($summaryQuery);
$summary = $summaryResult->fetch_assoc();

// Get category-wise breakdown
$categoryQuery = "SELECT 
    ec.category_name,
    COUNT(e.id) as count,
    SUM(e.total_amount) as total
    FROM expenses e
    JOIN expense_categories ec ON e.category_id = ec.id
    WHERE $whereClause
    GROUP BY ec.id, ec.category_name
    ORDER BY total DESC";
$categoryBreakdown = $conn->query($categoryQuery);

// Get recent expenses
$expensesQuery = "SELECT e.*, 
    ec.category_name,
    es.subcategory_name,
    v.vendor_name,
    u.full_name as created_by_name
    FROM expenses e
    JOIN expense_categories ec ON e.category_id = ec.id
    LEFT JOIN expense_subcategories es ON e.subcategory_id = es.id
    LEFT JOIN vendors v ON e.vendor_id = v.id
    LEFT JOIN users u ON e.created_by = u.id
    WHERE $whereClause
    ORDER BY e.expense_date DESC, e.created_at DESC
    LIMIT 50";
$expenses = $conn->query($expensesQuery);

// Get all categories for filter
$categoriesQuery = "SELECT * FROM expense_categories WHERE temple_id = $temple_id AND is_active = 1 ORDER BY category_name";
$categories = $conn->query($categoriesQuery);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Expense Management - <?php echo htmlspecialchars($templeInfo['temple_name']); ?></title>
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
            position: relative;
        }
        .header h1 {
            font-size: 24px;
        }
        .header .user-info {
            float: right;
            margin-top: -40px;
        }
        .lang-switcher {
            position: absolute;
            top: 20px;
            right: 20px;
        }
        .lang-select {
            background: rgba(255,255,255,0.9);
            padding: 8px 15px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            border: 2px solid rgba(255,255,255,0.5);
            color: #333;
            font-weight: 500;
            outline: none;
            transition: all 0.3s;
            min-width: 150px;
        }
        .lang-select:hover {
            background: white;
            border-color: white;
        }
        .lang-select:focus {
            border-color: #e74c3c;
            box-shadow: 0 0 0 3px rgba(231,76,60,0.2);
        }
        .lang-select option {
            padding: 10px;
            background: white;
            color: #333;
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
        }
        .stat-card h3 {
            color: #666;
            font-size: 14px;
            margin-bottom: 10px;
        }
        .stat-card .value {
            font-size: 32px;
            font-weight: bold;
            color: #e74c3c;
        }
        .stat-card.success .value {
            color: #27ae60;
        }
        .stat-card.warning .value {
            color: #f39c12;
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
            border-bottom: 2px solid #e74c3c;
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
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 13px;
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
        .btn-success {
            background: #27ae60;
            color: white;
        }
        .btn-info {
            background: #3498db;
            color: white;
        }
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        .status-badge {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
        }
        .status-paid {
            background: #d4edda;
            color: #155724;
        }
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
        .status-partial {
            background: #d1ecf1;
            color: #0c5460;
        }
        .text-right {
            text-align: right;
        }
        .total-row {
            font-weight: bold;
            background: #fff8f0;
        }
        .action-buttons {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        /* Multi-language font support */
        body.ml {
            font-family: 'Manjari', 'Noto Sans Malayalam', sans-serif;
        }
        body.ta {
            font-family: 'Catamaran', 'Noto Sans Tamil', sans-serif;
        }
        body.hi {
            font-family: 'Noto Sans Devanagari', sans-serif;
        }
        body.kn {
            font-family: 'Noto Sans Kannada', sans-serif;
        }
        body.te {
            font-family: 'Noto Sans Telugu', sans-serif;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="lang-switcher">
            <select class="lang-select" id="langSelect" onchange="setLanguage(this.value)">
                <option value="en">English</option>
                <option value="ml">മലയാളം (Malayalam)</option>
                <option value="ta">தமிழ் (Tamil)</option>
                <option value="hi">हिंदी (Hindi)</option>
                <option value="kn">ಕನ್ನಡ (Kannada)</option>
                <option value="te">తెలుగు (Telugu)</option>
            </select>
        </div>
        <h1><?php echo htmlspecialchars($templeInfo['temple_name']); ?> - <span data-en="Expense Management" 
                                                                               data-ml="ചെലവ് മാനേജ്മെന്റ്" 
                                                                               data-ta="செலவு மேலாண்மை"
                                                                               data-hi="व्यय प्रबंधन"
                                                                               data-kn="ಖರ್ಚು ನಿರ್ವಹಣೆ"
                                                                               data-te="ఖర్చు నిర్వహణ">Expense Management</span></h1>
        <div class="user-info">
            <span data-en="Welcome" 
                  data-ml="സ്വാഗതം" 
                  data-ta="வரவேற்கிறோம்"
                  data-hi="स्वागत है"
                  data-kn="ಸ್ವಾಗತ"
                  data-te="స్వాగతం">Welcome</span>, <?php echo htmlspecialchars($_SESSION['full_name']); ?> | 
            <a href="logout.php" style="color: white;"
               data-en="Logout" 
               data-ml="ലോഗൗട്ട്" 
               data-ta="வெளியேறு"
               data-hi="लॉगआउट"
               data-kn="ಲಾಗ್‌ಔಟ್"
               data-te="లాగ్‌అవుట్">Logout</a>
        </div>
    </div>
    
    <div class="nav">
        <ul>
            <li><a href="index.php" 
                   data-en="Dashboard" 
                   data-ml="ഡാഷ്ബോർഡ്" 
                   data-ta="டாஷ்போர்டு"
                   data-hi="डैशबोर्ड"
                   data-kn="ಡ್ಯಾಶ್‌ಬೋರ್ಡ್"
                   data-te="డాష్‌బోర్డ్">Dashboard</a></li>
            <li><a href="new_booking.php" 
                   data-en="New Booking" 
                   data-ml="പുതിയ ബുക്കിംഗ്" 
                   data-ta="புதிய முன்பதிவு"
                   data-hi="नई बुकिंग"
                   data-kn="ಹೊಸ ಬುಕಿಂಗ್"
                   data-te="కొత్త బుకింగ్">New Booking</a></li>
            <li><a href="expenses.php" class="active" 
                   data-en="Expenses" 
                   data-ml="ചെലവുകൾ" 
                   data-ta="செலவுகள்"
                   data-hi="व्यय"
                   data-kn="ಖರ್ಚುಗಳು"
                   data-te="ఖర్చులు">Expenses</a></li>
            <li><a href="reports.php" 
                   data-en="Reports" 
                   data-ml="റിപ്പോർട്ടുകൾ" 
                   data-ta="அறிக்கைகள்"
                   data-hi="रिपोर्ट"
                   data-kn="ವರದಿಗಳು"
                   data-te="నివేదికలు">Reports</a></li>
            <?php if (isTempleAdmin()): ?>
            <li><a href="admin.php" 
                   data-en="Admin" 
                   data-ml="അഡ്മിൻ" 
                   data-ta="நிர்வாகி"
                   data-hi="व्यवस्थापक"
                   data-kn="ನಿರ್ವಾಹಕ"
                   data-te="నిర్వాహకుడు">Admin</a></li>
            <?php endif; ?>
        </ul>
    </div>
    
    <div class="container">
        <div class="filter-box">
            <form method="GET" class="filter-row">
                <div class="form-group">
                    <label data-en="From Date" 
                           data-ml="തുടങ്ങുന്ന തീയതി" 
                           data-ta="தொடக்க தேதி"
                           data-hi="आरंभ तिथि"
                           data-kn="ಆರಂಭ ದಿನಾಂಕ"
                           data-te="ప్రారంబ తేదీ">From Date</label>
                    <input type="date" name="from_date" value="<?php echo $from_date; ?>">
                </div>
                <div class="form-group">
                    <label data-en="To Date" 
                           data-ml="വരെയുള്ള തീയതി" 
                           data-ta="கடைசி தேதி"
                           data-hi="अंतिम तिथि"
                           data-kn="ಕೊನೆಯ ದಿನಾಂಕ"
                           data-te="చివరి తేదీ">To Date</label>
                    <input type="date" name="to_date" value="<?php echo $to_date; ?>">
                </div>
                <div class="form-group">
                    <label data-en="Category" 
                           data-ml="വിഭാഗം" 
                           data-ta="வகை"
                           data-hi="श्रेणी"
                           data-kn="ವರ್ಗ"
                           data-te="వర్గం">Category</label>
                    <select name="category">
                        <option value="0"
                                data-en="All Categories" 
                                data-ml="എല്ലാ വിഭാഗങ്ങളും" 
                                data-ta="அனைத்து வகைகளும்"
                                data-hi="सभी श्रेणियाँ"
                                data-kn="ಎಲ್ಲಾ ವರ್ಗಗಳು"
                                data-te="అన్ని వర్గాలు">All Categories</option>
                        <?php while ($cat = $categories->fetch_assoc()): ?>
                        <option value="<?php echo $cat['id']; ?>" <?php echo $category_filter == $cat['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cat['category_name']); ?>
                        </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label data-en="Status" 
                           data-ml="സ്ഥിതി" 
                           data-ta="நிலை"
                           data-hi="स्थिति"
                           data-kn="ಸ್ಥಿತಿ"
                           data-te="స్థితి">Status</label>
                    <select name="status">
                        <option value=""
                                data-en="All Status" 
                                data-ml="എല്ലാ സ്ഥിതികളും" 
                                data-ta="அனைத்து நிலைகளும்"
                                data-hi="सभी स्थितियाँ"
                                data-kn="ಎಲ್ಲಾ ಸ್ಥಿತಿಗಳು"
                                data-te="అన్ని స్థితులు">All Status</option>
                        <option value="Paid" <?php echo $status_filter === 'Paid' ? 'selected' : ''; ?>
                                data-en="Paid" 
                                data-ml="ചെലവിട്ടു" 
                                data-ta="செலுத்தப்பட்டது"
                                data-hi="भुगतान किया"
                                data-kn="ಪಾವತಿಸಲಾಗಿದೆ"
                                data-te="చెల్లించబడింది">Paid</option>
                        <option value="Pending" <?php echo $status_filter === 'Pending' ? 'selected' : ''; ?>
                                data-en="Pending" 
                                data-ml="തീർപ്പാക്കാത്ത" 
                                data-ta="நிலுவையில் உள்ளது"
                                data-hi="लंबित"
                                data-kn="ಬಾಕಿ ಇದೆ"
                                data-te="పెండింగ్‌లో ఉంది">Pending</option>
                        <option value="Partial" <?php echo $status_filter === 'Partial' ? 'selected' : ''; ?>
                                data-en="Partial" 
                                data-ml="ഭാഗികം" 
                                data-ta="பகுதி"
                                data-hi="आंशिक"
                                data-kn="ಭಾಗಶಃ"
                                data-te="పాక్షిక">Partial</option>
                    </select>
                </div>
                <div class="form-group">
                    <button type="submit" class="btn btn-primary"
                            data-en="Filter" 
                            data-ml="ഫിൽട്ടർ" 
                            data-ta="வடிகட்டு"
                            data-hi="फ़िल्टर करें"
                            data-kn="ಫಿಲ್ಟರ್ ಮಾಡಿ"
                            data-te="ఫిల్టర్ చేయండి">Filter</button>
                </div>
            </form>
        </div>
        
        <h2 style="margin-bottom: 20px;">
            <span data-en="Expense Summary" 
                  data-ml="ചെലവ് സംഗ്രഹം" 
                  data-ta="செலவு சுருக்கம்"
                  data-hi="व्यय सारांश"
                  data-kn="ಖರ್ಚು ಸಾರಾಂಶ"
                  data-te="ఖర్చు సారాంశం">Expense Summary</span> - 
            <?php echo date('d M Y', strtotime($from_date)); ?> 
            <span data-en="to" 
                  data-ml="മുതൽ" 
                  data-ta="முதல்"
                  data-hi="से"
                  data-kn="ವರೆಗೆ"
                  data-te="నుండి">to</span> 
            <?php echo date('d M Y', strtotime($to_date)); ?>
        </h2>
        
        <div class="stats-grid">
            <div class="stat-card">
                <h3><span data-en="Total Expenses" 
                          data-ml="മൊത്തം ചെലവുകൾ" 
                          data-ta="மொத்த செலவுகள்"
                          data-hi="कुल व्यय"
                          data-kn="ಒಟ್ಟು ಖರ್ಚುಗಳು"
                          data-te="మొత్తం ఖర్చులు">Total Expenses</span></h3>
                <div class="value"><?php echo $summary['total_expenses']; ?></div>
            </div>
            <div class="stat-card">
                <h3><span data-en="Total Amount" 
                          data-ml="മൊത്തം തുക" 
                          data-ta="மொத்த தொகை"
                          data-hi="कुल राशि"
                          data-kn="ಒಟ್ಟು ಮೊತ್ತ"
                          data-te="మొత్తం మొత్తం">Total Amount</span></h3>
                <div class="value"><?php echo formatCurrency($summary['total_amount']); ?></div>
            </div>
            <div class="stat-card success">
                <h3><span data-en="Paid Amount" 
                          data-ml="ചെലവിട്ട തുക" 
                          data-ta="செலுத்தப்பட்ட தொகை"
                          data-hi="भुगतान राशि"
                          data-kn="ಪಾವತಿಸಿದ ಮೊತ್ತ"
                          data-te="చెల్లించిన మొత్తం">Paid Amount</span></h3>
                <div class="value"><?php echo formatCurrency($summary['paid_amount']); ?></div>
            </div>
            <div class="stat-card warning">
                <h3><span data-en="Pending Amount" 
                          data-ml="തീർപ്പാക്കാത്ത തുക" 
                          data-ta="நிலுவையில் உள்ள தொகை"
                          data-hi="लंबित राशि"
                          data-kn="ಬಾಕಿ ಇರುವ ಮೊತ್ತ"
                          data-te="పెండింగ్‌లో ఉన్న మొత్తం">Pending Amount</span></h3>
                <div class="value"><?php echo formatCurrency($summary['pending_amount']); ?></div>
            </div>
        </div>
        
        <div class="card">
            <h2><span data-en="Category-wise Breakdown" 
                      data-ml="വിഭാഗവിഭാഗമായി വിഭജനം" 
                      data-ta="வகை வாரியாக பிரித்தல்"
                      data-hi="श्रेणीवार विवरण"
                      data-kn="ವರ್ಗಾನುಸಾರ ವಿಭಾಗ"
                      data-te="వర్గం వారీగా విభజన">Category-wise Breakdown</span></h2>
            <table>
                <thead>
                    <tr>
                        <th><span data-en="Category" 
                                  data-ml="വിഭാഗം" 
                                  data-ta="வகை"
                                  data-hi="श्रेणी"
                                  data-kn="ವರ್ಗ"
                                  data-te="వర్గం">Category</span></th>
                        <th class="text-right"><span data-en="Count" 
                                                     data-ml="എണ്ണം" 
                                                     data-ta="எண்ணிக்கை"
                                                     data-hi="गिनती"
                                                     data-kn="ಎಣಿಕೆ"
                                                     data-te="లెక్కింపు">Count</span></th>
                        <th class="text-right"><span data-en="Total Amount" 
                                                     data-ml="മൊത്തം തുക" 
                                                     data-ta="மொத்த தொகை"
                                                     data-hi="कुल राशि"
                                                     data-kn="ಒಟ್ಟು ಮೊತ್ತ"
                                                     data-te="మొత్తం మొత్తం">Total Amount</span></th>
                        <th class="text-right"><span data-en="% of Total" 
                                                     data-ml="മൊത്തത്തിന്റെ %" 
                                                     data-ta="மொத்தத்தின் %"
                                                     data-hi="कुल का %"
                                                     data-kn="ಒಟ್ಟಿನ %"
                                                     data-te="మొత్తం %">% of Total</span></th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $cat_total = 0;
                    $cat_count = 0;
                    while ($cat = $categoryBreakdown->fetch_assoc()): 
                        $cat_total += $cat['total'];
                        $cat_count += $cat['count'];
                        $percentage = $summary['total_amount'] > 0 ? ($cat['total'] / $summary['total_amount']) * 100 : 0;
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($cat['category_name']); ?></td>
                        <td class="text-right"><?php echo $cat['count']; ?></td>
                        <td class="text-right"><?php echo formatCurrency($cat['total']); ?></td>
                        <td class="text-right"><?php echo number_format($percentage, 1); ?>%</td>
                    </tr>
                    <?php endwhile; ?>
                    <tr class="total-row">
                        <td><strong><span data-en="TOTAL" 
                                          data-ml="മൊത്തം" 
                                          data-ta="மொத்தம்"
                                          data-hi="कुल"
                                          data-kn="ಒಟ್ಟು"
                                          data-te="మొత్తం">TOTAL</span></strong></td>
                        <td class="text-right"><strong><?php echo $cat_count; ?></strong></td>
                        <td class="text-right"><strong><?php echo formatCurrency($cat_total); ?></strong></td>
                        <td class="text-right"><strong>100%</strong></td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <div class="card">
            <div class="action-buttons">
                <h2 style="margin: 0;"><span data-en="All Expenses" 
                                              data-ml="എല്ലാ ചെലവുകളും" 
                                              data-ta="அனைத்து செலவுகள்"
                                              data-hi="सभी व्यय"
                                              data-kn="ಎಲ್ಲಾ ಖರ್ಚುಗಳು"
                                              data-te="అన్ని ఖర్చులు">All Expenses</span></h2>
                <div>
                    <a href="add_expense.php" class="btn btn-success"
                       data-en="+ Add New Expense" 
                       data-ml="+ പുതിയ ചെലവ് ചേർക്കുക" 
                       data-ta="+ புதிய செலவைச் சேர்க்கவும்"
                       data-hi="+ नया व्यय जोड़ें"
                       data-kn="+ ಹೊಸ ಖರ್ಚು ಸೇರಿಸಿ"
                       data-te="+ కొత్త ఖర్చును జోడించండి">+ Add New Expense</a>
                    <a href="expense_categories.php" class="btn btn-info"
                       data-en="Manage Categories" 
                       data-ml="വിഭാഗങ്ങൾ നിയന്ത്രിക്കുക" 
                       data-ta="வகைகளை நிர்வகிக்கவும்"
                       data-hi="श्रेणियाँ प्रबंधित करें"
                       data-kn="ವರ್ಗಗಳನ್ನು ನಿರ್ವಹಿಸಿ"
                       data-te="వర్గాలను నిర్వహించండి">Manage Categories</a>
                    <a href="vendors.php" class="btn btn-info"
                       data-en="Manage Vendors" 
                       data-ml="വെണ്ടർമാരെ നിയന്ത്രിക്കുക" 
                       data-ta="விற்பனையாளர்களை நிர்வகிக்கவும்"
                       data-hi="विक्रेताओं को प्रबंधित करें"
                       data-kn="ವೆಂಡರ್‌ಗಳನ್ನು ನಿರ್ವಹಿಸಿ"
                       data-te="విక్రేతలను నిర్వహించండి">Manage Vendors</a>
                </div>
            </div>
            
            <table>
                <thead>
                    <tr>
                        <th><span data-en="Expense No" 
                                  data-ml="ചെലവ് നമ്പർ" 
                                  data-ta="செலவு எண்"
                                  data-hi="व्यय संख्या"
                                  data-kn="ಖರ್ಚು ಸಂಖ್ಯೆ"
                                  data-te="ఖర్చు నంబర్">Expense No</span></th>
                        <th><span data-en="Date" 
                                  data-ml="തീയതി" 
                                  data-ta="தேதி"
                                  data-hi="तिथि"
                                  data-kn="ದಿನಾಂಕ"
                                  data-te="తేదీ">Date</span></th>
                        <th><span data-en="Category" 
                                  data-ml="വിഭാഗം" 
                                  data-ta="வகை"
                                  data-hi="श्रेणी"
                                  data-kn="ವರ್ಗ"
                                  data-te="వర్గం">Category</span></th>
                        <th><span data-en="Description" 
                                  data-ml="വിവരണം" 
                                  data-ta="விளக்கம்"
                                  data-hi="विवरण"
                                  data-kn="ವಿವರಣೆ"
                                  data-te="వివరణ">Description</span></th>
                        <th><span data-en="Vendor" 
                                  data-ml="വെണ്ടർ" 
                                  data-ta="விற்பனையாளர்"
                                  data-hi="विक्रेता"
                                  data-kn="ವೆಂಡರ್"
                                  data-te="విక్రేత">Vendor</span></th>
                        <th class="text-right"><span data-en="Amount" 
                                                     data-ml="തുക" 
                                                     data-ta="தொகை"
                                                     data-hi="राशि"
                                                     data-kn="ಮೊತ್ತ"
                                                     data-te="మొత్తం">Amount</span></th>
                        <th><span data-en="Payment" 
                                  data-ml="പേയ്മെന്റ്" 
                                  data-ta="கட்டணம்"
                                  data-hi="भुगतान"
                                  data-kn="ಪಾವತಿ"
                                  data-te="చెల్లింపు">Payment</span></th>
                        <th><span data-en="Status" 
                                  data-ml="സ്ഥിതി" 
                                  data-ta="நிலை"
                                  data-hi="स्थिति"
                                  data-kn="ಸ್ಥಿತಿ"
                                  data-te="స్థితి">Status</span></th>
                        <th><span data-en="Actions" 
                                  data-ml="പ്രവർത്തനങ്ങൾ" 
                                  data-ta="செயல்கள்"
                                  data-hi="कार्रवाइयाँ"
                                  data-kn="ಕ್ರಿಯೆಗಳು"
                                  data-te="చర్యలు">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($expenses->num_rows > 0): ?>
                        <?php while ($expense = $expenses->fetch_assoc()): ?>
                        <tr>
                            <td><strong><?php echo $expense['expense_no']; ?></strong></td>
                            <td><?php echo date('d/m/Y', strtotime($expense['expense_date'])); ?></td>
                            <td>
                                <?php echo htmlspecialchars($expense['category_name']); ?>
                                <?php if ($expense['subcategory_name']): ?>
                                <br><small><?php echo htmlspecialchars($expense['subcategory_name']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars(substr($expense['description'], 0, 50)); ?></td>
                            <td><?php echo htmlspecialchars($expense['vendor_name'] ?: '-'); ?></td>
                            <td class="text-right"><?php echo formatCurrency($expense['total_amount']); ?></td>
                            <td><?php echo $expense['payment_mode']; ?></td>
                            <td>
                                <span class="status-badge status-<?php echo strtolower($expense['payment_status']); ?>">
                                    <?php echo $expense['payment_status']; ?>
                                </span>
                            </td>
                            <td>
                                <a href="view_expense.php?id=<?php echo $expense['id']; ?>" class="btn btn-info"
                                   data-en="View" 
                                   data-ml="കാണുക" 
                                   data-ta="காண்க"
                                   data-hi="देखें"
                                   data-kn="ನೋಡಿ"
                                   data-te="చూడండి">View</a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" style="text-align: center; padding: 30px;">
                                <span data-en="No expenses found for the selected period." 
                                      data-ml="തിരഞ്ഞെടുത്ത കാലയളവിൽ ചെലവുകളൊന്നും കണ്ടെത്തിയില്ല." 
                                      data-ta="தேர்ந்தெடுக்கப்பட்ட காலத்திற்கு செலவுகள் எதுவும் கிடைக்கவில்லை."
                                      data-hi="चयनित अवधि के लिए कोई व्यय नहीं मिला।"
                                      data-kn="ಆಯ್ಕೆ ಮಾಡಿದ ಅವಧಿಗೆ ಯಾವುದೇ ಖರ್ಚುಗಳು ಕಂಡುಬಂದಿಲ್ಲ."
                                      data-te="ఎంచుకున్న వ్యవధికి ఖర్చులు ఏవీ కనుగొనబడలేదు.">No expenses found for the selected period.</span>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <script>
        // Get current language from localStorage or default to English
        let currentLang = localStorage.getItem('lang') || 'en';

        // Apply language on page load
        window.addEventListener('DOMContentLoaded', function() {
            // Set the select dropdown to the saved language
            document.getElementById('langSelect').value = currentLang;
            applyLanguage(currentLang);
        });

        function setLanguage(lang) {
            currentLang = lang;
            localStorage.setItem('lang', lang);
            applyLanguage(lang);
        }

        function applyLanguage(lang) {
            // Update all elements with data attributes
            document.querySelectorAll('[data-en]').forEach(element => {
                if (element.hasAttribute('data-' + lang)) {
                    element.textContent = element.getAttribute('data-' + lang);
                }
            });

            // Update select options
            updateSelectOptions(lang);
            
            // Update body class for font
            document.body.className = '';
            if (lang !== 'en') {
                document.body.classList.add(lang);
            }
        }

        function updateSelectOptions(lang) {
            // Update category select first option
            const categorySelect = document.querySelector('select[name="category"]');
            if (categorySelect) {
                const firstOption = categorySelect.options[0];
                if (firstOption && firstOption.hasAttribute('data-' + lang)) {
                    firstOption.textContent = firstOption.getAttribute('data-' + lang);
                }
            }

            // Update status select options
            const statusSelect = document.querySelector('select[name="status"]');
            if (statusSelect) {
                Array.from(statusSelect.options).forEach(option => {
                    const value = option.value.toLowerCase();
                    if (option.hasAttribute('data-' + lang)) {
                        option.textContent = option.getAttribute('data-' + lang);
                    }
                });
            }
        }
    </script>
</body>
</html>
