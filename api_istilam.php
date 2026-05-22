<?php
// =====================================================
// api_istilam.php — عمليات الاستلام
// =====================================================
header('Content-Type: application/json; charset=utf-8');
require_once 'config.php';

$user   = require_auth();
$action = $_REQUEST['action'] ?? '';

switch ($action) {

    // ── جلب الرقم القادم للعملية ────────────────────
    case 'get_next_op_num':
        $res = $conn->query("SELECT MAX(id) AS max_id FROM istilam");
        $next_id = 1;
        if ($res && $row = $res->fetch_assoc()) {
            $next_id = (int)$row['max_id'] + 1;
        }
        json_success(['next_op_num' => $next_id]);
        break;

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
        
        $bags_data_json = $_POST['bags_data'] ?? '[]';
        $bags_array = json_decode($bags_data_json, true) ?: [];

        if (!$emp_id || !$client_id || !$op_date) {
            json_error('الموظف والعميل والتاريخ مطلوبة');
        }

        // Calculate totals from the bags_array
        $d50000 = $d25000 = $d10000 = $d5000 = $d1000 = $d500 = $d250 = $total = 0;
        foreach($bags_array as $b) {
            $d50000 += (int)($b['d50000'] ?? 0);
            $d25000 += (int)($b['d25000'] ?? 0);
            $d10000 += (int)($b['d10000'] ?? 0);
            $d5000  += (int)($b['d5000']  ?? 0);
            $d1000  += (int)($b['d1000']  ?? 0);
            $d500   += (int)($b['d500']   ?? 0);
            $d250   += (int)($b['d250']   ?? 0);
            $total  += (float)($b['total_amount'] ?? 0);
        }

        $valid_currencies = ['دينار', 'دولار', 'يورو'];
        if (!in_array($currency, $valid_currencies, true)) json_error('عملة غير صالحة');

        $conn->begin_transaction();
        try {
            // سندخل op_num مؤقت أولاً، ثم نحدثه ليكون نفس الـ ID المتسلسل
            $stmt = $conn->prepare(
                'INSERT INTO istilam
                 (op_num, emp_id, client_id, op_date, currency, total_amount, bags_count, liquidity,
                  d50000, d25000, d10000, d5000, d1000, d500, d250)
                 VALUES ("TEMP",?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
            );
            $stmt->bind_param(
                'iissddiiiiiiii',
                $emp_id, $client_id, $op_date, $currency,
                $total, $bags, $liquidity,
                $d50000, $d25000, $d10000, $d5000, $d1000, $d500, $d250
            );
            $stmt->execute();
            $new_id = (int)$conn->insert_id;
            
            // تحديث رقم العملية ليكون نفس المعرف (يبدأ من 1 ويزداد تلقائياً)
            $op_num = (string)$new_id;
            $conn->query("UPDATE istilam SET op_num = '$op_num' WHERE id = $new_id");

            // Insert bags
            $bag_stmt = $conn->prepare(
                'INSERT INTO istilam_bags 
                 (istilam_id, bag_num, d50000, d25000, d10000, d5000, d1000, d500, d250, total_amount)
                 VALUES (?,?,?,?,?,?,?,?,?,?)'
            );
            foreach($bags_array as $idx => $b) {
                $bnum = $idx + 1;
                $bd50000 = (int)($b['d50000'] ?? 0);
                $bd25000 = (int)($b['d25000'] ?? 0);
                $bd10000 = (int)($b['d10000'] ?? 0);
                $bd5000  = (int)($b['d5000']  ?? 0);
                $bd1000  = (int)($b['d1000']  ?? 0);
                $bd500   = (int)($b['d500']   ?? 0);
                $bd250   = (int)($b['d250']   ?? 0);
                $btot    = (float)($b['total_amount'] ?? 0);
                $bag_stmt->bind_param('iiiiiiiiid', $new_id, $bnum, $bd50000, $bd25000, $bd10000, $bd5000, $bd1000, $bd500, $bd250, $btot);
                $bag_stmt->execute();
            }

            $conn->commit();
            log_action('istilam_save', 'istilam', $new_id);
            json_success(['id' => $new_id, 'op_num' => $op_num], 'تم حفظ عملية الاستلام بنجاح');
        } catch (Exception $e) {
            $conn->rollback();
            json_error('حدث خطأ أثناء الحفظ: ' . $e->getMessage());
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
