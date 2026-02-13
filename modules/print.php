<?php
// print.php - (Royal Ops Room V38.4 - Token Auto-Fix)

// 1. إعدادات السيرفر القصوى (للملفات الكبيرة)
@ini_set('upload_max_filesize', '2048M'); // 2GB
@ini_set('post_max_size', '2048M');
@ini_set('max_execution_time', '3600'); // ساعة كاملة
@ini_set('max_input_time', '3600'); // مهم جداً للرفع البطيء
@ini_set('memory_limit', '2048M');

// ============================================================
// 🌟 معالجة الرفع السريع (AJAX) - للملفات العامة
// ============================================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['ajax_action']) && $_POST['ajax_action'] == 'universal_upload') {
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    require 'config.php';

    $response = ['status' => 'error', 'msg' => 'حدث خطأ غير معروف'];

    try {
        if (empty($_FILES['ajax_files']['name'][0])) throw new Exception("لم يتم استلام ملفات");

        $job_id = intval($_POST['job_id']);
        $stage = $_POST['stage'];
        $uploader = $_SESSION['name'] ?? 'System';
        $desc_base = $_POST['file_desc'] ?? 'ملف';

        $folder_map = [
            'briefing' => 'uploads/briefs',
            'design' => 'uploads/proofs',
            'materials' => 'uploads/materials',
            'pre_press_supplies' => 'uploads/source',
            'pre_press' => 'uploads/production',
            'printing' => 'uploads/production'
        ];
        
        $target_dir = $folder_map[$stage] ?? 'uploads/misc';
        if (!file_exists($target_dir)) @mkdir($target_dir, 0777, true);

        $success_count = 0;
        $errors = [];

        foreach ($_FILES['ajax_files']['name'] as $i => $name) {
            if ($_FILES['ajax_files']['error'][$i] === 0) {
                $ext = pathinfo($name, PATHINFO_EXTENSION);
                $clean_name = preg_replace('/[^A-Za-z0-9]/', '', pathinfo($name, PATHINFO_FILENAME));
                $new_name = time() . "_{$job_id}_{$clean_name}." . $ext;
                $target_file = "$target_dir/$new_name";
                
                $file_desc = (count($_FILES['ajax_files']['name']) > 1) ? "$desc_base (" . ($i+1) . ")" : $desc_base;

                if (move_uploaded_file($_FILES['ajax_files']['tmp_name'][$i], $target_file)) {
                    if ($stage == 'design') {
                        $conn->query("INSERT INTO job_proofs (job_id, file_path, description, status, client_comment) VALUES ($job_id, '$target_file', '$file_desc', 'pending', NULL)");
                    } else {
                        $conn->query("INSERT INTO job_files (job_id, file_path, stage, description, uploaded_by) VALUES ($job_id, '$target_file', '$stage', '$file_desc', '$uploader')");
                    }
                    $success_count++;
                } else {
                    $errors[] = "فشل نقل: $name";
                }
            } else {
                $errors[] = "خطأ سيرفر رقم: " . $_FILES['ajax_files']['error'][$i];
            }
        }

        if ($success_count > 0) $response = ['status' => 'success', 'msg' => "تم رفع $success_count ملف بنجاح"];
        else $response = ['status' => 'error', 'msg' => implode(" | ", $errors)];

    } catch (Exception $e) {
        $response = ['status' => 'error', 'msg' => $e->getMessage()];
    }
    echo json_encode($response);
    exit;
}

// ============================================================
// الصفحة الرئيسية
// ============================================================
ob_start();
ini_set('display_errors', 0);
require 'config.php';

$my_role = $_SESSION['role'] ?? 'guest';
$is_admin = in_array($my_role, ['admin', 'manager']);
$is_designer_or_admin = in_array($my_role, ['admin', 'designer', 'manager']);

// سير العمل
$workflow = [
    'briefing' => ['label'=>'1. التجهيز', 'prev'=>null, 'next'=>'design'],
    'design' => ['label'=>'2. التصميم', 'prev'=>'briefing', 'next'=>'client_rev'],
    'client_rev' => ['label'=>'3. المراجعة', 'prev'=>'design', 'next'=>'materials'],
    'materials' => ['label'=>'4. الخامات', 'prev'=>'client_rev', 'next'=>'pre_press'],
    'pre_press' => ['label'=>'5. التجهيز (CTP)', 'prev'=>'materials', 'next'=>'printing'],
    'printing' => ['label'=>'6. الطباعة', 'prev'=>'pre_press', 'next'=>'finishing'],
    'finishing' => ['label'=>'7. التشطيب', 'prev'=>'printing', 'next'=>'delivery'],
    'delivery' => ['label'=>'8. التسليم', 'prev'=>'finishing', 'next'=>'accounting'],
    'accounting' => ['label'=>'9. الحسابات', 'prev'=>'delivery', 'next'=>'completed'],
    'completed' => ['label'=>'10. الأرشيف', 'prev'=>'accounting', 'next'=>null]
];

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$job_query = $conn->query("SELECT j.*, c.name as client_name, c.phone as client_phone FROM job_orders j JOIN clients c ON j.client_id = c.id WHERE j.id = $id");
if ($job_query->num_rows == 0) die("أمر الشغل غير موجود");
$job = $job_query->fetch_assoc();
$curr = $job['current_stage'];
$prev_stage = $workflow[$curr]['prev'] ?? null;

// دوال المساعدة
function get_wa_link($phone, $text) {
    $phone = preg_replace('/[^0-9]/', '', $phone);
    if (strlen($phone) == 11 && substr($phone, 0, 2) == '01') $phone = '2' . $phone;
    elseif (strlen($phone) == 10 && substr($phone, 0, 2) == '05') $phone = '966' . substr($phone, 1);
    return "https://wa.me/$phone?text=" . urlencode($text);
}

// استخراج المواصفات
$raw_text = $job['job_details'] ?? '';
function get_spec($pattern, $text, $default = '') {
    preg_match($pattern, $text, $matches);
    return isset($matches[1]) ? trim($matches[1]) : $default;
}
$specs = [
    'p_len' => get_spec('/مقاس الورق:.*?([\d\.]+)\s*x/u', $raw_text, '0'),
    'p_wid' => get_spec('/مقاس الورق:.*?[\d\.]+\s*x\s*([\d\.]+)/u', $raw_text, '0'),
    'c_len' => get_spec('/مقاس القص:.*?([\d\.]+)\s*x/u', $raw_text, '0'),
    'c_wid' => get_spec('/مقاس القص:.*?[\d\.]+\s*x\s*([\d\.]+)/u', $raw_text, '0'),
    'machine' => get_spec('/الماكينة: (.*?)(?:\||$)/u', $raw_text, ''),
    'print_face' => get_spec('/الوجه: (.*?)(?:\||$)/u', $raw_text, ''),
    'colors' => get_spec('/الألوان: (.*?)(?:\||$)/u', $raw_text, ''),
    'zinc' => get_spec('/الزنكات: ([\d\.]+)/u', $raw_text, '0'),
];

$history_notes = [];
preg_match_all('/\[(.*?)\]:\s*(.*?)(?=\n\[|$)/s', $job['notes'] ?? '', $matches, PREG_SET_ORDER);
$history_notes = $matches;

