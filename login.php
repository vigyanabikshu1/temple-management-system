<?php
require_once 'config.php';

// Redirect if already logged in
if (isLoggedIn()) {
    if (isSuperAdmin()) {
        header('Location: super_admin.php');
    } else {
        header('Location: index.php');
    }
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitizeInput($_POST['username']);
    $password = $_POST['password'];
    $temple_id = isset($_POST['temple_id']) ? intval($_POST['temple_id']) : 0;
    
    // For super admin, temple_id can be 0
    if ($temple_id == 0) {
        $query = "SELECT u.*, t.temple_name, t.temple_code, t.is_active as temple_active 
                  FROM users u 
                  LEFT JOIN temples t ON u.temple_id = t.id
                  WHERE u.username = '$username' AND u.is_active = 1 AND u.role = 'SuperAdmin'";
    } else {
        $query = "SELECT u.*, t.temple_name, t.temple_code, t.is_active as temple_active 
                  FROM users u 
                  JOIN temples t ON u.temple_id = t.id
                  WHERE u.username = '$username' AND u.temple_id = $temple_id AND u.is_active = 1 AND t.is_active = 1";
    }
    
    $result = $conn->query($query);
    
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        
        // Verify password
        if (password_verify($password, $user['password'])) {
            // Check subscription if not super admin
            if ($user['role'] !== 'SuperAdmin' && !$user['temple_active']) {
                $error = 'Temple is currently inactive. Please contact administrator.';
            } else {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['temple_id'] = $user['temple_id'];
                $_SESSION['temple_name'] = $user['temple_name'];
                $_SESSION['temple_code'] = $user['temple_code'];
                
                // Update last login
                $updateLogin = "UPDATE users SET last_login = NOW() WHERE id = {$user['id']}";
                $conn->query($updateLogin);
                
                // Log audit
                logAudit('User Login', 'users', $user['id']);
                
                // Redirect based on role
                if ($user['role'] === 'SuperAdmin') {
                    header('Location: super_admin.php');
                } else {
                    header('Location: index.php');
                }
                exit();
            }
        } else {
            $error = 'Invalid username or password';
        }
    } else {
        $error = 'Invalid username or password';
    }
}

// Get all active temples for dropdown
$templesQuery = "SELECT id, temple_name, temple_code FROM temples WHERE is_active = 1 ORDER BY temple_name";
$temples = $conn->query($templesQuery);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo SYSTEM_NAME; ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }
        .login-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            max-width: 450px;
            width: 100%;
            padding: 40px;
        }
        .logo {
            text-align: center;
            margin-bottom: 30px;
        }
        .logo h1 {
            color: #667eea;
            font-size: 28px;
            margin-bottom: 5px;
        }
        .logo p {
            color: #666;
            font-size: 14px;
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
        input, select {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }
        input:focus, select:focus {
            outline: none;
            border-color: #667eea;
        }
        .btn-login {
            width: 100%;
            padding: 12px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s;
        }
        .btn-login:hover {
            background: #5568d3;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 20px;
            border: 1px solid #f5c6cb;
        }
        .info {
            background: #d1ecf1;
            color: #0c5460;
            padding: 12px;
            border-radius: 5px;
            margin-top: 20px;
            font-size: 13px;
            border: 1px solid #bee5eb;
        }
        .super-admin-link {
            text-align: center;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #eee;
        }
        .super-admin-link a {
            color: #667eea;
            text-decoration: none;
            font-size: 13px;
        }
        .super-admin-link a:hover {
            text-decoration: underline;
        }
        #superAdminFields {
            display: none;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo">
            <h1><?php echo SYSTEM_NAME; ?></h1>
            <p>Multi-Temple Vazhipadu Management</p>
        </div>
        
        <?php if ($error): ?>
        <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <form method="POST" id="loginForm">
            <div class="form-group" id="templeSelectGroup">
                <label for="temple_id">Select Temple</label>
                <select id="temple_id" name="temple_id" required>
                    <option value="">-- Select Your Temple --</option>
                    <?php while ($temple = $temples->fetch_assoc()): ?>
                    <option value="<?php echo $temple['id']; ?>">
                        <?php echo htmlspecialchars($temple['temple_name']); ?> (<?php echo $temple['temple_code']; ?>)
                    </option>
                    <?php endwhile; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" required autofocus>
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            
            <button type="submit" class="btn-login">Login</button>
        </form>
        
        <div class="super-admin-link">
            <a href="javascript:void(0)" onclick="toggleSuperAdmin()">Super Admin Login</a>
        </div>
        
        <div class="info">
            <strong>Default Temple Admin Login:</strong><br>
            Username: [temple_code]_admin<br>
            Password: admin123<br><br>
            <strong>Example:</strong><br>
            For TEMP001: username = temp001_admin
        </div>
    </div>
    
    <script>
        function toggleSuperAdmin() {
            const templeSelect = document.getElementById('templeSelectGroup');
            const temple_id = document.getElementById('temple_id');
            
            if (templeSelect.style.display === 'none') {
                templeSelect.style.display = 'block';
                temple_id.required = true;
            } else {
                templeSelect.style.display = 'none';
                temple_id.required = false;
                temple_id.value = '0';
                
                // Show hint for super admin
                alert('Super Admin Login:\nUsername: superadmin\nPassword: admin123');
            }
        }
    </script>
</body>
</html>