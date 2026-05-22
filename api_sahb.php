<?php
// =====================================================
// api_sahb.php — عمليات السحب
// =====================================================
header('Content-Type: application/json; charset=utf-8');
require_once 'config.php';

$user   = require_auth();
$action = $_REQUEST['action'] ?? '';

switch ($action) {

    case 'get_all':
        $stmt = $conn->prepare(
            'SELECT s.*, u.full_name AS emp_name, c.name AS client_name
             FROM sahb s
             JOIN users   u ON u.id = s.emp_id
             JOIN clients c ON c.id = s.client_id
             ORDER BY s.created_at DESC LIMIT 500'
        );
        $stmt->execute();
        json_success($stmt->get_result()->fetch_all(MYSQLI_ASSOC));
        break;

    case 'get_one':
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) json_error('id مطلوب');

        $stmt = $conn->prepare(
            'SELECT s.*, u.full_name AS emp_name, c.name AS client_name
             FROM sahb s
             JOIN users   u ON u.id = s.emp_id
             JOIN clients c ON c.id = s.client_id
             WHERE s.id = ?'
        );
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if (!$row) json_error('العملية غير موجودة', 404);
        json_success($row);
        break;

    case 'save':
        $emp_id    = (int)($_POST['emp_id']    ?? 0);
        $client_id = (int)($_POST['client_id'] ?? 0);
        $op_date   = $_POST['op_date']  ?? '';
        $currency  = $_POST['currency'] ?? 'دينار';
        $d50000    = (int)($_POST['d50000'] ?? 0);
        $d25000    = (int)($_POST['d25000'] ?? 0);
        $d10000    = (int)($_POST['d10000'] ?? 0);
        $d5000     = (int)($_POST['d5000']  ?? 0);
        $d1000     = (int)($_POST['d1000']  ?? 0);
        $d500      = (int)($_POST['d500']   ?? 0);
        $d250      = (int)($_POST['d250']   ?? 0);

        if (!$emp_id || !$client_id || !$op_date) {
            json_error('الموظف والعميل والتاريخ مطلوبة');
        }

        $total = ($d50000 * 50000) + ($d25000 * 25000) + ($d10000 * 10000)
               + ($d5000  *  5000) + ($d1000  *  1000) + ($d500   *   500)
               + ($d250   *   250);

        $valid_currencies = ['دينار', 'دولار', 'يورو'];
        if (!in_array($currency, $valid_currencies, true)) json_error('عملة غير صالحة');

        $op_num = generate_op_num('SAH');

        $stmt = $conn->prepare(
            'INSERT INTO sahb
             (op_num, emp_id, client_id, op_date, currency, total_amount,
              d50000, d25000, d10000, d5000, d1000, d500, d250)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)'
        );
        $stmt->bind_param(
            'siissdiiiiiii',
            $op_num, $emp_id, $client_id, $op_date, $currency, $total,
            $d50000, $d25000, $d10000, $d5000, $d1000, $d500, $d250
        );

        if ($stmt->execute()) {
            $new_id = (int)$conn->insert_id;
            log_action('sahb_save', 'sahb', $new_id);
            json_success(['id' => $new_id, 'op_num' => $op_num], 'تم حفظ عملية السحب بنجاح');
        } else {
            json_error('حدث خطأ أثناء الحفظ');
        }
        break;

    case 'get_pending':
        $stmt = $conn->prepare(
            "SELECT s.id, s.op_num, c.name AS client_name, s.total_amount
             FROM sahb s
             JOIN clients c ON c.id = s.client_id
             WHERE s.status = 'pending'
             ORDER BY s.created_at DESC"
        );
        $stmt->execute();
        json_success($stmt->get_result()->fetch_all(MYSQLI_ASSOC));
        break;

    default:
        json_error('action غير معروف', 404);
}

$conn->close();
?>
