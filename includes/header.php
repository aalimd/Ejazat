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
    <title><?php echo isset($pageTitle) ? $pageTitle . " - " . __('site_name') : __('site_name'); ?></title>
    
    <!-- PWA Settings & Manifest -->
    <link rel="manifest" href="<?php echo BASE_URL; ?>manifest.php">
    <meta name="theme-color" content="<?php echo h($primary_hex); ?>">
    
    <!-- Mobile Capability Meta Tags (iOS Support) -->
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="<?php echo h(SITE_NAME); ?>">
    <link rel="apple-touch-icon" href="<?php echo BASE_URL; ?>assets/images/icon-192.png">
    
    <script>
        const BASE_URL = '<?php echo BASE_URL; ?>';
    </script>
    
    <!-- Bootstrap CSS -->
    <?php if (__('dir') == 'rtl'): ?>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css">
    <?php else: ?>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <?php endif; ?>

    <!-- Google Fonts (الخط المطلوب حسب اللغة فقط لتحسين الأداء) -->
    <?php if (__('dir') == 'rtl'): ?>
        <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <?php else: ?>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <?php endif; ?>

    <!-- Custom Modern Styles -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/style.css?v=5">
    <!-- Chart.js (defer لعدم حجب عرض الصفحة) -->
    <script defer src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>

    <style>
        :root {
            --primary-color: <?php echo $primary_hex; ?>;
            --primary-color-rgb: <?php echo $primary_rgb; ?>;
        }
        body {
            font-family: <?php echo __('dir') == 'rtl' ? "'" . getSetting('font_family_ar', 'Cairo') . "', sans-serif" : "'" . getSetting('font_family_en', 'Inter') . "', sans-serif"; ?>;
            overflow-x: hidden;
        }
        .bg-primary { background-color: var(--primary-color) !important; }
        .text-primary { color: var(--primary-color) !important; }
        
        @media (max-width: 767.98px) {
            .sidebar {
                border-radius: 0 !important;
                box-shadow: none !important;
            }
            .offcanvas-body {
                padding: 0;
            }
            .sidebar .nav-link {
                margin: 5px 10px;
                padding: 15px 20px;
                font-size: 1rem;
            }
            main {
                padding-top: 10px;
            }
        }
    </style>
    <script>
        const storedTheme = localStorage.getItem('theme');
        const osDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        const currentTheme = storedTheme || (osDark ? 'dark' : 'light');
        if (currentTheme === 'dark') {
            document.documentElement.setAttribute('data-theme', 'dark');
        }
        document.addEventListener('DOMContentLoaded', () => {
            const themeToggle = document.getElementById('themeToggle');
            if(themeToggle) {
                themeToggle.innerHTML = currentTheme === 'dark' ? '<span class="emoji-icon">☀️</span>' : '<span class="emoji-icon">🌙</span>';
                themeToggle.addEventListener('click', () => {
                    let theme = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
                    document.documentElement.setAttribute('data-theme', theme);
                    localStorage.setItem('theme', theme);
                    themeToggle.innerHTML = theme === 'dark' ? '<span class="emoji-icon">☀️</span>' : '<span class="emoji-icon">🌙</span>';
                });
            }

            // تحديد كل الإشعارات كمقروءة عبر زر صريح
            window.markAllNotifsRead = function() {
                fetch('<?php echo BASE_URL; ?>mark_notifications_read.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'csrf_token=<?php echo csrf_token(); ?>'
                }).then(res => res.json()).then(data => {
                    if (data.status === 'success') {
                        const badge = document.querySelector('#notifDropdown .badge');
                        if (badge) badge.style.display = 'none';
                        location.reload();
                    }
                }).catch(err => console.error(err));
            };
        });
    </script>
</head>
<body>

