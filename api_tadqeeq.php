<?php
// =====================================================
// api_tadqeeq.php — تدقيق الاستلام والسحب
// =====================================================
header('Content-Type: application/json; charset=utf-8');
require_once 'config.php';

// تحديث بنية جدول الاختلافات تلقائياً لتجنب مشاكل الحفظ
try {
    $conn->query("ALTER TABLE ikhtilaf ADD COLUMN bag_num SMALLINT UNSIGNED NOT NULL AFTER op_id");
    $conn->query("ALTER TABLE ikhtilaf ADD COLUMN diff_amount DECIMAL(18,3) NOT NULL AFTER bag_num");
    $conn->query("ALTER TABLE ikhtilaf ADD COLUMN status ENUM('pending','resolved') NOT NULL DEFAULT 'pending'");
    $conn->query("ALTER TABLE ikhtilaf ADD COLUMN notes TEXT");
    $conn->query("ALTER TABLE ikhtilaf MODIFY manager_id INT UNSIGNED NULL");
    $conn->query("ALTER TABLE ikhtilaf MODIFY decision ENUM('accept_original','accept_auditor','new_value') NULL");
    $conn->query("ALTER TABLE ikhtilaf MODIFY final_total DECIMAL(18,3) NULL");
    $conn->query("ALTER TABLE ikhtilaf MODIFY original_total DECIMAL(18,3) NULL");
    $conn->query("ALTER TABLE ikhtilaf MODIFY auditor_total DECIMAL(18,3) NULL");
    $conn->query("ALTER TABLE ikhtilaf MODIFY difference DECIMAL(18,3) NULL");
} catch(Throwable $e) { }

$user   = require_auth();
$action = $_REQUEST['action'] ?? '';