// 🛠️ معالجة الطلبات (POST)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && !isset($_POST['ajax_action'])) {
    
    function safe_redirect($id) { echo "<script>window.location.href = 'job_details.php?id=$id';</script>"; exit; }

    // 1. التحكم الجبري
    if (isset($_POST['admin_change_stage']) && $is_admin) {
        $new_stage = $_POST['target_stage'];
        $conn->query("UPDATE job_orders SET current_stage='$new_stage' WHERE id=$id");
        safe_redirect($id);
    }

    // 2. حذف الملفات
    if (isset($_POST['delete_item'])) {
        $tbl = ($_POST['type'] == 'proof') ? 'job_proofs' : 'job_files';
        $item_id = intval($_POST['item_id']);
        $f = $conn->query("SELECT file_path FROM $tbl WHERE id=$item_id")->fetch_assoc();
        if ($f && !empty($f['file_path'])) {
            $abs_path = __DIR__ . '/' . $f['file_path'];
            if (file_exists($abs_path)) unlink($abs_path);
            elseif (file_exists($f['file_path'])) unlink($f['file_path']);
        }
        $conn->query("DELETE FROM $tbl WHERE id=$item_id");
        safe_redirect($id);
    }

    // 3. التجهيز
    if (isset($_POST['save_brief_notes'])) {
        $note = $conn->real_escape_string($_POST['notes']);
        $conn->query("UPDATE job_orders SET notes = CONCAT(IFNULL(notes, ''), '\n[📝 تجهيز]: $note'), current_stage='design' WHERE id=$id");
        safe_redirect($id);
    }

    // 4. التصميم والمراجعة
    if (isset($_POST['send_to_review'])) {
        $conn->query("UPDATE job_orders SET current_stage='client_rev' WHERE id=$id");
        safe_redirect($id);
    }
    if (isset($_POST['finalize_review'])) {
        $conn->query("UPDATE job_orders SET current_stage='materials' WHERE id=$id");
        safe_redirect($id);
    }
    if (isset($_POST['return_stage'])) {
        $prev = $_POST['prev_target'];
        $reason = $conn->real_escape_string($_POST['return_reason']);
        $note = "\n[⚠️ تراجع]: $reason";
        $conn->query("UPDATE job_orders SET current_stage='$prev', notes = CONCAT(IFNULL(notes, ''), '$note') WHERE id=$id");
        safe_redirect($id);
    }

    // 5. الخامات
    if (isset($_POST['save_materials']) || isset($_POST['finish_materials'])) {
        $items = $_POST['item_text'] ?? [];
        $suppliers = $_POST['supplier_phone'] ?? [];
        if (!file_exists('uploads/materials')) @mkdir('uploads/materials', 0777, true);
        if (is_array($items)) {
            foreach ($items as $i => $text) {
                if (!empty($text)) {
                    $file_link = '';
                    if (!empty($_FILES['item_file']['name'][$i])) {
                        $ext = pathinfo($_FILES['item_file']['name'][$i], PATHINFO_EXTENSION);
                        $target = "uploads/materials/" . time() . "_mat_$i.$ext";
                        if (move_uploaded_file($_FILES['item_file']['tmp_name'][$i], $target)) { $file_link = $target; }
                    }
                    $desc = $conn->real_escape_string($text);
                    $supp_phone = $conn->real_escape_string($suppliers[$i] ?? '');
                    $conn->query("INSERT INTO job_files (job_id, file_path, stage, description, uploaded_by) VALUES ($id, '$file_link', 'materials', '$desc', '$supp_phone')");
                }
            }
        }
        if (isset($_POST['finish_materials'])) { $conn->query("UPDATE job_orders SET current_stage='pre_press' WHERE id=$id"); }
        safe_redirect($id);
    }

    // 6. الزنكات (CTP) - منطق الرفع المتعدد
    if (isset($_POST['save_ctp_orders'])) {
        $items_text = $_POST['ctp_item'] ?? [];
        $items_supp = $_POST['ctp_supplier'] ?? [];
        if (!file_exists('uploads/source')) @mkdir('uploads/source', 0777, true);

        if (!empty($_FILES['ctp_file']['name'])) {
            foreach ($_FILES['ctp_file']['name'] as $i => $name) {
                if ($_FILES['ctp_file']['error'][$i] === 0) {
                    $ext = pathinfo($name, PATHINFO_EXTENSION);
                    $target = "uploads/source/" . time() . "_ctp_$i.$ext";
                    if (move_uploaded_file($_FILES['ctp_file']['tmp_name'][$i], $target)) {
                        $desc_idx = isset($items_text[$i]) ? $i : 0; 
                        $text = $items_text[$desc_idx] ?? 'ملف زنكات';
                        $supp = $items_supp[$desc_idx] ?? '';
                        $desc = $conn->real_escape_string($text);
                        $supp_phone = $conn->real_escape_string($supp);
                        $conn->query("INSERT INTO job_files (job_id, file_path, stage, description, uploaded_by) VALUES ($id, '$target', 'pre_press_supplies', '$desc', '$supp_phone')");
                    }
                }
            }
        } elseif (!empty($items_text[0])) {
             $desc = $conn->real_escape_string($items_text[0]);
             $supp_phone = $conn->real_escape_string($items_supp[0] ?? '');
             $conn->query("INSERT INTO job_files (job_id, file_path, stage, description, uploaded_by) VALUES ($id, '', 'pre_press_supplies', '$desc', '$supp_phone')");
        }
        safe_redirect($id);
    }

    // 7. ملفات الطباعة النهائية (تم تعديلها لتكون مثل CTP)
    if (isset($_POST['save_prepress_files'])) {
        $items_text = $_POST['prep_item'] ?? [];
        $items_supp = $_POST['prep_supplier'] ?? [];
        if (!file_exists('uploads/production')) @mkdir('uploads/production', 0777, true);

        if (!empty($_FILES['prep_file']['name'])) {
            foreach ($_FILES['prep_file']['name'] as $i => $name) {
                if ($_FILES['prep_file']['error'][$i] === 0) {
                    $ext = pathinfo($name, PATHINFO_EXTENSION);
                    $target = "uploads/production/" . time() . "_final_$i.$ext";
                    if (move_uploaded_file($_FILES['prep_file']['tmp_name'][$i], $target)) {
                        $desc_idx = isset($items_text[$i]) ? $i : 0; 
                        $text = $items_text[$desc_idx] ?? 'ملف طباعة نهائي';
                        $supp = $items_supp[$desc_idx] ?? ''; // يمكن اختيار مطبعة خارجية هنا
                        $desc = $conn->real_escape_string($text);
                        $supp_phone = $conn->real_escape_string($supp);
                        // stage = pre_press للملفات النهائية
                        $conn->query("INSERT INTO job_files (job_id, file_path, stage, description, uploaded_by) VALUES ($id, '$target', 'pre_press', '$desc', '$supp_phone')");
                    }
                }
            }
        }
        safe_redirect($id);
    }

    // 8. الطباعة
    if (isset($_POST['action']) && $_POST['action'] == 'start_printing') {
        $conn->query("UPDATE job_orders SET current_stage='printing' WHERE id=$id");
        safe_redirect($id);
    }
    if (isset($_POST['save_print_specs'])) {
        $new_specs = "مقاس الورق: {$_POST['p_len']}x{$_POST['p_wid']} | مقاس القص: {$_POST['c_len']}x{$_POST['c_wid']} | الماكينة: {$_POST['machine']} | الألوان: {$_POST['colors']} | الوجه: {$_POST['print_face']} | الزنكات: {$specs['zinc']}";
        $safe_log = $conn->real_escape_string($new_specs);
        $conn->query("UPDATE job_orders SET job_details = '$safe_log', current_stage='finishing' WHERE id=$id");
        if(!empty($_POST['print_notes'])) {
            $p_note = $conn->real_escape_string($_POST['print_notes']);
            $conn->query("UPDATE job_orders SET notes = CONCAT(IFNULL(notes, ''), '\n[🖨️ فني الطباعة]: $p_note') WHERE id=$id");
        }
        safe_redirect($id);
    }

    // 9. التسليم وإنهاء
    if (isset($_POST['finish_stage'])) { 
        $n = $conn->real_escape_string($_POST['finish_notes']);
        if(!empty($n)) $conn->query("UPDATE job_orders SET notes = CONCAT(IFNULL(notes, ''), '\n[✨ تشطيب]: $n') WHERE id=$id");
        $conn->query("UPDATE job_orders SET current_stage='delivery' WHERE id=$id"); 
        safe_redirect($id); 
    }
    if (isset($_POST['finish_delivery'])) {
        $chk = $conn->query("SELECT id FROM invoices WHERE job_id=$id");
        if ($chk->num_rows == 0) {
            $price = floatval($job['price']);
            $qty = floatval($job['quantity']);
            $items = [['desc' => $job['job_name'], 'qty' => $qty, 'price' => ($qty>0?$price/$qty:$price), 'total' => $price]];
            $json = $conn->real_escape_string(json_encode($items, JSON_UNESCAPED_UNICODE));
            $conn->query("INSERT INTO invoices (client_id, job_id, items_json, sub_total, total_amount, remaining_amount, inv_date, status) VALUES ({$job['client_id']}, $id, '$json', $price, $price, $price, NOW(), 'unpaid')");
        }
        $conn->query("UPDATE job_orders SET current_stage='accounting' WHERE id=$id");
        safe_redirect($id);
    }
    if (isset($_POST['archive_job'])) { $conn->query("UPDATE job_orders SET current_stage='completed' WHERE id=$id"); safe_redirect($id); }
    if (isset($_POST['reopen_job'])) { $conn->query("UPDATE job_orders SET current_stage='accounting' WHERE id=$id"); safe_redirect($id); }
    if(isset($_POST['save_prepress_zinc'])) {
        $zincs = floatval($_POST['zinc_count']);
        $safe_log = $conn->real_escape_string($raw_text . "\nالزنكات: $zincs |");
        $conn->query("UPDATE job_orders SET job_details = '$safe_log' WHERE id=$id");
        safe_redirect($id);
    }
}

