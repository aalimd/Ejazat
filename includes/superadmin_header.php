<!DOCTYPE html>
<html lang="<?php echo __('lang_code'); ?>" dir="<?php echo __('dir'); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php
    $primary_hex = sanitizeCssValue(getSetting('primary_color', '#0d6efd'));
    if (preg_match('/^#[0-9a-fA-F]{6}$/', $primary_hex)) {
        list($r, $g, $b) = sscanf($primary_hex, "#%02x%02x%02x");
    } else {
        $r = 13; $g = 110; $b = 253;
    }
    $primary_rgb = "$r, $g, $b";
    ?>
    <title><?php echo isset($pageTitle) ? $pageTitle . ' - ' . __('site_name') : __('site_name'); ?></title>
    <link rel="manifest" href="<?php echo BASE_URL; ?>manifest.php">
    <meta name="theme-color" content="<?php echo h($primary_hex); ?>">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="<?php echo h(SITE_NAME); ?>">
    <link rel="apple-touch-icon" href="<?php echo BASE_URL; ?>assets/images/icon-192.png">
    <script>const BASE_URL = '<?php echo BASE_URL; ?>';</script>
    <?php if (__('dir') == 'rtl'): ?>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css">
    <?php else: ?>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <?php endif; ?>
    <?php if (__('dir') == 'rtl'): ?>
        <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <?php else: ?>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <?php endif; ?>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/style.css?v=5">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
    <style>
        :root {
            --primary-color: <?php echo $primary_hex; ?>;
            --primary-color-rgb: <?php echo $primary_rgb; ?>;
            --sa-sidebar-bg: #0f172a;
            --sa-sidebar-hover: #1e293b;
            --sa-sidebar-active: #2563eb;
            --sa-sidebar-text: #94a3b8;
            --sa-sidebar-text-active: #ffffff;
            --sa-sidebar-width: 260px;
            --sa-topbar-bg: #ffffff;
            --sa-topbar-border: #e2e8f0;
            --sa-shadow-color: rgba(0,0,0,0.08);
        }
        [data-theme="dark"] {
            --sa-sidebar-bg: #0b1120;
            --sa-sidebar-hover: #1a2332;
            --sa-sidebar-active: #3b82f6;
            --sa-sidebar-text: #64748b;
            --sa-topbar-bg: #0f172a;
            --sa-topbar-border: #1e293b;
            --sa-shadow-color: rgba(0,0,0,0.4);
        }
        body {
            font-family: <?php echo __('dir') == 'rtl' ? "'Cairo', sans-serif" : "'Inter', sans-serif"; ?>;
            overflow-x: hidden;
            background: var(--bs-tertiary-bg);
        }
        /* ===== SIDEBAR ===== */
        .sa-sidebar {
            position: fixed;
            top: 0;
            bottom: 0;
            left: <?php echo __('dir') == 'rtl' ? 'auto' : '0'; ?>;
            right: <?php echo __('dir') == 'rtl' ? '0' : 'auto'; ?>;
            width: var(--sa-sidebar-width);
            background: var(--sa-sidebar-bg);
            z-index: 1030;
            overflow-y: auto;
            overflow-x: hidden;
            display: flex;
            flex-direction: column;
            transition: transform 0.3s ease;
        }
        .sa-sidebar-brand {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid rgba(255,255,255,0.06);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .sa-sidebar-brand-icon {
            width: 36px; height: 36px;
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            color: #fff;
            flex-shrink: 0;
        }
        .sa-sidebar-brand-text {
            font-weight: 700;
            font-size: 0.95rem;
            color: #f1f5f9;
            line-height: 1.2;
        }
        .sa-sidebar-brand-sub {
            font-size: 0.65rem;
            color: var(--sa-sidebar-text);
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        .sa-sidebar-section {
            padding: 1rem 1.5rem 0.35rem;
            font-size: 0.65rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--sa-sidebar-text);
            opacity: 0.5;
        }
        .sa-sidebar .nav-link {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.6rem 1.5rem;
            color: var(--sa-sidebar-text);
            font-size: 0.875rem;
            font-weight: 500;
            border-radius: 0;
            transition: all 0.15s ease;
            border-left: <?php echo __('dir') == 'rtl' ? '0' : '3px solid transparent'; ?>;
            border-right: <?php echo __('dir') == 'rtl' ? '3px solid transparent' : '0'; ?>;
        }
        .sa-sidebar .nav-link:hover {
            color: var(--sa-sidebar-text-active);
            background: var(--sa-sidebar-hover);
        }
        .sa-sidebar .nav-link.active {
            color: var(--sa-sidebar-text-active);
            background: rgba(37, 99, 235, 0.12);
            border-left-color: var(--sa-sidebar-active);
            border-right-color: var(--sa-sidebar-active);
        }
        .sa-sidebar .nav-link .sa-icon {
            width: 20px;
            text-align: center;
            font-size: 1rem;
            flex-shrink: 0;
        }
        .sa-sidebar-divider {
            height: 1px;
            background: rgba(255,255,255,0.06);
            margin: 0.5rem 1.5rem;
        }
        .sa-sidebar-footer {
            padding: 1rem 1.5rem;
            border-top: 1px solid rgba(255,255,255,0.06);
            margin-top: auto;
        }
        .sa-sidebar-footer .nav-link {
            padding: 0.5rem 0;
            font-size: 0.8rem;
        }
        /* ===== TOP BAR ===== */
        .sa-topbar {
            position: fixed;
            top: 0;
            left: <?php echo __('dir') == 'rtl' ? '0' : 'var(--sa-sidebar-width)'; ?>;
            right: <?php echo __('dir') == 'rtl' ? 'var(--sa-sidebar-width)' : '0'; ?>;
            height: 60px;
            background: var(--sa-topbar-bg);
            border-bottom: 1px solid var(--sa-topbar-border);
            z-index: 1025;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 1.5rem;
        }
        .sa-topbar-left {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        .sa-topbar-right {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .sa-topbar-user {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.35rem 0.75rem;
            border-radius: 8px;
            cursor: pointer;
            transition: background 0.15s;
        }
        .sa-topbar-user:hover {
            background: rgba(0,0,0,0.04);
        }
        [data-theme="dark"] .sa-topbar-user:hover {
            background: rgba(255,255,255,0.05);
        }
        .sa-topbar-avatar {
            width: 32px; height: 32px;
            border-radius: 8px;
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-weight: 700;
            font-size: 0.8rem;
        }
        .sa-topbar-user-name {
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--text-main);
        }
        .sa-topbar-user-role {
            font-size: 0.65rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #f59e0b;
        }
        .sa-topbar-user .dropdown-menu {
            top: 100%;
            margin-top: 0.5rem;
        }
        [dir="rtl"] .sa-topbar-user .dropdown-menu { right: auto; left: 0; }
        [dir="ltr"] .sa-topbar-user .dropdown-menu { left: auto; right: 0; }
        .sa-org-switcher {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .sa-org-switcher select {
            font-size: 0.8rem;
            max-width: 220px;
            border: 1px solid var(--sa-topbar-border);
            border-radius: 6px;
            padding: 0.3rem 0.6rem;
            background: transparent;
            color: var(--text-main);
        }
        /* ===== MAIN CONTENT ===== */
        .sa-main {
            margin-left: <?php echo __('dir') == 'rtl' ? '0' : 'var(--sa-sidebar-width)'; ?>;
            margin-right: <?php echo __('dir') == 'rtl' ? 'var(--sa-sidebar-width)' : '0'; ?>;
            margin-top: 60px;
            padding: 1.5rem;
            min-height: calc(100vh - 60px);
        }
        /* ===== ORG SWITCHER DROPDOWN ===== */
        .sa-org-dropdown .dropdown-item {
            font-size: 0.85rem;
            padding: 0.5rem 1rem;
        }
        .sa-badge-super {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: #000;
            font-size: 0.6rem;
            font-weight: 700;
            padding: 0.15em 0.5em;
            border-radius: 4px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        /* ===== MOBILE SIDEBAR ===== */
        .sa-sidebar-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.5);
            z-index: 1029;
        }
        @media (max-width: 767.98px) {
            .sa-sidebar {
                transform: translateX(<?php echo __('dir') == 'rtl' ? '100%' : '-100%'; ?>);
            }
            .sa-sidebar.open {
                transform: translateX(0);
            }
            .sa-sidebar-overlay.open {
                display: block;
            }
            .sa-main {
                margin-left: 0;
                margin-right: 0;
            }
            .sa-topbar {
                left: 0;
                right: 0;
            }
        }
    </style>
    <script>
        (function() {
            const storedTheme = localStorage.getItem('theme');
            const osDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            const t = storedTheme || (osDark ? 'dark' : 'light');
            if (t === 'dark') document.documentElement.setAttribute('data-theme', 'dark');
        })();
        document.addEventListener('DOMContentLoaded', () => {
            const btn = document.getElementById('saThemeToggle');
            if (btn) {
                const t = localStorage.getItem('theme') || 'light';
                btn.innerHTML = t === 'dark' ? '<span class="emoji-icon">☀️</span>' : '<span class="emoji-icon">🌙</span>';
                btn.addEventListener('click', () => {
                    let theme = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
                    document.documentElement.setAttribute('data-theme', theme);
                    localStorage.setItem('theme', theme);
                    btn.innerHTML = theme === 'dark' ? '<span class="emoji-icon">☀️</span>' : '<span class="emoji-icon">🌙</span>';
                });
            }
            const sidebarToggle = document.getElementById('saSidebarToggle');
            const sidebar = document.getElementById('saSidebar');
            const overlay = document.getElementById('saSidebarOverlay');
            if (sidebarToggle && sidebar && overlay) {
                const open = () => { sidebar.classList.add('open'); overlay.classList.add('open'); };
                const close = () => { sidebar.classList.remove('open'); overlay.classList.remove('open'); };
                sidebarToggle.addEventListener('click', open);
                overlay.addEventListener('click', close);
                document.querySelectorAll('.sa-sidebar .nav-link').forEach(el => {
                    el.addEventListener('click', () => { if (window.innerWidth < 768) close(); });
                });
            }
        });
    </script>
</head>
<body>

<?php
global $lang;
$current_page = basename($_SERVER['PHP_SELF']);
$current_dir = basename(dirname($_SERVER['PHP_SELF']));

// Avatar initials
$user_display = $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Super Admin';
$initials = '';
foreach (preg_split('/\s+/', trim($user_display)) as $word) {
    $initials .= mb_substr($word, 0, 1);
}
$initials = mb_strtoupper(mb_substr($initials, 0, 2));

$super_nav = [
    'dashboard' => [
        'label' => __('dashboard'),
        'icon' => '<span class="emoji-icon">📊</span>',
        'url' => BASE_URL . 'superadmin/dashboard.php',
        'active' => $current_page === 'dashboard.php',
    ],
    'section_platform' => ['section' => __('platform_management')],
    'organizations' => [
        'label' => __('organizations_mgmt'),
        'icon' => '<span class="emoji-icon">🏢</span>',
        'url' => BASE_URL . 'admin/organizations.php',
        'active' => $current_page === 'organizations.php',
    ],
    'users' => [
        'label' => __('system_users'),
        'icon' => '<span class="emoji-icon">👥</span>',
        'url' => BASE_URL . 'admin/users.php',
        'active' => $current_page === 'users.php',
    ],
    'registration' => [
        'label' => __('registration_user_onboarding'),
        'icon' => '<span class="emoji-icon">📋</span>',
        'url' => BASE_URL . 'admin/registration_control.php',
        'active' => $current_page === 'registration_control.php',
    ],
    'org_codes' => [
        'label' => __('invitation_codes'),
        'icon' => '<span class="emoji-icon">🎟️</span>',
        'url' => BASE_URL . 'admin/organization_codes.php',
        'active' => $current_page === 'organization_codes.php',
    ],
    'section_system' => ['section' => __('system_control')],
    'settings' => [
        'label' => __('system_settings_title'),
        'icon' => '<span class="emoji-icon">⚙️</span>',
        'url' => BASE_URL . 'admin/system_settings.php',
        'active' => $current_page === 'system_settings.php',
    ],
    'section_ops' => ['section' => __('operations')],
    'health' => [
        'label' => __('site_health_title'),
        'icon' => '<span class="emoji-icon">🩺</span>',
        'url' => BASE_URL . 'admin/site_health.php',
        'active' => $current_page === 'site_health.php',
    ],
    'activity' => [
        'label' => __('activity_log'),
        'icon' => '<span class="emoji-icon">📜</span>',
        'url' => BASE_URL . 'admin/activity_log.php',
        'active' => $current_page === 'activity_log.php',
    ],
    'db_migration' => [
        'label' => __('db_migration_center'),
        'icon' => '<span class="emoji-icon">🗄️</span>',
        'url' => BASE_URL . 'superadmin/database_migration.php',
        'active' => $current_page === 'database_migration.php',
    ],
];
?>

<!-- Sidebar Overlay (mobile) -->
<div id="saSidebarOverlay" class="sa-sidebar-overlay"></div>

<!-- Sidebar -->
<nav id="saSidebar" class="sa-sidebar">
    <div class="sa-sidebar-brand">
        <div class="sa-sidebar-brand-icon"><span class="emoji-icon">🔐</span></div>
        <div>
            <div class="sa-sidebar-brand-text"><?php echo __('site_name'); ?></div>
            <div class="sa-sidebar-brand-sub"><?php echo __('super_admin_title'); ?></div>
        </div>
    </div>
    <div class="flex-grow-1">
        <?php foreach ($super_nav as $item): ?>
            <?php if (isset($item['section'])): ?>
                <div class="sa-sidebar-section"><?php echo $item['section']; ?></div>
            <?php else: ?>
                <a class="nav-link <?php echo $item['active'] ? 'active' : ''; ?>" href="<?php echo $item['url']; ?>">
                    <span class="sa-icon"><?php echo $item['icon']; ?></span>
                    <?php echo $item['label']; ?>
                </a>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
    <div class="sa-sidebar-divider"></div>
    <div class="sa-sidebar-footer">
        <a class="nav-link" href="<?php echo BASE_URL; ?>auth/security.php">
            <span class="sa-icon"><span class="emoji-icon">🔑</span></span>
            <?php echo __('security_settings'); ?>
        </a>
        <form action="<?php echo BASE_URL; ?>auth/logout.php" method="POST" class="m-0">
            <?php echo csrf_field(); ?>
            <button type="submit" class="nav-link text-danger border-0 bg-transparent w-100 text-start px-0 py-2 d-flex align-items-center gap-2" style="font-size: 0.8rem;">
                <span class="sa-icon"><span class="emoji-icon">🚪</span></span>
                <?php echo __('logout'); ?>
            </button>
        </form>
    </div>
</nav>

<!-- Top Bar -->
<header class="sa-topbar">
    <div class="sa-topbar-left">
        <button id="saSidebarToggle" type="button" class="btn btn-sm btn-outline-secondary border-0 fs-5 d-md-none" aria-label="<?php echo __('menu'); ?>"><span class="emoji-icon">☰</span></button>
        <div class="d-none d-md-flex align-items-center gap-2">
            <span class="sa-badge-super"><?php echo __('super_admin'); ?></span>
        </div>
        <?php if (isset($show_org_switcher) && $show_org_switcher): ?>
        <div class="sa-org-switcher ms-3 d-none d-md-flex">
            <span class="small text-muted"><span class="emoji-icon">🏢</span></span>
            <select class="form-select-sm" aria-label="<?php echo __('switch_org'); ?>" onchange="if(this.value) window.location='<?php echo BASE_URL; ?>superadmin/dashboard.php?switch_org='+this.value; else window.location='<?php echo BASE_URL; ?>superadmin/dashboard.php?switch_org=0';">
                <option value=""><?php echo __('all_organizations'); ?></option>
                <?php
                $all_orgs = $pdo->query("SELECT id, name_ar, name_en FROM organizations WHERE is_active = 1 ORDER BY name_ar")->fetchAll();
                $current_sel = $_SESSION['organization_id'] ?? '';
                foreach ($all_orgs as $o):
                ?>
                    <option value="<?php echo $o['id']; ?>" <?php echo $current_sel == $o['id'] ? 'selected' : ''; ?>>
                        <?php echo get_name($o); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>
    </div>
    <div class="sa-topbar-right">
        <button id="saThemeToggle" type="button" class="btn btn-sm btn-outline-secondary border-0" aria-label="<?php echo __('theme_toggle'); ?>"><span class="emoji-icon">🌙</span></button>
        <a class="btn btn-sm btn-outline-secondary border-0" href="?lang=<?php echo $lang == 'ar' ? 'en' : 'ar'; ?>">
            <span class="emoji-icon"><?php echo __('language'); ?></span>
        </a>
        <div class="sa-topbar-user dropdown">
            <a class="d-flex align-items-center gap-2 text-decoration-none" href="#" id="saUserDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                <div class="sa-topbar-avatar"><?php echo h($initials); ?></div>
                <div class="d-none d-md-block">
                    <div class="sa-topbar-user-name"><?php echo h($_SESSION['full_name'] ?? $_SESSION['username']); ?></div>
                    <div class="sa-topbar-user-role"><?php echo __('super_admin'); ?></div>
                </div>
            </a>
            <ul class="dropdown-menu dropdown-menu-end shadow border-0 user-menu mt-2" aria-labelledby="saUserDropdown">
                <li><a class="dropdown-item small py-2" href="<?php echo BASE_URL; ?>index.php"><span class="emoji-icon">🏠</span> <?php echo __('back_to_app'); ?></a></li>
                <li><a class="dropdown-item small py-2" href="<?php echo BASE_URL; ?>auth/security.php"><span class="emoji-icon">🔑</span> <?php echo __('security_settings'); ?></a></li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <form action="<?php echo BASE_URL; ?>auth/logout.php" method="POST" class="m-0">
                        <?php echo csrf_field(); ?>
                        <button type="submit" class="dropdown-item small py-2 text-danger">
                            <span class="emoji-icon">🚪</span> <?php echo __('logout'); ?>
                        </button>
                    </form>
                </li>
            </ul>
        </div>
    </div>
</header>

<!-- Main Content -->
<main class="sa-main">
