<?php
require_once 'config.php';
requireLogin();

if (!isTempleAdmin()) {
    header('Location: expenses.php');
    exit();
}

$templeInfo = getCurrentTempleInfo();
$temple_id = getCurrentTempleId();

$message = '';
$messageType = '';

// Handle Actions
if (isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'add_vendor') {
        $vendor_name = sanitizeInput($_POST['vendor_name']);
        $contact_person = sanitizeInput($_POST['contact_person']);
        $phone = sanitizeInput($_POST['phone']);
        $email = sanitizeInput($_POST['email']);
        $address = sanitizeInput($_POST['address']);
        $gst_number = sanitizeInput($_POST['gst_number']);
        $pan_number = sanitizeInput($_POST['pan_number']);
        $bank_name = sanitizeInput($_POST['bank_name']);
        $account_number = sanitizeInput($_POST['account_number']);
        $ifsc_code = sanitizeInput($_POST['ifsc_code']);
        
        // Generate vendor code
        $prefix = 'VEN';
        $codeQuery = "SELECT vendor_code FROM vendors WHERE temple_id = $temple_id AND vendor_code LIKE '$prefix%' ORDER BY id DESC LIMIT 1";
        $codeResult = $conn->query($codeQuery);
        
        if ($codeResult && $codeResult->num_rows > 0) {
            $row = $codeResult->fetch_assoc();
            $lastNo = intval(substr($row['vendor_code'], 3));
            $newNo = $lastNo + 1;
        } else {
            $newNo = 1;
        }
        $vendor_code = $prefix . str_pad($newNo, 3, '0', STR_PAD_LEFT);
        
        $query = "INSERT INTO vendors (temple_id, vendor_code, vendor_name, contact_person, phone, email, address, gst_number, pan_number, bank_name, account_number, ifsc_code) 
                  VALUES ($temple_id, '$vendor_code', '$vendor_name', '$contact_person', '$phone', '$email', '$address', '$gst_number', '$pan_number', '$bank_name', '$account_number', '$ifsc_code')";
        
        if ($conn->query($query)) {
            logAudit('Vendor Created', 'vendors', $conn->insert_id);
            $message = "Vendor added successfully! Vendor Code: $vendor_code";
            $messageType = 'success';
        } else {
            $message = "Error: " . $conn->error;
            $messageType = 'error';
        }
    }
    
    elseif ($action === 'update_vendor') {
        $id = intval($_POST['id']);
        $vendor_name = sanitizeInput($_POST['vendor_name']);
        $contact_person = sanitizeInput($_POST['contact_person']);
        $phone = sanitizeInput($_POST['phone']);
        $email = sanitizeInput($_POST['email']);
        $address = sanitizeInput($_POST['address']);
        $gst_number = sanitizeInput($_POST['gst_number']);
        $pan_number = sanitizeInput($_POST['pan_number']);
        $bank_name = sanitizeInput($_POST['bank_name']);
        $account_number = sanitizeInput($_POST['account_number']);
        $ifsc_code = sanitizeInput($_POST['ifsc_code']);
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        $query = "UPDATE vendors SET 
                  vendor_name='$vendor_name', 
                  contact_person='$contact_person',
                  phone='$phone',
                  email='$email',
                  address='$address',
                  gst_number='$gst_number',
                  pan_number='$pan_number',
                  bank_name='$bank_name',
                  account_number='$account_number',
                  ifsc_code='$ifsc_code',
                  is_active=$is_active
                  WHERE id=$id AND temple_id=$temple_id";
        
        if ($conn->query($query)) {
            logAudit('Vendor Updated', 'vendors', $id);
            $message = "Vendor updated successfully!";
            $messageType = 'success';
        }
    }
    
    elseif ($action === 'delete_vendor') {
        $id = intval($_POST['id']);
        
        $checkQuery = "SELECT COUNT(*) as count FROM expenses WHERE vendor_id = $id";
        $checkResult = $conn->query($checkQuery);
        $check = $checkResult->fetch_assoc();
        
        if ($check['count'] > 0) {
            $message = "Cannot delete! This vendor has {$check['count']} expenses.";
            $messageType = 'error';
        } else {
            $query = "DELETE FROM vendors WHERE id=$id AND temple_id=$temple_id";
            if ($conn->query($query)) {
                logAudit('Vendor Deleted', 'vendors', $id);
                $message = "Vendor deleted successfully!";
                $messageType = 'success';
            }
        }
    }
}

// Get all vendors with expense count
$vendorsQuery = "SELECT v.*,
    (SELECT COUNT(*) FROM expenses WHERE vendor_id = v.id) as expense_count,
    (SELECT SUM(total_amount) FROM expenses WHERE vendor_id = v.id) as total_amount
    FROM vendors v
    WHERE v.temple_id = $temple_id
    ORDER BY v.vendor_name";
