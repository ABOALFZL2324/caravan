<?php
ob_start();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../sms_helper.php';

require_roles(['super_admin', 'registrar', 'leader']);

$msg = '';
$err = '';

// ۱. ارسال پیامک تکی و اختصاصی به یک زائر
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_single_sms'])) {
    $pilgrim_id = (int)($_POST['pilgrim_id'] ?? 0);
    $sms_content = trim($_POST['sms_content'] ?? '');

    if ($pilgrim_id <= 0 || empty($sms_content)) {
        $err = 'لطفاً زائر مورد نظر را انتخاب کرده و متن پیامک را وارد فرمایید.';
    } else {
        $stmt_p = $conn->prepare("SELECT pilgrims.*, caravans.title AS caravan_title FROM pilgrims JOIN caravans ON pilgrims.caravan_id = caravans.id WHERE pilgrims.id = ? LIMIT 1");
        $stmt_p->execute([$pilgrim_id]);
        $target_pilgrim = $stmt_p->fetch();

        if (!$target_pilgrim || empty($target_pilgrim['phone'])) {
            $err = 'شماره همراه معتبری برای این زائر یافت نشد.';
        } else {
            // جایگزینی هوشمند متغیرهای پویا
            $balance = max(0, (float)$target_pilgrim['final_price'] - (float)$target_pilgrim['paid_amount']);
            $final_msg = str_replace(
                ['{name}', '{fullname}', '{national_code}', '{caravan}', '{remaining}'],
                [$target_pilgrim['fullname'], $target_pilgrim['fullname'], $target_pilgrim['national_code'], $target_pilgrim['caravan_title'], number_format($balance) . ' تومان'],
                $sms_content
            );

            send_sms_notification($conn, $target_pilgrim['phone'], $target_pilgrim['fullname'], $final_msg, 'manual_single');
            $msg = "پیامک اختصاصی با موفقیت برای «{$target_pilgrim['fullname']}» ارسال شد.";
        }
    }
}

// ۲. ارسال دستی و گروهی پیامک با جایگزینی خودکار متغیرها
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_group_sms'])) {
    $target_caravan = (int)($_POST['caravan_id'] ?? 0);
    $target_status = safe($_POST['target_status'] ?? 'verified');
    $sms_content = trim($_POST['sms_content'] ?? '');

    if (empty($sms_content)) {
        $err = 'متن پیامک گروهی نمی‌تواند خالی باشد.';
    } else {
        $cond_parts = [];
        $prms = [];

        if ($target_caravan > 0) {
            $cond_parts[] = "pilgrims.caravan_id = ?";
            $prms[] = $target_caravan;
        }

        if ($target_status !== 'all') {
            $cond_parts[] = "pilgrims.status = ?";
            $prms[] = $target_status;
        } else {
            $cond_parts[] = "pilgrims.status != 'cancelled'";
        }

        $where_clause = "WHERE " . implode(" AND ", $cond_parts);

        $stmt_targets = $conn->prepare("SELECT pilgrims.*, caravans.title AS caravan_title FROM pilgrims JOIN caravans ON pilgrims.caravan_id = caravans.id $where_clause");
        $stmt_targets->execute($prms);
        $recipients = $stmt_targets->fetchAll();

        if (empty($recipients)) {
            $err = 'هیچ زائر واجد شرایطی با این فیلترها برای ارسال پیامک یافت نشد.';
        } else {
            $sent_count = 0;
            foreach ($recipients as $r) {
                if (!empty($r['phone'])) {
                    // محاسبه مقادیر پویا برای هر زائر به صورت مجزا
                    $balance = max(0, (float)$r['final_price'] - (float)$r['paid_amount']);
                    $personalized_msg = str_replace(
                        ['{name}', '{fullname}', '{national_code}', '{caravan}', '{remaining}'],
                        [$r['fullname'], $r['fullname'], $r['national_code'], $r['caravan_title'], number_format($balance) . ' تومان'],
                        $sms_content
                    );

                    send_sms_notification($conn, $r['phone'], $r['fullname'], $personalized_msg, 'manual_group');
                    $sent_count++;
                }
            }
            $msg = "پیامک گروهی هوشمند با موفقیت برای {$sent_count} مخاطب شخصی‌سازی و ارسال گردید.";
        }
    }
}

