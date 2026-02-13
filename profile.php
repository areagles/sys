<?php
// profile.php - الملف الشخصي (مصحح: تم نقل المنطق للأعلى)
ob_start(); // تفعيل التخزين المؤقت للمخرجات
require 'auth.php'; 
require 'config.php'; 

// تفعيل عرض الأخطاء لمعرفة السبب إذا حدثت مشكلة
ini_set('display_errors', 1);
error_reporting(E_ALL);

// 1. جلب بيانات المستخدم الحالي
$user_id = $_SESSION['user_id'];
$user_query = $conn->query("SELECT * FROM users WHERE id=$user_id");

if($user_query->num_rows == 0) {
    die("خطأ: المستخدم غير موجود.");
}
$user = $user_query->fetch_assoc();

$msg = "";

// 2. معالجة الحفظ (يجب أن تكون قبل أي HTML)
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])){
    $full_name = $conn->real_escape_string($_POST['full_name']);
    $email = $conn->real_escape_string($_POST['email']);
    $password = $_POST['password'];
    
    // أ. تحديث البيانات الأساسية
    $sql = "UPDATE users SET full_name='$full_name', email='$email' WHERE id=$user_id";
    if(!$conn->query($sql)) {
        die("خطأ في التحديث: " . $conn->error);
    }

    // ب. تحديث كلمة المرور (إذا تم كتابتها فقط)
    if(!empty($password)){
        $hashed_pass = password_hash($password, PASSWORD_DEFAULT);
        $conn->query("UPDATE users SET password='$hashed_pass' WHERE id=$user_id");
    }

    // ج. معالجة رفع الصورة
    if(isset($_FILES['avatar']) && !empty($_FILES['avatar']['name'])){
        $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
        $file_name = $_FILES['avatar']['name'];
        $file_tmp = $_FILES['avatar']['tmp_name'];
        $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        if(in_array($ext, $allowed_types)){
            // التأكد من وجود المجلد
            if(!is_dir('uploads/avatars')) {
                mkdir('uploads/avatars', 0777, true);
            }
            
            // تسمية فريدة
            $new_name = "user_{$user_id}_" . time() . ".$ext";
            $target_path = "uploads/avatars/" . $new_name;
            
            if(move_uploaded_file($file_tmp, $target_path)){
                // حذف القديمة
                if(!empty($user['profile_pic']) && file_exists($user['profile_pic'])){
                    unlink($user['profile_pic']);
                }
                
                // تحديث القاعدة
                $conn->query("UPDATE users SET profile_pic='$target_path' WHERE id=$user_id");
            } else {
                $msg = "<div class='alert-box' style='background:#c0392b'>⛔ فشل رفع الملف. تأكد من صلاحيات المجلد.</div>";
            }
        } else {
            $msg = "<div class='alert-box' style='background:#c0392b'>⛔ صيغة الملف غير مدعومة.</div>";
        }
    }

    // إذا لم تكن هناك أخطاء، قم بالتحويل
    if(empty($msg)){
        $_SESSION['name'] = $full_name; // تحديث الاسم في الجلسة
        header("Location: profile.php?success=1");
        exit();
    }
}

// ---------------------------------------------------------
// هنا يبدأ عرض الصفحة (HTML) بعد انتهاء المنطق البرمجي
// ---------------------------------------------------------
require 'header.php';

// الصورة المعروضة
$avatar_src = !empty($user['profile_pic']) ? $user['profile_pic'] : 'https://cdn-icons-png.flaticon.com/512/149/149071.png';
?>

<style>
    .profile-header {
        text-align: center;
        margin-bottom: 30px;
    }
    .avatar-wrapper {
        position: relative;
        width: 150px;
        height: 150px;
        margin: 0 auto 15px;
    }
    .avatar-img {
        width: 100%;
        height: 100%;
        border-radius: 50%;
        object-fit: cover;
        border: 4px solid var(--gold);
        box-shadow: 0 0 20px rgba(212, 175, 55, 0.3);
    }
    .file-input { display: none; }
    .camera-icon {
        position: absolute;
        bottom: 5px; right: 5px;
        background: var(--gold);
        color: #000;
        width: 35px; height: 35px;
        border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        cursor: pointer;
        border: 2px solid #000;
        font-size: 1.2rem;
        transition: 0.3s;
    }
    .camera-icon:hover { transform: scale(1.1); }
</style>

<div class="container">
    
    <?php if(isset($_GET['success'])) echo "<div class='alert-box' style='background:#2ecc71; color:#000;'>✅ تم تحديث بياناتك الشخصية بنجاح</div>"; ?>
    <?php echo $msg; ?>

    <div class="royal-card" style="max-width: 600px; margin: 0 auto;">
        <form method="POST" enctype="multipart/form-data">
            
            <div class="profile-header">
                <div class="avatar-wrapper">
                    <img src="<?php echo $avatar_src; ?>?t=<?php echo time(); ?>" id="preview" class="avatar-img">
                    <label for="avatarUpload" class="camera-icon">📷</label>
                    <input type="file" name="avatar" id="avatarUpload" class="file-input" accept="image/*" onchange="previewImage(this)">
                </div>
                <h2 style="color:var(--gold); margin:0;"><?php echo $user['full_name']; ?></h2>
                <div style="color:#777; font-size:0.9rem;"><?php echo ucfirst($user['role']); ?></div>
            </div>

            <hr style="border-color:#333; margin: 20px 0;">

            <div class="form-group" style="margin-bottom:15px;">
                <label>الاسم الكامل</label>
                <input type="text" name="full_name" value="<?php echo $user['full_name']; ?>" required>
            </div>

            <div class="form-group" style="margin-bottom:15px;">
                <label>البريد الإلكتروني</label>
                <input type="email" name="email" value="<?php echo $user['email']; ?>">
            </div>

            <div class="form-group" style="margin-bottom:15px;">
                <label>اسم المستخدم (لا يمكن تغييره)</label>
                <input type="text" value="<?php echo $user['username']; ?>" disabled style="background:#222; color:#555; cursor:not-allowed;">
            </div>

            <div style="background:#222; padding:15px; border-radius:8px; margin-top:20px; border:1px dashed #444;">
                <label style="color:var(--gold);">🔒 تغيير كلمة المرور</label>
                <input type="password" name="password" placeholder="اتركها فارغة إذا لم ترد التغيير" style="margin-top:10px;">
            </div>

            <button type="submit" name="update_profile" class="btn-royal" style="width:100%; margin-top:20px; padding:15px;">💾 حفظ التغييرات</button>
        
        </form>
    </div>
</div>

<script>
    function previewImage(input) {
        if (input.files && input.files[0]) {
            var reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('preview').src = e.target.result;
            }
            reader.readAsDataURL(input.files[0]);
        }
    }
</script>

<?php ob_end_flush(); ?>