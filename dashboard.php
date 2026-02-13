<?php
// dashboard.php - (Royal Phantom V26.2 - Priority Feature & Full Workflow)

ini_set('display_errors', 0);
error_reporting(E_ALL);

require 'auth.php'; 
require 'config.php'; 

// 1. الهوية
$my_role = $_SESSION['role'] ?? 'guest';
$my_name = $_SESSION['name'] ?? 'User';

// مصفوفة الكلمات الحلوة
$sweet_quotes = [
    "صباح الخير! بداية موفقة ويوم مليء بالإنجازات العظيمة بإذن الله.",
    "توكل على الله في كل أمورك، وثق بأن القادم أجمل.",
    "النجاح ليس محطة نصل إليها، بل هو أسلوب حياة وعمل مستمر.",
    "بارك الله في جهودك، إتقانك للعمل هو سر تميزك.",
    "تذكر دائماً أنك عنصر مؤثر وهام في نجاح هذه المنظومة.",
    "ابتسم، فالابتسامة مفتاح القلوب وبوابة التفاؤل.",
    "كل تحدٍ يواجهك هو فرصة جديدة لتثبت جدارتك وقوتك.",
    "العمل بروح الفريق الواحد يصنع المعجزات ويحقق المستحيل.",
    "استعن بالله ولا تعجز، فلكل مجتهد نصيب.",
    "الجودة في العمل ليست مجرد شعار، بل هي أمانة ومبدأ.",
    "ثق بقدراتك، فأنت تملك إمكانات لا حدود لها.",
    "هدوء النفس وراحة البال يبدآن من الرضا عما تقدمه.",
    "اجعل شغفك يقودك، ودع إنجازاتك تتحدث عنك.",
    "صباح الهمة والنشاط، يومك سعيد ومبارك.",
    "لا تؤجل عمل اليوم، فالإنجاز يمنحك شعوراً رائعاً بالراحة.",
    "كن فخوراً بكل خطوة تخطوها نحو أهدافك.",
    "الأمانة في العمل هي أقصر طريق لكسب ثقة الجميع.",
    "تفاءلوا بالخير تجدوه، فرب الخير لا يأتي إلا بالخير.",
    "شكراً لعطائك المستمر، جهودك محل تقدير واحترام.",
    "كل إنجاز عظيم بدأ بفكرة صغيرة وعزيمة قوية.",
    "الرزق بيد الله، والسعي واجب، والتوكل نجاة.",
    "كن مصدراً للطاقة الإيجابية والإلهام لمن حولك.",
    "التميز لا يأتي صدفة، بل هو نتاج الإخلاص والمثابرة.",
    "وقتك ثمين، استثمره فيما ينفعك ويرفع من شأنك.",
    "تعاونك مع زملائك يعكس رقي أخلاقك ومهنيتك.",
    "انظر للمستقبل بأمل، واعمل للحاضر بجد.",
    "الكلمة الطيبة صدقة، والعمل المتقن عبادة.",
    "لا تلتفت للوراء إلا لتتعلم، انطلق نحو الأمام بثقة.",
    "أنت مبدع، وفكرك خلاق، لا تتردد في طرح أفكارك.",
    "راحة الضمير هي الوسادة الأنعم للنوم، فأتقن عملك.",
    "يوم جديد يعني فرصة جديدة وكرم جديد من رب العالمين.",
    "الإصرار يفتح الأبواب المغلقة، والعزيمة تمهد الطرق الوعرة.",
    "حب ما تعمل حتى تعمل ما تحب بإبداع.",
    "التطوير المستمر للذات هو استثمار لا يخسر أبداً.",
    "كن كالغيث، أينما وقع نفع.",
    "التخطيط الجيد هو نصف الطريق نحو النجاح.",
    "تذكر أن الله يراك، فاجعل عملك خالصاً لوجهه الكريم.",
    "الصبر مفتاح الفرج، والعمل مفتاح الرزق.",
    "كل عقبة هي درجة تصعد بها نحو القمة.",
    "بيئة العمل الإيجابية تبدأ منك أنت.",
    "دمت منبعاً للخير والعطاء والتميز.",
    "ثق بأن الله اختار لك هذا المكان لسبب، فأدِّ دورك بأمانة.",
    "النجاح الحقيقي هو أن تترك أثراً طيباً في نفوس الآخرين.",
    "استقبل يومك بقلب راضٍ وعقل منفتح.",
    "النظام والترتيب يوفران الوقت والجهد.",
    "قدرتك على التحمل دليل على قوة شخصيتك.",
    "أنت تستحق النجاح، فلا تتنازل عن أحلامك.",
    "بذكر الله تطمئن القلوب، وبالعمل الصالح تنار الدروب.",
    "كل الشكر والتقدير لكل يد تبني وتعمر بإخلاص.",
    "أبشر بالخير، فالله كريم وعطاؤه واسع."
];
$random_quote = $sweet_quotes[array_rand($sweet_quotes)];

