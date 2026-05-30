-- Migration 006: Create system_settings table for super-admin system-wide settings
-- This is independent of organization-scoped settings (settings table)

CREATE TABLE IF NOT EXISTS system_settings (
    setting_key VARCHAR(50) PRIMARY KEY,
    setting_value TEXT DEFAULT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    updated_by INT DEFAULT NULL,
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed defaults
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
