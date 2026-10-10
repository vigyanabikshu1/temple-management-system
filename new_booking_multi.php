<?php
require_once 'config.php';
requireLogin();

// Set UTF-8 encoding
$conn->set_charset("utf8mb4");

$templeInfo = getCurrentTempleInfo();
$temple_id = getCurrentTempleId();

$message = '';
$messageType = '';

// Define common stars/nakshatras in multiple languages
$stars = [
    // Malayalam, Tamil, Telugu, Kannada, Hindi, English
    'അശ്വതി' => ['ml' => 'അശ്വതി', 'ta' => 'அசுவினி', 'te' => 'అశ్విని', 'kn' => 'ಅಶ್ವಿನಿ', 'hi' => 'अश्विनी', 'en' => 'Aswathi'],
    'ഭരണി' => ['ml' => 'ഭരണി', 'ta' => 'பரணி', 'te' => 'భరణి', 'kn' => 'భరణి', 'hi' => 'भरणी', 'en' => 'Bharani'],
    'കാർത്തിക' => ['ml' => 'കാർത്തിക', 'ta' => 'கார்த்திகை', 'te' => 'కృత్తిక', 'kn' => 'కృత్తిక', 'hi' => 'कृत्तिका', 'en' => 'Karthika'],
    'രോഹിണി' => ['ml' => 'രോഹിണി', 'ta' => 'ரோகிணி', 'te' => 'రోహిణి', 'kn' => 'రోహిణి', 'hi' => 'रोहिणी', 'en' => 'Rohini'],
    'മകയിരം' => ['ml' => 'മകയിരം', 'ta' => 'மிருகசீரிடம்', 'te' => 'మృగశిర', 'kn' => 'మృగశిర', 'hi' => 'मृगशीर्ष', 'en' => 'Makayiram'],
    'തിരുവാതിര' => ['ml' => 'തിരുവാതിര', 'ta' => 'திருவாதிரை', 'te' => 'ఆర్ద్ర', 'kn' => 'ఆర్ద్ర', 'hi' => 'आर्द्रा', 'en' => 'Thiruvathira'],
    'പുണർതം' => ['ml' => 'പുണർതം', 'ta' => 'புனர்பூசம்', 'te' => 'పునర్వసు', 'kn' => 'పునర్వసు', 'hi' => 'पुनर्वसु', 'en' => 'Punartham'],
    'പൂയം' => ['ml' => 'പൂയം', 'ta' => 'பூசம்', 'te' => 'పుష్య', 'kn' => 'పుష్య', 'hi' => 'पुष्य', 'en' => 'Pooyam'],
    'ആയില്യം' => ['ml' => 'ആയില്യം', 'ta' => 'ஆயில்யம்', 'te' => 'ఆశ్లేష', 'kn' => 'ఆశ్లేష', 'hi' => 'अश्लेषा', 'en' => 'Ayilyam'],
    'മകം' => ['ml' => 'മകം', 'ta' => 'மகம்', 'te' => 'మఘ', 'kn' => 'మఘ', 'hi' => 'मघा', 'en' => 'Makam'],
    'പൂരം' => ['ml' => 'പൂരം', 'ta' => 'பூரம்', 'te' => 'పూర్వ ఫల్గుణి', 'kn' => 'పూర్వ ఫల్గుణి', 'hi' => 'पूर्व फाल्गुनी', 'en' => 'Pooram'],
    'ഉത്രം' => ['ml' => 'ഉത്രം', 'ta' => 'உத்திரம்', 'te' => 'ఉత్తర ఫల్గుణి', 'kn' => 'ఉత్తర ఫల్గుణి', 'hi' => 'उत्तर फाल्गुनी', 'en' => 'Uthram'],
    'അത്തം' => ['ml' => 'അത്തം', 'ta' => 'அத்தம்', 'te' => 'హస్త', 'kn' => 'హస్త', 'hi' => 'हस्त', 'en' => 'Atham'],
    'ചിത്തിര' => ['ml' => 'ചിത്തിര', 'ta' => 'சித்திரை', 'te' => 'చిత్ర', 'kn' => 'చిత్ర', 'hi' => 'चित्रा', 'en' => 'Chithira'],
    'ചോതി' => ['ml' => 'ചോതി', 'ta' => 'சுவாதி', 'te' => 'స్వాతి', 'kn' => 'స్వాతి', 'hi' => 'स्वाति', 'en' => 'Chothi'],
    'വിശാഖം' => ['ml' => 'വിശാഖം', 'ta' => 'விசாகம்', 'te' => 'విశాఖ', 'kn' => 'విశాఖ', 'hi' => 'विशाखा', 'en' => 'Vishakham'],
    'അനിഴം' => ['ml' => 'അനിഴം', 'ta' => 'அனுஷம்', 'te' => 'అనూరాధ', 'kn' => 'అనూరాధ', 'hi' => 'अनुराधा', 'en' => 'Anizham'],
    'തൃക്കേട്ട' => ['ml' => 'തൃക്കേട്ട', 'ta' => 'கேட்டை', 'te' => 'జ్యేష్ఠ', 'kn' => 'జ్యేష్ఠ', 'hi' => 'ज्येष्ठा', 'en' => 'Thrikketta'],
    'മൂലം' => ['ml' => 'മൂലം', 'ta' => 'மூலம்', 'te' => 'మూల', 'kn' => 'మూల', 'hi' => 'मूल', 'en' => 'Moolam'],
    'പൂരാടം' => ['ml' => 'പൂരാടം', 'ta' => 'பூராடம்', 'te' => 'పూర్వాషాఢ', 'kn' => 'పూర్వాషాఢ', 'hi' => 'पूर्वाषाढ़ा', 'en' => 'Pooradam'],
    'ഉത്രാടം' => ['ml' => 'ഉത്രാടം', 'ta' => 'உத்திராடம்', 'te' => 'ఉత్తరాషాఢ', 'kn' => 'ఉత్తరాషాఢ', 'hi' => 'उत्तराषाढ़ा', 'en' => 'Uthradam'],
    'തിരുവോണം' => ['ml' => 'തിരുവോണം', 'ta' => 'திருவோணம்', 'te' => 'శ్రవణ', 'kn' => 'శ్రవణ', 'hi' => 'श्रवण', 'en' => 'Thiruvonam'],
    'അവിട്ടം' => ['ml' => 'അവിട്ടം', 'ta' => 'அவிட்டம்', 'te' => 'ధనిష్ఠ', 'kn' => 'ధనిష్ఠ', 'hi' => 'धनिष्ठा', 'en' => 'Avittam'],
    'ചതയം' => ['ml' => 'ചതയം', 'ta' => 'சதயம்', 'te' => 'శతభిష', 'kn' => 'శతభిష', 'hi' => 'शतभिषा', 'en' => 'Chathayam'],
    'പൂരുരുട്ടാതി' => ['ml' => 'പൂരുരുട്ടാതി', 'ta' => 'பூரட்டாதி', 'te' => 'పూర్వ భాద్రపద', 'kn' => 'పూర్వ భాద్రపద', 'hi' => 'पूर्व भाद्रपद', 'en' => 'Pooruruttathi'],
    'ഉത്രട്ടാതി' => ['ml' => 'ഉത്രട്ടാതി', 'ta' => 'உத்திரட்டாதி', 'te' => 'ఉత్తర భాద్రపద', 'kn' => 'ఉత్తర భాద్రపద', 'hi' => 'उत्तर भाद्रपद', 'en' => 'Uthrattathi'],
    'രേവതി' => ['ml' => 'രേവതി', 'ta' => 'ரேவதி', 'te' => 'రేవతి', 'kn' => 'రేవతి', 'hi' => 'रेवती', 'en' => 'Revathi']
];

