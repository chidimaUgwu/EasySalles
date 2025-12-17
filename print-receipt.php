<?php
// print-receipt.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require 'includes/auth.php';
require_login();
require_staff();

require 'config/db.php';

// Get sale ID from URL
$sale_id = $_GET['id'] ?? 0;
$sale_id = (int)$sale_id;

if (!$sale_id) {
    die('Invalid sale ID');
}

// Get sale details
$sql = "SELECT s.*, u.full_name as staff_name
        FROM EASYSALLES_SALES s
        LEFT JOIN EASYSALLES_USERS u ON s.staff_id = u.user_id
        WHERE s.sale_id = ?";
        
// Add security check for staff
if (isset($_SESSION['role']) && $_SESSION['role'] == 2) {
    $sql .= " AND s.staff_id = ?";
    $params = [$sale_id, $_SESSION['user_id']];
} else {
    $params = [$sale_id];
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$sale = $stmt->fetch();

if (!$sale) {
    die('Sale not found or you don\'t have permission to view it.');
}

// Get sale items
$items_sql = "SELECT si.*, p.product_name, p.product_code, p.unit_type
              FROM EASYSALLES_SALE_ITEMS si
              JOIN EASYSALLES_PRODUCTS p ON si.product_id = p.product_id
              WHERE si.sale_id = ?
              ORDER BY si.item_id";
$items_stmt = $pdo->prepare($items_sql);
$items_stmt->execute([$sale_id]);
$items = $items_stmt->fetchAll();

// Store information
$store_name = "EASYSALLES STORE";
$store_address = "123 Business Street, Sales City";
$store_phone = "+1 (555) 123-4567";
$store_email = "support@easysalles.com";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print Receipt - <?php echo $sale['transaction_code']; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Courier New', monospace;
            font-size: 14px;
            line-height: 1.4;
            color: #000;
            background: #fff;
            padding: 20px;
        }
        
        .receipt-container {
            max-width: 300px;
            margin: 0 auto;
            border: 2px solid #000;
            padding: 20px;
        }
        
        .receipt-header {
            text-align: center;
            margin-bottom: 15px;
            border-bottom: 1px dashed #000;
            padding-bottom: 10px;
        }
        
        .store-name {
            font-size: 18px;
            font-weight: bold;
            text-transform: uppercase;
            margin-bottom: 5px;
        }
        
        .store-info {
            font-size: 11px;
            color: #666;
            margin-bottom: 5px;
        }
        
        .receipt-title {
            font-size: 16px;
            font-weight: bold;
            margin: 10px 0;
        }
        
        .transaction-info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            font-size: 12px;
        }
        
        .customer-info {
            margin-bottom: 15px;
            border-bottom: 1px dashed #000;
            padding-bottom: 10px;
        }
        
        .customer-name {
            font-weight: bold;
            margin-bottom: 3px;
        }
        
        .customer-contact {
            font-size: 11px;
            color: #666;
        }
        
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        
        .items-table th {
            text-align: left;
            padding: 5px 0;
            border-bottom: 1px dashed #000;
            font-weight: bold;
        }
        
        .items-table td {
            padding: 4px 0;
            border-bottom: 1px dashed #ddd;
        }
        
        .item-quantity {
            text-align: center;
            width: 40px;
        }
        
        .item-name {
            max-width: 120px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        .item-price, .item-total {
            text-align: right;
            width: 60px;
        }
        
        .totals-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        
        .totals-table td {
            padding: 5px 0;
        }
        
        .total-label {
            text-align: right;
            padding-right: 10px;
        }
        
        .total-value {
            text-align: right;
            font-weight: bold;
        }
        
        .grand-total {
            border-top: 2px solid #000;
            font-size: 16px;
            font-weight: bold;
        }
        
        .payment-info {
            margin-bottom: 15px;
            border-bottom: 1px dashed #000;
            padding-bottom: 10px;
        }
        
        .payment-method {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
        }
        
        .payment-status {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 10px;
            font-weight: bold;
        }
        
        .status-paid {
            background: #10B981;
            color: white;
        }
        
        .status-pending {
            background: #F59E0B;
            color: white;
        }
        
        .status-cancelled {
            background: #EF4444;
            color: white;
        }
        
        .receipt-footer {
            text-align: center;
            margin-top: 15px;
            border-top: 1px dashed #000;
            padding-top: 10px;
            font-size: 11px;
            color: #666;
        }
        
        .thank-you {
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .staff-info {
            margin-bottom: 5px;
        }
        
        .barcode {
            text-align: center;
            margin: 10px 0;
            font-family: 'Libre Barcode 128', cursive;
            font-size: 30px;
        }
        
        .print-date {
            font-size: 11px;
            color: #666;
            text-align: center;
            margin-bottom: 10px;
        }
        
        @media print {
            body {
                padding: 0;
            }
            
            .receipt-container {
                border: none;
                padding: 10px;
                max-width: 100%;
            }
            
            .no-print {
                display: none !important;
            }
        }
        
        .print-actions {
            text-align: center;
            margin: 20px 0;
        }
        
        .print-btn {
            background: #4F46E5;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            font-size: 14px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .print-btn:hover {
            background: #4338CA;
        }
        
        .back-btn {
            background: #6B7280;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            font-size: 14px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            margin-left: 10px;
        }
        
        .back-btn:hover {
            background: #4B5563;
        }
    </style>
</head>
<body>
    <div class="print-actions no-print">
        <button class="print-btn" onclick="window.print()">
            <i class="fas fa-print"></i> Print Receipt
        </button>
        <a href="sale-details.php?id=<?php echo $sale_id; ?>" class="back-btn">
            <i class="fas fa-arrow-left"></i> Back to Details
        </a>
    </div>
    
    <div class="print-date no-print">
        Printed on <?php echo date('M j, Y h:i A'); ?>
    </div>
    
    <div class="receipt-container">
        <div class="receipt-header">
            <div class="store-name"><?php echo $store_name; ?></div>
            <div class="store-info"><?php echo $store_address; ?></div>
            <div class="store-info">Tel: <?php echo $store_phone; ?></div>
            <div class="store-info">Email: <?php echo $store_email; ?></div>
            
            <div class="receipt-title">SALES RECEIPT</div>
        </div>
        
        <div class="transaction-info">
            <div>
                <strong>Transaction:</strong><br>
                <?php echo $sale['transaction_code']; ?>
            </div>
            <div style="text-align: right;">
                <strong>Date:</strong><br>
                <?php echo date('M j, Y', strtotime($sale['sale_date'])); ?><br>
                <?php echo date('h:i A', strtotime($sale['sale_date'])); ?>
            </div>
        </div>
        
        <div class="customer-info">
            <div class="customer-name">Customer: <?php echo htmlspecialchars($sale['customer_name']); ?></div>
            <?php if ($sale['customer_phone']): ?>
                <div class="customer-contact">Phone: <?php echo htmlspecialchars($sale['customer_phone']); ?></div>
            <?php endif; ?>
            <?php if ($sale['customer_email']): ?>
                <div class="customer-contact">Email: <?php echo htmlspecialchars($sale['customer_email']); ?></div>
            <?php endif; ?>
        </div>
        
        <table class="items-table">
            <thead>
                <tr>
                    <th class="item-quantity">Qty</th>
                    <th class="item-name">Item</th>
                    <th class="item-price">Price</th>
                    <th class="item-total">Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                    <tr>
                        <td class="item-quantity"><?php echo $item['quantity']; ?></td>
                        <td class="item-name">
                            <?php echo htmlspecialchars($item['product_name']); ?>
                            <br><small><?php echo htmlspecialchars($item['product_code']); ?></small>
                        </td>
                        <td class="item-price">$<?php echo number_format($item['unit_price'], 2); ?></td>
                        <td class="item-total">$<?php echo number_format($item['subtotal'], 2); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <table class="totals-table">
            <tr>
                <td class="total-label">Subtotal:</td>
                <td class="total-value">$<?php echo number_format($sale['subtotal_amount'], 2); ?></td>
            </tr>
            
            <?php if ($sale['discount_amount'] > 0): ?>
            <tr>
                <td class="total-label">Discount:</td>
                <td class="total-value">-$<?php echo number_format($sale['discount_amount'], 2); ?></td>
            </tr>
            <?php endif; ?>
            
            <?php if ($sale['tax_amount'] > 0): ?>
            <tr>
                <td class="total-label">Tax:</td>
                <td class="total-value">$<?php echo number_format($sale['tax_amount'], 2); ?></td>
            </tr>
            <?php endif; ?>
            
            <tr class="grand-total">
                <td class="total-label">TOTAL:</td>
                <td class="total-value">$<?php echo number_format($sale['final_amount'], 2); ?></td>
            </tr>
        </table>
        
        <div class="payment-info">
            <div class="payment-method">
                <span>Payment Method:</span>
                <span>
                    <?php 
                    $method_icons = [
                        'cash' => 'money-bill-wave',
                        'card' => 'credit-card',
                        'mobile_money' => 'mobile-alt',
                        'credit' => 'handshake'
                    ];
                    $method_text = ucfirst(str_replace('_', ' ', $sale['payment_method']));
                    ?>
                    <i class="fas fa-<?php echo $method_icons[$sale['payment_method']] ?? 'money-bill-wave'; ?>"></i>
                    <?php echo $method_text; ?>
                </span>
            </div>
            
            <div style="text-align: center; margin-top: 5px;">
                <span class="payment-status status-<?php echo $sale['payment_status']; ?>">
                    <?php echo strtoupper($sale['payment_status']); ?>
                </span>
            </div>
        </div>
        
        <div class="barcode">
            *<?php echo $sale['transaction_code']; ?>*
        </div>
        
        <div class="receipt-footer">
            <div class="staff-info">
                Served by: <?php echo htmlspecialchars($sale['staff_name'] ?? 'Staff'); ?>
            </div>
            
            <div class="thank-you">THANK YOU FOR YOUR BUSINESS!</div>
            
            <div>
                Returns accepted within 7 days with original receipt<br>
                All sales are final on clearance items
            </div>
        </div>
    </div>
    
    <div class="print-actions no-print">
        <button class="print-btn" onclick="window.print()">
            <i class="fas fa-print"></i> Print Receipt
        </button>
        <a href="sale-details.php?id=<?php echo $sale_id; ?>" class="back-btn">
            <i class="fas fa-arrow-left"></i> Back to Details
        </a>
    </div>
    
    <script>
        // Auto-print if print parameter is set
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('print') === 'true') {
            window.print();
        }
        
        // Auto-close after printing
        window.onafterprint = function() {
            if (urlParams.get('autoclose') === 'true') {
                setTimeout(function() {
                    window.close();
                }, 500);
            }
        };
    </script>
</body>
</html>