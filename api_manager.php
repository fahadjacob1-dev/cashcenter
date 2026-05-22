<?php
// =====================================================
// api_manager.php — واجهة المدير لمعالجة الاختلافات
// =====================================================
header('Content-Type: application/json; charset=utf-8');
require_once 'config.php';

$user   = require_auth();
$action = $_REQUEST['action'] ?? '';

switch ($action) {

    // ── جلب الاختلافات المعلقة ────────────────────────────
    case 'get_pending_disputes':
        // يجلب كل الاختلافات المعلقة من جدول ikhtilaf
        $stmt = $conn->prepare(
            "SELECT ik.id as ikhtilaf_id, ik.op_type, ik.op_id, ik.bag_num, i.op_num, i.client_id, c.name as client_name, i.currency, i.op_date
             FROM ikhtilaf ik
             JOIN istilam i ON i.id = ik.op_id
             LEFT JOIN clients c ON c.id = i.client_id
             WHERE ik.op_type = 'istilam' AND ik.status = 'pending'"
        );
        $stmt->execute();
        $res = $stmt->get_result();
        $disputes = $res->fetch_all(MYSQLI_ASSOC);
        json_success($disputes);
        break;

    // ── جلب تفاصيل اختلاف لكيس معين ────────────────────────────
    case 'get_dispute_details':
        $ikh_id = (int)($_GET['ikhtilaf_id'] ?? 0);
        if (!$ikh_id) json_error('رقم الاختلاف مطلوب');

        // جلب معلومات الاختلاف
        $stmt = $conn->prepare("SELECT * FROM ikhtilaf WHERE id = ?");
        $stmt->bind_param('i', $ikh_id);
        $stmt->execute();
        $ikh = $stmt->get_result()->fetch_assoc();
        if (!$ikh) json_error('الاختلاف غير موجود', 404);

        $op_id = $ikh['op_id'];
        $bag_num = $ikh['bag_num'];

        // جلب الاستلام الأولي (istilam_bags)
        $stmt_init = $conn->prepare("SELECT * FROM istilam_bags WHERE istilam_id = ? AND bag_num = ?");
        $stmt_init->bind_param('ii', $op_id, $bag_num);
        $stmt_init->execute();
        $initial = $stmt_init->get_result()->fetch_assoc();

        // جلب تدقيق المدقق (tadqeeq_istilam)
        $stmt_aud = $conn->prepare("SELECT * FROM tadqeeq_istilam WHERE istilam_id = ? AND bag_num = ? ORDER BY id DESC LIMIT 1");
        $stmt_aud->bind_param('ii', $op_id, $bag_num);
        $stmt_aud->execute();
        $auditor = $stmt_aud->get_result()->fetch_assoc();

        json_success([
            'initial' => $initial,
            'auditor' => $auditor
        ]);
        break;

    // ── حفظ قرار المدير ────────────────────────────
    case 'resolve_dispute':
        $ikh_id = (int)($_POST['ikhtilaf_id'] ?? 0);
        if (!$ikh_id) json_error('رقم الاختلاف مطلوب');

        $d50000 = (int)($_POST['d50000'] ?? 0);
        $d25000 = (int)($_POST['d25000'] ?? 0);
        $d10000 = (int)($_POST['d10000'] ?? 0);
        $d5000  = (int)($_POST['d5000']  ?? 0);
        $d1000  = (int)($_POST['d1000']  ?? 0);
        $d500   = (int)($_POST['d500']   ?? 0);
        $d250   = (int)($_POST['d250']   ?? 0);
        
        $total = ($d50000 * 50000) + ($d25000 * 25000) + ($d10000 * 10000)
               + ($d5000  *  5000) + ($d1000  *  1000) + ($d500   *   500)
               + ($d250   *   250);

        // جلب الاختلاف
        $stmt = $conn->prepare("SELECT * FROM ikhtilaf WHERE id = ?");
        $stmt->bind_param('i', $ikh_id);
        $stmt->execute();
        $ikh = $stmt->get_result()->fetch_assoc();
        if (!$ikh) json_error('الاختلاف غير موجود');

        $op_id = $ikh['op_id'];
        $bag_num = $ikh['bag_num'];

        // جلب العملية الأصلية لمعرفة العملة
        $stmt_op = $conn->prepare("SELECT currency FROM istilam WHERE id = ?");
        $stmt_op->bind_param('i', $op_id);
        $stmt_op->execute();
        $op = $stmt_op->get_result()->fetch_assoc();
        
        $conn->begin_transaction();
        try {
            // 1. تحديث حالة الاختلاف
            $upd = $conn->prepare("UPDATE ikhtilaf SET status = 'resolved', notes = CONCAT(notes, ' | تم الحسم من المدير بمبلغ: ', ?) WHERE id = ?");
            $upd->bind_param('di', $total, $ikh_id);
            $upd->execute();

            // 2. إدخال القيمة المعتمدة في الخزنة (النقد المعدود أو غير المعدود حسب طلب العميل)
            $conn->query("CREATE TABLE IF NOT EXISTS naqd_ghayrmaadoood (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                istilam_id INT UNSIGNED NOT NULL,
                bag_num SMALLINT UNSIGNED NOT NULL,
                amount DECIMAL(18,3) NOT NULL,
                currency VARCHAR(50) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )");
            $ins_naqd = $conn->prepare("INSERT INTO naqd_ghayrmaadoood (istilam_id, bag_num, amount, currency) VALUES (?, ?, ?, ?)");
            $ins_naqd->bind_param('iids', $op_id, $bag_num, $total, $op['currency']);
            $ins_naqd->execute();

            // 3. التحقق إذا كل أكياس العملية انتهت من التدقيق/الاختلاف لنحدث حالة العملية الكلية إلى audited
            $check = $conn->prepare("SELECT COUNT(*) as c FROM istilam_bags WHERE istilam_id = ?");
            $check->bind_param('i', $op_id);
            $check->execute();
            $bags_total = $check->get_result()->fetch_assoc()['c'];
            
            $check2 = $conn->prepare("SELECT COUNT(*) as c FROM tadqeeq_istilam WHERE istilam_id = ? AND match_status='match'");
            $check2->bind_param('i', $op_id);
            $check2->execute();
            $bags_audited = $check2->get_result()->fetch_assoc()['c'];

            $check3 = $conn->prepare("SELECT COUNT(*) as c FROM ikhtilaf WHERE op_id = ? AND status='resolved'");
            $check3->bind_param('i', $op_id);
            $check3->execute();
            $bags_resolved = $check3->get_result()->fetch_assoc()['c'];

            if ($bags_total == ($bags_audited + $bags_resolved)) {
                $upd_op = $conn->prepare("UPDATE istilam SET status = 'audited' WHERE id = ?");
                $upd_op->bind_param('i', $op_id);
                $upd_op->execute();
            }

            $conn->commit();
            json_success(['total' => $total], 'تم تثبيت الكيس بنجاح وتحديث الخزنة');
        } catch (Exception $e) {
            $conn->rollback();
            json_error('حدث خطأ أثناء التثبيت: ' . $e->getMessage());
        }
        break;

    default:
        json_error('action غير صالح');
}
?>
