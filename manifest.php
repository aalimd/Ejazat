<?php
header("Content-Type: application/manifest+json; charset=utf-8");
require_once 'includes/config.php';

$theme_color = getSetting('primary_color', '#0d6efd');
$site_name = SITE_NAME;
$short_name = ($lang == 'en') ? getSetting('site_short_name_en', 'Ejazat') : getSetting('site_short_name_ar', 'إجازات');
$base_url = BASE_URL;

$manifest = [
    "name" => $site_name,
    "short_name" => $short_name,
    "description" => ($lang == 'en') ? "Professional HR and Leave Management System" : "نظام إجازات لإدارة الموظفين والمستندات بأسلوب عصري",
    "start_url" => $base_url . "index.php?utm_source=pwa",
    "display" => "standalone",
    "background_color" => "#f4f7fa",
    "theme_color" => $theme_color,
    "orientation" => "portrait-primary",
    "categories" => ["business", "productivity"],
    "icons" => [
        [
            "src" => $base_url . "assets/images/icon-192.png",
            "type" => "image/png",
            "sizes" => "192x192",
            "purpose" => "any maskable"
        ],
        [
            "src" => $base_url . "assets/images/icon-512.png",
            "type" => "image/png",
            "sizes" => "512x512",
            "purpose" => "any maskable"
        ],
        [
            "src" => $base_url . "assets/images/icon.svg",
            "type" => "image/svg+xml",
            "sizes" => "any",
            "purpose" => "any maskable"
        ]
    ],
    "shortcuts" => [
        [
            "name" => ($lang == 'en') ? "New Leave Request" : "طلب إجازة جديد",
            "short_name" => ($lang == 'en') ? "New Request" : "طلب جديد",
            "url" => $base_url . "leaves/my_requests.php?action=new",
            "icons" => [
                [
                    "src" => $base_url . "assets/images/icon-192.png",
                    "sizes" => "192x192",
                    "type" => "image/png"
                ]
            ]
        ]
    ]
];

echo json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
