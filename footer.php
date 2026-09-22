<?php
if (!isset($settings) && isset($conn)) {
    $settings = get_settings($conn);
}
?>
<footer class="bg-[#020d0b] border-t border-amber-500/20 text-slate-300 text-xs mt-16 pt-12 pb-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-8 mb-10">
            
            <div class="space-y-4 md:col-span-1">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-amber-400 to-amber-600 p-[1.5px] shrink-0">
                        <div class="w-full h-full bg-[#051f19] rounded-[10px] flex items-center justify-center p-1 overflow-hidden">
                            <?php if (!empty($settings['site_logo']) && file_exists(__DIR__ . '/uploads/' . $settings['site_logo'])): ?>
                                <img src="uploads/<?= safe($settings['site_logo']) ?>" alt="لوگو" class="max-h-full max-w-full object-contain">
                            <?php else: ?>
                                <span class="text-xl text-amber-400">🕌</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <span class="font-black text-white text-sm">
                        <?= safe($settings['site_title'] ?? 'کاروان زیارتی محبان اهل‌بیت (ع)') ?>
                    </span>
                </div>
                <p class="text-slate-400 text-[11px] leading-relaxed">
                    <?= safe($settings['site_tagline'] ?? 'مجری تخصصی تورهای عتبات عالیات و مشهد مقدس') ?>
                </p>
                <?php if (!empty($settings['site_description'])): ?>
                    <p class="text-slate-400 text-[11px] leading-relaxed">
                        <?= safe($settings['site_description']) ?>
                    </p>
                <?php endif; ?>
            </div>

            <div class="space-y-3">
                <h4 class="text-white font-black text-xs border-b border-amber-500/20 pb-2 flex items-center gap-1.5">
                    <span class="text-amber-400">✦</span> دسترسی سریع
                </h4>
                <ul class="space-y-2 text-[11px]">
                    <li><a href="index.php" class="hover:text-amber-300 transition">صفحه اصلی و کاروان‌ها</a></li>
                    <li><a href="tracking.php" class="hover:text-amber-300 transition">استعلام وضعیت با کد ملی</a></li>
                    <li><a href="index.php#caravans_section" class="hover:text-amber-300 transition">کاروان‌های در حال پذیرش</a></li>
                    <li><a href="admin/login.php" class="text-slate-400 hover:text-white transition">ورود خادمین و مدیران</a></li>
                </ul>
            </div>

            <div class="space-y-3">
                <h4 class="text-white font-black text-xs border-b border-amber-500/20 pb-2 flex items-center gap-1.5">
                    <span class="text-amber-400">✦</span> کانال‌های اطلاع‌رسانی
                </h4>
                <p class="text-[11px] text-slate-400">تصاویر، جلسات توجیهی و اطلاعیه‌های حرکت:</p>
                <div class="flex flex-wrap gap-2 pt-1">
                    <?php if (!empty($settings['eitaa_channel'])): ?>
                        <a href="https://<?= safe(ltrim($settings['eitaa_channel'], 'https://')) ?>" target="_blank" class="bg-[#041c16] hover:bg-amber-500/20 text-amber-300 border border-amber-500/30 px-3 py-1.5 rounded-xl text-[11px] font-bold transition">🔶 ایتا</a>
                    <?php endif; ?>
                    <?php if (!empty($settings['bale_channel'])): ?>
                        <a href="https://<?= safe(ltrim($settings['bale_channel'], 'https://')) ?>" target="_blank" class="bg-[#041c16] hover:bg-emerald-500/20 text-emerald-300 border border-emerald-500/30 px-3 py-1.5 rounded-xl text-[11px] font-bold transition">🟢 بله</a>
                    <?php endif; ?>
                    <?php if (!empty($settings['telegram_channel'])): ?>
                        <a href="https://<?= safe(ltrim($settings['telegram_channel'], 'https://')) ?>" target="_blank" class="bg-[#041c16] hover:bg-teal-500/20 text-teal-300 border border-teal-500/30 px-3 py-1.5 rounded-xl text-[11px] font-bold transition">✈️ تلگرام</a>
                    <?php endif; ?>
                    <?php if (!empty($settings['instagram_page'])): ?>
                        <a href="https://<?= safe(ltrim($settings['instagram_page'], 'https://')) ?>" target="_blank" class="bg-[#041c16] hover:bg-rose-500/20 text-rose-300 border border-rose-500/30 px-3 py-1.5 rounded-xl text-[11px] font-bold transition">📸 اینستاگرام</a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="space-y-3">
                <h4 class="text-white font-black text-xs border-b border-amber-500/20 pb-2 flex items-center gap-1.5">
                    <span class="text-amber-400">✦</span> ارتباط با دفتر کاروان
                </h4>
                <div class="space-y-2 text-[11px]">
                    <?php if (!empty($settings['phone_number'])): ?>
                        <div class="flex items-center gap-2">
                            <span class="text-amber-400">☎️</span>
                            <span class="text-slate-400">تلفن دفتر:</span>
                            <a href="tel:<?= safe($settings['phone_number']) ?>" class="font-mono text-white hover:text-amber-300 font-bold"><?= safe($settings['phone_number']) ?></a>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($settings['mobile_number'])): ?>
                        <div class="flex items-center gap-2">
                            <span class="text-amber-400">📱</span>
                            <span class="text-slate-400">همراه مدیر:</span>
                            <a href="tel:<?= safe($settings['mobile_number']) ?>" class="font-mono text-amber-200 hover:text-white font-bold"><?= safe($settings['mobile_number']) ?></a>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($settings['address'])): ?>
                        <div class="flex items-start gap-2 pt-1">
                            <span class="text-amber-400 mt-0.5">📍</span>
                            <span class="text-slate-400 leading-relaxed"><?= safe($settings['address']) ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>

        <div class="border-t border-white/5 pt-6 flex flex-col sm:flex-row justify-between items-center gap-3 text-[11px] text-slate-500">
            <div>تمامی حقوق مادی و معنوی متعلق به <strong class="text-amber-300"><?= safe($settings['site_title'] ?? 'کاروان زیارتی') ?></strong> می‌باشد.</div>
            <a href="https://mehraani.ir" target="_blank" rel="noopener noreferrer">طراحی و توسعه: ابوالفضل مهرانی (mehraani.ir)</a>
        </div>
    </div>
</footer>