$vendors = $conn->query($vendorsQuery);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vendors Management - <?php echo htmlspecialchars($templeInfo['temple_name']); ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f5f5f5; }
        .header { background: linear-gradient(135deg, #16a085 0%, #1abc9c 100%); color: white; padding: 20px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .header h1 { font-size: 24px; }
        .nav { background: white; padding: 0; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .nav ul { list-style: none; display: flex; }
        .nav li { flex: 1; }
        .nav a { display: block; padding: 15px 20px; text-decoration: none; color: #333; text-align: center; border-right: 1px solid #eee; transition: background 0.3s; }
        .nav a:hover, .nav a.active { background: #16a085; color: white; }
        .container { max-width: 1400px; margin: 20px auto; padding: 0 20px; }
        .card { background: white; border-radius: 10px; padding: 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .card h2 { margin-bottom: 20px; color: #333; border-bottom: 2px solid #16a085; padding-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #eee; font-size: 14px; }
        th { background: #f8f9fa; font-weight: 600; color: #333; }
        .btn { padding: 8px 16px; border: none; border-radius: 5px; cursor: pointer; font-size: 13px; transition: background 0.3s; text-decoration: none; display: inline-block; }
        .btn-primary { background: #16a085; color: white; }
        .btn-success { background: #27ae60; color: white; }
        .btn-secondary { background: #6c757d; color: white; }
        .btn-danger { background: #dc3545; color: white; }
        .message { padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        .message.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .message.error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; }
        .modal.active { display: flex; align-items: center; justify-content: center; }
        .modal-content { background: white; padding: 30px; border-radius: 10px; max-width: 700px; width: 90%; max-height: 90vh; overflow-y: auto; }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .close-modal { cursor: pointer; font-size: 24px; color: #666; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: 500; color: #333; font-size: 14px; }
        input, textarea { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; font-size: 14px; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        .status-badge { padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: 500; }
        .status-active { background: #d4edda; color: #155724; }
        .status-inactive { background: #f8d7da; color: #721c24; }
        .checkbox-group { display: flex; align-items: center; gap: 10px; }
        .checkbox-group input[type="checkbox"] { width: auto; }
        .vendor-code { background: #e8f5e9; padding: 4px 8px; border-radius: 4px; font-weight: bold; color: #2e7d32; }
        .text-right { text-align: right; }
    </style>
</head>
<body>
<div id="google_translate_element"></div>
    <div class="header">
        <h1><?php echo htmlspecialchars($templeInfo['temple_name']); ?> - Vendors Management</h1>
    </div>
    
    <div class="nav">
        <ul>
            <li><a href="index.php">Dashboard</a></li>
            <li><a href="expenses.php" class="active">Expenses</a></li>
            <li><a href="reports.php">Reports</a></li>
            <li><a href="admin.php">Admin</a></li>
        </ul>
    </div>
    
    <div class="container">
        <?php if ($message): ?>
        <div class="message <?php echo $messageType; ?>">
            <?php echo $message; ?>
        </div>
        <?php endif; ?>
        
        <div class="card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h2 style="margin: 0;">All Vendors / Suppliers</h2>
                <button class="btn btn-primary" onclick="openModal('addVendorModal')">+ Add Vendor</button>
            </div>
            
            <table>
                <thead>
                    <tr>
                        <th>Vendor Code</th>
                        <th>Vendor Name</th>
                        <th>Contact Person</th>
                        <th>Phone</th>
                        <th>GST Number</th>
                        <th>Expenses</th>
                        <th class="text-right">Total Amount</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($vendors->num_rows > 0): ?>
                        <?php while ($vendor = $vendors->fetch_assoc()): ?>
                        <tr>
                            <td><span class="vendor-code"><?php echo $vendor['vendor_code']; ?></span></td>
                            <td><strong><?php echo htmlspecialchars($vendor['vendor_name']); ?></strong></td>
                            <td><?php echo htmlspecialchars($vendor['contact_person'] ?: '-'); ?></td>
                            <td><?php echo htmlspecialchars($vendor['phone']); ?></td>
                            <td><?php echo htmlspecialchars($vendor['gst_number'] ?: '-'); ?></td>
                            <td><?php echo $vendor['expense_count']; ?></td>
                            <td class="text-right"><?php echo formatCurrency($vendor['total_amount'] ?: 0); ?></td>
                            <td>
                                <span class="status-badge <?php echo $vendor['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                                    <?php echo $vendor['is_active'] ? 'Active' : 'Inactive'; ?>
                                </span>
                            </td>
                            <td>
                                <button class="btn btn-secondary" onclick='editVendor(<?php echo json_encode($vendor); ?>)'>Edit</button>
                                <button class="btn btn-danger" onclick="deleteVendor(<?php echo $vendor['id']; ?>, '<?php echo addslashes($vendor['vendor_name']); ?>')">Delete</button>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" style="text-align: center; padding: 30px;">
                                No vendors found. Click "Add Vendor" to add your first vendor.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Add Vendor Modal -->
    <div id="addVendorModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Add New Vendor</h2>
                <span class="close-modal" onclick="closeModal('addVendorModal')">&times;</span>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="add_vendor">
                
                <h3 style="margin-bottom: 15px; color: #16a085;">Basic Information</h3>
                <div class="form-group">
                    <label>Vendor Name *</label>
                    <input type="text" name="vendor_name" required>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Contact Person</label>
                        <input type="text" name="contact_person">
                    </div>
                    <div class="form-group">
                        <label>Phone *</label>
                        <input type="tel" name="phone" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email">
                </div>
                
                <div class="form-group">
                    <label>Address</label>
                    <textarea name="address" rows="2"></textarea>
                </div>
                
                <h3 style="margin: 20px 0 15px; color: #16a085;">Tax Information</h3>
                <div class="form-row">
                    <div class="form-group">
                        <label>GST Number</label>
                        <input type="text" name="gst_number" placeholder="22AAAAA0000A1Z5">
                    </div>
                    <div class="form-group">
                        <label>PAN Number</label>
                        <input type="text" name="pan_number" placeholder="AAAAA0000A">
                    </div>
                </div>
                
                <h3 style="margin: 20px 0 15px; color: #16a085;">Bank Details</h3>
                <div class="form-group">
                    <label>Bank Name</label>
                    <input type="text" name="bank_name">
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Account Number</label>
                        <input type="text" name="account_number">
                    </div>
                    <div class="form-group">
                        <label>IFSC Code</label>
                        <input type="text" name="ifsc_code" placeholder="SBIN0000123">
                    </div>
                </div>
                
                <div style="text-align: right; margin-top: 20px;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('addVendorModal')">Cancel</button>
                    <button type="submit" class="btn btn-success">Add Vendor</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Edit Vendor Modal -->
    <div id="editVendorModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Edit Vendor</h2>
                <span class="close-modal" onclick="closeModal('editVendorModal')">&times;</span>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="update_vendor">
                <input type="hidden" name="id" id="edit_vendor_id">
                
                <div class="form-group">
                    <label>Vendor Code (Cannot be changed)</label>
                    <input type="text" id="edit_vendor_code" disabled>
                </div>
                
                <h3 style="margin-bottom: 15px; color: #16a085;">Basic Information</h3>
                <div class="form-group">
                    <label>Vendor Name *</label>
                    <input type="text" name="vendor_name" id="edit_vendor_name" required>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Contact Person</label>
                        <input type="text" name="contact_person" id="edit_contact_person">
                    </div>
                    <div class="form-group">
                        <label>Phone *</label>
                        <input type="tel" name="phone" id="edit_phone" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" id="edit_email">
                </div>
                
                <div class="form-group">
                    <label>Address</label>
                    <textarea name="address" id="edit_address" rows="2"></textarea>
                </div>
                
                <h3 style="margin: 20px 0 15px; color: #16a085;">Tax Information</h3>
                <div class="form-row">
                    <div class="form-group">
                        <label>GST Number</label>
                        <input type="text" name="gst_number" id="edit_gst">
                    </div>
                    <div class="form-group">
                        <label>PAN Number</label>
                        <input type="text" name="pan_number" id="edit_pan">
                    </div>
                </div>
                
                <h3 style="margin: 20px 0 15px; color: #16a085;">Bank Details</h3>
                <div class="form-group">
                    <label>Bank Name</label>
                    <input type="text" name="bank_name" id="edit_bank_name">
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Account Number</label>
                        <input type="text" name="account_number" id="edit_account_number">
                    </div>
                    <div class="form-group">
                        <label>IFSC Code</label>
                        <input type="text" name="ifsc_code" id="edit_ifsc_code">
                    </div>
                </div>
                
                <div class="form-group checkbox-group" style="margin-top: 15px;">
                    <input type="checkbox" name="is_active" id="edit_vendor_active" value="1">
                    <label style="margin: 0;">Active</label>
                </div>
                
                <div style="text-align: right; margin-top: 20px;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('editVendorModal')">Cancel</button>
                    <button type="submit" class="btn btn-success">Update Vendor</button>
                </div>
            </form>
        </div>
    </div>
    
    <form id="deleteForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="delete_vendor">
        <input type="hidden" name="id" id="delete_id">
    </form>
    
    <script>
        function openModal(modalId) {
            document.getElementById(modalId).classList.add('active');
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
        }
        
        function editVendor(data) {
            document.getElementById('edit_vendor_id').value = data.id;
            document.getElementById('edit_vendor_code').value = data.vendor_code;
            document.getElementById('edit_vendor_name').value = data.vendor_name;
            document.getElementById('edit_contact_person').value = data.contact_person || '';
            document.getElementById('edit_phone').value = data.phone;
            document.getElementById('edit_email').value = data.email || '';
            document.getElementById('edit_address').value = data.address || '';
            document.getElementById('edit_gst').value = data.gst_number || '';
            document.getElementById('edit_pan').value = data.pan_number || '';
            document.getElementById('edit_bank_name').value = data.bank_name || '';
            document.getElementById('edit_account_number').value = data.account_number || '';
            document.getElementById('edit_ifsc_code').value = data.ifsc_code || '';
            document.getElementById('edit_vendor_active').checked = data.is_active == 1;
            openModal('editVendorModal');
        }
        
        function deleteVendor(id, name) {
            if (confirm('Delete vendor "' + name + '"? This cannot be undone.')) {
                document.getElementById('delete_id').value = id;
                document.getElementById('deleteForm').submit();
            }
        }
        
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
