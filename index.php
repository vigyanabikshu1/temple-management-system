<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once 'config.php';
requireLogin();

// Get current temple info
$templeInfo = getCurrentTempleInfo();
$temple_id = getCurrentTempleId();

// Get today's statistics
$today = date('Y-m-d');

$statsQuery = "SELECT 
    COUNT(*) as total_bookings,
    COALESCE(SUM(amount), 0) as total_amount,
    COALESCE(SUM(CASE WHEN payment_mode = 'Cash' THEN amount ELSE 0 END), 0) as cash_amount,
    COALESCE(SUM(CASE WHEN payment_mode = 'UPI' THEN amount ELSE 0 END), 0) as upi_amount
    FROM vazhipadu_bookings 
    WHERE temple_id = $temple_id AND DATE(booking_date) = '$today' AND payment_status = 'Completed'";
$statsResult = $conn->query($statsQuery);
$stats = $statsResult->fetch_assoc();

// Get recent bookings
$recentQuery = "SELECT vb.*, vt.name as vazhipadu_name 
    FROM vazhipadu_bookings vb 
    JOIN vazhipadu_types vt ON vb.vazhipadu_type_id = vt.id 
    WHERE vb.temple_id = $temple_id
    ORDER BY vb.created_at DESC LIMIT 10";
$recentBookings = $conn->query($recentQuery);

$expenseQuery = "SELECT COALESCE(SUM(total_amount), 0) as today_expense 
                 FROM expenses 
                 WHERE temple_id = $temple_id 
                 AND DATE(expense_date) = CURDATE()
                 AND payment_status = 'Paid'";
