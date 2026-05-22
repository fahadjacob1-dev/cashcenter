<?php
// =====================================================
// api_tadqeeq.php — تدقيق الاستلام والسحب
// =====================================================
header('Content-Type: application/json; charset=utf-8');
require_once 'config.php';

$user   = require_auth();
$action = $_REQUEST['action'] ?? '';

switch ($action) {

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

        // جلب المبلغ الأصلي للمقارنة
        $orig = $conn->prepare('SELECT total_amount FROM istilam WHERE id = ?');
        $orig->bind_param('i', $istilam_id);
        $orig->execute();
        $orig_row = $orig->get_result()->fetch_assoc();
        if (!$orig_row) json_error('عملية الاستلام غير موجودة', 404);

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
            // إذا في اختلاف، حدّث حالة الاستلام
            if ($match === 'mismatch') {
                $upd = $conn->prepare("UPDATE istilam SET status = 'dispute' WHERE id = ?");
                $upd->bind_param('i', $istilam_id);
                $upd->execute();
            } else {
                $upd = $conn->prepare("UPDATE istilam SET status = 'audited' WHERE id = ?");
                $upd->bind_param('i', $istilam_id);
                $upd->execute();
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
