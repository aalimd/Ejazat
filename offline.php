<?php
require_once 'includes/config.php';
$primary_hex = getSetting('primary_color', '#0d6efd');
list($r, $g, $b) = sscanf($primary_hex, "#%02x%02x%02x");
$primary_rgb = "$r, $g, $b";
$site_name = SITE_NAME;
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>" dir="<?php echo $lang == 'ar' ? 'rtl' : 'ltr'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Offline - <?php echo h($site_name); ?></title>
    <!-- Bootstrap CSS -->
    <?php if ($lang == 'ar'): ?>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css">
    <?php else: ?>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <?php endif; ?>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: <?php echo $primary_hex; ?>;
            --primary-color-rgb: <?php echo $primary_rgb; ?>;
            --bg-color: #f4f7fa;
            --card-bg: #ffffff;
            --text-main: #2b3440;
            --text-muted: #6c757d;
        }
        
        [data-theme="dark"] {
            --bg-color: #0f172a;
            --card-bg: #1e293b;
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
        }

        body {
            background-color: var(--bg-color);
            color: var(--text-main);
            font-family: <?php echo $lang == 'ar' ? "'Cairo', sans-serif" : "'Inter', sans-serif"; ?>;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            transition: background-color 0.3s ease;
        }

        .offline-card {
            background: var(--card-bg);
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.05);
            max-width: 500px;
            width: 100%;
            text-align: center;
            padding: 40px 30px;
            border: 1px solid rgba(0,0,0,0.03);
            transition: all 0.3s ease;
        }

        [data-theme="dark"] .offline-card {
            box-shadow: 0 15px 35px rgba(0,0,0,0.3);
            border: 1px solid #334155;
        }

        .offline-icon {
            font-size: 5rem;
            color: var(--primary-color);
            margin-bottom: 24px;
            animation: pulse 2s infinite ease-in-out;
        }

        .btn-retry {
            background: linear-gradient(135deg, var(--primary-color), #0a58ca) !important;
            border: none !important;
            box-shadow: 0 4px 12px rgba(var(--primary-color-rgb), 0.2);
            color: #fff !important;
            font-weight: 600;
            padding: 12px 30px;
            border-radius: 8px;
            transition: all 0.2s ease;
        }

        .btn-retry:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(var(--primary-color-rgb), 0.3);
        }

        @keyframes pulse {
            0% { transform: scale(1); opacity: 0.9; }
            50% { transform: scale(1.05); opacity: 1; filter: drop-shadow(0 0 15px rgba(var(--primary-color-rgb), 0.4)); }
            100% { transform: scale(1); opacity: 0.9; }
        }
    </style>
    <script>
        // Apply saved theme
        const currentTheme = localStorage.getItem('theme') || 'light';
        if (currentTheme === 'dark') {
            document.documentElement.setAttribute('data-theme', 'dark');
        }

        // Auto-refresh when back online
        window.addEventListener('online', () => {
            window.location.reload();
        });

        function checkConnection() {
            const button = document.getElementById('retryBtn');
            const spinner = document.getElementById('spinner');
            
            button.classList.add('disabled');
            spinner.classList.remove('d-none');
            
            // Try to fetch a small resources to check connection
            fetch('<?php echo BASE_URL; ?>assets/images/icon-192.png', { mode: 'no-cors', cache: 'no-store' })
                .then(() => {
                    window.location.reload();
                })
                .catch(() => {
                    setTimeout(() => {
                        button.classList.remove('disabled');
                        spinner.classList.add('d-none');
                        alert(document.documentElement.lang === 'ar' ? 'ما زلت غير متصل بالإنترنت.' : 'You are still offline.');
                    }, 1000);
                });
        }
    </script>
</head>
<body>

    <div class="offline-card">
        <div class="offline-icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="96" height="96" fill="currentColor" class="bi bi-wifi-off" viewBox="0 0 16 16">
                <path d="M10.706 3.294A12.54 12.54 0 0 0 8 3C5.259 3 2.723 3.882.663 5.379a.485.485 0 0 0-.048.736.518.518 0 0 0 .668.05A11.448 11.448 0 0 1 8 5c.63 0 1.249.05 1.852.148l.854-.854zM8 6c-1.905 0-3.68.56-5.166 1.526a.48.48 0 0 0-.063.745.525.525 0 0 0 .652.065 8.448 8.448 0 0 1 9.094-.065.525.525 0 0 0 .652-.065.48.48 0 0 0-.063-.745A9.455 9.455 0 0 0 8 6zm0 3c-1.178 0-2.29.336-3.238.921a.48.48 0 0 0-.077.758.528.528 0 0 0 .647.078 6.448 6.448 0 0 1 5.336 0 .528.528 0 0 0 .647-.078.48.48 0 0 0-.077-.758A7.447 7.447 0 0 0 8 9zm0 3a1.5 1.5 0 1 0 0 3 1.5 1.5 0 0 0 0-3zm4.707-9.293a.5.5 0 0 0-.707 0L1.136 12.864a.5.5 0 1 0 .708.708l10.863-10.863a.5.5 0 0 0 0-.708z"/>
            </svg>
        </div>
        
        <?php if ($lang == 'ar'): ?>
            <h2 class="fw-bold mb-3">أنت غير متصل بالإنترنت</h2>
            <p class="text-muted mb-4">يبدو أنك تواجه مشكلات في الاتصال بالشبكة. يمكنك محاولة إعادة التحميل بمجرد عودة الاتصال.</p>
            <button id="retryBtn" onclick="checkConnection()" class="btn btn-retry btn-lg w-100">
                <span id="spinner" class="spinner-border spinner-border-sm me-2 d-none" role="status" aria-hidden="true"></span>
                إعادة المحاولة
            </button>
        <?php else: ?>
            <h2 class="fw-bold mb-3">You are offline</h2>
            <p class="text-muted mb-4">It looks like you are experiencing network connectivity issues. You can try reloading once you are back online.</p>
            <button id="retryBtn" onclick="checkConnection()" class="btn btn-retry btn-lg w-100">
                <span id="spinner" class="spinner-border spinner-border-sm me-2 d-none" role="status" aria-hidden="true"></span>
                Try Again
            </button>
        <?php endif; ?>
        
        <div class="mt-4 pt-3 border-top">
            <small class="text-muted"><?php echo h($site_name); ?> PWA Mode</small>
        </div>
    </div>

</body>
</html>
