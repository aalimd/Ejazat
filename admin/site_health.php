<?php
require_once '../includes/config.php';
checkAuth('super_admin');

$error = '';
$success = '';

// --- Handle test email ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_test_email'])) {
    if (!verify_csrf()) {
        $error = __('access_denied');
    } else {
        $test_recipient = trim($_POST['test_email'] ?? '');
        if (empty($test_recipient) || !filter_var($test_recipient, FILTER_VALIDATE_EMAIL)) {
            $error = __('invalid_email');
        } else {
            require_once '../includes/EmailHelper.php';
            $mailer = new EmailHelper($pdo);
            if (!$mailer->isConfigured()) {
                $error = __('email_not_configured');
            } else {
                $subject = ($lang === 'ar') ? '📧 فحص صحة النظام — رسالة اختبارية' : '📧 Site Health — Test Message';
                $body_html = '<h2>' . ($lang === 'ar' ? 'تم إرسال رسالة الاختبار بنجاح ✓' : 'Test message sent successfully ✓') . '</h2>'
                           . '<p>' . ($lang === 'ar' ? 'تم إرسال هذه الرسالة من لوحة فحص صحة النظام.' : 'This message was sent from the Site Health & Diagnostics page.') . '</p>'
                           . '<p><small>' . date('Y-m-d H:i:s') . '</small></p>';
                $body_text = ($lang === 'ar' ? 'رسالة اختبارية من لوحة فحص صحة النظام.' : 'Test message from Site Health & Diagnostics.')
                           . ' — ' . date('Y-m-d H:i:s');
                $result = $mailer->send($test_recipient, $subject, $body_html, $body_text);
                if (is_array($result) && !empty($result['success'])) {
                    $success = __('test_email_sent') . ' ' . __('check_spam');
                    logActivity("📧 إرسال بريد اختبار", "📧 Send Test Email", "To: $test_recipient");
                } else {
                    $error = __('test_email_failed') . ' ' . h($result['message'] ?? '');
                }
            }
        }
    }
}

// ============================================================
// HEALTH CHECK FUNCTIONS
// ============================================================

function status_healthy($label, $details = '', $value = '') {
    return ['status' => 'healthy', 'label' => $label, 'details' => $details, 'value' => $value];
}
function status_warning($label, $details = '', $value = '') {
    return ['status' => 'warning', 'label' => $label, 'details' => $details, 'value' => $value];
}
function status_critical($label, $details = '', $value = '') {
    return ['status' => 'critical', 'label' => $label, 'details' => $details, 'value' => $value];
}
function status_unknown($label, $details = '', $value = '') {
    return ['status' => 'unknown', 'label' => $label, 'details' => $details, 'value' => $value];
}

// ──────────────────────────────────────────
// 1. APPLICATION HEALTH
// ──────────────────────────────────────────
function check_application() {
    $results = [];

    // PHP version
    $php_version = phpversion();
    $version_ok = version_compare($php_version, '8.0', '>=');
    $results[] = $version_ok
        ? status_healthy('PHP Version', 'PHP ' . $php_version . ' — meets minimum 8.0', $php_version)
        : status_critical('PHP Version', 'PHP ' . $php_version . ' — below 8.0, upgrade required', $php_version);

    // Required extensions
    $required_exts = ['pdo', 'pdo_mysql', 'json', 'session', 'mbstring', 'curl', 'openssl'];
    $missing_exts = [];
    foreach ($required_exts as $ext) {
        if (!extension_loaded($ext)) $missing_exts[] = $ext;
    }
    $results[] = empty($missing_exts)
        ? status_healthy('PHP Extensions', 'All required extensions loaded')
        : status_critical('PHP Extensions', 'Missing: ' . implode(', ', $missing_exts));

    // Config constants defined
    $required_constants = ['DB_HOST', 'DB_NAME', 'DB_USER', 'BASE_URL'];
    $missing_consts = [];
    foreach ($required_constants as $c) {
        if (!defined($c)) $missing_consts[] = $c;
    }
    $results[] = empty($missing_consts)
        ? status_healthy('Config Integrity', 'All required constants defined')
        : status_critical('Config Integrity', 'Missing constants: ' . implode(', ', $missing_consts));

    // Environment
    $is_local = !isset($_SERVER['HTTP_HOST']) || $_SERVER['HTTP_HOST'] === 'localhost' || $_SERVER['HTTP_HOST'] === '127.0.0.1';
    $results[] = status_healthy('Environment', $is_local ? 'Local development' : 'Production', $is_local ? 'dev' : 'prod');

    // Required files
    $base = dirname(__DIR__) . '/';
    $required_files = [
        'includes/config.php', 'includes/header.php', 'includes/footer.php',
        'includes/languages.php', 'includes/EmailHelper.php',
        'assets/css/style.css', 'assets/js/script.js',
        'auth/login.php', 'index.php',
    ];
    $missing_files = [];
    foreach ($required_files as $f) {
        if (!file_exists($base . $f)) $missing_files[] = $f;
    }
    $results[] = empty($missing_files)
        ? status_healthy('Core Files', 'All critical application files present')
        : status_critical('Core Files', 'Missing: ' . implode(', ', $missing_files));

    // Session working
    $session_ok = session_status() === PHP_SESSION_ACTIVE;
    $results[] = $session_ok
        ? status_healthy('Session Handling', 'Session active (' . session_id() . ')')
        : status_critical('Session Handling', 'Session not active');

    return $results;
}

