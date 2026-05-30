-- =============================================
-- HR-App Multi-Tenant Database Schema
-- Engine: MariaDB 10.4+ / MySQL 5.7+
-- Charset: utf8mb4
-- =============================================

-- إنشاء قاعدة البيانات (اختياري)
-- CREATE DATABASE IF NOT EXISTS hr_app_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- USE hr_app_db;

-- =============================================
-- 1. المؤسسات (Organizations) — المستأجرون
-- =============================================
CREATE TABLE IF NOT EXISTS organizations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name_ar VARCHAR(150) NOT NULL,
    name_en VARCHAR(150) NOT NULL,
    slug VARCHAR(50) NOT NULL,
    status ENUM('active','suspended') DEFAULT 'active',
    is_public TINYINT(1) DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    email_enabled TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY (name_ar),
    UNIQUE KEY (name_en),
    UNIQUE KEY (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- 2. المستخدمين (Users)
-- =============================================
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) NOT NULL,
    role ENUM('super_admin','admin','manager','employee') NOT NULL DEFAULT 'employee',
    two_factor_secret VARCHAR(255) DEFAULT NULL,
    two_factor_enabled TINYINT(1) DEFAULT 0,
    organization_id INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY (username),
    UNIQUE KEY (email),
    KEY (organization_id),
    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- 3. الأقسام (Departments)
-- =============================================
CREATE TABLE IF NOT EXISTS departments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    organization_id INT NOT NULL,
    name_ar VARCHAR(100) DEFAULT NULL,
    name_en VARCHAR(100) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY (organization_id),
    UNIQUE KEY unique_org_dept_ar (organization_id, name_ar),
    UNIQUE KEY unique_org_dept_en (organization_id, name_en),
    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- 4. أنواع الإجازات (Leave Types)
-- =============================================
CREATE TABLE IF NOT EXISTS leave_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    organization_id INT NOT NULL,
    name_ar VARCHAR(100) NOT NULL,
    name_en VARCHAR(100) NOT NULL,
    deduct_from_balance TINYINT(1) DEFAULT 1,
    max_days_per_year INT DEFAULT 30,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY (organization_id),
    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- 5. الموظفين (Employees)
