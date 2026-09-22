<?php
require_once 'db.php';
require_once 'sms_helper.php';

$settings = get_settings($conn);
$caravan_id = (int)($_GET['caravan_id'] ?? 0);

if ($caravan_id <= 0) {
    header("Location: index.php");
    exit;
}

$stmt_c = $conn->prepare("SELECT * FROM caravans WHERE id = ? LIMIT 1");
$stmt_c->execute([$caravan_id]);
$caravan = $stmt_c->fetch();

if (!$caravan) {
    die('کاروان مورد نظر یافت نشد یا منقضی شده است.');
}

$msg = '';
$err = '';

// پردازش ثبت‌نام مسافر از طریق فرم عمومی سایت
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register_pilgrim'])) {
    $fullname = safe($_POST['fullname'] ?? '');
    $national_code = safe($_POST['national_code'] ?? '');
    $phone = safe($_POST['phone'] ?? '');
    $emergency_phone = safe($_POST['emergency_phone'] ?? '');
    $gender = in_array($_POST['gender'] ?? '', ['male', 'female']) ? $_POST['gender'] : 'male';
    $age_group = in_array($_POST['age_group'] ?? '', ['adult', 'child', 'infant']) ? $_POST['age_group'] : 'adult';
    $birth_date = safe($_POST['birth_date'] ?? '');
    $medical_notes = safe($_POST['medical_notes'] ?? '');
    $passport_number = safe($_POST['passport_number'] ?? '');
    $passport_expiry = safe($_POST['passport_expiry'] ?? '');
    $paid_amount = (float)str_replace(',', '', $_POST['paid_amount'] ?? 0);
    $payment_ref = safe($_POST['payment_ref'] ?? '');
    $rules_accepted = isset($_POST['rules_accepted']) ? 1 : 0;

    if (empty($fullname) || empty($national_code) || empty($phone)) {
        $err = 'لطفاً نام، کد ملی و شماره همراه خود را به طور کامل وارد کنید.';
    } elseif (strlen($national_code) !== 10 || !ctype_digit($national_code)) {
        $err = 'کد ملی باید دقیقاً ۱۰ رقم باشد.';
    } elseif ($caravan['requires_passport'] && empty($passport_number)) {
        $err = 'برای این سفر درج شماره گذرنامه الزامی است.';
    } elseif (!$rules_accepted) {
        $err = 'پذیرش قوانین و شرایط سفر الزامی است.';
    } else {
        // محاسبه قیمت بر اساس رده سنی
        $final_price = (float)$caravan['price_adult'];
        if ($age_group === 'child') $final_price = (float)($caravan['price_child'] ?: $caravan['price_adult']);
        elseif ($age_group === 'infant') $final_price = (float)($caravan['price_infant'] ?: 0);

        $upload_dir = __DIR__ . '/uploads/documents/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
        $max_size = 5 * 1024 * 1024; // ۵ مگابایت

        // آپلود کارت ملی
        $national_card_image = null;
        if (isset($_FILES['national_card_file']) && $_FILES['national_card_file']['error'] === UPLOAD_ERR_OK) {
            if ($_FILES['national_card_file']['size'] <= $max_size) {
                $ext = strtolower(pathinfo($_FILES['national_card_file']['name'], PATHINFO_EXTENSION));
                $national_card_image = 'nid_' . time() . '.' . $ext;
                move_uploaded_file($_FILES['national_card_file']['tmp_name'], $upload_dir . $national_card_image);
            }
        }

        // آپلود پاسپورت
        $passport_image = null;
        if (isset($_FILES['passport_file']) && $_FILES['passport_file']['error'] === UPLOAD_ERR_OK) {
            if ($_FILES['passport_file']['size'] <= $max_size) {
                $ext = strtolower(pathinfo($_FILES['passport_file']['name'], PATHINFO_EXTENSION));
                $passport_image = 'pass_' . time() . '.' . $ext;
                move_uploaded_file($_FILES['passport_file']['tmp_name'], $upload_dir . $passport_image);
            }
        }

        // آپلود فیش واریزی
        $payment_receipt_image = null;
        if (isset($_FILES['receipt_file']) && $_FILES['receipt_file']['error'] === UPLOAD_ERR_OK) {
            if ($_FILES['receipt_file']['size'] <= $max_size) {
                $ext = strtolower(pathinfo($_FILES['receipt_file']['name'], PATHINFO_EXTENSION));
                $payment_receipt_image = 'receipt_' . time() . '.' . $ext;
                move_uploaded_file($_FILES['receipt_file']['tmp_name'], $upload_dir . $payment_receipt_image);
            }
        }

        try {
            $conn->beginTransaction();

            $stmt_ins = $conn->prepare("INSERT INTO pilgrims 
                (caravan_id, fullname, national_code, phone, emergency_phone, gender, age_group, birth_date, 
                 medical_notes, passport_number, passport_expiry, national_card_image, passport_image, payment_receipt_image, 
                 final_price, paid_amount, payment_ref, status, rules_accepted, registered_by) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, 'user')");
            
            $stmt_ins->execute([
                $caravan_id, $fullname, $national_code, $phone, $emergency_phone, $gender, $age_group,
                $birth_date ?: null, $medical_notes ?: null, $passport_number ?: null, $passport_expiry ?: null,
                $national_card_image, $passport_image, $payment_receipt_image,
                $final_price, $paid_amount, $payment_ref, $rules_accepted
            ]);

            // کسر ظرفیت کاروان
            $conn->prepare("UPDATE caravans SET remaining_capacity = GREATEST(0, remaining_capacity - 1) WHERE id = ?")->execute([$caravan_id]);
            $conn->commit();

            // ارسال پیامک ثبت‌نام
            $track_url = "http://" . $_SERVER['HTTP_HOST'] . "/caravan/tracking.php?national_code=" . $national_code;
            send_sms_notification($conn, $phone, $fullname, "زائر گرامی {$fullname}\nثبت‌نام اولیه شما در کاروان {$caravan['title']} انجام شد و در انتظار بررسی مدارک است.\n{$track_url}", 'register');

            header("Location: tracking.php?national_code=" . $national_code . "&registered=success");
            exit;
        } catch (Exception $e) {
            if ($conn->inTransaction()) $conn->rollBack();
            $err = 'خطا در ثبت اطلاعات پایگاه داده: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ثبت‌نام در کاروان <?= safe($caravan['title']) ?> | <?= safe($settings['site_title'] ?? 'کاروان زیارتی') ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet">
    <style>
        body { font-family: 'Vazirmatn', Tahoma, sans-serif; background-color: #041411; color: #f8fafc; }
        .islami-bg {
            background-color: #041411;
            background-image: radial-gradient(#0f382f 1.2px, transparent 1.2px), radial-gradient(#14483c 1.2px, #041411 1.2px);
            background-size: 40px 40px;
            background-position: 0 0, 20px 20px;
        }
        .rogh-card { background: linear-gradient(180deg, rgba(13, 44, 38, 0.95) 0%, rgba(6, 26, 22, 0.98) 100%); border: 1px solid rgba(212, 175, 55, 0.3); }
        .site-logo-img { max-width: 45px !important; max-height: 45px !important; object-fit: contain !important; }
    </style>
</head>
<body class="islami-bg min-h-screen flex flex-col selection:bg-amber-500 selection:text-black text-xs">

    <!-- هدر سایت -->
    <header class="bg-[#051f19]/90 backdrop-blur-md border-b border-amber-500/30 sticky top-0 z-40 shadow-xl">
        <div class="max-w-4xl mx-auto px-4 h-18 py-3 flex justify-between items-center">
            <a href="index.php" class="flex items-center gap-3">
                <div class="w-11 h-11 bg-[#031914] rounded-xl flex items-center justify-center border border-amber-500/30">
                    <?php if (!empty($settings['site_logo']) && file_exists(__DIR__ . '/uploads/' . $settings['site_logo'])): ?>
                        <img src="uploads/<?= safe($settings['site_logo']) ?>" class="site-logo-img">
                    <?php else: ?>
                        <span class="text-amber-400 text-lg">🕌</span>
                    <?php endif; ?>
                </div>
                <div>
                    <span class="font-black text-white text-sm block"><?= safe($settings['site_title'] ?? 'کاروان زیارتی') ?></span>
                    <span class="text-[10px] text-emerald-300">فرم ثبت‌نام آنلاین مسافران</span>
                </div>
            </a>
            <a href="index.php" class="text-xs text-amber-300 font-bold hover:text-white">&larr; بازگشت به صفحه اصلی</a>
        </div>
    </header>

    <main class="max-w-3xl mx-auto w-full px-4 space-y-6 flex-grow py-8">

        <?php if ($err): ?>
            <div class="p-3.5 bg-rose-950/70 border border-rose-500/50 text-rose-200 rounded-2xl font-bold flex items-center gap-2"><span>❌</span> <?= safe($err) ?></div>
        <?php endif; ?>

        <!-- اطلاعات جامع کاروان انتخاب‌شده -->
        <div class="rogh-card rounded-3xl p-6 sm:p-7 border border-amber-500/40 space-y-5 shadow-2xl">
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center pb-4 border-b border-amber-500/20 gap-2">
                <div>
                    <span class="text-[10px] text-amber-300 bg-amber-500/20 px-2.5 py-1 rounded-lg font-bold border border-amber-500/30">مقصد: <?= safe($caravan['destination']) ?></span>
                    <h1 class="font-black text-white text-base sm:text-lg mt-1"><?= safe($caravan['title']) ?></h1>
                </div>
                <div class="text-left font-mono">
                    <span class="text-[10px] text-slate-400 block">تاریخ اعزام:</span>
                    <strong class="text-amber-300 text-sm"><?= safe($caravan['start_date']) ?></strong>
                </div>
            </div>

            <!-- اطلاعات اقامت، هتل‌ها و ترانسفر -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                
                <!-- برنامه تفکیکی اقامت -->
                <div class="bg-[#031712] p-4 rounded-2xl border border-amber-500/20 space-y-2">
                    <h3 class="font-black text-amber-300 text-xs flex items-center gap-1.5"><span>🏛️</span> برنامه اقامت و هتل‌ها:</h3>
                    <?php 
                        $itin = !empty($caravan['itinerary']) ? json_decode($caravan['itinerary'], true) : [];
                        if (!empty($itin)): 
                    ?>
                        <div class="space-y-1.5 text-[11px]">
                            <?php foreach ($itin as $it): ?>
                                <div class="bg-black/30 p-2 rounded-xl border border-white/5">
                                    <strong class="text-white"><?= safe($it['city']) ?></strong> (<?= safe($it['duration']) ?>)<br>
                                    <span class="text-slate-300">هتل: <?= safe($it['hotel'] ?: 'نامشخص') ?></span>
                                    <?php if(!empty($it['notes'])): ?>
                                        <div class="text-[10px] text-amber-200/80 mt-0.5">توضیحات: <?= safe($it['notes']) ?></div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-slate-400 text-[11px]">برنامه اقامتی ثبت نشده است.</p>
                    <?php endif; ?>

                    <?php if(!empty($caravan['special_pilgrimages'])): ?>
                        <div class="text-[10px] text-slate-300 pt-1 border-t border-white/10">
                            <strong>زیارت‌های دوره:</strong> <?= safe($caravan['special_pilgrimages']) ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- ناوگان و عوامل اجرایی -->
                <div class="bg-[#031712] p-4 rounded-2xl border border-amber-500/20 space-y-2.5">
                    <h3 class="font-black text-amber-300 text-xs flex items-center gap-1.5"><span>🚍</span> ناوگان و عوامل اجرایی سفر:</h3>
                    <div class="space-y-1.5 text-[11px] text-slate-300">
                        <div><strong>نوع اعزام:</strong> <span class="text-white"><?= safe($caravan['trip_type']) ?></span></div>
                        <?php if(!empty($caravan['transport_departure'])): ?>
                            <div><strong>وسیله رفت:</strong> <?= safe($caravan['transport_departure']) ?></div>
                        <?php endif; ?>
                        <?php if(!empty($caravan['transport_return'])): ?>
                            <div><strong>وسیله برگشت:</strong> <?= safe($caravan['transport_return']) ?></div>
                        <?php endif; ?>
                        <?php if(!empty($caravan['leader_name'])): ?>
                            <div><strong>سرپرست/مداح:</strong> <span class="text-amber-200"><?= safe($caravan['leader_name']) ?></span></div>
                        <?php endif; ?>
                        <?php if(!empty($caravan['cleric_name'])): ?>
                            <div><strong>روحانی کاروان:</strong> <span class="text-amber-200"><?= safe($caravan['cleric_name']) ?></span></div>
                        <?php endif; ?>
                        <?php if(!empty($caravan['food_services'])): ?>
                            <div><strong>تغذیه:</strong> <?= safe($caravan['food_services']) ?></div>
                        <?php endif; ?>
                    </div>
                </div>

            </div>

            <!-- قیمت‌گذاری و ظرفیت -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 bg-[#02100d] p-3.5 rounded-2xl border border-amber-500/20 text-center font-mono">
                <div>
                    <span class="text-[10px] text-slate-400 block">نرخ بزرگسال:</span>
                    <strong class="text-emerald-400"><?= number_format($caravan['price_adult']) ?> ت</strong>
                </div>
                <div>
                    <span class="text-[10px] text-slate-400 block">نرخ کودک:</span>
                    <strong class="text-emerald-300"><?= number_format($caravan['price_child'] ?: $caravan['price_adult']) ?> ت</strong>
                </div>
                <div>
                    <span class="text-[10px] text-slate-400 block">حداقل بیعانه:</span>
                    <strong class="text-amber-300"><?= number_format($caravan['deposit_amount']) ?> ت</strong>
                </div>
                <div>
                    <span class="text-[10px] text-slate-400 block">ظرفیت باقی‌مانده:</span>
                    <strong class="text-teal-300"><?= (int)$caravan['remaining_capacity'] ?> نفر</strong>
                </div>
            </div>

            <?php if(!empty($caravan['description'])): ?>
                <div class="p-3 bg-black/30 rounded-xl border border-amber-500/20 text-[11px] text-slate-300 leading-relaxed">
                    <strong class="text-amber-300 block mb-0.5">توضیحات و شرایط تکمیلی سفر:</strong>
                    <?= nl2br(safe($caravan['description'])) ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- فرم ثبت‌نام زائر -->
        <div class="rogh-card rounded-3xl p-6 sm:p-8 border border-amber-500/40 shadow-2xl">
            <h2 class="font-black text-sm text-white pb-3 border-b border-amber-500/20 mb-5 flex items-center gap-1.5">
                <span>📝</span> فرم مشخصات فردی و بارگذاری مدارک
            </h2>

            <form action="register.php?caravan_id=<?= $caravan['id'] ?>" method="POST" enctype="multipart/form-data" class="space-y-5">
                <input type="hidden" name="register_pilgrim" value="1">

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div>
                        <label class="block font-bold text-slate-200 mb-1">نام و نام خانوادگی: *</label>
                        <input type="text" name="fullname" required placeholder="نام کامل..." class="w-full bg-[#031712] border border-amber-500/30 rounded-xl p-3 text-white">
                    </div>
                    <div>
                        <label class="block font-bold text-slate-200 mb-1">کد ملی (۱۰ رقم): *</label>
                        <input type="text" name="national_code" maxlength="10" required placeholder="کد ملی بدون خط فاصله..." class="w-full bg-[#031712] border border-amber-500/30 rounded-xl p-3 text-amber-300 font-mono">
                    </div>
                    <div>
                        <label class="block font-bold text-slate-200 mb-1">شماره همراه: *</label>
                        <input type="tel" name="phone" required placeholder="09123456789" class="w-full bg-[#031712] border border-amber-500/30 rounded-xl p-3 text-white font-mono">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div>
                        <label class="block font-bold text-slate-200 mb-1">تاریخ تولد:</label>
                        <input type="text" name="birth_date" placeholder="۱۳۷۵/۰۲/۱۵" class="w-full bg-[#031712] border border-amber-500/30 rounded-xl p-3 text-amber-300 font-mono">
                    </div>
                    <div>
                        <label class="block font-bold text-slate-200 mb-1">جنسیت:</label>
                        <select name="gender" class="w-full bg-[#031712] border border-amber-500/30 rounded-xl p-3 text-white">
                            <option value="male">آقا</option>
                            <option value="female">خانم</option>
                        </select>
                    </div>
                    <div>
                        <label class="block font-bold text-slate-200 mb-1">رده سنی:</label>
                        <select name="age_group" class="w-full bg-[#031712] border border-amber-500/30 rounded-xl p-3 text-white">
                            <option value="adult">بزرگسال (نرخ کامل)</option>
                            <option value="child">کودک (۲ تا ۱۲ سال)</option>
                            <option value="infant">نوزاد (زیر ۲ سال)</option>
                        </select>
                    </div>
                </div>

                <?php if ($caravan['requires_passport']): ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 p-4 bg-[#031712] rounded-2xl border border-teal-500/30">
                    <div>
                        <label class="block font-bold text-teal-300 mb-1">شماره گذرنامه: *</label>
                        <input type="text" name="passport_number" required placeholder="مثال: A12345678" class="w-full bg-[#051c16] border border-teal-500/30 rounded-xl p-2.5 text-white font-mono">
                    </div>
                    <div>
                        <label class="block font-bold text-teal-300 mb-1">تاریخ انقضای گذرنامه: *</label>
                        <input type="text" name="passport_expiry" required placeholder="۱۴۰۶/۰۵/۲۰" class="w-full bg-[#051c16] border border-teal-500/30 rounded-xl p-2.5 text-amber-300 font-mono">
                    </div>
                </div>
                <?php endif; ?>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block font-bold text-slate-200 mb-1">تلفن تماس اضطراری:</label>
                        <input type="tel" name="emergency_phone" placeholder="شماره یکی از بستگان..." class="w-full bg-[#031712] border border-amber-500/30 rounded-xl p-3 text-white font-mono">
                    </div>
                    <div>
                        <label class="block font-bold text-slate-200 mb-1">ملاحظات پزشکی یا بیماری خاص:</label>
                        <input type="text" name="medical_notes" placeholder="در صورت نیاز به ویلچر یا داروی خاص..." class="w-full bg-[#031712] border border-amber-500/30 rounded-xl p-3 text-white">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 p-4 bg-[#031712] rounded-2xl border border-amber-500/20">
                    <div>
                        <label class="text-slate-300 block mb-1 font-bold">آپلود کارت ملی:</label>
                        <input type="file" name="national_card_file" accept=".jpg,.jpeg,.png,.pdf" class="w-full text-slate-400 text-[11px]">
                    </div>
                    <?php if ($caravan['requires_passport']): ?>
                    <div>
                        <label class="text-teal-300 block mb-1 font-bold">آپلود گذرنامه:</label>
                        <input type="file" name="passport_file" accept=".jpg,.jpeg,.png,.pdf" class="w-full text-slate-400 text-[11px]">
                    </div>
                    <?php endif; ?>
                    <div>
                        <label class="text-emerald-300 block mb-1 font-bold">آپلود فیش واریزی بیعانه:</label>
                        <input type="file" name="receipt_file" accept=".jpg,.jpeg,.png,.pdf" class="w-full text-slate-400 text-[11px]">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block font-bold text-slate-200 mb-1">مبلغ واریز شده (تومان):</label>
                        <input type="number" name="paid_amount" placeholder="حداقل بیعانه: <?= number_format($caravan['deposit_amount']) ?>" class="w-full bg-[#031712] border border-amber-500/30 rounded-xl p-3 text-amber-300 font-mono">
                    </div>
                    <div>
                        <label class="block font-bold text-slate-200 mb-1">شماره ارجاع / پیگیری فیش بانکی:</label>
                        <input type="text" name="payment_ref" placeholder="کد رهگیری تراکنش..." class="w-full bg-[#031712] border border-amber-500/30 rounded-xl p-3 text-white font-mono">
                    </div>
                </div>

                <div class="flex items-center gap-2.5 p-3.5 bg-[#031712] rounded-xl border border-amber-500/20">
                    <input type="checkbox" name="rules_accepted" id="rules" value="1" required class="w-4 h-4 text-amber-500 rounded cursor-pointer">
                    <label for="rules" class="text-xs font-bold text-amber-200 cursor-pointer select-none">
                        صحت اطلاعات واردشده را تأیید نموده و قوانین و شرایط کنسلی کاروان را می‌پذیرم.
                    </label>
                </div>

                <button type="submit" class="w-full bg-gradient-to-r from-amber-500 via-amber-400 to-amber-500 hover:from-amber-400 text-slate-950 font-black py-3.5 rounded-xl transition shadow-xl shadow-amber-500/20 text-xs sm:text-sm">
                    ثبت‌نام قطعی و ارسال پرونده &larr;
                </button>
            </form>
        </div>

    </main>

    <?php require_once 'footer.php'; ?>
</body>
</html>