<?php
// =====================================================
// api_taayeen.php — تعيين الأكياس
// =====================================================
header('Content-Type: application/json; charset=utf-8');
require_once 'config.php';

$user   = require_auth();
$action = $_REQUEST['action'] ?? '';

switch ($action) {

    // ── حفظ تعيين كيس ───────────────────────────────
    case 'save':
        $op_type         = $_POST['op_type']          ?? '';
        $op_id           = (int)($_POST['op_id']       ?? 0);
        $bag_num         = (int)($_POST['bag_num']      ?? 0);
        $counter_emp_id  = (int)($_POST['counter_emp_id'] ?? 0);
        $device_counter  = trim($_POST['device_counter']  ?? '');
        $device_camera   = trim($_POST['device_camera']   ?? '');
        $device_rcounter = trim($_POST['device_rcounter'] ?? '');
        $device_rcamera  = trim($_POST['device_rcamera']  ?? '');

        $valid_types = ['istilam', 'sahb'];
        if (!in_array($op_type, $valid_types, true)) json_error('نوع عملية غير صالح');
        if (!$op_id || !$bag_num || !$counter_emp_id) json_error('البيانات الأساسية مطلوبة');

        $stmt = $conn->prepare(
            'INSERT INTO taayeen
             (op_type, op_id, bag_num, counter_emp_id,
              device_counter, device_camera, device_rcounter, device_rcamera)
             VALUES (?,?,?,?,?,?,?,?)'
        );
        $stmt->bind_param(
            'siiissss',
            $op_type, $op_id, $bag_num, $counter_emp_id,
            $device_counter, $device_camera, $device_rcounter, $device_rcamera
        );

        if ($stmt->execute()) {
            log_action('taayeen_save', 'taayeen', (int)$conn->insert_id);
            json_success(['id' => $conn->insert_id], 'تم تعيين الكيس بنجاح');
        } else {
            json_error('حدث خطأ أثناء الحفظ');
        }
        break;

    // ── جلب الأجهزة المتاحة ──────────────────────────
    case 'get_devices':
        $type  = $_GET['type'] ?? '';
        $valid = ['counter', 'camera', 'recounter', 'recamera'];
        if (!in_array($type, $valid, true)) json_error('نوع جهاز غير صالح');

        $stmt = $conn->prepare(
            'SELECT device_code FROM devices WHERE device_type = ? AND is_active = 1 ORDER BY device_code'
        );
        $stmt->bind_param('s', $type);
        $stmt->execute();
        json_success($stmt->get_result()->fetch_all(MYSQLI_ASSOC));
        break;

    default:
        json_error('action غير معروف', 404);
}

$conn->close();
?>
