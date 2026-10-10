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
    
    if ($action === 'add_category') {
        $name = sanitizeInput($_POST['category_name']);
        $description = sanitizeInput($_POST['description']);
        
        $query = "INSERT INTO expense_categories (temple_id, category_name, description) 
                  VALUES ($temple_id, '$name', '$description')";
        
        if ($conn->query($query)) {
            logAudit('Category Created', 'expense_categories', $conn->insert_id);
            $message = "Category added successfully!";
            $messageType = 'success';
        } else {
            $message = "Error: " . $conn->error;
            $messageType = 'error';
        }
    }
    
    elseif ($action === 'update_category') {
        $id = intval($_POST['id']);
        $name = sanitizeInput($_POST['category_name']);
        $description = sanitizeInput($_POST['description']);
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        $query = "UPDATE expense_categories 
                  SET category_name='$name', description='$description', is_active=$is_active 
                  WHERE id=$id AND temple_id=$temple_id";
        
        if ($conn->query($query)) {
            logAudit('Category Updated', 'expense_categories', $id);
            $message = "Category updated successfully!";
            $messageType = 'success';
        }
    }
    
    elseif ($action === 'delete_category') {
        $id = intval($_POST['id']);
        
        // Check if category has expenses
        $checkQuery = "SELECT COUNT(*) as count FROM expenses WHERE category_id = $id";
        $checkResult = $conn->query($checkQuery);
        $check = $checkResult->fetch_assoc();
        
        if ($check['count'] > 0) {
            $message = "Cannot delete! This category has {$check['count']} expenses.";
            $messageType = 'error';
        } else {
            $query = "DELETE FROM expense_categories WHERE id=$id AND temple_id=$temple_id";
            if ($conn->query($query)) {
                logAudit('Category Deleted', 'expense_categories', $id);
                $message = "Category deleted successfully!";
                $messageType = 'success';
            }
        }
    }
    
    // Subcategory Actions
    elseif ($action === 'add_subcategory') {
        $category_id = intval($_POST['category_id']);
        $name = sanitizeInput($_POST['subcategory_name']);
        $description = sanitizeInput($_POST['sub_description']);
        
        $query = "INSERT INTO expense_subcategories (temple_id, category_id, subcategory_name, description) 
                  VALUES ($temple_id, $category_id, '$name', '$description')";
        
        if ($conn->query($query)) {
            logAudit('Subcategory Created', 'expense_subcategories', $conn->insert_id);
            $message = "Subcategory added successfully!";
            $messageType = 'success';
        }
    }
    
    elseif ($action === 'update_subcategory') {
        $id = intval($_POST['id']);
        $name = sanitizeInput($_POST['subcategory_name']);
        $description = sanitizeInput($_POST['sub_description']);
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        $query = "UPDATE expense_subcategories 
                  SET subcategory_name='$name', description='$description', is_active=$is_active 
                  WHERE id=$id AND temple_id=$temple_id";
        
        if ($conn->query($query)) {
            logAudit('Subcategory Updated', 'expense_subcategories', $id);
            $message = "Subcategory updated successfully!";
            $messageType = 'success';
        }
    }
    
    elseif ($action === 'delete_subcategory') {
        $id = intval($_POST['id']);
        
        $checkQuery = "SELECT COUNT(*) as count FROM expenses WHERE subcategory_id = $id";
        $checkResult = $conn->query($checkQuery);
        $check = $checkResult->fetch_assoc();
        
        if ($check['count'] > 0) {
            $message = "Cannot delete! This subcategory has {$check['count']} expenses.";
            $messageType = 'error';
        } else {
            $query = "DELETE FROM expense_subcategories WHERE id=$id AND temple_id=$temple_id";
            if ($conn->query($query)) {
                logAudit('Subcategory Deleted', 'expense_subcategories', $id);
                $message = "Subcategory deleted successfully!";
                $messageType = 'success';
            }
        }
    }
}

// Get all categories with expense count
$categoriesQuery = "SELECT c.*, 
    (SELECT COUNT(*) FROM expenses WHERE category_id = c.id) as expense_count,
    (SELECT COUNT(*) FROM expense_subcategories WHERE category_id = c.id) as subcategory_count
    FROM expense_categories c
    WHERE c.temple_id = $temple_id
    ORDER BY c.category_name";
$categories = $conn->query($categoriesQuery);

// Get all subcategories
$subcategoriesQuery = "SELECT s.*, c.category_name,
    (SELECT COUNT(*) FROM expenses WHERE subcategory_id = s.id) as expense_count
    FROM expense_subcategories s
    JOIN expense_categories c ON s.category_id = c.id
    WHERE s.temple_id = $temple_id
    ORDER BY c.category_name, s.subcategory_name";
$subcategories = $conn->query($subcategoriesQuery);

