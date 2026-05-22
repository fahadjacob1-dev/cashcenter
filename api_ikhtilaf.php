<?php
// =====================================================
// api_ikhtilaf.php — قرارات الاختلاف
// =====================================================
header('Content-Type: application/json; charset=utf-8');
require_once 'config.php';

$user   = require_auth();
$action = $_REQUEST['action'] ?? '';

switch ($action) {

    // ── حفظ قرار المدير ─────────────────────────────
    case 'save':
        require_role('admin', 'supervisor');

        $op_type   = $_POST['op_type']  ?? '';
        $op_id     = (int)($_POST['op_id'] ?? 0);
        $decision  = $_POST['decision'] ?? '';
        $notes     = trim($_POST['notes'] ?? '');

        $valid_types    = ['istilam', 'sahb'];
        $valid_decisions = ['accept_original', 'accept_auditor', 'new_value'];

        if (!in_array($op_type, $valid_types, true))     json_error('نوع عملية غير صالح');
        if (!in_array($decision, $valid_decisions, true)) json_error('قرار غير صالح');
        if (!$op_id) json_error('رقم العملية مطلوب');

        $d50000 = (int)($_POST['d50000'] ?? 0);
        $d25000 = (int)($_POST['d25000'] ?? 0);
        $d10000 = (int)($_POST['d10000'] ?? 0);
        $d5000  = (int)($_POST['d5000']  ?? 0);
        $d1000  = (int)($_POST['d1000']  ?? 0);
        $d500   = (int)($_POST['d500']   ?? 0);
        $d250   = (int)($_POST['d250']   ?? 0);

        $manager_total = ($d50000 * 50000) + ($d25000 * 25000) + ($d10000 * 10000)
                       + ($d5000  *  5000) + ($d1000  *  1000) + ($d500   *   500)
                       + ($d250   *   250);

        // جلب المبالغ الأصلية
        $table = $op_type === 'istilam' ? 'istilam' : 'sahb';
        $orig  = $conn->prepare("SELECT total_amount FROM `$table` WHERE id = ?");
        $orig->bind_param('i', $op_id);
        $orig->execute();
        $orig_row = $orig->get_result()->fetch_assoc();
        if (!$orig_row) json_error('العملية غير موجودة', 404);

        // جلب مبلغ التدقيق
        if ($op_type === 'istilam') {
            $aud = $conn->prepare('SELECT SUM(total_amount) AS t FROM tadqeeq_istilam WHERE istilam_id = ?');
        } else {
            $aud = $conn->prepare('SELECT total_amount AS t FROM tadqeeq_sahb WHERE sahb_id = ? ORDER BY id DESC LIMIT 1');
        }
        $aud->bind_param('i', $op_id);
        $aud->execute();
        $aud_row  = $aud->get_result()->fetch_assoc();

        $orig_total  = (float)$orig_row['total_amount'];
        $audit_total = (float)($aud_row['t'] ?? 0);
        $diff        = $orig_total - $audit_total;

        $final_total = match($decision) {
            'accept_original' => $orig_total,
            'accept_auditor'  => $audit_total,
            'new_value'       => $manager_total,
        };

        $manager_id = $user['id'];

        $stmt = $conn->prepare(
            'INSERT INTO ikhtilaf
             (op_type, op_id, original_total, auditor_total, difference,
              manager_id, manager_d50000, manager_d25000, manager_d10000,
              manager_d5000, manager_d1000, manager_d500, manager_d250,
              manager_total, decision, final_total, notes)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
        );
        $stmt->bind_param(
            'sidddiiiiiiiidds s',
            $op_type, $op_id, $orig_total, $audit_total, $diff,
            $manager_id, $d50000, $d25000, $d10000, $d5000, $d1000, $d500, $d250,
            $manager_total, $decision, $final_total, $notes
        );

        if ($stmt->execute()) {
            // تحديث حالة العملية
            $upd = $conn->prepare("UPDATE `$table` SET status = 'closed', total_amount = ? WHERE id = ?");
            $upd->bind_param('di', $final_total, $op_id);
            $upd->execute();

            log_action('ikhtilaf_' . $decision, 'ikhtilaf', (int)$conn->insert_id);
            json_success(['final_total' => $final_total], 'تم تثبيت قرار المدير بنجاح');
        } else {
            json_error('حدث خطأ أثناء الحفظ: ' . $conn->error);
        }
        break;

    // ── جلب حالات الاختلاف المعلقة ──────────────────
    case 'get_disputes':
        require_role('admin', 'supervisor');
        $rows = [];

        // استلام
        $stmt1 = $conn->prepare(
            "SELECT 'istilam' AS op_type, i.id AS op_id, i.op_num,
                    c.name AS client_name, i.total_amount, i.created_at
             FROM istilam i
             JOIN clients c ON c.id = i.client_id
             WHERE i.status = 'dispute'"
        );
        $stmt1->execute();
        $rows = array_merge($rows, $stmt1->get_result()->fetch_all(MYSQLI_ASSOC));

        // سحب
        $stmt2 = $conn->prepare(
            "SELECT 'sahb' AS op_type, s.id AS op_id, s.op_num,
                    c.name AS client_name, s.total_amount, s.created_at
             FROM sahb s
             JOIN clients c ON c.id = s.client_id
             WHERE s.status = 'dispute'"
        );
        $stmt2->execute();
        $rows = array_merge($rows, $stmt2->get_result()->fetch_all(MYSQLI_ASSOC));

        json_success($rows);
        break;

    default:
        json_error('action غير معروف', 404);
}

$conn->close();
?>
