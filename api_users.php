<?php
// =====================================================
// api_users.php — إدارة الموظفين (للمدير فقط)
// =====================================================
require_once 'config.php';

// التأكد من أن المستخدم مدير (admin) فقط
require_auth();
require_role('admin');

$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'list':
            // جلب قائمة الموظفين
            $stmt = $conn->prepare("SELECT id, username, full_name, role, is_active, created_at FROM users ORDER BY id DESC");
            $stmt->execute();
            $result = $stmt->get_result();
            $users = $result->fetch_all(MYSQLI_ASSOC);
            json_success($users);
            break;

        case 'add':
            // إضافة موظف جديد
            $username = trim($_POST['username'] ?? '');
            $full_name = trim($_POST['full_name'] ?? '');
            $password = $_POST['password'] ?? '';
            $role = $_POST['role'] ?? 'employee';

            if (empty($username) || empty($full_name) || empty($password)) {
                json_error('جميع الحقول مطلوبة');
            }

            // التأكد من عدم تكرار اليوزر
            $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->bind_param('s', $username);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                json_error('اسم المستخدم موجود مسبقاً');
            }

            // تشفير الباسورد
            $hashed_password = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

            $stmt = $conn->prepare("INSERT INTO users (username, full_name, password, role) VALUES (?, ?, ?, ?)");
            $stmt->bind_param('ssss', $username, $full_name, $hashed_password, $role);
            if ($stmt->execute()) {
                log_action('add_user', 'users', $stmt->insert_id);
                json_success(null, 'تمت إضافة الموظف بنجاح');
            } else {
                json_error('فشل في الإضافة');
            }
            break;

        case 'update':
            // تعديل بيانات الموظف أو الباسورد
            $id = (int)($_POST['id'] ?? 0);
            $full_name = trim($_POST['full_name'] ?? '');
            $role = $_POST['role'] ?? 'employee';
            $password = $_POST['password'] ?? '';
            $is_active = (int)($_POST['is_active'] ?? 1);

            if ($id <= 0 || empty($full_name)) {
                json_error('بيانات غير صحيحة');
            }

            if (!empty($password)) {
                // إذا كتب باسورد جديد، غيره
                $hashed_password = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
                $stmt = $conn->prepare("UPDATE users SET full_name=?, role=?, is_active=?, password=? WHERE id=?");
                $stmt->bind_param('ssisi', $full_name, $role, $is_active, $hashed_password, $id);
            } else {
                // إذا ترك الباسورد فارغ، لا تغيره
                $stmt = $conn->prepare("UPDATE users SET full_name=?, role=?, is_active=? WHERE id=?");
                $stmt->bind_param('ssii', $full_name, $role, $is_active, $id);
            }

            if ($stmt->execute()) {
                log_action('update_user', 'users', $id);
                json_success(null, 'تم التعديل بنجاح');
            } else {
                json_error('فشل في التعديل');
            }
            break;

        case 'delete':
            // حذف الموظف
            $id = (int)($_POST['id'] ?? 0);
            
            // منع المدير من حذف نفسه
            if ($id === $_SESSION['user_id']) {
                json_error('لا يمكنك حذف حسابك الحالي');
            }

            $stmt = $conn->prepare("DELETE FROM users WHERE id=?");
            $stmt->bind_param('i', $id);
            if ($stmt->execute()) {
                log_action('delete_user', 'users', $id);
                json_success(null, 'تم حذف الموظف بنجاح');
            } else {
                json_error('لا يمكن حذف الموظف لوجود عمليات مرتبطة به');
            }
            break;

        default:
            json_error('إجراء غير صالح');
    }
} catch (Exception $e) {
    json_error($e->getMessage());
}