// ──────────────────────────────────────────
// 2. DATABASE HEALTH
// ──────────────────────────────────────────
function check_database() {
    global $pdo;
    $results = [];

    // DB connection
    $connected = $pdo !== null;
    $results[] = $connected
        ? status_healthy('DB Connection', 'Connected to ' . DB_NAME)
        : status_critical('DB Connection', 'No database connection');

    if (!$connected) {
        $results[] = status_critical('Query Execution', 'Cannot test — no connection');
        return $results;
    }

    // Query execution time
    $start = microtime(true);
    try {
        $pdo->query('SELECT 1');
        $query_time = round((microtime(true) - $start) * 1000, 2);
        $results[] = $query_time < 50
            ? status_healthy('Query Performance', "Simple query: {$query_time}ms", "{$query_time}ms")
            : status_warning('Query Performance', "Slow query: {$query_time}ms", "{$query_time}ms");
    } catch (Exception $e) {
        $results[] = status_critical('Query Performance', 'Query failed: ' . $e->getMessage());
    }

    // Required tables exist
    $required_tables = [
        'users', 'employees', 'organizations', 'activity_log',
        'leave_types', 'leave_requests', 'employee_leave_balances',
        'settings', 'system_settings', 'email_logs',
        'login_attempts', 'password_resets', 'notifications',
    ];
    try {
        $stmt = $pdo->query("SHOW TABLES");
        $existing_tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $missing_tables = array_diff($required_tables, $existing_tables);
        $results[] = empty($missing_tables)
            ? status_healthy('DB Schema', 'All ' . count($required_tables) . ' required tables present')
            : status_critical('DB Schema', 'Missing tables: ' . implode(', ', $missing_tables));
    } catch (Exception $e) {
        $results[] = status_critical('DB Schema', 'Cannot check tables: ' . $e->getMessage());
    }

    // Table row counts
    try {
        $counts = [];
        $count_queries = [
            'organizations' => "SELECT COUNT(*) FROM organizations",
            'users' => "SELECT COUNT(*) FROM users",
            'employees' => "SELECT COUNT(*) FROM employees",
            'leave_requests' => "SELECT COUNT(*) FROM leave_requests",
        ];
        foreach ($count_queries as $name => $sql) {
            $counts[$name] = $pdo->query($sql)->fetchColumn();
        }
        $results[] = status_healthy('Data Volume', json_encode($counts));
    } catch (Exception $e) {
        $results[] = status_warning('Data Volume', 'Cannot read row counts');
    }

    return $results;
}

