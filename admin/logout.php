<?php
// ۱. شروع بافر خروجی جهت جلوگیری از خطای Headers already sent
ob_start();

// ۲. فراخوانی فایل auth جهت اطمینان از استارت بودن session_start()
require_once __DIR__ . '/auth.php';

// ۳. تخلیه کامل متغیرهای آرایه Session
$_SESSION = [];

// ۴. باطل کردن کوکی Session در مرورگر کاربر (Cookie Invalidation)
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// ۵. نابود کردن کامل سشن در سمت سرور
session_destroy();

// ۶. ارسال هدرهای ضد کش (Anti-Cache) تا با زدن دکمه بازگشت مرورگر صفحه لاگین‌شده دیده نشود
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// ۷. هدایت قطعی به صفحه لاگین با پیام موفقیت
header('Location: login.php?logged_out=1');
exit;