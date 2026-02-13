<?php
// print_invoice.php - (Internal Use Only - Requires Login)
require 'auth.php'; 
require 'config.php';

$id = intval($_GET['id']);
$res = $conn->query("SELECT i.*, c.name as client_name, c.phone as client_phone, c.address as client_address FROM invoices i JOIN clients c ON i.client_id=c.id WHERE i.id=$id");

if(!$res || $res->num_rows==0) die("الفاتورة غير موجودة.");
$inv = $res->fetch_assoc();
$items = json_decode($inv['items_json'], true);
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <title>فاتورة #<?php echo $id; ?></title>
    <style>
        body { font-family: 'Tahoma', sans-serif; padding: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: center; }
        .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #000; padding-bottom: 10px; }
        @media print { button { display: none; } }
    </style>
</head>
<body onload="window.print()">
    <div class="header">
        <h2>Arab Eagles</h2>
        <p>فاتورة رقم <?php echo $id; ?></p>
    </div>
    
    <div style="display:flex; justify-content:space-between;">
        <div><strong>العميل:</strong> <?php echo $inv['client_name']; ?></div>
        <div><strong>التاريخ:</strong> <?php echo $inv['inv_date']; ?></div>
    </div>

    <table>
        <thead><tr><th>م</th><th>الصنف</th><th>الكمية</th><th>السعر</th><th>الإجمالي</th></tr></thead>
        <tbody>
            <?php $i=1; foreach($items as $item): ?>
            <tr>
                <td><?php echo $i++; ?></td>
                <td><?php echo $item['desc']; ?></td>
                <td><?php echo $item['qty']; ?></td>
                <td><?php echo $item['price']; ?></td>
                <td><?php echo $item['total']; ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <h3 style="text-align:left; margin-top:20px;">
        الإجمالي: <?php echo number_format($inv['total_amount'], 2); ?> EGP
    </h3>
</body>
</html>