// ۳. تنظیمات درگاه پیامک
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_sms_gateway']) && has_role(['super_admin'])) {
    $provider = safe($_POST['sms_provider'] ?? 'none');
    $api_key = trim($_POST['sms_api_key'] ?? '');
    $sender_line = trim($_POST['sms_sender_line'] ?? '');
    $sms_username = trim($_POST['sms_username'] ?? '');
    $sms_password = trim($_POST['sms_password'] ?? '');

    $save_stmt = $conn->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    $save_stmt->execute(['sms_provider', $provider]);
    $save_stmt->execute(['sms_api_key', $api_key]);
    $save_stmt->execute(['sms_sender_line', $sender_line]);
    $save_stmt->execute(['sms_username', $sms_username]);
    $save_stmt->execute(['sms_password', $sms_password]);

    $msg = 'تنظیمات درگاه وب‌سرویس پیامک با موفقیت ذخیره شد.';
}

$settings = get_settings($conn);
$all_caravans = $conn->query("SELECT id, title, start_date FROM caravans ORDER BY id DESC")->fetchAll();
$all_pilgrims_list = $conn->query("SELECT id, fullname, national_code, phone FROM pilgrims WHERE status != 'cancelled' ORDER BY fullname ASC")->fetchAll();

$log_search = trim($_GET['log_search'] ?? '');
$log_where = "";
$log_params = [];
if (!empty($log_search)) {
    $log_where = "WHERE receiver_name LIKE ? OR receiver_phone LIKE ? OR message LIKE ?";
    $like = "%{$log_search}%";
    $log_params = [$like, $like, $like];
}

$stmt_logs = $conn->prepare("SELECT * FROM sms_logs $log_where ORDER BY id DESC LIMIT 30");
$stmt_logs->execute($log_params);
$logs = $stmt_logs->fetchAll();

require_once __DIR__ . '/header.php';
?>

