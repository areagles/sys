<?php
// users.php - إدارة الموظفين (تصميم ملكي فاخر - Royal Dark & Gold)
require 'auth.php'; 
require 'config.php'; 
require 'header.php';

// 1. التحقق من الصلاحية (Admin Only)
if (!isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'admin') {
    die("<div class='container' style='margin-top:100px; text-align:center;'><h2 style='color:#e74c3c;'>⛔ عذراً، الوصول مرفوض.</h2></div>");
}

/* ==================================================
   Auto-Fix Database (إصلاح تلقائي للقاعدة)
   ================================================== */
$cols_to_check = ['phone', 'avatar'];
foreach($cols_to_check as $col){
    $check = $conn->query("SHOW COLUMNS FROM users LIKE '$col'");
    if($check->num_rows == 0){
        $def = ($col == 'avatar') ? "VARCHAR(255) DEFAULT 'default.png'" : "VARCHAR(20) DEFAULT NULL";
        $conn->query("ALTER TABLE users ADD COLUMN $col $def");
    }
}
if (!file_exists('uploads/users')) { @mkdir('uploads/users', 0777, true); }

/* ==================================================
   Logic (معالجة البيانات)
   ================================================== */
$msg = "";
$edit_mode = false;
$user_data = ['username'=>'', 'full_name'=>'', 'role'=>'employee', 'phone'=>'', 'avatar'=>'', 'id'=>''];

// A. الحذف
if (isset($_GET['del'])) {
    $id = intval($_GET['del']);
    // حماية: لا يمكن حذف الأدمن رقم 1 ولا المستخدم الحالي
    if ($id != 1 && $id != $_SESSION['user_id']) {
        $old_img = $conn->query("SELECT avatar FROM users WHERE id=$id")->fetch_object()->avatar ?? '';
        if($old_img && $old_img != 'default.png' && file_exists("uploads/users/$old_img")) @unlink("uploads/users/$old_img");
        
        $conn->query("DELETE FROM users WHERE id=$id");
        header("Location: users.php?msg=deleted"); exit();
    } else {
        $msg = "<div class='royal-alert error'>⛔ لا يمكن حذف الحساب الرئيسي أو حسابك الحالي!</div>";
    }
}

// B. التعديل
if (isset($_GET['edit'])) {
    $id = intval($_GET['edit']);
    $res = $conn->query("SELECT * FROM users WHERE id=$id");
    if ($res->num_rows > 0) {
        $user_data = $res->fetch_assoc();
        $edit_mode = true;
    }
}

// C. الحفظ (مع الربط التلقائي بجدول employees)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $full_name = $conn->real_escape_string($_POST['full_name']);
    $username = $conn->real_escape_string($_POST['username']);
    $role = $_POST['role'];
    $phone = $conn->real_escape_string($_POST['phone']);
    $password = $_POST['password'];

    $avatar_sql = ""; 
    if(isset($_FILES['avatar']) && $_FILES['avatar']['error'] == 0){
        $allowed = ['jpg','jpeg','png','webp'];
        $ext = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));
        if(in_array($ext, $allowed)){
            $new_name = uniqid() . "." . $ext;
            move_uploaded_file($_FILES['avatar']['tmp_name'], "uploads/users/" . $new_name);
            $avatar_sql = ", avatar='$new_name'";
        }
    }

    if (isset($_POST['update_id'])) {
        // تحديث
        $uid = intval($_POST['update_id']);
        $sql = "UPDATE users SET full_name='$full_name', username='$username', role='$role', phone='$phone' $avatar_sql WHERE id=$uid";
        
        if($conn->query($sql)){
            if (!empty($password)) {
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $conn->query("UPDATE users SET password='$hashed' WHERE id=$uid");
            }
            
            // تحديث الاسم في جدول الموظفين أيضاً للمطابقة (Sync Logic)
            $conn->query("UPDATE employees SET name='$full_name' WHERE name='{$user_data['full_name']}'"); 
            
            $msg = "<div class='royal-alert success'>✅ تم تحديث البيانات بنجاح</div>";
            $edit_mode = false; 
            $user_data = ['username'=>'', 'full_name'=>'', 'role'=>'employee', 'phone'=>'', 'avatar'=>'', 'id'=>''];
        }
    } else {
        // إضافة جديد
        $check = $conn->query("SELECT id FROM users WHERE username='$username'");
        if ($check->num_rows > 0) {
            $msg = "<div class='royal-alert error'>⛔ اسم المستخدم مسجل مسبقاً</div>";
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $img_to_db = isset($new_name) ? $new_name : 'default.png';
            $sql = "INSERT INTO users (username, password, full_name, role, phone, avatar) VALUES ('$username', '$hashed', '$full_name', '$role', '$phone', '$img_to_db')";
            
            if ($conn->query($sql)) {
                // *** الإضافة الجوهرية: إضافة نسخة في جدول employees تلقائياً ***
                $emp_check = $conn->query("SELECT id FROM employees WHERE name='$full_name'");
                if ($emp_check->num_rows == 0) {
                    $conn->query("INSERT INTO employees (name, job_title, initial_balance) VALUES ('$full_name', '$role', 0)");
                }
                
                $msg = "<div class='royal-alert success'>✅ تم إضافة الموظف والمستخدم بنجاح</div>";
            }
        }
    }
}
?>

