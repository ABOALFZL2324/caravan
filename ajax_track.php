<?php
require_once 'db.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'درخواست نامعتبر است.']);
    exit;
}

$national_code = safe($_POST['national_code'] ?? '');

if (empty($national_code) || strlen($national_code) !== 10 || !ctype_digit($national_code)) {
    echo json_encode(['success' => false, 'message' => 'لطفاً یک کد ملی معتبر (۱۰ رقمی) وارد کنید.']);
    exit;
}

// جستجوی سوابق ثبت‌نام با کدملی
$stmt = $conn->prepare("SELECT pilgrims.*, 
                               caravans.title AS caravan_title, 
                               caravans.destination, 
                               caravans.trip_type, 
                               caravans.start_date, 
                               caravans.end_date 
                        FROM pilgrims 
                        JOIN caravans ON pilgrims.caravan_id = caravans.id 
                        WHERE pilgrims.national_code = ? 
                        ORDER BY pilgrims.id DESC");
$stmt->execute([$national_code]);
$records = $stmt->fetchAll();

if (empty($records)) {
    echo json_encode([
        'success' => false, 
        'message' => 'هیچ ثبت‌نامی با این کد ملی یافت نشد. لطفاً از صحت کدملی اطمینان حاصل کنید یا با پشتیبانی تماس بگیرید.'
    ]);
    exit;
}

// آماده‌سازی خروجی با تبدیل وضعیت‌ها به متن و رنگ استاندارد
$results = [];
foreach ($records as $r) {
    $status_label = 'در انتظار بررسی مدارک';
    $status_badge = 'bg-amber-100 text-amber-800 border-amber-300';
    $status_desc = 'مدارک شما ثبت شده و توسط خادمین مجموعه در حال بررسی است.';

    if ($r['status'] === 'verified') {
        $status_label = 'تأیید نهایی شده';
        $status_badge = 'bg-emerald-100 text-emerald-800 border-emerald-300';
        $status_desc = 'ثبت‌نام و مدارک شما تأیید گردید. جایگاه شما در کاروان قطعی است.';
    } elseif ($r['status'] === 'rejected') {
        $status_label = 'نیاز به اصلاح مدارک / رد شده';
        $status_badge = 'bg-red-100 text-red-800 border-red-300';
        $status_desc = 'مدارک هویتی شما مورد تایید قرار نگرفت یا ناخوانا بود. لطفاً با پشتیبانی ایتا تماس بگیرید.';
    } elseif ($r['status'] === 'cancelled') {
        $status_label = 'کنسل‌شده';
        $status_badge = 'bg-slate-100 text-slate-600 border-slate-300';
        $status_desc = 'این ثبت‌نام بنا به درخواست یا شرایط سفر لغو شده است.';
    }

    $results[] = [
        'id' => $r['id'],
        'fullname' => $r['fullname'],
        'caravan_title' => $r['caravan_title'],
        'destination' => $r['destination'],
        'trip_type' => $r['trip_type'],
        'start_date' => $r['start_date'],
        'end_date' => $r['end_date'] ?: 'مشخص نشده',
        'final_price' => number_format($r['final_price']) . ' تومان',
        'status_label' => $status_label,
        'status_badge' => $status_badge,
        'status_desc' => $status_desc,
        'has_passport' => !empty($r['passport_number']) ? $r['passport_number'] : 'ثبت نشده',
        'registered_date' => substr($r['created_at'], 0, 10)
    ];
}

echo json_encode(['success' => true, 'records' => $results]);