// ──────────────────────────────────────────
// 3. EMAIL / SMTP HEALTH
// ──────────────────────────────────────────
function check_email() {
    global $pdo;
    $results = [];

    // Check sndr.sh global config (system_settings)
    $config_keys = ['sndr_api_key', 'sndr_sender_email', 'sndr_sender_name'];
    $found_keys = [];
    $missing_keys = [];

    try {
        $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('sndr_api_key','sndr_sender_email','sndr_sender_name')");
        $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        foreach ($config_keys as $key) {
            if (!empty($rows[$key] ?? '')) {
                $found_keys[] = $key;
            } else {
                $missing_keys[] = $key;
            }
        }
    } catch (Exception $e) {
        $results[] = status_warning('Email Config', 'Cannot read system_settings table');
        return $results;
    }

    $results[] = empty($missing_keys)
        ? status_healthy('Email Config', 'Global email settings configured')
        : status_warning('Email Config', 'Missing: ' . implode(', ', $missing_keys));

    // EmailHelper loads
    $helper_ok = class_exists('EmailHelper');
    $results[] = $helper_ok
        ? status_healthy('Email Service', 'EmailHelper class loaded')
        : status_critical('Email Service', 'EmailHelper class not found');

    // Email logs summary
    try {
        $stmt = $pdo->query("SELECT status, COUNT(*) as cnt FROM email_logs GROUP BY status");
        $email_stats = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        $sent_count = (int)($email_stats['sent'] ?? 0);
        $failed_count = (int)($email_stats['failed'] ?? 0);
        $bounced_count = (int)($email_stats['bounced'] ?? 0);
        $total_emails = $sent_count + $failed_count + $bounced_count;

        if ($total_emails === 0) {
            $results[] = status_healthy('Email History', 'No email activity yet', '0 total');
        } elseif ($failed_count === 0 && $bounced_count === 0) {
            $results[] = status_healthy('Email History', "{$sent_count} sent, 0 failed", "{$sent_count} sent");
        } elseif ($failed_count > 0 || $bounced_count > 0) {
            $ratio = $total_emails > 0 ? round(($failed_count + $bounced_count) / $total_emails * 100, 1) : 0;
            $results[] = ($ratio < 20)
                ? status_warning('Email History', "{$sent_count} sent, {$failed_count} failed, {$bounced_count} bounced ({$ratio}%)")
                : status_critical('Email History', "High failure rate: {$failed_count} failed, {$bounced_count} bounced ({$ratio}%)");
        }
    } catch (Exception $e) {
        $results[] = status_unknown('Email History', 'Cannot read email_logs');
    }

    return $results;
}

// ──────────────────────────────────────────
// 4. AUTHENTICATION HEALTH
// ──────────────────────────────────────────
function check_authentication() {
    global $pdo;
    $results = [];

    // Session lifetime setting
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'session_lifetime_minutes'");
        $stmt->execute();
        $session_lifetime = (int)($stmt->fetchColumn() ?: 120);
        $results[] = $session_lifetime >= 30 && $session_lifetime <= 480
            ? status_healthy('Session Lifetime', "{$session_lifetime} min — within safe range", "{$session_lifetime} min")
            : status_warning('Session Lifetime', "{$session_lifetime} min — consider 30–480 min range", "{$session_lifetime} min");
    } catch (Exception $e) {
        $results[] = status_unknown('Session Lifetime', 'Cannot read setting');
    }

    // Password policy
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'min_password_length'");
        $stmt->execute();
        $min_pass = (int)($stmt->fetchColumn() ?: 6);
        $results[] = $min_pass >= 6
            ? status_healthy('Password Policy', "Min length: {$min_pass} characters", "{$min_pass} chars")
            : status_warning('Password Policy', "Min length: {$min_pass} — recommend ≥ 6", "{$min_pass} chars");
    } catch (Exception $e) {
        $results[] = status_unknown('Password Policy', 'Cannot read setting');
    }

    // Login attempts
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM login_attempts WHERE last_attempt > DATE_SUB(NOW(), INTERVAL 24 HOUR)");
        $recent_attempts = (int)$stmt->fetchColumn();
        $results[] = $recent_attempts < 100
            ? status_healthy('Login Activity', "{$recent_attempts} attempts in last 24h", "{$recent_attempts}")
            : status_warning('Login Activity', "{$recent_attempts} attempts in last 24h — possible brute force", "{$recent_attempts}");
    } catch (Exception $e) {
        $results[] = status_unknown('Login Activity', 'Cannot read login_attempts');
    }

    // Locked accounts
    try {
        $stmt = $pdo->query("SELECT COUNT(DISTINCT username) FROM login_attempts WHERE failed_attempts >= 5 AND last_attempt > DATE_SUB(NOW(), INTERVAL 1 HOUR)");
        $locked = (int)$stmt->fetchColumn();
        $results[] = $locked === 0
            ? status_healthy('Locked Accounts', 'No accounts currently locked')
            : status_warning('Locked Accounts', "{$locked} account(s) locked due to failed attempts", "{$locked}");
    } catch (Exception $e) {
        $results[] = status_unknown('Locked Accounts', 'Cannot check');
    }

    // 2FA enabled users
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE two_factor_enabled = 1");
        $twofa_count = (int)$stmt->fetchColumn();
        $results[] = status_healthy('Two-Factor Auth', "{$twofa_count} user(s) have 2FA enabled", "{$twofa_count}");
    } catch (Exception $e) {
        $results[] = status_unknown('Two-Factor Auth', 'Cannot check');
    }

    return $results;
}

