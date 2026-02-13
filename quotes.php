<?php
// quotes.php - إدارة عروض الأسعار (النسخة الملكية المطورة V48.0)
ob_start();
require 'auth.php'; 
require 'config.php'; 
require 'header.php';

// 1. كود التصليح الذاتي
$conn->query("CREATE TABLE IF NOT EXISTS `quotes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `client_id` int(11) NOT NULL,
  `created_at` date NOT NULL,
  `valid_until` date NOT NULL,
  `total_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `access_token` varchar(100) DEFAULT NULL,
  `items_json` longtext DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

$my_role = $_SESSION['role'];
if (!in_array($my_role, ['admin', 'manager', 'sales', 'accountant'])) {
    die("<div class='container' style='text-align:center; padding:100px; color:#e74c3c;'><h2>⛔ غير مصرح لك بالدخول.</h2></div>");
}

// 2. معالجة إلغاء الاعتماد (Reset Status)
if (isset($_GET['reset']) && in_array($my_role, ['admin', 'manager'])) {
    $id = intval($_GET['reset']);
    $conn->query("UPDATE quotes SET status = 'pending' WHERE id = $id");
    header("Location: quotes.php?msg=reset"); exit;
}

// 3. معالجة الحذف
if (isset($_GET['del']) && $my_role == 'admin') {
    $id = intval($_GET['del']);
    $conn->query("DELETE FROM quote_items WHERE quote_id = $id");
    $conn->query("DELETE FROM quotes WHERE id = $id");
    header("Location: quotes.php?msg=deleted"); exit;
}

$search = isset($_GET['q']) ? $conn->real_escape_string($_GET['q']) : '';
$where = "WHERE 1=1";
if(!empty($search)) $where .= " AND (q.id LIKE '%$search%' OR c.name LIKE '%$search%')";

$sql = "SELECT q.*, c.name as client_name, c.phone as client_phone FROM quotes q LEFT JOIN clients c ON q.client_id = c.id $where ORDER BY q.id DESC";
$res = $conn->query($sql);
?>

<style>
    :root { --gold: #d4af37; --gold-dark: #b8860b; --bg-dark: #0a0a0a; --card-bg: #161616; --border: #2a2a2a; }
    body { background-color: var(--bg-dark); font-family: 'Cairo', sans-serif; color: #e0e0e0; }

    /* تحسين الهيدر الملكي */
    .page-header { 
        display: flex; 
        justify-content: space-between; 
        align-items: center; 
        margin: 40px 0; 
        background: var(--card-bg);
        padding: 25px 40px; /* زيادة التباعد الداخلي لشكل أفخم */
        border-radius: 15px;
        border: 1px solid var(--border);
        flex-wrap: wrap;
        gap: 30px;
    }

    .header-actions { 
        display: flex; 
        gap: 60px; /* مسافة أمان كبيرة بين البحث والزر */
        align-items: center; 
        flex-grow: 1; 
        justify-content: flex-end; 
    }

    .search-box { position: relative; width: 350px; }
    .search-box input { 
        width: 100%; padding: 12px 45px 12px 15px; 
        background: #000; border: 1px solid var(--border); 
        color: #fff; border-radius: 10px; font-family: 'Cairo';
        transition: 0.3s;
    }
    .search-box input:focus { border-color: var(--gold); box-shadow: 0 0 10px rgba(212, 175, 55, 0.2); outline: none; }
    .search-box i { position: absolute; right: 15px; top: 15px; color: var(--gold); }

    /* الجداول الفاخرة المتباعدة */
    .royal-table-card { 
        background: var(--card-bg); border: 1px solid var(--border); 
        border-radius: 15px; padding: 10px; box-shadow: 0 20px 40px rgba(0,0,0,0.5); 
    }
    table { width: 100%; border-collapse: separate; border-spacing: 0 12px; }
    th { padding: 15px; color: var(--gold); font-size: 0.85rem; text-align: right; }
    td { padding: 20px 15px; background: #1c1c1c; border-top: 1px solid var(--border); border-bottom: 1px solid var(--border); }
    td:first-child { border-right: 1px solid var(--border); border-radius: 0 12px 12px 0; }
    td:last-child { border-left: 1px solid var(--border); border-radius: 12px 0 0 12px; }
    
    tr:hover td { background: #222; transform: scale(1.005); transition: 0.2s ease; }

    /* الأزرار */
    .btn-new { 
        background: linear-gradient(135deg, var(--gold), var(--gold-dark)); 
        color: #000; padding: 12px 30px; border-radius: 10px; 
        text-decoration: none; font-weight: 800; display: flex; align-items: center; gap: 10px; 
        white-space: nowrap; transition: 0.3s;
    }
    .btn-new:hover { transform: translateY(-3px); box-shadow: 0 10px 25px rgba(212, 175, 55, 0.4); }

    .action-btn { 
        width: 36px; height: 36px; border-radius: 8px; display: inline-flex; 
        align-items: center; justify-content: center; transition: 0.3s; font-size: 1rem; color: #fff;
        border: 1px solid #333; background: #222;
    }
    .btn-wa { color: #25D366; border-color: #25D366; }
    .btn-wa:hover { background: #25D366; color: #fff; }
    .btn-view { color: var(--gold); border-color: var(--gold); }
    .btn-view:hover { background: var(--gold); color: #000; }
    .btn-reset { color: #f39c12; border-color: #f39c12; } /* لون برتقالي لإعادة الضبط */
    .btn-reset:hover { background: #f39c12; color: #fff; }
    
    .badge { padding: 6px 15px; border-radius: 50px; font-size: 0.75rem; font-weight: 700; border: 1px solid transparent; }
    .status-pending { background: rgba(241, 196, 15, 0.1); color: #f1c40f; border-color: #f1c40f; }
    .status-approved { background: rgba(46, 204, 113, 0.1); color: #2ecc71; border-color: #2ecc71; }
    .status-rejected { background: rgba(231, 76, 60, 0.1); color: #e74c3c; border-color: #e74c3c; }
    
    @media (max-width: 768px) {
        .header-actions { flex-direction: column; width: 100%; gap: 15px; }
        .search-box { width: 100%; }
        .btn-new { width: 100%; justify-content: center; }
    }
</style>

<div class="container">
    <div class="page-header">
        <h2 class="page-title"><i class="fa-solid fa-file-invoice-dollar"></i> عروض الأسعار</h2>
        
        <div class="header-actions">
            <form method="GET" class="search-box">
                <input type="text" name="q" value="<?php echo htmlspecialchars($search); ?>" placeholder="ابحث برقم العرض أو العميل...">
                <i class="fa-solid fa-magnifying-glass"></i>
            </form>
            
            <a href="add_quote.php" class="btn-new"><i class="fa-solid fa-plus-circle"></i> إنشاء عرض سعر</a>
        </div>
    </div>

    <?php if(isset($_GET['msg']) && $_GET['msg']=='reset'): ?>
        <div style="background: rgba(243, 156, 18, 0.1); color: #f39c12; padding: 15px; border-radius: 8px; margin-bottom: 20px; text-align: center; border: 1px dashed #f39c12;">
            🔄 تم إلغاء اعتماد العرض وإعادته للمراجعة بنجاح
        </div>
    <?php endif; ?>

    <div class="royal-table-card">
        <div style="overflow-x:auto;">
            <table>
                <thead>
                    <tr>
                        <th>الرقم</th>
                        <th>العميل</th>
                        <th>تاريخ العرض</th>
                        <th>صالح لغاية</th>
                        <th>القيمة</th>
                        <th>الحالة</th>
                        <th style="text-align:center;">التحكم</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if($res && $res->num_rows > 0): ?>
                        <?php while($row = $res->fetch_assoc()): 
                            $st_txt = ['pending'=>'قيد الانتظار', 'approved'=>'مقبول ✅', 'rejected'=>'مرفوض ❌'];
                            $status_class = "status-" . ($row['status'] ?? 'pending');
                            $phone = preg_replace('/[^0-9]/', '', $row['client_phone'] ?? '');
                            if(substr($phone, 0, 1) == '0') $phone = '2'.$phone;
                            $link = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/view_quote.php?id=" . $row['id'] . "&token=" . ($row['access_token'] ?? '');
                            $wa_url = "https://api.whatsapp.com/send?phone=$phone&text=" . urlencode("شريكنا العزيز *{$row['client_name']}*، إليك عرض السعر من Arab Eagles:\n$link");
                        ?>
                        <tr>
                            <td style="color:var(--gold); font-family:monospace; font-weight:bold;">#<?php echo str_pad($row['id'], 5, '0', STR_PAD_LEFT); ?></td>
                            <td>
                                <strong style="display:block;"><?php echo $row['client_name']; ?></strong>
                                <small style="color:#777;"><?php echo $row['client_phone']; ?></small>
                            </td>
                            <td><?php echo $row['created_at']; ?></td>
                            <td style="color:#e74c3c; font-weight:bold;"><?php echo $row['valid_until']; ?></td>
                            <td><b style="font-size:1.1rem; color:var(--gold);"><?php echo number_format($row['total_amount'], 2); ?></b> <small>ج.م</small></td>
                            <td><span class="badge <?php echo $status_class; ?>"><?php echo $st_txt[$row['status']] ?? $row['status']; ?></span></td>
                            <td style="text-align:center;">
                                <div style="display:flex; justify-content:center; gap:8px;">
                                    <a href="<?php echo $wa_url; ?>" target="_blank" class="action-btn btn-wa" title="إرسال واتساب"><i class="fa-brands fa-whatsapp"></i></a>
                                    <a href="view_quote.php?id=<?php echo $row['id']; ?>" class="action-btn btn-view" title="عرض العرض"><i class="fa-solid fa-expand"></i></a>
                                    
                                    <?php if($row['status'] != 'pending'): ?>
                                        <a href="?reset=<?php echo $row['id']; ?>" class="action-btn btn-reset" title="إعادة فتح للمراجعة" onclick="return confirm('هل تود إلغاء الحالة الحالية وإعادته للعميل للموافقة؟')"><i class="fa-solid fa-rotate-left"></i></a>
                                    <?php endif; ?>

                                    <a href="edit_quote.php?id=<?php echo $row['id']; ?>" class="action-btn" style="color:#3498db; border-color:#3498db;" title="تعديل"><i class="fa-solid fa-pen-nib"></i></a>
                                    
                                    <?php if($my_role == 'admin'): ?>
                                        <a href="?del=<?php echo $row['id']; ?>" onclick="return confirm('حذف نهائي؟')" class="action-btn" style="color:#c0392b; border-color:#c0392b;" title="حذف"><i class="fa-solid fa-trash-can"></i></a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="7" style="text-align:center; padding:50px; color:#555;">لا يوجد عروض حالياً.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include 'footer.php'; ob_end_flush(); ?>