switch ($action) {

    // ── جلب تفاصيل الاستلام برقم العملية ────────────────────
    case 'get_istilam_info':
        $op_num = trim($_GET['op_num'] ?? '');
        if (!$op_num) json_error('رقم العملية مطلوب');

        $stmt = $conn->prepare('SELECT i.*, c.name as client_name, u.full_name as emp_name FROM istilam i JOIN clients c ON c.id = i.client_id JOIN users u ON u.id = i.emp_id WHERE i.op_num = ?');
        $stmt->bind_param('s', $op_num);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res->num_rows === 0) json_error('العملية غير موجودة', 404);
        
        $info = $res->fetch_assoc();
        json_success($info);
        break;

    // ── حفظ تدقيق استلام ────────────────────────────
    case 'save_istilam':
        $istilam_id  = (int)($_POST['istilam_id'] ?? 0);
        $bag_num     = (int)($_POST['bag_num']    ?? 0);
        $auditor_id  = $user['id'];
        $d50000      = (int)($_POST['d50000'] ?? 0);
        $d25000      = (int)($_POST['d25000'] ?? 0);
        $d10000      = (int)($_POST['d10000'] ?? 0);
        $d5000       = (int)($_POST['d5000']  ?? 0);
        $d1000       = (int)($_POST['d1000']  ?? 0);
        $d500        = (int)($_POST['d500']   ?? 0);
        $d250        = (int)($_POST['d250']   ?? 0);
        $notes       = trim($_POST['notes']   ?? '');

        if (!$istilam_id || !$bag_num) json_error('رقم العملية والكيس مطلوبان');

        $total = ($d50000 * 50000) + ($d25000 * 25000) + ($d10000 * 10000)
               + ($d5000  *  5000) + ($d1000  *  1000) + ($d500   *   500)
               + ($d250   *   250);

        // جلب المبلغ الأصلي للمقارنة من جدول الأكياس
        $orig = $conn->prepare('SELECT b.total_amount, i.client_id, i.currency FROM istilam_bags b JOIN istilam i ON i.id = b.istilam_id WHERE b.istilam_id = ? AND b.bag_num = ?');
        $orig->bind_param('ii', $istilam_id, $bag_num);
        $orig->execute();
        $orig_row = $orig->get_result()->fetch_assoc();
        if (!$orig_row) json_error('الكيس غير موجود ضمن عملية الاستلام المحددة', 404);

        $match = (abs($total - (float)$orig_row['total_amount']) < 0.001) ? 'match' : 'mismatch';

        $stmt = $conn->prepare(
            'INSERT INTO tadqeeq_istilam
             (istilam_id, bag_num, auditor_id, d50000, d25000, d10000, d5000, d1000, d500, d250,
              total_amount, match_status, notes)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)'
        );
        $stmt->bind_param(
            'iiiiiiiiiiids',
            $istilam_id, $bag_num, $auditor_id,
            $d50000, $d25000, $d10000, $d5000, $d1000, $d500, $d250,
            $total, $match, $notes
        );

        if ($stmt->execute()) {
            $tadqeeq_id = (int)$conn->insert_id;
            
            // إذا في اختلاف، حولها للمدير (جدول الاختلافات)
            if ($match === 'mismatch') {
                $upd = $conn->prepare("UPDATE istilam SET status = 'dispute' WHERE id = ?");
                $upd->bind_param('i', $istilam_id);
                $upd->execute();
                
                $diff_amount = $total - (float)$orig_row['total_amount'];
                $ikh = $conn->prepare("INSERT INTO ikhtilaf (op_type, op_id, bag_num, diff_amount, notes, status) VALUES ('istilam', ?, ?, ?, 'اختلاف أثناء التدقيق', 'pending')");
                $ikh->bind_param('iid', $istilam_id, $bag_num, $diff_amount);
                $ikh->execute();
            } else {
                // إذا متطابقة، يثبتها نقد غير معدود (للمدير أو الخزنة)
                // سنقوم بإنشاء جدول نقد غير معدود مؤقت أو تحديث حالة العملية
                $conn->query("CREATE TABLE IF NOT EXISTS naqd_ghayrmaadoood (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    istilam_id INT UNSIGNED NOT NULL,
                    bag_num SMALLINT UNSIGNED NOT NULL,
                    amount DECIMAL(18,3) NOT NULL,
                    currency VARCHAR(50) NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )");
                $ins_naqd = $conn->prepare("INSERT INTO naqd_ghayrmaadoood (istilam_id, bag_num, amount, currency) VALUES (?, ?, ?, ?)");
                $ins_naqd->bind_param('iids', $istilam_id, $bag_num, $total, $orig_row['currency']);
                $ins_naqd->execute();

                // التحقق ما إذا كانت جميع الأكياس مدققة بنجاح
                $check = $conn->prepare("SELECT COUNT(*) as c FROM istilam_bags WHERE istilam_id = ?");
                $check->bind_param('i', $istilam_id);
                $check->execute();
                $bags_total = $check->get_result()->fetch_assoc()['c'];
                
                $check2 = $conn->prepare("SELECT COUNT(*) as c FROM tadqeeq_istilam WHERE istilam_id = ? AND match_status='match'");
                $check2->bind_param('i', $istilam_id);
                $check2->execute();
                $bags_audited = $check2->get_result()->fetch_assoc()['c'];
                
                if ($bags_total == $bags_audited) {
                    $upd = $conn->prepare("UPDATE istilam SET status = 'audited' WHERE id = ?");
                    $upd->bind_param('i', $istilam_id);
                    $upd->execute();
                }
            }

            log_action('tadqeeq_istilam_' . $match, 'tadqeeq_istilam', (int)$conn->insert_id);
            json_success([
                'match'  => $match,
                'total'  => $total,
            ], $match === 'match' ? 'تدقيق ✓ مطابق' : '⚠️ يوجد اختلاف');
        } else {
            json_error('حدث خطأ أثناء الحفظ');
        }
        break;

    // ── حفظ تدقيق سحب ───────────────────────────────
    case 'save_sahb':
        $sahb_id    = (int)($_POST['sahb_id']   ?? 0);
        $auditor_id = $user['id'];
        $d50000     = (int)($_POST['d50000'] ?? 0);
        $d25000     = (int)($_POST['d25000'] ?? 0);
        $d10000     = (int)($_POST['d10000'] ?? 0);
        $d5000      = (int)($_POST['d5000']  ?? 0);
        $d1000      = (int)($_POST['d1000']  ?? 0);
        $d500       = (int)($_POST['d500']   ?? 0);
        $d250       = (int)($_POST['d250']   ?? 0);
        $notes      = trim($_POST['notes']   ?? '');

        if (!$sahb_id) json_error('رقم عملية السحب مطلوب');

        $total = ($d50000 * 50000) + ($d25000 * 25000) + ($d10000 * 10000)
               + ($d5000  *  5000) + ($d1000  *  1000) + ($d500   *   500)
               + ($d250   *   250);

        $orig = $conn->prepare('SELECT total_amount FROM sahb WHERE id = ?');
        $orig->bind_param('i', $sahb_id);
        $orig->execute();
        $orig_row = $orig->get_result()->fetch_assoc();
        if (!$orig_row) json_error('عملية السحب غير موجودة', 404);

        $match = (abs($total - (float)$orig_row['total_amount']) < 0.001) ? 'match' : 'mismatch';

        $stmt = $conn->prepare(
            'INSERT INTO tadqeeq_sahb
             (sahb_id, auditor_id, d50000, d25000, d10000, d5000, d1000, d500, d250,
              total_amount, match_status, notes)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?)'
        );
        $stmt->bind_param(
            'iiiiiiiiiids',
            $sahb_id, $auditor_id,
            $d50000, $d25000, $d10000, $d5000, $d1000, $d500, $d250,
            $total, $match, $notes
        );

        if ($stmt->execute()) {
            if ($match === 'mismatch') {
                $upd = $conn->prepare("UPDATE sahb SET status = 'dispute' WHERE id = ?");
            } else {
                $upd = $conn->prepare("UPDATE sahb SET status = 'audited' WHERE id = ?");
            }
            $upd->bind_param('i', $sahb_id);
            $upd->execute();

            log_action('tadqeeq_sahb_' . $match, 'tadqeeq_sahb', (int)$conn->insert_id);
            json_success(['match' => $match, 'total' => $total],
                $match === 'match' ? 'تدقيق ✓ مطابق' : '⚠️ يوجد اختلاف');
        } else {
            json_error('حدث خطأ أثناء الحفظ');
        }
        break;

    // ── جلب نتائج التدقيق لعملية ─────────────────────
    case 'get_istilam_results':
        $id = (int)($_GET['istilam_id'] ?? 0);
        if (!$id) json_error('id مطلوب');

        $stmt = $conn->prepare(
            'SELECT t.*, u.full_name AS auditor_name
             FROM tadqeeq_istilam t
             JOIN users u ON u.id = t.auditor_id
             WHERE t.istilam_id = ?
             ORDER BY t.bag_num'
        );
        $stmt->bind_param('i', $id);
        $stmt->execute();
        json_success($stmt->get_result()->fetch_all(MYSQLI_ASSOC));
        break;

    case 'get_sahb_results':
        $id = (int)($_GET['sahb_id'] ?? 0);
        if (!$id) json_error('id مطلوب');

        $stmt = $conn->prepare(
            'SELECT t.*, u.full_name AS auditor_name
             FROM tadqeeq_sahb t
             JOIN users u ON u.id = t.auditor_id
             WHERE t.sahb_id = ?'
        );
        $stmt->bind_param('i', $id);
        $stmt->execute();
        json_success($stmt->get_result()->fetch_all(MYSQLI_ASSOC));
        break;

    default:
        json_error('action غير معروف', 404);
}

$conn->close();
?>
