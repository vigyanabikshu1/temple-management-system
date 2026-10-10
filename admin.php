<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once 'config.php';
requireLogin();
$templeid=$_SESSION['temple_id'];
// Check if user is admin
if (!isTempleAdmin()) {
    header('Location: index.php');
    exit();
}

$message = '';
$messageType = '';

// Handle Vazhipadu Type Actions
if (isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'add_vazhipadu') {
        $name = sanitizeInput($_POST['name']);
        $description = sanitizeInput($_POST['description']);
        $amount = floatval($_POST['amount']);
        
        $query = "INSERT INTO vazhipadu_types (temple_id, name, description, amount) VALUES ('$templeid','$name', '$description', $amount)";
        if ($conn->query($query)) {
            $message = "Vazhipadu type added successfully!";
            $messageType = 'success';
        } else {
            $message = "Error: " . $conn->error;
            $messageType = 'error';
        }
    }
    
    elseif ($action === 'update_vazhipadu') {
        $id = intval($_POST['id']);
        $name = sanitizeInput($_POST['name']);
        $description = sanitizeInput($_POST['description']);
        $amount = floatval($_POST['amount']);
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        $query = "UPDATE vazhipadu_types SET temple_id='$templeid' name='$name', description='$description', amount=$amount, is_active=$is_active WHERE id=$id";
        if ($conn->query($query)) {
            $message = "Vazhipadu type updated successfully!";
            $messageType = 'success';
        } else {
            $message = "Error: " . $conn->error;
            $messageType = 'error';
        }
    }
    
    elseif ($action === 'delete_vazhipadu') {
        $id = intval($_POST['id']);
        
        // Check if there are bookings for this type
        $checkQuery = "SELECT COUNT(*) as count FROM vazhipadu_bookings WHERE vazhipadu_type_id = $id AND temple_id='$templeid'";
        $checkResult = $conn->query($checkQuery);
        $check = $checkResult->fetch_assoc();
        
        if ($check['count'] > 0) {
            $message = "Cannot delete! There are existing bookings for this vazhipadu type.";
            $messageType = 'error';
        } else {
            $query = "DELETE FROM vazhipadu_types WHERE id=$id";
            if ($conn->query($query)) {
                $message = "Vazhipadu type deleted successfully!";
                $messageType = 'success';
            } else {
                $message = "Error: " . $conn->error;
                $messageType = 'error';
            }
        }
    }
    
    elseif ($action === 'add_user') {
        $username = sanitizeInput($_POST['username']);
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $full_name = sanitizeInput($_POST['full_name']);
        $role = sanitizeInput($_POST['role']);
        
        // Check if username exists
        $checkQuery = "SELECT id FROM users WHERE username = '$username'";
        $checkResult = $conn->query($checkQuery);
        
        if ($checkResult->num_rows > 0) {
            $message = "Username already exists!";
            $messageType = 'error';
        } else {
            $query = "INSERT INTO users (username, password, full_name, role) VALUES ('$username', '$password', '$full_name', '$role')";
            if ($conn->query($query)) {
                $message = "User added successfully!";
                $messageType = 'success';
            } else {
                $message = "Error: " . $conn->error;
                $messageType = 'error';
            }
        }
    }
    
    elseif ($action === 'update_user') {
        $id = intval($_POST['id']);
        $full_name = sanitizeInput($_POST['full_name']);
        $role = sanitizeInput($_POST['role']);
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        $query = "UPDATE users SET full_name='$full_name', role='$role', is_active=$is_active WHERE id=$id";
        
        // Update password if provided
        if (!empty($_POST['password'])) {
            $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $query = "UPDATE users SET full_name='$full_name', role='$role', is_active=$is_active, password='$password' WHERE id=$id";
        }
        
        if ($conn->query($query)) {
            $message = "User updated successfully!";
            $messageType = 'success';
        } else {
            $message = "Error: " . $conn->error;
            $messageType = 'error';
        }
    }
    
    elseif ($action === 'delete_user') {
        $id = intval($_POST['id']);
        
        if ($id == $_SESSION['user_id']) {
            $message = "You cannot delete your own account!";
            $messageType = 'error';
        } else {
            $query = "DELETE FROM users WHERE id=$id";
            if ($conn->query($query)) {
                $message = "User deleted successfully!";
                $messageType = 'success';
            } else {
                $message = "Error: " . $conn->error;
                $messageType = 'error';
            }
        }
    }
}

// Get all vazhipadu types
$vazhipaduQuery = "SELECT * FROM vazhipadu_types ORDER BY name";
$vazhipaduTypes = $conn->query($vazhipaduQuery);

// Get all users
$usersQuery = "SELECT * FROM users ORDER BY full_name";
$users = $conn->query($usersQuery);

// Get system statistics
$statsQuery = "SELECT 
    (SELECT COUNT(*) FROM devotees) as total_devotees,
    (SELECT COUNT(*) FROM vazhipadu_bookings) as total_bookings,
    (SELECT SUM(amount) FROM vazhipadu_bookings WHERE payment_status='Completed') as total_revenue,
    (SELECT COUNT(*) FROM users WHERE is_active=1) as active_users";
