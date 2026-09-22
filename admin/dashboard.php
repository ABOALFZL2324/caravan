<?php
ob_start();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/auth.php';

require_roles(['super_admin', 'registrar', 'finance', 'leader']);

$settings = get_settings($conn);

// ۱. آمارهای کلی و کلیدی سیستم
$total_caravans = $conn->query("SELECT COUNT(*) FROM caravans")->fetchColumn();
$active_caravans = $conn->query("SELECT COUNT(*) FROM caravans WHERE status = 'active'")->fetchColumn();
$total_pilgrims = $conn->query("SELECT COUNT(*) FROM pilgrims WHERE status != 'cancelled'")->fetchColumn();
$pending_pilgrims = $conn->query("SELECT COUNT(*) FROM pilgrims WHERE status = 'pending'")->fetchColumn();
$verified_pilgrims = $conn->query("SELECT COUNT(*) FROM pilgrims WHERE status = 'verified'")->fetchColumn();
$rejected_pilgrims = $conn->query("SELECT COUNT(*) FROM pilgrims WHERE status = 'rejected'")->fetchColumn();

// ۲. آمار مالی (مجموع هزینه‌ها و واریزی‌ها)
$financial_stats = $conn->query("SELECT SUM(final_price) AS total_price, SUM(paid_amount) AS total_paid FROM pilgrims WHERE status != 'cancelled'")->fetch();
$total_expected_revenue = (float)($financial_stats['total_price'] ?? 0);
$total_collected_revenue = (float)($financial_stats['total_paid'] ?? 0);

// ۳. آخرین ثبت‌نام‌های انجام شده
$recent_pilgrims = $conn->query("SELECT pilgrims.*, caravans.title AS caravan_title FROM pilgrims JOIN caravans ON pilgrims.caravan_id = caravans.id ORDER BY pilgrims.id DESC LIMIT 6")->fetchAll();

// ۴. آخرین پیامک‌های ارسالی سیستم (لاگ‌ها)
$recent_sms = [];
try {
    $recent_sms = $conn->query("SELECT * FROM sms_logs ORDER BY id DESC LIMIT 5")->fetchAll();
} catch (Exception $e) {}

require_once __DIR__ . '/header.php';
?>

