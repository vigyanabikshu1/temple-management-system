<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once 'config.php';
requireLogin();

// Get filter parameters
$report_type = isset($_GET['report_type']) ? $_GET['report_type'] : 'daily';
$from_date = isset($_GET['from_date']) ? $_GET['from_date'] : date('Y-m-d');
$to_date = isset($_GET['to_date']) ? $_GET['to_date'] : date('Y-m-d');
$vazhipadu_type = isset($_GET['vazhipadu_type']) ? intval($_GET['vazhipadu_type']) : 0;
$payment_mode = isset($_GET['payment_mode']) ? $_GET['payment_mode'] : '';

// Build query based on filters
$whereConditions = ["vb.payment_status = 'Completed'"];

if ($report_type === 'daily') {
    $whereConditions[] = "DATE(vb.booking_date) = '$from_date'";
} elseif ($report_type === 'range') {
    $whereConditions[] = "DATE(vb.booking_date) BETWEEN '$from_date' AND '$to_date'";
} elseif ($report_type === 'monthly') {
    $month = date('Y-m', strtotime($from_date));
    $whereConditions[] = "DATE_FORMAT(vb.booking_date, '%Y-%m') = '$month'";
}

if ($vazhipadu_type > 0) {
    $whereConditions[] = "vb.vazhipadu_type_id = $vazhipadu_type";
}

if ($payment_mode !== '') {
    $whereConditions[] = "vb.payment_mode = '$payment_mode'";
}

$whereClause = implode(' AND ', $whereConditions);

// Get summary statistics
$summaryQuery = "SELECT 
    COUNT(*) as total_bookings,
    COALESCE(SUM(vb.amount), 0) as total_amount,
    COALESCE(SUM(CASE WHEN vb.payment_mode = 'Cash' THEN vb.amount ELSE 0 END), 0) as cash_amount,
    COALESCE(SUM(CASE WHEN vb.payment_mode = 'Card' THEN vb.amount ELSE 0 END), 0) as card_amount,
    COALESCE(SUM(CASE WHEN vb.payment_mode = 'UPI' THEN vb.amount ELSE 0 END), 0) as upi_amount,
    COALESCE(SUM(CASE WHEN vb.payment_mode = 'Online' THEN vb.amount ELSE 0 END), 0) as online_amount
    FROM vazhipadu_bookings vb
    WHERE $whereClause";
$summaryResult = $conn->query($summaryQuery);
$summary = $summaryResult->fetch_assoc();

// Get vazhipadu-wise breakdown
$breakdownQuery = "SELECT 
    vt.name as vazhipadu_name,
    COUNT(*) as count,
    SUM(vb.amount) as total_amount
    FROM vazhipadu_bookings vb
    JOIN vazhipadu_types vt ON vb.vazhipadu_type_id = vt.id
    WHERE $whereClause
    GROUP BY vt.id, vt.name
    ORDER BY total_amount DESC";
$breakdownResult = $conn->query($breakdownQuery);

// Get detailed bookings with devotee info
$detailsQuery = "SELECT vb.*, vt.name as vazhipadu_name, d.name as receipt_holder_name, d.phone as receipt_holder_phone
    FROM vazhipadu_bookings vb 
    JOIN vazhipadu_types vt ON vb.vazhipadu_type_id = vt.id 
    LEFT JOIN devotees d ON vb.devotee_id = d.id
    WHERE $whereClause
    ORDER BY vb.created_at DESC";
$detailsResult = $conn->query($detailsQuery);

