<?php
require_once 'config.php';
requireLogin();

// Check if user is super admin
if (!isSuperAdmin()) {
    header('Location: index.php');
    exit();
}

$message = '';
$messageType = '';

// Handle Temple Actions
if (isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'add_temple') {
        $temple_code = strtoupper(sanitizeInput($_POST['temple_code']));
        $temple_name = sanitizeInput($_POST['temple_name']);
        $address = sanitizeInput($_POST['address']);
        $city = sanitizeInput($_POST['city']);
        $state = sanitizeInput($_POST['state']);
        $pincode = sanitizeInput($_POST['pincode']);
        $phone = sanitizeInput($_POST['phone']);
        $email = sanitizeInput($_POST['email']);
        $subscription_plan = sanitizeInput($_POST['subscription_plan']);
        $subscription_expiry = sanitizeInput($_POST['subscription_expiry']);
        
        // Check if temple code exists
        $checkQuery = "SELECT id FROM temples WHERE temple_code = '$temple_code'";
        $checkResult = $conn->query($checkQuery);
        
        if ($checkResult->num_rows > 0) {
            $message = "Temple code already exists!";
            $messageType = 'error';
        } else {
            $query = "INSERT INTO temples (temple_code, temple_name, address, city, state, pincode, phone, email, subscription_plan, subscription_expiry) 
                      VALUES ('$temple_code', '$temple_name', '$address', '$city', '$state', '$pincode', '$phone', '$email', '$subscription_plan', '$subscription_expiry')";
            
            if ($conn->query($query)) {
                $temple_id = $conn->insert_id;
                
                // Create default admin user for this temple
                $admin_username = strtolower(str_replace(' ', '_', $temple_code)) . '_admin';
                $admin_password = password_hash('admin123', PASSWORD_DEFAULT);
                
                $userQuery = "INSERT INTO users (temple_id, username, password, full_name, role) 
                              VALUES ($temple_id, '$admin_username', '$admin_password', '$temple_name Admin', 'TempleAdmin')";
                $conn->query($userQuery);
                
                logAudit('Temple Created', 'temples', $temple_id, null, ['temple_name' => $temple_name]);
                
                $message = "Temple added successfully! Admin username: $admin_username, Password: admin123";
                $messageType = 'success';
            } else {
                $message = "Error: " . $conn->error;
                $messageType = 'error';
            }
        }
    }
    
    elseif ($action === 'update_temple') {
        $id = intval($_POST['id']);
        $temple_name = sanitizeInput($_POST['temple_name']);
        $address = sanitizeInput($_POST['address']);
        $city = sanitizeInput($_POST['city']);
        $state = sanitizeInput($_POST['state']);
        $pincode = sanitizeInput($_POST['pincode']);
        $phone = sanitizeInput($_POST['phone']);
        $email = sanitizeInput($_POST['email']);
        $subscription_plan = sanitizeInput($_POST['subscription_plan']);
        $subscription_expiry = sanitizeInput($_POST['subscription_expiry']);
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        $query = "UPDATE temples SET 
                  temple_name='$temple_name', 
                  address='$address', 
                  city='$city', 
                  state='$state', 
                  pincode='$pincode', 
                  phone='$phone', 
                  email='$email',
                  subscription_plan='$subscription_plan',
                  subscription_expiry='$subscription_expiry',
                  is_active=$is_active 
                  WHERE id=$id";
        
        if ($conn->query($query)) {
            logAudit('Temple Updated', 'temples', $id, null, ['temple_name' => $temple_name]);
            $message = "Temple updated successfully!";
            $messageType = 'success';
        } else {
            $message = "Error: " . $conn->error;
            $messageType = 'error';
        }
    }
}

// Get all temples
$templesQuery = "SELECT t.*, 
    (SELECT COUNT(*) FROM users WHERE temple_id = t.id AND is_active = 1) as active_users,
    (SELECT COUNT(*) FROM vazhipadu_bookings WHERE temple_id = t.id) as total_bookings,
    (SELECT SUM(amount) FROM vazhipadu_bookings WHERE temple_id = t.id AND payment_status='Completed') as total_revenue
    FROM temples t
    ORDER BY t.created_at DESC";
$temples = $conn->query($templesQuery);

// Get system-wide statistics
$statsQuery = "SELECT 
    (SELECT COUNT(*) FROM temples WHERE is_active=1) as active_temples,
    (SELECT COUNT(*) FROM temples) as total_temples,
    (SELECT COUNT(*) FROM users WHERE is_active=1) as total_users,
    (SELECT COUNT(*) FROM vazhipadu_bookings) as total_bookings,
    (SELECT SUM(amount) FROM vazhipadu_bookings WHERE payment_status='Completed') as total_revenue";