<style>
    /* --- Royal Dark & Dynamic Gold Theme --- */
    :root {
        --charcoal: #121212;
        --card-bg: #1e1e1e;
        --gold: #d4af37;
        --gold-glow: rgba(212, 175, 55, 0.3);
        --text-color: #e0e0e0;
        --input-bg: #0a0a0a;
    }

    body {
        background-color: var(--charcoal);
        color: var(--text-color);
        font-family: 'Cairo', sans-serif;
    }

    /* Cards */
    .royal-card {
        background: var(--card-bg);
        border: 1px solid #333;
        border-radius: 12px;
        padding: 25px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.5);
        margin-bottom: 25px;
        position: relative;
        overflow: hidden;
    }
    .royal-card::before {
        content: '';
        position: absolute;
        top: 0; left: 0; width: 100%; height: 3px;
        background: linear-gradient(90deg, transparent, var(--gold), transparent);
    }

    /* Headings */
    .royal-title {
        color: var(--gold);
        font-weight: 700;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
        text-transform: uppercase;
        letter-spacing: 1px;
    }

    /* Inputs */
    .form-group label {
        display: block;
        margin-bottom: 8px;
        color: #aaa;
        font-size: 0.9rem;
    }
    .royal-input, .royal-select {
        width: 100%;
        background: var(--input-bg);
        border: 1px solid #444;
        color: #fff;
        padding: 12px 15px;
        border-radius: 8px;
        transition: all 0.3s ease;
        font-family: 'Cairo', sans-serif;
        box-sizing: border-box;
    }
    .royal-input:focus, .royal-select:focus {
        border-color: var(--gold);
        box-shadow: 0 0 15px var(--gold-glow);
        outline: none;
    }

    /* Buttons */
    .royal-btn {
        width: 100%;
        padding: 14px;
        background: linear-gradient(135deg, var(--gold), #b8860b);
        color: #000;
        font-weight: bold;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        transition: 0.3s;
        font-size: 1rem;
        box-shadow: 0 4px 15px rgba(212, 175, 55, 0.2);
    }
    .royal-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(212, 175, 55, 0.4);
    }

    /* Table */
    .royal-table-container { overflow-x: auto; }
    .royal-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 10px;
    }
    .royal-table th {
        text-align: right;
        padding: 15px;
        color: var(--gold);
        border-bottom: 2px solid #333;
        font-size: 0.9rem;
    }
    .royal-table td {
        padding: 15px;
        border-bottom: 1px solid #2a2a2a;
        vertical-align: middle;
    }
    .royal-table tr:hover { background: rgba(255,255,255,0.02); }

    /* Action Buttons (Delete & Edit) */
    .action-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 38px;
        height: 38px;
        border-radius: 8px;
        transition: 0.3s;
        text-decoration: none;
        margin-left: 5px;
        border: 1px solid #333;
        font-size: 1.1rem;
    }
    .action-btn.edit { color: var(--gold); background: rgba(212, 175, 55, 0.1); border-color: var(--gold); }
    .action-btn.edit:hover { background: var(--gold); color: #000; }
    
    .action-btn.delete { color: #e74c3c; background: rgba(231, 76, 60, 0.1); border-color: #e74c3c; }
    .action-btn.delete:hover { background: #e74c3c; color: #fff; box-shadow: 0 0 10px rgba(231, 76, 60, 0.4); }

    .action-btn.disabled { color: #555; background: #222; border-color: #444; cursor: not-allowed; opacity: 0.6; }

    /* Role Badges */
    .badge { padding: 5px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: bold; display: inline-block; }
    .badge-admin { background: rgba(212, 175, 55, 0.15); color: var(--gold); border: 1px solid var(--gold); }
    .badge-user { background: rgba(255, 255, 255, 0.1); color: #aaa; border: 1px solid #555; }
    
    /* Alerts */
    .royal-alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: bold; text-align: center; }
    .royal-alert.success { background: rgba(46, 204, 113, 0.2); color: #2ecc71; border: 1px solid #2ecc71; }
    .royal-alert.error { background: rgba(231, 76, 60, 0.2); color: #e74c3c; border: 1px solid #e74c3c; }

    /* Layout */
    .grid-container { display: grid; grid-template-columns: 350px 1fr; gap: 30px; }
    @media(max-width:992px){ .grid-container { grid-template-columns: 1fr; } }
    .form-grid { display: grid; grid-template-columns: 1fr; gap: 15px; }

    .avatar-preview { width: 80px; height: 80px; border-radius: 50%; border: 2px solid var(--gold); object-fit: cover; margin-bottom: 15px; }
    .avatar-mini { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; border: 1px solid #444; vertical-align: middle; margin-left: 10px; }
</style>

<div class="container" style="margin-top:30px; margin-bottom:50px;">
    
    <?php echo $msg; ?>

    <div class="grid-container">
        
        <div class="royal-card">
            <h3 class="royal-title">
                <?php echo $edit_mode ? '✏️ تعديل بيانات' : '➕ إضافة موظف'; ?>
            </h3>

            <form method="POST" enctype="multipart/form-data">
                <?php if($edit_mode): ?>
                    <input type="hidden" name="update_id" value="<?php echo $user_data['id']; ?>">
                    <div style="text-align:center;">
                        <?php $img_src = !empty($user_data['avatar']) ? "uploads/users/".$user_data['avatar'] : "https://via.placeholder.com/80/333/d4af37?text=User"; ?>
                        <img src="<?php echo $img_src; ?>" class="avatar-preview">
                    </div>
                <?php endif; ?>

                <div class="form-grid">
                    <div class="form-group">
                        <label>الاسم بالكامل</label>
                        <input type="text" name="full_name" class="royal-input" required value="<?php echo $user_data['full_name']; ?>" placeholder="الاسم هنا">
                    </div>
                    
                    <div class="form-group">
                        <label>رقم الهاتف</label>
                        <input type="text" name="phone" class="royal-input" value="<?php echo $user_data['phone']; ?>" placeholder="01xxxxxxxxx">
                    </div>

                    <div class="form-group">
                        <label>اسم المستخدم (للدخول)</label>
                        <input type="text" name="username" class="royal-input" required value="<?php echo $user_data['username']; ?>" placeholder="Username">
                    </div>

                    <div class="form-group">
                        <label>كلمة المرور <?php echo $edit_mode ? '<small>(اتركها فارغة للإبقاء)</small>' : ''; ?></label>
                        <input type="password" name="password" class="royal-input" <?php echo $edit_mode ? '' : 'required'; ?> placeholder="******">
                    </div>

                    <div class="form-group">
                        <label>الصلاحية / الوظيفة</label>
                        <select name="role" class="royal-select" required>
                            <option value="">-- اختر الرتبة --</option>
                            <option value="admin" <?php if($user_data['role']=='admin') echo 'selected'; ?>>👑 Admin (مدير عام)</option>
                            <option value="manager" <?php if($user_data['role']=='manager') echo 'selected'; ?>>👔 Manager (مدير تنفيذي)</option>
                            <option value="monitor" <?php if($user_data['role']=='monitor') echo 'selected'; ?>>🔍 Monitor (مراقب جودة)</option>
                            <option value="accountant" <?php if($user_data['role']=='accountant') echo 'selected'; ?>>💰 Accountant (محاسب)</option>
                            <option value="sales" <?php if($user_data['role']=='sales') echo 'selected'; ?>>📞 Sales (مبيعات)</option>
                            <option value="marketer" <?php if($user_data['role']=='marketer') echo 'selected'; ?>>📈 Marketer (تسويق)</option>
                            <option value="designer" <?php if($user_data['role']=='designer') echo 'selected'; ?>>🎨 Designer (مصمم)</option>
                            <option value="production" <?php if($user_data['role']=='production') echo 'selected'; ?>>⚙️ Production (إنتاج/فني)</option>
                            <option value="driver" <?php if($user_data['role']=='driver') echo 'selected'; ?>>🚚 Driver (سائق)</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>صورة شخصية (اختياري)</label>
                        <input type="file" name="avatar" class="royal-input" style="padding: 10px;" accept="image/*">
                    </div>
                </div>

                <button type="submit" class="royal-btn" style="margin-top:20px;">
                    <?php echo $edit_mode ? 'حفظ التغييرات' : 'تسجيل الموظف'; ?>
                </button>
                
                <?php if($edit_mode): ?>
                    <a href="users.php" style="display:block; text-align:center; margin-top:15px; color:#888; text-decoration:none;">إلغاء العملية</a>
                <?php endif; ?>
            </form>
        </div>

        <div class="royal-card">
            <h3 class="royal-title">👥 فريق العمل</h3>
            
            <div class="royal-table-container">
                <table class="royal-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>الموظف</th>
                            <th>الهاتف</th>
                            <th>الصلاحية</th>
                            <th style="text-align:center;">إجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $res = $conn->query("SELECT * FROM users ORDER BY role='admin' DESC, id ASC");
                        if($res->num_rows > 0):
                            while($row = $res->fetch_assoc()):
                                $img = !empty($row['avatar']) ? "uploads/users/".$row['avatar'] : "https://ui-avatars.com/api/?name=".urlencode($row['full_name'])."&background=random";
                                $badge = ($row['role'] == 'admin') ? 'badge-admin' : 'badge-user';
                        ?>
                        <tr>
                            <td style="color:#666; font-size:0.8rem;"><?php echo $row['id']; ?></td>
                            <td>
                                <div style="display:flex; align-items:center;">
                                    <img src="<?php echo $img; ?>" class="avatar-mini">
                                    <div>
                                        <div style="font-weight:bold; color:#fff;"><?php echo $row['full_name']; ?></div>
                                        <div style="font-size:0.75rem; color:#888;">@<?php echo $row['username']; ?></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <?php if($row['phone']): ?>
                                    <span style="font-size:0.85rem; color:#bbb;"><?php echo $row['phone']; ?></span>
                                <?php else: ?>
                                    <span style="color:#444;">-</span>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge <?php echo $badge; ?>"><?php echo strtoupper($row['role']); ?></span></td>
                            
                            <td style="text-align:center;">
                                <a href="?edit=<?php echo $row['id']; ?>" class="action-btn edit" title="تعديل">✏️</a>
                                
                                <?php if($row['id'] != 1 && $row['id'] != $_SESSION['user_id']): ?>
                                    <a href="?del=<?php echo $row['id']; ?>" onclick="return confirm('⚠️ هل أنت متأكد تماماً من حذف هذا الموظف؟')" class="action-btn delete" title="حذف">🗑️</a>
                                <?php else: ?>
                                    <span class="action-btn disabled" title="لا يمكن حذف هذا الحساب (حماية)">🚫</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; else: ?>
                            <tr><td colspan="5" style="text-align:center; padding:30px; color:#666;">لا يوجد موظفين مسجلين.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<?php include 'footer.php'; ?>