// ──────────────────────────────────────────
// 5. STORAGE / FILE SYSTEM HEALTH
// ──────────────────────────────────────────
function check_storage() {
    $results = [];
    $base = dirname(__DIR__) . '/';

    // Critical files readable
    $critical_files = [
        'assets/css/style.css',
        'assets/js/script.js',
        'manifest.php',
        'robots.txt' => null,
    ];
    foreach ($critical_files as $key => $rel) {
        if (is_int($key)) {
            $rel = $rel;
            $name = basename($rel);
        } else {
            $name = $key;
            $rel = $rel;
        }
        if ($name !== null && file_exists($base . $rel)) {
            $results[] = status_healthy($name, 'Readable');
        }
    }

    // Assets directory
    $assets_dir = $base . 'assets';
    if (is_dir($assets_dir)) {
        $results[] = status_healthy('Assets Directory', 'Accessible');
    } else {
        $results[] = status_critical('Assets Directory', 'Missing');
    }

    // Disk free space
    if (function_exists('disk_free_space')) {
        $df = @disk_free_space($base);
        $dt = @disk_total_space($base);
        if ($df !== false && $dt !== false) {
            $free_gb = round($df / 1073741824, 1);
            $total_gb = round($dt / 1073741824, 1);
            $pct = round($df / $dt * 100, 1);
            $results[] = $pct > 10
                ? status_healthy('Disk Space', "{$free_gb}GB free of {$total_gb}GB ({$pct}%)")
                : status_warning('Disk Space', "Only {$free_gb}GB free of {$total_gb}GB ({$pct}%) — low space");
        } else {
            $results[] = status_unknown('Disk Space', 'Cannot measure');
        }
    } else {
        $results[] = status_unknown('Disk Space', 'disk_free_space not available');
    }

    // Uploads directory
    $uploads_dir = $base . 'uploads';
    if (is_dir($uploads_dir)) {
        $results[] = status_healthy('Uploads Directory', 'Exists');
    } else {
        $results[] = status_unknown('Uploads Directory', 'Not created (no file uploads implemented)');
    }

    return $results;
}

// ──────────────────────────────────────────
// 6. ROUTING & PAGE HEALTH
// ──────────────────────────────────────────
function check_routing() {
    $results = [];
    $base_url = BASE_URL;

    // Check include files exist
    $base = dirname(__DIR__) . '/';
    $pages = [
        'Dashboard' => 'index.php',
        'Login' => 'auth/login.php',
        'Register' => 'auth/register.php',
        'Employees' => 'employees/list.php',
        'Leave Requests' => 'leaves/my_requests.php',
        'Admin Settings' => 'admin/settings.php',
        'Activity Log' => 'admin/activity_log.php',
    ];
    $missing_pages = [];
    foreach ($pages as $name => $path) {
        if (!file_exists($base . $path)) $missing_pages[] = $name;
    }
    $results[] = empty($missing_pages)
        ? status_healthy('Critical Pages', 'All ' . count($pages) . ' checked pages exist')
        : status_critical('Critical Pages', 'Missing: ' . implode(', ', $missing_pages));

    // Template files
    $templates = ['includes/header.php', 'includes/footer.php', 'includes/languages.php'];
    $missing_templates = [];
    foreach ($templates as $t) {
        if (!file_exists($base . $t)) $missing_templates[] = $t;
    }
    $results[] = empty($missing_templates)
        ? status_healthy('Templates', 'All template files present')
        : status_critical('Templates', 'Missing: ' . implode(', ', $missing_templates));

    return $results;
}

