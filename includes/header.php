<!DOCTYPE html>
<html lang="<?php echo __('lang_code'); ?>" dir="<?php echo __('dir'); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php
    $primary_hex = getSetting('primary_color', '#0d6efd');
    // تحويل Hex إلى RGB للاستخدام في CSS (مثلاً للشفافية)
    list($r, $g, $b) = sscanf($primary_hex, "#%02x%02x%02x");
    $primary_rgb = "$r, $g, $b";
    ?>
    <title><?php echo isset($pageTitle) ? $pageTitle . " - " . __('site_name') : __('site_name'); ?></title>
    
    <!-- PWA Settings & Manifest -->
    <link rel="manifest" href="<?php echo BASE_URL; ?>manifest.php">
    <meta name="theme-color" content="<?php echo $primary_hex; ?>">
    
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

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&family=Inter:wght@400;600;700&family=Almarai:wght@400;700&family=Tajawal:wght@400;700&family=Roboto:wght@400;700&family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">

    <!-- Custom Modern Styles -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/style.css?v=3">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

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
        const currentTheme = localStorage.getItem('theme') || 'light';
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

            // إخفاء الإشعارات وتحديدها كمقروءة عند النقر
            const notifDropdown = document.getElementById('notifDropdown');
            if (notifDropdown) {
                notifDropdown.addEventListener('show.bs.dropdown', () => {
                    const badge = notifDropdown.querySelector('.badge');
                    if (badge && badge.style.display !== 'none') {
                        badge.style.display = 'none';
                        fetch('<?php echo BASE_URL; ?>mark_notifications_read.php', { method: 'POST' })
                            .catch(err => console.error(err));
                    }
                });
            }
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
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary sticky-top shadow">
    <div class="container-fluid">
        <!-- Sidebar Toggler for Mobile -->
        <button class="navbar-toggler border-0 shadow-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebarMenu" aria-controls="sidebarMenu">
            <span class="navbar-toggler-icon"></span>
        </button>
        
        <a class="navbar-brand fw-bold px-lg-3" href="<?php echo BASE_URL; ?>index.php">
            <?php echo __('site_name'); ?>
         </a>

        <!-- Top Menu Toggler -->
        <button class="navbar-toggler border-0 shadow-none" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="fs-4"><span class="emoji-icon">👤</span></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav <?php echo __('dir') == 'rtl' ? 'ms-auto' : 'me-auto'; ?> mb-2 mb-lg-0">
                <li class="nav-item">
                    <span class="nav-link text-white">
                        <?php echo __('welcome'); ?>, <?php echo h($_SESSION['full_name'] ?? $_SESSION['username']); ?>
                    </span>
                </li>
                <?php if (hasRole('super_admin')): ?>
                <li class="nav-item dropdown ms-3 me-3">
                    <a class="nav-link dropdown-toggle text-white fw-bold bg-dark bg-opacity-25 rounded px-3" href="#" id="orgSwitchDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <span class="emoji-icon">🏢</span> <?php 
                            $current_org_id = $_SESSION['organization_id'] ?? null;
                            if ($current_org_id) {
                                $stmtSwitch = $pdo->prepare("SELECT name_ar, name_en FROM organizations WHERE id = ?");
                                $stmtSwitch->execute([$current_org_id]);
                                $activeOrg = $stmtSwitch->fetch();
                                echo $lang == 'en' ? h($activeOrg['name_en']) : h($activeOrg['name_ar']);
                            } else {
                                echo __('all_organizations');
                            }
                        ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end shadow border-0" aria-labelledby="orgSwitchDropdown">
                        <li><a class="dropdown-item small" href="?switch_org=0"><span class="emoji-icon">🌐</span> <?php echo __('all_organizations'); ?></a></li>
                        <li><hr class="dropdown-divider"></li>
                        <?php
                        $all_orgs = $pdo->query("SELECT id, name_ar, name_en FROM organizations")->fetchAll();
                        foreach ($all_orgs as $o):
                        ?>
                            <li><a class="dropdown-item small" href="?switch_org=<?php echo $o['id']; ?>"><span class="emoji-icon">🏢</span> <?php echo $lang == 'en' ? h($o['name_en']) : h($o['name_ar']); ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                </li>
                <li class="nav-item">
                    <a class="nav-link text-white fw-bold bg-danger bg-opacity-75 rounded px-3 ms-2" href="<?php echo BASE_URL; ?>superadmin/dashboard.php" title="Super Admin Control Panel">
                        <span class="emoji-icon">🔐</span> Control Panel
                    </a>
                </li>
                <?php endif; ?>
            </ul>
            <ul class="navbar-nav mb-2 mb-lg-0">
                <!-- Theme Toggle -->
                <li class="nav-item me-2 d-flex align-items-center">
                    <button id="themeToggle" class="btn btn-sm btn-outline-light border-0 fs-5"><span class="emoji-icon">🌙</span></button>
                </li>
                <!-- Notifications Dropdown -->
                <?php
                $notif_stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? AND is_read = 0 ORDER BY created_at DESC LIMIT 5");
                $notif_stmt->execute([$_SESSION['user_id']]);
                $unread_notifs = $notif_stmt->fetchAll();
                $unread_count = count($unread_notifs);
                ?>
                <li class="nav-item dropdown me-2">
                    <a class="nav-link text-white position-relative" href="#" id="notifDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <?php echo __('notifications'); ?>
                        <?php if ($unread_count > 0): ?>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.6rem;">
                                <?php echo $unread_count; ?>
                            </span>
                        <?php endif; ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end shadow border-0 py-0" aria-labelledby="notifDropdown" style="width: 300px; max-height: 400px; overflow-y: auto;">
                        <li class="dropdown-header bg-light py-2 fw-bold text-dark border-bottom">
                            <?php echo __('notifications'); ?>
                        </li>
                        <?php if ($unread_count == 0): ?>
                            <li class="p-3 text-center text-muted small"><?php echo __('no_notifications'); ?></li>
                        <?php else: ?>
                            <?php foreach ($unread_notifs as $n): ?>
                                <li class="border-bottom">
                                    <a class="dropdown-item p-3 small white-space-normal" href="#">
                                        <div class="text-wrap">
                                            <?php echo $lang == 'en' ? h($n['message_en']) : h($n['message_ar']); ?>
                                        </div>
                                        <div class="text-muted x-small mt-1"><?php echo $n['created_at']; ?></div>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                </li>

                <li class="nav-item">
                    <a class="nav-link text-white fw-bold px-3" href="?lang=<?php echo $lang == 'ar' ? 'en' : 'ar'; ?>">
                        <?php echo __('language'); ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link text-white px-3" href="<?php echo BASE_URL; ?>auth/logout.php">
                        <?php echo __('logout'); ?>
                    </a>
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
                <h5 class="offcanvas-title fw-bold"><?php echo __('site_name'); ?></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" data-bs-target="#sidebarMenu" aria-label="Close"></button>
            </div>
            <div class="offcanvas-body position-sticky pt-3">
                <ul class="nav flex-column w-100">
                    <li class="nav-item">
                        <a class="nav-link <?php echo $current_page == 'index.php' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>index.php">
                            <span class="emoji-icon">📊</span> <?php echo __('dashboard'); ?>
                        </a>
                    </li>

                    <?php if (hasRole('super_admin')): ?>
                    <li class="nav-item">
                        <a class="nav-link fw-bold text-warning" href="<?php echo BASE_URL; ?>superadmin/dashboard.php">
                            <span class="emoji-icon">🛡️</span> <?php echo __('super_admin_title'); ?>
                        </a>
                    </li>
                <?php endif; ?>
                    
                    <?php if (hasRole(['admin', 'manager'])): ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($current_dir == 'employees' && $current_page == 'list.php') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>employees/list.php">
                            <span class="emoji-icon">👥</span> <?php echo __('employees'); ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($current_dir == 'employees' && $current_page == 'approvals.php') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>employees/approvals.php">
                            <span class="emoji-icon">✅</span> <?php echo __('approvals'); ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($current_dir == 'leaves' && $current_page == 'manage.php') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>leaves/manage.php">
                            <span class="emoji-icon">📅</span> <?php echo __('leaves'); ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($current_dir == 'leaves' && $current_page == 'reports.php') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>leaves/reports.php">
                            <span class="emoji-icon">📋</span> <?php echo __('leave_reports'); ?>
                        </a>
                    </li>
                    <?php endif; ?>

                    <?php if (hasRole('employee')): ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($current_dir == 'employees' && $current_page == 'view.php') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>employees/view.php">
                            <span class="emoji-icon">👤</span> <?php echo __('my_profile'); ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($current_dir == 'leaves' && $current_page == 'my_requests.php') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>leaves/my_requests.php">
                            <span class="emoji-icon">📋</span> <?php echo __('my_requests'); ?>
                        </a>
                    </li>
                    <?php endif; ?>

                    <?php if (hasRole('admin')): ?>
                    <li class="nav-item mt-3 border-top pt-2">
                        <span class="px-3 text-muted small fw-bold"><?php echo __('system_users'); ?></span>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($current_dir == 'admin' && $current_page == 'users.php') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>admin/users.php">
                            <span class="emoji-icon">👤</span> <?php echo __('system_users'); ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($current_dir == 'admin' && $current_page == 'departments.php') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>admin/departments.php">
                            <span class="emoji-icon">🏢</span> <?php echo __('departments'); ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($current_dir == 'admin' && $current_page == 'leave_types.php') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>admin/leave_types.php">
                            <span class="emoji-icon">📋</span> <?php echo __('leave_types'); ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($current_dir == 'admin' && $current_page == 'holidays.php') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>admin/holidays.php">
                            <span class="emoji-icon">🎉</span> <?php echo __('holidays'); ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($current_dir == 'admin' && $current_page == 'activity_log.php') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>admin/activity_log.php">
                            <span class="emoji-icon">📜</span> <?php echo __('activity_log'); ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($current_dir == 'admin' && $current_page == 'settings.php') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>admin/settings.php">
                            <span class="emoji-icon">⚙️</span> <?php echo __('settings'); ?>
                        </a>
                    </li>
                    <?php endif; ?>

                    <li class="nav-item mt-3 border-top pt-2">
                        <a class="nav-link <?php echo ($current_dir == 'auth' && $current_page == 'security.php') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>auth/security.php">
                            <span class="emoji-icon">🔑</span> <?php echo __('security_settings'); ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-danger" href="<?php echo BASE_URL; ?>auth/logout.php">
                            <span class="emoji-icon">🚪</span> <?php echo __('logout'); ?>
                        </a>
                    </li>
                </ul>
            </div>
        </nav>

        <!-- Main Content -->
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
<?php else: ?>
    <!-- Language Switcher & Theme Toggle for Login Page -->
    <div class="p-3 d-flex justify-content-end align-items-center gap-2">
        <button id="themeToggle" class="btn btn-outline-primary btn-sm fs-5"><span class="emoji-icon">🌙</span></button>
        <a class="btn btn-outline-primary btn-sm" href="?lang=<?php echo $lang == 'ar' ? 'en' : 'ar'; ?>">
            <?php echo __('language'); ?>
        </a>
    </div>
<?php endif; ?>
