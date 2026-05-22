<?php
// =====================================================
// api_auth.php — تسجيل الدخول والخروج
// =====================================================
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: ' . (defined('APP_URL') ? APP_URL : '*'));
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once 'config.php';
init_session();

$action = $_REQUEST['action'] ?? '';

switch ($action) {

    // ── تسجيل الدخول ────────────────────────────────
    case 'login':
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (!$username || !$password) {
            json_error('يرجى إدخال اسم المستخدم وكلمة المرور');
        }

        $stmt = $conn->prepare(
            'SELECT id, username, full_name, password, role, is_active
             FROM users WHERE username = ? LIMIT 1'
        );
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        if (!$user || !password_verify($password, $user['password'])) {
            log_action('login_failed_' . $username);
            json_error('اسم المستخدم أو كلمة المرور غير صحيحة', 401);
        }

        if (!$user['is_active']) {
            json_error('هذا الحساب موقوف، تواصل مع المدير', 403);
        }

        // إنشاء الجلسة
        session_regenerate_id(true);
        $_SESSION['user_id']   = $user['id'];
        $_SESSION['username']  = $user['username'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['role']      = $user['role'];
        $_SESSION['expires_at']= time() + SESSION_TIMEOUT;

        log_action('login_success', 'users', $user['id']);

        json_success([
            'id'        => $user['id'],
            'username'  => $user['username'],
            'full_name' => $user['full_name'],
            'role'      => $user['role'],
        ], 'مرحباً ' . $user['full_name']);
        break;

    // ── تسجيل الخروج ────────────────────────────────
    case 'logout':
        log_action('logout');
        session_destroy();
        json_success(null, 'تم تسجيل الخروج');
        break;

    // ── التحقق من الجلسة الحالية ─────────────────────
    case 'check':
        if (!empty($_SESSION['user_id'])) {
            json_success([
                'logged_in' => true,
                'user' => [
                    'id'        => $_SESSION['user_id'],
                    'username'  => $_SESSION['username'],
                    'full_name' => $_SESSION['full_name'],
                    'role'      => $_SESSION['role'],
                ]
            ]);
        } else {
            json_success(['logged_in' => false]);
        }
        break;

    // ── جلب قائمة الموظفين (للـ dropdowns) ──────────
    case 'get_employees':
        require_auth();
        $stmt = $conn->prepare(
            'SELECT id, full_name, role FROM users WHERE is_active = 1 ORDER BY full_name'
        );
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        json_success($rows);
        break;

    // ── تغيير كلمة المرور ────────────────────────────
    case 'change_password':
        $user = require_auth();
        $old = $_POST['old_password'] ?? '';
        $new = $_POST['new_password'] ?? '';

        if (strlen($new) < 8) {
            json_error('كلمة المرور الجديدة يجب أن تكون 8 أحرف على الأقل');
        }

        $stmt = $conn->prepare('SELECT password FROM users WHERE id = ?');
        $stmt->bind_param('i', $user['id']);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();

        if (!password_verify($old, $row['password'])) {
            json_error('كلمة المرور القديمة غير صحيحة');
        }

        $hash = password_hash($new, PASSWORD_BCRYPT, ['cost' => 12]);
        $stmt2 = $conn->prepare('UPDATE users SET password = ? WHERE id = ?');
        $stmt2->bind_param('si', $hash, $user['id']);
        $stmt2->execute();

        log_action('change_password', 'users', $user['id']);
        json_success(null, 'تم تغيير كلمة المرور بنجاح');
        break;

    // ── إضافة موظف (admin فقط) ───────────────────────
    case 'add_user':
        require_role('admin');
        $uname = trim($_POST['username'] ?? '');
        $fname = trim($_POST['full_name'] ?? '');
        $pass  = $_POST['password'] ?? '';
        $role  = $_POST['role'] ?? 'employee';

        if (!$uname || !$fname || !$pass) {
            json_error('جميع الحقول مطلوبة');
        }
        if (strlen($pass) < 8) {
            json_error('كلمة المرور يجب أن تكون 8 أحرف على الأقل');
        }

        $valid_roles = ['admin','supervisor','employee','auditor'];
        if (!in_array($role, $valid_roles, true)) {
            json_error('دور غير صالح');
        }

        $hash = password_hash($pass, PASSWORD_BCRYPT, ['cost' => 12]);
        $stmt = $conn->prepare(
            'INSERT INTO users (username, full_name, password, role) VALUES (?, ?, ?, ?)'
        );
        $stmt->bind_param('ssss', $uname, $fname, $hash, $role);

        if ($stmt->execute()) {
            log_action('add_user', 'users', (int)$conn->insert_id);
            json_success(['id' => $conn->insert_id], 'تم إضافة الموظف بنجاح');
        } else {
            json_error('اسم المستخدم موجود مسبقاً');
        }
        break;

    default:
        json_error('action غير معروف', 404);
}

$conn->close();
?>
