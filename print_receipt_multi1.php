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

while ($row = $result->fetch_assoc()) {
    $bookings[] = $row;
    $total_amount += $row['amount'];
    if (!$first_booking) {
        $first_booking = $row;
    }
}

// Get receipt holder details
$receipt_holder_name = '';
$receipt_holder_phone = '';
if ($first_booking) {
    $devotee_query = "SELECT name, phone FROM devotees WHERE id = {$first_booking['devotee_id']}";
    $devotee_result = $conn->query($devotee_query);
    if ($devotee_result && $devotee_result->num_rows > 0) {
        $devotee_data = $devotee_result->fetch_assoc();
        $receipt_holder_name = $devotee_data['name'];
        $receipt_holder_phone = $devotee_data['phone'];
    }
}

// Check if we should generate text output
$format = isset($_GET['format']) ? $_GET['format'] : 'html';

if ($format === 'text') {
    // Generate plain text receipt for dot matrix printer
    // 3 copies per page (26cm height ÷ 3 = ~8.67cm per receipt)
    // 15cm width = approximately 56 characters at 10 CPI (characters per inch)
    
    header('Content-Type: text/plain; charset=utf-8');
    header('Content-Disposition: inline; filename="receipt_' . $receipt_no . '.txt"');
    
    $width = 56; // Character width for 15cm paper at 10 CPI
    $receipt_lines = 28; // Lines per receipt (approximately for 8.67cm at 6 LPI)
    
    // Helper functions
    function centerText($text, $width) {
        $text = (string)$text;
        $len = strlen($text);
        if ($len >= $width) return substr($text, 0, $width);
        $pad = floor(($width - $len) / 2);
        return str_repeat(' ', $pad) . $text;
    }
    
    function leftRight($left, $right, $width) {
        $left = (string)$left;
        $right = (string)$right;
        $left = substr($left, 0, floor($width * 0.65));
        $right = substr($right, 0, floor($width * 0.30));
        $spaces = $width - strlen($left) - strlen($right);
        return $left . str_repeat(' ', max(1, $spaces)) . $right;
    }
    
    function line($char, $width) {
        return str_repeat($char, $width);
    }
    
    function truncate($text, $maxlen) {
        $text = (string)$text;
        if (strlen($text) <= $maxlen) return $text;
        return substr($text, 0, $maxlen - 3) . '...';
    }
    
    // Function to generate single receipt
    function generateReceipt($templeInfo, $receipt_no, $first_booking, $receipt_holder_name, $receipt_holder_phone, $bookings, $total_amount, $width) {
        $output = "";
        
        // Sanitize all values
        $temple_name = isset($templeInfo['temple_name']) ? $templeInfo['temple_name'] : 'Temple';
        $address = isset($templeInfo['address']) ? $templeInfo['address'] : '';
        $city = isset($templeInfo['city']) ? $templeInfo['city'] : '';
        $pincode = isset($templeInfo['pincode']) ? $templeInfo['pincode'] : '';
        $phone = isset($templeInfo['phone']) ? $templeInfo['phone'] : '';
        
        // Header - compact
        $output .= line('=', $width) . "\n";
        $output .= centerText(strtoupper($temple_name), $width) . "\n";
        if ($address) {
            $output .= centerText(truncate($address, $width), $width) . "\n";
        }
        if ($city || $pincode) {
            $output .= centerText($city . ($city && $pincode ? ', ' : '') . $pincode, $width) . "\n";
        }
        if ($phone) {
            $output .= centerText('Ph: ' . $phone, $width) . "\n";
        }
        $output .= line('=', $width) . "\n";
        
        // Receipt info
        $created_at = isset($first_booking['created_at']) ? $first_booking['created_at'] : date('Y-m-d H:i:s');
        $output .= leftRight('Rcpt: ' . $receipt_no, date('d/m/Y H:i', strtotime($created_at)), $width) . "\n";
        $output .= line('-', $width) . "\n";
        
        // Devotee info
        $output .= "Devotee: " . truncate(strtoupper($receipt_holder_name), $width - 9) . "\n";
        if ($receipt_holder_phone) {
            $output .= "Phone: " . $receipt_holder_phone . "\n";
        }
        $output .= line('-', $width) . "\n";
        
        // Items header
        $output .= centerText('VAZHIPADU DETAILS', $width) . "\n";
        $output .= line('-', $width) . "\n";
        
        // Items - compact display (max 5 items to fit)
        $max_items = 5;
        foreach (array_slice($bookings, 0, $max_items) as $idx => $booking) {
            $vazhipadu_name = isset($booking['vazhipadu_name']) ? $booking['vazhipadu_name'] : 'N/A';
            $amount = isset($booking['amount']) ? $booking['amount'] : 0;
            
            // Item name and amount on same line
            $item_line = ($idx + 1) . ". " . truncate(strtoupper($vazhipadu_name), 30);
            $output .= leftRight($item_line, formatCurrency($amount), $width) . "\n";
            
            // Details in one compact line
            $devotee_name = isset($booking['devotee_name']) ? $booking['devotee_name'] : '';
            $details = "For: " . truncate($devotee_name, 15);
            
            if (isset($booking['star_name']) && $booking['star_name']) {
                $details .= " | " . truncate($booking['star_name'], 10);
            }
            if (isset($booking['seva_date']) && $booking['seva_date']) {
                $details .= " | " . date('d/m/y', strtotime($booking['seva_date']));
            }
            $output .= "   " . truncate($details, $width - 3) . "\n";
        }
        
        if (count($bookings) > $max_items) {
            $output .= "   ..." . (count($bookings) - $max_items) . " more items\n";
        }
        
        $output .= line('-', $width) . "\n";
        
        // Total
        $payment_mode = isset($first_booking['payment_mode']) ? $first_booking['payment_mode'] : 'CASH';
        $output .= leftRight('Items: ' . count($bookings) . ' | ' . $payment_mode, '', $width) . "\n";
        $output .= leftRight('TOTAL AMOUNT:', formatCurrency($total_amount), $width) . "\n";
        $output .= line('=', $width) . "\n";
        
        // Footer
        $output .= centerText('Thank You - May God Bless You', $width) . "\n";
        $output .= line('=', $width) . "\n";
        
        return $output;
    }
    
    // Generate single receipt
    $output = "\n"; // Top margin
    $output .= generateReceipt($templeInfo, $receipt_no, $first_booking, $receipt_holder_name, $receipt_holder_phone, $bookings, $total_amount, $width);
    
    // Add form feed to eject paper
    $output .= "\f";
    
    echo $output;
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print Receipt - <?php echo $receipt_no; ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 900px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        
        .card {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        h1 {
            color: #333;
            margin-bottom: 10px;
        }
        
        .info {
            background: #e3f2fd;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }
        
        .paper-info {
            background: #fff3e0;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
            border-left: 4px solid #ff9800;
        }
        
        .paper-info strong {
            color: #e65100;
        }
        
        .btn-group {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }
        
        .btn {
            flex: 1;
            padding: 15px 30px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            text-decoration: none;
            text-align: center;
            display: inline-block;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background: #2196F3;
            color: white;
        }
        
        .btn-primary:hover {
            background: #1976D2;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }
        
        .btn-success {
            background: #4CAF50;
            color: white;
        }
        
        .btn-success:hover {
            background: #45a049;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }
        
        .preview {
            background: #f9f9f9;
            padding: 20px;
            border: 2px dashed #ccc;
            border-radius: 5px;
            margin-top: 20px;
            font-family: 'Courier New', monospace;
            white-space: pre;
            overflow-x: auto;
            font-size: 11px;
            line-height: 1.3;
            max-height: 600px;
            overflow-y: auto;
        }
        
        .instructions {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin-top: 20px;
        }
        
        .instructions h3 {
            margin-top: 0;
            color: #856404;
        }
        
        .instructions ol {
            margin: 10px 0;
            padding-left: 20px;
        }
        
        .instructions li {
            margin: 8px 0;
        }
        
        .copy-indicator {
            color: #666;
            font-size: 13px;
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <div class="card">
        <h1>🖨️ Dot Matrix Receipt Printer</h1>
        <p>Receipt Number: <strong><?php echo $receipt_no; ?></strong></p>
        
        <div class="paper-info">
            <strong>📄 Paper Format:</strong> 15cm × 26cm<br>
            <strong>🔢 Print Mode:</strong> Single receipt per page<br>
            <strong>📏 Width:</strong> 56 characters @ 10 CPI
        </div>
        
        <div class="info">
            <strong>ℹ️ Receipt Details:</strong><br>
            Devotee: <?php echo htmlspecialchars($receipt_holder_name); ?><br>
            Items: <?php echo count($bookings); ?><br>
            Total: <?php echo formatCurrency($total_amount); ?>
        </div>
        
        <div class="btn-group">
            <a href="?receipt_no=<?php echo urlencode($receipt_no); ?>&format=text" 
               class="btn btn-primary" target="_blank">
               📄 View Text Receipt
            </a>
            <a href="?receipt_no=<?php echo urlencode($receipt_no); ?>&format=text" 
               class="btn btn-success" download="receipt_<?php echo $receipt_no; ?>.txt">
               💾 Download Text File
            </a>
        </div>
        
        <div class="instructions">
            <h3>📋 Printing Instructions:</h3>
            <ol>
                <li><strong>Paper Setup:</strong> Load 15cm × 26cm continuous paper in your dot matrix printer</li>
                <li><strong>View Receipt:</strong> Click blue button to see the text receipt</li>
                <li><strong>Print Directly:</strong> From text view, use Ctrl+P and select your dot matrix printer</li>
                <li><strong>Download:</strong> Click green button to save as .txt file for later printing</li>
                <li><strong>Command Line Print (Windows):</strong>
                    <pre style="background: #333; color: #0f0; padding: 10px; margin: 5px 0;">COPY receipt_<?php echo $receipt_no; ?>.txt LPT1</pre>
                    or
                    <pre style="background: #333; color: #0f0; padding: 10px; margin: 5px 0;">PRINT /D:LPT1 receipt_<?php echo $receipt_no; ?>.txt</pre>
                </li>
                <li><strong>Command Line Print (Linux):</strong>
                    <pre style="background: #333; color: #0f0; padding: 10px; margin: 5px 0;">lpr -P printer_name receipt_<?php echo $receipt_no; ?>.txt</pre>
                </li>
            </ol>
        </div>
        
        <div class="copy-indicator">
            <strong>Note:</strong> One receipt printed per page. Print multiple times if you need copies.
        </div>
        
        <div class="preview" id="preview">Loading preview...</div>
    </div>
    
    <script>
        // Load preview
        fetch('?receipt_no=<?php echo urlencode($receipt_no); ?>&format=text')
            .then(response => response.text())
            .then(data => {
                document.getElementById('preview').textContent = data;
            })
            .catch(error => {
                document.getElementById('preview').textContent = 'Error loading preview';
            });
    </script>
</body>
</html>
