<?php
require_once '../includes/config.php';

if ($_SESSION['role'] !== 'super_admin') {
    redirect('index.php');
}

redirect('../superadmin/dashboard.php');
