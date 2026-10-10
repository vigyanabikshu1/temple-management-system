<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once 'config.php';
requireLogin();

$message = '';
$messageType = '';

// Get all vazhipadu types
$typesQuery = "SELECT * FROM vazhipadu_types WHERE is_active = 1 ORDER BY name";
$types = $conn->query($typesQuery);

// Define common stars/nakshatras
$stars = [
    'Aswathi', 'Bharani', 'Karthika', 'Rohini', 'Makayiram', 'Thiruvathira', 'Punartham',
    'Pooyam', 'Ayilyam', 'Makam', 'Pooram', 'Uthram', 'Atham', 'Chithira', 'Chothi',
    'Vishakham', 'Anizham', 'Thrikketta', 'Moolam', 'Pooradam', 'Uthradam', 'Thiruvonam',
    'Avittam', 'Chathayam', 'Pooruruttathi', 'Uthrattathi', 'Revathi'
];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $devotee_name = sanitizeInput($_POST['devotee_name']);
    $phone = sanitizeInput($_POST['phone']);
    $star_name = sanitizeInput($_POST['star_name']);
    $vazhipadu_type_id = intval($_POST['vazhipadu_type_id']);
    $seva_date = sanitizeInput($_POST['seva_date']);
    $payment_mode = sanitizeInput($_POST['payment_mode']);
    $remarks = sanitizeInput($_POST['remarks']);
    
    // Handle custom star if provided
    if (isset($_POST['custom_star']) && !empty($_POST['custom_star'])) {
        $star_name = sanitizeInput($_POST['custom_star']);
    }
    
    $devotee_id = null;
    
    // Check if devotee exists by phone (only if phone is provided)
    if (!empty($phone)) {
        $devoteeQuery = "SELECT id FROM devotees WHERE phone = '$phone'";
        $devoteeResult = $conn->query($devoteeQuery);
        
        if ($devoteeResult->num_rows > 0) {
            $devotee = $devoteeResult->fetch_assoc();
            $devotee_id = $devotee['id'];
            
            // Update devotee name if changed
            $updateQuery = "UPDATE devotees SET name = '$devotee_name' WHERE id = $devotee_id";
            $conn->query($updateQuery);
        } else {
            // Insert new devotee with phone
            $insertDevotee = "INSERT INTO devotees (temple_id, name, phone) VALUES (".$_SESSION['temple_id'].", '$devotee_name', '$phone')";
            if ($conn->query($insertDevotee)) {
                $devotee_id = $conn->insert_id;
            }
        }
    }
    
    // If no phone provided or devotee not found by phone, create devotee without phone
    if (empty($devotee_id)) {
        // Generate a unique identifier for devotees without phone
        $unique_id = 'NO_PHONE_' . time() . '_' . rand(1000, 9999);
        $insertDevotee = "INSERT INTO devotees (temple_id, name, phone) VALUES (".$_SESSION['temple_id'].", '$devotee_name', '$unique_id')";
        if ($conn->query($insertDevotee)) {
            $devotee_id = $conn->insert_id;
        }
    }
    
    // Get vazhipadu amount
    $amountQuery = "SELECT amount FROM vazhipadu_types WHERE id = $vazhipadu_type_id";
    $amountResult = $conn->query($amountQuery);
    $amount = $amountResult->fetch_assoc()['amount'];
    
    // Generate receipt number
    $receipt_no = generateReceiptNo();
    $booking_date = date('Y-m-d');
    
    // Insert booking
    $insertBooking = "INSERT INTO vazhipadu_bookings 
        (temple_id, receipt_no, devotee_id, vazhipadu_type_id, devotee_name, star_name, 
         booking_date, seva_date, amount, payment_mode, remarks, created_by) 
        VALUES 
        (".$_SESSION['temple_id'].",'$receipt_no', $devotee_id, $vazhipadu_type_id, '$devotee_name', '$star_name', 
         '$booking_date', " . ($seva_date ? "'$seva_date'" : "NULL") . ", 
         $amount, '$payment_mode', '$remarks', {$_SESSION['user_id']})";
    
    if ($conn->query($insertBooking)) {
        $booking_id = $conn->insert_id;
        $message = "Booking created successfully! Receipt No: $receipt_no";
        $messageType = 'success';
        
        // Redirect to print receipt
        echo "<script>
            if(confirm('Booking successful! Do you want to print the receipt?')) {
                window.open('print_receipt.php?id=$booking_id', '_blank');
            }
            window.location.href = 'new_booking.php';
        </script>";
    } else {
        $message = "Error creating booking: " . $conn->error;
        $messageType = 'error';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Booking - <?php echo $_SESSION['temple_name']; ?></title>
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
            background: #ff6b35;
            color: white;
        }
        .container {
            max-width: 800px;
            margin: 30px auto;
            padding: 0 20px;
        }
        .card {
            background: white;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
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
        .optional {
            color: #666;
            font-weight: normal;
            font-size: 0.9em;
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
        .amount-display {
            font-size: 24px;
            font-weight: bold;
            color: #ff6b35;
            text-align: center;
            padding: 15px;
            background: #fff3e0;
            border-radius: 5px;
            margin-top: 10px;
        }
        .star-option {
            padding: 8px 12px;
        }
    </style>
</head>
<body>
<div id="google_translate_element"></div>
    <div class="header">
        <h1><?php echo $_SESSION['temple_name']; ?> - New Booking</h1>
    </div>
    
    <div class="nav">
        <ul>
            <li><a href="index.php">Dashboard</a></li>
            <li><a href="new_booking.php" class="active">New Booking</a></li>
            <li><a href="search.php">Search</a></li>
            <li><a href="reports.php">Reports</a></li>
            <?php if (isTempleAdmin()): ?>
            <li><a href="admin.php">Admin</a></li>
            <?php endif; ?>
        </ul>
    </div>
    
    <div class="container">
        <div class="card">
            <h2>New Vazhipadu Booking</h2>
            
            <?php if ($message): ?>
            <div class="message <?php echo $messageType; ?>">
                <?php echo $message; ?>
            </div>
            <?php endif; ?>
            
            <form method="POST" id="bookingForm">
                <div class="form-row">
                    <div class="form-group">
                        <label for="devotee_name">Devotee Name *</label>
                        <input type="text" id="devotee_name" name="devotee_name" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="phone">Phone Number <span class="optional">(Optional)</span></label>
                        <input type="tel" id="phone" name="phone" pattern="[0-9]{10}" 
                               placeholder="10-digit number">
                        <small style="color: #666; font-size: 12px; margin-top: 5px; display: block;">
                            Optional - for devotee records
                        </small>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="star_name">Star/Nakshatra</label>
                        <select id="star_name" name="star_name">
                            <option value="">Select Star</option>
                            <?php foreach ($stars as $star): ?>
                                <option value="<?php echo $star; ?>" class="star-option">
                                    <?php echo $star; ?>
                                </option>
                            <?php endforeach; ?>
                            <option value="Other">Other</option>
                        </select>
                        <input type="text" id="custom_star" name="custom_star" 
                               placeholder="Enter custom star name" 
                               style="display: none; margin-top: 8px;">
                    </div>
                    
                    <div class="form-group">
                        <label for="seva_date">Seva Date</label>
                        <input type="date" id="seva_date" name="seva_date" min="<?php echo date('Y-m-d'); ?>">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="vazhipadu_type_id">Vazhipadu Type *</label>
                    <select id="vazhipadu_type_id" name="vazhipadu_type_id" required>
                        <option value="">Select Vazhipadu</option>
                        <?php while ($type = $types->fetch_assoc()): ?>
                        <option value="<?php echo $type['id']; ?>" 
                                data-amount="<?php echo $type['amount']; ?>">
                            <?php echo $type['name']; ?> - ₹<?php echo $type['amount']; ?>
                        </option>
                        <?php endwhile; ?>
                    </select>
                    <div id="amountDisplay" class="amount-display" style="display: none;">
                        Amount: ₹<span id="amount">0.00</span>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="payment_mode">Payment Mode *</label>
                    <select id="payment_mode" name="payment_mode" required>
                        <option value="Cash">Cash</option>
                        <option value="Card">Card</option>
                        <option value="UPI">UPI</option>
                        <option value="Online">Online</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="remarks">Remarks</label>
                    <textarea id="remarks" name="remarks" rows="3"></textarea>
                </div>
                
                <div style="text-align: center; margin-top: 30px;">
                    <button type="submit" class="btn btn-primary">Create Booking</button>
                    <button type="reset" class="btn btn-secondary">Clear Form</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // Vazhipadu amount display
        document.getElementById('vazhipadu_type_id').addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const amount = selectedOption.getAttribute('data-amount');
            
            if (amount) {
                document.getElementById('amount').textContent = parseFloat(amount).toFixed(2);
                document.getElementById('amountDisplay').style.display = 'block';
            } else {
                document.getElementById('amountDisplay').style.display = 'none';
            }
        });
        
        // Auto-format phone number
        document.getElementById('phone').addEventListener('input', function() {
            this.value = this.value.replace(/[^0-9]/g, '').substring(0, 10);
        });
        
        // Handle custom star input
        document.getElementById('star_name').addEventListener('change', function() {
            const customStarInput = document.getElementById('custom_star');
            if (this.value === 'Other') {
                customStarInput.style.display = 'block';
                customStarInput.required = true;
            } else {
                customStarInput.style.display = 'none';
                customStarInput.required = false;
                customStarInput.value = '';
            }
        });
        
        // Form submission - handle custom star
        document.getElementById('bookingForm').addEventListener('submit', function(e) {
            const starSelect = document.getElementById('star_name');
            const customStarInput = document.getElementById('custom_star');
            
            if (starSelect.value === 'Other' && customStarInput.value.trim() === '') {
                e.preventDefault();
                alert('Please enter a custom star name or select from the list.');
                customStarInput.focus();
                return false;
            }
            
            // If custom star is entered, use that value instead
            if (starSelect.value === 'Other' && customStarInput.value.trim() !== '') {
                // Create a hidden input to send the custom star value
                const hiddenInput = document.createElement('input');
                hiddenInput.type = 'hidden';
                hiddenInput.name = 'star_name';
                hiddenInput.value = customStarInput.value.trim();
                this.appendChild(hiddenInput);
                
                // Disable the original select to avoid conflict
                starSelect.disabled = true;
            }
        });
    </script>
    
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
