<?php
ob_start();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/auth.php';

require_roles(['super_admin', 'registrar', 'leader']);

$settings = get_settings($conn);

$selected_caravan_id = (int)($_GET['caravan_id'] ?? 0);
$export_format = safe($_GET['format'] ?? ''); // 'print', 'xls', 'json'
$lang = safe($_GET['lang'] ?? 'fa'); // 'fa', 'ar', 'en'

// واژه‌نامه چندزبانه مانیفست
$dict = [
    'fa' => [
        'dir' => 'rtl',
        'title' => 'مانیفست رسمی زائران عتبات عالیات',
        'subtitle' => 'صورت‌جلسه رسمی اعزام مسافران تحت نظارت کاروان',
        'caravan' => 'نام کاروان',
        'destination' => 'مقصد سفر',
        'departure_date' => 'تاریخ اعزام',
        'departure_time' => 'ساعت حرکت',
        'total_pilgrims' => 'تعداد مسافران نهایی',
        'row' => 'ردیف',
        'fullname' => 'نام و نام خانوادگی',
        'national_code' => 'کد ملی',
        'passport_no' => 'شماره گذرنامه',
        'passport_exp' => 'تاریخ انقضای گذرنامه',
        'phone' => 'شماره همراه',
        'emergency_phone' => 'تماس اضطراری',
        'gender' => 'جنسیت',
        'age_group' => 'رده سنی',
        'male' => 'مرد',
        'female' => 'زن',
        'adult' => 'بزرگسال',
        'child' => 'کودک',
        'infant' => 'نوزاد',
        'medical_notes' => 'ملاحظات پزشکی',
        'health' => 'سالم',
        'sign_leader' => 'مهر و امضای سرپرست کاروان:',
        'sign_hotel' => 'تأییدیه هتل و پذیرش عراق:',
        'sign_agency' => 'مهر و امضای دفتر اعزام:',
        'empty' => 'هیچ زائر تاییدشده‌ای برای این کاروان ثبت نشده است.'
    ],
    'ar' => [
        'dir' => 'rtl',
        'title' => 'قائمة الحجاج والمعتمرين الرسمية (المانيفست)',
        'subtitle' => 'كشف أسماء زوار العتبات المقدسة في جمهورية العراق',
        'caravan' => 'اسم الحملة / القافلة',
        'destination' => 'الوجهة المقدسة',
        'departure_date' => 'تاريخ الانطلاق',
        'departure_time' => 'وقت الحركة',
        'total_pilgrims' => 'العدد الكلي للزوار',
        'row' => 'ت',
        'fullname' => 'الاسم الثلاثي واللقب',
        'national_code' => 'الرقم الوطني',
        'passport_no' => 'رقم جواز السفر',
        'passport_exp' => 'تاريخ النفاذ',
        'phone' => 'رقم الهاتف',
        'emergency_phone' => 'هاتف الطوارئ',
        'gender' => 'الجنس',
        'age_group' => 'الفئة العمرية',
        'male' => 'ذكر',
        'female' => 'أنثى',
        'adult' => 'بالغ',
        'child' => 'طفل',
        'infant' => 'رضيع',
        'medical_notes' => 'الحالة الصحية',
        'health' => 'سليم',
        'sign_leader' => 'توقيع ومدير القافلة:',
        'sign_hotel' => 'ختم إدارة الفندق في العراق:',
        'sign_agency' => 'ختم الشركة المعتمدة:',
        'empty' => 'لا يوجد زوار مؤكدين في هذه القافلة.'
    ],
    'en' => [
        'dir' => 'ltr',
        'title' => 'OFFICIAL PILGRIM MANIFEST',
        'subtitle' => 'Official Passenger List for Holy Shrine Pilgrimage Tour',
        'caravan' => 'Caravan Title',
        'destination' => 'Destination',
        'departure_date' => 'Departure Date',
        'departure_time' => 'Departure Time',
        'total_pilgrims' => 'Total Pilgrims',
        'row' => 'No.',
        'fullname' => 'Full Name',
        'national_code' => 'National ID',
        'passport_no' => 'Passport No.',
        'passport_exp' => 'Expiry Date',
        'phone' => 'Mobile Phone',
        'emergency_phone' => 'Emergency Contact',
        'gender' => 'Gender',
        'age_group' => 'Age Group',
        'male' => 'Male',
        'female' => 'Female',
        'adult' => 'Adult',
        'child' => 'Child',
        'infant' => 'Infant',
        'medical_notes' => 'Medical Notes',
        'health' => 'Healthy',
        'sign_leader' => 'Tour Leader Signature:',
        'sign_hotel' => 'Hotel Reception Stamp:',
        'sign_agency' => 'Official Agency Stamp:',
        'empty' => 'No verified pilgrims found for this caravan.'
    ]
];

