<?php
require_once 'db.php';
require_once 'sms_helper.php';

$settings = get_settings($conn);
$national_code = safe($_GET['national_code'] ?? ($_POST['national_code'] ?? ''));
$pilgrim = null;
$caravan = null;
$msg = '';
$err = '';

// پردازش آپلود مجدد مدارک (حداکثر ۵ مگابایت) و تغییر وضعیت قطعی به pending
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reupload_documents'])) {
    $p_id = (int)($_POST['pilgrim_id'] ?? 0);
    $p_code = safe($_POST['national_code'] ?? '');

    $stmt_chk = $conn->prepare("SELECT * FROM pilgrims WHERE id = ? AND national_code = ? LIMIT 1");
    $stmt_chk->execute([$p_id, $p_code]);
    $curr_p = $stmt_chk->fetch();

    if (!$curr_p) {
        $err = 'پرونده یافت نشد.';
    } elseif ($curr_p['status'] === 'verified') {
        $err = 'این پرونده تأیید نهایی شده است و امکان تغییر مدارک وجود ندارد.';
    } else {
        $upload_dir = __DIR__ . '/uploads/documents/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
        $max_size = 5 * 1024 * 1024; // ۵ مگابایت

        $updates = ["status = 'pending'", "rejection_reason = ''"];
        $params = [];

        // ۱. کارت ملی جدید
        if (isset($_FILES['new_national_card']) && $_FILES['new_national_card']['error'] === UPLOAD_ERR_OK) {
            if ($_FILES['new_national_card']['size'] <= $max_size) {
                $ext = strtolower(pathinfo($_FILES['new_national_card']['name'], PATHINFO_EXTENSION));
                $new_nid = 'nid_' . time() . '.' . $ext;
                if (move_uploaded_file($_FILES['new_national_card']['tmp_name'], $upload_dir . $new_nid)) {
                    if (!empty($curr_p['national_card_image']) && file_exists($upload_dir . $curr_p['national_card_image'])) {
                        @unlink($upload_dir . $curr_p['national_card_image']);
                    }
                    $updates[] = "national_card_image = ?";
                    $params[] = $new_nid;
                }
            } else {
                $err = 'حجم کارت ملی بیشتر از ۵ مگابایت است.';
            }
        }

        // ۲. گذرنامه جدید
        if (empty($err) && isset($_FILES['new_passport']) && $_FILES['new_passport']['error'] === UPLOAD_ERR_OK) {
            if ($_FILES['new_passport']['size'] <= $max_size) {
                $ext = strtolower(pathinfo($_FILES['new_passport']['name'], PATHINFO_EXTENSION));
                $new_pass = 'pass_' . time() . '.' . $ext;
                if (move_uploaded_file($_FILES['new_passport']['tmp_name'], $upload_dir . $new_pass)) {
                    if (!empty($curr_p['passport_image']) && file_exists($upload_dir . $curr_p['passport_image'])) {
                        @unlink($upload_dir . $curr_p['passport_image']);
                    }
                    $updates[] = "passport_image = ?";
                    $params[] = $new_pass;
                }
            } else {
                $err = 'حجم گذرنامه بیشتر از ۵ مگابایت است.';
            }
        }

        // ۳. فیش واریزی جدید (نام دقیق ستون دیتابیس: payment_receipt_image)
        if (empty($err) && isset($_FILES['new_receipt']) && $_FILES['new_receipt']['error'] === UPLOAD_ERR_OK) {
            if ($_FILES['new_receipt']['size'] <= $max_size) {
                $ext = strtolower(pathinfo($_FILES['new_receipt']['name'], PATHINFO_EXTENSION));
                $new_rec = 'receipt_' . time() . '.' . $ext;
                if (move_uploaded_file($_FILES['new_receipt']['tmp_name'], $upload_dir . $new_rec)) {
                    if (!empty($curr_p['payment_receipt_image']) && file_exists($upload_dir . $curr_p['payment_receipt_image'])) {
                        @unlink($upload_dir . $curr_p['payment_receipt_image']);
                    }
                    $updates[] = "payment_receipt_image = ?";
                    $params[] = $new_rec;
                }
            } else {
                $err = 'حجم فیش بیشتر از ۵ مگابایت است.';
            }
        }

        // اطلاعات مالی جدید در صورت ورود
        if (!empty($_POST['new_paid_amount'])) {
            $updates[] = "paid_amount = ?";
            $params[] = (float)str_replace(',', '', $_POST['new_paid_amount']);
        }
        if (!empty($_POST['new_payment_ref'])) {
            $updates[] = "payment_ref = ?";
            $params[] = safe($_POST['new_payment_ref']);
        }

        // اگر خطایی نبود، دستور UPDATE حتما وضعیت را به pending تبدیل می‌کند
        if (empty($err)) {
            $params[] = $p_id;
            $sql = "UPDATE pilgrims SET " . implode(", ", $updates) . " WHERE id = ?";

            $stmt_up = $conn->prepare($sql);
            if ($stmt_up->execute($params)) {
                $msg = '✅ مدارک جدید با موفقیت بارگذاری شد و وضعیت پرونده به «در انتظار بررسی» تغییر یافت.';
                $national_code = $p_code;
            } else {
                $err = 'خطا در به‌روزرسانی پایگاه داده.';
            }
        }
    }
}

