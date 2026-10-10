<?php
require_once 'config.php';
requireLogin();

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id <= 0) {
    die("Invalid booking ID");
}

$query = "SELECT vb.*, vt.name as vazhipadu_name, vt.description 
          FROM vazhipadu_bookings vb 
          JOIN vazhipadu_types vt ON vb.vazhipadu_type_id = vt.id 
          WHERE vb.id = ?";
          
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Booking not found");
}

$booking = $result->fetch_assoc();
$stmt->close();

// Set fallback values for temple info
$temple_name = defined('TEMPLE_NAME') && TEMPLE_NAME ? TEMPLE_NAME : 'SREE KRISHNA TEMPLE';
$temple_address = defined('TEMPLE_ADDRESS') && TEMPLE_ADDRESS ? TEMPLE_ADDRESS : 'Temple Street, City, State';
$temple_phone = defined('TEMPLE_PHONE') && TEMPLE_PHONE ? TEMPLE_PHONE : '+91 XXXXX XXXXX';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt - <?php echo htmlspecialchars($booking['receipt_no']); ?></title>
    <style>
        @media print {
            .no-print {
                display: none;
            }
            body {
                margin: 0;
            }
        }
        body {
            font-family: 'Courier New', monospace;
            max-width: 80mm;
            margin: 0 auto;
            padding: 10px;
        }
        .receipt {
            border: 2px solid #000;
            padding: 15px;
        }
        .header {
            text-align: center;
            border-bottom: 2px dashed #000;
            padding-bottom: 10px;
            margin-bottom: 15px;
        }
        .header h1 {
            font-size: 20px;
            margin: 5px 0;
        }
        .header p {
            margin: 3px 0;
            font-size: 11px;
        }
        .receipt-no {
            text-align: center;
            font-weight: bold;
            font-size: 16px;
            margin: 10px 0;
        }
        .row {
            display: flex;
            justify-content: space-between;
            margin: 8px 0;
            font-size: 12px;
        }
        .row .label {
            font-weight: bold;
        }
        .divider {
            border-top: 1px dashed #000;
            margin: 10px 0;
        }
        .amount-section {
            background: #f0f0f0;
            padding: 10px;
            margin: 10px 0;
            text-align: center;
        }
        .amount {
            font-size: 24px;
            font-weight: bold;
        }
        .footer {
            text-align: center;
            margin-top: 15px;
            font-size: 11px;
            border-top: 2px dashed #000;
            padding-top: 10px;
        }
        .btn-print {
            background: #ff6b35;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            margin: 20px auto;
            display: block;
        }
    </style>
</head>
<body>
<div id="google_translate_element"></div>
    <button class="btn-print no-print" onclick="window.print()">Print Receipt</button>
    
    <div class="receipt">
        <div class="header">
            <h1><?php echo htmlspecialchars($temple_name); ?></h1>
            <p><?php echo htmlspecialchars($temple_address); ?></p>
            <p>Phone: <?php echo htmlspecialchars($temple_phone); ?></p>
        </div>
        
        <div class="receipt-no">
            Receipt No: <?php echo htmlspecialchars($booking['receipt_no']); ?>
        </div>
        
        <div class="row">
            <span class="label">Date:</span>
            <span><?php echo date('d/m/Y h:i A', strtotime($booking['created_at'])); ?></span>
        </div>
        
        <div class="divider"></div>
        
        <div class="row">
            <span class="label">Devotee Name:</span>
            <span><?php echo htmlspecialchars(strtoupper($booking['devotee_name'])); ?></span>
        </div>
        
        <?php if (!empty($booking['star_name'])): ?>
        <div class="row">
            <span class="label">Star:</span>
            <span><?php echo htmlspecialchars($booking['star_name']); ?></span>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($booking['seva_date'])): ?>
        <div class="row">
            <span class="label">Seva Date:</span>
            <span><?php echo date('d/m/Y', strtotime($booking['seva_date'])); ?></span>
        </div>
        <?php endif; ?>
        
        <div class="divider"></div>
        
        <div class="row">
            <span class="label">Vazhipadu:</span>
            <span><?php echo htmlspecialchars($booking['vazhipadu_name']); ?></span>
        </div>
        
        <?php if (!empty($booking['description'])): ?>
        <div class="row">
            <span style="font-size: 10px; font-style: italic;">
                <?php echo htmlspecialchars($booking['description']); ?>
            </span>
        </div>
        <?php endif; ?>
        
        <div class="amount-section">
            <div>Amount Paid</div>
            <div class="amount"><?php echo formatCurrency($booking['amount']); ?></div>
            <div style="font-size: 11px; margin-top: 5px;">
                Payment Mode: <?php echo htmlspecialchars($booking['payment_mode']); ?>
            </div>
        </div>
        
        <?php if (!empty($booking['remarks'])): ?>
        <div class="row">
            <span class="label">Remarks:</span>
        </div>
        <div style="font-size: 11px; margin-top: 5px;">
            <?php echo htmlspecialchars($booking['remarks']); ?>
        </div>
        <?php endif; ?>
        
        <div class="footer">
            <p>Thank you for your offering!</p>
            <p>May God bless you!</p>
            <p style="margin-top: 10px; font-size: 9px;">
                This is a computer generated receipt
            </p>
        </div>
    </div>
    
    <button class="btn-print no-print" onclick="window.print()" style="margin-top: 10px;">
        Print Receipt
    </button>
    
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