$role_quotes = [
    'admin'       => ['quote' => 'القيادة رؤية وتنفيذ.', 'icon' => 'fa-crown', 'color' => '#d4af37'],
    'manager'     => ['quote' => 'التخطيط نصف الإنجاز.', 'icon' => 'fa-chess-king', 'color' => '#3498db'],
    'accountant'  => ['quote' => 'لغة الأرقام لا تكذب.', 'icon' => 'fa-calculator', 'color' => '#2ecc71'],
    'designer'    => ['quote' => 'الإبداع بلا حدود.', 'icon' => 'fa-palette', 'color' => '#9b59b6'],
    'sales'       => ['quote' => 'العميل شريك نجاح.', 'icon' => 'fa-handshake', 'color' => '#e67e22'],
    'production'  => ['quote' => 'الجودة في التفاصيل.', 'icon' => 'fa-gears', 'color' => '#e74c3c'],
    'monitor'     => ['quote' => 'الدقة هي المعيار.', 'icon' => 'fa-eye', 'color' => '#1abc9c'],
];
$theme = $role_quotes[$my_role] ?? ['quote' => 'مرحباً.', 'icon' => 'fa-user', 'color' => '#888'];
$primary_color = $theme['color'];

$is_admin = ($my_role == 'admin'); 
// المصمم الآن مثل الباقين يرى ولكن التعديل حسب الصلاحيات أدناه
$can_edit = in_array($my_role, ['admin', 'manager', 'sales', 'accountant', 'monitor', 'designer']);

// 2. الحذف
if(isset($_GET['delete_job']) && $is_admin){
    $jid = intval($_GET['delete_job']);
    $tables = ['social_posts', 'job_files', 'job_proofs', 'invoices', 'job_orders'];
    foreach($tables as $tbl) $conn->query("DELETE FROM $tbl WHERE " . ($tbl=='job_orders'?'id':'job_id') . "=$jid");
    header("Location: dashboard.php?msg=deleted"); exit;
}

// 3. معالجة الرفض (إلغاء الطلب مع سبب) و الاعتماد والأولوية
if(isset($_GET['action']) && $can_edit) {
    $type = $_GET['type'] ?? ''; // order OR quote
    $id = intval($_GET['id']);
    $act = $_GET['action'];

    // أ) الرفض مع السبب
    if ($act == 'reject') {
        $reason = $conn->real_escape_string($_GET['reason'] ?? 'تم الرفض من الإدارة');
        if($type == 'order') {
            $sql = "UPDATE job_orders SET status = 'cancelled', current_stage = 'cancelled', notes = CONCAT(IFNULL(notes,''), '\n[سبب الرفض: $reason]') WHERE id = $id";
            $conn->query($sql);
        } elseif ($type == 'quote') {
            $sql = "UPDATE quotes SET status = 'rejected', notes = CONCAT(IFNULL(notes,''), '\n[سبب الرفض: $reason]') WHERE id = $id";
            $conn->query($sql);
        }
        header("Location: dashboard.php?msg=rejected"); exit;
    } 
    // ب) الاعتماد (تفعيل الطلب)
    elseif ($act == 'approve' && $type == 'order') {
        // تحويل الحالة من pending إلى active، والمرحلة إلى briefing
        $conn->query("UPDATE job_orders SET status = 'active', current_stage = 'briefing' WHERE id = $id");
        header("Location: dashboard.php?msg=approved"); exit;
    }
    // ج) تبديل الأولوية (High Priority)
    elseif ($act == 'toggle_priority' && $type == 'order') {
        // ملاحظة: تأكد من إضافة عمود priority للجدول
        $conn->query("UPDATE job_orders SET priority = IF(priority='high', 'normal', 'high') WHERE id = $id");
        header("Location: dashboard.php?msg=priority_changed"); exit;
    }
}