$statsResult = $conn->query($statsQuery);
$stats = $statsResult->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin Dashboard - <?php echo SYSTEM_NAME; ?></title>
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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .header h1 {
            font-size: 24px;
            margin-bottom: 5px;
        }
        .header .user-info {
            float: right;
            margin-top: -40px;
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
            background: #667eea;
            color: white;
        }
        .container {
            max-width: 1400px;
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
            color: #667eea;
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
            border-bottom: 2px solid #667eea;
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
            background: #667eea;
            color: white;
        }
        .btn-primary:hover {
            background: #5568d3;
        }
        .btn-success {
            background: #28a745;
            color: white;
        }
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        .btn-danger {
            background: #dc3545;
            color: white;
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
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
        }
        .modal.active {
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 10px;
            max-width: 600px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .close-modal {
            cursor: pointer;
            font-size: 24px;
            color: #666;
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #333;
            font-size: 14px;
        }
        input, select, textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        .status-badge {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
        }
        .status-active {
            background: #d4edda;
            color: #155724;
        }
        .status-inactive {
            background: #f8d7da;
            color: #721c24;
        }
        .plan-badge {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 500;
        }
        .plan-free { background: #e7e7e7; color: #333; }
        .plan-basic { background: #cfe2ff; color: #084298; }
        .plan-premium { background: #fef3cd; color: #664d03; }
        .plan-enterprise { background: #d1e7dd; color: #0f5132; }
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .checkbox-group input[type="checkbox"] {
            width: auto;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1><?php echo SYSTEM_NAME; ?> - Super Admin</h1>
        <p>Multi-Temple Management Dashboard</p>
        <div class="user-info">
            Welcome, <?php echo htmlspecialchars($_SESSION['full_name']); ?> | 
            <a href="logout.php" style="color: white;">Logout</a>
        </div>
    </div>
    
    <div class="nav">
        <ul>
            <li><a href="super_admin.php" class="active">Temples</a></li>
            <li><a href="super_admin_users.php">All Users</a></li>
            <li><a href="super_admin_reports.php">System Reports</a></li>
            <li><a href="super_admin_audit.php">Audit Logs</a></li>
        </ul>
    </div>
    
    <div class="container">
        <?php if ($message): ?>
        <div class="message <?php echo $messageType; ?>">
            <?php echo $message; ?>
        </div>
        <?php endif; ?>
        
        <h2 style="margin-bottom: 20px;">System Overview</h2>
        
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Active Temples</h3>
                <div class="value"><?php echo $stats['active_temples']; ?></div>
                <small>out of <?php echo $stats['total_temples']; ?> total</small>
            </div>
            <div class="stat-card">
                <h3>Total Users</h3>
                <div class="value"><?php echo number_format($stats['total_users']); ?></div>
            </div>
            <div class="stat-card">
                <h3>Total Bookings</h3>
                <div class="value"><?php echo number_format($stats['total_bookings']); ?></div>
            </div>
            <div class="stat-card">
                <h3>Total Revenue</h3>
                <div class="value"><?php echo formatCurrency($stats['total_revenue']); ?></div>
            </div>
        </div>
        
        <div class="card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h2 style="margin: 0;">All Temples</h2>
                <button class="btn btn-primary" onclick="openModal('addTempleModal')">
                    + Onboard New Temple
                </button>
            </div>
            
            <table>
                <thead>
                    <tr>
                        <th>Temple Code</th>
                        <th>Temple Name</th>
                        <th>Location</th>
                        <th>Plan</th>
                        <th>Expiry</th>
                        <th>Users</th>
                        <th>Bookings</th>
                        <th>Revenue</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($temple = $temples->fetch_assoc()): ?>
                    <tr>
                        <td><strong><?php echo $temple['temple_code']; ?></strong></td>
                        <td><?php echo htmlspecialchars($temple['temple_name']); ?></td>
                        <td><?php echo htmlspecialchars($temple['city'] . ', ' . $temple['state']); ?></td>
                        <td>
                            <span class="plan-badge plan-<?php echo strtolower($temple['subscription_plan']); ?>">
                                <?php echo $temple['subscription_plan']; ?>
                            </span>
                        </td>
                        <td><?php echo $temple['subscription_expiry'] ? date('d/m/Y', strtotime($temple['subscription_expiry'])) : '-'; ?></td>
                        <td><?php echo $temple['active_users']; ?></td>
                        <td><?php echo number_format($temple['total_bookings']); ?></td>
                        <td><?php echo formatCurrency($temple['total_revenue']); ?></td>
                        <td>
                            <span class="status-badge <?php echo $temple['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                                <?php echo $temple['is_active'] ? 'Active' : 'Inactive'; ?>
                            </span>
                        </td>
                        <td>
                            <button class="btn btn-secondary" onclick='editTemple(<?php echo json_encode($temple); ?>)'>Edit</button>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Add Temple Modal -->
    <div id="addTempleModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Onboard New Temple</h2>
                <span class="close-modal" onclick="closeModal('addTempleModal')">&times;</span>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="add_temple">
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Temple Code * (e.g., TEMP004)</label>
                        <input type="text" name="temple_code" required pattern="[A-Z0-9]{4,20}" style="text-transform: uppercase;">
                    </div>
                    <div class="form-group">
                        <label>Temple Name *</label>
                        <input type="text" name="temple_name" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Address</label>
                    <textarea name="address" rows="2"></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>City *</label>
                        <input type="text" name="city" required>
                    </div>
                    <div class="form-group">
                        <label>State *</label>
                        <input type="text" name="state" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Pincode</label>
                        <input type="text" name="pincode" pattern="[0-9]{6}">
                    </div>
                    <div class="form-group">
                        <label>Phone *</label>
                        <input type="tel" name="phone" required pattern="[+0-9\s]{10,15}">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email">
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Subscription Plan *</label>
                        <select name="subscription_plan" required>
                            <option value="Free">Free</option>
                            <option value="Basic" selected>Basic</option>
                            <option value="Premium">Premium</option>
                            <option value="Enterprise">Enterprise</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Subscription Expiry *</label>
                        <input type="date" name="subscription_expiry" required value="<?php echo date('Y-m-d', strtotime('+1 year')); ?>">
                    </div>
                </div>
                
                <div style="text-align: right; margin-top: 20px;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('addTempleModal')">Cancel</button>
                    <button type="submit" class="btn btn-success">Onboard Temple</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Edit Temple Modal -->
    <div id="editTempleModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Edit Temple</h2>
                <span class="close-modal" onclick="closeModal('editTempleModal')">&times;</span>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="update_temple">
                <input type="hidden" name="id" id="edit_temple_id">
                
                <div class="form-group">
                    <label>Temple Code (cannot be changed)</label>
                    <input type="text" id="edit_temple_code" disabled>
                </div>
                
                <div class="form-group">
                    <label>Temple Name *</label>
                    <input type="text" name="temple_name" id="edit_temple_name" required>
                </div>
                
                <div class="form-group">
                    <label>Address</label>
                    <textarea name="address" id="edit_temple_address" rows="2"></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>City *</label>
                        <input type="text" name="city" id="edit_temple_city" required>
                    </div>
                    <div class="form-group">
                        <label>State *</label>
                        <input type="text" name="state" id="edit_temple_state" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Pincode</label>
                        <input type="text" name="pincode" id="edit_temple_pincode">
                    </div>
                    <div class="form-group">
                        <label>Phone *</label>
                        <input type="tel" name="phone" id="edit_temple_phone" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" id="edit_temple_email">
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Subscription Plan *</label>
                        <select name="subscription_plan" id="edit_temple_plan" required>
                            <option value="Free">Free</option>
                            <option value="Basic">Basic</option>
                            <option value="Premium">Premium</option>
                            <option value="Enterprise">Enterprise</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Subscription Expiry *</label>
                        <input type="date" name="subscription_expiry" id="edit_temple_expiry" required>
                    </div>
                </div>
                
                <div class="form-group checkbox-group">
                    <input type="checkbox" name="is_active" id="edit_temple_active" value="1">
                    <label style="margin: 0;">Active</label>
                </div>
                
                <div style="text-align: right; margin-top: 20px;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('editTempleModal')">Cancel</button>
                    <button type="submit" class="btn btn-success">Update Temple</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function openModal(modalId) {
            document.getElementById(modalId).classList.add('active');
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
        }
        
        function editTemple(data) {
            document.getElementById('edit_temple_id').value = data.id;
            document.getElementById('edit_temple_code').value = data.temple_code;
            document.getElementById('edit_temple_name').value = data.temple_name;
            document.getElementById('edit_temple_address').value = data.address || '';
            document.getElementById('edit_temple_city').value = data.city;
            document.getElementById('edit_temple_state').value = data.state;
            document.getElementById('edit_temple_pincode').value = data.pincode || '';
            document.getElementById('edit_temple_phone').value = data.phone;
            document.getElementById('edit_temple_email').value = data.email || '';
            document.getElementById('edit_temple_plan').value = data.subscription_plan;
            document.getElementById('edit_temple_expiry').value = data.subscription_expiry;
            document.getElementById('edit_temple_active').checked = data.is_active == 1;
            openModal('editTempleModal');
        }
        
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.classList.remove('active');
            }
        }
    </script>
</body>
</html>