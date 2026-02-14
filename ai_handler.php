<?php
// ai_handler.php (V7.0 - Supplier Purchase Orders)
require 'auth.php';
require 'config.php';

header('Content-Type: application/json');

// ... (Input data handling remains the same) ...
$data = json_decode(file_get_contents('php://input'), true);
$message = isset($data['message']) ? trim($data['message']) : '';

if (empty($message)) {
    echo json_encode(['reply' => 'عفواً، لم يصلني أي طلب.']);
    exit;
}

$reply = 'عفواً، لم أفهم طلبك. جرب: "أضف منتج جديد" أو "العمليات المفتوحة لعميل س".';
$action = null;

// --- INTENT DETECTION (V7) ---

// ... (Quick commands logic remains the same) ...

$found_command = false;
// ... (Loop for quick commands) ...

if (!$found_command) {
    // --- Complex Intents ---

    // Intent: List Open Purchase Orders for a Supplier
    if (preg_match('/(أوامر الشراء|طلبات الشراء|المشتريات المفتوحة)( للمورد| لـ)? (.*)/u', $message, $matches)) {
        $supplier_name = trim($matches[3]);
        $stmt_supplier = $conn->prepare("SELECT id, name FROM suppliers WHERE name LIKE ?");
        $search_term = "%{$supplier_name}%";
        $stmt_supplier->bind_param("s", $search_term);
        $stmt_supplier->execute();
        $supplier_result = $stmt_supplier->get_result();

        if ($supplier_result->num_rows === 1) {
            $supplier = $supplier_result->fetch_assoc();
            $stmt_pos = $conn->prepare("SELECT id, status, total_amount FROM purchase_orders WHERE supplier_id = ? AND status != 'received' ORDER BY id DESC");
            $stmt_pos->bind_param("i", $supplier['id']);
            $stmt_pos->execute();
            $pos_result = $stmt_pos->get_result();

            $status_labels = ['pending' => 'معلق', 'ordered' => 'تم الطلب', 'partially_received' => 'تم الاستلام جزئياً'];

            if ($pos_result->num_rows > 0) {
                $reply = "✅ أوامر الشراء المفتوحة للمورد <strong>" . htmlspecialchars($supplier['name']) . "</strong>:<ul class='chat-list'>";
                while ($po = $pos_result->fetch_assoc()) {
                    $status_label = $status_labels[$po['status']] ?? ucfirst($po['status']);
                    $reply .= sprintf(
                        "<li><a href='edit_purchase_order.php?id=%d' class='chat-link'>أمر شراء #%d</a> (الحالة: %s)</li>",
                        $po['id'], $po['id'], $status_label
                    );
                }
                $reply .= "</ul>";
            } else {
                $reply = "🎉 لا توجد أوامر شراء مفتوحة حالياً للمورد <strong>" . htmlspecialchars($supplier['name']) . "</strong>.";
            }
        } elseif ($supplier_result->num_rows > 1) {
            $reply = "⚠️ وجدت عدة موردين بهذا الاسم. يرجى تحديد اسم المورد بشكل أدق.";
        } else {
            $reply = "⚠️ لم أجد مورداً بالاسم: <strong>" . htmlspecialchars($supplier_name) . "</strong>.";
        }
    }
    // Fallback to other intents
    elseif (preg_match('/(العمليات المفتوحة|أوامر الشغل|الشغل المفتوح)( لعميل| لـ)? (.*)/u', $message, $matches)) {
        // ... existing open client jobs logic ...
    }
    elseif (preg_match('/(الفواتير غير المدفوعة|الفواتير المستحقة|الديون)/u', $message)) {
        // ... existing unpaid invoices logic ...
    }
    // ... (other intents) ...
}

echo json_encode(['reply' => $reply, 'action' => $action]);
?>