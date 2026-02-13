<?php
// header.php - (Royal Responsive Header V19.0 - Aggressive PWA Install)

if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once 'config.php';

$current_page = basename($_SERVER['PHP_SELF']);
$public_pages = ['login.php', 'client_review.php', 'client_finance.php', 'view_quote.php', 'print_order.php', 'print_invoice.php', 'print_quote.php'];

if (!isset($_SESSION['user_id']) && !in_array($current_page, $public_pages)) {
    header("Location: login.php"); exit();
}

$current_id = $_SESSION['user_id'] ?? 0;
if($current_id){
    $user_data = $conn->query("SELECT * FROM users WHERE id='$current_id'")->fetch_assoc();
    $avatar = !empty($user_data['profile_pic']) ? $user_data['profile_pic'] . '?t='.time() : 'https://ui-avatars.com/api/?name='.$user_data['full_name'].'&background=d4af37&color=000';
    $role = $user_data['role'] ?? 'employee';
    $name = $user_data['full_name'] ?? 'User';
} else {
    $role = 'guest'; $name = 'Guest'; $avatar = '';
}
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Arab Eagles ERP</title>
    
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#d4af37">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Arab Eagles">
    <link rel="apple-touch-icon" href="assets/img/icon-192x192.png">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;900&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --bg-dark: #0f0f0f;
            --panel-bg: #1a1a1a;
            --gold-primary: #d4af37;
            --gold-gradient: linear-gradient(135deg, #d4af37 0%, #aa842c 100%);
            --text-main: #ffffff;
            --border-color: #333;
        }

        * { box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
        
        body { 
            background-color: #050505; color: var(--text-main); font-family: 'Cairo', sans-serif; 
            margin: 0; padding-top: 80px; overflow-x: hidden; 
        }

        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-track { background: #000; }
        ::-webkit-scrollbar-thumb { background: #333; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: var(--gold-primary); }

        a { text-decoration: none; transition: 0.3s; }
        .container { max-width: 1300px; margin: 0 auto; padding: 20px; }

        /* Navbar */
        .main-navbar {
            background: rgba(10, 10, 10, 0.95);
            backdrop-filter: blur(15px);
            border-bottom: 1px solid rgba(212, 175, 55, 0.2);
            padding: 0 20px; height: 70px;
            display: flex; align-items: center; justify-content: space-between;
            position: fixed; top: 0; left: 0; right: 0; z-index: 2000;
            box-shadow: 0 5px 30px rgba(0,0,0,0.5);
        }

        .brand-logo { display: flex; align-items: center; gap: 10px; color: #fff; font-size: 1.4rem; font-weight: 900; }
        .brand-icon { 
            width: 35px; height: 35px; background: var(--gold-gradient); color: #000; 
            border-radius: 8px; display: flex; align-items: center; justify-content: center; 
            box-shadow: 0 0 15px rgba(212, 175, 55, 0.4);
        }

        .nav-links { display: flex; gap: 5px; align-items: center; }
        .nav-item {
            color: #bbb; padding: 8px 12px; border-radius: 8px; font-size: 0.9rem; font-weight: 600;
            display: flex; align-items: center; gap: 8px; transition: 0.2s; white-space: nowrap;
        }
        .nav-item i { color: var(--gold-primary); font-size: 1rem; transition: 0.2s; }
        .nav-item:hover { background: rgba(255,255,255,0.05); color: #fff; }
        .nav-item.active { background: rgba(212, 175, 55, 0.1); color: var(--gold-primary); border: 1px solid rgba(212, 175, 55, 0.3); }

        .btn-new-job {
            background: var(--gold-gradient); color: #000 !important;
            padding: 8px 15px; box-shadow: 0 0 10px rgba(212, 175, 55, 0.3);
        }
        .btn-new-job:hover { transform: translateY(-2px); box-shadow: 0 5px 20px rgba(212, 175, 55, 0.5); }

        /* Install Buttons & Modals */
        #installAppBtn {
            display: none; 
            background: #2ecc71; color: #fff; border: none; padding: 8px 15px; 
            border-radius: 8px; font-weight: bold; cursor: pointer; align-items: center; gap: 8px;
            animation: pulse-green 2s infinite;
        }
        @keyframes pulse-green { 0% { box-shadow: 0 0 0 0 rgba(46, 204, 113, 0.7); } 70% { box-shadow: 0 0 0 10px rgba(46, 204, 113, 0); } 100% { box-shadow: 0 0 0 0 rgba(46, 204, 113, 0); } }

        /* Install Modal (PWA Prompt) */
        .pwa-modal {
            display: none; position: fixed; bottom: 0; left: 0; width: 100%; 
            background: #1a1a1a; border-top: 4px solid var(--gold-primary);
            padding: 20px; z-index: 9999; box-shadow: 0 -5px 20px rgba(0,0,0,0.8);
            animation: slideUp 0.5s ease-out;
        }
        @keyframes slideUp { from { transform: translateY(100%); } to { transform: translateY(0); } }
        
        .pwa-content { display: flex; align-items: center; justify-content: space-between; max-width: 1000px; margin: 0 auto; flex-wrap: wrap; gap: 15px; }
        .pwa-text h4 { margin: 0 0 5px 0; color: #fff; }
        .pwa-text p { margin: 0; color: #aaa; font-size: 0.9rem; }
        .pwa-actions { display: flex; gap: 10px; }
        .pwa-btn-install { background: var(--gold-primary); color: #000; border: none; padding: 10px 20px; border-radius: 6px; font-weight: bold; cursor: pointer; }
        .pwa-btn-close { background: transparent; border: 1px solid #555; color: #ccc; padding: 10px 20px; border-radius: 6px; cursor: pointer; }

        /* iOS Instructions */
        .ios-prompt {
            display: none; position: fixed; bottom: 20px; left: 50%; transform: translateX(-50%);
            width: 90%; max-width: 400px; background: #222; border: 1px solid #444; 
            border-radius: 12px; padding: 20px; z-index: 9999; box-shadow: 0 10px 30px rgba(0,0,0,0.8);
            text-align: center;
        }
        .ios-prompt::after {
            content: ''; position: absolute; bottom: -10px; left: 50%; margin-left: -10px;
            border-width: 10px; border-style: solid; border-color: #222 transparent transparent transparent;
        }

        /* User Profile */
        .user-profile { display: flex; align-items: center; gap: 10px; cursor: pointer; padding: 5px 10px; border-radius: 50px; position: relative; }
        .user-profile:hover { background: rgba(255,255,255,0.05); }
        .avatar-circle { width: 40px; height: 40px; border-radius: 50%; border: 2px solid var(--gold-primary); object-fit: cover; }
        .user-info { text-align: right; }
        .u-name { display: block; font-size: 0.9rem; font-weight: 700; color: #fff; line-height: 1.2; }
        .u-role { display: block; font-size: 0.7rem; color: var(--gold-primary); text-transform: uppercase; }

        .dropdown-menu {
            position: absolute; top: 60px; left: 0; width: 220px;
            background: #1a1a1a; border: 1px solid #333; border-top: 3px solid var(--gold-primary);
            border-radius: 8px; display: flex; flex-direction: column;
            opacity: 0; visibility: hidden; transform: translateY(10px); transition: 0.3s;
            box-shadow: 0 10px 40px rgba(0,0,0,0.8); z-index: 2001;
        }
        .user-profile:hover .dropdown-menu { opacity: 1; visibility: visible; transform: translateY(0); }
        .dd-item { padding: 12px 20px; color: #ccc; display: flex; align-items: center; gap: 10px; border-bottom: 1px solid #222; }
        .dd-item:hover { background: #222; color: #fff; padding-right: 25px; }

        /* Mobile Menu */
        .hamburger { display: none; font-size: 1.5rem; color: var(--gold-primary); cursor: pointer; padding: 10px; }
        .mobile-overlay {
            position: fixed; top: 0; right: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.8); backdrop-filter: blur(5px);
            z-index: 2001; display: none; opacity: 0; transition: 0.3s;
        }
        .mobile-sidebar {
            position: fixed; top: 0; right: -300px; width: 280px; height: 100%;
            background: #111; border-left: 1px solid #333; z-index: 2002;
            padding: 20px; display: flex; flex-direction: column; gap: 10px; overflow-y: auto;
            transition: 0.4s cubic-bezier(0.4, 0, 0.2, 1); box-shadow: -5px 0 30px rgba(0,0,0,0.8);
        }
        .mobile-sidebar.open { right: 0; }
        .mobile-overlay.open { display: block; opacity: 1; }

        .m-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 30px; border-bottom: 1px solid #222; padding-bottom: 15px; }
        .m-link { padding: 15px; background: #1a1a1a; border-radius: 10px; color: #ddd; display: flex; align-items: center; gap: 15px; font-size: 1rem; border: 1px solid transparent; }
        .m-link i { color: var(--gold-primary); width: 25px; text-align: center; }
        .m-link.active { border-color: var(--gold-primary); background: rgba(212, 175, 55, 0.05); color: #fff; }

        @media (max-width: 1200px) {
            .nav-links, .user-profile .user-info { display: none; }
            .hamburger { display: block; }
            .user-profile { padding: 0; }
            .user-profile:hover .dropdown-menu { display: none; } 
        }
    </style>
</head>
<body>

<?php if(isset($_SESSION['user_id'])): ?>

    <nav class="main-navbar">
        <a href="dashboard.php" class="brand-logo">
            <div class="brand-icon"><i class="fa-solid fa-eagle"></i></div>
            <span>Arab Eagles</span>
        </a>

        <div class="nav-links">
            <a href="dashboard.php" class="nav-item <?php echo $current_page=='dashboard.php'?'active':''; ?>"><i class="fa-solid fa-house"></i> الرئيسية</a>
            <?php if(!in_array($role, ['driver', 'accountant'])): ?><a href="add_job.php" class="nav-item btn-new-job"><i class="fa-solid fa-plus"></i> أمر شغل</a><?php endif; ?>
            <?php if(in_array($role, ['admin', 'manager', 'sales', 'accountant'])): ?><a href="quotes.php" class="nav-item <?php echo $current_page=='quotes.php'?'active':''; ?>"><i class="fa-solid fa-file-contract"></i> العروض</a><?php endif; ?>
            <?php if(in_array($role, ['admin', 'manager', 'accountant'])): ?>
                <a href="finance.php" class="nav-item <?php echo $current_page=='finance.php'?'active':''; ?>"><i class="fa-solid fa-coins"></i> المالية</a>
                <a href="invoices.php" class="nav-item <?php echo $current_page=='invoices.php'?'active':''; ?>"><i class="fa-solid fa-file-invoice"></i> الفواتير</a>
                <a href="finance_reports.php" class="nav-item <?php echo $current_page=='finance_reports.php'?'active':''; ?>"><i class="fa-solid fa-chart-pie"></i> التقارير</a>
            <?php endif; ?>
            <?php if(in_array($role, ['admin', 'sales', 'manager', 'purchasing'])): ?>
                <a href="clients.php" class="nav-item <?php echo $current_page=='clients.php'?'active':''; ?>"><i class="fa-solid fa-users"></i> العملاء</a>
                <a href="suppliers.php" class="nav-item <?php echo $current_page=='suppliers.php'?'active':''; ?>"><i class="fa-solid fa-truck-field"></i> الموردين</a>
            <?php endif; ?>
        </div>

        <div style="display:flex; align-items:center; gap:15px;">
            <button id="installAppBtn" onclick="installPWA()"><i class="fa-solid fa-download"></i> تثبيت التطبيق</button>
            
            <div class="user-profile">
                <div class="user-info"><span class="u-name"><?php echo explode(' ', $name)[0]; ?></span><span class="u-role"><?php echo $role; ?></span></div>
                <img src="<?php echo $avatar; ?>" class="avatar-circle">
                <div class="dropdown-menu">
                    <a href="profile.php" class="dd-item"><i class="fa-solid fa-user"></i> حسابي</a>
                    <?php if($role == 'admin'): ?>
                        <a href="users.php" class="dd-item"><i class="fa-solid fa-users-gear"></i> الموظفين</a>
                        <a href="backup.php" class="dd-item"><i class="fa-solid fa-database"></i> النسخ</a>
                    <?php endif; ?>
                    <a href="logout.php" class="dd-item"><i class="fa-solid fa-power-off"></i> خروج</a>
                </div>
            </div>
            <div class="hamburger" onclick="toggleMenu()"><i class="fa-solid fa-bars-staggered"></i></div>
        </div>
    </nav>

    <div class="mobile-overlay" id="mobileOverlay" onclick="toggleMenu()"></div>
    <div class="mobile-sidebar" id="mobileSidebar">
        <div class="m-header">
            <div class="brand-logo"><div class="brand-icon"><i class="fa-solid fa-eagle"></i></div> القائمة</div>
            <div class="close-btn" onclick="toggleMenu()">×</div>
        </div>
        <div style="text-align:center; margin-bottom:20px; border-bottom:1px dashed #333; padding-bottom:20px;">
            <img src="<?php echo $avatar; ?>" style="width:70px; height:70px; border-radius:50%; border:2px solid var(--gold-primary); margin-bottom:10px;">
            <div style="color:#fff; font-weight:bold;"><?php echo $name; ?></div>
            <div style="color:var(--gold-primary); font-size:0.8rem; text-transform:uppercase;"><?php echo $role; ?></div>
        </div>

        <a href="dashboard.php" class="m-link <?php echo $current_page=='dashboard.php'?'active':''; ?>"><i class="fa-solid fa-house"></i> الرئيسية</a>
        <?php if(!in_array($role, ['driver', 'accountant'])): ?><a href="add_job.php" class="m-link" style="border-color:var(--gold-primary); background:rgba(212, 175, 55, 0.1);"><i class="fa-solid fa-plus"></i> أمر شغل جديد</a><?php endif; ?>
        <?php if(in_array($role, ['admin', 'manager', 'sales'])): ?><a href="quotes.php" class="m-link"><i class="fa-solid fa-file-contract"></i> عروض الأسعار</a><?php endif; ?>
        <?php if(in_array($role, ['admin', 'manager', 'accountant'])): ?>
            <a href="finance.php" class="m-link"><i class="fa-solid fa-coins"></i> الإدارة المالية</a>
            <a href="invoices.php" class="m-link"><i class="fa-solid fa-file-invoice"></i> الفواتير</a>
        <?php endif; ?>
        <?php if(in_array($role, ['admin', 'sales', 'manager'])): ?>
            <a href="clients.php" class="m-link"><i class="fa-solid fa-users"></i> العملاء</a>
            <a href="suppliers.php" class="m-link"><i class="fa-solid fa-truck-field"></i> الموردين</a>
        <?php endif; ?>
        <?php if($role == 'admin'): ?>
            <a href="users.php" class="m-link"><i class="fa-solid fa-users-gear"></i> إدارة الموظفين</a>
        <?php endif; ?>
        
        <button id="installAppBtnMobile" class="m-link" style="width:100%; border:none; background:#2ecc71; color:#fff; display:none; justify-content:flex-start; text-align:right;" onclick="installPWA()">
            <i class="fa-solid fa-download" style="color:#fff;"></i> تثبيت التطبيق
        </button>

        <a href="logout.php" class="m-link" style="margin-top:auto; color:#e74c3c;"><i class="fa-solid fa-power-off"></i> تسجيل الخروج</a>
    </div>

    <div id="pwaInstallModal" class="pwa-modal">
        <div class="pwa-content">
            <div style="font-size:3rem; color:var(--gold-primary);"><i class="fa-solid fa-mobile-screen-button"></i></div>
            <div class="pwa-text">
                <h4>تثبيت نظام Arab Eagles</h4>
                <p>لتجربة أفضل وأسرع، قم بتثبيت النظام كتطبيق على جهازك الآن.</p>
            </div>
            <div class="pwa-actions">
                <button class="pwa-btn-install" onclick="installPWA()">تثبيت الآن</button>
                <button class="pwa-btn-close" onclick="closePwaModal()">لاحقاً</button>
            </div>
        </div>
    </div>

    <div id="iosInstallPrompt" class="ios-prompt">
        <div style="text-align:right; cursor:pointer; color:#888;" onclick="document.getElementById('iosInstallPrompt').style.display='none'">×</div>
        <h4 style="color:var(--gold-primary); margin-top:0;">تثبيت التطبيق على آيفون</h4>
        <p style="color:#ccc; font-size:0.9rem;">لتثبيت التطبيق، اضغط على زر المشاركة <img src="data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCA1MCA1MCIgZW5hYmxlLWJhY2tncm91bmQ9Im5ldyAwIDAgNTAgNTAiPjxwYXRoIGQ9Ik0zMC4zIDEzLjdMMjUgOC40bC01LjMgNS4zLTEuNC0xLjRMMjUgNC4ybDYuNyA4LjF6IiBmaWxsPSIjNDQ4YWZmIi8+PHBhdGggZD0iTTI0IDZ2MThoMnYtMTh6IiBmaWxsPSIjNDQ4YWZmIi8+PHBhdGggZD0iTTM1IDM0djhIMTV2LThoLTJ2MTBoMjR2LTEweiIgZmlsbD0iIzQ0OGFmZiIvPjwvc3ZnPg==" style="width:20px; vertical-align:middle;"> في الأسفل، ثم اختر <strong>"إضافة إلى الشاشة الرئيسية"</strong> <i class="fa-regular fa-square-plus"></i>.</p>
    </div>

    <script>
        function toggleMenu() {
            document.getElementById('mobileSidebar').classList.toggle('open');
            document.getElementById('mobileOverlay').classList.toggle('open');
        }

        // PWA & Service Worker Logic
        let deferredPrompt;
        const installBtn = document.getElementById('installAppBtn');
        const installBtnMobile = document.getElementById('installAppBtnMobile');
        const pwaModal = document.getElementById('pwaInstallModal');
        const iosPrompt = document.getElementById('iosInstallPrompt');

        // Check if iOS
        const isIos = () => {
            const userAgent = window.navigator.userAgent.toLowerCase();
            return /iphone|ipad|ipod/.test(userAgent);
        }
        // Check if in Standalone Mode
        const isInStandalone = () => {
            return ('standalone' in window.navigator) && (window.navigator.standalone);
        }

        window.addEventListener('beforeinstallprompt', (e) => {
            // Prevent Chrome 67+ from automatically showing the prompt
            e.preventDefault();
            deferredPrompt = e;
            
            // Show custom UI
            installBtn.style.display = 'flex';
            installBtnMobile.style.display = 'flex';
            
            // Show modal after 3 seconds if not installed
            setTimeout(() => {
                pwaModal.style.display = 'block';
            }, 3000);
        });

        // iOS Logic
        if (isIos() && !isInStandalone()) {
            setTimeout(() => {
                iosPrompt.style.display = 'block';
            }, 4000);
        }

        async function installPWA() {
            if (deferredPrompt) {
                deferredPrompt.prompt();
                const { outcome } = await deferredPrompt.userChoice;
                if (outcome === 'accepted') {
                    installBtn.style.display = 'none';
                    installBtnMobile.style.display = 'none';
                    pwaModal.style.display = 'none';
                }
                deferredPrompt = null;
            }
        }

        function closePwaModal() {
            pwaModal.style.display = 'none';
        }

        // Register Service Worker
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('service-worker.js')
                    .then(reg => console.log('Service Worker Registered'))
                    .catch(err => console.log('Service Worker Failed', err));
            });
        }
    </script>

<?php endif; ?>