<div class="space-y-8 max-w-6xl mx-auto text-xs">

    <!-- نوار عنوان -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center pb-4 border-b border-amber-500/20 gap-3">
        <div>
            <h1 class="text-xl sm:text-2xl font-black text-white flex items-center gap-2">
                <span>📱</span> سامانه هوشمند پیامک و شخصی‌سازی متن‌ها
            </h1>
            <p class="text-xs text-amber-200/80 mt-1">ارسال پیامک‌های گروهی و تکی با قابلیت جایگزینی خودکار نام، کد ملی و مانده‌حساب زائر</p>
        </div>
        <span class="text-xs text-emerald-300 font-bold bg-[#031712] border border-emerald-500/30 px-3.5 py-1.5 rounded-xl flex items-center gap-1.5">
            <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span> درگاه پیامک فعال است
        </span>
    </div>

    <?php if ($msg): ?>
        <div class="p-3.5 bg-emerald-950/60 border border-emerald-500/40 text-emerald-200 rounded-2xl font-bold flex items-center gap-2">
            <span>✅</span> <?= safe($msg) ?>
        </div>
    <?php endif; ?>

    <?php if ($err): ?>
        <div class="p-3.5 bg-rose-950/60 border border-rose-500/40 text-rose-200 rounded-2xl font-bold flex items-center gap-2">
            <span>❌</span> <?= safe($err) ?>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

        <!-- ستون سمت راست: ارسال گروهی و تکی (۷ ستون) -->
        <div class="lg:col-span-7 space-y-6">
            
            <!-- بخش ۱: ارسال پیامک تکی با متغیرها -->
            <div class="rogh-card rounded-3xl p-6 border border-amber-500/30 space-y-4">
                <div class="flex items-center justify-between pb-2 border-b border-amber-500/20">
                    <h2 class="font-black text-sm text-amber-300 flex items-center gap-2">
                        <span>👤</span> ارسال پیامک تکی و اختصاصی
                    </h2>
                </div>

                <form action="sms.php" method="POST" class="space-y-3.5">
                    <input type="hidden" name="send_single_sms" value="1">

                    <div>
                        <label class="block font-bold text-slate-200 mb-1">انتخاب مسافر:</label>
                        <select name="pilgrim_id" required class="w-full bg-[#031712] border border-amber-500/30 rounded-xl p-2.5 text-white">
                            <option value="">-- جستجو یا انتخاب زائر --</option>
                            <?php foreach ($all_pilgrims_list as $pl): ?>
                                <option value="<?= $pl['id'] ?>"><?= safe($pl['fullname']) ?> (کدملی: <?= safe($pl['national_code']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block font-bold text-slate-200 mb-1">متن پیامک (با قابلیت استفاده از متغیرها):</label>
                        <textarea name="sms_content" rows="3" required placeholder="باسلام {fullname} گرامی، کد ملی شما {national_code} است..." 
                                  class="w-full bg-[#031712] border border-amber-500/30 rounded-xl p-3 text-white leading-relaxed focus:outline-none focus:border-amber-400"></textarea>
                    </div>

                    <button type="submit" class="w-full bg-gradient-to-r from-teal-500 to-teal-600 hover:from-teal-400 text-slate-950 font-black py-2.5 rounded-xl transition shadow-md">
                        ارسال پیامک تکی هوشمند &larr;
                    </button>
                </form>
            </div>

            <!-- بخش ۲: ارسال پیامک گروهی پویا -->
            <div class="rogh-card rounded-3xl p-6 border border-amber-500/30 space-y-4">
                <div class="flex items-center justify-between pb-2 border-b border-amber-500/20">
                    <h2 class="font-black text-sm text-white flex items-center gap-2">
                        <span>📤</span> ارسال پیامک گروهی پویا و هدفمند
                    </h2>
                </div>

                <!-- راهنمای متغیرهای پویا -->
                <div class="p-3 bg-[#031712] rounded-2xl border border-amber-500/20 text-[11px] text-amber-200/90 space-y-1">
                    <strong class="text-amber-300 block">💡 راهنمای کلمات کلیدی هوشمند در متن پیامک:</strong>
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-1 font-mono pt-1 text-[10px]">
                        <span>{fullname} : نام زائر</span>
                        <span>{national_code} : کد ملی</span>
                        <span>{caravan} : نام کاروان</span>
                        <span>{remaining} : مانده بدهی</span>
                    </div>
                </div>

                <form action="sms.php" method="POST" class="space-y-3.5">
                    <input type="hidden" name="send_group_sms" value="1">

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block font-bold text-slate-200 mb-1">انتخاب کاروان:</label>
                            <select name="caravan_id" class="w-full bg-[#031712] border border-amber-500/30 rounded-xl p-2.5 text-white">
                                <option value="0">تمام کاروان‌ها (کل سیستم)</option>
                                <?php foreach ($all_caravans as $c): ?>
                                    <option value="<?= $c['id'] ?>"><?= safe($c['title']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label class="block font-bold text-slate-200 mb-1">وضعیت هدف زائران:</label>
                            <select name="target_status" class="w-full bg-[#031712] border border-amber-500/30 rounded-xl p-2.5 text-amber-300 font-bold">
                                <option value="verified">✅ فقط تأییدشدگان قطعی</option>
                                <option value="pending">⏳ معلق (در انتظار بررسی)</option>
                                <option value="all">👥 همه مسافران</option>
                            </select>
                        </div>
                    </div>

                    <div class="space-y-1">
                        <label class="block font-bold text-slate-300">الگوهای آماده پویا:</label>
                        <div class="flex flex-wrap gap-1">
                            <button type="button" onclick="setTemplate('باسلام {fullname} گرامی، ثبت‌نام شما در {caravan} ثبت شد. کد ملی: {national_code}')" class="bg-[#031712] text-amber-300 border border-amber-500/30 px-2 py-1 rounded text-[10px]">الگوی ثبت‌نام</button>
                            <button type="button" onclick="setTemplate('زائر گرامی {fullname}، مبلغ مانده‌حساب شما جهت تسویه در {caravan} برابر با {remaining} می‌باشد.')" class="bg-[#031712] text-emerald-300 border border-amber-500/30 px-2 py-1 rounded text-[10px]">یادآوری تسویه حساب</button>
                        </div>
                    </div>

                    <div>
                        <label class="block font-bold text-slate-200 mb-1">متن پیامک گروهی:</label>
                        <textarea id="sms_content_box" name="sms_content" rows="3" required placeholder="باسلام {fullname} گرامی در کاروان {caravan}..." 
                                  class="w-full bg-[#031712] border border-amber-500/30 rounded-xl p-3 text-white leading-relaxed focus:outline-none focus:border-amber-400"></textarea>
                    </div>

                    <button type="submit" onclick="return confirm('آیا از ارسال پیامک گروهی هوشمند اطمینان دارید؟');" 
                            class="w-full bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-400 text-slate-950 font-black py-2.5 rounded-xl transition shadow-md">
                        ارسال گروهی هوشمند با جایگزینی متغیرها &larr;
                    </button>
                </form>
            </div>

        </div>

        <!-- ستون سمت چپ: تنظیمات درگاه (۵ ستون) -->
        <div class="lg:col-span-5 rogh-card rounded-3xl p-6 border border-amber-500/30 space-y-4">
            <div class="flex items-center justify-between pb-2 border-b border-amber-500/20">
                <h2 class="font-black text-sm text-white flex items-center gap-2">
                    <span>⚙️</span> تنظیمات درگاه پیامک
                </h2>
            </div>

            <?php if (has_role(['super_admin'])): ?>
                <form action="sms.php" method="POST" class="space-y-3">
                    <input type="hidden" name="save_sms_gateway" value="1">

                    <div>
                        <label class="block font-bold text-slate-200 mb-1">سامانه ارائه‌دهنده درگاه:</label>
                        <select name="sms_provider" class="w-full bg-[#031712] border border-amber-500/30 rounded-xl p-2.5 text-white">
                            <option value="none" <?= ($settings['sms_provider'] ?? '') === 'none' ? 'selected' : '' ?>>شبیه‌ساز داخلی (ثبت در دیتابیس)</option>
                            <option value="ippanel" <?= ($settings['sms_provider'] ?? '') === 'ippanel' ? 'selected' : '' ?>>آی‌پی‌پنل / فراز اس‌ام‌اس (IPPanel)</option>
                            <option value="kavenegar" <?= ($settings['sms_provider'] ?? '') === 'kavenegar' ? 'selected' : '' ?>>کاوه‌نگار (Kavenegar)</option>
                        </select>
                    </div>

                    <div>
                        <label class="block font-bold text-slate-200 mb-1">کلید API یا توکن امنیتی:</label>
                        <input type="text" name="sms_api_key" value="<?= safe($settings['sms_api_key'] ?? '') ?>" placeholder="کلید وب‌سرویس..." 
                               class="w-full bg-[#031712] border border-amber-500/30 rounded-xl p-2 text-white font-mono text-left">
                    </div>

                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="block font-bold text-slate-200 mb-1">نام کاربری:</label>
                            <input type="text" name="sms_username" value="<?= safe($settings['sms_username'] ?? '') ?>" 
                                   class="w-full bg-[#031712] border border-amber-500/30 rounded-xl p-2 text-white font-mono">
                        </div>
                        <div>
                            <label class="block font-bold text-slate-200 mb-1">کلمه عبور:</label>
                            <input type="password" name="sms_password" value="<?= safe($settings['sms_password'] ?? '') ?>" 
                                   class="w-full bg-[#031712] border border-amber-500/30 rounded-xl p-2 text-white font-mono">
                        </div>
                    </div>

                    <div>
                        <label class="block font-bold text-slate-200 mb-1">شماره خط ارسال‌کننده:</label>
                        <input type="text" name="sms_sender_line" value="<?= safe($settings['sms_sender_line'] ?? '') ?>" placeholder="مثال: 50004000..." 
                               class="w-full bg-[#031712] border border-amber-500/30 rounded-xl p-2 text-white font-mono">
                    </div>

                    <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-500 text-white font-black py-2.5 rounded-xl transition shadow-md">
                        ذخیره تنظیمات درگاه
                    </button>
                </form>
            <?php else: ?>
                <div class="p-4 bg-slate-900 rounded-xl text-slate-400 text-center">
                    تنظیمات درگاه پیامک صرفاً برای مدیر ارشد قابل ویرایش است.
                </div>
            <?php endif; ?>
        </div>

    </div>

    <!-- بخش ۳: تاریخچه و لاگ پیشرفته پیامک‌ها -->
    <div class="rogh-card rounded-3xl overflow-hidden border border-amber-500/30 space-y-4">
        <div class="p-4 sm:p-5 border-b border-amber-500/20 flex flex-col sm:flex-row justify-between items-start sm:items-center bg-[#031511] gap-3">
            <h2 class="font-black text-xs sm:text-sm text-white flex items-center gap-2">
                <span>📜</span> تاریخچه و مانیتورینگ رویدادهای پیامکی سیستم
            </h2>

            <form method="GET" action="sms.php" class="flex gap-2 w-full sm:w-auto">
                <input type="text" name="log_search" value="<?= safe($log_search) ?>" placeholder="جستجو در گیرنده یا متن..." 
                       class="bg-[#031712] border border-amber-500/30 rounded-xl px-3 py-1.5 text-xs text-white w-full sm:w-60">
                <button type="submit" class="bg-amber-500 text-slate-950 font-bold px-4 py-1.5 rounded-xl text-xs">جستجو</button>
                <?php if(!empty($log_search)): ?>
                    <a href="sms.php" class="bg-slate-800 text-slate-300 px-3 py-1.5 rounded-xl text-xs font-bold flex items-center">حذف فیلتر</a>
                <?php endif; ?>
            </form>
        </div>

        <div class="overflow-x-auto px-4 pb-4">
            <table class="w-full text-right border-collapse text-xs">
                <thead>
                    <tr class="bg-[#02100d] text-amber-200/80 border-b border-amber-500/10">
                        <th class="p-3.5" style="width: 40px;">#</th>
                        <th class="p-3.5">دریافت‌کننده</th>
                        <th class="p-3.5">شماره همراه</th>
                        <th class="p-3.5">نوع رویداد</th>
                        <th class="p-3.5">متن پیامک ارسالی</th>
                        <th class="p-3.5 text-center">زمان ارسال</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-amber-500/10">
                    <?php if (empty($logs)): ?>
                        <tr><td colspan="6" class="text-center py-10 text-slate-500">هیچ سابقه پیامکی در سیستم ثبت نشده است.</td></tr>
                    <?php else: ?>
                        <?php foreach ($logs as $idx => $lg): ?>
                            <tr class="hover:bg-white/5 transition">
                                <td class="p-3.5 text-slate-400 font-mono"><?= $idx + 1 ?></td>
                                <td class="p-3.5 font-bold text-white"><?= safe($lg['receiver_name']) ?></td>
                                <td class="p-3.5 font-mono text-amber-300"><?= safe($lg['receiver_phone']) ?></td>
                                <td class="p-3.5">
                                    <?php
                                        $type_badge = match($lg['event_type']) {
                                            'register' => '<span class="bg-amber-500/20 text-amber-300 border border-amber-500/40 px-2 py-0.5 rounded text-[10px]">ثبت‌نام اولیه</span>',
                                            'verified' => '<span class="bg-emerald-500/20 text-emerald-300 border border-emerald-500/40 px-2 py-0.5 rounded text-[10px]">تأیید مدارک</span>',
                                            'rejected' => '<span class="bg-rose-500/20 text-rose-300 border border-rose-500/40 px-2 py-0.5 rounded text-[10px]">رد مدارک</span>',
                                            'cancelled' => '<span class="bg-purple-500/20 text-purple-300 border border-purple-500/40 px-2 py-0.5 rounded text-[10px]">لغو ثبت‌نام</span>',
                                            'manual_single' => '<span class="bg-teal-500/20 text-teal-300 border border-teal-500/40 px-2 py-0.5 rounded text-[10px]">تکی اختصاصی</span>',
                                            default => '<span class="bg-sky-500/20 text-sky-300 border border-sky-500/40 px-2 py-0.5 rounded text-[10px]">گروهی هوشمند</span>'
                                        };
                                        echo $type_badge;
                                    ?>
                                </td>
                                <td class="p-3.5 text-slate-300 max-w-md line-clamp-2" title="<?= safe($lg['message']) ?>">
                                    <?= nl2br(safe($lg['message'])) ?>
                                </td>
                                <td class="p-3.5 text-center text-slate-400 font-mono text-[10px] whitespace-nowrap">
                                    <?= safe($lg['sent_at']) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<script>
function setTemplate(text) {
    document.getElementById('sms_content_box').value = text;
}
</script>

<?php require_once __DIR__ . '/footer.php'; ?>