// ──────────────────────────────────────────
// 7. SECURITY & CONFIGURATION HEALTH
// ──────────────────────────────────────────
function check_security() {
    global $pdo;
    $results = [];

    // Error reporting
    $is_local = !isset($_SERVER['HTTP_HOST']) || $_SERVER['HTTP_HOST'] === 'localhost' || $_SERVER['HTTP_HOST'] === '127.0.0.1';
    $display_errors = ini_get('display_errors');
    if ($is_local) {
        $results[] = status_healthy('Error Reporting', 'Display enabled (local environment — safe)');
    } else {
        $results[] = $display_errors === '0' || $display_errors === 'off'
            ? status_healthy('Error Reporting', 'Display disabled (secure — production)')
            : status_critical('Error Reporting', 'Display enabled in production — sensitive data may leak');
    }

    // CSRF token initialized
    $csrf_ok = isset($_SESSION['csrf_token']);
    $results[] = $csrf_ok
        ? status_healthy('CSRF Protection', 'Token initialized')
        : status_warning('CSRF Protection', 'No CSRF token in session (will be created on first form load)');

    // https check
    $is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['SERVER_PORT'] ?? 80) == 443;
    $results[] = $is_https
        ? status_healthy('HTTPS', 'Connection is secure')
        : status_warning('HTTPS', 'Not using HTTPS — credentials transmitted in plaintext');

    // Maintenance mode
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'maintenance_mode'");
        $stmt->execute();
        $mm = $stmt->fetchColumn();
        if ($mm === '1') {
            $results[] = status_warning('Maintenance Mode', 'System is in maintenance mode — users may be blocked');
        } else {
            $results[] = status_healthy('Maintenance Mode', 'System is live');
        }
    } catch (Exception $e) {
        $results[] = status_unknown('Maintenance Mode', 'Cannot check');
    }

    // Registration open?
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'allow_new_org_registration'");
        $stmt->execute();
        $allow_reg = $stmt->fetchColumn();
        $results[] = $allow_reg === '1'
            ? status_healthy('Registration', 'New organization registration is open')
            : status_warning('Registration', 'New organization registration is disabled');
    } catch (Exception $e) {
        $results[] = status_unknown('Registration', 'Cannot check');
    }

    return $results;
}

// ============================================================
// RUN ALL CHECKS
// ============================================================
$start_time = microtime(true);
$health = [
    'application' => ['title' => __('health_app'), 'icon' => '🚀', 'checks' => check_application()],
    'database'    => ['title' => __('health_db'),    'icon' => '🗄️',  'checks' => check_database()],
    'email'       => ['title' => __('health_email'),  'icon' => '📧',  'checks' => check_email()],
    'auth'        => ['title' => __('health_auth'),   'icon' => '🔐',  'checks' => check_authentication()],
    'storage'     => ['title' => __('health_storage'),'icon' => '💾',  'checks' => check_storage()],
    'routing'     => ['title' => __('health_routing'),'icon' => '🔗',  'checks' => check_routing()],
    'security'    => ['title' => __('health_security'),'icon' => '🛡️', 'checks' => check_security()],
];
$execution_time = round((microtime(true) - $start_time) * 1000, 0);

// Compute overall status
$overall = 'healthy';
foreach ($health as $group) {
    foreach ($group['checks'] as $c) {
        if ($c['status'] === 'critical') $overall = 'critical';
        elseif ($c['status'] === 'warning' && $overall !== 'critical') $overall = 'warning';
    }
}

// ──────────────────────────────────────────
// DIAGNOSTICS PANEL DATA
// ──────────────────────────────────────────
$diagnostics = [];

// Recent activity (last 20 entries)
try {
    $stmt = $pdo->query("SELECT al.*, u.username FROM activity_log al LEFT JOIN users u ON al.user_id = u.id ORDER BY al.created_at DESC LIMIT 20");
    $diagnostics['recent_activity'] = $stmt->fetchAll();
} catch (Exception $e) {
    $diagnostics['recent_activity'] = [];
}

