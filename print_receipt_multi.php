<?php
require_once 'config.php';
requireLogin();

$templeInfo = getCurrentTempleInfo();
$temple_id = getCurrentTempleId();
$receipt_no = isset($_GET['receipt_no']) ? sanitizeInput($_GET['receipt_no']) : '';
$receipt_no = strtok($receipt_no, '/');

// Get all bookings with this receipt number
$query = "SELECT vb.*, vt.name as vazhipadu_name, vt.description 
          FROM vazhipadu_bookings vb 
          JOIN vazhipadu_types vt ON vb.vazhipadu_type_id = vt.id 
          WHERE vb.receipt_no LIKE '$receipt_no%' AND vb.temple_id = $temple_id
          ORDER BY vb.id";
$result = $conn->query($query);

if ($result->num_rows === 0) {
    die("Receipt not found");
}

// Get bookings and calculate total
$bookings = [];
$total_amount = 0;
$first_booking = null;
$receipt_holder_name = '';
$receipt_holder_phone = '';

while ($row = $result->fetch_assoc()) {
    $bookings[] = $row;
    $total_amount += $row['amount'];
    if (!$first_booking) {
        $first_booking = $row;
    }
}

// Get receipt holder details from devotees table
if ($first_booking) {
    $devotee_query = "SELECT name, phone FROM devotees WHERE id = {$first_booking['devotee_id']}";
    $devotee_result = $conn->query($devotee_query);
    if ($devotee_result && $devotee_result->num_rows > 0) {
        $devotee_data = $devotee_result->fetch_assoc();
        $receipt_holder_name = $devotee_data['name'];
        $receipt_holder_phone = $devotee_data['phone'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print Receipt - <?php echo $receipt_no; ?></title>
    <style>
        @media print {
            .no-print { 
                display: none !important; 
            }
            body { 
                margin: 0 !important; 
                padding: 0 !important; 
                background: none !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }

        @page {
            size: 150mm 65mm; 
            margin: 0;
        }

        body {
            font-family: 'Courier New', monospace;
            width: 150mm;
            margin: 0 auto;
            padding: 0;
            font-size: 16px;
            line-height: 1.2;
            background: white;
            font-weight: bold;
        }

        .receipt-container {
            width: 148mm;
            height: 65mm;
            padding: 2mm;
            box-sizing: border-box;
            position: relative;
            overflow: hidden;
            border-bottom: 2px dashed #000;
        }

        /* FIXED HEADER STYLE - More compact */
        .header { 
            text-align: center; 
            margin-bottom: 1mm;
            padding-bottom: 0.5mm;
            border-bottom: 1px solid #000;
            max-height: 12mm;
            overflow: hidden;
        }
        
        .temple-name {
            font-size: 14px !important; /* Slightly smaller to fit */
            margin: 0;
            padding: 0;
            text-transform: uppercase;
            font-weight: bold;
            letter-spacing: 0.5px;
            line-height: 1.1;
        }
        
        .address-line {
            font-size: 9px !important; /* Smaller for address */
            margin: 0.2mm 0;
            padding: 0;
            line-height: 1;
        }
        
        .receipt-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 0.5mm 0;
            font-size: 11px;
            font-weight: bold;
        }

        .receipt-no {
            font-size: 12px;
            font-weight: bold;
        }
        
        .receipt-date {
            font-size: 11px;
            font-weight: bold;
        }

        .devotee-section {
            margin: 0.5mm 0;
            padding: 0.5mm 0;
        }
        
        .devotee-name {
            font-size: 11px;
            font-weight: bold;
            margin: 0;
        }
        
        .devotee-phone {
            font-size: 10px;
            margin: 0.2mm 0 0 0;
        }

        .divider { 
            border-top: 1px solid #000; 
            margin: 0.5mm 0; 
            height: 0;
        }

        .items-section {
            height: 25mm;
            overflow-y: auto;
            margin-bottom: 0.5mm;
        }

        .item-header {
            font-size: 10px;
            font-weight: bold;
            text-align: center;
            margin: 0.5mm 0;
            text-decoration: underline;
        }

        .item-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 0.5mm;
            padding-bottom: 0.2mm;
            border-bottom: 1px dotted #666;
        }
        
        .item-name-col {
            flex: 3;
            font-size: 10px;
            font-weight: bold;
        }
        
        .item-details-col {
            flex: 3;
            font-size: 9px;
        }
        
        .item-amount-col {
            flex: 1;
            text-align: right;
            font-size: 10px;
            font-weight: bold;
        }

        .total-section {
            position: absolute;
            right: 2mm;
            left: 2mm; /* Stretching across to help alignment */
            background: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .total-label {
            font-size: 11px;
            font-weight: bold;
            margin: 0;
            flex: 1;
        }

        .total-amount {
            font-size: 18px !important; /* Slightly larger since it's now horizontal */
            font-weight: bold;
            margin: 0;
            color: #000;
            text-align: right;
        }
        
        .payment-meta {
            font-size: 8px;
            margin-left: 5px;
            font-weight: normal;
        }
        .btn-print {
            padding: 20px 40px;
            background: #28a745;
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: bold;
            cursor: pointer;
            margin: 30px;
            font-size: 18px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }

        .btn-print:hover {
            background: #218838;
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(0,0,0,0.3);
        }

        /* Print info */
        .print-info {
            text-align: center;
            font-size: 14px;
            color: #666;
            margin-top: 10px;
        }

        /* Hide scrollbar in print */
        @media print {
            .items-section {
                overflow-y: visible !important;
            }
        }

        /* Additional temple info if needed */
        .temple-contact {
            font-size: 8px;
            margin-top: 0.2mm;
        }
    </style>
</head>
<body>

    <div class="no-print" style="text-align: center;">
        <button class="btn-print" onclick="window.print()">🖨️ PRINT RECEIPT</button>
        <div class="print-info">
            Paper: 15cm × 6.5cm | Single receipt per print
        </div>
    </div>

    <div class="receipt-container">
        <!-- FIXED HEADER - More compact -->
        <div class="header">
            <div class="temple-name"><?php echo htmlspecialchars($templeInfo['temple_name']); ?></div>
            <div class="address-line"><?php echo htmlspecialchars($templeInfo['address']); ?></div>
            <div class="address-line">
                <?php echo htmlspecialchars($templeInfo['city'] . ', ' . $templeInfo['state'] . ' - ' . $templeInfo['pincode']); ?>
            </div>
            <?php if ($templeInfo['phone']): ?>
            <div class="temple-contact">
                Ph: <?php echo htmlspecialchars($templeInfo['phone']); ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Receipt Info -->
        <div class="receipt-info">
            <span class="receipt-no">Rcpt: <?php echo $receipt_no; ?></span>
            <span class="receipt-date">Date: <?php echo date('d/m/Y H:i', strtotime($first_booking['created_at'])); ?></span>
        </div>

        <!-- Devotee Info -->
        <div class="devotee-section">
            <div class="devotee-name">
                Devotee: <?php echo strtoupper(htmlspecialchars($receipt_holder_name)); ?>
                <?php if ($receipt_holder_phone): ?>
                Phone: <?php echo htmlspecialchars($receipt_holder_phone); ?>
            <?php endif; ?>
            </div>
        </div>

        <div class="divider"></div>

        <!-- Vazhipadu Items -->
        <div class="items-section">
            <div class="item-header">VAZHIPADU DETAILS</div>
            
            <?php 
            $item_count = 0;
            foreach ($bookings as $booking): 
                if ($item_count >= 4) break; // Limit to 4 items for space
            ?>
            <div class="item-row">
                <div class="item-name-col">
                    <?php echo htmlspecialchars(substr($booking['vazhipadu_name'], 0, 20)); ?>
                    <?php if (strlen($booking['vazhipadu_name']) > 20): ?>...<?php endif; ?>
                    <div style="font-size: 8px;">Qty: <?php echo $booking['quantity']; ?></div>
                </div>
                
                <div class="item-details-col">
                    <div style="font-weight: bold; font-size: 8px;">For: <?php echo htmlspecialchars(substr($booking['devotee_name'], 0, 15)); ?></div>
                    <?php if ($booking['star_name']): ?>
                    <div style="font-size: 8px;">Star: <?php echo htmlspecialchars($booking['star_name']); ?></div>
                    <?php endif; ?>
                    <?php if ($booking['seva_date']): ?>
                    <div style="font-size: 8px;">Date: <?php echo date('d/m/Y', strtotime($booking['seva_date'])); ?></div>
                    <?php endif; ?>
                </div>
                
                <div class="item-amount-col">
                    <?php echo formatCurrency($booking['amount']); ?>
                </div>
            </div>
            <?php 
                $item_count++;
            endforeach; 
            
            // Show item count if more than 4
            if (count($bookings) > 4): ?>
            <div style="text-align: center; font-size: 9px; font-weight: bold; color: #666; margin-top: 0.5mm;">
                ... and <?php echo (count($bookings) - 4); ?> more items
            </div>
            <?php endif; ?>
        </div>

        <!-- Total Amount -->
        <div class="total-section">
            <div class="total-label">
                TOTAL PAID 
                <span class="payment-meta">
                    (<?php echo $first_booking['payment_mode']; ?> | Items: <?php echo count($bookings); ?>)
                </span>
            </div>
            <div class="total-amount">
                <?php echo formatCurrency($total_amount); ?>
            </div>
        </div>
    </div>

    <script>
        // Debug: Check if temple name is loading
        document.addEventListener('DOMContentLoaded', function() {
            console.log("Temple Name:", "<?php echo htmlspecialchars($templeInfo['temple_name']); ?>");
            
            // Auto-print if requested
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('autoprint') === '1') {
                setTimeout(function() {
                    window.print();
                }, 500);
            }
        });
    </script>

</body>
</html>