<?php 
global $lang; 
if (isLoggedIn()): 
    if (empty($_SESSION['full_name']) && !empty($_SESSION['employee_id'])) {
        $stmtEmpFallback = $pdo->prepare("SELECT full_name FROM employees WHERE id = ?");
        $stmtEmpFallback->execute([$_SESSION['employee_id']]);
        $empFallback = $stmtEmpFallback->fetch();
        if ($empFallback && !empty($empFallback['full_name'])) {
            $_SESSION['full_name'] = $empFallback['full_name'];
        }
    }
    
    $current_page = basename($_SERVER['PHP_SELF']);
    $current_dir = basename(dirname($_SERVER['PHP_SELF']));

    // User display name + avatar initials
    $user_display = $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'User';
    $initials = '';
    foreach (preg_split('/\s+/', trim($user_display)) as $word) {
        $initials .= mb_substr($word, 0, 1);
    }
    $initials = mb_strtoupper(mb_substr($initials, 0, 2));
    $user_role = $_SESSION['role'] ?? '';
    $role_label = in_array($user_role, ['super_admin', 'admin', 'manager', 'employee']) ? __('' . $user_role) : '';
?>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary sticky-top shadow app-navbar">
    <div class="container-fluid px-lg-4">
        <!-- Sidebar Toggler for Mobile -->
        <button class="navbar-toggler border-0 shadow-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebarMenu" aria-controls="sidebarMenu" aria-label="<?php echo __('menu'); ?>">
            <span class="navbar-toggler-icon"></span>
        </button>
        
        <a class="navbar-brand d-flex align-items-center gap-2 py-2 px-lg-2" href="<?php echo BASE_URL; ?>index.php">
            <span class="brand-mark d-flex align-items-center justify-content-center"><span class="emoji-icon">🏢</span></span>
            <span class="brand-text"><?php echo __('site_name'); ?></span>
        </a>

        <!-- Top Menu Toggler -->
        <button class="navbar-toggler border-0 shadow-none" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-label="<?php echo __('menu'); ?>">
            <span class="fs-4"><span class="emoji-icon">👤</span></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarNav">
            <?php if (hasRole('super_admin')): ?>
            <ul class="navbar-nav <?php echo __('dir') == 'rtl' ? 'ms-auto' : 'me-auto'; ?> mb-2 mb-lg-0">
                <li class="nav-item dropdown">
                    <a class="nav-link text-white fw-bold org-switch-btn d-flex align-items-center gap-2" href="#" id="orgSwitchDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <span class="emoji-icon">🏢</span>
                        <span class="d-inline-block text-truncate" style="max-width: 180px;"><?php
                            $current_org_id = $_SESSION['organization_id'] ?? null;
                            if ($current_org_id) {
                                $stmtSwitch = $pdo->prepare("SELECT name_ar, name_en FROM organizations WHERE id = ?");
                                $stmtSwitch->execute([$current_org_id]);
                                $activeOrg = $stmtSwitch->fetch();
                                echo $lang == 'en' ? h($activeOrg['name_en']) : h($activeOrg['name_ar']);
                            } else {
                                echo __('all_organizations');
                            }
                        ?></span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end shadow border-0 org-switch-menu" aria-labelledby="orgSwitchDropdown">
                        <li><h6 class="dropdown-header fw-bold small"><?php echo __('switch_org'); ?></h6></li>
                        <li><a class="dropdown-item small" href="?switch_org=0"><span class="emoji-icon">🌐</span> <?php echo __('all_organizations'); ?></a></li>
                        <li><hr class="dropdown-divider"></li>
                        <?php
                        $all_orgs = $pdo->query("SELECT id, name_ar, name_en FROM organizations ORDER BY id ASC")->fetchAll();
                        foreach ($all_orgs as $o):
                        ?>
                            <li><a class="dropdown-item small <?php echo $current_org_id == $o['id'] ? 'active' : ''; ?>" href="?switch_org=<?php echo $o['id']; ?>"><span class="emoji-icon">🏢</span> <?php echo $lang == 'en' ? h($o['name_en']) : h($o['name_ar']); ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                </li>
                <li class="nav-item d-flex align-items-center">
                    <a class="nav-link text-white fw-bold sa-panel-link px-3 mx-1" href="<?php echo BASE_URL; ?>superadmin/dashboard.php">
                        <span class="emoji-icon">🛡️</span> <span class="d-none d-xl-inline"><?php echo __('super_admin_title'); ?></span>
                    </a>
                </li>
            </ul>
            <?php endif; ?>

            <ul class="navbar-nav <?php echo __('dir') == 'rtl' ? 'me-auto' : 'ms-auto'; ?> align-items-lg-center mb-2 mb-lg-0">
                <!-- Theme Toggle -->
                <li class="nav-item d-flex align-items-center me-1">
                    <button id="themeToggle" type="button" class="icon-btn icon-btn-light" aria-label="<?php echo __('theme_toggle'); ?>"><span class="emoji-icon">🌙</span></button>
                </li>
                <!-- Notifications Dropdown -->
                <?php
                $notif_stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? AND is_read = 0 ORDER BY created_at DESC LIMIT 5");
                $notif_stmt->execute([$_SESSION['user_id']]);
                $unread_notifs = $notif_stmt->fetchAll();
                $unread_count = count($unread_notifs);
                ?>
                <li class="nav-item dropdown d-flex align-items-center me-1">
                    <a class="icon-btn icon-btn-light position-relative" href="#" id="notifDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="<?php echo __('notifications'); ?>">
                        <span class="emoji-icon">🔔</span>
                        <?php if ($unread_count > 0): ?>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger notif-badge">
                                <?php echo $unread_count > 9 ? '9+' : $unread_count; ?>
                            </span>
                        <?php endif; ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end shadow border-0 py-0 notif-menu" aria-labelledby="notifDropdown">
                        <li class="dropdown-header bg-light py-2 fw-bold text-dark border-bottom d-flex justify-content-between align-items-center">
                            <span class="small"><?php echo __('notifications'); ?></span>
                            <?php if ($unread_count > 0): ?>
                                <button type="button" class="btn btn-sm btn-link text-primary text-decoration-none p-0 fw-bold" onclick="markAllNotifsRead()"><?php echo __('mark_as_read'); ?></button>
                            <?php endif; ?>
                        </li>
                        <?php if ($unread_count == 0): ?>
                            <li class="p-3 text-center text-muted small"><?php echo __('no_notifications'); ?></li>
                        <?php else: ?>
                            <?php foreach ($unread_notifs as $n): ?>
                                <li class="border-bottom">
                                    <a class="dropdown-item p-3 small text-wrap" href="<?php echo BASE_URL; ?>notifications.php">
                                        <div class="text-wrap">
                                            <?php echo $lang == 'en' ? h($n['message_en']) : h($n['message_ar']); ?>
                                        </div>
                                        <div class="text-muted small mt-1"><?php echo $n['created_at']; ?></div>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                            <li class="p-2 text-center">
                                <a class="btn btn-sm btn-outline-primary w-100" href="<?php echo BASE_URL; ?>notifications.php"><?php echo __('view_all_notifications'); ?></a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </li>
                <!-- Language Switcher -->
                <li class="nav-item d-flex align-items-center me-1">
                    <a class="icon-btn icon-btn-light" href="?lang=<?php echo $lang == 'ar' ? 'en' : 'ar'; ?>" title="<?php echo __('language'); ?>" aria-label="<?php echo __('language'); ?>">
                        <span class="emoji-icon"><?php echo __('language'); ?></span>
                    </a>
                </li>
                <!-- User Dropdown -->
                <li class="nav-item dropdown d-flex align-items-center ms-lg-1">
                    <a class="nav-link d-flex align-items-center gap-2 text-white py-1" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="<?php echo __('my_profile'); ?>">
                        <span class="user-avatar d-flex align-items-center justify-content-center"><?php echo h($initials); ?></span>
                        <span class="d-none d-lg-flex flex-column lh-1 text-start">
                            <span class="fw-bold user-name text-truncate" style="max-width: 140px;"><?php echo h($user_display); ?></span>
                            <?php if ($role_label): ?>
                                <small class="opacity-75" style="font-size: 0.68rem;"><?php echo h($role_label); ?></small>
                            <?php endif; ?>
                        </span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end shadow border-0 user-menu" aria-labelledby="userDropdown">
                        <li>
                            <div class="dropdown-header px-3 py-2 border-bottom d-flex align-items-center gap-2">
                                <span class="user-avatar user-avatar-sm d-flex align-items-center justify-content-center"><?php echo h($initials); ?></span>
                                <div class="lh-1">
                                    <div class="fw-bold small"><?php echo h($user_display); ?></div>
                                    <?php if ($role_label): ?><div class="text-muted" style="font-size: 0.7rem;"><?php echo h($role_label); ?></div><?php endif; ?>
                                </div>
                            </div>
                        </li>
                        <?php if (hasRole('employee')): ?>
                            <li><a class="dropdown-item small py-2" href="<?php echo BASE_URL; ?>employees/view.php"><span class="emoji-icon">👤</span> <?php echo __('my_profile'); ?></a></li>
                        <?php endif; ?>
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
                </li>
            </ul>
        </div>
    </div>