// Get categories for dropdown
$catDropdown = "SELECT * FROM expense_categories WHERE temple_id = $temple_id AND is_active = 1 ORDER BY category_name";
$catOptions = $conn->query($catDropdown);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Expense Categories - <?php echo htmlspecialchars($templeInfo['temple_name']); ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f5f5f5; }
        .header { background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%); color: white; padding: 20px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .header h1 { font-size: 24px; }
        .nav { background: white; padding: 0; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .nav ul { list-style: none; display: flex; }
        .nav li { flex: 1; }
        .nav a { display: block; padding: 15px 20px; text-decoration: none; color: #333; text-align: center; border-right: 1px solid #eee; transition: background 0.3s; }
        .nav a:hover, .nav a.active { background: #e74c3c; color: white; }
        .container { max-width: 1200px; margin: 20px auto; padding: 0 20px; }
        .tabs { display: flex; gap: 10px; margin-bottom: 20px; }
        .tab-btn { padding: 12px 25px; background: white; border: none; border-radius: 5px 5px 0 0; cursor: pointer; font-size: 15px; font-weight: 500; transition: all 0.3s; }
        .tab-btn.active { background: #e74c3c; color: white; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        .card { background: white; border-radius: 10px; padding: 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .card h2 { margin-bottom: 20px; color: #333; border-bottom: 2px solid #e74c3c; padding-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #eee; }
        th { background: #f8f9fa; font-weight: 600; color: #333; }
        .btn { padding: 8px 16px; border: none; border-radius: 5px; cursor: pointer; font-size: 13px; transition: background 0.3s; text-decoration: none; display: inline-block; }
        .btn-primary { background: #e74c3c; color: white; }
        .btn-success { background: #27ae60; color: white; }
        .btn-secondary { background: #6c757d; color: white; }
        .btn-danger { background: #dc3545; color: white; }
        .message { padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        .message.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .message.error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; }
        .modal.active { display: flex; align-items: center; justify-content: center; }
        .modal-content { background: white; padding: 30px; border-radius: 10px; max-width: 500px; width: 90%; max-height: 80vh; overflow-y: auto; }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .close-modal { cursor: pointer; font-size: 24px; color: #666; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: 500; color: #333; }
        input, select, textarea { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; font-size: 14px; }
        .status-badge { padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: 500; }
        .status-active { background: #d4edda; color: #155724; }
        .status-inactive { background: #f8d7da; color: #721c24; }
        .checkbox-group { display: flex; align-items: center; gap: 10px; }
        .checkbox-group input[type="checkbox"] { width: auto; }
    </style>
</head>
<body>
<div id="google_translate_element"></div>
    <div class="header">
        <h1><?php echo htmlspecialchars($templeInfo['temple_name']); ?> - Expense Categories</h1>
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
        
        <div class="tabs">
            <button class="tab-btn active" onclick="switchTab('categories')">Categories</button>
            <button class="tab-btn" onclick="switchTab('subcategories')">Sub-Categories</button>
        </div>
        
        <!-- Categories Tab -->
        <div id="categories-tab" class="tab-content active">
            <div class="card">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h2 style="margin: 0;">Expense Categories</h2>
                    <button class="btn btn-primary" onclick="openModal('addCategoryModal')">+ Add Category</button>
                </div>
                
                <table>
                    <thead>
                        <tr>
                            <th>Category Name</th>
                            <th>Description</th>
                            <th>Sub-Categories</th>
                            <th>Expenses</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($cat = $categories->fetch_assoc()): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($cat['category_name']); ?></strong></td>
                            <td><?php echo htmlspecialchars(substr($cat['description'], 0, 50)); ?></td>
                            <td><?php echo $cat['subcategory_count']; ?></td>
                            <td><?php echo $cat['expense_count']; ?></td>
                            <td>
                                <span class="status-badge <?php echo $cat['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                                    <?php echo $cat['is_active'] ? 'Active' : 'Inactive'; ?>
                                </span>
                            </td>
                            <td>
                                <button class="btn btn-secondary" onclick='editCategory(<?php echo json_encode($cat); ?>)'>Edit</button>
                                <button class="btn btn-danger" onclick="deleteCategory(<?php echo $cat['id']; ?>, '<?php echo addslashes($cat['category_name']); ?>')">Delete</button>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Subcategories Tab -->
        <div id="subcategories-tab" class="tab-content">
            <div class="card">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h2 style="margin: 0;">Sub-Categories</h2>
                    <button class="btn btn-primary" onclick="openModal('addSubcategoryModal')">+ Add Sub-Category</button>
                </div>
                
                <table>
                    <thead>
                        <tr>
                            <th>Category</th>
                            <th>Sub-Category Name</th>
                            <th>Description</th>
                            <th>Expenses</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($sub = $subcategories->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($sub['category_name']); ?></td>
                            <td><strong><?php echo htmlspecialchars($sub['subcategory_name']); ?></strong></td>
                            <td><?php echo htmlspecialchars(substr($sub['description'], 0, 40)); ?></td>
                            <td><?php echo $sub['expense_count']; ?></td>
                            <td>
                                <span class="status-badge <?php echo $sub['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                                    <?php echo $sub['is_active'] ? 'Active' : 'Inactive'; ?>
                                </span>
                            </td>
                            <td>
                                <button class="btn btn-secondary" onclick='editSubcategory(<?php echo json_encode($sub); ?>)'>Edit</button>
                                <button class="btn btn-danger" onclick="deleteSubcategory(<?php echo $sub['id']; ?>, '<?php echo addslashes($sub['subcategory_name']); ?>')">Delete</button>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Modals -->
    <div id="addCategoryModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Add Category</h2>
                <span class="close-modal" onclick="closeModal('addCategoryModal')">&times;</span>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="add_category">
                <div class="form-group">
                    <label>Category Name *</label>
                    <input type="text" name="category_name" required>
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" rows="3"></textarea>
                </div>
                <div style="text-align: right; margin-top: 20px;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('addCategoryModal')">Cancel</button>
                    <button type="submit" class="btn btn-success">Add</button>
                </div>
            </form>
        </div>
    </div>
    
    <div id="editCategoryModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Edit Category</h2>
                <span class="close-modal" onclick="closeModal('editCategoryModal')">&times;</span>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="update_category">
                <input type="hidden" name="id" id="edit_cat_id">
                <div class="form-group">
                    <label>Category Name *</label>
                    <input type="text" name="category_name" id="edit_cat_name" required>
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" id="edit_cat_desc" rows="3"></textarea>
                </div>
                <div class="form-group checkbox-group">
                    <input type="checkbox" name="is_active" id="edit_cat_active" value="1">
                    <label style="margin: 0;">Active</label>
                </div>
                <div style="text-align: right; margin-top: 20px;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('editCategoryModal')">Cancel</button>
                    <button type="submit" class="btn btn-success">Update</button>
                </div>
            </form>
        </div>
    </div>
    
    <div id="addSubcategoryModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Add Sub-Category</h2>
                <span class="close-modal" onclick="closeModal('addSubcategoryModal')">&times;</span>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="add_subcategory">
                <div class="form-group">
                    <label>Category *</label>
                    <select name="category_id" required>
                        <option value="">Select Category</option>
                        <?php while ($opt = $catOptions->fetch_assoc()): ?>
                        <option value="<?php echo $opt['id']; ?>"><?php echo htmlspecialchars($opt['category_name']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Sub-Category Name *</label>
                    <input type="text" name="subcategory_name" required>
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="sub_description" rows="3"></textarea>
                </div>
                <div style="text-align: right; margin-top: 20px;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('addSubcategoryModal')">Cancel</button>
                    <button type="submit" class="btn btn-success">Add</button>
                </div>
            </form>
        </div>
    </div>
    
    <div id="editSubcategoryModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Edit Sub-Category</h2>
                <span class="close-modal" onclick="closeModal('editSubcategoryModal')">&times;</span>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="update_subcategory">
                <input type="hidden" name="id" id="edit_sub_id">
                <div class="form-group">
                    <label>Sub-Category Name *</label>
                    <input type="text" name="subcategory_name" id="edit_sub_name" required>
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="sub_description" id="edit_sub_desc" rows="3"></textarea>
                </div>
                <div class="form-group checkbox-group">
                    <input type="checkbox" name="is_active" id="edit_sub_active" value="1">
                    <label style="margin: 0;">Active</label>
                </div>
                <div style="text-align: right; margin-top: 20px;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('editSubcategoryModal')">Cancel</button>
                    <button type="submit" class="btn btn-success">Update</button>
                </div>
            </form>
        </div>
    </div>
    
    <form id="deleteForm" method="POST" style="display: none;">
        <input type="hidden" name="action" id="delete_action">
        <input type="hidden" name="id" id="delete_id">
    </form>
    
    <script>
        function switchTab(tab) {
            document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
            document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
            document.getElementById(tab + '-tab').classList.add('active');
            event.target.classList.add('active');
        }
        
        function openModal(modalId) {
            document.getElementById(modalId).classList.add('active');
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
        }
        
        function editCategory(data) {
            document.getElementById('edit_cat_id').value = data.id;
            document.getElementById('edit_cat_name').value = data.category_name;
            document.getElementById('edit_cat_desc').value = data.description || '';
            document.getElementById('edit_cat_active').checked = data.is_active == 1;
            openModal('editCategoryModal');
        }
        
        function deleteCategory(id, name) {
            if (confirm('Delete category "' + name + '"?')) {
                document.getElementById('delete_action').value = 'delete_category';
                document.getElementById('delete_id').value = id;
                document.getElementById('deleteForm').submit();
            }
        }
        
        function editSubcategory(data) {
            document.getElementById('edit_sub_id').value = data.id;
            document.getElementById('edit_sub_name').value = data.subcategory_name;
            document.getElementById('edit_sub_desc').value = data.description || '';
            document.getElementById('edit_sub_active').checked = data.is_active == 1;
            openModal('editSubcategoryModal');
        }
        
        function deleteSubcategory(id, name) {
            if (confirm('Delete sub-category "' + name + '"?')) {
                document.getElementById('delete_action').value = 'delete_subcategory';
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