$expenseResult = $conn->query($expenseQuery);
$todayExpense = $expenseResult->fetch_assoc()['today_expense'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $_SESSION['temple_name']; ?> - Vazhipadu Management</title>
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
            margin-bottom: 5px;
        }
        .header .user-info {
            float: right;
            margin-top: -40px;
        }
        .lang-switcher {
            position: absolute;
            top: 10px;
            right: 20px;
        }
        .lang-select {
            background: rgba(255,255,255,0.9);
            padding: 0px 0px;
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
            max-width: 1200px;
            margin: 20px auto;
            padding: 0 20px;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
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
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            text-decoration: none;
            display: inline-block;
            transition: background 0.3s;
        }
        .btn-primary {
            background: #ff6b35;
            color: white;
        }
        .btn-primary:hover {
            background: #e55a2b;
        }
        .status-completed {
            color: green;
            font-weight: bold;
        }
        .status-pending {
            color: orange;
            font-weight: bold;
        }
        /* Malayalam, Tamil, and other language font support */
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
        <h1><?php echo htmlspecialchars($templeInfo['temple_name']); ?></h1>
        <p data-en="Vazhipadu Management System" 
           data-ml="വഴിപാട് മാനേജ്മെന്റ് സിസ്റ്റം" 
           data-ta="வழிபாடு மேலாண்மை அமைப்பு"
           data-hi="वझिपाडु प्रबंधन प्रणाली"
           data-kn="ವಜಿಪಾಡು ನಿರ್ವಹಣೆ ವ್ಯವಸ್ಥೆ"
           data-te="వజిపాడు నిర్వహణ వ్యవస్థ">Vazhipadu Management System</p>
        <div class="user-info">
            <span data-en="Welcome" 
                  data-ml="സ്വാഗതം" 
                  data-ta="வரவேற்பு"
                  data-hi="स्वागत"
                  data-kn="ಸ್ವಾಗತ"
                  data-te="స్వాగతం">Welcome</span>, <?php echo htmlspecialchars($_SESSION['full_name']); ?> | 
            <a href="logout.php" style="color: white;" 
               data-en="Logout" 
               data-ml="പുറത്തുകടക്കുക" 
               data-ta="வெளியேறு"
               data-hi="लॉगआउट"
               data-kn="ನಿರ್ಗಮಿಸು"
               data-te="నిష్క్రమించు">Logout</a>
        </div>
    </div>
    
    <div class="nav">
        <ul>
            <li><a href="index.php" class="active" 
                   data-en="Dashboard" 
                   data-ml="ഡാഷ്ബോർഡ്" 
                   data-ta="டாஷ்போர்டு"
                   data-hi="डैशबोर्ड"
                   data-kn="ಡ್ಯಾಶ್‌ಬೋರ್ಡ್"
                   data-te="డాష్‌బోర్డ్">Dashboard</a></li>
            <li><a href="new_booking_multi.php" 
                   data-en="New Booking" 
                   data-ml="പുതിയ ബുക്കിംഗ്" 
                   data-ta="புதிய முன்பதிவு"
                   data-hi="नई बुकिंग"
                   data-kn="ಹೊಸ ಬುಕಿಂಗ್"
                   data-te="కొత్త బుకింగ్">New Booking</a></li>
            <li><a href="search.php" 
                   data-en="Search" 
                   data-ml="തിരയുക" 
                   data-ta="தேடல்"
                   data-hi="खोजें"
                   data-kn="ಹುಡುಕಿ"
                   data-te="శోధించు">Search</a></li>
            <li><a href="reports.php" 
                   data-en="Reports" 
                   data-ml="റിപ്പോർട്ടുകൾ" 
                   data-ta="அறிக்கைகள்"
                   data-hi="रिपोर्ट"
                   data-kn="ವರದಿಗಳು"
                   data-te="నివేదికలు">Reports</a></li>
            <li><a href="expenses.php" 
                   data-en="Expenses" 
                   data-ml="ചെലവുകൾ" 
                   data-ta="செலவுகள்"
                   data-hi="खर्चे"
                   data-kn="ವೆಚ್ಚಗಳು"
                   data-te="ఖర్చులు">Expenses</a></li>
            <?php if (isTempleAdmin()): ?>
            <li><a href="admin.php" 
                   data-en="Admin" 
                   data-ml="അഡ്മിൻ" 
                   data-ta="நிர்வாகி"
                   data-hi="व्यवस्थापक"
                   data-kn="ನಿರ್ವಾಹಕ"
                   data-te="నిర్వాహకుడు">Admin</a></li>
            <?php endif; ?>
            <?php if (isSuperAdmin()): ?>
            <li><a href="super_admin.php" 
                   data-en="Super Admin" 
                   data-ml="സൂപ്പർ അഡ്മിൻ" 
                   data-ta="மேல்நிலை நிர்வாகி"
                   data-hi="सुपर व्यवस्थापक"
                   data-kn="ಸೂಪರ್ ನಿರ್ವಾಹಕ"
                   data-te="సూపర్ నిర్వాహకుడు">Super Admin</a></li>
            <?php endif; ?>
        </ul>
    </div>
    
    <div class="container">
        <h2 style="margin-bottom: 20px;">
            <span data-en="Today's Summary" 
                  data-ml="ഇന്നത്തെ സംഗ്രഹം" 
                  data-ta="இன்றைய சுருக்கம்"
                  data-hi="आज का सारांश"
                  data-kn="ಇಂದಿನ ಸಾರಾಂಶ"
                  data-te="నేటి సారాంశం">Today's Summary</span> - <?php echo date('d M Y'); ?>
        </h2>
        
        <div class="stats-grid">
            <div class="stat-card">
                <h3 data-en="Total Bookings" 
                    data-ml="മൊത്തം ബുക്കിംഗുകൾ" 
                    data-ta="மொத்த முன்பதிவுகள்"
                    data-hi="कुल बुकिंग"
                    data-kn="ಒಟ್ಟು ಬುಕಿಂಗ್‌ಗಳು"
                    data-te="మొత్తం బుకింగ్‌లు">Total Bookings</h3>
                <div class="value"><?php echo $stats['total_bookings']; ?></div>
            </div>
            <div class="stat-card">
                <h3 data-en="Total Collection" 
                    data-ml="മൊത്തം വരവ്" 
                    data-ta="மொத்த வசூல்"
                    data-hi="कुल संग्रह"
                    data-kn="ಒಟ್ಟು ಸಂಗ್ರಹ"
                    data-te="మొత్తం సేకరణ">Total Collection</h3>
                <div class="value"><?php echo formatCurrency($stats['total_amount']); ?></div>
            </div>
            <div class="stat-card">
                <h3 data-en="Cash Collection" 
                    data-ml="പണം വരവ്" 
                    data-ta="பண வசூல்"
                    data-hi="नकद संग्रह"
                    data-kn="ನಗದು ಸಂಗ್ರಹ"
                    data-te="నగదు సేకరణ">Cash Collection</h3>
                <div class="value"><?php echo formatCurrency($stats['cash_amount']); ?></div>
            </div>
            <div class="stat-card">
                <h3 data-en="UPI Collection" 
                    data-ml="യു.പി.ഐ വരവ്" 
                    data-ta="யூபிஐ வசூல்"
                    data-hi="यूपीआई संग्रह"
                    data-kn="ಯುಪಿಐ ಸಂಗ್ರಹ"
                    data-te="యూపీఐ సేకరణ">UPI Collection</h3>
                <div class="value"><?php echo formatCurrency($stats['upi_amount']); ?></div>
            </div>
            <div class="stat-card">
                <h3 data-en="Today's Expenses" 
                    data-ml="ഇന്നത്തെ ചെലവുകൾ" 
                    data-ta="இன்றைய செலவுகள்"
                    data-hi="आज के खर्चे"
                    data-kn="ಇಂದಿನ ವೆಚ್ಚಗಳು"
                    data-te="నేటి ఖర్చులు">Today's Expenses</h3>
                <div class="value"><?php echo formatCurrency($todayExpense); ?></div>
            </div>
        </div>
        
        <div class="card">
            <h2 data-en="Recent Bookings" 
                data-ml="സമീപകാല ബുക്കിംഗുകൾ" 
                data-ta="சமீபத்திய முன்பதிவுகள்"
                data-hi="हाल की बुकिंग"
                data-kn="ಇತ್ತೀಚಿನ ಬುಕಿಂಗ್‌ಗಳು"
                data-te="ఇటీవలి బుకింగ్‌లు">Recent Bookings</h2>
            <table>
                <thead>
                    <tr>
                        <th data-en="Receipt No" 
                            data-ml="രസീത് നമ്പർ" 
                            data-ta="ரசீது எண்"
                            data-hi="रसीद नंबर"
                            data-kn="ರಸೀದಿ ಸಂಖ್ಯೆ"
                            data-te="రసీదు సంఖ్య">Receipt No</th>
                        <th data-en="Devotee Name" 
                            data-ml="ഭക്തന്റെ പേര്" 
                            data-ta="பக்தரின் பெயர்"
                            data-hi="भक्त का नाम"
                            data-kn="ಭಕ್ತರ ಹೆಸರು"
                            data-te="భక్తుడి పేరు">Devotee Name</th>
                        <th data-en="Vazhipadu" 
                            data-ml="വഴിപാട്" 
                            data-ta="வழிபாடு"
                            data-hi="वझिपाडु"
                            data-kn="ವಜಿಪಾಡು"
                            data-te="వజిపాడు">Vazhipadu</th>
                        <th data-en="Amount" 
                            data-ml="തുക" 
                            data-ta="தொகை"
                            data-hi="राशि"
                            data-kn="ಮೊತ್ತ"
                            data-te="మొత్తం">Amount</th>
                        <th data-en="Payment Mode" 
                            data-ml="പേയ്മെന്റ് രീതി" 
                            data-ta="கட்டண முறை"
                            data-hi="भुगतान मोड"
                            data-kn="ಪಾವತಿ ವಿಧಾನ"
                            data-te="చెల్లింపు పద్ధతి">Payment Mode</th>
                        <th data-en="Date & Time" 
                            data-ml="തീയതിയും സമയും" 
                            data-ta="தேதி மற்றும் நேரம்"
                            data-hi="तिथि और समय"
                            data-kn="ದಿನಾಂಕ ಮತ್ತು ಸಮಯ"
                            data-te="తేదీ మరియు సమయం">Date & Time</th>
                        <th data-en="Action" 
                            data-ml="പ്രവർത്തനം" 
                            data-ta="செயல்"
                            data-hi="कार्यवाही"
                            data-kn="ಕ್ರಿಯೆ"
                            data-te="చర్య">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($booking = $recentBookings->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo $booking['receipt_no']; ?></td>
                        <td><?php echo htmlspecialchars($booking['devotee_name']); ?></td>
                        <td><?php echo htmlspecialchars($booking['vazhipadu_name']); ?></td>
                        <td><?php echo formatCurrency($booking['amount']); ?></td>
                        <td><?php echo $booking['payment_mode']; ?></td>
                        <td><?php echo date('d/m/Y h:i A', strtotime($booking['created_at'])); ?></td>
                        <td>
                            <a href="print_receipt_multi.php?receipt_no=<?php echo $booking['receipt_no']; ?>" 
                               class="btn btn-primary" target="_blank" 
                               data-en="Print" 
                               data-ml="പ്രിന്റ്" 
                               data-ta="அச்சிடு"
                               data-hi="प्रिंट"
                               data-kn="ಮುದ್ರಿಸು"
                               data-te="ముద్రించు">Print</a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
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

            // Update body class for font
            document.body.className = '';
            if (lang !== 'en') {
                document.body.classList.add(lang);
            }
        }
    </script>
</body>
</html>
