<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once 'config.php';
requireLogin();

$results = null;
$searchTerm = '';

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['search'])) {
    $searchTerm = sanitizeInput($_GET['search']);
    
    $query = "SELECT vb.*, vt.name as vazhipadu_name 
              FROM vazhipadu_bookings vb 
              JOIN vazhipadu_types vt ON vb.vazhipadu_type_id = vt.id 
              WHERE vb.receipt_no LIKE '%$searchTerm%' 
                 OR vb.devotee_name LIKE '%$searchTerm%'
              ORDER BY vb.created_at DESC 
              LIMIT 50";
    
    $results = $conn->query($query);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search - <?php echo $_SESSION['temple_name']; ?></title>
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
            max-width: 1000px;
            margin: 30px auto;
            padding: 0 20px;
        }
        .search-box {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 25px;
        }
        .search-form {
            display: flex;
            gap: 10px;
        }
        .search-input {
            flex: 1;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
        }
        .search-input:focus {
            outline: none;
            border-color: #ff6b35;
        }
        .btn {
            padding: 12px 30px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
        }
        .btn-primary {
            background: #ff6b35;
            color: white;
        }
        .btn-primary:hover {
            background: #e55a2b;
        }
        .btn-small {
            padding: 8px 15px;
            font-size: 13px;
        }
        .results {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
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
        .no-results {
            text-align: center;
            padding: 40px;
            color: #666;
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
        <h1><?php echo $_SESSION['temple_name']; ?> - <span data-en="Search" 
                                                              data-ml="തിരയുക" 
                                                              data-ta="தேடல்"
                                                              data-hi="खोजें"
                                                              data-kn="ಹುಡುಕಿ"
                                                              data-te="శోధించు">Search</span></h1>
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
            <li><a href="search.php" class="active" 
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
        <div class="search-box">
            <h2 style="margin-bottom: 20px;" 
                data-en="Search Bookings" 
                data-ml="ബുക്കിംഗുകൾ തിരയുക" 
                data-ta="முன்பதிவுகளைத் தேடுங்கள்"
                data-hi="बुकिंग खोजें"
                data-kn="ಬುಕಿಂಗ್‌ಗಳನ್ನು ಹುಡುಕಿ"
                data-te="బుకింగ్‌లను శోధించండి">Search Bookings</h2>
            <form method="GET" class="search-form">
                <input type="text" 
                       name="search" 
                       class="search-input" 
                       placeholder="Search by Receipt No or Devotee Name..." 
                       value="<?php echo htmlspecialchars($searchTerm); ?>"
                       id="searchInput"
                       required>
                <button type="submit" class="btn btn-primary" 
                        data-en="Search" 
                        data-ml="തിരയുക" 
                        data-ta="தேடல்"
                        data-hi="खोजें"
                        data-kn="ಹುಡುಕಿ"
                        data-te="శోధించు">Search</button>
            </form>
        </div>
        
        <?php if ($results !== null): ?>
        <div class="results">
            <?php if ($results->num_rows > 0): ?>
                <h3 style="margin-bottom: 20px;">
                    <span data-en="Found" 
                          data-ml="കണ്ടെത്തി" 
                          data-ta="கண்டுபிடிக்கப்பட்டது"
                          data-hi="मिला"
                          data-kn="ಕಂಡುಬಂದಿದೆ"
                          data-te="దొరికింది">Found</span> 
                    <?php echo $results->num_rows; ?> 
                    <span data-en="result(s)" 
                          data-ml="ഫലം (കൾ)" 
                          data-ta="முடிவு(கள்)"
                          data-hi="परिणाम"
                          data-kn="ಫಲಿತಾಂಶ(ಗಳು)"
                          data-te="ఫలితం(లు)">result(s)</span>
                </h3>
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
                            <th data-en="Date" 
                                data-ml="തീയതി" 
                                data-ta="தேதி"
                                data-hi="तिथि"
                                data-kn="ದಿನಾಂಕ"
                                data-te="తేదీ">Date</th>
                            <th data-en="Action" 
                                data-ml="പ്രവർത്തനം" 
                                data-ta="செயல்"
                                data-hi="कार्यवाही"
                                data-kn="ಕ್ರಿಯೆ"
                                data-te="చర్య">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $results->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $row['receipt_no']; ?></td>
                            <td><?php echo htmlspecialchars($row['devotee_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['vazhipadu_name']); ?></td>
                            <td><?php echo formatCurrency($row['amount']); ?></td>
                            <td><?php echo date('d/m/Y', strtotime($row['booking_date'])); ?></td>
                            <td>
                                <a href="print_receipt.php?id=<?php echo $row['id']; ?>" 
                                   class="btn btn-primary btn-small" 
                                   target="_blank"
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
            <?php else: ?>
                <div class="no-results">
                    <p><span data-en="No bookings found matching" 
                             data-ml="പൊരുത്തപ്പെടുന്ന ബുക്കിംഗുകൾ കണ്ടെത്തിയില്ല" 
                             data-ta="பொருந்தும் முன்பதிவுகள் எதுவும் கிடைக்கவில்லை"
                             data-hi="कोई बुकिंग नहीं मिली"
                             data-kn="ಯಾವುದೇ ಬುಕಿಂಗ್‌ಗಳು ಕಂಡುಬಂದಿಲ್ಲ"
                             data-te="ఏ బుకింగ్‌లు దొరకలేదు">No bookings found matching</span> 
                       "<?php echo htmlspecialchars($searchTerm); ?>"</p>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
    
    <script>
        // Placeholder translations
        const placeholders = {
            en: "Search by Receipt No or Devotee Name...",
            ml: "രസീത് നമ്പർ അല്ലെങ്കിൽ ഭക്തന്റെ പേര് ഉപയോഗിച്ച് തിരയുക...",
            ta: "ரசீது எண் அல்லது பக்தரின் பெயரால் தேடுங்கள்...",
            hi: "रसीद नंबर या भक्त के नाम से खोजें...",
            kn: "ರಸೀದಿ ಸಂಖ್ಯೆ ಅಥವಾ ಭಕ್ತರ ಹೆಸರಿನಿಂದ ಹುಡುಕಿ...",
            te: "రసీదు సంఖ్య లేదా భక్తుడి పేరు ద్వారా శోధించండి..."
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

            // Update placeholder
            const searchInput = document.getElementById('searchInput');
            if (searchInput && placeholders[lang]) {
                searchInput.placeholder = placeholders[lang];
            }

            // Update body class for font
            document.body.className = '';
            if (lang !== 'en') {
                document.body.classList.add(lang);
            }
        }
    </script>
</body>
</html>
