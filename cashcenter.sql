-- =====================================================
-- Cash Center — قاعدة البيانات الكاملة
-- cashcenter.sql
-- =====================================================

CREATE DATABASE IF NOT EXISTS cashcenter CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE cashcenter;

-- =====================================================
-- 1. جدول الموظفين (users)
-- =====================================================
CREATE TABLE IF NOT EXISTS users (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username    VARCHAR(60)  NOT NULL UNIQUE,
    full_name   VARCHAR(120) NOT NULL,
    password    VARCHAR(255) NOT NULL,          -- bcrypt hash
    role        ENUM('admin','supervisor','employee','auditor') NOT NULL DEFAULT 'employee',
    is_active   TINYINT(1) NOT NULL DEFAULT 1,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- موظفين افتراضيين (password: 123456 — غيّر بعد الرفع)
INSERT INTO users (username, full_name, password, role) VALUES
('admin',   'المدير العام',   '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin'),
('ahmed',   'أحمد محمد',      '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'employee'),
('ali',     'علي حسن',        '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'employee'),
('sara',    'سارة خالد',      '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'auditor'),
('mudeer1', 'محمد العلي',     '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'supervisor');

-- =====================================================
-- 2. جدول العملاء (clients)
-- =====================================================
CREATE TABLE IF NOT EXISTS clients (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(150) NOT NULL,
    is_active  TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO clients (name) VALUES
('البنك الأهلي'),
('المصرف العربي'),
('بنك بغداد'),
('مصرف الرشيد'),
('مصرف الرافدين');

-- =====================================================
-- 3. جدول الأجهزة (devices)
-- =====================================================
CREATE TABLE IF NOT EXISTS devices (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    device_code VARCHAR(20) NOT NULL UNIQUE,
    device_type ENUM('counter','camera','recounter','recamera') NOT NULL,
    is_active   TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO devices (device_code, device_type) VALUES
('M-001','counter'),('M-002','counter'),('M-003','counter'),
('C-001','camera'), ('C-002','camera'),
('R-001','recounter'),('R-002','recounter'),
('RC-001','recamera'),('RC-002','recamera');

-- =====================================================
-- 4. جدول الاستلام (istilam)
-- =====================================================
CREATE TABLE IF NOT EXISTS istilam (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    op_num        VARCHAR(20)  NOT NULL UNIQUE,        -- رقم تسلسلي: IST-000001
    emp_id        INT UNSIGNED NOT NULL,
    client_id     INT UNSIGNED NOT NULL,
    op_date       DATETIME     NOT NULL,
    currency      ENUM('دينار','دولار','يورو') NOT NULL DEFAULT 'دينار',
    total_amount  DECIMAL(18,3) NOT NULL DEFAULT 0,
    bags_count    SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    liquidity     DECIMAL(18,3) NOT NULL DEFAULT 0,
    d50000        INT UNSIGNED NOT NULL DEFAULT 0,
    d25000        INT UNSIGNED NOT NULL DEFAULT 0,
    d10000        INT UNSIGNED NOT NULL DEFAULT 0,
    d5000         INT UNSIGNED NOT NULL DEFAULT 0,
    d1000         INT UNSIGNED NOT NULL DEFAULT 0,
    d500          INT UNSIGNED NOT NULL DEFAULT 0,
    d250          INT UNSIGNED NOT NULL DEFAULT 0,
    status        ENUM('pending','audited','closed','dispute') NOT NULL DEFAULT 'pending',
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (emp_id)    REFERENCES users(id),
    FOREIGN KEY (client_id) REFERENCES clients(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- 5. جدول السحب (sahb)
-- =====================================================
CREATE TABLE IF NOT EXISTS sahb (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    op_num        VARCHAR(20)  NOT NULL UNIQUE,        -- رقم تسلسلي: SAH-000001
    emp_id        INT UNSIGNED NOT NULL,
    client_id     INT UNSIGNED NOT NULL,
    op_date       DATETIME     NOT NULL,
    currency      ENUM('دينار','دولار','يورو') NOT NULL DEFAULT 'دينار',
    total_amount  DECIMAL(18,3) NOT NULL DEFAULT 0,
    d50000        INT UNSIGNED NOT NULL DEFAULT 0,
    d25000        INT UNSIGNED NOT NULL DEFAULT 0,
    d10000        INT UNSIGNED NOT NULL DEFAULT 0,
    d5000         INT UNSIGNED NOT NULL DEFAULT 0,
    d1000         INT UNSIGNED NOT NULL DEFAULT 0,
    d500          INT UNSIGNED NOT NULL DEFAULT 0,
    d250          INT UNSIGNED NOT NULL DEFAULT 0,
    status        ENUM('pending','audited','closed','dispute') NOT NULL DEFAULT 'pending',
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (emp_id)    REFERENCES users(id),
    FOREIGN KEY (client_id) REFERENCES clients(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- 6. جدول تعيين الأكياس (taayeen)
-- =====================================================
CREATE TABLE IF NOT EXISTS taayeen (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    op_type         ENUM('istilam','sahb') NOT NULL,
    op_id           INT UNSIGNED NOT NULL,
    bag_num         SMALLINT UNSIGNED NOT NULL,
    counter_emp_id  INT UNSIGNED NOT NULL,
    device_counter  VARCHAR(20),
    device_camera   VARCHAR(20),
    device_rcounter VARCHAR(20),
    device_rcamera  VARCHAR(20),
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (counter_emp_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- 7. جدول تدقيق الاستلام (tadqeeq_istilam)
-- =====================================================
CREATE TABLE IF NOT EXISTS tadqeeq_istilam (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    istilam_id    INT UNSIGNED NOT NULL,
    bag_num       SMALLINT UNSIGNED NOT NULL,
    auditor_id    INT UNSIGNED NOT NULL,
    d50000        INT UNSIGNED NOT NULL DEFAULT 0,
    d25000        INT UNSIGNED NOT NULL DEFAULT 0,
    d10000        INT UNSIGNED NOT NULL DEFAULT 0,
    d5000         INT UNSIGNED NOT NULL DEFAULT 0,
    d1000         INT UNSIGNED NOT NULL DEFAULT 0,
    d500          INT UNSIGNED NOT NULL DEFAULT 0,
    d250          INT UNSIGNED NOT NULL DEFAULT 0,
    total_amount  DECIMAL(18,3) NOT NULL DEFAULT 0,
    match_status  ENUM('match','mismatch') NOT NULL DEFAULT 'match',
    notes         TEXT,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (istilam_id) REFERENCES istilam(id),
    FOREIGN KEY (auditor_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- 8. جدول تدقيق السحب (tadqeeq_sahb)
-- =====================================================
CREATE TABLE IF NOT EXISTS tadqeeq_sahb (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sahb_id       INT UNSIGNED NOT NULL,
    auditor_id    INT UNSIGNED NOT NULL,
    d50000        INT UNSIGNED NOT NULL DEFAULT 0,
    d25000        INT UNSIGNED NOT NULL DEFAULT 0,
    d10000        INT UNSIGNED NOT NULL DEFAULT 0,
    d5000         INT UNSIGNED NOT NULL DEFAULT 0,
    d1000         INT UNSIGNED NOT NULL DEFAULT 0,
    d500          INT UNSIGNED NOT NULL DEFAULT 0,
    d250          INT UNSIGNED NOT NULL DEFAULT 0,
    total_amount  DECIMAL(18,3) NOT NULL DEFAULT 0,
    match_status  ENUM('match','mismatch') NOT NULL DEFAULT 'match',
    notes         TEXT,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sahb_id)    REFERENCES sahb(id),
    FOREIGN KEY (auditor_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- 9. جدول تسجيل عد الأكياس (tasjeel_add)
-- =====================================================
CREATE TABLE IF NOT EXISTS tasjeel_add (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    op_type       ENUM('istilam','sahb') NOT NULL,
    op_id         INT UNSIGNED NOT NULL,
    bag_num       SMALLINT UNSIGNED NOT NULL,
    emp_id        INT UNSIGNED NOT NULL,
    count_date    DATETIME NOT NULL,
    currency      ENUM('دينار','دولار','يورو') NOT NULL DEFAULT 'دينار',
    d50000        INT UNSIGNED NOT NULL DEFAULT 0,
    d25000        INT UNSIGNED NOT NULL DEFAULT 0,
    d10000        INT UNSIGNED NOT NULL DEFAULT 0,
    d5000         INT UNSIGNED NOT NULL DEFAULT 0,
    d1000         INT UNSIGNED NOT NULL DEFAULT 0,
    d500          INT UNSIGNED NOT NULL DEFAULT 0,
    d250          INT UNSIGNED NOT NULL DEFAULT 0,
    bag_total     DECIMAL(18,3) NOT NULL DEFAULT 0,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (emp_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- 10. جدول النقد المعدود (naqdmaadood)
-- =====================================================
CREATE TABLE IF NOT EXISTS naqdmaadood (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    op_type         ENUM('istilam','sahb') NOT NULL,
    op_id           INT UNSIGNED NOT NULL,
    bag_num         SMALLINT UNSIGNED NOT NULL,
    emp_id          INT UNSIGNED NOT NULL,
    count_date      DATETIME NOT NULL,
    currency        ENUM('دينار','دولار','يورو') NOT NULL DEFAULT 'دينار',
    -- نقص / زيادة لكل فئة
    d50000_naqis    INT NOT NULL DEFAULT 0,
    d50000_ziyada   INT NOT NULL DEFAULT 0,
    d25000_naqis    INT NOT NULL DEFAULT 0,
    d25000_ziyada   INT NOT NULL DEFAULT 0,
    d10000_naqis    INT NOT NULL DEFAULT 0,
    d10000_ziyada   INT NOT NULL DEFAULT 0,
    d5000_naqis     INT NOT NULL DEFAULT 0,
    d5000_ziyada    INT NOT NULL DEFAULT 0,
    d1000_naqis     INT NOT NULL DEFAULT 0,
    d1000_ziyada    INT NOT NULL DEFAULT 0,
    d500_naqis      INT NOT NULL DEFAULT 0,
    d500_ziyada     INT NOT NULL DEFAULT 0,
    d250_naqis      INT NOT NULL DEFAULT 0,
    d250_ziyada     INT NOT NULL DEFAULT 0,
    -- أنواع ملاحظات
    mazayaf         INT NOT NULL DEFAULT 0,
    ikhtilaf_arqam  INT NOT NULL DEFAULT 0,
    maktabi         INT NOT NULL DEFAULT 0,
    mahrooq         INT NOT NULL DEFAULT 0,
    talif_qeema     INT NOT NULL DEFAULT 0,
    soo_istikhdam   INT NOT NULL DEFAULT 0,
    actual_amount   DECIMAL(18,3) NOT NULL DEFAULT 0,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (emp_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- 11. جدول الاختلاف (ikhtilaf)
-- =====================================================
CREATE TABLE IF NOT EXISTS ikhtilaf (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    op_type         ENUM('istilam','sahb') NOT NULL,
    op_id           INT UNSIGNED NOT NULL,
    original_total  DECIMAL(18,3) NOT NULL,
    auditor_total   DECIMAL(18,3) NOT NULL,
    difference      DECIMAL(18,3) NOT NULL,
    manager_id      INT UNSIGNED NOT NULL,
    manager_d50000  INT UNSIGNED NOT NULL DEFAULT 0,
    manager_d25000  INT UNSIGNED NOT NULL DEFAULT 0,
    manager_d10000  INT UNSIGNED NOT NULL DEFAULT 0,
    manager_d5000   INT UNSIGNED NOT NULL DEFAULT 0,
    manager_d1000   INT UNSIGNED NOT NULL DEFAULT 0,
    manager_d500    INT UNSIGNED NOT NULL DEFAULT 0,
    manager_d250    INT UNSIGNED NOT NULL DEFAULT 0,
    manager_total   DECIMAL(18,3) NOT NULL DEFAULT 0,
    decision        ENUM('accept_original','accept_auditor','new_value') NOT NULL,
    final_total     DECIMAL(18,3) NOT NULL,
    notes           TEXT,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (manager_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- 12. جدول الترزيم (tarzeem)
-- =====================================================
CREATE TABLE IF NOT EXISTS tarzeem (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    op_type     ENUM('istilam','sahb') NOT NULL,
    op_id       INT UNSIGNED NOT NULL,
    emp_id      INT UNSIGNED NOT NULL,
    bags_count  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    op_date     DATETIME NOT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (emp_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- 13. جدول الجلسات (sessions) — للـ auth
-- =====================================================
CREATE TABLE IF NOT EXISTS sessions (
    id          VARCHAR(64) PRIMARY KEY,
    user_id     INT UNSIGNED NOT NULL,
    ip_address  VARCHAR(45),
    user_agent  VARCHAR(255),
    last_active DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    expires_at  DATETIME NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- 14. جدول السجل / Log (audit_log)
-- =====================================================
CREATE TABLE IF NOT EXISTS audit_log (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED,
    action      VARCHAR(100) NOT NULL,
    table_name  VARCHAR(60),
    record_id   INT UNSIGNED,
    ip_address  VARCHAR(45),
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- Indexes للأداء
-- =====================================================
CREATE INDEX idx_istilam_date     ON istilam(op_date);
CREATE INDEX idx_istilam_client   ON istilam(client_id);
CREATE INDEX idx_istilam_status   ON istilam(status);
CREATE INDEX idx_sahb_date        ON sahb(op_date);
CREATE INDEX idx_sahb_client      ON sahb(client_id);
CREATE INDEX idx_tadqeeq_istilam  ON tadqeeq_istilam(istilam_id);
CREATE INDEX idx_tadqeeq_sahb     ON tadqeeq_sahb(sahb_id);
CREATE INDEX idx_sessions_user    ON sessions(user_id);
CREATE INDEX idx_sessions_expires ON sessions(expires_at);
CREATE INDEX idx_log_user         ON audit_log(user_id);
CREATE INDEX idx_log_date         ON audit_log(created_at);
