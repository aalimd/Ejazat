-- إنشاء قاعدة البيانات (اختياري إذا كانت موجودة مسبقاً)
-- CREATE DATABASE IF NOT EXISTS hr_app_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- USE hr_app_db;

-- جدول الأقسام
CREATE TABLE IF NOT EXISTS departments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name_ar VARCHAR(100) NOT NULL UNIQUE,
    name_en VARCHAR(100) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- جدول أنواع الإجازات
CREATE TABLE IF NOT EXISTS leave_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name_ar VARCHAR(100) NOT NULL UNIQUE,
    name_en VARCHAR(100) NOT NULL UNIQUE,
    deduct_from_balance TINYINT(1) DEFAULT 1,
    max_days_per_year INT DEFAULT 30,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- جدول المستخدمين (للدخول إلى النظام)
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    role ENUM('admin', 'manager', 'employee') NOT NULL DEFAULT 'employee',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- جدول الموظفين (بيانات الموظف التفصيلية)
CREATE TABLE IF NOT EXISTS employees (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    employee_id_number VARCHAR(20) NOT NULL UNIQUE, -- الرقم الوظيفي
    system_id VARCHAR(20) UNIQUE, -- معرف النظام (HR2026xxxx)
    full_name VARCHAR(150) NOT NULL,
    phone VARCHAR(20),
    department_id INT,
    job_title VARCHAR(100),
    initial_leave_balance INT DEFAULT 0,
    leave_balance_verified TINYINT(1) DEFAULT 0,
    can_request_leave TINYINT(1) DEFAULT 1,
    registration_code VARCHAR(20), -- كود عملية التسجيل
    hire_date DATE,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    rejection_reason TEXT,
    decision_date DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- جدول طلبات الإجازات
CREATE TABLE IF NOT EXISTS leave_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    leave_type_id INT NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    reason TEXT,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    request_code VARCHAR(20), -- كود عملية طلب الإجازة
    manager_note TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (leave_type_id) REFERENCES leave_types(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- جدول الإشعارات
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    message_ar TEXT NOT NULL,
    message_en TEXT NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- جدول سجل النشاطات
CREATE TABLE IF NOT EXISTS activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action_ar VARCHAR(255) NOT NULL,
    action_en VARCHAR(255) NOT NULL,
    details TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- جدول الإعدادات
CREATE TABLE IF NOT EXISTS settings (
    setting_key VARCHAR(50) PRIMARY KEY,
    setting_value TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- إدخال بيانات أولية للأقسام
INSERT IGNORE INTO departments (name_ar, name_en) VALUES 
('الموارد البشرية', 'Human Resources'), 
('المحاسبة', 'Accounting'), 
('تقنية المعلومات', 'Information Technology'), 
('المبيعات', 'Sales');

-- إدخال بيانات أولية لأنواع الإجازات
INSERT IGNORE INTO leave_types (name_ar, name_en) VALUES 
('إجازة سنوية', 'Annual Leave'), 
('إجازة مرضية', 'Sick Leave'), 
('إجازة اضطرارية', 'Emergency Leave'), 
('إجازة بدون راتب', 'Unpaid Leave');

-- إدخال الإعدادات الافتراضية
INSERT IGNORE INTO settings (setting_key, setting_value) VALUES 
('site_name_ar', '🏢 نظام إدارة الموظفين'),
('site_name_en', '🏢 HR Management System'),
('primary_color', '#0d6efd'),
('font_family_ar', 'Cairo'),
('font_family_en', 'Inter'),
('allow_registration', '1'),
('allow_leave_requests', '1'),
('footer_text_ar', 'جميع الحقوق محفوظة'),
('footer_text_en', 'All Rights Reserved');

-- إدخال مدير النظام الافتراضي (كلمة المرور: admin123)
INSERT IGNORE INTO users (username, password, email, role) VALUES 
('admin', '$2y$12$gnxafHiSL4LrqnR4Y9L/BOnc1YiYbeaHUfaMeU.p7MiDgqT96inVS', 'admin@example.com', 'admin');