// Email failures
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM email_logs WHERE status = 'failed'");
    $diagnostics['email_failures'] = (int)$stmt->fetchColumn();
} catch (Exception $e) {
    $diagnostics['email_failures'] = -1;
}

// Failed login attempts today
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM login_attempts WHERE DATE(last_attempt) = CURDATE()");
    $diagnostics['login_failures_today'] = (int)$stmt->fetchColumn();
} catch (Exception $e) {
    $diagnostics['login_failures_today'] = -1;
}

// Users registered today
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE DATE(created_at) = CURDATE()");
    $diagnostics['users_today'] = (int)$stmt->fetchColumn();
} catch (Exception $e) {
    $diagnostics['users_today'] = -1;
}

// Pending leave requests
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM leave_requests WHERE status = 'pending'");
    $diagnostics['pending_leaves'] = (int)$stmt->fetchColumn();
} catch (Exception $e) {
    $diagnostics['pending_leaves'] = -1;
}

// Pending employee approvals
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM employees WHERE status = 'pending'");
    $diagnostics['pending_employees'] = (int)$stmt->fetchColumn();
} catch (Exception $e) {
    $diagnostics['pending_employees'] = -1;
}

$pageTitle = __('site_health_title');
if ($_SESSION['role'] === 'super_admin') {
    include '../includes/superadmin_header.php';
} else {
    include '../includes/header.php';
}
?>

