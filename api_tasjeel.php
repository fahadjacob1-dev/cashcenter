<?php
// =====================================================
// api_tasjeel.php — تسجيل العد والنقد المعدود
// =====================================================
header('Content-Type: application/json; charset=utf-8');
require_once 'config.php';

$user   = require_auth();
$action = $_REQUEST['action'] ?? '';

switch ($action) {

    // ── حفظ عد الكيس ────────────────────────────────
    case 'save_add':
        $op_type    = $_POST['op_type']    ?? '';
        $op_id      = (int)($_POST['op_id']     ?? 0);
        $bag_num    = (int)($_POST['bag_num']    ?? 0);
        $count_date = $_POST['count_date'] ?? '';
        $currency   = $_POST['currency']   ?? 'دينار';
        $d50000     = (int)($_POST['d50000'] ?? 0);
        $d25000     = (int)($_POST['d25000'] ?? 0);
        $d10000     = (int)($_POST['d10000'] ?? 0);
        $d5000      = (int)($_POST['d5000']  ?? 0);
        $d1000      = (int)($_POST['d1000']  ?? 0);
        $d500       = (int)($_POST['d500']   ?? 0);
        $d250       = (int)($_POST['d250']   ?? 0);
        $emp_id     = $user['id'];

        $valid_types = ['istilam', 'sahb'];
        if (!in_array($op_type, $valid_types, true)) json_error('نوع عملية غير صالح');
        if (!$op_id || !$bag_num || !$count_date) json_error('البيانات الأساسية مطلوبة');

        $bag_total = ($d50000 * 50000) + ($d25000 * 25000) + ($d10000 * 10000)
                   + ($d5000  *  5000) + ($d1000  *  1000) + ($d500   *   500)
                   + ($d250   *   250);

        $stmt = $conn->prepare(
            'INSERT INTO tasjeel_add
             (op_type, op_id, bag_num, emp_id, count_date, currency,
              d50000, d25000, d10000, d5000, d1000, d500, d250, bag_total)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
        );
        $stmt->bind_param(
            'siiiissiiiiiiid',
            $op_type, $op_id, $bag_num, $emp_id, $count_date, $currency,
            $d50000, $d25000, $d10000, $d5000, $d1000, $d500, $d250, $bag_total
        );

        if ($stmt->execute()) {
            log_action('tasjeel_add_save', 'tasjeel_add', (int)$conn->insert_id);
            json_success(['bag_total' => $bag_total], 'تم تسجيل العد بنجاح');
        } else {
            json_error('حدث خطأ أثناء الحفظ');
        }
        break;

    // ── حفظ النقد المعدود ────────────────────────────
    case 'save_naqdmaadood':
        $op_type    = $_POST['op_type']    ?? '';
        $op_id      = (int)($_POST['op_id']     ?? 0);
        $bag_num    = (int)($_POST['bag_num']    ?? 0);
        $count_date = $_POST['count_date'] ?? '';
        $currency   = $_POST['currency']   ?? 'دينار';
        $emp_id     = $user['id'];

        $valid_types = ['istilam', 'sahb'];
        if (!in_array($op_type, $valid_types, true)) json_error('نوع عملية غير صالح');
        if (!$op_id || !$count_date) json_error('البيانات الأساسية مطلوبة');

        // الفئات — نقص وزيادة
        $fields = ['d50000','d25000','d10000','d5000','d1000','d500','d250'];
        $params = [];
        $actual = 0;

        foreach ($fields as $f) {
            $naqis  = (int)($_POST[$f.'_naqis']  ?? 0);
            $ziyada = (int)($_POST[$f.'_ziyada'] ?? 0);
            $params[$f.'_naqis']  = $naqis;
            $params[$f.'_ziyada'] = $ziyada;
            $denom = (int)str_replace('d', '', $f);
            $actual += $ziyada * $denom - $naqis * $denom;
        }

        $mazayaf       = (int)($_POST['mazayaf']       ?? 0);
        $ikhtilaf_arqam = (int)($_POST['ikhtilaf_arqam'] ?? 0);
        $maktabi       = (int)($_POST['maktabi']       ?? 0);
        $mahrooq       = (int)($_POST['mahrooq']       ?? 0);
        $talif_qeema   = (int)($_POST['talif_qeema']   ?? 0);
        $soo_istikhdam = (int)($_POST['soo_istikhdam'] ?? 0);

        $stmt = $conn->prepare(
            'INSERT INTO naqdmaadood
             (op_type, op_id, bag_num, emp_id, count_date, currency,
              d50000_naqis, d50000_ziyada, d25000_naqis, d25000_ziyada,
              d10000_naqis, d10000_ziyada, d5000_naqis, d5000_ziyada,
              d1000_naqis, d1000_ziyada, d500_naqis, d500_ziyada,
              d250_naqis, d250_ziyada,
              mazayaf, ikhtilaf_arqam, maktabi, mahrooq, talif_qeema, soo_istikhdam,
              actual_amount)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
        );
        $stmt->bind_param(
            'siiiissiiiiiiiiiiiiiiiiiiid',
            $op_type, $op_id, $bag_num, $emp_id, $count_date, $currency,
            $params['d50000_naqis'],  $params['d50000_ziyada'],
            $params['d25000_naqis'],  $params['d25000_ziyada'],
            $params['d10000_naqis'],  $params['d10000_ziyada'],
            $params['d5000_naqis'],   $params['d5000_ziyada'],
            $params['d1000_naqis'],   $params['d1000_ziyada'],
            $params['d500_naqis'],    $params['d500_ziyada'],
            $params['d250_naqis'],    $params['d250_ziyada'],
            $mazayaf, $ikhtilaf_arqam, $maktabi, $mahrooq, $talif_qeema, $soo_istikhdam,
            $actual
        );

        if ($stmt->execute()) {
            log_action('naqdmaadood_save', 'naqdmaadood', (int)$conn->insert_id);
            json_success(['actual_amount' => $actual], 'تم تسجيل النقد المعدود بنجاح');
        } else {
            json_error('حدث خطأ: ' . $conn->error);
        }
        break;

    // ── جلب سجل عد لعملية ───────────────────────────
    case 'get_add_records':
        $op_type = $_GET['op_type'] ?? '';
        $op_id   = (int)($_GET['op_id'] ?? 0);
        if (!$op_id) json_error('id مطلوب');

        $stmt = $conn->prepare(
            'SELECT ta.*, u.full_name AS emp_name
             FROM tasjeel_add ta
             JOIN users u ON u.id = ta.emp_id
             WHERE ta.op_type = ? AND ta.op_id = ?
             ORDER BY ta.bag_num'
        );
        $stmt->bind_param('si', $op_type, $op_id);
        $stmt->execute();
        json_success($stmt->get_result()->fetch_all(MYSQLI_ASSOC));
        break;

    default:
        json_error('action غير معروف', 404);
}

$conn->close();
?>