$L = $dict[$lang] ?? $dict['fa'];

// واکشی داده‌های کاروان و زائران در صورت انتخاب
if ($selected_caravan_id > 0 && !empty($export_format)) {
    $stmt_c = $conn->prepare("SELECT * FROM caravans WHERE id = ?");
    $stmt_c->execute([$selected_caravan_id]);
    $caravan = $stmt_c->fetch();

    if (!$caravan) {
        die('کاروان مورد نظر یافت نشد.');
    }

    $stmt_p = $conn->prepare("SELECT * FROM pilgrims WHERE caravan_id = ? AND status = 'verified' ORDER BY id ASC");
    $stmt_p->execute([$selected_caravan_id]);
    $pilgrims = $stmt_p->fetchAll();

    // ۱. خروجی چاپی مانیفست رسمی (A4 افقی / Landscape)
    if ($export_format === 'print') {
        ?>
        <!DOCTYPE html>
        <html lang="<?= $lang ?>" dir="<?= $L['dir'] ?>">
        <head>
            <meta charset="UTF-8">
            <title><?= $L['title'] ?> - <?= safe($caravan['title']) ?></title>
            <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet">
            <style>
                @page { size: A4 landscape; margin: 10mm; }
                body {
                    font-family: 'Vazirmatn', Tahoma, sans-serif;
                    direction: <?= $L['dir'] ?>;
                    font-size: 11px;
                    color: #000;
                    margin: 0;
                    padding: 10px;
                    background: #fff;
                }
                .header-box {
                    width: 100%;
                    border-bottom: 2px solid #111;
                    padding-bottom: 10px;
                    margin-bottom: 12px;
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                }
                .header-title { text-align: center; flex: 1; }
                .header-title h1 { margin: 0 0 4px 0; font-size: 16px; font-weight: 900; }
                .header-title p { margin: 0; font-size: 11px; color: #444; }
                .info-grid {
                    width: 100%;
                    border-collapse: collapse;
                    margin-bottom: 12px;
                    background-color: #f9f9f9;
                }
                .info-grid td {
                    border: 1px solid #ccc;
                    padding: 5px 8px;
                    font-size: 11px;
                }
                .manifest-table {
                    width: 100%;
                    border-collapse: collapse;
                }
                .manifest-table th, .manifest-table td {
                    border: 1px solid #222;
                    padding: 5px 6px;
                    text-align: center;
                }
                .manifest-table th {
                    background-color: #eee;
                    font-weight: bold;
                    font-size: 11px;
                }
                .manifest-table td.text-start {
                    text-align: <?= $L['dir'] === 'rtl' ? 'right' : 'left' ?>;
                }
                .signature-box {
                    margin-top: 25px;
                    display: flex;
                    justify-content: space-between;
                    padding: 0 25px;
                    font-weight: bold;
                    font-size: 11px;
                }
                .no-print {
                    background: #f1f5f9;
                    padding: 10px 15px;
                    border-radius: 8px;
                    margin-bottom: 15px;
                    display: flex;
                    gap: 10px;
                    border: 1px solid #cbd5e1;
                }
                .no-print button {
                    padding: 6px 14px;
                    font-family: inherit;
                    font-weight: bold;
                    cursor: pointer;
                    border-radius: 6px;
                    border: 1px solid #94a3b8;
                    background: #fff;
                }
                @media print {
                    .no-print { display: none !important; }
                    body { padding: 0; }
                }
            </style>
        </head>
        <body onload="window.print();">

            <div class="no-print">
                <button onclick="window.print();">🖨️ <?= $lang === 'en' ? 'Print' : 'چاپ مانیفست' ?></button>
                <button onclick="window.close();"><?= $lang === 'en' ? 'Close Window' : 'بستن پنجره' ?></button>
            </div>

            <div class="header-box">
                <div style="width: 25%;">
                    <strong><?= safe($settings['site_title'] ?? 'کاروان زیارتی محبان اهل‌بیت (ع)') ?></strong><br>
                    <span style="font-size: 10px; color: #555;"><?= safe($settings['site_tagline'] ?? '') ?></span>
                </div>
                <div class="header-title">
                    <h1><?= $L['title'] ?></h1>
                    <p><?= $L['subtitle'] ?></p>
                </div>
                <div style="width: 25%; text-align: <?= $L['dir'] === 'rtl' ? 'left' : 'right' ?>; font-size: 10px;">
                    <?= $L['departure_date'] ?>: <strong style="font-family: monospace;"><?= safe($caravan['start_date']) ?></strong><br>
                    <?= $L['total_pilgrims'] ?>: <strong style="font-family: monospace;"><?= count($pilgrims) ?></strong>
                </div>
            </div>

            <table class="info-grid">
                <tr>
                    <td style="width: 33%;"><strong><?= $L['caravan'] ?>:</strong> <?= safe($caravan['title']) ?></td>
                    <td style="width: 33%;"><strong><?= $L['destination'] ?>:</strong> <?= safe($caravan['destination']) ?></td>
                    <td style="width: 34%;"><strong><?= $L['departure_time'] ?>:</strong> <?= safe($caravan['departure_time'] ?: '—') ?></td>
                </tr>
            </table>

            <table class="manifest-table">
                <thead>
                    <tr>
                        <th style="width: 30px;"><?= $L['row'] ?></th>
                        <th><?= $L['fullname'] ?></th>
                        <th><?= $L['national_code'] ?></th>
                        <th><?= $L['passport_no'] ?></th>
                        <th><?= $L['passport_exp'] ?></th>
                        <th><?= $L['phone'] ?></th>
                        <th><?= $L['emergency_phone'] ?></th>
                        <th><?= $L['gender'] ?></th>
                        <th><?= $L['age_group'] ?></th>
                        <th><?= $L['medical_notes'] ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($pilgrims)): ?>
                        <tr><td colspan="10" style="padding: 20px;"><?= $L['empty'] ?></td></tr>
                    <?php else: ?>
                        <?php foreach ($pilgrims as $idx => $p): ?>
                            <tr>
                                <td style="font-family: monospace;"><?= $idx + 1 ?></td>
                                <td class="text-start" style="font-weight: bold;"><?= safe($p['fullname']) ?></td>
                                <td style="font-family: monospace;"><?= safe($p['national_code']) ?></td>
                                <td style="font-family: monospace; font-weight: bold;"><?= safe($p['passport_number'] ?: '—') ?></td>
                                <td style="font-family: monospace;"><?= safe($p['passport_expiry'] ?: '—') ?></td>
                                <td style="font-family: monospace;"><?= safe($p['phone']) ?></td>
                                <td style="font-family: monospace;"><?= safe($p['emergency_phone'] ?: '—') ?></td>
                                <td><?= $p['gender'] === 'female' ? $L['female'] : $L['male'] ?></td>
                                <td><?= $L[$p['age_group']] ?? $L['adult'] ?></td>
                                <td style="font-size: 10px;"><?= safe($p['medical_notes'] ?: $L['health']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <div class="signature-box">
                <div><?= $L['sign_leader'] ?></div>
                <div><?= $L['sign_hotel'] ?></div>
                <div><?= $L['sign_agency'] ?></div>
            </div>

        </body>
        </html>
        <?php
        exit;
    }

    // ۲. خروجی اکسل پیشرفته (HTML Excel Table با پشتیبانی ۱۰۰٪ از فونت فارسی و اعداد)
    if ($export_format === 'xls') {
        $filename = "manifest_" . $selected_caravan_id . "_" . $lang . "_" . date('Ymd') . ".xls";

        header("Content-Type: application/vnd.ms-excel; charset=UTF-8");
        header("Content-Disposition: attachment; filename=\"$filename\"");
        header("Pragma: no-cache");
        header("Expires: 0");

        // درج BOM برای تضمین باز شدن سالم در اکسل ویندوز
        echo "\xEF\xBB\xBF";
        ?>
        <html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
        <head>
            <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
            <style>
                body { font-family: Tahoma, Arial, sans-serif; direction: <?= $L['dir'] ?>; }
                table { border-collapse: collapse; width: 100%; }
                th { background-color: #1b4d3e; color: #ffffff; border: 1px solid #000; padding: 8px; }
                td { border: 1px solid #999; padding: 6px; text-align: center; }
                .text { mso-number-format:"\@"; } /* جلوگیری از حذف صفرهای ابتدای کدملی و تلفن */
            </style>
        </head>
        <body>
            <table>
                <tr>
                    <th colspan="12" style="font-size: 16px; background-color: #0b3328; color: #fef08a; padding: 12px;">
                        <?= $L['title'] ?> - <?= safe($caravan['title']) ?> (<?= safe($caravan['destination']) ?>)
                    </th>
                </tr>
                <tr>
                    <td colspan="6" style="text-align: right;"><strong><?= $L['departure_date'] ?>:</strong> <?= safe($caravan['start_date']) ?></td>
                    <td colspan="6" style="text-align: left;"><strong><?= $L['total_pilgrims'] ?>:</strong> <?= count($pilgrims) ?></td>
                </tr>
                <tr>
                    <th><?= $L['row'] ?></th>
                    <th><?= $L['fullname'] ?></th>
                    <th><?= $L['national_code'] ?></th>
                    <th><?= $L['passport_no'] ?></th>
                    <th><?= $L['passport_exp'] ?></th>
                    <th><?= $L['phone'] ?></th>
                    <th><?= $L['emergency_phone'] ?></th>
                    <th><?= $L['gender'] ?></th>
                    <th><?= $L['age_group'] ?></th>
                    <th>هزینه مصوب (تومان)</th>
                    <th>مبلغ واریزی</th>
                    <th><?= $L['medical_notes'] ?></th>
                </tr>
                <?php foreach ($pilgrims as $idx => $p): ?>
                    <tr>
                        <td><?= $idx + 1 ?></td>
                        <td style="text-align: right; font-weight: bold;"><?= safe($p['fullname']) ?></td>
                        <td class="text"><?= safe($p['national_code']) ?></td>
                        <td class="text"><?= safe($p['passport_number']) ?></td>
                        <td class="text"><?= safe($p['passport_expiry']) ?></td>
                        <td class="text"><?= safe($p['phone']) ?></td>
                        <td class="text"><?= safe($p['emergency_phone']) ?></td>
                        <td><?= $p['gender'] === 'female' ? $L['female'] : $L['male'] ?></td>
                        <td><?= $L[$p['age_group']] ?? $L['adult'] ?></td>
                        <td><?= number_format($p['final_price']) ?></td>
                        <td><?= number_format($p['paid_amount']) ?></td>
                        <td><?= safe($p['medical_notes'] ?: $L['health']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </body>
        </html>
        <?php
        exit;
    }

    // ۳. خروجی ساختاریافتهٔ استاندارد JSON
    if ($export_format === 'json') {
        $filename = "manifest_" . $selected_caravan_id . ".json";
        header('Content-Type: application/json; charset=utf-8');
        header("Content-Disposition: attachment; filename=\"$filename\"");

        $export_payload = [
            'meta' => [
                'agency' => $settings['site_title'] ?? 'Caravan',
                'language' => $lang,
                'generated_at' => date('Y-m-d H:i:s'),
                'total_records' => count($pilgrims)
            ],
            'caravan' => [
                'id' => (int)$caravan['id'],
                'title' => $caravan['title'],
                'destination' => $caravan['destination'],
                'start_date' => $caravan['start_date'],
                'end_date' => $caravan['end_date'],
                'departure_time' => $caravan['departure_time'],
                'departure_place' => $caravan['departure_place'],
                'itinerary' => json_decode($caravan['itinerary'] ?? '[]', true)
            ],
            'pilgrims' => []
        ];

        foreach ($pilgrims as $idx => $p) {
            $export_payload['pilgrims'][] = [
                'row' => $idx + 1,
                'fullname' => $p['fullname'],
                'national_code' => $p['national_code'],
                'passport_number' => $p['passport_number'],
                'passport_expiry' => $p['passport_expiry'],
                'phone' => $p['phone'],
                'emergency_phone' => $p['emergency_phone'],
                'gender' => $p['gender'],
                'age_group' => $p['age_group'],
                'final_price' => (float)$p['final_price'],
                'paid_amount' => (float)$p['paid_amount'],
                'payment_ref' => $p['payment_ref'],
                'medical_notes' => $p['medical_notes'],
                'status' => $p['status']
            ];
        }

        echo json_encode($export_payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// لیست کلیه کاروان‌ها برای فرم انتخاب
$caravans = $conn->query("SELECT id, title, start_date, destination FROM caravans ORDER BY id DESC")->fetchAll();

require_once __DIR__ . '/header.php';
?>

<div class="space-y-6 max-w-4xl mx-auto">

    <!-- نوار عنوان -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center pb-4 border-b border-amber-500/20 gap-3">
        <div>
            <h1 class="text-xl sm:text-2xl font-black text-white flex items-center gap-2">
                <span>📥</span> مرکز چندزبانه مانیفست و خروجی‌های رسمی
            </h1>
            <p class="text-xs text-amber-200/80 mt-1">تولید صورت‌جلسه رسمی پرواز و مرز عراق به زبان‌های فارسی، عربی و انگلیسی (Excel / PDF / JSON)</p>
        </div>
        <span class="text-xs text-amber-300 font-mono bg-[#031712] px-3 py-1.5 rounded-xl border border-amber-500/20">
            استاندارد مرزبانی و حج
        </span>
    </div>

    <!-- کارت فرم فیلتر و انتخاب قالب -->
    <div class="rogh-card rounded-3xl p-6 sm:p-8 space-y-6 border border-amber-500/30">
        
        <form method="GET" action="export_center.php" target="_blank" class="space-y-6">
            
            <!-- انتخاب کاروان -->
            <div>
                <label class="block text-xs font-bold text-slate-200 mb-2">۱. انتخاب کاروان زیارتی مدنظر: <span class="text-rose-400">*</span></label>
                <select name="caravan_id" required class="w-full bg-[#031712] border border-amber-500/30 rounded-xl p-3 text-xs text-white focus:outline-none focus:border-amber-400">
                    <option value="">-- لطفاً کاروان مورد نظر را انتخاب فرمایید --</option>
                    <?php foreach ($caravans as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $selected_caravan_id === (int)$c['id'] ? 'selected' : '' ?>>
                            <?= safe($c['title']) ?> (مقصد: <?= safe($c['destination']) ?> | اعزام: <?= safe($c['start_date']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- انتخاب زبان مانیفست -->
            <div class="space-y-2">
                <label class="block text-xs font-bold text-slate-200">۲. زبان مانیفست و صورت‌جلسه:</label>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <label class="p-3.5 bg-[#031712] rounded-xl border border-amber-500/30 flex items-center gap-3 cursor-pointer hover:border-amber-400 transition">
                        <input type="radio" name="lang" value="fa" checked class="text-amber-500 w-4 h-4">
                        <div>
                            <strong class="text-white text-xs block">🇮🇷 فارسی</strong>
                            <span class="text-[10px] text-slate-400">ویژه بیمه، حج و سازمان</span>
                        </div>
                    </label>

                    <label class="p-3.5 bg-[#031712] rounded-xl border border-amber-500/30 flex items-center gap-3 cursor-pointer hover:border-amber-400 transition">
                        <input type="radio" name="lang" value="ar" class="text-amber-500 w-4 h-4">
                        <div>
                            <strong class="text-emerald-300 text-xs block">🇮🇶 العربية (العراق)</strong>
                            <span class="text-[10px] text-slate-400">ویژه مرزبانی، فرودگاه نجف و هتل‌ها</span>
                        </div>
                    </label>

                    <label class="p-3.5 bg-[#031712] rounded-xl border border-amber-500/30 flex items-center gap-3 cursor-pointer hover:border-amber-400 transition">
                        <input type="radio" name="lang" value="en" class="text-amber-500 w-4 h-4">
                        <div>
                            <strong class="text-amber-200 text-xs block">🌐 English</strong>
                            <span class="text-[10px] text-slate-400">بین‌المللی و خطوط هواپیمایی</span>
                        </div>
                    </label>
                </div>
            </div>

            <!-- انتخاب فرمت خروجی -->
            <div class="space-y-2">
                <label class="block text-xs font-bold text-slate-200">۳. فرمت فایل خروجی:</label>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <label class="p-4 bg-[#031712] rounded-2xl border border-amber-500/30 flex flex-col justify-between cursor-pointer hover:border-amber-400 transition space-y-2">
                        <div class="flex items-center gap-2.5">
                            <input type="radio" name="format" value="print" checked class="text-amber-500 w-4 h-4">
                            <strong class="text-white text-xs">📄 مانیفست رسمی چاپی (A4)</strong>
                        </div>
                        <p class="text-[10px] text-slate-400 leading-relaxed">جدول افقی کادربندی‌شده با سربرگ، مشخصات پاسپورت و کادر امضا.</p>
                    </label>

                    <label class="p-4 bg-[#031712] rounded-2xl border border-amber-500/30 flex flex-col justify-between cursor-pointer hover:border-amber-400 transition space-y-2">
                        <div class="flex items-center gap-2.5">
                            <input type="radio" name="format" value="xls" class="text-amber-500 w-4 h-4">
                            <strong class="text-emerald-300 text-xs">📊 خروجی اکسل (Excel XLS)</strong>
                        </div>
                        <p class="text-[10px] text-slate-400 leading-relaxed">فایل اکسل با پشتیبانی کامل از کاراکترهای فارسی و حفظ صفرهای اول شماره‌ها.</p>
                    </label>

                    <label class="p-4 bg-[#031712] rounded-2xl border border-amber-500/30 flex flex-col justify-between cursor-pointer hover:border-amber-400 transition space-y-2">
                        <div class="flex items-center gap-2.5">
                            <input type="radio" name="format" value="json" class="text-amber-500 w-4 h-4">
                            <strong class="text-amber-300 text-xs">{ } خروجی ساختاریافته JSON</strong>
                        </div>
                        <p class="text-[10px] text-slate-400 leading-relaxed">خروجی خام برای اپلیکیشن، وب‌سرویس‌ها، بیمه یا بک‌آپ سریع.</p>
                    </label>
                </div>
            </div>

            <!-- دکمه دریافت خروجی -->
            <button type="submit" class="w-full bg-gradient-to-r from-amber-500 via-amber-400 to-amber-500 hover:from-amber-400 text-slate-950 font-black py-4 rounded-2xl text-xs sm:text-sm transition duration-200 shadow-xl shadow-amber-500/20 flex items-center justify-center gap-2">
                <span>🚀</span> تولید و دریافت خروجی مانیفست در برگه جدید
            </button>
        </form>

    </div>

</div>

<?php require_once __DIR__ . '/footer.php'; ?>