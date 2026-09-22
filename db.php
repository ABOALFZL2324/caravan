<?php
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'caravan_db';

try {
    $conn = new PDO("mysql:host={$db_host};dbname={$db_name};charset=utf8mb4", $db_user, $db_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
} catch (PDOException $e) {
    die('<div style="direction:rtl;font-family:Tahoma;padding:20px;color:red;">خطا در اتصال به پایگاه داده: ' . $e->getMessage() . '</div>');
}

// تابع پالایش و پاکسازی ورودی‌ها
function safe($data) {
    if ($data === null) return '';
    return htmlspecialchars(trim((string)$data), ENT_QUOTES, 'UTF-8');
}

// دریافت آرایه تنظیمات سراسری سیستم
function get_settings($conn) {
    $settings = [];
    try {
        $stmt = $conn->query("SELECT setting_key, setting_value FROM settings");
        while ($row = $stmt->fetch()) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
    } catch (Exception $e) {}
    return $settings;
}