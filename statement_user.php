<?php
// statement_user.php - (Fixed Version)
ob_start();
// تفعيل الأخطاء
ini_set('display_errors', 1);
error_reporting(E_ALL);

require 'auth.php'; 
require 'config.php'; 
require 'header.php';

if(!isset($_GET['user_id']) || empty($_GET['user_id'])){
    die("<div class='container' style='padding:50px; color:red; text-align:center;'>⛔ لم يتم تحديد الموظف.</div>");
}

$user_id = intval($_GET['user_id']);
$date_from = $_GET['from'] ?? date('Y-m-01'); 
$date_to   = $_GET['to']   ?? date('Y-m-d');   

// جلب الموظف
$u_q = $conn->query("SELECT * FROM users WHERE id = $user_id");
if(!$u_q) die("Database Error: " . $conn->error);
$user_data = $u_q->fetch_assoc();

if(!$user_data) die("<div class='container'>⛔ الموظف غير موجود.</div>");

// جلب الحركات (سندات الصرف والقبض الخاصة بالموظف + الرواتب)
$sql = "
    SELECT trans_date as t_date, id as ref_id, amount, description, type 
    FROM financial_receipts 
    WHERE user_id = $user_id AND trans_date BETWEEN '$date_from' AND '$date_to'
    ORDER BY trans_date ASC
";
$transactions = $conn->query($sql);
if(!$transactions) die("Query Error: " . $conn->error);
?>

<style>
    :root { --gold: #d4af37; }
    .printable-area { background: #fff; color: #333; padding: 30px; border-radius: 10px; margin-top: 20px; }
    table { width: 100%; border-collapse: collapse; margin-top: 20px; }
    th { background: #333; color: #fff; padding: 10px; }
    td { border: 1px solid #ddd; padding: 8px; text-align: center; }
</style>

<div class="container">
    <div class="no-print" style="text-align:center; margin:20px;">
        <form method="GET" style="display:inline-block; background:#222; padding:15px; border-radius:10px;">
            <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">
            <label style="color:#fff;">من:</label> <input type="date" name="from" value="<?php echo $date_from; ?>">
            <label style="color:#fff;">إلى:</label> <input type="date" name="to" value="<?php echo $date_to; ?>">
            <button type="submit" style="background:var(--gold); cursor:pointer;">عرض</button>
            <button type="button" onclick="window.print()" style="cursor:pointer;">طباعة</button>
        </form>
    </div>

    <div class="printable-area">
        <h2 style="text-align:center; border-bottom:2px solid var(--gold); padding-bottom:10px;">
            كشف حساب / عهدة: <?php echo $user_data['full_name']; ?>
        </h2>
        
        <table>
            <thead>
                <tr>
                    <th>التاريخ</th>
                    <th>البيان</th>
                    <th>استلام (عهدة/راتب)</th>
                    <th>صرف (مصاريف)</th>
                    <th>نوع الحركة</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $total_in = 0; 
                $total_out = 0;
                if($transactions->num_rows > 0):
                    while($row = $transactions->fetch_assoc()): 
                        if($row['type'] == 'in') $total_in += $row['amount']; else $total_out += $row['amount'];
                ?>
                <tr>
                    <td><?php echo $row['t_date']; ?></td>
                    <td style="text-align:right;"><?php echo $row['description']; ?></td>
                    <td style="color:green;"><?php echo $row['type'] == 'out' ? number_format($row['amount'], 2) : '-'; ?></td>
                    <td style="color:red;"><?php echo $row['type'] == 'in' ? number_format($row['amount'], 2) : '-'; ?></td>
                    <td><?php echo $row['type'] == 'out' ? 'استلام نقدية' : 'إخلاء عهدة/توريد'; ?></td>
                </tr>
                <?php endwhile; else: ?>
                <tr><td colspan="5">لا توجد حركات.</td></tr>
                <?php endif; ?>
            </tbody>
            <tfoot>
                <tr style="background:#eee; font-weight:bold;">
                    <td colspan="2">الإجماليات</td>
                    <td><?php echo number_format($total_out, 2); ?></td>
                    <td><?php echo number_format($total_in, 2); ?></td>
                    <td>الصافي: <?php echo number_format($total_out - $total_in, 2); ?></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<?php include 'footer.php'; ob_end_flush(); ?>