// واکشی مشخصات پرونده
if (!empty($national_code)) {
    $stmt = $conn->prepare("SELECT * FROM pilgrims WHERE national_code = ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$national_code]);
    $pilgrim = $stmt->fetch();
    if ($pilgrim) {
        $stmt_c = $conn->prepare("SELECT * FROM caravans WHERE id = ? LIMIT 1");
        $stmt_c->execute([$pilgrim['caravan_id']]);
        $caravan = $stmt_c->fetch();
    }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>استعلام و پیگیری پرونده | <?= safe($settings['site_title'] ?? 'کاروان زیارتی') ?></title>
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
        .site-logo-img { max-width: 40px !important; max-height: 40px !important; object-fit: contain !important; }
    </style>
</head>
<body class="islami-bg min-h-screen flex flex-col selection:bg-amber-500 selection:text-black">
    <header class="bg-[#051f19]/90 backdrop-blur-md border-b border-amber-500/30 sticky top-0 z-40 mb-8 shadow-xl">
        <div class="max-w-4xl mx-auto px-4 h-18 py-3 flex justify-between items-center">
            <a href="index.php" class="flex items-center gap-3">
                <div class="w-10 h-10 bg-[#031914] rounded-xl flex items-center justify-center border border-amber-500/30">
                    <?php if (!empty($settings['site_logo']) && file_exists(__DIR__ . '/uploads/' . $settings['site_logo'])): ?>
                        <img src="uploads/<?= safe($settings['site_logo']) ?>" class="site-logo-img">
                    <?php else: ?>
                        <span class="text-amber-400">🕌</span>
                    <?php endif; ?>
                </div>
                <div>
                    <span class="font-black text-white text-sm block"><?= safe($settings['site_title'] ?? 'کاروان زیارتی') ?></span>
                    <span class="text-[10px] text-emerald-300">سامانه پیگیری و استعلام</span>
                </div>
            </a>
            <a href="index.php" class="text-xs text-amber-300 font-bold hover:text-white">&larr; صفحه اصلی</a>
        </div>
    </header>

    <main class="max-w-3xl mx-auto w-full px-4 space-y-6 flex-grow pb-12 text-xs">
        <div class="rogh-card rounded-3xl p-6 border border-amber-500/30 space-y-3">
            <h1 class="text-sm font-black text-white text-center">استعلام و پیگیری وضعیت پرونده زائر</h1>
            <form action="tracking.php" method="GET" class="flex gap-2">
                <input type="text" name="national_code" maxlength="10" required value="<?= safe($national_code) ?>" placeholder="کد ملی ۱۰ رقمی مسافر..." class="flex-1 bg-[#031712] border border-amber-500/40 rounded-xl p-3 text-center font-mono text-amber-300 focus:outline-none focus:border-amber-400">
                <button type="submit" class="bg-amber-500 hover:bg-amber-400 text-slate-950 font-black px-6 py-3 rounded-xl transition">استعلام</button>
            </form>
        </div>

        <?php if ($msg): ?><div class="p-3 bg-emerald-950 text-emerald-200 rounded-xl font-bold flex items-center gap-2"><span>✅</span> <?= safe($msg) ?></div><?php endif; ?>
        <?php if ($err): ?><div class="p-3 bg-rose-950 text-rose-200 rounded-xl font-bold flex items-center gap-2"><span>❌</span> <?= safe($err) ?></div><?php endif; ?>

        <?php if ($pilgrim && $caravan): ?>
            <div class="rogh-card rounded-3xl p-6 sm:p-8 border border-amber-500/40 space-y-6">
                <div class="flex justify-between items-center pb-3 border-b border-amber-500/20">
                    <div>
                        <span class="text-[10px] text-slate-400 block">نام مسافر:</span>
                        <h2 class="font-black text-white text-base"><?= safe($pilgrim['fullname']) ?></h2>
                    </div>
                    <div>
                        <?php if ($pilgrim['status'] === 'verified'): ?>
                            <span class="px-3 py-1.5 rounded-xl font-bold bg-emerald-500/20 text-emerald-300 border border-emerald-500/40 inline-block">✅ تأیید قطعی</span>
                        <?php elseif ($pilgrim['status'] === 'rejected'): ?>
                            <span class="px-3 py-1.5 rounded-xl font-bold bg-rose-500/20 text-rose-300 border border-rose-500/40 inline-block">❌ مدارک نیازمند اصلاح</span>
                        <?php else: ?>
                            <span class="px-3 py-1.5 rounded-xl font-bold bg-amber-500/20 text-amber-300 border border-amber-500/40 inline-block animate-pulse">⏳ در انتظار بررسی خادمین</span>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($pilgrim['status'] === 'rejected' && !empty($pilgrim['rejection_reason'])): ?>
                    <div class="p-4 bg-rose-950/70 border border-rose-500/50 rounded-2xl space-y-1">
                        <strong class="text-rose-300 block">⚠️ علت عدم تأیید مدارک توسط خادم:</strong>
                        <p class="text-white bg-black/30 p-2.5 rounded-xl border border-rose-500/30"><?= safe($pilgrim['rejection_reason']) ?></p>
                    </div>
                <?php endif; ?>

                <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 bg-[#031712] p-4 rounded-2xl border border-amber-500/20 font-mono">
                    <div>کاروان: <strong class="text-white block"><?= safe($caravan['title']) ?></strong></div>
                    <div>مبلغ واریزی: <strong class="text-emerald-400 block"><?= number_format($pilgrim['paid_amount']) ?> ت</strong></div>
                    <div>تاریخ اعزام: <strong class="text-amber-300 block"><?= safe($caravan['start_date']) ?></strong></div>
                </div>

                <!-- فرم بارگذاری مجدد مدارک -->
                <?php if (in_array($pilgrim['status'], ['rejected', 'pending'])): ?>
                    <div class="border-t border-amber-500/20 pt-5 space-y-4">
                        <div>
                            <h3 class="font-black text-sm text-amber-300">📤 ارسال مجدد مدارک اصلاحی یا فیش جدید</h3>
                            <p class="text-[11px] text-slate-400">حداکثر حجم مجاز برای هر فایل: <strong class="text-amber-200">۵ مگابایت</strong> (فرمت: JPG, PNG, PDF)</p>
                        </div>

                        <form action="tracking.php" method="POST" enctype="multipart/form-data" class="bg-[#031712] p-5 rounded-2xl border border-amber-500/30 space-y-4">
                            <input type="hidden" name="reupload_documents" value="1">
                            <input type="hidden" name="pilgrim_id" value="<?= $pilgrim['id'] ?>">
                            <input type="hidden" name="national_code" value="<?= safe($pilgrim['national_code']) ?>">

                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                <div>
                                    <label class="text-slate-300 block mb-1 font-bold">کارت ملی جدید:</label>
                                    <input type="file" name="new_national_card" accept=".jpg,.jpeg,.png,.pdf" class="w-full text-slate-400 text-[11px]">
                                </div>
                                <?php if ($caravan['requires_passport']): ?>
                                <div>
                                    <label class="text-slate-300 block mb-1 font-bold">گذرنامه جدید:</label>
                                    <input type="file" name="new_passport" accept=".jpg,.jpeg,.png,.pdf" class="w-full text-slate-400 text-[11px]">
                                </div>
                                <?php endif; ?>
                                <div>
                                    <label class="text-emerald-300 block mb-1 font-bold">فیش واریزی جدید:</label>
                                    <input type="file" name="new_receipt" accept=".jpg,.jpeg,.png,.pdf" class="w-full text-slate-400 text-[11px]">
                                </div>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-2 border-t border-white/5">
                                <div>
                                    <label class="text-slate-300 block mb-1">مبلغ واریزی جدید (تومان):</label>
                                    <input type="number" name="new_paid_amount" placeholder="در صورت واریز وجه..." class="w-full bg-[#051c16] border border-amber-500/30 rounded-xl p-2.5 text-amber-300 font-mono text-xs">
                                </div>
                                <div>
                                    <label class="text-slate-300 block mb-1">شماره ارجاع / پیگیری تراکنش:</label>
                                    <input type="text" name="new_payment_ref" placeholder="کد رهگیری فیش..." class="w-full bg-[#051c16] border border-amber-500/30 rounded-xl p-2.5 text-white font-mono text-xs">
                                </div>
                            </div>

                            <button type="submit" class="w-full bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-400 text-slate-950 font-black py-3 rounded-xl transition shadow-lg">بارگذاری مدارک و ارسال جهت بررسی مجدد &larr;</button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </main>

    <div class="mt-auto">
        <?php require_once 'footer.php'; ?>
    </div>
</body>
</html>