// Get all vazhipadu types for filter
$typesQuery = "SELECT * FROM vazhipadu_types WHERE is_active = 1 ORDER BY name";
$types = $conn->query($typesQuery);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - <?php echo $_SESSION['temple_name']; ?></title>
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
            background: linear-gradient(135deg, #ff6b35 0%, #f7931e 100%);
            color: white;
            padding: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            position: relative;
        }
        .header h1 {
            font-size: 24px;
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
            border-color: #ff6b35;
            box-shadow: 0 0 0 3px rgba(255,107,53,0.2);
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
            background: #ff6b35;
            color: white;
        }
        .container {
            max-width: 1400px;
            margin: 20px auto;
            padding: 0 20px;
        }
        .filter-box {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .filter-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
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
        select:focus, input:focus {
            outline: none;
            border-color: #ff6b35;
        }
        .btn {
            padding: 10px 25px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            transition: background 0.3s;
        }
        .btn-primary {
            background: #ff6b35;
            color: white;
        }
        .btn-primary:hover {
            background: #e55a2b;
        }
        .btn-secondary {
            background: #6c757d;
            color: white;
            margin-left: 10px;
        }
        .btn-secondary:hover {
            background: #5a6268;
        }
        .btn-success {
            background: #28a745;
            color: white;
            margin-left: 10px;
        }
        .btn-success:hover {
            background: #218838;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .stat-card h3 {
            color: #666;
            font-size: 13px;
            margin-bottom: 8px;
        }
        .stat-card .value {
            font-size: 28px;
            font-weight: bold;
            color: #ff6b35;
        }
        .card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .card h2 {
            margin-bottom: 20px;
            color: #333;
            border-bottom: 2px solid #ff6b35;
            padding-bottom: 10px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 12px 8px;
            text-align: left;
            border-bottom: 1px solid #eee;
            font-size: 13px;
        }
        th {
            background: #f8f9fa;
            font-weight: 600;
            color: #333;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .total-row {
            font-weight: bold;
            background: #fff8f0;
        }
        .sub-info {
            font-size: 11px;
            color: #666;
            margin-top: 3px;
        }
        .highlight-cell {
            background: #f0f8ff;
        }
        @media print {
            .no-print {
                display: none;
            }
            body {
                background: white;
            }
            .card {
                box-shadow: none;
                page-break-inside: avoid;
            }
        }
        .button-group {
            display: flex;
            gap: 10px;
            margin-top: 15px;
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
    <div class="header no-print">
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
        <h1><?php echo $_SESSION['temple_name']; ?> - <span data-en="Reports" 
                                                              data-ml="റിപ്പോർട്ടുകൾ" 
                                                              data-ta="அறிக்கைகள்"
                                                              data-hi="रिपोर्ट"
                                                              data-kn="ವರದಿಗಳು"
                                                              data-te="నివేదికలు">Reports</span></h1>
    </div>
    
    <div class="nav no-print">
        <ul>
            <li><a href="index.php" 
                   data-en="Dashboard" 
                   data-ml="ഡാഷ്ബോർഡ്" 
                   data-ta="டாஷ்போர்டு"
                   data-hi="डैशबोर्ड"
                   data-kn="ಡ್ಯಾಶ್‌ಬೋರ್ಡ್"
                   data-te="డాష్‌బోర్డ్">Dashboard</a></li>
            <li><a href="new_booking.php" 
                   data-en="Single Booking" 
                   data-ml="ഒറ്റ ബുക്കിംഗ്" 
                   data-ta="ஒற்றை முன்பதிவு"
                   data-hi="सिंगल बुकिंग"
                   data-kn="ಸಿಂಗಲ್ ಬುಕಿಂಗ್"
                   data-te="సింగిల్ బుకింగ్">Single Booking</a></li>
            <li><a href="new_booking_multi.php" 
                   data-en="Multiple Booking" 
                   data-ml="മൾട്ടിപ്പിൾ ബുക്കിംഗ്" 
                   data-ta="பல முன்பதிவு"
                   data-hi="मल्टीपल बुकिंग"
                   data-kn="ಮಲ್ಟಿಪಲ್ ಬುಕಿಂಗ್"
                   data-te="మల్టిపుల్ బుకింగ్">Multiple Booking</a></li>
            <li><a href="search.php" 
                   data-en="Search" 
                   data-ml="തിരയുക" 
                   data-ta="தேடல்"
                   data-hi="खोजें"
                   data-kn="ಹುಡುಕಿ"
                   data-te="శోధించు">Search</a></li>
            <li><a href="reports.php" class="active" 
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
        <div class="filter-box no-print">
            <h2 style="margin-bottom: 20px;" 
                data-en="Generate Report" 
                data-ml="റിപ്പോർട്ട് സൃഷ്ടിക്കുക" 
                data-ta="அறிக்கையை உருவாக்கவும்"
                data-hi="रिपोर्ट बनाएं"
                data-kn="ವರದಿಯನ್ನು ರಚಿಸಿ"
                data-te="నివేదికను సృష్టించండి">Generate Report</h2>
            <form method="GET" id="reportForm">
                <div class="filter-row">
                    <div class="form-group">
                        <label data-en="Report Type" 
                               data-ml="റിപ്പോർട്ട് തരം" 
                               data-ta="அறிக்கை வகை"
                               data-hi="रिपोर्ट प्रकार"
                               data-kn="ವರದಿ ಪ್ರಕಾರ"
                               data-te="నివేదిక రకం">Report Type</label>
                        <select name="report_type" id="report_type" onchange="updateDateFields()">
                            <option value="daily" <?php echo $report_type === 'daily' ? 'selected' : ''; ?>
                                    data-en="Daily" 
                                    data-ml="ദിവസേനയുള്ള" 
                                    data-ta="தினசரி"
                                    data-hi="दैनिक"
                                    data-kn="ದೈನಂದಿನ"
                                    data-te="రోజువారీ">Daily</option>
                            <option value="range" <?php echo $report_type === 'range' ? 'selected' : ''; ?>
                                    data-en="Date Range" 
                                    data-ml="തീയതി ശ്രേണി" 
                                    data-ta="தேதி வரம்பு"
                                    data-hi="तिथि सीमा"
                                    data-kn="ದಿನಾಂಕ ವ್ಯಾಪ್ತಿ"
                                    data-te="తేదీ పరిధి">Date Range</option>
                            <option value="monthly" <?php echo $report_type === 'monthly' ? 'selected' : ''; ?>
                                    data-en="Monthly" 
                                    data-ml="മാസിക" 
                                    data-ta="மாதாந்திர"
                                    data-hi="मासिक"
                                    data-kn="ಮಾಸಿಕ"
                                    data-te="నెలవారీ">Monthly</option>
                        </select>
                    </div>
                    
                    <div class="form-group" id="fromDateGroup">
                        <label id="fromDateLabel"
                               data-en="Date" 
                               data-ml="തീയതി" 
                               data-ta="தேதி"
                               data-hi="तिथि"
                               data-kn="ದಿನಾಂಕ"
                               data-te="తేదీ">Date</label>
                        <input type="date" name="from_date" value="<?php echo $from_date; ?>" max="<?php echo date('Y-m-d'); ?>">
                    </div>
                    
                    <div class="form-group" id="toDateGroup" style="<?php echo $report_type !== 'range' ? 'display:none;' : ''; ?>">
                        <label data-en="To Date" 
                               data-ml="വരെയുള്ള തീയതി" 
                               data-ta="கடைசி தேதி"
                               data-hi="अंतिम तिथि"
                               data-kn="ಕೊನೆಯ ದಿನಾಂಕ"
                               data-te="చివరి తేదీ">To Date</label>
                        <input type="date" name="to_date" value="<?php echo $to_date; ?>" max="<?php echo date('Y-m-d'); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label data-en="Vazhipadu Type" 
                               data-ml="വഴിപാട് തരം" 
                               data-ta="வழிபாட்டு வகை"
                               data-hi="वझिपाडु प्रकार"
                               data-kn="ವಜಿಪಾಡು ಪ್ರಕಾರ"
                               data-te="వజిపాడు రకం">Vazhipadu Type</label>
                        <select name="vazhipadu_type">
                            <option value="0"
                                    data-en="All Types" 
                                    data-ml="എല്ലാം" 
                                    data-ta="அனைத்தும்"
                                    data-hi="सभी प्रकार"
                                    data-kn="ಎಲ್ಲಾ ವಿಧಗಳು"
                                    data-te="అన్ని రకాలు">All Types</option>
                            <?php while ($type = $types->fetch_assoc()): ?>
                            <option value="<?php echo $type['id']; ?>" 
                                <?php echo $vazhipadu_type == $type['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($type['name']); ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label data-en="Payment Mode" 
                               data-ml="പേയ്മെന്റ് മോഡ്" 
                               data-ta="கட்டண முறை"
                               data-hi="भुगतान मोड"
                               data-kn="ಪಾವತಿ ವಿಧಾನ"
                               data-te="చెల్లింపు పద్ధతి">Payment Mode</label>
                        <select name="payment_mode">
                            <option value=""
                                    data-en="All Modes" 
                                    data-ml="എല്ലാ മോഡുകളും" 
                                    data-ta="அனைத்து முறைகளும்"
                                    data-hi="सभी मोड"
                                    data-kn="ಎಲ್ಲಾ ವಿಧಾನಗಳು"
                                    data-te="అన్ని పద్ధతులు">All Modes</option>
                            <option value="Cash" <?php echo $payment_mode === 'Cash' ? 'selected' : ''; ?>
                                    data-en="Cash" 
                                    data-ml="പണം" 
                                    data-ta="பணம்"
                                    data-hi="नकद"
                                    data-kn="ರೋಕಡ"
                                    data-te="నగదు">Cash</option>
                            <option value="Card" <?php echo $payment_mode === 'Card' ? 'selected' : ''; ?>
                                    data-en="Card" 
                                    data-ml="കാർഡ്" 
                                    data-ta="கார்டு"
                                    data-hi="कार्ड"
                                    data-kn="ಕಾರ್ಡ್"
                                    data-te="కార్డ్">Card</option>
                            <option value="UPI" <?php echo $payment_mode === 'UPI' ? 'selected' : ''; ?>
                                    data-en="UPI" 
                                    data-ml="യുപിഐ" 
                                    data-ta="யூபிஐ"
                                    data-hi="यूपीआई"
                                    data-kn="ಯುಪಿಐ"
                                    data-te="యుపిఐ">UPI</option>
                            <option value="Online" <?php echo $payment_mode === 'Online' ? 'selected' : ''; ?>
                                    data-en="Online" 
                                    data-ml="ഓൺലൈൻ" 
                                    data-ta="ஆன்லைன்"
                                    data-hi="ऑनलाइन"
                                    data-kn="ಆನ್‌ಲೈನ್"
                                    data-te="ఆన్‌లైన్">Online</option>
                        </select>
                    </div>
                </div>
                
                <div class="button-group">
                    <button type="submit" class="btn btn-primary"
                            data-en="Generate Report" 
                            data-ml="റിപ്പോർട്ട് സൃഷ്ടിക്കുക" 
                            data-ta="அறிக்கையை உருவாக்கவும்"
                            data-hi="रिपोर्ट बनाएं"
                            data-kn="ವರದಿಯನ್ನು ರಚಿಸಿ"
                            data-te="నివేదికను సృష్టించండి">Generate Report</button>
                    <button type="button" class="btn btn-secondary" onclick="window.print()"
                            data-en="Print Report" 
                            data-ml="റിപ്പോർട്ട് പ്രിന്റ് ചെയ്യുക" 
                            data-ta="அறிக்கையை அச்சிடுக"
                            data-hi="रिपोर्ट प्रिंट करें"
                            data-kn="ವರದಿ ಮುದ್ರಿಸಿ"
                            data-te="నివేదికను ముద్రించండి">Print Report</button>
                    <button type="button" class="btn btn-success" onclick="exportToCSV()"
                            data-en="Export to CSV" 
                            data-ml="സിഎസ്വിയായി എക്സ്പോർട്ട് ചെയ്യുക" 
                            data-ta="CSV ஆக ஏற்றுமதி செய்க"
                            data-hi="CSV में निर्यात करें"
                            data-kn="CSV ಗೆ ರಫ್ತು ಮಾಡಿ"
                            data-te="CSV కి ఎగుమతి చేయండి">Export to CSV</button>
                </div>
            </form>
        </div>
        
        <h2 style="margin-bottom: 20px;">
            <span data-en="Report Summary" 
                  data-ml="റിപ്പോർട്ട് സംഗ്രഹം" 
                  data-ta="அறிக்கை சுருக்கம்"
                  data-hi="रिपोर्ट सारांश"
                  data-kn="ವರದಿ ಸಾರಾಂಶ"
                  data-te="నివేదిక సారాంశం">Report Summary</span>
            <?php 
            if ($report_type === 'daily') {
                echo " - " . date('d M Y', strtotime($from_date));
            } elseif ($report_type === 'range') {
                echo " - " . date('d M Y', strtotime($from_date)) . " to " . date('d M Y', strtotime($to_date));
            } elseif ($report_type === 'monthly') {
                echo " - " . date('F Y', strtotime($from_date));
            }
            ?>
        </h2>
        
        <div class="stats-grid">
            <div class="stat-card">
                <h3><span data-en="Total Bookings" 
                          data-ml="മൊത്തം ബുക്കിംഗുകൾ" 
                          data-ta="மொத்த முன்பதிவுகள்"
                          data-hi="कुल बुकिंग"
                          data-kn="ಒಟ್ಟು ಬುಕಿಂಗ್‌ಗಳು"
                          data-te="మొత్తం బుకింగ్‌లు">Total Bookings</span></h3>
                <div class="value"><?php echo $summary['total_bookings']; ?></div>
            </div>
            <div class="stat-card">
                <h3><span data-en="Total Collection" 
                          data-ml="മൊത്തം ശേഖരണം" 
                          data-ta="மொத்த வசூல்"
                          data-hi="कुल संग्रह"
                          data-kn="ಒಟ್ಟು ಸಂಗ್ರಹ"
                          data-te="మొత్తం సేకరణ">Total Collection</span></h3>
                <div class="value"><?php echo formatCurrency($summary['total_amount']); ?></div>
            </div>
            <div class="stat-card">
                <h3><span data-en="Cash" 
                          data-ml="പണം" 
                          data-ta="பணம்"
                          data-hi="नकद"
                          data-kn="ರೋಕಡ"
                          data-te="నగదు">Cash</span></h3>
                <div class="value"><?php echo formatCurrency($summary['cash_amount']); ?></div>
            </div>
            <div class="stat-card">
                <h3><span data-en="Card" 
                          data-ml="കാർഡ്" 
                          data-ta="கார்டு"
                          data-hi="कार्ड"
                          data-kn="ಕಾರ್ಡ್"
                          data-te="కార్డ్">Card</span></h3>
                <div class="value"><?php echo formatCurrency($summary['card_amount']); ?></div>
            </div>
            <div class="stat-card">
                <h3><span data-en="UPI" 
                          data-ml="യുപിഐ" 
                          data-ta="யூபிஐ"
                          data-hi="यूपीआई"
                          data-kn="ಯುಪಿಐ"
                          data-te="యుపిఐ">UPI</span></h3>
                <div class="value"><?php echo formatCurrency($summary['upi_amount']); ?></div>
            </div>
            <div class="stat-card">
                <h3><span data-en="Online" 
                          data-ml="ഓൺലൈൻ" 
                          data-ta="ஆன்லைன்"
                          data-hi="ऑनलाइन"
                          data-kn="ಆನ್‌ಲೈನ್"
                          data-te="ఆన్‌లైన్">Online</span></h3>
                <div class="value"><?php echo formatCurrency($summary['online_amount']); ?></div>
            </div>
        </div>
        
        <div class="card">
            <h2><span data-en="Vazhipadu-wise Breakdown" 
                      data-ml="വഴിപാട് അനുസരിച്ചുള്ള വിശദാംശങ്ങൾ" 
                      data-ta="வழிபாட்டு வகையின் அடிப்படையில் விவரங்கள்"
                      data-hi="वझिपाडु के अनुसार विवरण"
                      data-kn="ವಜಿಪಾಡು ಪ್ರಕಾರ ವಿವರಗಳು"
                      data-te="వజిపాడు రకం ఆధారంగా వివరాలు">Vazhipadu-wise Breakdown</span></h2>
            <table>
                <thead>
                    <tr>
                        <th><span data-en="Vazhipadu Type" 
                                  data-ml="വഴിപാട് തരം" 
                                  data-ta="வழிபாட்டு வகை"
                                  data-hi="वझिपाडु प्रकार"
                                  data-kn="ವಜಿಪಾಡು ಪ್ರಕಾರ"
                                  data-te="వజిపాడు రకం">Vazhipadu Type</span></th>
                        <th class="text-center"><span data-en="Count" 
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
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $breakdown_total = 0;
                    $breakdown_count = 0;
                    while ($row = $breakdownResult->fetch_assoc()): 
                        $breakdown_total += $row['total_amount'];
                        $breakdown_count += $row['count'];
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['vazhipadu_name']); ?></td>
                        <td class="text-center"><?php echo $row['count']; ?></td>
                        <td class="text-right"><?php echo formatCurrency($row['total_amount']); ?></td>
                    </tr>
                    <?php endwhile; ?>
                    <tr class="total-row">
                        <td><strong><span data-en="TOTAL" 
                                          data-ml="മൊത്തം" 
                                          data-ta="மொத்தம்"
                                          data-hi="कुल"
                                          data-kn="ಒಟ್ಟು"
                                          data-te="మొత్తం">TOTAL</span></strong></td>
                        <td class="text-center"><strong><?php echo $breakdown_count; ?></strong></td>
                        <td class="text-right"><strong><?php echo formatCurrency($breakdown_total); ?></strong></td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <div class="card">
            <h2><span data-en="Detailed Bookings" 
                      data-ml="വിശദമായ ബുക്കിംഗുകൾ" 
                      data-ta="விரிவான முன்பதிவுகள்"
                      data-hi="विस्तृत बुकिंग"
                      data-kn="ವಿವರವಾದ ಬುಕಿಂಗ್‌ಗಳು"
                      data-te="వివరణాత్మక బుకింగ్‌లు">Detailed Bookings</span></h2>
            <table id="detailsTable">
                <thead>
                    <tr>
                        <th><span data-en="Receipt No" 
                                  data-ml="രസീത് നമ്പർ" 
                                  data-ta="ரசீது எண்"
                                  data-hi="रसीद नंबर"
                                  data-kn="ರಸೀದಿ ಸಂಖ್ಯೆ"
                                  data-te="రసీదు సంఖ్య">Receipt No</span></th>
                        <th><span data-en="Date & Time" 
                                  data-ml="തീയതിയും സമയവും" 
                                  data-ta="தேதி & நேரம்"
                                  data-hi="तिथि और समय"
                                  data-kn="ದಿನಾಂಕ ಮತ್ತು ಸಮಯ"
                                  data-te="తేదీ & సమయం">Date & Time</span></th>
                        <th><span data-en="Receipt Holder" 
                                  data-ml="രസീത് ഉടമ" 
                                  data-ta="ரசீது வைத்திருப்பவர்"
                                  data-hi="रसीद धारक"
                                  data-kn="ರಸೀದಿ ಹೊಂದಿರುವವರು"
                                  data-te="రసీదు హోల్డర్">Receipt Holder</span></th>
                        <th><span data-en="Vazhipadu For" 
                                  data-ml="ഏതിനുള്ള വഴിപാട്" 
                                  data-ta="எவருக்கான வழிபாடு"
                                  data-hi="किसके लिए वझिपाडु"
                                  data-kn="ಯಾರಿಗಾಗಿ ವಜಿಪಾಡು"
                                  data-te="ఎవరికి వజిపాడు">Vazhipadu For</span></th>
                        <th><span data-en="Vazhipadu Type" 
                                  data-ml="വഴിപാട് തരം" 
                                  data-ta="வழிபாட்டு வகை"
                                  data-hi="वझिपाडु प्रकार"
                                  data-kn="ವಜಿಪಾಡು ಪ್ರಕಾರ"
                                  data-te="వజిపాడు రకం">Vazhipadu Type</span></th>
                        <th class="text-center"><span data-en="Qty" 
                                                      data-ml="എണ്ണം" 
                                                      data-ta="அளவு"
                                                      data-hi="मात्रा"
                                                      data-kn="ಪ್ರಮಾಣ"
                                                      data-te="పరిమాణం">Qty</span></th>
                        <th class="text-right"><span data-en="Amount" 
                                                     data-ml="തുക" 
                                                     data-ta="தொகை"
                                                     data-hi="राशि"
                                                     data-kn="ಮೊತ್ತ"
                                                     data-te="మొత్తం">Amount</span></th>
                        <th class="text-center"><span data-en="Payment" 
                                                      data-ml="പേയ്മെന്റ്" 
                                                      data-ta="கட்டணம்"
                                                      data-hi="भुगतान"
                                                      data-kn="ಪಾವತಿ"
                                                      data-te="చెల్లింపు">Payment</span></th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $details_total = 0;
                    while ($row = $detailsResult->fetch_assoc()): 
                        $details_total += $row['amount'];
                    ?>
                    <tr>
                        <td><?php echo $row['receipt_no']; ?></td>
                        <td><?php echo date('d/m/Y h:i A', strtotime($row['created_at'])); ?></td>
                        <td class="highlight-cell">
                            <strong><?php echo htmlspecialchars($row['receipt_holder_name']); ?></strong>
                            <?php if ($row['receipt_holder_phone']): ?>
                            <div class="sub-info">📞 <?php echo htmlspecialchars($row['receipt_holder_phone']); ?></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <strong><?php echo htmlspecialchars($row['devotee_name']); ?></strong>
                            <?php if ($row['star_name']): ?>
                            <div class="sub-info">⭐ <?php echo htmlspecialchars($row['star_name']); ?></div>
                            <?php endif; ?>
                            <?php if ($row['seva_date']): ?>
                            <div class="sub-info">📅 <?php echo date('d/m/Y', strtotime($row['seva_date'])); ?></div>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($row['vazhipadu_name']); ?></td>
                        <td class="text-center"><?php echo $row['quantity'] ?: 1; ?></td>
                        <td class="text-right"><strong><?php echo formatCurrency($row['amount']); ?></strong></td>
                        <td class="text-center"><?php echo $row['payment_mode']; ?></td>
                    </tr>
                    <?php endwhile; ?>
                    <tr class="total-row">
                        <td colspan="6" class="text-right"><strong><span data-en="TOTAL" 
                                                                          data-ml="മൊത്തം" 
                                                                          data-ta="மொத்தம்"
                                                                          data-hi="कुल"
                                                                          data-kn="ಒಟ್ಟು"
                                                                          data-te="మొత్తం">TOTAL</span></strong></td>
                        <td class="text-right"><strong><?php echo formatCurrency($details_total); ?></strong></td>
                        <td></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    
    <script>
        // Placeholder translations for select options
        const selectOptionsTranslations = {
            report_type: {
                daily: {
                    en: "Daily",
                    ml: "ദിവസേനയുള്ള",
                    ta: "தினசரி",
                    hi: "दैनिक",
                    kn: "ದೈನಂದಿನ",
                    te: "రోజువారీ"
                },
                range: {
                    en: "Date Range",
                    ml: "തീയതി ശ്രേണി",
                    ta: "தேதி வரம்பு",
                    hi: "तिथि सीमा",
                    kn: "ದಿನಾಂಕ ವ್ಯಾಪ್ತಿ",
                    te: "తేదీ పరిధి"
                },
                monthly: {
                    en: "Monthly",
                    ml: "മാസിക",
                    ta: "மாதாந்திர",
                    hi: "मासिक",
                    kn: "ಮಾಸಿಕ",
                    te: "నెలవారీ"
                }
            },
            vazhipadu_type: {
                all: {
                    en: "All Types",
                    ml: "എല്ലാം",
                    ta: "அனைத்தும்",
                    hi: "सभी प्रकार",
                    kn: "ಎಲ್ಲಾ ವಿಧಗಳು",
                    te: "అన్ని రకాలు"
                }
            },
            payment_mode: {
                all: {
                    en: "All Modes",
                    ml: "എല്ലാ മോഡുകളും",
                    ta: "அனைத்து முறைகளும்",
                    hi: "सभी मोड",
                    kn: "ಎಲ್ಲಾ ವಿಧಾನಗಳು",
                    te: "అన్ని పద్ధతులు"
                },
                cash: {
                    en: "Cash",
                    ml: "പണം",
                    ta: "பணம்",
                    hi: "नकद",
                    kn: "ರೋಕಡ",
                    te: "నగదు"
                },
                card: {
                    en: "Card",
                    ml: "കാർഡ്",
                    ta: "கார்டு",
                    hi: "कार्ड",
                    kn: "ಕಾರ್ಡ್",
                    te: "కార్డ్"
                },
                upi: {
                    en: "UPI",
                    ml: "യുപിഐ",
                    ta: "யூபிஐ",
                    hi: "यूपीआई",
                    kn: "ಯುಪಿಐ",
                    te: "యుపిఐ"
                },
                online: {
                    en: "Online",
                    ml: "ഓൺലൈൻ",
                    ta: "ஆன்லைன்",
                    hi: "ऑनलाइन",
                    kn: "ಆನ್‌ಲೈನ್",
                    te: "ఆన్‌లైన్"
                }
            }
        };

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
            
            // Update date label based on report type
            updateDateLabel(lang);
            
            // Update body class for font
            document.body.className = '';
            if (lang !== 'en') {
                document.body.classList.add(lang);
            }
        }

        function updateSelectOptions(lang) {
            // Update report_type select options
            const reportTypeSelect = document.querySelector('select[name="report_type"]');
            if (reportTypeSelect) {
                Array.from(reportTypeSelect.options).forEach(option => {
                    const value = option.value;
                    if (selectOptionsTranslations.report_type[value]) {
                        if (option.hasAttribute('data-' + lang)) {
                            option.textContent = option.getAttribute('data-' + lang);
                        }
                    }
                });
            }

            // Update payment_mode select options
            const paymentModeSelect = document.querySelector('select[name="payment_mode"]');
            if (paymentModeSelect) {
                Array.from(paymentModeSelect.options).forEach(option => {
                    const value = option.value.toLowerCase();
                    if (selectOptionsTranslations.payment_mode[value]) {
                        if (option.hasAttribute('data-' + lang)) {
                            option.textContent = option.getAttribute('data-' + lang);
                        }
                    } else if (value === '') {
                        if (option.hasAttribute('data-' + lang)) {
                            option.textContent = option.getAttribute('data-' + lang);
                        }
                    }
                });
            }

            // Update vazhipadu_type select first option
            const vazhipaduTypeSelect = document.querySelector('select[name="vazhipadu_type"]');
            if (vazhipaduTypeSelect) {
                const firstOption = vazhipaduTypeSelect.options[0];
                if (firstOption && firstOption.hasAttribute('data-' + lang)) {
                    firstOption.textContent = firstOption.getAttribute('data-' + lang);
                }
            }
        }

        function updateDateLabel(lang) {
            const reportType = document.getElementById('report_type').value;
            const fromDateLabel = document.getElementById('fromDateLabel');
            
            if (reportType === 'range') {
                if (lang === 'ml') {
                    fromDateLabel.textContent = 'തുടങ്ങുന്ന തീയതി';
                } else if (lang === 'ta') {
                    fromDateLabel.textContent = 'தொடக்க தேதி';
                } else if (lang === 'hi') {
                    fromDateLabel.textContent = 'आरंभ तिथि';
                } else if (lang === 'kn') {
                    fromDateLabel.textContent = 'ಆರಂಭ ದಿನಾಂಕ';
                } else if (lang === 'te') {
                    fromDateLabel.textContent = 'ప్రారంబ తేదీ';
                } else {
                    fromDateLabel.textContent = 'From Date';
                }
            } else if (reportType === 'monthly') {
                if (lang === 'ml') {
                    fromDateLabel.textContent = 'മാസം';
                } else if (lang === 'ta') {
                    fromDateLabel.textContent = 'மாதம்';
                } else if (lang === 'hi') {
                    fromDateLabel.textContent = 'माह';
                } else if (lang === 'kn') {
                    fromDateLabel.textContent = 'ತಿಂಗಳು';
                } else if (lang === 'te') {
                    fromDateLabel.textContent = 'నెల';
                } else {
                    fromDateLabel.textContent = 'Month';
                }
            } else {
                if (lang === 'ml') {
                    fromDateLabel.textContent = 'തീയതി';
                } else if (lang === 'ta') {
                    fromDateLabel.textContent = 'தேதி';
                } else if (lang === 'hi') {
                    fromDateLabel.textContent = 'तिथि';
                } else if (lang === 'kn') {
                    fromDateLabel.textContent = 'ದಿನಾಂಕ';
                } else if (lang === 'te') {
                    fromDateLabel.textContent = 'తేదీ';
                } else {
                    fromDateLabel.textContent = 'Date';
                }
            }
        }

        function updateDateFields() {
            const reportType = document.getElementById('report_type').value;
            const toDateGroup = document.getElementById('toDateGroup');
            const fromDateLabel = document.getElementById('fromDateLabel');
            
            if (reportType === 'range') {
                toDateGroup.style.display = 'flex';
                updateDateLabel(currentLang);
            } else if (reportType === 'monthly') {
                toDateGroup.style.display = 'none';
                updateDateLabel(currentLang);
            } else {
                toDateGroup.style.display = 'none';
                updateDateLabel(currentLang);
            }
        }
        
        function exportToCSV() {
            const table = document.getElementById('detailsTable');
            let csv = [];
            
            // Get translated headers
            const headers = [];
            table.querySelectorAll('thead th span[data-en]').forEach(th => {
                const headerText = th.getAttribute('data-' + currentLang) || th.textContent;
                headers.push(headerText);
            });
            
            // Add report header
            csv.push(["<?php echo $_SESSION['temple_name']; ?>"]);
            csv.push([headers.join(',') === 'Receipt No,Date & Time,Receipt Holder,Vazhipadu For,Vazhipadu Type,Qty,Amount,Payment' ? 
                'Vazhipadu Report' : 'വഴിപാട് റിപ്പോർട്ട്']);
            csv.push(['Generated on: ' + new Date().toLocaleString()]);
            csv.push([]);
            
            // Add table headers
            csv.push(headers);
            
            // Add table rows
            table.querySelectorAll('tbody tr:not(.total-row)').forEach(tr => {
                const cells = tr.querySelectorAll('td');
                if (cells.length > 0) {
                    const receiptNo = cells[0].textContent.trim();
                    const dateTime = cells[1].textContent.trim();
                    const holderInfo = cells[2].textContent.trim().split('\n');
                    const receiptHolder = holderInfo[0].replace('📞', '').trim();
                    const receiptPhone = holderInfo[1] ? holderInfo[1].replace('📞', '').trim() : '';
                    const vazzipaduInfo = cells[3].textContent.trim().split('\n');
                    const vazzipaduFor = vazzipaduInfo[0].replace('⭐', '').replace('📅', '').trim();
                    const nakshatra = vazzipaduInfo[1] ? vazzipaduInfo[1].replace('⭐', '').trim() : '';
                    const sevaDate = vazzipaduInfo[2] ? vazzipaduInfo[2].replace('📅', '').trim() : '';
                    const vazzipaduType = cells[4].textContent.trim();
                    const qty = cells[5].textContent.trim();
                    const amount = cells[6].textContent.trim();
                    const payment = cells[7].textContent.trim();
                    
                    csv.push([receiptNo, dateTime, receiptHolder, receiptPhone, vazzipaduFor, nakshatra, sevaDate, vazzipaduType, qty, amount, payment]);
                }
            });
            
            // Add total row
            const totalRow = table.querySelector('tbody tr.total-row');
            if (totalRow) {
                const totalCells = totalRow.querySelectorAll('td');
                csv.push(['', '', '', '', '', '', '', '', '', totalCells[1].textContent.trim(), '']);
            }
            
            // Convert to CSV string
            const csvContent = csv.map(row => row.map(cell => `"${cell}"`).join(',')).join('\n');
            
            // Download
            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'vazhipadu_report_' + new Date().getTime() + '.csv';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
        }
        
        // Initialize on load
        updateDateFields();
    </script>
</body>
</html>
