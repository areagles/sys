<?php
// users.php - إدارة الموظفين (Royal Security Edition)
require 'auth.php'; 
require 'config.php'; 
require 'header.php';

// 1. التحقق من الصلاحية (Admin Only)
if (!isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'admin') {
    die("<div class='container' style='margin-top:100px; text-align:center;'><h2 style='color:#e74c3c;'>⛔ عذراً، الوصول مرفوض.</h2></div>");
}

if (!file_exists('uploads/users')) { @mkdir('uploads/users', 0755, true); }

$msg = "";
$edit_mode = false;
$user_data = ['username'=>'', 'full_name'=>'', 'role'=>'employee', 'phone'=>'', 'avatar'=>'', 'id'=>''];

// A. الحذف
if (isset($_GET['del'])) {
    $id = intval($_GET['del']);
    if ($id != 1 && $id != $_SESSION['user_id']) {
        // جلب الصورة القديمة لحذفها
        $stmt_img = $conn->prepare("SELECT avatar FROM users WHERE id=?");
        $stmt_img->bind_param("i", $id);
        $stmt_img->execute();
        $res_img = $stmt_img->get_result();
        $old_img = $res_img->fetch_object()->avatar ?? '';
        
        if($old_img && $old_img != 'default.png' && file_exists("uploads/users/$old_img")) @unlink("uploads/users/$old_img");
        
        $stmt_del = $conn->prepare("DELETE FROM users WHERE id=?");
        $stmt_del->bind_param("i", $id);
        if($stmt_del->execute()){
            header("Location: users.php?msg=deleted"); exit();
        }
    } else {
        $msg = "<div class='royal-alert error'>⛔ لا يمكن حذف الحساب الرئيسي أو حسابك الحالي!</div>";
    }
}

// B. التعديل
if (isset($_GET['edit'])) {
    $id = intval($_GET['edit']);
    $stmt_get = $conn->prepare("SELECT * FROM users WHERE id=?");
    $stmt_get->bind_param("i", $id);
    $stmt_get->execute();
    $res = $stmt_get->get_result();
    
    if ($res->num_rows > 0) {
        $user_data = $res->fetch_assoc();
        $edit_mode = true;
    }
}

// C. الحفظ
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $full_name = trim($_POST['full_name']);
    $username = trim($_POST['username']);
    $role = $_POST['role'];
    $phone = trim($_POST['phone']);
    $password = $_POST['password'];

    // معالجة الصورة (Secure Upload Strategy)
    $avatar_sql = ""; 
    $params_type = ""; 
    $params_values = [];

    if(isset($_FILES['avatar']) && $_FILES['avatar']['error'] == 0){
        $allowed_ext = ['jpg', 'jpeg', 'png', 'webp'];
        $ext = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));
        
        if(in_array($ext, $allowed_ext)){
            // فحص نوع المحتوى الحقيقي (MIME Type)
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $_FILES['avatar']['tmp_name']);
            finfo_close($finfo);
            
            if(strpos($mime, 'image') === 0) {
                $new_name = uniqid("user_") . "." . $ext;
                move_uploaded_file($_FILES['avatar']['tmp_name'], "uploads/users/" . $new_name);
                $avatar_sql = ", avatar=?"; 
                $img_to_db = $new_name;
            }
        }
    }

    if (isset($_POST['update_id'])) {
        // --- تحديث ---
        $uid = intval($_POST['update_id']);
        $sql = "UPDATE users SET full_name=?, username=?, role=?, phone=?";
        if($avatar_sql) $sql .= $avatar_sql;
        $sql .= " WHERE id=?";
        
        $stmt = $conn->prepare($sql);
        
        if($avatar_sql) {
            $stmt->bind_param("sssssi", $full_name, $username, $role, $phone, $img_to_db, $uid);
        } else {
            $stmt->bind_param("ssssi", $full_name, $username, $role, $phone, $uid);
        }
        
        if($stmt->execute()){
            if (!empty($password)) {
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $stmt_pw = $conn->prepare("UPDATE users SET password=? WHERE id=?");
                $stmt_pw->bind_param("si", $hashed, $uid);
                $stmt_pw->execute();
            }
            
            // Sync with Employees Table
            $stmt_sync = $conn->prepare("UPDATE employees SET name=? WHERE name=?");
            $stmt_sync->bind_param("ss", $full_name, $user_data['full_name']);
            $stmt_sync->execute();
            
            $msg = "<div class='royal-alert success'>✅ تم تحديث البيانات بنجاح</div>";
            $edit_mode = false; 
            $user_data = ['username'=>'', 'full_name'=>'', 'role'=>'employee', 'phone'=>'', 'avatar'=>'', 'id'=>''];
        }
    } else {
        // --- إضافة جديد ---
        $stmt_check = $conn->prepare("SELECT id FROM users WHERE username=?");
        $stmt_check->bind_param("s", $username);
        $stmt_check->execute();
        
        if ($stmt_check->get_result()->num_rows > 0) {
            $msg = "<div class='royal-alert error'>⛔ اسم المستخدم مسجل مسبقاً</div>";
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $final_avatar = isset($img_to_db) ? $img_to_db : 'default.png';
            
            $stmt_add = $conn->prepare("INSERT INTO users (username, password, full_name, role, phone, avatar) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt_add->bind_param("ssssss", $username, $hashed, $full_name, $role, $phone, $final_avatar);
            
            if ($stmt_add->execute()) {
                // Sync with Employees
                $stmt_emp = $conn->prepare("SELECT id FROM employees WHERE name=?");
                $stmt_emp->bind_param("s", $full_name);
                $stmt_emp->execute();
                if ($stmt_emp->get_result()->num_rows == 0) {
                    $conn->query("INSERT INTO employees (name, job_title, initial_balance) VALUES ('$full_name', '$role', 0)");
                }
                $msg = "<div class='royal-alert success'>✅ تم إضافة الموظف والمستخدم بنجاح</div>";
            }
        }
    }
}
?>

