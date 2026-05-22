<?php
// =====================================================
// api_istilam.php — عمليات الاستلام
// =====================================================
header('Content-Type: application/json; charset=utf-8');
require_once 'config.php';

$user   = require_auth();
$action = $_REQUEST['action'] ?? '';

switch ($action) {

    // ── جلب كل عمليات الاستلام ──────────────────────
    case 'get_all':
        $stmt = $conn->prepare(
            'SELECT i.*, u.full_name AS emp_name, c.name AS client_name
             FROM istilam i
             JOIN users   u ON u.id = i.emp_id
             JOIN clients c ON c.id = i.client_id
             ORDER BY i.created_at DESC
             LIMIT 500'
        );
        $stmt->execute();
        json_success($stmt->get_result()->fetch_all(MYSQLI_ASSOC));
        break;

    // ── جلب عملية واحدة ─────────────────────────────
    case 'get_one':
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) json_error('id مطلوب');

        $stmt = $conn->prepare(
            'SELECT i.*, u.full_name AS emp_name, c.name AS client_name
             FROM istilam i
             JOIN users   u ON u.id = i.emp_id
             JOIN clients c ON c.id = i.client_id
             WHERE i.id = ?'
        );
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if (!$row) json_error('العملية غير موجودة', 404);
        json_success($row);
        break;

    // ── حفظ عملية استلام جديدة ──────────────────────
    case 'save':
        $emp_id    = (int)($_POST['emp_id']      ?? 0);
        $client_id = (int)($_POST['client_id']   ?? 0);
        $op_date   = $_POST['op_date']    ?? '';
        $currency  = $_POST['currency']   ?? 'دينار';
        $bags      = (int)($_POST['bags_count']  ?? 0);
        $liquidity = (float)($_POST['liquidity'] ?? 0);
        $d50000    = (int)($_POST['d50000']  ?? 0);
        $d25000    = (int)($_POST['d25000']  ?? 0);
        $d10000    = (int)($_POST['d10000']  ?? 0);
        $d5000     = (int)($_POST['d5000']   ?? 0);
        $d1000     = (int)($_POST['d1000']   ?? 0);
        $d500      = (int)($_POST['d500']    ?? 0);
        $d250      = (int)($_POST['d250']    ?? 0);

        // Validation
        if (!$emp_id || !$client_id || !$op_date) {
            json_error('الموظف والعميل والتاريخ مطلوبة');
        }

        // حساب المجموع
        $total = ($d50000 * 50000) + ($d25000 * 25000) + ($d10000 * 10000)
               + ($d5000  *  5000) + ($d1000  *  1000) + ($d500   *   500)
               + ($d250   *   250);

        $valid_currencies = ['دينار', 'دولار', 'يورو'];
        if (!in_array($currency, $valid_currencies, true)) json_error('عملة غير صالحة');

        $op_num = generate_op_num('IST');

        $stmt = $conn->prepare(
            'INSERT INTO istilam
             (op_num, emp_id, client_id, op_date, currency, total_amount, bags_count, liquidity,
              d50000, d25000, d10000, d5000, d1000, d500, d250)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
        );
        $stmt->bind_param(
            'siissddiiiiiiii',
            $op_num, $emp_id, $client_id, $op_date, $currency,
            $total, $bags, $liquidity,
            $d50000, $d25000, $d10000, $d5000, $d1000, $d500, $d250
        );

        if ($stmt->execute()) {
            $new_id = (int)$conn->insert_id;
            log_action('istilam_save', 'istilam', $new_id);
            json_success(['id' => $new_id, 'op_num' => $op_num], 'تم حفظ عملية الاستلام بنجاح');
        } else {
            json_error('حدث خطأ أثناء الحفظ');
        }
        break;

    // ── جلب العمليات للـ dropdowns ───────────────────
    case 'get_pending':
        $stmt = $conn->prepare(
            "SELECT i.id, i.op_num, c.name AS client_name, i.total_amount, i.bags_count
             FROM istilam i
             JOIN clients c ON c.id = i.client_id
             WHERE i.status = 'pending'
             ORDER BY i.created_at DESC"
        );
        $stmt->execute();
        json_success($stmt->get_result()->fetch_all(MYSQLI_ASSOC));
        break;

    // ── تحديث حالة العملية ───────────────────────────
    case 'update_status':
        require_role('admin', 'supervisor');
        $id     = (int)($_POST['id']     ?? 0);
        $status = $_POST['status'] ?? '';
        $valid  = ['pending', 'audited', 'closed', 'dispute'];

        if (!$id || !in_array($status, $valid, true)) json_error('بيانات غير صالحة');

        $stmt = $conn->prepare('UPDATE istilam SET status = ? WHERE id = ?');
        $stmt->bind_param('si', $status, $id);
        $stmt->execute();
        log_action('istilam_status_' . $status, 'istilam', $id);
        json_success(null, 'تم تحديث الحالة');
        break;

    default:
        json_error('action غير معروف', 404);
}

$conn->close();
?>
