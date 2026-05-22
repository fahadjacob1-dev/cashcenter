<?php
// =====================================================
// api_taqareer.php — التقارير
// =====================================================
header('Content-Type: application/json; charset=utf-8');
require_once 'config.php';

$user     = require_auth();
$action   = $_GET['action']    ?? '';
$date_from = $_GET['date_from'] ?? date('Y-m-d');
$date_to   = $_GET['date_to']   ?? date('Y-m-d');
$currency  = $_GET['currency']  ?? 'دينار';

// Validate inputs
$valid_currencies = ['دينار', 'دولار', 'يورو'];
if (!in_array($currency, $valid_currencies, true)) json_error('عملة غير صالحة');

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_from) ||
    !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_to)) {
    json_error('تنسيق التاريخ غير صحيح');
}

switch ($action) {

    // ── تقرير الاستلام ───────────────────────────────
    case 'istilam':
        $stmt = $conn->prepare(
            'SELECT i.op_num, u.full_name AS emp_name, c.name AS client_name,
                    i.op_date, i.currency, i.total_amount, i.bags_count, i.status
             FROM istilam i
             JOIN users   u ON u.id = i.emp_id
             JOIN clients c ON c.id = i.client_id
             WHERE DATE(i.op_date) BETWEEN ? AND ?
               AND i.currency = ?
             ORDER BY i.op_date DESC'
        );
        $stmt->bind_param('sss', $date_from, $date_to, $currency);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        json_success(['data' => $rows, 'count' => count($rows)]);
        break;

    // ── تقرير السحب ─────────────────────────────────
    case 'sahb':
        $stmt = $conn->prepare(
            'SELECT s.op_num, u.full_name AS emp_name, c.name AS client_name,
                    s.op_date, s.currency, s.total_amount, s.status
             FROM sahb s
             JOIN users   u ON u.id = s.emp_id
             JOIN clients c ON c.id = s.client_id
             WHERE DATE(s.op_date) BETWEEN ? AND ?
               AND s.currency = ?
             ORDER BY s.op_date DESC'
        );
        $stmt->bind_param('sss', $date_from, $date_to, $currency);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        json_success(['data' => $rows, 'count' => count($rows)]);
        break;

    // ── ملخص عام ─────────────────────────────────────
    case 'summary':
        $s1 = $conn->prepare(
            'SELECT COUNT(*) AS cnt, COALESCE(SUM(total_amount),0) AS total
             FROM istilam
             WHERE DATE(op_date) BETWEEN ? AND ? AND currency = ?'
        );
        $s1->bind_param('sss', $date_from, $date_to, $currency);
        $s1->execute();
        $i_row = $s1->get_result()->fetch_assoc();

        $s2 = $conn->prepare(
            'SELECT COUNT(*) AS cnt, COALESCE(SUM(total_amount),0) AS total
             FROM sahb
             WHERE DATE(op_date) BETWEEN ? AND ? AND currency = ?'
        );
        $s2->bind_param('sss', $date_from, $date_to, $currency);
        $s2->execute();
        $s_row = $s2->get_result()->fetch_assoc();

        json_success([
            'istilam' => ['count' => $i_row['cnt'], 'total' => $i_row['total']],
            'sahb'    => ['count' => $s_row['cnt'], 'total' => $s_row['total']],
            'net'     => (float)$i_row['total'] - (float)$s_row['total'],
        ]);
        break;

    // ── جرد النقد الكلي ──────────────────────────────
    case 'jard':
        $stmt = $conn->prepare(
            'SELECT
               SUM(d50000) d50000, SUM(d25000) d25000,
               SUM(d10000) d10000, SUM(d5000)  d5000,
               SUM(d1000)  d1000,  SUM(d500)   d500,
               SUM(d250)   d250,
               SUM(total_amount) total
             FROM istilam WHERE currency = ?'
        );
        $stmt->bind_param('s', $currency);
        $stmt->execute();
        json_success($stmt->get_result()->fetch_assoc());
        break;

    // ── تقرير التدقيق الداخلي ────────────────────────
    case 'tadqeeq':
        $stmt = $conn->prepare(
            "SELECT 'استلام' AS op_type, i.op_num, u.full_name AS auditor,
                    t.created_at, t.match_status, t.total_amount, t.notes
             FROM tadqeeq_istilam t
             JOIN istilam i ON i.id = t.istilam_id
             JOIN users   u ON u.id = t.auditor_id
             WHERE DATE(t.created_at) BETWEEN ? AND ?
             UNION ALL
             SELECT 'سحب' AS op_type, s.op_num, u.full_name AS auditor,
                    t.created_at, t.match_status, t.total_amount, t.notes
             FROM tadqeeq_sahb t
             JOIN sahb  s ON s.id = t.sahb_id
             JOIN users u ON u.id = t.auditor_id
             WHERE DATE(t.created_at) BETWEEN ? AND ?
             ORDER BY created_at DESC"
        );
        $stmt->bind_param('ssss', $date_from, $date_to, $date_from, $date_to);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        json_success(['data' => $rows, 'count' => count($rows)]);
        break;

    // ── تقرير تعيين الأكياس ──────────────────────────
    case 'taayeen':
        $stmt = $conn->prepare(
            "SELECT t.op_type, t.bag_num,
                    CASE t.op_type
                        WHEN 'istilam' THEN i.op_num
                        ELSE s.op_num
                    END AS op_num,
                    u.full_name AS counter_emp,
                    t.device_counter, t.device_camera, t.created_at
             FROM taayeen t
             LEFT JOIN istilam i ON t.op_type = 'istilam' AND i.id = t.op_id
             LEFT JOIN sahb    s ON t.op_type = 'sahb'    AND s.id = t.op_id
             JOIN users u ON u.id = t.counter_emp_id
             WHERE DATE(t.created_at) BETWEEN ? AND ?
             ORDER BY t.created_at DESC"
        );
        $stmt->bind_param('ss', $date_from, $date_to);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        json_success(['data' => $rows, 'count' => count($rows)]);
        break;

    // ── ملخص العد لفترة زمنية ────────────────────────
    case 'count_summary':
        $stmt = $conn->prepare(
            "SELECT DATE(ta.count_date) AS count_day,
                    COUNT(*) AS ops_count,
                    SUM(ta.bag_total) AS total_amount
             FROM tasjeel_add ta
             WHERE DATE(ta.count_date) BETWEEN ? AND ?
               AND ta.currency = ?
             GROUP BY DATE(ta.count_date)
             ORDER BY count_day DESC"
        );
        $stmt->bind_param('sss', $date_from, $date_to, $currency);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        json_success(['data' => $rows]);
        break;

    // ── تفصيل العروقات ───────────────────────────────
    case 'uruqat_detail':
        $stmt = $conn->prepare(
            "SELECT 'استلام' AS op_type, i.op_num, i.op_date,
                    c.name AS client_name,
                    i.d50000, i.d25000, i.d10000, i.d5000, i.d1000, i.d500, i.d250,
                    i.total_amount
             FROM istilam i JOIN clients c ON c.id = i.client_id
             WHERE DATE(i.op_date) BETWEEN ? AND ? AND i.currency = ?
             UNION ALL
             SELECT 'سحب' AS op_type, s.op_num, s.op_date,
                    c.name AS client_name,
                    s.d50000, s.d25000, s.d10000, s.d5000, s.d1000, s.d500, s.d250,
                    s.total_amount
             FROM sahb s JOIN clients c ON c.id = s.client_id
             WHERE DATE(s.op_date) BETWEEN ? AND ? AND s.currency = ?
             ORDER BY op_date DESC"
        );
        $stmt->bind_param('ssssss', $date_from, $date_to, $currency, $date_from, $date_to, $currency);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        json_success(['data' => $rows, 'count' => count($rows)]);
        break;

    default:
        json_error('action غير معروف', 404);
}

$conn->close();
?>