</nav>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <nav id="sidebarMenu" class="col-md-3 col-lg-2 d-md-block sidebar offcanvas-md offcanvas-start shadow-sm" tabindex="-1">
            <div class="offcanvas-header bg-primary text-white d-md-none">
                <div class="offcanvas-title d-flex align-items-center gap-2">
                    <span class="brand-mark brand-mark-sm d-flex align-items-center justify-content-center"><span class="emoji-icon">🏢</span></span>
                    <span class="fw-bold"><?php echo __('site_name'); ?></span>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" data-bs-target="#sidebarMenu" aria-label="Close"></button>
            </div>
            <div class="offcanvas-body position-sticky pt-3">
                <ul class="nav flex-column w-100">

                    <li class="nav-section-label"><?php echo __('menu'); ?></li>

                    <li class="nav-item">
                        <a class="nav-link <?php echo $current_page == 'index.php' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>index.php">
                            <span class="nav-icon"><span class="emoji-icon">📊</span></span> <?php echo __('dashboard'); ?>
                        </a>
                    </li>

                    <?php if (hasRole('super_admin')): ?>
                    <li class="nav-item">
                        <a class="nav-link fw-bold text-warning <?php echo ($current_dir == 'superadmin' || $current_page == 'organizations.php' || $current_page == 'registration_control.php' || $current_page == 'organization_codes.php' || $current_page == 'system_settings.php' || $current_page == 'site_health.php') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>superadmin/dashboard.php">
                            <span class="nav-icon"><span class="emoji-icon">🛡️</span></span> <?php echo __('super_admin_title'); ?>
                        </a>
                    </li>
                <?php endif; ?>
                    
                    <?php if (hasRole(['admin', 'manager'])): ?>
                    <li class="nav-section-label mt-3"><?php echo __('employees'); ?></li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($current_dir == 'employees' && $current_page == 'list.php') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>employees/list.php">
                            <span class="nav-icon"><span class="emoji-icon">👥</span></span> <?php echo __('employees'); ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($current_dir == 'employees' && $current_page == 'team.php') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>employees/team.php">
                            <span class="nav-icon"><span class="emoji-icon">👤</span></span> <?php echo __('my_team'); ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($current_dir == 'employees' && $current_page == 'approvals.php') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>employees/approvals.php">
                            <span class="nav-icon"><span class="emoji-icon">✅</span></span> <?php echo __('approvals'); ?>
                        </a>
                    </li>
                    <li class="nav-section-label mt-3"><?php echo __('leaves'); ?></li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($current_dir == 'leaves' && $current_page == 'manage.php') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>leaves/manage.php">
                            <span class="nav-icon"><span class="emoji-icon">📅</span></span> <?php echo __('leaves'); ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($current_dir == 'leaves' && $current_page == 'reports.php') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>leaves/reports.php">
                            <span class="nav-icon"><span class="emoji-icon">📋</span></span> <?php echo __('leave_reports'); ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($current_dir == 'leaves' && $current_page == 'calendar.php') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>leaves/calendar.php">
                            <span class="nav-icon"><span class="emoji-icon">🗓️</span></span> <?php echo __('leave_calendar'); ?>
                        </a>
                    </li>
                    <?php endif; ?>

                    <?php if (hasRole('employee')): ?>
                    <li class="nav-section-label mt-3"><?php echo __('my_account'); ?></li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($current_dir == 'employees' && ($current_page == 'view.php' || $current_page == 'my_profile_edit.php')) ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>employees/view.php">
                            <span class="nav-icon"><span class="emoji-icon">👤</span></span> <?php echo __('my_profile'); ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($current_dir == 'leaves' && $current_page == 'my_requests.php') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>leaves/my_requests.php">
                            <span class="nav-icon"><span class="emoji-icon">📋</span></span> <?php echo __('my_requests'); ?>
                        </a>
                    </li>
                    <?php endif; ?>

                    <?php if (hasRole(['employee', 'admin', 'manager'])): ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $current_page == 'notifications.php' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>notifications.php">
                            <span class="nav-icon"><span class="emoji-icon">🔔</span></span> <?php echo __('notifications_page'); ?>
                        </a>
                    </li>
                    <?php endif; ?>

                    <?php if (hasRole('admin')): ?>
                    <li class="nav-section-label mt-3"><?php echo __('system_settings'); ?></li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($current_dir == 'admin' && $current_page == 'users.php') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>admin/users.php">
                            <span class="nav-icon"><span class="emoji-icon">👤</span></span> <?php echo __('system_users'); ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($current_dir == 'admin' && $current_page == 'departments.php') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>admin/departments.php">
                            <span class="nav-icon"><span class="emoji-icon">🏢</span></span> <?php echo __('departments'); ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($current_dir == 'admin' && $current_page == 'leave_types.php') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>admin/leave_types.php">
                            <span class="nav-icon"><span class="emoji-icon">📋</span></span> <?php echo __('leave_types'); ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($current_dir == 'admin' && $current_page == 'holidays.php') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>admin/holidays.php">
                            <span class="nav-icon"><span class="emoji-icon">🎉</span></span> <?php echo __('holidays'); ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($current_dir == 'admin' && $current_page == 'activity_log.php') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>admin/activity_log.php">
                            <span class="nav-icon"><span class="emoji-icon">📜</span></span> <?php echo __('activity_log'); ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($current_dir == 'admin' && $current_page == 'settings.php') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>admin/settings.php">
                            <span class="nav-icon"><span class="emoji-icon">⚙️</span></span> <?php echo __('settings'); ?>
                        </a>
                    </li>
                    <?php endif; ?>

                    <li class="nav-section-label mt-3"><?php echo __('my_account'); ?></li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($current_dir == 'auth' && $current_page == 'security.php') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>auth/security.php">
                            <span class="nav-icon"><span class="emoji-icon">🔑</span></span> <?php echo __('security_settings'); ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <form action="<?php echo BASE_URL; ?>auth/logout.php" method="POST" class="m-0">
                            <?php echo csrf_field(); ?>
                            <button type="submit" class="nav-link text-danger border-0 bg-transparent w-100 text-start px-3 py-2" style="font-size: 1rem;">
                                <span class="nav-icon"><span class="emoji-icon">🚪</span></span> <?php echo __('logout'); ?>
                            </button>
                        </form>
                    </li>
                </ul>
            </div>
        </nav>

        <!-- Main Content -->
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
<?php else: ?>
    <!-- Language Switcher & Theme Toggle for Login Page -->
    <div class="position-absolute top-0 end-0 p-3 d-flex align-items-center gap-2" style="z-index: 1050;">
        <button id="themeToggle" type="button" class="btn border-0 p-1 fs-5 lh-1 bg-transparent" title="<?php echo __('settings'); ?>" aria-label="<?php echo __('theme_toggle'); ?>"><span class="emoji-icon">🌙</span></button>
        <a class="text-decoration-none p-1 fs-5 lh-1" href="?lang=<?php echo $lang == 'ar' ? 'en' : 'ar'; ?>" title="<?php echo __('language'); ?>">
            <span class="emoji-icon"><?php echo __('language'); ?></span>
        </a>
    </div>
<?php endif; ?>