$statsResult = $conn->query($statsQuery);
$stats = $statsResult->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - <?php echo $_SESSION['temple_name']; ?></title>
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
        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        .tab-btn {
            padding: 12px 25px;
            background: white;
            border: none;
            border-radius: 5px 5px 0 0;
            cursor: pointer;
            font-size: 15px;
            font-weight: 500;
            transition: all 0.3s;
        }
        .tab-btn.active {
            background: #ff6b35;
            color: white;
        }
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
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
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #333;
        }
        input, select, textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }
        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: #ff6b35;
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
            background: #ff6b35;
            color: white;
        }
        .btn-primary:hover {
            background: #e55a2b;
        }
        .btn-success {
            background: #28a745;
            color: white;
        }
        .btn-success:hover {
            background: #218838;
        }
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        .btn-danger:hover {
            background: #c82333;
        }
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        .btn-secondary:hover {
            background: #5a6268;
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
            max-width: 500px;
            width: 90%;
            max-height: 80vh;
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
<div id="google_translate_element"></div>

    <div class="header">
        <h1><?php echo $_SESSION['temple_name']; ?> - Admin Panel</h1>
    </div>
    
    <div class="nav">
        <ul>
            <li><a href="index.php">Dashboard</a></li>
            <li><a href="new_booking.php">New Booking</a></li>
            <li><a href="search.php">Search</a></li>
            <li><a href="reports.php">Reports</a></li>
            <li><a href="admin.php" class="active">Admin</a></li>
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
                <h3>Total Devotees</h3>
                <div class="value"><?php echo number_format($stats['total_devotees']); ?></div>
            </div>
            <div class="stat-card">
                <h3>Total Bookings</h3>
                <div class="value"><?php echo number_format($stats['total_bookings']); ?></div>
            </div>
            <div class="stat-card">
                <h3>Total Revenue</h3>
                <div class="value"><?php echo formatCurrency($stats['total_revenue']); ?></div>
            </div>
            <div class="stat-card">
                <h3>Active Users</h3>
                <div class="value"><?php echo $stats['active_users']; ?></div>
            </div>
        </div>
        
        <div class="tabs">
            <button class="tab-btn active" onclick="switchTab('vazhipadu')">Vazhipadu Types</button>
            <button class="tab-btn" onclick="switchTab('users')">User Management</button>
        </div>
        
        <!-- Vazhipadu Types Tab -->
        <div id="vazhipadu-tab" class="tab-content active">
            <div class="card">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h2 style="margin: 0;">Vazhipadu Types</h2>
                    <button class="btn btn-primary" onclick="openModal('addVazhipaduModal')">
                        + Add New Type
                    </button>
                </div>
                
                <table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Description</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($type = $vazhipaduTypes->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($type['name']); ?></td>
                            <td><?php echo htmlspecialchars($type['description']); ?></td>
                            <td><?php echo formatCurrency($type['amount']); ?></td>
                            <td>
                                <span class="status-badge <?php echo $type['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                                    <?php echo $type['is_active'] ? 'Active' : 'Inactive'; ?>
                                </span>
                            </td>
                            <td>
                                <button class="btn btn-secondary" onclick='editVazhipadu(<?php echo json_encode($type); ?>)'>Edit</button>
                                <button class="btn btn-danger" onclick="deleteVazhipadu(<?php echo $type['id']; ?>, '<?php echo addslashes($type['name']); ?>')">Delete</button>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Users Tab -->
        <div id="users-tab" class="tab-content">
            <div class="card">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h2 style="margin: 0;">System Users</h2>
                    <button class="btn btn-primary" onclick="openModal('addUserModal')">
                        + Add New User
                    </button>
                </div>
                
                <table>
                    <thead>
                        <tr>
                            <th>Username</th>
                            <th>Full Name</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($user = $users->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($user['username']); ?></td>
                            <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                            <td><?php echo $user['role']; ?></td>
                            <td>
                                <span class="status-badge <?php echo $user['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                                    <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                                </span>
                            </td>
                            <td>
                                <button class="btn btn-secondary" onclick='editUser(<?php echo json_encode($user); ?>)'>Edit</button>
                                <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                <button class="btn btn-danger" onclick="deleteUser(<?php echo $user['id']; ?>, '<?php echo addslashes($user['username']); ?>')">Delete</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Add Vazhipadu Modal -->
    <div id="addVazhipaduModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Add Vazhipadu Type</h2>
                <span class="close-modal" onclick="closeModal('addVazhipaduModal')">&times;</span>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="add_vazhipadu">
                <div class="form-group">
                    <label>Name *</label>
                    <input type="text" name="name" required>
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" rows="3"></textarea>
                </div>
                <div class="form-group">
                    <label>Amount *</label>
                    <input type="number" name="amount" step="0.01" min="0" required>
                </div>
                <div style="text-align: right; margin-top: 20px;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('addVazhipaduModal')">Cancel</button>
                    <button type="submit" class="btn btn-success">Add Type</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Edit Vazhipadu Modal -->
    <div id="editVazhipaduModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Edit Vazhipadu Type</h2>
                <span class="close-modal" onclick="closeModal('editVazhipaduModal')">&times;</span>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="update_vazhipadu">
                <input type="hidden" name="id" id="edit_vazhipadu_id">
                <div class="form-group">
                    <label>Name *</label>
                    <input type="text" name="name" id="edit_vazhipadu_name" required>
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" id="edit_vazhipadu_description" rows="3"></textarea>
                </div>
                <div class="form-group">
                    <label>Amount *</label>
                    <input type="number" name="amount" id="edit_vazhipadu_amount" step="0.01" min="0" required>
                </div>
                <div class="form-group checkbox-group">
                    <input type="checkbox" name="is_active" id="edit_vazhipadu_active" value="1">
                    <label style="margin: 0;">Active</label>
                </div>
                <div style="text-align: right; margin-top: 20px;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('editVazhipaduModal')">Cancel</button>
                    <button type="submit" class="btn btn-success">Update</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Add User Modal -->
    <div id="addUserModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Add New User</h2>
                <span class="close-modal" onclick="closeModal('addUserModal')">&times;</span>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="add_user">
                <div class="form-group">
                    <label>Username *</label>
                    <input type="text" name="username" required>
                </div>
                <div class="form-group">
                    <label>Password *</label>
                    <input type="password" name="password" required minlength="6">
                </div>
                <div class="form-group">
                    <label>Full Name *</label>
                    <input type="text" name="full_name" required>
                </div>
                <div class="form-group">
                    <label>Role *</label>
                    <select name="role" required>
                        <option value="Staff">Staff</option>
                        <option value="Counter">Counter</option>
                        <option value="Admin">Admin</option>
                    </select>
                </div>
                <div style="text-align: right; margin-top: 20px;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('addUserModal')">Cancel</button>
                    <button type="submit" class="btn btn-success">Add User</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Edit User Modal -->
    <div id="editUserModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Edit User</h2>
                <span class="close-modal" onclick="closeModal('editUserModal')">&times;</span>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="update_user">
                <input type="hidden" name="id" id="edit_user_id">
                <div class="form-group">
                    <label>Username (cannot be changed)</label>
                    <input type="text" id="edit_user_username" disabled>
                </div>
                <div class="form-group">
                    <label>New Password (leave blank to keep current)</label>
                    <input type="password" name="password" minlength="6">
                </div>
                <div class="form-group">
                    <label>Full Name *</label>
                    <input type="text" name="full_name" id="edit_user_fullname" required>
                </div>
                <div class="form-group">
                    <label>Role *</label>
                    <select name="role" id="edit_user_role" required>
                        <option value="Staff">Staff</option>
                        <option value="Counter">Counter</option>
                        <option value="Admin">Admin</option>
                    </select>
                </div>
                <div class="form-group checkbox-group">
                    <input type="checkbox" name="is_active" id="edit_user_active" value="1">
                    <label style="margin: 0;">Active</label>
                </div>
                <div style="text-align: right; margin-top: 20px;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('editUserModal')">Cancel</button>
                    <button type="submit" class="btn btn-success">Update</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Delete Form (hidden) -->
    <form id="deleteForm" method="POST" style="display: none;">
        <input type="hidden" name="action" id="delete_action">
        <input type="hidden" name="id" id="delete_id">
    </form>
    
    <script>
        function switchTab(tab) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
            document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
            
            // Show selected tab
            document.getElementById(tab + '-tab').classList.add('active');
            event.target.classList.add('active');
        }
        
        function openModal(modalId) {
            document.getElementById(modalId).classList.add('active');
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
        }
        
        function editVazhipadu(data) {
            document.getElementById('edit_vazhipadu_id').value = data.id;
            document.getElementById('edit_vazhipadu_name').value = data.name;
            document.getElementById('edit_vazhipadu_description').value = data.description;
            document.getElementById('edit_vazhipadu_amount').value = data.amount;
            document.getElementById('edit_vazhipadu_active').checked = data.is_active == 1;
            openModal('editVazhipaduModal');
        }
        
        function deleteVazhipadu(id, name) {
            if (confirm('Are you sure you want to delete "' + name + '"?')) {
                document.getElementById('delete_action').value = 'delete_vazhipadu';
                document.getElementById('delete_id').value = id;
                document.getElementById('deleteForm').submit();
            }
        }
        
        function editUser(data) {
            document.getElementById('edit_user_id').value = data.id;
            document.getElementById('edit_user_username').value = data.username;
            document.getElementById('edit_user_fullname').value = data.full_name;
            document.getElementById('edit_user_role').value = data.role;
            document.getElementById('edit_user_active').checked = data.is_active == 1;
            openModal('editUserModal');
        }
        
        function deleteUser(id, username) {
            if (confirm('Are you sure you want to delete user "' + username + '"?')) {
                document.getElementById('delete_action').value = 'delete_user';
                document.getElementById('delete_id').value = id;
                document.getElementById('deleteForm').submit();
            }
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.classList.remove('active');
            }
        }
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