// --- [AJAX HANDLER] معالج البيانات الحية ---
if (isset($_GET['live_updates'])) {
    if (ob_get_length()) ob_clean();
    header('Content-Type: application/json; charset=utf-8');

    $status_filter = $_GET['status'] ?? 'active';
    $type_filter   = $_GET['type'] ?? 'all';
    $search_query  = isset($_GET['q']) ? $conn->real_escape_string($_GET['q']) : '';

    $sql = "SELECT j.*, c.name as client_name FROM job_orders j LEFT JOIN clients c ON j.client_id = c.id WHERE 1=1";
    
    if ($status_filter == 'active') $sql .= " AND current_stage != 'completed' AND current_stage != 'cancelled'";
    elseif ($status_filter == 'late') $sql .= " AND delivery_date < CURDATE() AND current_stage != 'completed' AND current_stage != 'cancelled'";
    elseif ($status_filter == 'completed') $sql .= " AND (current_stage = 'completed' OR current_stage = 'cancelled')";
    
    if ($type_filter != 'all') $sql .= " AND job_type = '$type_filter'";
    if (!empty($search_query)) $sql .= " AND (job_name LIKE '%$search_query%' OR c.name LIKE '%$search_query%' OR j.id = '$search_query')";
    
    // ترتيب حسب الأولوية أولاً، ثم التاريخ
    // تأكد من وجود عمود priority، إذا لم يوجد لن يؤثر هذا الترتيب
    $sql .= " ORDER BY j.priority DESC, j.delivery_date ASC, j.id DESC";
    $result = $conn->query($sql);

    // الإحصائيات
    $count_active = $conn->query("SELECT COUNT(*) FROM job_orders WHERE current_stage != 'completed' AND current_stage != 'cancelled'")->fetch_row()[0] ?? 0;
    $count_late = $conn->query("SELECT COUNT(*) FROM job_orders WHERE delivery_date < CURDATE() AND current_stage != 'completed' AND current_stage != 'cancelled'")->fetch_row()[0] ?? 0;
    
    $stats = ['active' => $count_active, 'late' => $count_late];

    // بناء الكروت
    ob_start();
    
    $progress_map = [
        'pending' => 0, 'cancelled' => 0,
        'briefing' => 5, 'idea_review' => 10, 'content_writing' => 15, 'content_review' => 20,
        'design' => 30, 'designing' => 30, 'design_review' => 35,
        'client_rev' => 40, 'materials' => 50,
        'pre_press' => 60, 'cylinders' => 65, 'extrusion' => 70,
        'printing' => 80, 'cutting' => 85, 'finishing' => 90,
        'delivery' => 95, 'accounting' => 98, 'completed' => 100
    ];
    $stage_ar = [
        'pending'=>'جديد', 'cancelled'=>'ملغي',
        'briefing'=>'تجهيز','idea_review'=>'فكرة','content_writing'=>'محتوى','content_review'=>'مراجعة','design'=>'تصميم','designing'=>'تصميم','design_review'=>'تدقيق','client_rev'=>'عميل','pre_press'=>'CTP','printing'=>'طباعة','finishing'=>'تشطيب','delivery'=>'تسليم','completed'=>'أرشيف','accounting'=>'مالية','materials'=>'خامات','cylinders'=>'سلندرات','extrusion'=>'سحب'
    ];
    $icons = ['print'=>'fa-print','carton'=>'fa-box-open','plastic'=>'fa-bag-shopping','social'=>'fa-hashtag','web'=>'fa-laptop-code','design_only'=>'fa-pen-nib'];
    
    if ($result && $result->num_rows > 0): 
        while($row = $result->fetch_assoc()): 
            $st = $row['current_stage'];
            $priority = $row['priority'] ?? 'normal';
            $prog = $progress_map[$st] ?? 5;
            $st_label = $stage_ar[$st] ?? $st;
            $icon = $icons[$row['job_type']] ?? 'fa-circle';
            
            $days = 0; $late = false; $urgent = false; $day_msg = '';
            $d_date = $row['delivery_date'];
            
            if ($st == 'completed') {
                $day_msg = "مكتملة";
            } elseif ($st == 'cancelled') {
                $day_msg = "ملغي";
            } elseif (!empty($d_date) && $d_date != '0000-00-00') {
                try {
                    $diff = (new DateTime())->diff(new DateTime($d_date));
                    $days = (int)$diff->format('%r%a');
                    if ($days < 0) { $late = true; $day_msg = "متأخر " . abs($days) . " يوم"; }
                    elseif ($days <= 2) { $urgent = true; $day_msg = "باقي $days يوم"; }
                    else { $day_msg = "باقي $days يوم"; }
                } catch (Exception $e) { $day_msg = "-"; }
            } else { $day_msg = "غير محدد"; }

            $card_class = 'ph-card-normal';
            $bar_color = 'var(--ae-gold)';
            
            if ($st == 'completed') { $card_class = 'ph-card-done'; $bar_color = '#2ecc71'; }
            elseif ($st == 'cancelled') { $card_class = 'ph-card-done'; $bar_color = '#e74c3c'; }
            elseif ($late) { $card_class = 'ph-card-late'; $bar_color = '#e74c3c'; }
            elseif ($urgent) { $card_class = 'ph-card-urgent'; $bar_color = '#f1c40f'; }
            
            // كلاس الأولوية العالية
            if ($priority == 'high') { $card_class .= ' ph-card-high'; }
    ?>
    <div class="ph-card <?php echo $card_class; ?>">
        <div class="ph-card-header">
            <span class="ph-id">#<?php echo $row['id']; ?></span>
            <div style="display:flex; gap:10px; align-items:center;">
                <?php if($priority == 'high'): ?><i class="fa-solid fa-fire fa-beat" style="color:#e74c3c;" title="أولوية قصوى"></i><?php endif; ?>
                <span class="ph-icon"><i class="fa-solid <?php echo $icon; ?>"></i></span>
            </div>
        </div>
        <div class="ph-card-body" onclick="window.location.href='job_details.php?id=<?php echo $row['id']; ?>'">
            <h3 class="ph-job-title"><?php echo $row['job_name']; ?></h3>
            <div class="ph-client"><i class="fa-regular fa-user"></i> <?php echo $row['client_name']; ?></div>
            <div class="ph-prog-container">
                <div class="ph-prog-labels">
                    <span><?php echo $st_label; ?></span>
                    <span><?php echo $prog; ?>%</span>
                </div>
                <div class="ph-prog-bar">
                    <div class="ph-prog-fill" style="width:<?php echo $prog; ?>%; background:<?php echo $bar_color; ?>; box-shadow: 0 0 10px <?php echo $bar_color; ?>;"></div>
                </div>
            </div>
        </div>
        <div class="ph-card-footer">
            <div class="ph-status-badge <?php echo $late?'late':($urgent?'urgent':'normal'); ?>">
                <i class="fa-regular fa-clock"></i> <?php echo $day_msg; ?>
            </div>
            <div class="ph-actions">
                <a href="job_details.php?id=<?php echo $row['id']; ?>" class="ph-btn ph-btn-enter">دخول</a>
                <?php if($can_edit && $st!='completed' && $st!='cancelled'): ?>
                    <a href="edit_job.php?id=<?php echo $row['id']; ?>" class="ph-btn ph-btn-icon"><i class="fa-solid fa-pen"></i></a>
                    <a href="dashboard.php?action=toggle_priority&type=order&id=<?php echo $row['id']; ?>" class="ph-btn ph-btn-icon" style="<?php echo ($priority=='high'?'color:#e74c3c;border-color:#e74c3c;':''); ?>" title="تغيير الأولوية">
                        <i class="fa-solid fa-fire"></i>
                    </a>
                <?php endif; ?>
                <?php if($is_admin): ?>
                    <a href="?delete_job=<?php echo $row['id']; ?>" class="ph-btn ph-btn-icon ph-btn-del" onclick="return confirm('حذف نهائي؟')"><i class="fa-solid fa-trash"></i></a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endwhile; else: ?>
        <div style="grid-column:1/-1; text-align:center; padding:80px 0; color:#666;">
            <i class="fa-solid fa-wind fa-3x"></i><br><br>لا توجد عمليات تطابق البحث
        </div>
    <?php endif; 
    $grid_html = ob_get_clean();

    ob_start();
    $alerts_q = $conn->query("SELECT j.id, j.job_name, (SELECT status FROM job_proofs WHERE job_id=j.id ORDER BY id DESC LIMIT 1) as st FROM job_orders j WHERE j.current_stage IN ('client_rev','design_review')");
    $alerts = [];
    if($alerts_q) while($r = $alerts_q->fetch_assoc()) $alerts[] = $r;
    
    if(!empty($alerts)): ?>
    <div class="ticker-content">
        <?php foreach($alerts as $al): 
            $s = $al['st']; $col = '#f1c40f'; $txt = 'بانتظار العميل';
            if(strpos($s,'reject')!==false){ $col='#e74c3c'; $txt='مطلوب تعديل'; }
            elseif(strpos($s,'approv')!==false){ $col='#2ecc71'; $txt='تم الاعتماد'; }
        ?>
        <div class="ticker-item">
            <span class="dot" style="background:<?php echo $col; ?>"></span>
            <span><?php echo $txt; ?>: <?php echo $al['job_name']; ?></span>
            <a href="job_details.php?id=<?php echo $al['id']; ?>">عرض</a>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif;
    $ticker_html = ob_get_clean();

    $last_job = $conn->query("SELECT id, job_name FROM job_orders ORDER BY id DESC LIMIT 1")->fetch_assoc();
    $last_review = $conn->query("SELECT p.status, j.job_name, p.job_id FROM job_proofs p JOIN job_orders j ON p.job_id=j.id ORDER BY p.id DESC LIMIT 1")->fetch_assoc();

    echo json_encode(['stats' => $stats, 'grid' => $grid_html, 'ticker' => $ticker_html, 'last_job' => $last_job, 'last_review' => $last_review]);
    exit;
}