<div class="space-y-6">

    <!-- نوار خوش‌آمدگویی حرفه‌ای و میانبرها -->
    <div class="rogh-card rounded-3xl p-6 sm:p-8 border border-amber-500/30 flex flex-col lg:flex-row justify-between items-start lg:items-center gap-4">
        <div class="space-y-1">
            <span class="bg-amber-500/20 text-amber-300 border border-amber-500/30 px-3 py-1 rounded-full text-[10px] font-bold">
                پنل مدیریت جامع کاروان زیارتی
            </span>
            <h1 class="text-xl sm:text-2xl font-black text-white pt-1">
                خوش آمدید، <?= safe($_SESSION['admin_fullname'] ?? 'مدیر سیستم') ?> عزیز
            </h1>
            <p class="text-xs text-slate-300">
                نقش دسترسی: <span class="font-bold text-amber-300"><?= safe($_SESSION['admin_role'] ?? '') ?></span> | وضعیت سیستم پیامک و پایگاه داده: <span class="text-emerald-400 font-bold">● فعال و متصل</span>
            </p>
        </div>

        <div class="flex flex-wrap gap-2">
            <a href="caravans.php?action=add" class="bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-400 text-slate-950 font-black px-4 py-2.5 rounded-xl text-xs transition shadow-md flex items-center gap-1.5">
                <span>🚌</span> تعریف کاروان جدید
            </a>
            <a href="pilgrims.php?status=pending" class="bg-amber-950/60 hover:bg-amber-900/80 text-amber-200 border border-amber-500/40 font-bold px-4 py-2.5 rounded-xl text-xs transition flex items-center gap-1.5">
                <span>⏳</span> بررسی معلق‌ها (<?= $pending_pilgrims ?>)
            </a>
            <a href="sms.php" class="bg-[#031712] hover:bg-white/10 text-slate-200 border border-amber-500/30 font-bold px-4 py-2.5 rounded-xl text-xs transition flex items-center gap-1.5">
                <span>📱</span> ارسال پیامک گروهی
            </a>
        </div>
    </div>

    <!-- بلوک اول آمار کلیدی (کارت‌های آماری) -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 text-xs">
        
        <div class="rogh-card rounded-2xl p-5 border border-amber-500/30 space-y-2 relative overflow-hidden">
            <div class="flex justify-between items-center text-slate-400">
                <span>کاروان‌های فعال</span>
                <span class="text-amber-400 text-lg">🚌</span>
            </div>
            <div class="text-2xl font-black text-white font-mono"><?= number_format($active_caravans) ?> <span class="text-xs text-slate-400 font-normal">از <?= $total_caravans ?></span></div>
            <div class="text-[10px] text-amber-300/80">برنامه‌های زیارتی در حال اجرا</div>
        </div>

        <div class="rogh-card rounded-2xl p-5 border border-amber-500/30 space-y-2 relative overflow-hidden">
            <div class="flex justify-between items-center text-slate-400">
                <span>مجموع زائران ثبت‌نامی</span>
                <span class="text-amber-400 text-lg">👥</span>
            </div>
            <div class="text-2xl font-black text-amber-300 font-mono"><?= number_format($total_pilgrims) ?></div>
            <div class="text-[10px] text-slate-400">بدون احتساب موارد انصرافی</div>
        </div>

        <div class="rogh-card rounded-2xl p-5 border border-amber-500/30 space-y-2 relative overflow-hidden">
            <div class="flex justify-between items-center text-slate-400">
                <span>پرونده‌های تأییدشده</span>
                <span class="text-emerald-400 text-lg">✅</span>
            </div>
            <div class="text-2xl font-black text-emerald-400 font-mono"><?= number_format($verified_pilgrims) ?></div>
            <div class="text-[10px] text-emerald-300/80">آماده برای صدور مانیفست نهایی</div>
        </div>

        <div class="rogh-card rounded-2xl p-5 border border-amber-500/30 space-y-2 relative overflow-hidden">
            <div class="flex justify-between items-center text-slate-400">
                <span>نیازمند بازبینی مدارک</span>
                <span class="text-rose-400 text-lg">⚠️</span>
            </div>
            <div class="text-2xl font-black text-rose-400 font-mono"><?= number_format($pending_pilgrims + $rejected_pilgrims) ?></div>
            <div class="text-[10px] text-rose-300/80">معلق (<?= $pending_pilgrims ?>) و ردشده (<?= $rejected_pilgrims ?>)</div>
        </div>

    </div>

    <!-- بلوک دوم: خلاصه وضعیت مالی کل کاروان‌ها -->
    <div class="rogh-card rounded-3xl p-6 border border-emerald-500/30 space-y-4">
        <div class="flex justify-between items-center pb-3 border-b border-emerald-500/20">
            <h2 class="font-black text-xs sm:text-sm text-emerald-300 flex items-center gap-2">
                <span>💰</span> وضعیت کلان مالی و درآمدی زائران کاروان‌ها
            </h2>
            <a href="pilgrims.php" class="text-xs text-amber-300 hover:underline">مدیریت حساب‌ها &larr;</a>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 font-mono text-xs">
            <div class="bg-[#031712] p-4 rounded-2xl border border-emerald-500/20 space-y-1">
                <span class="text-slate-400 text-[11px] block font-sans">کل مبلغ فاکتور شده (تعهد مالی):</span>
                <span class="text-white text-base font-black"><?= number_format($total_expected_revenue) ?> تومان</span>
            </div>
            <div class="bg-[#031712] p-4 rounded-2xl border border-emerald-500/20 space-y-1">
                <span class="text-slate-400 text-[11px] block font-sans">مجموع واریزی‌های تأییدشده:</span>
                <span class="text-emerald-400 text-base font-black"><?= number_format($total_collected_revenue) ?> تومان</span>
            </div>
            <div class="bg-[#031712] p-4 rounded-2xl border border-emerald-500/20 space-y-1">
                <span class="text-slate-400 text-[11px] block font-sans">مانده بدهی کل زائران:</span>
                <span class="text-amber-300 text-base font-black"><?= number_format(max(0, $total_expected_revenue - $total_collected_revenue)) ?> تومان</span>
            </div>
        </div>
    </div>

    <!-- بلوک سوم: دو جدول پیشرفته (آخرین ثبت‌نام‌ها و آخرین رویدادهای پیامکی) -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        
        <!-- آخرین ثبت‌نام‌ها (۲ ستون) -->
        <div class="lg:col-span-2 rogh-card rounded-3xl overflow-hidden border border-amber-500/30 flex flex-col">
            <div class="p-4 sm:p-5 border-b border-amber-500/20 flex justify-between items-center bg-[#031511]">
                <h2 class="font-black text-xs sm:text-sm text-white flex items-center gap-2">
                    <span>📋</span> آخرین ثبت‌نام‌های انجام شده در سامانه
                </h2>
                <a href="pilgrims.php" class="text-xs text-amber-300 hover:underline font-bold">لیست کامل زائران &larr;</a>
            </div>

            <div class="overflow-x-auto flex-grow">
                <table class="w-full text-right border-collapse text-xs">
                    <thead>
                        <tr class="bg-[#02100d] text-amber-200/80 border-b border-amber-500/10">
                            <th class="p-3.5">نام زائر</th>
                            <th class="p-3.5">کد ملی</th>
                            <th class="p-3.5">کاروان</th>
                            <th class="p-3.5 text-center">وضعیت</th>
                            <th class="p-3.5 text-center">عملیات</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-amber-500/10">
                        <?php if (empty($recent_pilgrims)): ?>
                            <tr><td colspan="5" class="text-center py-8 text-slate-500">هیچ ثبت‌نامی انجام نشده است.</td></tr>
                        <?php else: ?>
                            <?php foreach ($recent_pilgrims as $rp): ?>
                                <tr class="hover:bg-white/5 transition">
                                    <td class="p-3.5 font-bold text-white"><?= safe($rp['fullname']) ?></td>
                                    <td class="p-3.5 font-mono text-amber-300"><?= safe($rp['national_code']) ?></td>
                                    <td class="p-3.5 text-slate-300 truncate max-w-[150px]"><?= safe($rp['caravan_title']) ?></td>
                                    <td class="p-3.5 text-center">
                                        <?php if ($rp['status'] === 'verified'): ?>
                                            <span class="bg-emerald-500/20 text-emerald-300 border border-emerald-500/40 px-2.5 py-0.5 rounded-lg text-[10px] font-bold">تأیید شده</span>
                                        <?php elseif ($rp['status'] === 'rejected'): ?>
                                            <span class="bg-rose-500/20 text-rose-300 border border-rose-500/40 px-2.5 py-0.5 rounded-lg text-[10px] font-bold">رد مدارک</span>
                                        <?php else: ?>
                                            <span class="bg-amber-500/20 text-amber-300 border border-amber-500/40 px-2.5 py-0.5 rounded-lg text-[10px] font-bold">در انتظار</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="p-3.5 text-center">
                                        <a href="pilgrims.php?search=<?= safe($rp['national_code']) ?>" class="text-amber-300 hover:text-white font-bold underline text-[11px]">بررسی</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- آخرین پیامک‌های سیستم (۱ ستون) -->
        <div class="rogh-card rounded-3xl overflow-hidden border border-amber-500/30 flex flex-col">
            <div class="p-4 sm:p-5 border-b border-amber-500/20 flex justify-between items-center bg-[#031511]">
                <h2 class="font-black text-xs sm:text-sm text-white flex items-center gap-2">
                    <span>📱</span> مانیتورینگ پیامک‌ها
                </h2>
                <a href="sms.php" class="text-xs text-amber-300 hover:underline">مشاهده همه &larr;</a>
            </div>

            <div class="p-4 space-y-3 flex-grow overflow-y-auto max-h-[350px] text-xs">
                <?php if (empty($recent_sms)): ?>
                    <p class="text-center py-8 text-slate-500">پیامکی ثبت نشده است.</p>
                <?php else: ?>
                    <?php foreach ($recent_sms as $sm): ?>
                        <div class="bg-[#031712] p-3 rounded-2xl border border-amber-500/20 space-y-1">
                            <div class="flex justify-between items-center text-[10px]">
                                <span class="font-bold text-amber-300"><?= safe($sm['receiver_name'] ?: $sm['receiver_phone']) ?></span>
                                <span class="text-slate-400 font-mono"><?= safe(substr($sm['sent_at'], 11, 5)) ?></span>
                            </div>
                            <p class="text-slate-300 text-[11px] line-clamp-2"><?= safe($sm['message']) ?></p>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

    </div>

</div>

<?php require_once __DIR__ . '/footer.php'; ?>