// قائمة الموردين
$suppliers_options = "";
$s_res = $conn->query("SELECT * FROM suppliers");
if($s_res) {
    while($r = $s_res->fetch_assoc()) {
        $suppliers_options .= "<option value='{$r['phone']}'>{$r['name']}</option>";
    }
}
$all_files = $conn->query("SELECT * FROM job_files WHERE job_id=$id ORDER BY id DESC");
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>إدارة العملية - <?php echo $job['job_name']; ?></title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
    :root { --ae-gold: #d4af37; --ae-gold-light: #f1c40f; --ae-dark: #121212; --ae-card: #1e1e1e; --ae-green: #2ecc71; --ae-red: #e74c3c; }
    body { background-color: #000; color: #fff; font-family: 'Cairo', sans-serif; margin: 0; padding: 10px; }
    
    .container.split-layout { display: flex; gap: 20px; align-items: flex-start; flex-wrap: wrap; }
    .sidebar { width: 300px; flex-shrink: 0; background: #151515; border: 1px solid #333; border-radius: 12px; padding: 20px; }
    .main-content { flex: 1; min-width: 0; min-width: 300px; }
    @media (max-width: 900px) { .sidebar { width: 100%; margin-bottom: 20px; box-sizing:border-box; } .main-content { width: 100%; } }

    @property --angle { syntax: '<angle>'; initial-value: 0deg; inherits: false; }
    @keyframes rotateOrbit { to { --angle: 360deg; } }
    @keyframes liquidGold { 0% { background-position: 0% 50%; } 100% { background-position: 100% 50%; } }

    .btn-liquid { position: relative; border: none; background: transparent; padding: 0; cursor: pointer; outline: none; border-radius: 6px; z-index: 1; width: 100%; display: block; margin-top: 10px; text-decoration: none; }
    .btn-liquid::before { content: ''; position: absolute; z-index: -2; top: -2px; left: -2px; right: -2px; bottom: -2px; border-radius: 8px; background: var(--ae-dark); background-image: conic-gradient(from var(--angle), transparent 0%, transparent 70%, var(--ae-gold) 85%, var(--ae-gold-light) 95%, transparent 100%); animation: rotateOrbit 3s linear infinite; }
    .btn-content { display: flex; align-items: center; justify-content: center; gap: 8px; background: linear-gradient(90deg, var(--ae-gold), #b8860b, var(--ae-gold-light), var(--ae-gold)); background-size: 300% 100%; padding: 12px 20px; border-radius: 6px; color: #000; font-weight: 800; font-family: 'Cairo'; animation: liquidGold 3s linear infinite; }
    .btn-liquid:hover::before { filter: drop-shadow(0 0 10px var(--ae-gold)); animation-duration: 1.5s; }
    .btn-liquid:disabled { opacity: 0.7; cursor: not-allowed; }

    .info-block { margin-bottom: 15px; border-bottom: 1px dashed #333; padding-bottom: 10px; }
    .info-label { color: var(--ae-gold); font-size: 0.8rem; font-weight: bold; display: block; }
    .info-value { background: #0a0a0a; padding: 8px; border-radius: 6px; border: 1px solid #222; font-size: 0.9rem; margin-top: 3px; word-wrap: break-word; }
    
    .file-item { display: flex; align-items: center; gap: 5px; background: #0a0a0a; padding: 8px; margin-bottom: 5px; border-radius: 6px; border: 1px solid #333; position: relative; }
    .file-item:hover { border-color: var(--ae-gold); }
    .file-link { color: #fff; flex: 1; text-decoration: none; overflow: hidden; white-space: nowrap; text-overflow: ellipsis; font-size: 0.85rem; display: flex; align-items: center; gap: 5px; }
    
    .action-btn { width: 25px; height: 25px; border-radius: 4px; display: flex; align-items: center; justify-content: center; cursor: pointer; border: none; color: #fff; transition: 0.2s; font-size: 0.8rem; }
    .btn-del { background: rgba(231, 76, 60, 0.2); color: var(--ae-red); }
    .btn-del:hover { background: var(--ae-red); color: #fff; }
    .btn-share { background: rgba(37, 211, 102, 0.2); color: #25D366; }
    .btn-share:hover { background: #25D366; color: #fff; }
    .btn-wa { background: #25D366; font-size: 0.8rem; padding: 5px 10px; border-radius: 15px; color:#fff; text-decoration:none; display:inline-flex; align-items:center; gap:5px; margin-top:5px; }

    .main-card { background: var(--ae-card); padding: 20px; border-radius: 12px; border: 1px solid #333; margin-bottom: 20px; }
    .card-title { color: var(--ae-gold); border-bottom: 1px dashed #444; padding-bottom: 10px; font-size: 1.2rem; margin-top: 0; }
    .stage-header { display: flex; gap: 5px; overflow-x: auto; padding-bottom: 10px; margin-bottom: 20px; border-bottom: 1px solid #333; }
    .step-badge { background: #333; color: #777; padding: 5px 15px; border-radius: 20px; white-space: nowrap; font-size: 0.8rem; }
    .step-badge.active { background: var(--ae-gold); color: #000; font-weight: bold; }
    .p-input { background: #000; border: 1px solid #444; color: #fff; padding: 10px; width: 100%; border-radius: 6px; box-sizing: border-box; font-family: 'Cairo'; margin-bottom: 5px; }
    
    .upload-zone { border: 2px dashed #444; padding: 20px; border-radius: 8px; background: #111; text-align: center; margin-top: 15px; }
    .progress-wrapper { width: 100%; background: #333; height: 10px; border-radius: 5px; margin-top: 10px; overflow: hidden; display: none; }
    .progress-fill { height: 100%; background: var(--ae-green); width: 0%; transition: width 0.3s; }
    
    .gallery-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 15px; margin-top: 15px; }
    .gallery-item { background: #000; border: 1px solid #333; border-radius: 8px; overflow: hidden; position: relative; transition:0.2s; }
    .gallery-item.rejected-item { border: 1px solid var(--ae-red); box-shadow: 0 0 10px rgba(231, 76, 60, 0.2); }
    .gallery-item.approved-item { border: 1px solid var(--ae-green); box-shadow: 0 0 10px rgba(46, 204, 113, 0.2); }
    .g-thumb { width: 100%; height: 120px; object-fit: cover; display:block; background:#111; }
    
    .client-feedback-box { background: rgba(231, 76, 60, 0.1); border: 1px solid var(--ae-red); padding: 10px; margin-top: 8px; color: #ffcccc; font-size: 0.85rem; line-height: 1.4; border-radius: 6px; font-weight:bold; }
    .client-approval-box { background: rgba(46, 204, 113, 0.1); border: 1px solid var(--ae-green); padding: 5px; margin-top: 8px; color: var(--ae-green); font-size: 0.8rem; text-align:center; border-radius: 4px; font-weight:bold; }

    .btn-simple { padding: 8px 15px; border: none; border-radius: 5px; cursor: pointer; color: #fff; font-weight: bold; font-family: 'Cairo'; display: inline-block; text-decoration: none; font-size: 0.9rem; transition:0.2s; }
    .btn-simple:hover { opacity:0.8; }
    .btn-green { background: var(--ae-green); } .btn-red { background: var(--ae-red); } .btn-gray { background: #444; }

    .admin-controls { background: #2c0b0b; border: 1px solid var(--ae-red); padding: 10px; border-radius: 8px; margin-bottom: 15px; }
    .admin-badge { background: var(--ae-red); color: white; padding: 2px 8px; border-radius: 4px; font-size: 0.7rem; font-weight: bold; margin-bottom: 5px; display: inline-block; }

    .modal-overlay { display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.9); z-index:9999; justify-content:center; align-items:center; }
    .modal-box { background:#1a1a1a; padding:25px; width:90%; max-width:400px; border:1px solid var(--ae-gold); border-radius:10px; text-align:center; position: relative; }
    
    /* تحميل الصفحة عند الرفع التقليدي */
    .loading-overlay { position:fixed; top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.8);z-index:99999;display:none;align-items:center;justify-content:center;flex-direction:column;color:#fff; }
</style>

<script>
function uploadFilesUniversal(stage, inputId, progressId, textId, btnId) {
    const fileInput = document.getElementById(inputId);
    const files = fileInput.files;
    const descInput = document.getElementById(inputId + '_desc');
    
    if (files.length === 0) return alert("⚠️ اختر ملفات أولاً");

    const formData = new FormData();
    for (let i = 0; i < files.length; i++) formData.append("ajax_files[]", files[i]);
    formData.append("job_id", "<?php echo $id; ?>");
    formData.append("stage", stage);
    formData.append("ajax_action", "universal_upload");
    if(descInput) formData.append("file_desc", descInput.value);

    const xhr = new XMLHttpRequest();
    xhr.timeout = 0; // 🛑 إلغاء التايم أوت للملفات الكبيرة
    
    const btn = document.getElementById(btnId);
    const pWrap = document.getElementById(progressId);
    const pFill = pWrap.querySelector('.progress-fill');
    const pText = document.getElementById(textId);
    const originalContent = btn.innerHTML;

    btn.disabled = true;
    btn.querySelector('.btn-content').innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> جاري الرفع...';
    pWrap.style.display = 'block';
    pText.style.display = 'block';
    pFill.style.width = "0%";

    xhr.upload.addEventListener("progress", function(evt) {
        if (evt.lengthComputable) {
            const percent = Math.round((evt.loaded / evt.total) * 100);
            pFill.style.width = percent + "%";
            pText.innerHTML = percent + "%";
        }
    });

    xhr.onload = function() {
        if (xhr.status === 200) {
            try {
                const res = JSON.parse(xhr.responseText);
                if (res.status === 'success') {
                    pText.innerHTML = "✅ " + res.msg;
                    setTimeout(() => window.location.reload(), 1000);
                } else {
                    alert("❌ " + res.msg);
                    resetBtn(btn, originalContent);
                }
            } catch (e) {
                console.error(xhr.responseText);
                alert("خطأ في السيرفر");
                resetBtn(btn, originalContent);
            }
        } else {
            alert("فشل الاتصال");
            resetBtn(btn, originalContent);
        }
    };
    xhr.onerror = function() { alert("خطأ شبكة"); resetBtn(btn, originalContent); };
    xhr.open("POST", window.location.href);
    xhr.send(formData);
}

function resetBtn(btn, content) {
    setTimeout(() => { btn.disabled = false; btn.innerHTML = content; }, 1000);
}

function showLoading() {
    document.getElementById('fullPageLoader').style.display = 'flex';
}

// دالة المشاركة
let currentFileUrl = '';
function openShareModal(fileUrl, fileName) {
    currentFileUrl = fileUrl;
    document.getElementById('shareFileName').innerText = fileName;
    document.getElementById('shareModal').style.display = 'flex';
}
function sendWhatsApp() {
    const phone = document.getElementById('shareSupplier').value;
    if(!phone) return alert('اختر مورد من القائمة');
    const msg = "مرفق ملف خاص بعملية: <?php echo htmlspecialchars($job['job_name']); ?>\n" + currentFileUrl;
    let cleanPhone = phone.replace(/[^0-9]/g, '');
    if (cleanPhone.length == 11 && cleanPhone.startsWith('01')) cleanPhone = '2' + cleanPhone;
    const url = `https://wa.me/${cleanPhone}?text=${encodeURIComponent(msg)}`;
    window.open(url, '_blank');
    document.getElementById('shareModal').style.display = 'none';
}
</script>
</head>
<body>

<div id="fullPageLoader" class="loading-overlay">
    <i class="fa-solid fa-circle-notch fa-spin fa-3x" style="color:var(--ae-gold);"></i>
    <h3 style="margin-top:20px;">جاري رفع الملفات الكبيرة... يرجى الانتظار</h3>
</div>

<div class="container split-layout">
    <div class="sidebar">
        <?php if($is_admin): ?>
        <div class="admin-controls">
            <span class="admin-badge">تحكم إداري</span>
            <form method="POST" onsubmit="return confirm('⚠️ تغيير المرحلة جبرياً؟');">
                <input type="hidden" name="admin_change_stage" value="1">
                <select name="target_stage" class="p-input" style="font-size:0.8rem; height:35px; padding:5px;">
                    <?php foreach($workflow as $key => $val): ?>
                        <option value="<?php echo $key; ?>" <?php if($key == $curr) echo 'selected'; ?>><?php echo $val['label']; ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="btn-simple btn-red" style="width:100%; font-size:0.8rem;">تغيير المرحلة</button>
            </form>
        </div>
        <?php endif; ?>

        <h3 style="color:#fff; border-bottom:2px solid var(--ae-gold); padding-bottom:10px;">📂 ملف العملية</h3>
        <div class="info-block">
            <span class="info-label">👤 العميل:</span>
            <div class="info-value" style="color:var(--ae-gold); font-weight:bold;"><?php echo $job['client_name']; ?></div>
            <div class="info-value" style="margin-top:5px; font-size:0.8rem;"><?php echo $job['client_phone']; ?></div>
        </div>
        <div class="info-block">
            <span class="info-label">🔖 العملية:</span>
            <div class="info-value"><?php echo $job['job_name']; ?></div>
        </div>
        <div class="info-block">
            <span class="info-label">📄 المواصفات الأساسية:</span>
            <div class="info-value"><?php echo nl2br($job['job_details'] ?? '-'); ?></div>
        </div>

        <?php if(!empty($history_notes)): ?>
        <div class="info-block">
            <span class="info-label">📜 سجل الملاحظات والبيانات:</span>
            <?php foreach($history_notes as $note): ?>
                <div class="info-value" style="margin-bottom:5px; font-size:0.85rem;">
                    <span style="color:var(--ae-gold); font-weight:bold;"><?php echo $note[1]; ?>:</span>
                    <?php echo nl2br($note[2]); ?>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="info-block" style="border:none;">
            <span class="info-label">📎 الملفات المرفوعة (الكل):</span>
            <?php if($all_files->num_rows > 0): while($f = $all_files->fetch_assoc()): 
                $file_url = (isset($_SERVER['HTTPS'])?"https":"http")."://".$_SERVER['HTTP_HOST']. dirname($_SERVER['PHP_SELF']) . "/" .$f['file_path'];
                $file_name = basename($f['file_path']);
            ?>
                <div class="file-item">
                    <a href="<?php echo $file_url; ?>" target="_blank" class="file-link" title="<?php echo $file_name; ?>">
                        <i class="fa-solid fa-file-arrow-down" style="color:var(--ae-gold);"></i> <?php echo mb_strimwidth($file_name, 0, 15, "..."); ?>
                    </a>
                    <button class="action-btn btn-share" onclick="openShareModal('<?php echo $file_url; ?>', '<?php echo addslashes($file_name); ?>')" title="إرسال واتساب"><i class="fa-brands fa-whatsapp"></i></button>
                    <form method="POST" style="margin:0;"><input type="hidden" name="delete_item" value="1"><input type="hidden" name="type" value="file"><input type="hidden" name="item_id" value="<?php echo $f['id']; ?>"><button class="action-btn btn-del" onclick="return confirm('حذف الملف نهائياً؟')" title="حذف"><i class="fa-solid fa-times"></i></button></form>
                </div>
            <?php endwhile; else: echo "<div style='color:#666;'>لا يوجد ملفات</div>"; endif; ?>
        </div>
    </div>

    <div class="main-content">
        <div class="stage-header">
            <?php foreach($workflow as $key => $data): ?><div class="step-badge <?php echo ($key == $curr) ? 'active' : ''; ?>"><?php echo $data['label']; ?></div><?php endforeach; ?>
        </div>

        <?php if($curr == 'briefing'): ?>
        <div class="main-card">
            <h3 class="card-title">📝 التجهيز والبيانات</h3>
            <form method="POST">
                <textarea name="notes" rows="3" class="p-input" placeholder="ملاحظات فنية..."></textarea>
                <button name="save_brief_notes" class="btn-liquid"><span class="btn-content"><i class="fa-solid fa-floppy-disk"></i> حفظ وبدء التصميم ➡️</span></button>
            </form>
            <div class="upload-zone">
                <h4 style="margin-top:0; color:#fff;">رفع ملفات مساعدة</h4>
                <p style="color:#666; font-size:0.8rem;">يمكنك رفع الملفات ومشاركتها فوراً من القائمة الجانبية</p>
                <input type="text" id="brief_desc" class="p-input" placeholder="وصف الملفات">
                <input type="file" id="brief_files" multiple class="p-input">
                <div id="prog_brief" class="progress-wrapper"><div class="progress-fill"></div></div>
                <div id="txt_brief" class="progress-text">0%</div>
                <button type="button" id="btn_brief" class="btn-liquid" onclick="uploadFilesUniversal('briefing', 'brief_files', 'prog_brief', 'txt_brief', 'btn_brief')">
                    <span class="btn-content"><i class="fa-solid fa-upload"></i> رفع الملفات</span>
                </button>
            </div>
            
            <h4 style="color:var(--ae-gold); margin-bottom:10px;">📤 ملفات التجهيز (للمشاركة السريعة)</h4>
             <?php $briefs = $conn->query("SELECT * FROM job_files WHERE job_id=$id AND stage='briefing'"); 
             if($briefs->num_rows > 0): while($b = $briefs->fetch_assoc()): 
                $b_url = (isset($_SERVER['HTTPS'])?"https":"http")."://".$_SERVER['HTTP_HOST']. dirname($_SERVER['PHP_SELF']) . "/" .$b['file_path'];
             ?>
                <div class="file-item">
                    <span><?php echo $b['description']; ?></span>
                    <button class="btn-wa" onclick="openShareModal('<?php echo $b_url; ?>', '<?php echo addslashes($b['description']); ?>')"><i class="fa-brands fa-whatsapp"></i> مشاركة</button>
                </div>
             <?php endwhile; endif; ?>
        </div>
        <?php endif; ?>

        <?php if($curr == 'design' || $is_designer_or_admin): ?>
        <div class="main-card">
            <h3 class="card-title">🎨 التصميم</h3>
            <div class="upload-zone">
                <h4 style="margin-top:0; color:#fff;">رفع بروفات العميل</h4>
                <input type="text" id="proof_desc" class="p-input" placeholder="اسم البروفة">
                <input type="file" id="proof_file" multiple class="p-input">
                <div id="prog_proof" class="progress-wrapper"><div class="progress-fill"></div></div>
                <div id="txt_proof" class="progress-text">0%</div>
                <button type="button" id="btn_proof" class="btn-liquid" onclick="uploadFilesUniversal('design', 'proof_file', 'prog_proof', 'txt_proof', 'btn_proof')">
                    <span class="btn-content"><i class="fa-solid fa-upload"></i> رفع البروفة</span>
                </button>
            </div>
            <div class="gallery-grid">
                <?php $proofs = $conn->query("SELECT * FROM job_proofs WHERE job_id=$id"); while($p = $proofs->fetch_assoc()): 
                    $url = (isset($_SERVER['HTTPS'])?"https":"http")."://".$_SERVER['HTTP_HOST']. dirname($_SERVER['PHP_SELF']) . "/" .$p['file_path'];
                    $is_rejected = ($p['status'] == 'rejected' || $p['status'] == 'pending_revision');
                    $is_approved = ($p['status'] == 'approved');
                ?>
                <div class="gallery-item <?php echo $is_rejected ? 'rejected-item' : ($is_approved ? 'approved-item' : ''); ?>">
                    <a href="<?php echo $url; ?>" target="_blank">
                        <?php if(preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $p['file_path'])): ?><img src="<?php echo $url; ?>" class="g-thumb"><?php else: ?><div class="g-thumb" style="display:flex;align-items:center;justify-content:center;color:#777;"><i class="fa-solid fa-file-pdf fa-2x"></i></div><?php endif; ?>
                    </a>
                    <div style="padding:10px; font-size:0.85rem;">
                        <div style="font-weight:bold; margin-bottom:5px; color:#fff;"><?php echo $p['description']; ?></div>
                        
                        <?php if(!empty($p['client_comment']) && $is_rejected): ?>
                            <div class="client-feedback-box">
                                <i class="fa-solid fa-triangle-exclamation"></i> مطلوب تعديل:<br>
                                <?php echo $p['client_comment']; ?>
                            </div>
                        <?php endif; ?>

                        <?php if($is_approved): ?>
                            <div class="client-approval-box">
                                <i class="fa-solid fa-circle-check"></i> تمت الموافقة
                            </div>
                        <?php endif; ?>

                        <div style="display:flex; justify-content:space-between; margin-top:5px;">
                            <button class="action-btn btn-share" onclick="openShareModal('<?php echo $url; ?>', 'بروفة')" title="مشاركة"><i class="fa-brands fa-whatsapp"></i></button>
                            <form method="POST"><input type="hidden" name="delete_item" value="1"><input type="hidden" name="type" value="proof"><input type="hidden" name="item_id" value="<?php echo $p['id']; ?>"><button class="action-btn btn-del" onclick="return confirm('حذف؟')"><i class="fa-solid fa-times"></i></button></form>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
            <?php if($curr == 'design'): ?>
            <form method="POST" style="margin-top:20px;">
                <button name="send_to_review" class="btn-liquid"><span class="btn-content">إرسال للمراجعة <i class="fa-solid fa-paper-plane"></i></span></button>
            </form>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if($curr == 'client_rev'): ?>
        <div class="main-card">
            <h3 class="card-title">⏳ انتظار العميل</h3>
            <?php 
                $token = $job['access_token'];
                if(empty($token)) { $token = bin2hex(random_bytes(16)); $conn->query("UPDATE job_orders SET access_token='$token' WHERE id=$id"); }
                $link = (isset($_SERVER['HTTPS']) ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'] . str_replace('/modules', '', dirname($_SERVER['PHP_SELF'])) . "/client_review.php?token=" . $token;
                $wa = get_wa_link($job['client_phone'], "رابط مراجعة التصميم:\n$link");
            ?>
            <div style="text-align:center; background:#000; padding:15px; border-radius:8px;">
                <input type="text" value="<?php echo $link; ?>" readonly class="p-input" style="direction:ltr; text-align:center;">
                <div style="margin-top:10px;">
                    <a href="<?php echo $wa; ?>" target="_blank" class="btn-simple btn-green">📱 إرسال واتساب</a>
                    <a href="<?php echo $link; ?>" target="_blank" class="btn-simple btn-gray">👁️ معاينة الرابط</a>
                </div>
            </div>
            <div style="display:flex; gap:10px; margin-top:20px;">
                <form method="POST" style="flex:1;"><input type="hidden" name="prev_target" value="design"><input type="hidden" name="return_reason" value="تعديل"><button name="return_stage" class="btn-simple btn-red" style="width:100%">↩️ إعادة للتصميم (تعديل)</button></form>
                <form method="POST" style="flex:1;"><button name="finalize_review" class="btn-simple btn-green" style="width:100%">تجاوز (اعتماد) ➡️</button></form>
            </div>
        </div>
        <?php endif; ?>

        <?php if($curr == 'materials'): ?>
        <div class="main-card">
            <h3 class="card-title">📦 إدارة الخامات</h3>
            <?php $mats = $conn->query("SELECT * FROM job_files WHERE job_id=$id AND stage='materials'");
            if($mats && $mats->num_rows > 0): ?>
            <div style="margin-bottom:20px; border:1px solid #333; padding:10px; border-radius:8px;">
                <h4 style="margin:0 0 10px 0; color:#aaa;">طلبات سابقة:</h4>
                <?php while($m = $mats->fetch_assoc()): 
                    $sup_ph = preg_replace('/[^0-9]/', '', $m['uploaded_by']);
                    $msg_text = "مطلوب خامات لعملية {$job['job_name']}:\n" . $m['description'];
                    if (!empty($m['file_path'])) {
                        $file_full_url = (isset($_SERVER['HTTPS']) ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/" . $m['file_path'];
                        $msg_text .= "\n📎 رابط الملف: " . $file_full_url;
                    }
                    $wa_sup = (!empty($sup_ph)) ? get_wa_link($sup_ph, $msg_text) : '#';
                ?>
                <div style="display:flex; justify-content:space-between; margin-bottom:5px; background:#000; padding:10px; border-radius:4px; align-items:center;">
                    <span><?php echo $m['description']; ?></span>
                    <div style="display:flex; align-items:center; gap:10px;">
                        <?php if(!empty($sup_ph)): ?>
                            <a href="<?php echo $wa_sup; ?>" target="_blank" class="btn-wa" style="margin:0;"><i class="fa-brands fa-whatsapp"></i> إرسال للمورد</a>
                        <?php endif; ?>
                        <form method="POST" style="margin:0;"><input type="hidden" name="delete_item" value="1"><input type="hidden" name="type" value="file"><input type="hidden" name="item_id" value="<?php echo $m['id']; ?>"><button class="delete-btn" onclick="return confirm('حذف؟')">×</button></form>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data">
                <div id="mat-items-container">
                    <div style="display:flex; gap:5px; margin-bottom:10px; flex-wrap:wrap;">
                        <input type="text" name="item_text[]" placeholder="صنف (ورق/أحبار)" class="p-input" style="flex:2;">
                        <select name="supplier_phone[]" class="p-input" style="flex:1;">
                            <option value="">-- اختر مورد --</option>
                            <?php echo $suppliers_options; ?>
                        </select>
                        <input type="file" name="item_file[]" class="p-input" style="flex:1;" multiple>
                    </div>
                </div>
                <button type="button" onclick="addMatItem()" class="btn-simple btn-gray">+ بند إضافي</button>
                <div style="display:flex; gap:10px; margin-top:20px;">
                    <button name="save_materials" class="btn-liquid" style="flex:1;"><span class="btn-content">💾 حفظ البيانات</span></button>
                    <button name="finish_materials" class="btn-liquid" style="flex:1;"><span class="btn-content">إنهاء الخامات ➡️</span></button>
                </div>
            </form>
        </div>
        <?php endif; ?>

        <?php if($curr == 'pre_press'): ?>
        <div class="main-card">
            <h3 class="card-title">⚙️ التجهيز (CTP)</h3>
             <?php $ctp_reqs = $conn->query("SELECT * FROM job_files WHERE job_id=$id AND stage='pre_press_supplies'");
            if($ctp_reqs && $ctp_reqs->num_rows > 0): ?>
            <div style="margin-bottom:20px; border:1px dashed var(--ae-gold); padding:10px; border-radius:8px;">
                <h4 style="margin:0 0 10px 0; color:var(--ae-gold);">طلبات زنكات:</h4>
                <?php while($c = $ctp_reqs->fetch_assoc()): 
                    $s_ph = preg_replace('/[^0-9]/', '', $c['uploaded_by']);
                    $w_msg = "طلب زنكات لعملية {$job['job_name']}:\n" . $c['description'];
                    if (!empty($c['file_path'])) {
                         $file_full_url = (isset($_SERVER['HTTPS']) ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/" . $c['file_path'];
                         $w_msg .= "\n📎 رابط الملف: " . $file_full_url;
                    }
                    $w_lnk = (!empty($s_ph)) ? get_wa_link($s_ph, $w_msg) : '#';
                ?>
                <div style="background:#000; padding:8px; margin-bottom:5px; border-radius:4px; display:flex; justify-content:space-between; align-items:center;">
                    <span><?php echo $c['description']; ?></span>
                    <div>
                        <?php if(!empty($s_ph)): ?><a href="<?php echo $w_lnk; ?>" target="_blank" class="btn-wa" style="margin:0;">إرسال</a><?php endif; ?>
                        <form method="POST" style="display:inline;"><input type="hidden" name="delete_item" value="1"><input type="hidden" name="type" value="file"><input type="hidden" name="item_id" value="<?php echo $c['id']; ?>"><button class="delete-btn">×</button></form>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data" onsubmit="showLoading()" style="border-bottom:1px solid #333; padding-bottom:15px; margin-bottom:15px;">
                <div style="display:flex; gap:5px; flex-wrap:wrap;">
                    <input type="text" name="ctp_item[]" class="p-input" placeholder="زنكات (عدد/مقاس)" style="flex:2;">
                    <select name="ctp_supplier[]" class="p-input" style="flex:1;">
                        <option value="">-- اختر مورد --</option>
                        <?php echo $suppliers_options; ?>
                    </select>
                    <input type="file" name="ctp_file[]" class="p-input" style="flex:1;" multiple>
                    <button name="save_ctp_orders" class="btn-simple btn-gray">إضافة</button>
                </div>
            </form>

            <h4 style="margin-top:20px; color:#fff;">📂 ملفات الطباعة النهائية (Pre-Press)</h4>
            <?php $prep_files = $conn->query("SELECT * FROM job_files WHERE job_id=$id AND stage='pre_press'");
            if($prep_files && $prep_files->num_rows > 0): ?>
            <div style="margin-bottom:20px; border:1px solid #444; padding:10px; border-radius:8px;">
                <?php while($pf = $prep_files->fetch_assoc()): 
                    $s_ph = preg_replace('/[^0-9]/', '', $pf['uploaded_by']);
                    $w_msg = "ملف طباعة نهائي للعملية {$job['job_name']}:\n" . $pf['description'];
                    if (!empty($pf['file_path'])) {
                         $file_full_url = (isset($_SERVER['HTTPS']) ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/" . $pf['file_path'];
                         $w_msg .= "\n📎 تحميل: " . $file_full_url;
                    }
                    $w_lnk = (!empty($s_ph)) ? get_wa_link($s_ph, $w_msg) : '#';
                ?>
                <div style="background:#000; padding:8px; margin-bottom:5px; border-radius:4px; display:flex; justify-content:space-between; align-items:center;">
                    <a href="<?php echo $file_full_url ?? '#'; ?>" target="_blank" style="color:#fff; text-decoration:none;">
                        <i class="fa-solid fa-file-pdf" style="color:var(--ae-green);"></i> <?php echo $pf['description']; ?>
                    </a>
                    <div>
                        <?php if(!empty($s_ph)): ?><a href="<?php echo $w_lnk; ?>" target="_blank" class="btn-wa" style="margin:0;">إرسال للمطبعة</a><?php endif; ?>
                        <form method="POST" style="display:inline;"><input type="hidden" name="delete_item" value="1"><input type="hidden" name="type" value="file"><input type="hidden" name="item_id" value="<?php echo $pf['id']; ?>"><button class="delete-btn">×</button></form>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data" onsubmit="showLoading()">
                <div style="display:flex; gap:5px; flex-wrap:wrap;">
                    <input type="text" name="prep_item[]" class="p-input" placeholder="وصف الملف (وجه 1 / كوشيه..)" style="flex:2;">
                    <select name="prep_supplier[]" class="p-input" style="flex:1;">
                        <option value="">-- اختر مطبعة --</option>
                        <?php echo $suppliers_options; ?>
                    </select>
                    <input type="file" name="prep_file[]" class="p-input" style="flex:1;" multiple>
                    <button name="save_prepress_files" class="btn-simple btn-gray">رفع الملف</button>
                </div>
            </form>

            <form method="POST" style="margin-top:20px;">
                <input type="hidden" name="action" value="start_printing">
                <button class="btn-liquid"><span class="btn-content">اعتماد وبدء الطباعة ➡️</span></button>
            </form>
        </div>
        <?php endif; ?>

        <?php if($curr == 'printing'): ?>
        <div class="main-card">
            <h3 class="card-title">🖨️ الطباعة</h3>
            <?php 
            // 🌟 إصلاح مشكلة التوكن المفقود هنا
            $token = $job['access_token'];
            if(empty($token)) { 
                $token = bin2hex(random_bytes(16)); 
                $conn->query("UPDATE job_orders SET access_token='$token' WHERE id=$id"); 
                $job['access_token'] = $token; // تحديث المتغير المحلي
            }
            $order_link = (isset($_SERVER['HTTPS'])?"https":"http")."://".$_SERVER['HTTP_HOST'].str_replace('/modules','',dirname($_SERVER['PHP_SELF']))."/view_order.php?id=$id&token={$token}";
            ?>
            <div style="margin-bottom:20px;">
                <a href="https://wa.me/?text=<?php echo urlencode("أمر تشغيل:\n$order_link"); ?>" target="_blank" class="btn-simple btn-green">
                    <i class="fa-brands fa-whatsapp"></i> إرسال أمر الشغل (رابط)
                </a>
            </div>
            
            <form method="POST">
                <table style="width:100%; color:#ccc;">
                    <tr><td>ورق: <input type="number" step="any" name="p_len" value="<?php echo $specs['p_len']; ?>" class="p-input" style="width:60px;display:inline;"> x <input type="number" step="any" name="p_wid" value="<?php echo $specs['p_wid']; ?>" class="p-input" style="width:60px;display:inline;"></td></tr>
                    <tr><td>قص: <input type="number" step="any" name="c_len" value="<?php echo $specs['c_len']; ?>" class="p-input" style="width:60px;display:inline;"> x <input type="number" step="any" name="c_wid" value="<?php echo $specs['c_wid']; ?>" class="p-input" style="width:60px;display:inline;"></td></tr>
                    <tr><td>ماكينة: <input type="text" name="machine" value="<?php echo $specs['machine']; ?>" class="p-input"></td></tr>
                    <tr><td>ألوان: <input type="text" name="colors" value="<?php echo $specs['colors']; ?>" class="p-input"></td></tr>
                    <tr><td>زنكات: <input type="number" name="zinc" value="<?php echo $specs['zinc']; ?>" class="p-input"></td></tr>
                    <tr><td>وجه: <select name="print_face" class="p-input"><option value="<?php echo $specs['print_face']; ?>"><?php echo $specs['print_face']?:'اختر'; ?></option><option>وجه واحد</option><option>وجهين</option></select></td></tr>
                </table>
                <textarea name="print_notes" class="p-input" rows="3" placeholder="تقرير الفني..."></textarea>
                <button name="save_print_specs" class="btn-liquid"><span class="btn-content">تأكيد وبدء التشطيب ➡️</span></button>
            </form>
        </div>
        <?php endif; ?>

        <?php if($curr == 'finishing'): ?>
        <div class="main-card">
            <h3 class="card-title">✨ التشطيب</h3>
            <p style="color:#ccc;">المواصفات: <?php echo get_spec('/التكميلي: (.*?)(?:\||$)/u', $raw_text, 'غير محدد'); ?></p>
            <form method="POST">
                <textarea name="finish_notes" class="p-input" rows="3" placeholder="ملاحظات التسليم..."></textarea>
                <button name="finish_stage" class="btn-liquid"><span class="btn-content">تم التشطيب ➡️</span></button>
            </form>
        </div>
        <?php endif; ?>

        <?php if($curr == 'delivery'): ?>
        <div class="main-card">
            <h3 class="card-title">🚚 التسليم</h3>
            <div style="background:#111; padding:15px; border-radius:8px; margin-bottom:15px; border:1px solid #333;">
                <h4 style="margin-top:0; color:var(--ae-gold);">بيانات العميل للتواصل:</h4>
                <p>👤 <?php echo $job['client_name']; ?></p>
                <p>📞 <?php echo $job['client_phone']; ?></p>
                <div style="display:flex; gap:10px; margin-top:10px;">
                    <a href="tel:<?php echo $job['client_phone']; ?>" class="btn-simple btn-gray">📞 اتصال</a>
                    <a href="<?php echo get_wa_link($job['client_phone'], "مرحباً {$job['client_name']}، طلبك ({$job['job_name']}) جاهز للتسليم."); ?>" target="_blank" class="btn-simple btn-green">💬 واتساب</a>
                    <a href="<?php echo get_wa_link($job['client_phone'], "مرحباً، برجاء إرسال الموقع (Location) لتسليم الطلب."); ?>" target="_blank" class="btn-simple btn-gray">📍 طلب اللوكيشن</a>
                </div>
            </div>
            <form method="POST" onsubmit="return confirm('إغلاق نهائي؟');">
                <button name="finish_delivery" class="btn-liquid"><span class="btn-content">تسليم وإغلاق 🏁</span></button>
            </form>
        </div>
        <?php endif; ?>

        <?php if(in_array($curr, ['accounting', 'completed'])): ?>
        <div class="main-card" style="text-align:center;">
            <h2 style="color:var(--ae-green);">✅ العملية مكتملة</h2>
            <?php if($is_admin): ?>
                <a href="invoices.php" class="btn-simple btn-gray">الملف المالي</a>
                <?php if($curr == 'accounting'): ?><form method="POST" style="margin-top:20px;"><button name="archive_job" class="btn-liquid"><span class="btn-content">أرشفة نهائية</span></button></form><?php endif; ?>
            <?php endif; ?>
            <?php if($curr == 'completed'): ?><form method="POST" style="margin-top:20px;" onsubmit="return confirm('تأكيد؟');"><button name="reopen_job" class="btn-simple btn-red">🔄 إعادة فتح</button></form><?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if($prev_stage && !in_array($curr, ['completed'])): ?>
        <div style="text-align:right; margin-top:20px;">
            <button onclick="document.getElementById('backModal').style.display='flex'" class="btn-simple btn-red" style="font-size:0.8rem;">↩️ تراجع خطوة</button>
        </div>
        <?php endif; ?>

    </div>
</div>

<div id="backModal" class="modal-overlay">
    <div class="modal-box">
        <h3 style="color:var(--ae-red);">⚠️ تراجع للمرحلة السابقة</h3>
        <form method="POST">
            <input type="hidden" name="prev_target" value="<?php echo $prev_stage; ?>">
            <textarea name="return_reason" required placeholder="سبب التراجع..." class="p-input" style="height:80px;"></textarea>
            <div style="display:flex; gap:10px; margin-top:10px;">
                <button name="return_stage" class="btn-simple btn-red" style="flex:1;">تأكيد</button>
                <button type="button" onclick="document.getElementById('backModal').style.display='none'" class="btn-simple btn-gray" style="flex:1;">إلغاء</button>
            </div>
        </form>
    </div>
</div>

<div id="shareModal" class="modal-overlay">
    <div class="modal-box" style="border-color:var(--ae-green);">
        <h3 style="color:var(--ae-green);"><i class="fa-brands fa-whatsapp"></i> مشاركة ملف</h3>
        <p id="shareFileName" style="color:#aaa; font-size:0.9rem; margin-bottom:15px;"></p>
        <label style="display:block; text-align:right; margin-bottom:5px;">اختر المورد:</label>
        <select id="shareSupplier" class="p-input">
            <option value="">-- اختر من القائمة --</option>
            <?php echo $suppliers_options; ?>
        </select>
        <div style="margin:10px 0; color:#666; font-size:0.8rem;">أو اكتب رقم (اختياري)</div>
        <input type="text" id="manualPhone" class="p-input" placeholder="01xxxxxxxxx" onchange="document.getElementById('shareSupplier').value = this.value">
        <div style="display:flex; gap:10px; margin-top:15px;">
            <button onclick="sendWhatsApp()" class="btn-simple btn-green" style="flex:1;">إرسال</button>
            <button onclick="document.getElementById('shareModal').style.display='none'" class="btn-simple btn-gray" style="flex:1;">إلغاء</button>
        </div>
    </div>
</div>

</body>
</html>