// Get all vazhipadu types
$typesQuery = "SELECT * FROM vazhipadu_types WHERE temple_id = $temple_id AND is_active = 1 ORDER BY display_order, name";
$types = $conn->query($typesQuery);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['items'])) {
    $devotee_name = sanitizeInput($_POST['devotee_name']);
    $phone = sanitizeInput($_POST['phone']);
    $payment_mode = sanitizeInput($_POST['payment_mode']);
    $remarks = sanitizeInput($_POST['remarks']);
    $items = json_decode($_POST['items'], true);
    
    if (empty($items)) {
        $message = "Please add at least one vazhipadu item!";
        $messageType = 'error';
    } else {
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Check if devotee exists (only if phone is provided)
            if (!empty($phone)) {
                $devoteeQuery = "SELECT id FROM devotees WHERE temple_id = $temple_id AND phone = '$phone'";
                $devoteeResult = $conn->query($devoteeQuery);
                
                if ($devoteeResult->num_rows > 0) {
                    $devotee_id = $devoteeResult->fetch_assoc()['id'];
                    $updateQuery = "UPDATE devotees SET name = '$devotee_name' WHERE id = $devotee_id";
                    $conn->query($updateQuery);
                } else {
                    $insertDevotee = "INSERT INTO devotees (temple_id, name, phone) VALUES ($temple_id, '$devotee_name', '$phone')";
                    $conn->query($insertDevotee);
                    $devotee_id = $conn->insert_id;
                }
            } else {
                // Create devotee without phone
                $insertDevotee = "INSERT INTO devotees (temple_id, name, phone) VALUES ($temple_id, '$devotee_name', NULL)";
                $conn->query($insertDevotee);
                $devotee_id = $conn->insert_id;
            }
            
            // Generate main receipt number
            $receipt_no = generateReceiptNo();
            $booking_date = date('Y-m-d');
            $total_amount = 0;
            $booking_ids = [];
            $index=0;
            
            // Insert each vazhipadu item
            foreach ($items as $item) {
                $index+=1;
                $item_receipt_no = $receipt_no . "/" . $index;
                $vazhipadu_type_id = intval($item['type_id']);
                $quantity = intval($item['quantity']);
                $seva_date = !empty($item['seva_date']) ? sanitizeInput($item['seva_date']) : null;
                $vazhipadu_name = sanitizeInput($item['vazhipadu_name']); // Name for this vazhipadu
                $star_name = sanitizeInput($item['star_name']); // Star for this vazhipadu
                
                // Get vazhipadu amount
                $amountQuery = "SELECT amount FROM vazhipadu_types WHERE id = $vazhipadu_type_id";
                $amountResult = $conn->query($amountQuery);
                $unit_amount = $amountResult->fetch_assoc()['amount'];
                $item_total = $unit_amount * $quantity;
                $total_amount += $item_total;
                
                // Insert booking for each item with individual name and star
                $insertBooking = "INSERT INTO vazhipadu_bookings 
                    (temple_id, receipt_no, devotee_id, vazhipadu_type_id, devotee_name, star_name, 
                     booking_date, seva_date, amount, quantity, payment_mode, remarks, created_by) 
                    VALUES 
                    ($temple_id, '$item_receipt_no', $devotee_id, $vazhipadu_type_id, '$vazhipadu_name', '$star_name', 
                     '$booking_date', " . ($seva_date ? "'$seva_date'" : "NULL") . ", 
                     $item_total, $quantity, '$payment_mode', '$remarks', {$_SESSION['user_id']})";
                
                $conn->query($insertBooking);
                $booking_ids[] = $conn->insert_id;
            }
            
            // Commit transaction
            $conn->commit();
            
            logAudit('Multiple Vazhipadu Booking Created', 'vazhipadu_bookings', null, null, [
                'receipt_no' => $receipt_no,
                'item_count' => count($items),
                'total_amount' => $total_amount
            ]);
            
            $message = "Booking created successfully! Receipt No: $receipt_no <a href = 'print_receipt_multi.php?receipt_no=$receipt_no'>Print</a>";
            $messageType = 'success';
            
        } catch (Exception $e) {
            $conn->rollback();
            $message = "Error creating booking: " . $e->getMessage();
            $messageType = 'error';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Multiple Vazhipadu Booking - <?php echo htmlspecialchars($templeInfo['temple_name'], ENT_QUOTES, 'UTF-8'); ?></title>
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
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
        }
        .card {
            background: white;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .card h2 {
            margin-bottom: 25px;
            color: #333;
            border-bottom: 2px solid #ff6b35;
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
            border-color: #ff6b35;
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
            font-family: inherit;
        }
        .btn-primary {
            background: #ff6b35;
            color: white;
        }
        .btn-primary:hover {
            background: #e55a2b;
        }
        .btn-success {
            background: #27ae60;
            color: white;
        }
        .btn-secondary {
            background: #6c757d;
            color: white;
            margin-left: 10px;
        }
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        .btn-small {
            padding: 8px 16px;
            font-size: 14px;
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
        
        /* Item Selection */
        .item-selector {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .item-selector h3 {
            margin-bottom: 15px;
            color: #333;
        }
        .item-row {
            display: grid;
            grid-template-columns: 2fr 2fr 1fr 1fr 1fr auto;
            gap: 15px;
            align-items: end;
            margin-bottom: 15px;
        }
        
        /* Items Table */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .items-table th,
        .items-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        .items-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #333;
        }
        .items-table .text-right {
            text-align: right;
        }
        .items-table .total-row {
            font-weight: bold;
            background: #fff3e0;
            font-size: 18px;
        }
        
        /* Total Display */
        .total-display {
            background: linear-gradient(135deg, #fff5f0 0%, #ffe5d5 100%);
            padding: 25px;
            border-radius: 10px;
            text-align: center;
            margin: 20px 0;
            border: 2px solid #ff6b35;
        }
        .total-display .label {
            font-size: 16px;
            color: #666;
            margin-bottom: 10px;
        }
        .total-display .amount {
            font-size: 42px;
            font-weight: bold;
            color: #ff6b35;
        }
        .total-display .count {
            font-size: 14px;
            color: #888;
            margin-top: 10px;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #999;
        }
        .empty-state .icon {
            font-size: 64px;
            margin-bottom: 20px;
        }
        
        .info-box {
            background: #e3f2fd;
            border-left: 4px solid #2196f3;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        .info-box strong {
            color: #1976d2;
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
        select option {
            font-family: inherit;
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
        <h1><?php echo htmlspecialchars($templeInfo['temple_name'], ENT_QUOTES, 'UTF-8'); ?> - <span data-en="Multiple Vazhipadu Booking" 
                                                                                                 data-ml="ഒന്നിലധികം വഴിപാട് ബുക്കിംഗ്" 
                                                                                                 data-ta="பல வழிபாடு முன்பதிவு"
                                                                                                 data-hi="एकाधिक वझिपाडु बुकिंग"
                                                                                                 data-kn="ಬಹು ವಜಿಪಾಡು ಬುಕಿಂಗ್"
                                                                                                 data-te="బహుళ వజిపాడు బుకింగ్">Multiple Vazhipadu Booking</span></h1>
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
            <li><a href="new_booking_multi.php" class="active" 
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
            <li><a href="reports.php" 
                   data-en="Reports" 
                   data-ml="റിപ്പോർട്ടുകൾ" 
                   data-ta="அறிக்கைகள்"
                   data-hi="रिपोर्ट"
                   data-kn="ವರದಿಗಳು"
                   data-te="నివేదికలు">Reports</a></li>
        </ul>
    </div>
    
    <div class="container">
        <div class="card">
            <h2><span data-en="Multiple Vazhipadu Booking" 
                      data-ml="ഒന്നിലധികം വഴിപാട് ബുക്കിംഗ്" 
                      data-ta="பல வழிபாடு முன்பதிவு"
                      data-hi="एकाधिक वझिपाडु बुकिंग"
                      data-kn="ಬಹು ವಜಿಪಾಡು ಬುಕಿಂಗ್"
                      data-te="బహుళ వజిపాడు బుకింగ్">Multiple Vazhipadu Booking</span></h2>
            
            <?php if ($message): ?>
            <div class="message <?php echo $messageType; ?>">
                <?php echo $message; ?>
            </div>
            <?php endif; ?>
            
            <div class="info-box">
                <strong data-en="📋 Note:" 
                        data-ml="📋 കുറിപ്പ്:" 
                        data-ta="📋 குறிப்பு:"
                        data-hi="📋 नोट:"
                        data-kn="📋 ನೋಟ್:"
                        data-te="📋 గమనిక:">📋 Note:</strong> 
                <span data-en="The devotee name and phone number below will be used as the receipt holder. Each vazhipadu item can have a different person's name and nakshatra." 
                      data-ml="ചുവടെയുള്ള ഭക്തന്റെ പേരും ഫോൺ നമ്പറും രസീത് ഉടമയായി ഉപയോഗിക്കും. ഓരോ വഴിപാട് ഇനത്തിനും വ്യത്യസ്ത വ്യക്തിയുടെ പേരും നക്ഷത്രവും ഉണ്ടാകാം." 
                      data-ta="கீழே உள்ள பக்தரின் பெயர் மற்றும் தொலைபேசி எண் ரசீது வைத்திருப்பவராகப் பயன்படுத்தப்படும். ஒவ்வொரு வழிபாட்டுப் பொருளுக்கும் வெவ்வேறு நபரின் பெயர் மற்றும் நட்சத்திரம் இருக்கலாம்."
                      data-hi="नीचे दिए गए भक्त का नाम और फ़ोन नंबर रसीद धारक के रूप में उपयोग किया जाएगा। प्रत्येक वझिपाडु आइटम में अलग व्यक्ति का नाम और नक्षत्र हो सकता है।"
                      data-kn="ಕೆಳಗೆ ನೀಡಿರುವ ಭಕ್ತರ ಹೆಸರು ಮತ್ತು ಫೋನ್ ಸಂಖ್ಯೆಯನ್ನು ರಸೀದಿ ಹೊಂದಿರುವವರಾಗಿ ಬಳಸಲಾಗುತ್ತದೆ. ಪ್ರತಿಯೊಂದು ವಜಿಪಾಡು ಐಟಂ‌ನಲ್ಲಿ ವಿಭಿನ್ನ ವ್ಯಕ್ತಿಯ ಹೆಸರು ಮತ್ತು ನಕ್ಷತ್ರ ಇರಬಹುದು."
                      data-te="క్రింద ఉన్న భక్తుడి పేరు మరియు ఫోన్ నంబర్ రసీదు హోల్డర్‌గా ఉపయోగించబడుతుంది. ప్రతి వజిపాడు ఐటమ్‌లో వేరే వ్యక్తి పేరు మరియు నక్షత్రం ఉంటాయి.">
                    The devotee name and phone number below will be used as the receipt holder. Each vazhipadu item can have a different person's name and nakshatra.
                </span>
            </div>
            
            <form method="POST" id="bookingForm" accept-charset="UTF-8">
                <input type="hidden" name="items" id="items_json">
                
                <h3 style="margin-bottom: 20px; color: #ff6b35;">
                    <span data-en="Receipt Holder Information" 
                          data-ml="രസീത് ഉടമയുടെ വിവരങ്ങൾ" 
                          data-ta="ரசீது வைத்திருப்பவரின் தகவல்"
                          data-hi="रसीद धारक की जानकारी"
                          data-kn="ರಸೀದಿ ಹೊಂದಿರುವವರ ಮಾಹಿತಿ"
                          data-te="రసీదు హోల్డర్ సమాచారం">Receipt Holder Information</span>
                </h3>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="devotee_name">
                            <span data-en="Receipt Holder Name *" 
                                  data-ml="രസീത് ഉടമയുടെ പേര് *" 
                                  data-ta="ரசீது வைத்திருப்பவர் பெயர் *"
                                  data-hi="रसीद धारक का नाम *"
                                  data-kn="ರಸೀದಿ ಹೊಂದಿರುವವರ ಹೆಸರು *"
                                  data-te="రసీదు హోల్డర్ పేరు *">Receipt Holder Name *</span>
                        </label>
                        <input type="text" id="devotee_name" name="devotee_name" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="phone">
                            <span data-en="Phone Number" 
                                  data-ml="ഫോൺ നമ്പർ" 
                                  data-ta="தொலைபேசி எண்"
                                  data-hi="फ़ोन नंबर"
                                  data-kn="ಫೋನ್ ಸಂಖ್ಯೆ"
                                  data-te="ఫోన్ నంబర్">Phone Number</span>
                        </label>
                        <input type="tel" id="phone" name="phone" pattern="[0-9]{10}" 
                               placeholder="<?php 
                                   echo htmlspecialchars('Optional', ENT_QUOTES, 'UTF-8'); 
                               ?>">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="payment_mode">
                            <span data-en="Payment Mode *" 
                                  data-ml="പേയ്മെന്റ് മോഡ് *" 
                                  data-ta="கட்டண முறை *"
                                  data-hi="भुगतान मोड *"
                                  data-kn="ಪಾವತಿ ವಿಧಾನ *"
                                  data-te="చెల్లింపు పద్ధతి *">Payment Mode *</span>
                        </label>
                        <select id="payment_mode" name="payment_mode" required>
                            <option value="Cash" 
                                    data-en="Cash" 
                                    data-ml="പണം" 
                                    data-ta="பணம்"
                                    data-hi="नकद"
                                    data-kn="ರೋಕಡ"
                                    data-te="నగదు">Cash</option>
                            <option value="Card"
                                    data-en="Card" 
                                    data-ml="കാർഡ്" 
                                    data-ta="கார்டு"
                                    data-hi="कार्ड"
                                    data-kn="ಕಾರ್ಡ್"
                                    data-te="కార్డ్">Card</option>
                            <option value="UPI"
                                    data-en="UPI" 
                                    data-ml="യുപിഐ" 
                                    data-ta="யூபிஐ"
                                    data-hi="यूपीआई"
                                    data-kn="ಯುಪಿಐ"
                                    data-te="యుపిఐ">UPI</option>
                            <option value="Online"
                                    data-en="Online" 
                                    data-ml="ഓൺലൈൻ" 
                                    data-ta="ஆன்லைன்"
                                    data-hi="ऑनलाइन"
                                    data-kn="ಆನ್‌ಲೈನ್"
                                    data-te="ఆన్‌లైన్">Online</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="remarks">
                            <span data-en="Remarks" 
                                  data-ml="അഭിപ്രായങ്ങൾ" 
                                  data-ta="குறிப்புகள்"
                                  data-hi="टिप्पणियाँ"
                                  data-kn="ಟಿಪ್ಪಣಿಗಳು"
                                  data-te="వ్యాఖ్యలు">Remarks</span>
                        </label>
                        <textarea id="remarks" name="remarks" rows="1"></textarea>
                    </div>
                </div>
                
                <hr style="margin: 30px 0; border: none; border-top: 2px solid #eee;">
                
                <h3 style="margin-bottom: 20px; color: #ff6b35;">
                    <span data-en="Add Vazhipadu Items" 
                          data-ml="വഴിപാട് ഇനങ്ങൾ ചേർക്കുക" 
                          data-ta="வழிபாட்டுப் பொருட்களைச் சேர்க்கவும்"
                          data-hi="वझिपाडु आइटम जोड़ें"
                          data-kn="ವಜಿಪಾಡು ಐಟಂಗಳನ್ನು ಸೇರಿಸಿ"
                          data-te="వజిపాడు ఐటమ్‌లను జోడించండి">Add Vazhipadu Items</span>
                </h3>
                
                <div class="item-selector">
                    <div class="item-row">
                        <div class="form-group" style="margin: 0;">
                            <label>
                                <span data-en="Vazhipadu Type *" 
                                      data-ml="വഴിപാട് തരം *" 
                                      data-ta="வழிபாட்டு வகை *"
                                      data-hi="वझिपाडु प्रकार *"
                                      data-kn="ವಜಿಪಾಡು ಪ್ರಕಾರ *"
                                      data-te="వజిపాడు రకం *">Vazhipadu Type *</span>
                            </label>
                            <select id="vazhipadu_type">
                                <option value=""
                                        data-en="Select Vazhipadu" 
                                        data-ml="വഴിപാട് തിരഞ്ഞെടുക്കുക" 
                                        data-ta="வழிபாட்டைத் தேர்ந்தெடுக்கவும்"
                                        data-hi="वझिपाडु चुनें"
                                        data-kn="ವಜಿಪಾಡು ಆಯ್ಕೆಮಾಡಿ"
                                        data-te="వజిపాడు ఎంచుకోండి">Select Vazhipadu</option>
                                <?php while ($type = $types->fetch_assoc()): ?>
                                <option value="<?php echo $type['id']; ?>" 
                                        data-name="<?php echo htmlspecialchars($type['name'], ENT_QUOTES, 'UTF-8'); ?>"
                                        data-amount="<?php echo $type['amount']; ?>"
                                        data-en="<?php echo htmlspecialchars($type['name'], ENT_QUOTES, 'UTF-8'); ?> - ₹<?php echo $type['amount']; ?>">
                                    <?php echo htmlspecialchars($type['name'], ENT_QUOTES, 'UTF-8'); ?> - ₹<?php echo $type['amount']; ?>
                                </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        
                        <div class="form-group" style="margin: 0;">
                            <label>
                                <span data-en="Vazhipadu in the Name of *" 
                                      data-ml="വഴിപാട് ഏതുപേരിൽ *" 
                                      data-ta="எவரின் பெயரில் வழிபாடு *"
                                      data-hi="किसके नाम पर वझिपाडु *"
                                      data-kn="ಯಾರ ಹೆಸರಿನಲ್ಲಿ ವಜಿಪಾಡು *"
                                      data-te="ఎవరి పేరుతో వజిపాడు *">Vazhipadu in the Name of *</span>
                            </label>
                            <input type="text" id="vazhipadu_name" 
                                   placeholder="<?php 
                                       echo htmlspecialchars('Enter name', ENT_QUOTES, 'UTF-8'); 
                                   ?>">
                        </div>
                        
                        <div class="form-group" style="margin: 0;">
                            <label>
                                <span data-en="നക്ഷത്രം / Star" 
                                      data-ml="നക്ഷത്രം / സ്റ്റാർ" 
                                      data-ta="நட்சத்திரம் / ஸ்டார்"
                                      data-hi="नक्षत्र / स्टार"
                                      data-kn="ನಕ್ಷತ್ರ / ಸ್ಟಾರ್"
                                      data-te="నక్షత్రం / స్టార్">നക്ഷത്രം / Star</span>
                            </label>
                            <select id="star_name">
                                <option value=""
                                        data-en="Select Star" 
                                        data-ml="നക്ഷത്രം തിരഞ്ഞെടുക്കുക" 
                                        data-ta="நட்சத்திரத்தைத் தேர்ந்தெடுக்கவும்"
                                        data-hi="नक्षत्र चुनें"
                                        data-kn="ನಕ್ಷತ್ರ ಆಯ್ಕೆಮಾಡಿ"
                                        data-te="నక్షత్రం ఎంచుకోండి">Select Star</option>
                                <?php foreach ($stars as $english => $translations): ?>
                                    <option value="<?php echo htmlspecialchars($translations['ml'], ENT_QUOTES, 'UTF-8'); ?>" 
                                            class="star-option"
                                            data-en="<?php echo htmlspecialchars($english, ENT_QUOTES, 'UTF-8'); ?>"
                                            data-ml="<?php echo htmlspecialchars($translations['ml'], ENT_QUOTES, 'UTF-8'); ?>"
                                            data-ta="<?php echo htmlspecialchars($translations['ta'], ENT_QUOTES, 'UTF-8'); ?>"
                                            data-hi="<?php echo htmlspecialchars($translations['hi'], ENT_QUOTES, 'UTF-8'); ?>"
                                            data-kn="<?php echo htmlspecialchars($translations['kn'], ENT_QUOTES, 'UTF-8'); ?>"
                                            data-te="<?php echo htmlspecialchars($translations['te'], ENT_QUOTES, 'UTF-8'); ?>">
                                        <?php echo htmlspecialchars($translations['ml'], ENT_QUOTES, 'UTF-8'); ?> (<?php echo $english; ?>)
                                    </option>
                                <?php endforeach; ?>
                                <option value="Other"
                                        data-en="Other" 
                                        data-ml="മറ്റുള്ളവ" 
                                        data-ta="மற்றவை"
                                        data-hi="अन्य"
                                        data-kn="ಇತರೆ"
                                        data-te="ఇతర">Other</option>
                            </select>
                        </div>
                        
                        <div class="form-group" style="margin: 0;">
                            <label>
                                <span data-en="Quantity" 
                                      data-ml="എണ്ണം" 
                                      data-ta="அளவு"
                                      data-hi="मात्रा"
                                      data-kn="ಪ್ರಮಾಣ"
                                      data-te="పరిమాణం">Quantity</span>
                            </label>
                            <input type="number" id="item_quantity" value="1" min="1" max="100">
                        </div>
                        
                        <div class="form-group" style="margin: 0;">
                            <label>
                                <span data-en="Seva Date" 
                                      data-ml="സേവ തീയതി" 
                                      data-ta="சேவா தேதி"
                                      data-hi="सेवा तिथि"
                                      data-kn="ಸೇವಾ ದಿನಾಂಕ"
                                      data-te="సేవ తేదీ">Seva Date</span>
                            </label>
                            <input type="date" id="item_seva_date" min="<?php echo date('Y-m-d'); ?>">
                        </div>
                        
                        <div class="form-group" style="margin: 0;">
                            <label>&nbsp;</label>
                            <button type="button" class="btn btn-success" onclick="addItem()">
                                <span data-en="+ Add" 
                                      data-ml="+ ചേർക്കുക" 
                                      data-ta="+ சேர்க்கவும்"
                                      data-hi="+ जोड़ें"
                                      data-kn="+ ಸೇರಿಸಿ"
                                      data-te="+ జోడించండి">+ Add</span>
                            </button>
                        </div>
                    </div>
                </div>
                
                <div id="items_container">
                    <div id="empty_state" class="empty-state">
                        <div class="icon">🛒</div>
                        <p>
                            <span data-en="No items added yet. Add vazhipadu items above." 
                                  data-ml="ഇതുവരെ ഇനങ്ങളൊന്നും ചേർത്തിട്ടില്ല. മുകളിൽ വഴിപാട് ഇനങ്ങൾ ചേർക്കുക." 
                                  data-ta="இன்னும் உருப்படிகள் சேர்க்கப்படவில்லை. மேலே வழிபாட்டுப் பொருட்களைச் சேர்க்கவும்."
                                  data-hi="अभी तक कोई आइटम नहीं जोड़ा गया है। ऊपर वझिपाडु आइटम जोड़ें।"
                                  data-kn="ಇನ್ನೂ ಯಾವುದೇ ಐಟಂಗಳು ಸೇರಿಸಲಾಗಿಲ್ಲ. ಮೇಲೆ ವಜಿಪಾಡು ಐಟಂಗಳನ್ನು ಸೇರಿಸಿ."
                                  data-te="ఇంకా ఎటువంటి ఐటమ్‌లు జోడించబడలేదు. పైన వజిపాడు ఐటమ్‌లను జోడించండి.">
                                No items added yet. Add vazhipadu items above.
                            </span>
                        </p>
                    </div>
                    
                    <table class="items-table" id="items_table" style="display: none;">
                        <thead>
                            <tr>
                                <th><span data-en="Vazhipadu" 
                                          data-ml="വഴിപാട്" 
                                          data-ta="வழிபாடு"
                                          data-hi="वझिपाडु"
                                          data-kn="ವಜಿಪಾಡು"
                                          data-te="వజిపాడు">Vazhipadu</span></th>
                                <th><span data-en="In the Name of" 
                                          data-ml="ഏതുപേരിൽ" 
                                          data-ta="எவரின் பெயரில்"
                                          data-hi="किसके नाम पर"
                                          data-kn="ಯಾರ ಹೆಸರಿನಲ್ಲಿ"
                                          data-te="ఎవరి పేరుతో">In the Name of</span></th>
                                <th><span data-en="Nakshatra" 
                                          data-ml="നക്ഷത്രം" 
                                          data-ta="நட்சத்திரம்"
                                          data-hi="नक्षत्र"
                                          data-kn="ನಕ್ಷತ್ರ"
                                          data-te="నక్షత్రం">Nakshatra</span></th>
                                <th><span data-en="Seva Date" 
                                          data-ml="സേവ തീയതി" 
                                          data-ta="சேவா தேதி"
                                          data-hi="सेवा तिथि"
                                          data-kn="ಸೇವಾ ದಿನಾಂಕ"
                                          data-te="సేవ తేదీ">Seva Date</span></th>
                                <th class="text-right"><span data-en="Rate" 
                                                              data-ml="നിരക്ക്" 
                                                              data-ta="விகிதம்"
                                                              data-hi="दर"
                                                              data-kn="ದರ"
                                                              data-te="రేటు">Rate</span></th>
                                <th class="text-right"><span data-en="Qty" 
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
                                <th class="text-right"><span data-en="Action" 
                                                              data-ml="പ്രവർത്തനം" 
                                                              data-ta="செயல்"
                                                              data-hi="कार्रवाई"
                                                              data-kn="ಕ್ರಿಯೆ"
                                                              data-te="చర్య">Action</span></th>
                            </tr>
                        </thead>
                        <tbody id="items_body">
                        </tbody>
                        <tfoot>
                            <tr class="total-row">
                                <td colspan="6" class="text-right">
                                    <span data-en="TOTAL" 
                                          data-ml="മൊത്തം" 
                                          data-ta="மொத்தம்"
                                          data-hi="कुल"
                                          data-kn="ಒಟ್ಟು"
                                          data-te="మొత్తం">TOTAL</span>
                                </td>
                                <td class="text-right" id="grand_total">₹0.00</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                
                <div id="total_display" class="total-display" style="display: none;">
                    <div class="label">
                        <span data-en="Total Amount" 
                              data-ml="മൊത്തം തുക" 
                              data-ta="மொத்த தொகை"
                              data-hi="कुल राशि"
                              data-kn="ಒಟ್ಟು ಮೊತ್ತ"
                              data-te="మొత్తం మొత్తం">Total Amount</span>
                    </div>
                    <div class="amount" id="total_amount_display">₹0.00</div>
                    <div class="count" id="item_count_display">
                        <span data-en="0 items" 
                              data-ml="0 ഇനങ്ങൾ" 
                              data-ta="0 பொருட்கள்"
                              data-hi="0 आइटम"
                              data-kn="0 ಐಟಂಗಳು"
                              data-te="0 ఐటమ్‌లు">0 items</span>
                    </div>
                </div>
                
                <div style="text-align: center; margin-top: 30px;">
                    <button type="submit" class="btn btn-primary" id="submit_btn" disabled>
                        <span data-en="Create Booking" 
                              data-ml="ബുക്കിംഗ് സൃഷ്ടിക്കുക" 
                              data-ta="முன்பதிவை உருவாக்கவும்"
                              data-hi="बुकिंग बनाएं"
                              data-kn="ಬುಕಿಂಗ್ ರಚಿಸಿ"
                              data-te="బుకింగ్ సృష్టించండి">Create Booking</span>
                    </button>
                    <button type="reset" class="btn btn-secondary" onclick="clearItems()">
                        <span data-en="Clear All" 
                              data-ml="എല്ലാം മായ്‌ക്കുക" 
                              data-ta="அனைத்தையும் அழிக்கவும்"
                              data-hi="सभी साफ करें"
                              data-kn="ಎಲ್ಲಾ ತೆರವುಗೊಳಿಸಿ"
                              data-te="అన్నీ క్లియర్ చేయండి">Clear All</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        let items = [];
        let currentLang = localStorage.getItem('lang') || 'en';
        
        // Get current language on page load
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
            
            // Update star options based on language
            updateStarOptions(lang);
            
            // Update body class for font
            document.body.className = '';
            if (lang !== 'en') {
                document.body.classList.add(lang);
            }
            
            // Update items table if there are items
            if (items.length > 0) {
                updateDisplay();
            }
        }

        function updateSelectOptions(lang) {
            // Update vazhipadu_type select options
            const vazhipaduSelect = document.getElementById('vazhipadu_type');
            if (vazhipaduSelect) {
                Array.from(vazhipaduSelect.options).forEach(option => {
                    if (option.hasAttribute('data-' + lang)) {
                        option.textContent = option.getAttribute('data-' + lang);
                    }
                });
            }

            // Update payment_mode select options
            const paymentModeSelect = document.getElementById('payment_mode');
            if (paymentModeSelect) {
                Array.from(paymentModeSelect.options).forEach(option => {
                    if (option.hasAttribute('data-' + lang)) {
                        option.textContent = option.getAttribute('data-' + lang);
                    }
                });
            }
        }

        function updateStarOptions(lang) {
            const starSelect = document.getElementById('star_name');
            if (starSelect) {
                Array.from(starSelect.options).forEach(option => {
                    if (option.classList.contains('star-option') && option.hasAttribute('data-' + lang)) {
                        const starValue = option.getAttribute('data-' + lang);
                        const englishName = option.getAttribute('data-en');
                        option.textContent = `${starValue} (${englishName})`;
                    } else if (option.hasAttribute('data-' + lang)) {
                        option.textContent = option.getAttribute('data-' + lang);
                    }
                });
            }
        }

        function addItem() {
            const select = document.getElementById('vazhipadu_type');
            const vazzipaduName = document.getElementById('vazhipadu_name').value.trim();
            const starName = document.getElementById('star_name').value;
            const quantity = parseInt(document.getElementById('item_quantity').value);
            const sevaDate = document.getElementById('item_seva_date').value;
            
            if (!select.value) {
                alert(getTranslation('Please select a vazhipadu type'));
                return;
            }
            
            if (!vazzipaduName) {
                alert(getTranslation('Please enter the name for this vazhipadu'));
                document.getElementById('vazhipadu_name').focus();
                return;
            }
            
            if (quantity < 1) {
                alert(getTranslation('Quantity must be at least 1'));
                return;
            }
            
            const option = select.options[select.selectedIndex];
            const typeId = option.value;
            const typeName = option.getAttribute('data-name');
            const amount = parseFloat(option.getAttribute('data-amount'));
            const total = amount * quantity;
            
            // Get star name in current language
            let starDisplay = starName;
            if (starName !== 'Other' && starName !== '') {
                const starSelect = document.getElementById('star_name');
                const starOption = Array.from(starSelect.options).find(opt => opt.value === starName);
                if (starOption && starOption.hasAttribute('data-en')) {
                    const englishName = starOption.getAttribute('data-en');
                    const currentLangName = starOption.hasAttribute('data-' + currentLang) ? 
                        starOption.getAttribute('data-' + currentLang) : starName;
                    starDisplay = currentLangName;
                }
            }
            
            items.push({
                type_id: typeId,
                type_name: typeName,
                vazhipadu_name: vazzipaduName,
                star_name: starName,
                star_display: starDisplay,
                amount: amount,
                quantity: quantity,
                total: total,
                seva_date: sevaDate
            });
            
            // Reset form
            select.value = '';
            document.getElementById('vazhipadu_name').value = '';
            document.getElementById('star_name').value = '';
            document.getElementById('item_quantity').value = 1;
            document.getElementById('item_seva_date').value = '';
            
            updateDisplay();
        }
        
        function removeItem(index) {
            if (confirm(getTranslation('Are you sure you want to remove this item?'))) {
                items.splice(index, 1);
                updateDisplay();
            }
        }
        
        function updateDisplay() {
            const emptyState = document.getElementById('empty_state');
            const itemsTable = document.getElementById('items_table');
            const totalDisplay = document.getElementById('total_display');
            const submitBtn = document.getElementById('submit_btn');
            
            if (items.length === 0) {
                emptyState.style.display = 'block';
                itemsTable.style.display = 'none';
                totalDisplay.style.display = 'none';
                submitBtn.disabled = true;
                return;
            }
            
            emptyState.style.display = 'none';
            itemsTable.style.display = 'table';
            totalDisplay.style.display = 'block';
            submitBtn.disabled = false;
            
            // Update table
            const tbody = document.getElementById('items_body');
            tbody.innerHTML = '';
            
            let grandTotal = 0;
            
            items.forEach((item, index) => {
                grandTotal += item.total;
                
                const row = tbody.insertRow();
                row.innerHTML = `
                    <td><strong>${escapeHtml(item.type_name)}</strong></td>
                    <td>${escapeHtml(item.vazhipadu_name)}</td>
                    <td>${item.star_display ? escapeHtml(item.star_display) : '-'}</td>
                    <td>${item.seva_date ? new Date(item.seva_date).toLocaleDateString('en-GB') : '-'}</td>
                    <td class="text-right">₹${item.amount.toFixed(2)}</td>
                    <td class="text-right">${item.quantity}</td>
                    <td class="text-right"><strong>₹${item.total.toFixed(2)}</strong></td>
                    <td class="text-right">
                        <button type="button" class="btn btn-danger btn-small" onclick="removeItem(${index})">
                            ${getTranslation('Remove')}
                        </button>
                    </td>
                `;
            });
            
            // Update totals
            document.getElementById('grand_total').textContent = '₹' + grandTotal.toFixed(2);
            document.getElementById('total_amount_display').textContent = '₹' + grandTotal.toFixed(2);
            
            // Update item count with translation
            const itemCountElement = document.getElementById('item_count_display');
            const itemCountText = items.length === 1 ? 
                getTranslation('1 item') : 
                `${items.length} ${getTranslation('items')}`;
            itemCountElement.textContent = itemCountText;
            
            // Update hidden input
            document.getElementById('items_json').value = JSON.stringify(items);
        }
        
        function escapeHtml(text) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, m => map[m]);
        }
        
        function clearItems() {
            if (items.length > 0) {
                if (confirm(getTranslation('Are you sure you want to clear all items?'))) {
                    items = [];
                    updateDisplay();
                }
            }
        }
        
        function getTranslation(key) {
            const translations = {
                'Please select a vazhipadu type': {
                    en: 'Please select a vazhipadu type',
                    ml: 'ദയവായി ഒരു വഴിപാട് തരം തിരഞ്ഞെടുക്കുക',
                    ta: 'தயவு செய்து ஒரு வழிபாட்டு வகையைத் தேர்ந்தெடுக்கவும்',
                    hi: 'कृपया एक वझिपाडु प्रकार चुनें',
                    kn: 'ದಯವಿಟ್ಟು ಒಂದು ವಜಿಪಾಡು ಪ್ರಕಾರವನ್ನು ಆಯ್ಕೆಮಾಡಿ',
                    te: 'దయచేసి ఒక వజిపాడు రకాన్ని ఎంచుకోండి'
                },
                'Please enter the name for this vazhipadu': {
                    en: 'Please enter the name for this vazhipadu',
                    ml: 'ഈ വഴിപാടിനുള്ള പേര് നൽകുക',
                    ta: 'இந்த வழிபாட்டிற்கான பெயரை உள்ளிடவும்',
                    hi: 'कृपया इस वझिपाडु के लिए नाम दर्ज करें',
                    kn: 'ದಯವಿಟ್ಟು ಈ ವಜಿಪಾಡುಗಾಗಿ ಹೆಸರನ್ನು ನಮೂದಿಸಿ',
                    te: 'దయచేసి ఈ వజిపాడు కోసం పేరును నమోదు చేయండి'
                },
                'Quantity must be at least 1': {
                    en: 'Quantity must be at least 1',
                    ml: 'എണ്ണം കുറഞ്ഞത് 1 ആയിരിക്കണം',
                    ta: 'அளவு குறைந்தது 1 ஆக இருக்க வேண்டும்',
                    hi: 'मात्रा कम से कम 1 होनी चाहिए',
                    kn: 'ಪ್ರಮಾಣ ಕನಿಷ್ಠ 1 ಆಗಿರಬೇಕು',
                    te: 'పరిమాణం కనీసం 1గా ఉండాలి'
                },
                'Are you sure you want to remove this item?': {
                    en: 'Are you sure you want to remove this item?',
                    ml: 'ഈ ഇനം നീക്കം ചെയ്യാൻ ആഗ്രഹിക്കുന്നുണ്ടോ?',
                    ta: 'இந்த உருப்படியை நீக்க விரும்புகிறீர்களா?',
                    hi: 'क्या आप वाकई इस आइटम को हटाना चाहते हैं?',
                    kn: 'ನೀವು ಈ ಐಟಂ ಅನ್ನು ತೆಗೆದುಹಾಕಲು ಬಯಸುತ್ತೀರಾ?',
                    te: 'మీరు ఈ ఐటమ్‌ను తీసివేయాలనుకుంటున్నారా?'
                },
                'Are you sure you want to clear all items?': {
                    en: 'Are you sure you want to clear all items?',
                    ml: 'എല്ലാ ഇനങ്ങളും മായ്‌ക്കാൻ ആഗ്രഹിക്കുന്നുണ്ടോ?',
                    ta: 'அனைத்து உருப்படிகளையும் அழிக்க விரும்புகிறீர்களா?',
                    hi: 'क्या आप वाकई सभी आइटम साफ करना चाहते हैं?',
                    kn: 'ನೀವು ಎಲ್ಲಾ ಐಟಂಗಳನ್ನು ತೆರವುಗೊಳಿಸಲು ಬಯಸುತ್ತೀರಾ?',
                    te: 'మీరు అన్ని ఐటమ్‌లను క్లియర్ చేయాలనుకుంటున్నారా?'
                },
                'Remove': {
                    en: 'Remove',
                    ml: 'നീക്കം ചെയ്യുക',
                    ta: 'அகற்று',
                    hi: 'हटाएं',
                    kn: 'ತೆಗೆದುಹಾಕಿ',
                    te: 'తీసివేయండి'
                },
                '1 item': {
                    en: '1 item',
                    ml: '1 ഇനം',
                    ta: '1 பொருள்',
                    hi: '1 आइटम',
                    kn: '1 ಐಟಂ',
                    te: '1 ఐటమ్'
                },
                'items': {
                    en: 'items',
                    ml: 'ഇനങ്ങൾ',
                    ta: 'பொருட்கள்',
                    hi: 'आइटम',
                    kn: 'ಐಟಂಗಳು',
                    te: 'ఐటమ్‌లు'
                },
                'Phone number must be exactly 10 digits or leave it empty': {
                    en: 'Phone number must be exactly 10 digits or leave it empty',
                    ml: 'ഫോൺ നമ്പർ കൃത്യമായി 10 അക്കങ്ങളായിരിക്കണം അല്ലെങ്കിൽ ശൂന്യമായി വിടുക',
                    ta: 'தொலைபேசி எண் சரியாக 10 இலக்கங்களாக இருக்க வேண்டும் அல்லது காலியாக விடவும்',
                    hi: 'फ़ोन नंबर ठीक 10 अंकों का होना चाहिए या खाली छोड़ दें',
                    kn: 'ಫೋನ್ ಸಂಖ್ಯೆಯು ನಿಖರವಾಗಿ 10 ಅಂಕೆಗಳಾಗಿರಬೇಕು ಅಥವಾ ಖಾಲಿ ಬಿಡಿ',
                    te: 'ఫోన్ నంబర్ ఖచ్చితంగా 10 అంకెలుగా ఉండాలి లేదా ఖాళీగా ఉంచండి'
                },
                'Please add at least one vazhipadu item': {
                    en: 'Please add at least one vazhipadu item',
                    ml: 'ദയവായി കുറഞ്ഞത് ഒരു വഴിപാട് ഇനം ചേർക്കുക',
                    ta: 'தயவு செய்து குறைந்தது ஒரு வழிபாட்டுப் பொருளைச் சேர்க்கவும்',
                    hi: 'कृपया कम से कम एक वझिपाडु आइटम जोड़ें',
                    kn: 'ದಯವಿಟ್ಟು ಕನಿಷ್ಠ ಒಂದು ವಜಿಪಾಡು ಐಟಂ ಸೇರಿಸಿ',
                    te: 'దయచేసి కనీసం ఒక వజిపాడు ఐటమ్‌ను జోడించండి'
                }
            };
            
            return translations[key] ? translations[key][currentLang] || translations[key]['en'] : key;
        }
        
        // Auto-format phone number (only validate if not empty)
        document.getElementById('phone').addEventListener('input', function() {
            this.value = this.value.replace(/[^0-9]/g, '').substring(0, 10);
        });
        
        // Add custom validation for phone pattern only when filled
        document.getElementById('bookingForm').addEventListener('submit', function(e) {
            const phoneInput = document.getElementById('phone');
            const phoneValue = phoneInput.value.trim();
            
            // If phone is provided, it must be 10 digits
            if (phoneValue !== '' && phoneValue.length !== 10) {
                e.preventDefault();
                alert(getTranslation('Phone number must be exactly 10 digits or leave it empty'));
                phoneInput.focus();
                return false;
            }
            
            if (items.length === 0) {
                e.preventDefault();
                alert(getTranslation('Please add at least one vazhipadu item'));
                return false;
            }
        });
    </script>
</body>
</html>
