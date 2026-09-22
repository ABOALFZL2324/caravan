<?php
require_once __DIR__ . '/header.php';

// آمار کلی داشبورد
$total_pilgrims = $conn->query("SELECT COUNT(*) FROM pilgrims WHERE status != 'cancelled'")->fetchColumn() ?: 0;
$verified_pilgrims = $conn->query("SELECT COUNT(*) FROM pilgrims WHERE status = 'verified'")->fetchColumn() ?: 0;
$pending_pilgrims = $conn->query("SELECT COUNT(*) FROM pilgrims WHERE status = 'pending'")->fetchColumn() ?: 0;
$active_caravans_count = $conn->query("SELECT COUNT(*) FROM caravans WHERE status = 'active'")->fetchColumn() ?: 0;

// جمع مبالغ وصولی
$total_revenue = $conn->query("SELECT SUM(final_price) FROM pilgrims WHERE status = 'verified'")->fetchColumn() ?: 0;

// آخرین ۵ زائر ثبت‌نامی
$recent_pilgrims = $conn->query("SELECT pilgrims.*, caravans.title AS caravan_title 
                                 FROM pilgrims 
                                 JOIN caravans ON pilgrims.caravan_id = caravans.id 
                                 ORDER BY pilgrims.id DESC LIMIT 5")->fetchAll();
?>
    <!--آیکون سایت-->
    <link rel="icon" href=""></div>
<div class="space-y-6">

    <!-- نوار خوش‌آمدگویی کتیبه‌ای -->
    <div class="rogh-card rounded-2xl p-5 sm:p-6 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
            <div class="inline-flex items-center gap-1.5 text-xs text-amber-300 font-bold mb-1">
                <span>🕌</span> سامانه هوشمند مدیریت اعزام زائران
            </div>
            <h1 class="text-lg sm:text-xl font-black text-white">
                پیشخوان مدیریت کاروان زیارتی
            </h1>
            <p class="text-xs text-slate-300 mt-0.5">خلاصه وضعیت کاروان‌ها و زائران در یک نگاه</p>
        </div>

        <div class="flex items-center gap-2">
            <a href="caravans.php" class="bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-400 hover:to-amber-500 text-slate-950 font-black text-xs px-4 py-2.5 rounded-xl transition shadow-md shadow-amber-600/20">
                ➕ افزودن کاروان جدید
            </a>
        </div>
    </div>

    <!-- کارت‌های آماری شمسه‌ای (Widgets) -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        
        <!-- کل زائران -->
        <div class="rogh-card p-4 rounded-2xl space-y-2">
            <div class="flex justify-between items-center">
                <span class="text-xs text-slate-300 font-bold">کل زائران ثبت‌نامی:</span>
                <span class="text-xl">👥</span>
            </div>
            <div class="text-2xl font-black text-white font-mono"><?= number_format($total_pilgrims) ?> <span class="text-xs font-normal text-slate-400">نفر</span></div>
            <div class="text-[11px] text-emerald-400">تأیید نهایی: <?= $verified_pilgrims ?> نفر</div>
        </div>

        <!-- مدارک در انتظار بررسی -->
        <div class="rogh-card p-4 rounded-2xl space-y-2 border-amber-500/40">
            <div class="flex justify-between items-center">
                <span class="text-xs text-slate-300 font-bold">در انتظار بررسی مدارک:</span>
                <span class="text-xl">⏳</span>
            </div>
            <div class="text-2xl font-black text-amber-400 font-mono"><?= number_format($pending_pilgrims) ?> <span class="text-xs font-normal text-slate-400">مورد</span></div>
            <div class="text-[11px] text-amber-300/80">نیازمند بررسی خادم</div>
        </div>

        <!-- کاروان‌های فعال -->
        <div class="rogh-card p-4 rounded-2xl space-y-2">
            <div class="flex justify-between items-center">
                <span class="text-xs text-slate-300 font-bold">کاروان‌های فعال:</span>
                <span class="text-xl">🧭</span>
            </div>
            <div class="text-2xl font-black text-teal-300 font-mono"><?= number_format($active_caravans_count) ?> <span class="text-xs font-normal text-slate-400">کاروان</span></div>
            <div class="text-[11px] text-slate-400">در حال نام‌نویسی</div>
        </div>

        <!-- مبالغ وصولی -->
        <div class="rogh-card p-4 rounded-2xl space-y-2">
            <div class="flex justify-between items-center">
                <span class="text-xs text-slate-300 font-bold">مجموع مبالغ تاییدشده:</span>
                <span class="text-xl">💳</span>
            </div>
            <div class="text-xl font-black text-emerald-300 font-mono"><?= number_format($total_revenue) ?> <span class="text-[10px] font-normal text-slate-400">تومان</span></div>
            <div class="text-[11px] text-emerald-400/80">وصول‌شده قطعی</div>
        </div>

    </div>

    <!-- جدول آخرین ثبت‌نام‌ها -->
    <div class="rogh-card rounded-2xl overflow-hidden">
        <div class="p-4 border-b border-amber-500/20 flex justify-between items-center">
            <div class="flex items-center gap-2">
                <span class="text-amber-400 font-bold text-sm">✦</span>
                <h2 class="font-black text-xs sm:text-sm text-white">آخرین ثبت‌نام‌های انجام‌شده</h2>
            </div>
            <a href="pilgrims.php" class="text-xs text-amber-300 hover:text-white font-bold transition">
                مشاهده همه زائران &larr;
            </a>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-right border-collapse text-xs">
                <thead>
                    <tr class="bg-[#031511] text-amber-200/80 border-b border-amber-500/10">
                        <th class="p-3">نام و نام خانوادگی</th>
                        <th class="p-3">کاروان</th>
                        <th class="p-3">کد ملی</th>
                        <th class="p-3">شماره تماس</th>
                        <th class="p-3">هزینه محاسبه‌شده</th>
                        <th class="p-3 text-center">وضعیت مدارک</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-amber-500/10">
                    <?php if (empty($recent_pilgrims)): ?>
                        <tr><td colspan="6" class="text-center py-6 text-slate-500">هنوز زائری در سامانه ثبت‌نام نکرده است.</td></tr>
                    <?php else: ?>
                        <?php foreach ($recent_pilgrims as $p): ?>
                            <tr class="hover:bg-white/5 transition">
                                <td class="p-3 font-bold text-white"><?= safe($p['fullname']) ?></td>
                                <td class="p-3 text-slate-300"><?= safe($p['caravan_title']) ?></td>
                                <td class="p-3 font-mono text-amber-200"><?= safe($p['national_code']) ?></td>
                                <td class="p-3 font-mono text-slate-300"><?= safe($p['phone']) ?></td>
                                <td class="p-3 font-mono text-emerald-400 font-bold"><?= number_format($p['final_price']) ?> تومان</td>
                                <td class="p-3 text-center">
                                    <?php if ($p['status'] === 'verified'): ?>
                                        <span class="bg-emerald-500/20 text-emerald-300 border border-emerald-500/30 px-2 py-0.5 rounded-lg text-[10px] font-bold">تأیید شده</span>
                                    <?php elseif ($p['status'] === 'pending'): ?>
                                        <span class="bg-amber-500/20 text-amber-300 border border-amber-500/30 px-2 py-0.5 rounded-lg text-[10px] font-bold">در انتظار بررسی</span>
                                    <?php elseif ($p['status'] === 'cancelled'): ?>
                                        <span class="bg-slate-700 text-slate-300 px-2 py-0.5 rounded-lg text-[10px]">کنسل‌شده</span>
                                    <?php else: ?>
                                        <span class="bg-rose-500/20 text-rose-300 border border-rose-500/30 px-2 py-0.5 rounded-lg text-[10px] font-bold">رد شده</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<?php require_once __DIR__ . '/footer.php'; ?>