<style>
    .health-card { background: var(--card-bg, #fff); border-radius: var(--border-radius); border: none; box-shadow: var(--card-shadow); margin-bottom: 1.5rem; overflow: hidden; }
    .health-card-header { padding: 1.25rem 1.5rem; font-weight: 700; font-size: 1.1rem; display: flex; align-items: center; gap: 0.75rem; border-bottom: 1px solid rgba(0,0,0,0.04); }
    .health-card-body { padding: 0; }
    .health-check-item { display: flex; align-items: flex-start; gap: 0.75rem; padding: 0.875rem 1.5rem; border-bottom: 1px solid rgba(0,0,0,0.03); }
    .health-check-item:last-child { border-bottom: none; }
    .health-indicator { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; margin-top: 0.4rem; }
    .indicator-healthy { background-color: #22c55e; box-shadow: 0 0 6px rgba(34,197,94,0.4); }
    .indicator-warning { background-color: #eab308; box-shadow: 0 0 6px rgba(234,179,8,0.4); }
    .indicator-critical { background-color: #ef4444; box-shadow: 0 0 6px rgba(239,68,68,0.4); }
    .indicator-unknown { background-color: #94a3b8; box-shadow: 0 0 6px rgba(148,163,184,0.4); }
    .health-label { font-weight: 600; font-size: 0.9rem; color: var(--text-main); }
    .health-details { font-size: 0.8rem; color: var(--text-muted); margin-top: 0.15rem; }
    .health-value { font-size: 0.8rem; font-weight: 600; color: var(--text-muted); white-space: nowrap; margin-left: auto; padding-left: 1rem; }

    .overall-badge { display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.5rem 1.25rem; border-radius: 50px; font-weight: 700; font-size: 1rem; }
    .overall-healthy { background-color: rgba(34,197,94,0.12); color: #22c55e; border: 1px solid rgba(34,197,94,0.25); }
    .overall-warning { background-color: rgba(234,179,8,0.12); color: #eab308; border: 1px solid rgba(234,179,8,0.25); }
    .overall-critical { background-color: rgba(239,68,68,0.12); color: #ef4444; border: 1px solid rgba(239,68,68,0.25); }

    .stat-mini-card { background: var(--card-bg, #fff); border-radius: var(--border-radius); padding: 1.25rem; text-align: center; border: 1px solid rgba(0,0,0,0.04); }
    .stat-mini-value { font-size: 1.75rem; font-weight: 800; line-height: 1.2; }
    .stat-mini-label { font-size: 0.8rem; color: var(--text-muted); margin-top: 0.25rem; }

    .diag-table { width: 100%; font-size: 0.85rem; }
    .diag-table th { padding: 0.625rem 1rem; text-align: start; font-weight: 600; color: var(--text-muted); border-bottom: 1px solid rgba(0,0,0,0.04); }

    .diag-table td { padding: 0.5rem 1rem; border-bottom: 1px solid rgba(0,0,0,0.03); color: var(--text-main); vertical-align: top; }
    .diag-table tr:last-child td { border-bottom: none; }
    .diag-badge { display: inline-block; padding: 0.15rem 0.5rem; border-radius: 4px; font-size: 0.75rem; font-weight: 600; }
    .diag-badge-sent { background-color: rgba(34,197,94,0.12); color: #22c55e; }
    .diag-badge-failed { background-color: rgba(239,68,68,0.12); color: #ef4444; }
    .diag-badge-pending { background-color: rgba(234,179,8,0.12); color: #eab308; }
    .diag-ip { font-family: monospace; font-size: 0.75rem; color: var(--text-muted); }

    .health-summary-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 0.75rem; margin-bottom: 1.5rem; }
    .health-summary-card { text-align: center; padding: 0.5rem 0.75rem; border-radius: var(--border-radius); background: var(--card-bg, #fff); border: 1px solid rgba(0,0,0,0.04); cursor: default; }
    .health-summary-value { font-size: 1.35rem; font-weight: 700; }
    .health-summary-label { font-size: 0.72rem; color: var(--text-muted); margin-top: 0.15rem; }

    [data-theme="dark"] .health-card { border: 1px solid var(--border-color); }
    [data-theme="dark"] .health-card-header { border-bottom-color: var(--border-color); }
    [data-theme="dark"] .health-check-item { border-bottom-color: rgba(255,255,255,0.04); }
    [data-theme="dark"] .stat-mini-card { border-color: var(--border-color); }
    [data-theme="dark"] .diag-table th { border-bottom-color: var(--border-color); }
    [data-theme="dark"] .diag-table td { border-bottom-color: rgba(255,255,255,0.04); }
    [data-theme="dark"] .health-summary-card { border-color: var(--border-color); }
</style>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1"><?php echo __('site_health_title'); ?></h1>
        <p class="text-muted small mb-0"><?php echo __('site_health_desc'); ?></p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <span class="small text-muted"><?php echo __('last_checked'); ?>: <?php echo date('H:i:s'); ?></span>
        <span class="small text-muted">| <?php echo $execution_time; ?>ms</span>
        <a href="site_health.php" class="btn btn-sm btn-outline-primary"><?php echo __('refresh'); ?></a>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger shadow-sm border-0 mb-4"><?php echo $error; ?></div>
<?php endif; ?>
<?php if ($success): ?>
    <div class="alert alert-success shadow-sm border-0 mb-4"><?php echo $success; ?></div>
<?php endif; ?>

<!-- Overall Status -->
<div class="d-flex align-items-center gap-3 mb-4 flex-wrap">
    <span class="overall-badge overall-<?php echo $overall; ?>">
        <?php if ($overall === 'healthy'): ?><i class="bi bi-circle-fill text-success"></i><?php elseif ($overall === 'warning'): ?><i class="bi bi-circle-fill text-warning"></i><?php else: ?><i class="bi bi-circle-fill text-danger"></i><?php endif; ?>
        <?php echo __('health_overall_' . $overall); ?>
    </span>
    <span class="text-muted small"><?php echo sprintf(__('health_checks_run'), count($health)); ?></span>
</div>

<!-- Health Check Groups -->
<div class="row">
    <?php foreach ($health as $groupId => $group): ?>
    <div class="col-lg-6 mb-3">
        <div class="health-card">
            <div class="health-card-header">
                <span><?php echo $group['icon']; ?></span>
                <?php echo $group['title']; ?>
                <?php
                $group_status = 'healthy';
                foreach ($group['checks'] as $c) {
                    if ($c['status'] === 'critical') $group_status = 'critical';
                    elseif ($c['status'] === 'warning' && $group_status !== 'critical') $group_status = 'warning';
                }
                ?>
                <span class="ms-auto health-indicator indicator-<?php echo $group_status; ?>" title="<?php echo ucfirst($group_status); ?>"></span>
            </div>
            <div class="health-card-body">
                <?php foreach ($group['checks'] as $c): ?>
                <div class="health-check-item">
                    <span class="health-indicator indicator-<?php echo $c['status']; ?>" title="<?php echo ucfirst($c['status']); ?>"></span>
                    <div class="flex-grow-1">
                        <div class="health-label"><?php echo h($c['label']); ?></div>
                        <div class="health-details"><?php echo h($c['details']); ?></div>
                    </div>
                    <?php if ($c['value']): ?>
                    <span class="health-value"><?php echo h($c['value']); ?></span>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Test Email Card -->
<div class="health-card">
    <div class="health-card-header">
        <span><i class="bi bi-envelope"></i></span> <?php echo __('health_test_email'); ?>
    </div>
    <div class="health-card-body p-3">
        <form method="POST" action="site_health.php" class="row g-2 align-items-end">
            <?php echo csrf_field(); ?>
            <div class="col-md-6 col-lg-4">
                <label for="test_email" class="form-label small fw-bold"><?php echo __('health_test_email_recipient'); ?></label>
                <input type="email" name="test_email" id="test_email" class="form-control" placeholder="admin@example.com" required>
            </div>
            <div class="col-md-3 col-lg-2">
                <button type="submit" name="send_test_email" class="btn btn-primary w-100"><?php echo __('health_send_test'); ?></button>
            </div>
        </form>
    </div>
</div>

<!-- Diagnostics Panel -->
<div class="health-card">
    <div class="health-card-header">
        <span><i class="bi bi-bar-chart-line"></i></span> <?php echo __('health_diagnostics'); ?>
    </div>
    <div class="health-card-body">
        <!-- Mini stats -->
        <div class="health-summary-row p-3 pb-0">
            <div class="stat-mini-card">
                <div class="stat-mini-value text-success"><?php echo $diagnostics['users_today'] >= 0 ? $diagnostics['users_today'] : '?'; ?></div>
                <div class="stat-mini-label"><?php echo __('health_users_today'); ?></div>
            </div>
            <div class="stat-mini-card">
                <div class="stat-mini-value text-warning"><?php echo $diagnostics['pending_leaves'] >= 0 ? $diagnostics['pending_leaves'] : '?'; ?></div>
                <div class="stat-mini-label"><?php echo __('health_pending_leaves'); ?></div>
            </div>
            <div class="stat-mini-card">
                <div class="stat-mini-value text-warning"><?php echo $diagnostics['pending_employees'] >= 0 ? $diagnostics['pending_employees'] : '?'; ?></div>
                <div class="stat-mini-label"><?php echo __('health_pending_employees'); ?></div>
            </div>
            <div class="stat-mini-card">
                <div class="stat-mini-value text-danger"><?php echo $diagnostics['email_failures'] >= 0 ? $diagnostics['email_failures'] : '?'; ?></div>
                <div class="stat-mini-label"><?php echo __('health_email_failures'); ?></div>
            </div>
            <div class="stat-mini-card">
                <div class="stat-mini-value text-danger"><?php echo $diagnostics['login_failures_today'] >= 0 ? $diagnostics['login_failures_today'] : '?'; ?></div>
                <div class="stat-mini-label"><?php echo __('health_failed_logins'); ?></div>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="p-3">
            <h6 class="fw-bold mb-2"><?php echo __('health_recent_activity'); ?></h6>
            <?php if (empty($diagnostics['recent_activity'])): ?>
                <p class="text-muted small mb-0"><?php echo __('no_data'); ?></p>
            <?php else: ?>
            <div style="max-height: 400px; overflow-y: auto;">
                <table class="diag-table">
                    <thead>
                        <tr>
                            <th><?php echo __('time'); ?></th>
                            <th><?php echo __('user'); ?></th>
                            <th><?php echo __('action'); ?></th>
                            <th><?php echo __('ip'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($diagnostics['recent_activity'] as $log): ?>
                        <tr>
                            <td style="white-space: nowrap;"><?php echo date('H:i', strtotime($log['created_at'])); ?></td>
                            <td><?php echo h($log['username'] ?? '-'); ?></td>
                            <td><?php echo $lang === 'ar' ? h($log['action_ar']) : h($log['action_en']); ?></td>
                            <td><span class="diag-ip"><?php echo h($log['ip_address'] ?? '-'); ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if ($_SESSION['role'] === 'super_admin') { include '../includes/superadmin_footer.php'; } else { include '../includes/footer.php'; } ?>