<style>
    :root { --charcoal: #121212; --card-bg: #1e1e1e; --gold: #d4af37; --gold-glow: rgba(212, 175, 55, 0.3); --text-color: #e0e0e0; --input-bg: #0a0a0a; }
    body { background-color: var(--charcoal); color: var(--text-color); font-family: 'Cairo', sans-serif; }
    .royal-card { background: var(--card-bg); border: 1px solid #333; border-radius: 12px; padding: 25px; box-shadow: 0 10px 30px rgba(0,0,0,0.5); margin-bottom: 25px; position: relative; overflow: hidden; }
    .royal-card::before { content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 3px; background: linear-gradient(90deg, transparent, var(--gold), transparent); }
    .royal-title { color: var(--gold); font-weight: 700; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; text-transform: uppercase; letter-spacing: 1px; }
    .form-group label { display: block; margin-bottom: 8px; color: #aaa; font-size: 0.9rem; }
    .royal-input, .royal-select { width: 100%; background: var(--input-bg); border: 1px solid #444; color: #fff; padding: 12px 15px; border-radius: 8px; transition: all 0.3s ease; font-family: 'Cairo', sans-serif; box-sizing: border-box; }
    .royal-input:focus, .royal-select:focus { border-color: var(--gold); box-shadow: 0 0 15px var(--gold-glow); outline: none; }
    .royal-btn { width: 100%; padding: 14px; background: linear-gradient(135deg, var(--gold), #b8860b); color: #000; font-weight: bold; border: none; border-radius: 8px; cursor: pointer; transition: 0.3s; font-size: 1rem; box-shadow: 0 4px 15px rgba(212, 175, 55, 0.2); }
    .royal-btn:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(212, 175, 55, 0.4); }
    .royal-table-container { overflow-x: auto; }
    .royal-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
    .royal-table th { text-align: right; padding: 15px; color: var(--gold); border-bottom: 2px solid #333; font-size: 0.9rem; }
    .royal-table td { padding: 15px; border-bottom: 1px solid #2a2a2a; vertical-align: middle; }
    .action-btn { display: inline-flex; align-items: center; justify-content: center; width: 38px; height: 38px; border-radius: 8px; transition: 0.3s; text-decoration: none; margin-left: 5px; border: 1px solid #333; font-size: 1.1rem; }
    .action-btn.edit { color: var(--gold); background: rgba(212, 175, 55, 0.1); border-color: var(--gold); }
    .action-btn.edit:hover { background: var(--gold); color: #000; }
    .action-btn.delete { color: #e74c3c; background: rgba(231, 76, 60, 0.1); border-color: #e74c3c; }
    .action-btn.delete:hover { background: #e74c3c; color: #fff; box-shadow: 0 0 10px rgba(231, 76, 60, 0.4); }
    .badge { padding: 5px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: bold; display: inline-block; }
    .badge-admin { background: rgba(212, 175, 55, 0.15); color: var(--gold); border: 1px solid var(--gold); }
    .badge-user { background: rgba(255, 255, 255, 0.1); color: #aaa; border: 1px solid #555; }
    .royal-alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: bold; text-align: center; }
    .royal-alert.success { background: rgba(46, 204, 113, 0.2); color: #2ecc71; border: 1px solid #2ecc71; }
    .royal-alert.error { background: rgba(231, 76, 60, 0.2); color: #e74c3c; border: 1px solid #e74c3c; }
    .grid-container { display: grid; grid-template-columns: 350px 1fr; gap: 30px; }
    @media(max-width:992px){ .grid-container { grid-template-columns: 1fr; } }
    .avatar-mini { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; border: 1px solid #444; vertical-align: middle; margin-left: 10px; }
</style>

<div class="container" style="margin-top:30px; margin-bottom:50px;">
    <?php echo $msg; ?>
    <div class="grid-container">
        <div class="royal-card">
            <h3 class="royal-title"><?php echo $edit_mode ? '✏️ تعديل بيانات' : '➕ إضافة موظف'; ?></h3>
            <form method="POST" enctype="multipart/form-data">
                <?php if($edit_mode): ?>
                    <input type="hidden" name="update_id" value="<?php echo $user_data['id']; ?>">
                <?php endif; ?>
                <div class="form-group"><label>الاسم بالكامل</label><input type="text" name="full_name" class="royal-input" required value="<?php echo $user_data['full_name']; ?>"></div>
                <div class="form-group" style="margin-top:10px;"><label>رقم الهاتف</label><input type="text" name="phone" class="royal-input" value="<?php echo $user_data['phone']; ?>"></div>
                <div class="form-group" style="margin-top:10px;"><label>اسم المستخدم</label><input type="text" name="username" class="royal-input" required value="<?php echo $user_data['username']; ?>"></div>
                <div class="form-group" style="margin-top:10px;"><label>كلمة المرور</label><input type="password" name="password" class="royal-input" <?php echo $edit_mode ? '' : 'required'; ?>></div>
                <div class="form-group" style="margin-top:10px;">
                    <label>الوظيفة</label>
                    <select name="role" class="royal-select" required>
                        <option value="">-- اختر --</option>
                        <option value="admin" <?php if($user_data['role']=='admin') echo 'selected'; ?>>👑 Admin</option>
                        <option value="manager" <?php if($user_data['role']=='manager') echo 'selected'; ?>>👔 Manager</option>
                        <option value="accountant" <?php if($user_data['role']=='accountant') echo 'selected'; ?>>💰 Accountant</option>
                        <option value="designer" <?php if($user_data['role']=='designer') echo 'selected'; ?>>🎨 Designer</option>
                        <option value="production" <?php if($user_data['role']=='production') echo 'selected'; ?>>⚙️ Production</option>
                        <option value="sales" <?php if($user_data['role']=='sales') echo 'selected'; ?>>📞 Sales</option>
                        <option value="driver" <?php if($user_data['role']=='driver') echo 'selected'; ?>>🚚 Driver</option>
                    </select>
                </div>
                <div class="form-group" style="margin-top:10px;"><label>الصورة</label><input type="file" name="avatar" class="royal-input" accept="image/*"></div>
                <button type="submit" class="royal-btn" style="margin-top:20px;"><?php echo $edit_mode ? 'حفظ التغييرات' : 'تسجيل الموظف'; ?></button>
            </form>
        </div>
        <div class="royal-card">
            <h3 class="royal-title">👥 فريق العمل</h3>
            <div class="royal-table-container">
                <table class="royal-table">
                    <thead><tr><th>#</th><th>الموظف</th><th>الهاتف</th><th>الصلاحية</th><th style="text-align:center;">إجراءات</th></tr></thead>
                    <tbody>
                        <?php 
                        $res = $conn->query("SELECT * FROM users ORDER BY role='admin' DESC, id ASC");
                        while($row = $res->fetch_assoc()):
                            $img = !empty($row['avatar']) && file_exists("uploads/users/".$row['avatar']) ? "uploads/users/".$row['avatar'] : "https://ui-avatars.com/api/?name=".urlencode($row['full_name']);
                        ?>
                        <tr>
                            <td><?php echo $row['id']; ?></td>
                            <td><img src="<?php echo $img; ?>" class="avatar-mini"> <b><?php echo $row['full_name']; ?></b></td>
                            <td><?php echo $row['phone'] ? $row['phone'] : '-'; ?></td>
                            <td><span class="badge <?php echo ($row['role'] == 'admin') ? 'badge-admin' : 'badge-user'; ?>"><?php echo strtoupper($row['role']); ?></span></td>
                            <td style="text-align:center;">
                                <a href="?edit=<?php echo $row['id']; ?>" class="action-btn edit">✏️</a>
                                <?php if($row['id'] != 1 && $row['id'] != $_SESSION['user_id']): ?>
                                    <a href="?del=<?php echo $row['id']; ?>" onclick="return confirm('حذف؟')" class="action-btn delete">🗑️</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php include 'footer.php'; ?>