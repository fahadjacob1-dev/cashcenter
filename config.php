<?php
// =====================================================
// config.php — إعدادات المشروع المؤمنة
// =====================================================

// ── منع الوصول المباشر ──────────────────────────────
if (basename($_SERVER['PHP_SELF']) === 'config.php') {
    http_response_code(403);
    exit('Access denied');
}

// ── إعدادات قاعدة البيانات ──────────────────────────
// جلب الإعدادات تلقائياً من بيئة Railway (أو استخدام الافتراضي للوكل)
define('DB_HOST', getenv('DB_HOST') ?: getenv('MYSQLHOST') ?: '127.0.0.1');
define('DB_USER', getenv('DB_USER') ?: getenv('MYSQLUSER') ?: 'cashcenter_user');
define('DB_PASS', getenv('DB_PASS') ?: getenv('MYSQLPASSWORD') ?: 'CHANGE_THIS_STRONG_PASSWORD');
define('DB_NAME', getenv('DB_NAME') ?: getenv('MYSQLDATABASE') ?: 'cashcenter');
define('DB_PORT', getenv('DB_PORT') ?: getenv('MYSQLPORT') ?: 3306);

// ── إعدادات الجلسة ──────────────────────────────────
define('SESSION_NAME',    'cc_session');
define('SESSION_TIMEOUT', 3600);       // ساعة واحدة بالثواني
define('SESSION_SECRET',  'CHANGE_THIS_64CHAR_RANDOM_SECRET'); // ← غيّر هذا

// ── إعدادات البيئة ──────────────────────────────────
define('APP_ENV',  'production');      // 'development' أو 'production'
define('APP_URL',  'https://yourdomain.com'); // ← دومينك أو IP السيرفر

// ── الاتصال بقاعدة البيانات ─────────────────────────
try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
    $conn->set_charset('utf8mb4');

    if ($conn->connect_error) {
        throw new Exception('DB connection failed');
    }
} catch (Exception $e) {
    if (APP_ENV === 'development') {
        die(json_encode(['error' => $e->getMessage()]));
    } else {
        http_response_code(503);
        die(json_encode(['error' => 'Service unavailable'])); // لا تكشف التفاصيل في production
    }
}

// ── دالة مساعدة: جلب الـ PDO (للـ prepared statements) ─
function get_pdo(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host='.DB_HOST.';port='.DB_PORT.';dbname='.DB_NAME.';charset=utf8mb4';
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}

// ── إعداد الجلسة ─────────────────────────────────────
function init_session(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_name(SESSION_NAME);
        session_set_cookie_params([
            'lifetime' => SESSION_TIMEOUT,
            'path'     => '/',
            'secure'   => true,    // HTTPS فقط
            'httponly' => true,    // منع JavaScript من قراءة الكوكي
            'samesite' => 'Strict',
        ]);
        session_start();
    }
}

// ── التحقق من تسجيل الدخول ──────────────────────────
function require_auth(): array {
    init_session();

    if (empty($_SESSION['user_id'])) {
        if (str_contains($_SERVER['REQUEST_URI'] ?? '', 'api_')) {
            http_response_code(401);
            echo json_encode(['error' => 'غير مصرح، يرجى تسجيل الدخول']);
            exit;
        }
        header('Location: login.html');
        exit;
    }

    // تحقق من انتهاء الجلسة
    if (!empty($_SESSION['expires_at']) && time() > $_SESSION['expires_at']) {
        session_destroy();
        header('Location: login.html?timeout=1');
        exit;
    }

    $_SESSION['expires_at'] = time() + SESSION_TIMEOUT;

    return [
        'id'        => $_SESSION['user_id'],
        'username'  => $_SESSION['username'],
        'full_name' => $_SESSION['full_name'],
        'role'      => $_SESSION['role'],
    ];
}

// ── التحقق من صلاحية معينة ──────────────────────────
function require_role(string ...$roles): void {
    $user = require_auth();
    if (!in_array($user['role'], $roles, true)) {
        http_response_code(403);
        echo json_encode(['error' => 'ليس لديك صلاحية لهذه العملية']);
        exit;
    }
}

// ── تسجيل في الـ Log ─────────────────────────────────
function log_action(string $action, string $table = '', int $record_id = 0): void {
    global $conn;
    init_session();
    $user_id = $_SESSION['user_id'] ?? null;
    $ip      = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';

    $stmt = $conn->prepare(
        'INSERT INTO audit_log (user_id, action, table_name, record_id, ip_address)
         VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->bind_param('issis', $user_id, $action, $table, $record_id, $ip);
    $stmt->execute();
}

// ── رد JSON موحد ─────────────────────────────────────
function json_success(mixed $data = null, string $message = ''): void {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => true, 'data' => $data, 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

function json_error(string $message, int $code = 400): void {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

// ── رقم تسلسلي للعمليات ──────────────────────────────
function generate_op_num(string $prefix): string {
    global $conn;
    $table = $prefix === 'IST' ? 'istilam' : 'sahb';
    $result = $conn->query("SELECT COUNT(*) AS cnt FROM `$table`");
    $cnt = (int)$result->fetch_assoc()['cnt'];
    return $prefix . '-' . str_pad($cnt + 1, 6, '0', STR_PAD_LEFT);
}
?>
