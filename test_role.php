<?php
session_start();
$_SESSION['user_id'] = 5;
$_SESSION['role'] = 'super_admin';
$_SESSION['organization_id'] = 1;

function isLoggedIn() { return true; }

function hasRole($roles) {
    if (!isLoggedIn()) return false;
    
    $user_role = $_SESSION['role'];
    $is_acting_as_admin = ($user_role === 'super_admin' && !empty($_SESSION['organization_id']));
    
    if (is_array($roles)) {
        if ($is_acting_as_admin && in_array('admin', $roles)) return true;
        return in_array($user_role, $roles);
    }
    
    if ($is_acting_as_admin && $roles === 'admin') return true;
    return $user_role === $roles;
}

echo "is super_admin? " . (hasRole('super_admin') ? 'Yes' : 'No') . "\n";
echo "is admin? " . (hasRole('admin') ? 'Yes' : 'No') . "\n";
