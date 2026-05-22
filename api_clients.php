<?php
// =====================================================
// api_clients.php — إدارة العملاء
// =====================================================
header('Content-Type: application/json; charset=utf-8');
require_once 'config.php';

$user   = require_auth();
$action = $_REQUEST['action'] ?? '';

switch ($action) {

    case 'get_all':
        $stmt = $conn->prepare('SELECT id, name FROM clients WHERE is_active = 1 ORDER BY name');
        $stmt->execute();
        json_success($stmt->get_result()->fetch_all(MYSQLI_ASSOC));
        break;

    case 'add':
        require_role('admin');
        $name = trim($_POST['name'] ?? '');
        if (!$name) json_error('اسم العميل مطلوب');

        $stmt = $conn->prepare('INSERT INTO clients (name) VALUES (?)');
        $stmt->bind_param('s', $name);
        if ($stmt->execute()) {
            json_success(['id' => $conn->insert_id], 'تم إضافة العميل بنجاح');
        } else {
            json_error('حدث خطأ أثناء الإضافة');
        }
        break;

    default:
        json_error('action غير معروف', 404);
}

$conn->close();
?>
