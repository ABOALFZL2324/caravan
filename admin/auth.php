<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function is_logged_in() {
    return isset($_SESSION['admin_id']) && (int)$_SESSION['admin_id'] > 0;
}

function has_role($roles) {
    if (!is_logged_in()) return false;
    $current_role = $_SESSION['admin_role'] ?? '';
    if ($current_role === 'super_admin') return true;
    if (is_array($roles)) return in_array($current_role, $roles);
    return $current_role === $roles;
}

function require_roles($roles) {
    if (!is_logged_in()) {
        header('Location: login.php');
        exit;
    }
    if (!has_role($roles)) {
        die('<div style="direction:rtl;font-family:Tahoma;padding:30px;color:red;text-align:center;">شما دسترسی لازم برای مشاهده این بخش را ندارید. <a href="dashboard.php">بازگشت</a></div>');
    }
}