require 'header.php'; 
?>

<style>
    :root { 
        --bg: #050505; 
        --card-bg: #141414; 
        --ae-gold: #d4af37;
        --ae-gold-light: #f1d592; 
        --border: rgba(212, 175, 55, 0.15); 
        --text: #eee;
        --red-glow: 0 0 15px rgba(231, 76, 60, 0.4);
        --gold-glow: 0 0 20px rgba(212, 175, 55, 0.2);
        --high-prio-glow: 0 0 15px rgba(231, 76, 60, 0.3), 0 0 5px rgba(241, 196, 15, 0.3);
    }
    body { background-color: var(--bg); font-family: 'Cairo', sans-serif; color: var(--text); padding-bottom: 80px; }

    /* Sweet Bar */
    .sweet-bar {
        background: linear-gradient(90deg, #111, #000); 
        padding: 12px 20px; margin-bottom: 25px;
        border-radius: 50px; border: 1px solid var(--border); 
        text-align: center; font-weight: bold; color: #fff;
        display: flex; align-items: center; justify-content: center; gap: 10px;
        box-shadow: var(--gold-glow); 
        animation: slideDown 0.6s cubic-bezier(0.2, 0.8, 0.2, 1);
    }
    .sweet-bar i { color: var(--ae-gold); animation: pulse 2s infinite; }
    .sweet-text { 
        background: linear-gradient(to right, #fff, #bbb, #fff); 
        -webkit-background-clip: text; -webkit-text-fill-color: transparent; 
        background-size: 200% auto; animation: shine 5s linear infinite;
    }
    @keyframes slideDown { from { transform: translateY(-20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
    @keyframes pulse { 0% { transform: scale(1); opacity:0.8; } 50% { transform: scale(1.2); opacity:1; } 100% { transform: scale(1); opacity:0.8; } }
    @keyframes shine { to { background-position: 200% center; } }

    /* Hero & General */
    .ph-hero { 
        background: linear-gradient(to bottom, #0a0a0a, #050505); 
        border-bottom: 1px solid var(--border); 
        padding: 20px 0; margin-bottom: 25px; 
        display: flex; align-items: center; justify-content: space-between; 
    }
    .ph-user { display: flex; align-items: center; gap: 15px; }
    .ph-avatar { 
        width: 60px; height: 60px; border-radius: 50%; 
        border: 2px solid var(--ae-gold); padding: 2px; 
        box-shadow: 0 0 15px rgba(212, 175, 55, 0.3);
    }
    
    .ph-welcome { display: flex; flex-direction: column; justify-content: center; }
    .ph-welcome h2 { margin: 0; font-size: 1.5rem; color: var(--ae-gold); text-shadow: 0 0 10px rgba(212,175,55,0.2); }
    .ph-welcome span { color: #fff; }
    
    .ph-kpi { text-align: left; }
    .ph-num { font-size: 2.2rem; font-weight: 900; line-height: 1; color: #fff; text-shadow: 0 0 20px rgba(255,255,255,0.1); }
    .ph-lbl { font-size: 0.8rem; color: #888; text-transform: uppercase; letter-spacing: 1px; }

    /* Filters */
    .ph-filters { 
        background: rgba(20, 20, 20, 0.6); backdrop-filter: blur(10px);
        border: 1px solid var(--border); border-radius: 16px; 
        padding: 15px; margin-bottom: 30px; 
        display: flex; flex-wrap: wrap; gap: 15px; align-items: center; 
        box-shadow: 0 10px 30px rgba(0,0,0,0.3);
    }
    .ph-select { 
        background: #000; color: #ccc; border: 1px solid #333; 
        padding: 10px 20px; border-radius: 8px; font-family: 'Cairo'; 
        outline: none; cursor: pointer; transition: 0.3s;
    }
    .ph-select:focus { border-color: var(--ae-gold); color: #fff; }
    .ph-search { 
        flex: 1; min-width: 200px; background: #000; border: 1px solid #333; 
        padding: 10px 15px; border-radius: 8px; color: #fff; transition: 0.3s;
    }
    .ph-search:focus { border-color: var(--ae-gold); box-shadow: 0 0 10px rgba(212,175,55,0.1); }

    /* Buttons */
    .btn-add { 
        background: linear-gradient(90deg, var(--ae-gold), #b8860b); 
        color: #000; padding: 10px 25px; border-radius: 8px; 
        font-weight: bold; text-decoration: none; 
        display: flex; align-items: center; gap: 8px; 
        box-shadow: 0 5px 15px rgba(212, 175, 55, 0.2); transition: 0.3s;
    }
    .btn-add:hover { transform: translateY(-2px); box-shadow: 0 10px 20px rgba(212, 175, 55, 0.3); }
    
    .btn-archive { 
        background: rgba(255,255,255,0.05); color: #ccc; 
        padding: 10px 20px; border-radius: 8px; text-decoration: none; 
        border: 1px solid #333; display: flex; align-items: center; gap: 8px; transition: 0.3s;
    }
    .btn-archive:hover, .btn-archive.active { background: #eee; color: #000; }

    /* Grid & Cards (Royal Style) */
    .ph-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 25px; }
    .ph-card { 
        background: var(--card-bg); 
        border: 1px solid var(--border); 
        border-radius: 16px; overflow: hidden; position: relative; 
        transition: all 0.3s ease; display: flex; flex-direction: column;
    }
    .ph-card:hover { 
        transform: translateY(-5px); 
        border-color: var(--ae-gold); 
        box-shadow: 0 10px 30px rgba(0,0,0,0.5);
    }
    /* ستايلات الحالات */
    .ph-card-late { border-color: #c0392b; animation: pulseRed 2s infinite; }
    .ph-card-urgent { border-color: #f39c12; }
    .ph-card-done { opacity: 0.6; filter: grayscale(0.8); }
    
    /* ستايل الأولوية العالية الجديد */
    .ph-card-high { 
        border: 1px solid #e74c3c; 
        box-shadow: var(--high-prio-glow);
    }

    @keyframes pulseRed { 0% { box-shadow: 0 0 0 rgba(192, 57, 43, 0); } 50% { box-shadow: var(--red-glow); } 100% { box-shadow: 0 0 0 rgba(192, 57, 43, 0); } }
    
    .ph-card-header { 
        padding: 18px 22px; background: rgba(255,255,255,0.02); 
        border-bottom: 1px solid rgba(255,255,255,0.05); 
        display: flex; justify-content: space-between; align-items: center; 
    }
    .ph-id { color: #666; font-family: monospace; font-size: 0.95rem; font-weight: bold; }
    .ph-icon { color: var(--ae-gold); filter: drop-shadow(0 0 5px rgba(212,175,55,0.5)); }
    
    .ph-card-body { padding: 22px; flex: 1; cursor: pointer; }
    .ph-job-title { margin: 0 0 8px 0; font-size: 1.2rem; color: #fff; font-weight: 700; }
    .ph-client { color: #888; font-size: 0.9rem; margin-bottom: 15px; display: flex; align-items: center; gap: 6px; }
    
    .ph-prog-container { margin-top: 15px; }
    .ph-prog-labels { display: flex; justify-content: space-between; font-size: 0.8rem; color: #aaa; margin-bottom: 6px; }
    .ph-prog-bar { height: 6px; background: #222; border-radius: 3px; overflow: hidden; border: 1px solid #333; }
    .ph-prog-fill { height: 100%; border-radius: 3px; transition: width 0.6s cubic-bezier(0.2, 0.8, 0.2, 1); }
    
    .ph-card-footer { 
        padding: 15px 22px; background: #0a0a0a; 
        border-top: 1px solid rgba(255,255,255,0.05); 
        display: flex; justify-content: space-between; align-items: center; 
    }
    .ph-status-badge { font-size: 0.8rem; padding: 5px 12px; border-radius: 20px; display: flex; align-items: center; gap: 6px; }
    .ph-status-badge.late { color: #e74c3c; background: rgba(231,76,60,0.1); font-weight: bold; }
    .ph-status-badge.urgent { color: #f1c40f; background: rgba(241,196,15,0.1); }
    .ph-status-badge.normal { color: #2ecc71; background: rgba(46,204,113,0.1); }
    
    .ph-actions { display: flex; gap: 8px; }
    .ph-btn { 
        background: #222; border: 1px solid #333; color: #ccc; 
        padding: 7px 14px; border-radius: 8px; font-size: 0.85rem; 
        text-decoration: none; transition: 0.2s; cursor: pointer; 
    }
    .ph-btn:hover { background: #fff; color: #000; border-color: #fff; }
    .ph-btn-enter { 
        background: linear-gradient(135deg, var(--ae-gold), #b8860b); 
        color: #000; font-weight: bold; border: none; 
        box-shadow: 0 4px 10px rgba(0,0,0,0.3);
    }
    .ph-btn-enter:hover { box-shadow: 0 0 15px rgba(212,175,55,0.4); transform: translateY(-1px); }
    .ph-btn-del:hover { background: #e74c3c; border-color: #e74c3c; color: #fff; }

    /* Ticker */
    .ticker-bar { 
        background: #111; border: 1px solid var(--border); border-radius: 12px; 
        height: 45px; overflow: hidden; margin-bottom: 30px; 
        display: flex; align-items: center; padding: 0 15px; 
        box-shadow: inset 0 0 20px rgba(0,0,0,0.5);
    }
    .ticker-content { display: flex; gap: 40px; animation: scrollTicker 25s linear infinite; white-space: nowrap; }
    @keyframes scrollTicker { 0% { transform: translateX(100%); } 100% { transform: translateX(-100%); } }
    .ticker-item { display: flex; align-items: center; gap: 10px; color: #ccc; font-size: 0.9rem; }
    .ticker-item .dot { width: 8px; height: 8px; border-radius: 50%; box-shadow: 0 0 5px currentColor; }
    .ticker-item a { color: var(--ae-gold); text-decoration: none; border-bottom: 1px dashed var(--ae-gold); }

    @media (max-width: 768px) {
        .ph-filters { flex-direction: column; align-items: stretch; gap: 10px; }
        .ph-search { width: 100%; }
        .btn-archive { margin-right: 0; justify-content: center; }
        .btn-add { justify-content: center; }
    }
</style>

<div class="container">
    
    <div id="live-ticker" class="ticker-bar"></div>

    <div class="sweet-bar">
        <i class="fa-solid fa-star"></i>
        <span class="sweet-text"><?php echo $random_quote; ?></span>
        <i class="fa-solid fa-star"></i>
    </div>

    <?php 
    $n_quotes = $conn->query("SELECT id FROM quotes WHERE total_amount=0 AND status='pending'");
    $n_orders = $conn->query("SELECT id, job_name FROM job_orders WHERE client_id != 0 AND status = 'pending'");
    
    if(($n_quotes && $n_quotes->num_rows > 0) || ($n_orders && $n_orders->num_rows > 0)): 
    ?>
    <div style="background:rgba(212, 175, 55, 0.05); border:1px solid var(--ae-gold); padding:20px; margin-bottom:25px; border-radius:15px; position:relative; overflow:hidden;">
        <div style="position:absolute; top:0; left:0; width:4px; height:100%; background:var(--ae-gold); box-shadow: 0 0 15px var(--ae-gold);"></div>
        <h3 style="color:var(--ae-gold); margin:0 0 15px 0; font-size:1.1rem; display:flex; align-items:center; gap:10px;">
            <i class="fa-solid fa-bell fa-shake"></i> طلبات واردة من البوابة
        </h3>
        <div style="display:grid; gap:10px;">
            <?php while($q = $n_quotes->fetch_assoc()): ?>
            <div style="display:flex; justify-content:space-between; align-items:center; background:#111; padding:12px; border-radius:8px; border:1px solid #333;">
                <span style="color:#3498db; font-weight:bold;"><i class="fa-solid fa-file-invoice"></i> طلب تسعير #<?php echo $q['id']; ?></span>
                <div style="display:flex; gap:8px;">
                    <a href="view_quote.php?id=<?php echo $q['id']; ?>" class="ph-btn" style="font-size:0.85rem;">تسعير الآن</a>
                    <button onclick="rejectItem('quote', <?php echo $q['id']; ?>)" class="ph-btn" style="font-size:0.85rem; background:#c0392b; border-color:#c0392b; color:#fff;">رفض ❌</button>
                </div>
            </div>
            <?php endwhile; ?>

            <?php while($o = $n_orders->fetch_assoc()): ?>
            <div style="display:flex; justify-content:space-between; align-items:center; background:#111; padding:12px; border-radius:8px; border:1px solid #333;">
                <span style="color:#2ecc71; font-weight:bold;"><i class="fa-solid fa-industry"></i> أمر شغل #<?php echo $o['id']; ?>: <?php echo $o['job_name']; ?></span>
                <div style="display:flex; gap:8px;">
                    <a href="dashboard.php?action=approve&type=order&id=<?php echo $o['id']; ?>" class="ph-btn" style="font-size:0.85rem; background:linear-gradient(135deg, #27ae60, #2ecc71); color:#fff; border:none;">اعتماد ✅</a>
                    <button onclick="rejectItem('order', <?php echo $o['id']; ?>)" class="ph-btn" style="font-size:0.85rem; background:#c0392b; border-color:#c0392b; color:#fff;">رفض ❌</button>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
    </div>
    <?php endif; ?>
    <div class="ph-hero">
        <div class="ph-user">
            <?php $u_img = $_SESSION['avatar'] ?? "https://ui-avatars.com/api/?name=$my_name&background=random&color=fff"; ?>
            <img src="<?php echo $u_img; ?>" class="ph-avatar">
            
            <div class="ph-welcome">
                <div style="color:var(--ae-gold); font-size:0.85rem; font-weight:bold; letter-spacing:1px; margin-bottom:5px;">
                    <?php echo date('d M, Y'); ?>
                </div>
                <?php 
                    $h = date('H'); 
                    $greet = ($h < 12) ? 'صباح الخير ☀️' : 'مساء الخير 🌙'; 
                ?>
                <h2><?php echo $greet; ?>، <span style="color:#fff;"><?php echo explode(' ', $my_name)[0]; ?></span> 👋</h2>
                <div style="font-size:0.85rem; color:#666;">نتمنى لك يوماً مثمراً في Arab Eagles</div>
            </div>
        </div>
        
        <div class="ph-kpi" id="live-stats">
            <div class="ph-num">--</div>
            <div class="ph-lbl">عملية نشطة</div>
        </div>
    </div>

    <form method="GET" class="ph-filters">
        <select name="status" class="ph-select" onchange="this.form.submit()">
            <option value="active" <?php echo ($_GET['status']??'active')=='active'?'selected':''; ?>>⚡ العمليات الجارية</option>
            <option value="late" <?php echo ($_GET['status']??'')=='late'?'selected':''; ?>>🔥 المتأخرة فقط</option>
            <option value="all" <?php echo ($_GET['status']??'')=='all'?'selected':''; ?>>📂 الكل</option>
        </select>
        
        <select name="type" class="ph-select" onchange="this.form.submit()">
            <option value="all">🌐 كل الأقسام</option>
            <option value="print" <?php echo ($_GET['type']??'')=='print'?'selected':''; ?>>🖨️ طباعة</option>
            <option value="carton" <?php echo ($_GET['type']??'')=='carton'?'selected':''; ?>>📦 كرتون</option>
            <option value="plastic" <?php echo ($_GET['type']??'')=='plastic'?'selected':''; ?>>🛍️ بلاستيك</option>
            <option value="social" <?php echo ($_GET['type']??'')=='social'?'selected':''; ?>>📱 سوشيال</option>
        </select>
        
        <input type="text" name="q" class="ph-search" placeholder="بحث سريع..." value="<?php echo htmlspecialchars($search_query); ?>">
        
        <a href="?status=completed" class="btn-archive <?php echo ($_GET['status']??'')=='completed'?'active':''; ?>">
            <i class="fa-solid fa-box-archive"></i> الأرشيف
        </a>

        <?php if(in_array($my_role, ['admin', 'manager', 'sales'])): ?>
            <a href="add_job.php" class="btn-add"><i class="fa-solid fa-plus"></i> إضافة</a>
        <?php endif; ?>
    </form>

    <div class="ph-grid" id="live-grid">
        <div style="grid-column:1/-1; text-align:center; padding:80px 0; color:#444;">
            <i class="fa-solid fa-circle-notch fa-spin fa-2x"></i><br>جاري الاتصال...
        </div>
    </div>

</div>

<audio id="notif-sound" src="assets/notification.mp3" preload="auto"></audio>

<script>
    const currentParams = new URLSearchParams(window.location.search);
    let lastJobId = -1;
    let lastReviewId = -1;

    if ("Notification" in window && Notification.permission !== "granted") {
        Notification.requestPermission();
    }

    function rejectItem(type, id) {
        let reason = prompt("ما هو سبب الرفض؟ (سيظهر للعميل)");
        if (reason !== null) {
            if(reason.trim() === "") reason = "لم يتم ذكر سبب محدد";
            window.location.href = `dashboard.php?action=reject&type=${type}&id=${id}&reason=${encodeURIComponent(reason)}`;
        }
    }

    function safeNotify(title, body, id) {
        const sound = document.getElementById('notif-sound');
        if(sound) sound.play().catch(e => {});

        if ("Notification" in window && Notification.permission === "granted") {
            try {
                if("serviceWorker" in navigator && navigator.serviceWorker.controller){
                    navigator.serviceWorker.ready.then(reg => {
                        reg.showNotification(title, { body: body, icon: 'assets/img/icon-192x192.png', data: { job_id: id } });
                    });
                } else {
                    new Notification(title, { body: body, icon: 'assets/img/icon-192x192.png' });
                }
            } catch(e) { console.log("Notify Error"); }
        }
    }

    function fetchUpdates() {
        fetch('dashboard.php?live_updates=1&' + currentParams.toString())
            .then(r => {
                if (!r.ok) throw new Error("Network error");
                return r.json();
            })
            .then(data => {
                document.querySelector('#live-stats .ph-num').textContent = data.stats.active;
                if(data.stats.late > 0) {
                    document.querySelector('#live-stats .ph-num').style.color = '#e74c3c';
                    document.querySelector('#live-stats .ph-lbl').textContent = data.stats.late + ' متأخرة!';
                } else {
                    document.querySelector('#live-stats .ph-num').style.color = '#fff';
                    document.querySelector('#live-stats .ph-lbl').textContent = 'عملية نشطة';
                }

                document.getElementById('live-grid').innerHTML = data.grid;

                const ticker = document.getElementById('live-ticker');
                if(data.ticker.trim()){
                    if(ticker.innerHTML != data.ticker) ticker.innerHTML = data.ticker;
                } else { ticker.innerHTML = ''; }

                if(data.last_job && data.last_job.id) {
                    let newId = parseInt(data.last_job.id);
                    if(lastJobId !== -1 && newId > lastJobId) {
                        safeNotify("🚀 عملية جديدة", data.last_job.job_name, newId);
                    }
                    lastJobId = newId;
                }
                
                if(data.last_review && data.last_review.job_id) {
                    let currentReviewKey = data.last_review.job_id + data.last_review.status;
                    if(lastReviewId !== -1 && currentReviewKey !== lastReviewId) {
                        let msg = data.last_review.status.includes('rejected') ? "تعديلات مطلوبة" : "تم الاعتماد";
                        safeNotify("🔔 تحديث حالة", `${msg}: ${data.last_review.job_name}`, data.last_review.job_id);
                    }
                    lastReviewId = currentReviewKey;
                }
            })
            .catch(e => {
                console.log("Connection paused...");
            });
    }

    fetchUpdates();
    setInterval(fetchUpdates, 5000);
    
    document.addEventListener("visibilitychange", () => {
        if (!document.hidden) fetchUpdates();
    });
</script>

<?php include 'footer.php'; ?>