-- =============================================
CREATE TABLE IF NOT EXISTS employees (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    organization_id INT NOT NULL,
    employee_id_number VARCHAR(20) NOT NULL,
    system_id VARCHAR(20) DEFAULT NULL,
    full_name VARCHAR(150) NOT NULL,
    phone VARCHAR(20) DEFAULT NULL,
    department_id INT DEFAULT NULL,
    job_title VARCHAR(100) DEFAULT NULL,
    initial_leave_balance INT DEFAULT 0,
    leave_balance_verified TINYINT(1) DEFAULT 0,
    can_request_leave TINYINT(1) DEFAULT 1,
    registration_code VARCHAR(20) DEFAULT NULL,
    hire_date DATE DEFAULT NULL,
    status ENUM('pending','approved','rejected') DEFAULT 'pending',
    rejection_reason TEXT DEFAULT NULL,
    decision_date DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY (employee_id_number),
    UNIQUE KEY (system_id),
    KEY (user_id),
    KEY (department_id),
    KEY (organization_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL,
    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- 6. طلبات الإجازات (Leave Requests)
-- =============================================
CREATE TABLE IF NOT EXISTS leave_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    organization_id INT NOT NULL,
    employee_id INT NOT NULL,
    leave_type_id INT NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    reason TEXT DEFAULT NULL,
    status ENUM('pending','approved','rejected') DEFAULT 'pending',
    request_code VARCHAR(20) DEFAULT NULL,
    manager_note TEXT DEFAULT NULL,
    action_at DATETIME DEFAULT NULL,
    attachment_url VARCHAR(500) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY (organization_id),
    KEY (employee_id),
    KEY (leave_type_id),
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (leave_type_id) REFERENCES leave_types(id) ON DELETE CASCADE,
    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- 7. أرصدة الموظفين (Employee Leave Balances)
-- =============================================
CREATE TABLE IF NOT EXISTS employee_leave_balances (
    employee_id INT NOT NULL,
    leave_type_id INT NOT NULL,
    balance INT DEFAULT 0,
    PRIMARY KEY (employee_id, leave_type_id),
    KEY (leave_type_id),
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (leave_type_id) REFERENCES leave_types(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- 8. الإجازات الرسمية (Holidays)
-- =============================================
CREATE TABLE IF NOT EXISTS holidays (
    id INT AUTO_INCREMENT PRIMARY KEY,
    organization_id INT NOT NULL,
    name_ar VARCHAR(150) NOT NULL,
    name_en VARCHAR(150) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    KEY (organization_id),
    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- 9. الإشعارات (Notifications)
-- =============================================
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    organization_id INT NOT NULL,
    user_id INT NOT NULL,
    message_ar TEXT NOT NULL,
    message_en TEXT NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY (organization_id),
    KEY (user_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- 10. سجل النشاطات (Activity Log)
-- =============================================
CREATE TABLE IF NOT EXISTS activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    organization_id INT NOT NULL,
    user_id INT DEFAULT NULL,
    action_ar VARCHAR(255) NOT NULL,
    action_en VARCHAR(255) NOT NULL,
    details TEXT DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    user_agent TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY (organization_id),
    KEY (user_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- 11. الإعدادات الخاصة بالمؤسسات (Settings)
-- =============================================
CREATE TABLE IF NOT EXISTS settings (
    organization_id INT NOT NULL,
    setting_key VARCHAR(50) NOT NULL,
    setting_value TEXT DEFAULT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (organization_id, setting_key),
    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- 12. طلبات إنشاء مؤسسات جديدة
-- =============================================
CREATE TABLE IF NOT EXISTS organization_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name_ar VARCHAR(150) NOT NULL,
    name_en VARCHAR(150) NOT NULL,
    slug VARCHAR(50) NOT NULL UNIQUE,
    manager_name VARCHAR(150) NOT NULL,
    manager_email VARCHAR(100) NOT NULL,
    manager_phone VARCHAR(20) NOT NULL,
    notes TEXT,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- 13. سجل البريد الإلكتروني (Email Logs)
-- =============================================
CREATE TABLE IF NOT EXISTS email_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    organization_id INT NOT NULL,
    recipient VARCHAR(255) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    status ENUM('sent','failed','bounced') DEFAULT 'sent',
    response LONGTEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY (organization_id),
    KEY (recipient),
    KEY (created_at),
    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- 14. التحقق من البريد الإلكتروني
-- =============================================
CREATE TABLE IF NOT EXISTS email_verifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(255) NOT NULL UNIQUE,
    verified_at TIMESTAMP NULL,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY (user_id),
    KEY (token),
    KEY (expires_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- 15. إعادة تعيين كلمة المرور
-- =============================================
CREATE TABLE IF NOT EXISTS password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(255) NOT NULL UNIQUE,
    used_at TIMESTAMP NULL,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY (user_id),
    KEY (token),
    KEY (expires_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- 16. محاولات تسجيل الدخول (Login Attempts)
-- =============================================
CREATE TABLE IF NOT EXISTS login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(255) NOT NULL UNIQUE,
    failed_attempts INT DEFAULT 0,
    last_attempt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY (username),
    KEY (last_attempt)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- 17. أكواد دعوة المؤسسات
-- =============================================
CREATE TABLE IF NOT EXISTS organization_invitation_codes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    organization_id INT NOT NULL,
    code VARCHAR(20) NOT NULL UNIQUE,
    is_active TINYINT(1) DEFAULT 1,
    previous_code VARCHAR(20) DEFAULT NULL,
    code_regenerated_at TIMESTAMP NULL,
    regenerated_by_user_id INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY (organization_id),
    KEY (code),
    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
    FOREIGN KEY (regenerated_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- 18. سجل محاولات التحقق من الأكواد
-- =============================================
CREATE TABLE IF NOT EXISTS organization_code_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    organization_id INT DEFAULT NULL,
    code_entered VARCHAR(20) DEFAULT NULL,
    ip_address VARCHAR(45) NOT NULL,
    success TINYINT(1) DEFAULT 0,
    attempt_type ENUM('registration','verification') DEFAULT 'registration',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY (ip_address, created_at),
    KEY (organization_id),
    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- 19. إعدادات النظام العام (System Settings)
-- =============================================
CREATE TABLE IF NOT EXISTS system_settings (
    setting_key VARCHAR(50) PRIMARY KEY,
    setting_value TEXT DEFAULT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    updated_by INT DEFAULT NULL,
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- 20. تتبع التحديثات (Schema Migrations)
-- =============================================
CREATE TABLE IF NOT EXISTS schema_migrations (
    version VARCHAR(50) NOT NULL PRIMARY KEY,
    name VARCHAR(255) NOT NULL DEFAULT '',
    description TEXT,
    checksum VARCHAR(64) NOT NULL DEFAULT '',
    status ENUM('applied', 'failed', 'rolled_back') NOT NULL DEFAULT 'applied',
    executed_by INT DEFAULT NULL,
    executed_at TIMESTAMP NULL DEFAULT NULL,
    completed_at TIMESTAMP NULL DEFAULT NULL,
    duration_ms INT DEFAULT 0,
    output TEXT,
    rollback_sql TEXT,
    FOREIGN KEY (executed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- البيانات الأساسية
-- =============================================

-- المؤسسة الافتراضية
INSERT IGNORE INTO organizations (name_ar, name_en, slug, status, is_public, is_active) VALUES
('الجهة الافتراضية', 'Default Organization', 'default', 'active', 1, 1);

-- إعدادات النظام العامة
INSERT IGNORE INTO system_settings (setting_key, setting_value) VALUES
('maintenance_mode', '0'),
('allow_new_org_registration', '1'),
('auto_approve_orgs', '0'),
('max_organizations', '100'),
('min_password_length', '6'),
('session_lifetime_minutes', '120'),
('default_language', 'ar'),
('company_name_ar', 'نظام إدارة الموارد البشرية'),
('company_name_en', 'HR Management System'),
('contact_email', ''),
('max_login_attempts', '5');

-- إعدادات المؤسسة الافتراضية
INSERT IGNORE INTO settings (organization_id, setting_key, setting_value) VALUES
(1, 'site_name_ar', 'نظام إدارة الموظفين'),
(1, 'site_name_en', 'HR Management System'),
(1, 'primary_color', '#0d6efd'),
(1, 'font_family_ar', 'Cairo'),
(1, 'font_family_en', 'Inter'),
(1, 'allow_registration', '1'),
(1, 'allow_leave_requests', '1'),
(1, 'footer_text_ar', 'جميع الحقوق محفوظة'),
(1, 'footer_text_en', 'All Rights Reserved'),
(1, 'email_notifications_enabled', '1'),
(1, 'email_verification_required', '1');

-- المستخدم المطور الأساسي (كلمة المرور: admin123)
INSERT IGNORE INTO users (username, password, email, role, organization_id) VALUES
('superadmin', '$2y$12$zlKWSuwH4Nu63gHpea6H.uGGWZkqOf2VjmjekG.pxLT2q.JuSU6YG', 'admin@ejazat.com', 'super_admin', NULL);

-- الأقسام الافتراضية للمؤسسة الأولى
INSERT IGNORE INTO departments (organization_id, name_ar, name_en) VALUES
(1, 'الموارد البشرية', 'Human Resources'),
(1, 'المحاسبة', 'Accounting'),
(1, 'تقنية المعلومات', 'Information Technology'),
(1, 'المبيعات', 'Sales');

-- أنواع الإجازات الافتراضية للمؤسسة الأولى
INSERT IGNORE INTO leave_types (organization_id, name_ar, name_en) VALUES
(1, 'إجازة سنوية', 'Annual Leave'),
(1, 'إجازة مرضية', 'Sick Leave'),
(1, 'إجازة اضطرارية', 'Emergency Leave'),
(1, 'إجازة بدون راتب', 'Unpaid Leave');
