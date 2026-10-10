<?php
require_once 'config.php';
requireLogin();

$templeInfo = getCurrentTempleInfo();
$temple_id = getCurrentTempleId();
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Get expense details
$query = "SELECT e.*, 
    ec.category_name,
    es.subcategory_name,
    v.vendor_name, v.phone as vendor_phone, v.email as vendor_email, v.gst_number,
    u.full_name as created_by_name,
    a.full_name as approved_by_name
    FROM expenses e
    JOIN expense_categories ec ON e.category_id = ec.id
    LEFT JOIN expense_subcategories es ON e.subcategory_id = es.id
    LEFT JOIN vendors v ON e.vendor_id = v.id
    LEFT JOIN users u ON e.created_by = u.id
    LEFT JOIN users a ON e.approved_by = a.id
    WHERE e.id = $id AND e.temple_id = $temple_id";

$result = $conn->query($query);

if ($result->num_rows === 0) {
    die("Expense not found");
}

$expense = $result->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Expense Details - <?php echo $expense['expense_no']; ?></title>
    <style>
        @media print {
            .no-print { display: none; }
            body { margin: 0; }
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f5f5f5; }
        .header { background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%); color: white; padding: 20px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .header h1 { font-size: 24px; }
        .container { max-width: 900px; margin: 30px auto; padding: 0 20px; }
        .card { background: white; border-radius: 10px; padding: 40px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .expense-header { text-align: center; border-bottom: 3px solid #e74c3c; padding-bottom: 20px; margin-bottom: 30px; }
        .expense-header h1 { font-size: 28px; color: #333; margin-bottom: 10px; }
        .expense-header .expense-no { font-size: 20px; color: #e74c3c; font-weight: bold; }
        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-bottom: 30px; }
        .info-section h3 { color: #e74c3c; font-size: 16px; margin-bottom: 15px; border-bottom: 2px solid #ecf0f1; padding-bottom: 8px; }
        .info-row { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #ecf0f1; }
        .info-label { font-weight: 600; color: #555; }
        .info-value { color: #333; text-align: right; }
        .amount-section { background: linear-gradient(135deg, #fff5f5 0%, #ffe5e5 100%); padding: 30px; border-radius: 10px; text-align: center; margin: 30px 0; }
        .amount-section h3 { color: #666; font-size: 14px; margin-bottom: 10px; }
        .amount-section .amount { font-size: 48px; font-weight: bold; color: #e74c3c; }
        .status-badge { padding: 6px 15px; border-radius: 20px; font-size: 13px; font-weight: 500; display: inline-block; }
        .status-paid { background: #d4edda; color: #155724; }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-partial { background: #d1ecf1; color: #0c5460; }
        .status-cancelled { background: #f8d7da; color: #721c24; }
        .description-box { background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0; }
        .description-box h3 { color: #555; font-size: 15px; margin-bottom: 10px; }
        .description-box p { color: #666; line-height: 1.6; }
        .btn { padding: 12px 25px; border: none; border-radius: 5px; cursor: pointer; font-size: 14px; text-decoration: none; display: inline-block; margin: 5px; transition: background 0.3s; }
        .btn-primary { background: #e74c3c; color: white; }
        .btn-primary:hover { background: #c0392b; }
        .btn-secondary { background: #6c757d; color: white; }
        .btn-secondary:hover { background: #5a6268; }
        .action-buttons { text-align: center; margin-top: 30px; padding-top: 30px; border-top: 2px solid #ecf0f1; }
        .vendor-details { background: #f0f8ff; padding: 15px; border-radius: 8px; margin-top: 15px; }
        .vendor-details p { margin: 5px 0; font-size: 14px; color: #555; }
        .timeline { margin-top: 30px; }
        .timeline h3 { color: #555; font-size: 16px; margin-bottom: 15px; }
        .timeline-item { padding: 12px 0; border-left: 3px solid #e74c3c; padding-left: 20px; margin-left: 10px; }
        .timeline-item .date { font-weight: 600; color: #e74c3c; font-size: 13px; }
        .timeline-item .event { color: #666; margin-top: 5px; font-size: 14px; }
    </style>
</head>
<body>
<div id="google_translate_element"></div>
    <div class="header no-print">
        <h1><?php echo htmlspecialchars($templeInfo['temple_name']); ?> - Expense Details</h1>
    </div>
    
    <div class="container">
        <div class="card">
            <div class="expense-header">
                <h1>Expense Voucher</h1>
                <div class="expense-no"><?php echo $expense['expense_no']; ?></div>
                <div style="margin-top: 10px;">
                    <span class="status-badge status-<?php echo strtolower($expense['payment_status']); ?>">
                        <?php echo $expense['payment_status']; ?>
                    </span>
                </div>
            </div>
            
            <div class="info-grid">
                <div class="info-section">
                    <h3>Expense Information</h3>
                    <div class="info-row">
                        <span class="info-label">Expense Date</span>
                        <span class="info-value"><?php echo date('d F Y', strtotime($expense['expense_date'])); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Category</span>
                        <span class="info-value"><?php echo htmlspecialchars($expense['category_name']); ?></span>
                    </div>
                    <?php if ($expense['subcategory_name']): ?>
                    <div class="info-row">
                        <span class="info-label">Sub-Category</span>
                        <span class="info-value"><?php echo htmlspecialchars($expense['subcategory_name']); ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="info-row">
                        <span class="info-label">Created By</span>
                        <span class="info-value"><?php echo htmlspecialchars($expense['created_by_name']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Created On</span>
                        <span class="info-value"><?php echo date('d/m/Y h:i A', strtotime($expense['created_at'])); ?></span>
                    </div>
                </div>
                
                <div class="info-section">
                    <h3>Payment Information</h3>
                    <div class="info-row">
                        <span class="info-label">Payment Mode</span>
                        <span class="info-value"><?php echo $expense['payment_mode']; ?></span>
                    </div>
                    <?php if ($expense['payment_reference']): ?>
                    <div class="info-row">
                        <span class="info-label">Reference</span>
                        <span class="info-value"><?php echo htmlspecialchars($expense['payment_reference']); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ($expense['bill_number']): ?>
                    <div class="info-row">
                        <span class="info-label">Bill Number</span>
                        <span class="info-value"><?php echo htmlspecialchars($expense['bill_number']); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ($expense['bill_date']): ?>
                    <div class="info-row">
                        <span class="info-label">Bill Date</span>
                        <span class="info-value"><?php echo date('d/m/Y', strtotime($expense['bill_date'])); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ($expense['approved_by_name']): ?>
                    <div class="info-row">
                        <span class="info-label">Approved By</span>
                        <span class="info-value"><?php echo htmlspecialchars($expense['approved_by_name']); ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if ($expense['vendor_name']): ?>
            <div class="info-section">
                <h3>Vendor Details</h3>
                <div class="vendor-details">
                    <p><strong>Name:</strong> <?php echo htmlspecialchars($expense['vendor_name']); ?></p>
                    <?php if ($expense['vendor_phone']): ?>
                    <p><strong>Phone:</strong> <?php echo htmlspecialchars($expense['vendor_phone']); ?></p>
                    <?php endif; ?>
                    <?php if ($expense['vendor_email']): ?>
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($expense['vendor_email']); ?></p>
                    <?php endif; ?>
                    <?php if ($expense['gst_number']): ?>
                    <p><strong>GST:</strong> <?php echo htmlspecialchars($expense['gst_number']); ?></p>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="description-box">
                <h3>Description</h3>
                <p><?php echo nl2br(htmlspecialchars($expense['description'])); ?></p>
            </div>
            
            <div class="amount-section">
                <h3>Amount Breakdown</h3>
                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px; margin-top: 20px; text-align: left;">
                    <div>
                        <div style="font-size: 13px; color: #666;">Base Amount</div>
                        <div style="font-size: 24px; font-weight: bold; color: #555;"><?php echo formatCurrency($expense['amount']); ?></div>
                    </div>
                    <div>
                        <div style="font-size: 13px; color: #666;">GST Amount</div>
                        <div style="font-size: 24px; font-weight: bold; color: #555;"><?php echo formatCurrency($expense['gst_amount']); ?></div>
                    </div>
                    <div>
                        <div style="font-size: 13px; color: #666;">Total Amount</div>
                        <div class="amount"><?php echo formatCurrency($expense['total_amount']); ?></div>
                    </div>
                </div>
                
                <?php if ($expense['payment_status'] === 'Partial'): ?>
                <div style="margin-top: 20px; padding-top: 20px; border-top: 2px dashed #ccc;">
                    <div style="font-size: 13px; color: #666;">Paid Amount</div>
                    <div style="font-size: 28px; font-weight: bold; color: #27ae60;"><?php echo formatCurrency($expense['paid_amount']); ?></div>
                    <div style="font-size: 13px; color: #666; margin-top: 5px;">
                        Balance: <?php echo formatCurrency($expense['total_amount'] - $expense['paid_amount']); ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <?php if ($expense['remarks']): ?>
            <div class="description-box">
                <h3>Remarks</h3>
                <p><?php echo nl2br(htmlspecialchars($expense['remarks'])); ?></p>
            </div>
            <?php endif; ?>
            
            <div class="action-buttons no-print">
                <button onclick="window.print()" class="btn btn-primary">🖨️ Print</button>
                <a href="expenses.php" class="btn btn-secondary">← Back to Expenses</a>
                <?php if (isTempleAdmin()): ?>
                <a href="edit_expense.php?id=<?php echo $expense['id']; ?>" class="btn btn-secondary">✏️ Edit</a>
                <?php endif; ?>
            </div>
        </div>
    </div>

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
