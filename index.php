<?php
require_once 'db.php';

$settings = get_settings($conn);

// ۱. واکشی کاروان‌های فعال و در حال پذیرش
$caravans = $conn->query("SELECT * FROM caravans WHERE status = 'active' ORDER BY id DESC")->fetchAll();

// ۲. واکشی اعلانات و اطلاعیه‌های فعال کاروان
$announcements = [];
try {
    $announcements = $conn->query("SELECT * FROM announcements WHERE status = 'active' ORDER BY id DESC LIMIT 5")->fetchAll();
} catch (Exception $e) {
    try {
        $announcements = $conn->query("SELECT * FROM announcements ORDER BY id DESC LIMIT 5")->fetchAll();
    } catch (Exception $e2) {
        $announcements = [];
    }
}

// ۳. واکشی پرسش‌های متداول از تنظیمات
$faqs_list = [];
if (!empty($settings['faqs_list'])) {
    $faqs_list = json_decode($settings['faqs_list'], true) ?: [];
}

// ۴. واکشی مقالات دانشنامه زیارت
$blog_posts = [];
try {
    $blog_posts = $conn->query("SELECT * FROM blog_posts WHERE status = 'published' ORDER BY id DESC LIMIT 3")->fetchAll();
} catch (Exception $e) {}

// ۵. آمار کلی سامانه
$total_pilgrims = 0;
$total_trips = 0;
try {
    $total_pilgrims = (int)$conn->query("SELECT COUNT(*) FROM pilgrims WHERE status != 'cancelled'")->fetchColumn();
    $total_trips = (int)$conn->query("SELECT COUNT(*) FROM caravans")->fetchColumn();
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= safe($settings['site_title'] ?? 'کاروان زیارتی محبان اهل‌بیت (ع)') ?> | سامانه تخصصی اعزام زائران به عتبات عالیات</title>
    
    <!-- آیکون سایت -->
    <link rel="icon" href="img/icon.png">

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- قلم اصیل وزیرمتن -->
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet">
    
    <style>
        body { 
            font-family: 'Vazirmatn', Tahoma, sans-serif; 
            background-color: #041411;
            color: #f8fafc;
        }

        .islami-bg {
            background-color: #041411;
            background-image: radial-gradient(#0f382f 1.2px, transparent 1.2px), radial-gradient(#14483c 1.2px, #041411 1.2px);
            background-size: 40px 40px;
            background-position: 0 0, 20px 20px;
        }

        .rogh-card {
            background: linear-gradient(180deg, rgba(13, 44, 38, 0.94) 0%, rgba(6, 26, 22, 0.98) 100%);
            border: 1px solid rgba(212, 175, 55, 0.28);
            box-shadow: 0 20px 40px -15px rgba(0, 0, 0, 0.7);
        }

        .gold-gradient-text {
            background: linear-gradient(135deg, #fef08a 0%, #d4af37 50%, #ca8a04 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        /* کنترل اجباری و مهار ابعاد لوگو */
        .site-logo-img {
            max-width: 44px !important;
            max-height: 44px !important;
            width: auto;
            height: auto;
            object-fit: contain !important;
        }
    </style>
</head>
<body class="islami-bg selection:bg-amber-500 selection:text-black min-h-screen flex flex-col">

    <!-- ۱. نوار متبرک بالای صفحه -->
    <div class="bg-gradient-to-r from-[#021813] via-[#053227] to-[#021813] border-b border-amber-500/20 text-[11px] py-1.5 px-4 sticky top-0 z-50">
        <div class="max-w-7xl mx-auto flex flex-col sm:flex-row justify-between items-center gap-2 text-amber-200/90 font-medium">
            <span class="flex items-center gap-2">
                <span class="text-amber-400 text-xs">🕌</span> 
                اَلسَّلامُ عَلَیْکَ یا اَبا عَبْدِاللّهِ وَ عَلَی الاَْرْواحِ الَّتی حَلَّتْ بِفِنائِکَ
            </span>
            <div class="flex items-center gap-4">
                <a href="tracking.php" class="text-amber-300 hover:text-white font-bold transition flex items-center gap-1">
                    <span>🔍</span> پیگیری ثبت‌نام با کدملی
                </a>
                <span class="text-slate-600 hidden sm:inline">|</span>
                <a href="admin/login.php" class="text-slate-400 hover:text-amber-200 transition flex items-center gap-1">
                    <span>👤</span> ورود خادمین
                </a>
            </div>
        </div>
    </div>

    <!-- ۲. هدر شیشه‌ای اصلی -->
    <header class="bg-[#051f19]/90 backdrop-blur-md border-b border-amber-500/30 sticky top-7 z-40 shadow-xl">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 h-20 flex justify-between items-center">
            
            <!-- لوگو و نام (کاملاً کنترل‌شده در کادر ۴۸ پیکسل) -->
            <a href="index.php" class="flex items-center gap-3.5 group">
                <div class="w-12 h-12 max-w-[48px] max-h-[48px] shrink-0 rounded-2xl bg-gradient-to-br from-amber-400 via-emerald-600 to-amber-600 p-[2px] shadow-lg shadow-amber-500/20 group-hover:scale-105 transition duration-300">
                    <div class="w-full h-full bg-[#031914] rounded-[14px] flex items-center justify-center p-1 overflow-hidden">
                        <?php if (!empty($settings['site_logo']) && file_exists(__DIR__ . '/uploads/' . $settings['site_logo'])): ?>
                            <img src="uploads/<?= safe($settings['site_logo']) ?>" alt="لوگو" class="site-logo-img">
                        <?php else: ?>
                            <span class="text-2xl text-amber-400">🕌</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div>
                    <span class="font-black text-white text-base sm:text-lg block tracking-tight group-hover:text-amber-300 transition">
                        <?= safe($settings['site_title'] ?? 'کاروان زیارتی محبان اهل‌بیت (ع)') ?>
                    </span>
                    <span class="text-[11px] text-emerald-300/80 font-medium block mt-0.5">
                        <?= safe($settings['site_tagline'] ?? 'مجری تخصصی سفرهای عتبات عالیات و مشهد مقدس') ?>
                    </span>
                </div>
            </a>

            <!-- ناوبری دسکتاپ -->
            <nav class="hidden lg:flex items-center gap-6 text-xs font-bold text-slate-300">
                <a href="#caravans_section" class="hover:text-amber-300 transition flex items-center gap-1.5">
                    <span>🧭</span> کاروان‌های فعال
                </a>
                <a href="#bank_info_section" class="hover:text-amber-300 transition flex items-center gap-1.5">
                    <span>💳</span> حساب‌های بانکی
                </a>
                <a href="#services_section" class="hover:text-amber-300 transition flex items-center gap-1.5">
                    <span>⭐</span> خدمات و تمایزها
                </a>
                <a href="#faq_section" class="hover:text-amber-300 transition flex items-center gap-1.5">
                    <span>❓</span> پرسش‌های متداول
                </a>
                <a href="tracking.php" class="hover:text-amber-300 transition flex items-center gap-1.5">
                    <span>🔍</span> استعلام پرونده
                </a>
            </nav>

            <!-- تماس مستقیم و دکمه رزرو -->
            <div class="flex items-center gap-3">
                <?php if (!empty($settings['mobile_number'])): ?>
                    <a href="tel:<?= safe($settings['mobile_number']) ?>" class="hidden sm:flex text-xs bg-[#0b3328] text-amber-200 border border-amber-500/30 px-3.5 py-2.5 rounded-xl font-bold items-center gap-2 hover:bg-[#0f4435] transition">
                        <span class="relative flex h-2 w-2">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                        </span>
                        <span class="font-mono"><?= safe($settings['mobile_number']) ?></span>
                    </a>
                <?php endif; ?>
                
                <a href="#caravans_section" class="bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-400 text-slate-950 font-black text-xs px-4 sm:px-5 py-2.5 rounded-xl transition shadow-lg shadow-amber-500/20">
                    رزرو صندلی کاروان
                </a>
            </div>

        </div>
    </header>

    <!-- ۳. بخش بنر اصلی قهرمان (Hero Section) -->
    <section class="relative py-14 sm:py-24 px-4 overflow-hidden border-b border-amber-500/20">
        <div class="max-w-5xl mx-auto text-center space-y-6 relative z-10">
            <span class="text-amber-300 text-xs font-black tracking-wider bg-[#0b3328] px-4 py-1.5 rounded-full border border-amber-500/30 inline-block shadow-sm">
                اعزام‌های سراسری زمینی، هوایی و VIP تحت نظارت
            </span>
            
            <h1 class="text-3xl sm:text-5xl md:text-6xl font-black text-white leading-tight">
                سفری همراه با معرفت و آرامش به <br class="hidden sm:block">
                <span class="gold-gradient-text">عتبات مقدسه عراق</span> و حریم رضوی
            </h1>

            <p class="text-xs sm:text-sm text-slate-300 max-w-2xl mx-auto leading-relaxed">
                رزرو آنلاین و شفاف کاروان، هتل‌های نزدیک به حرم مطهر در کربلا و نجف، بیمه مسافرتی کامل، آشپزخانه مرکزی با غذای درجه یک ایرانی، و همراهی مداحان و روحانیون برجسته کشور.
            </p>

            <div class="flex flex-wrap justify-center items-center gap-3.5 pt-2">
                <a href="#caravans_section" class="bg-gradient-to-r from-amber-500 via-amber-400 to-amber-500 hover:from-amber-400 text-slate-950 font-black text-xs sm:text-sm px-7 py-3.5 rounded-2xl transition shadow-xl shadow-amber-500/25">
                    مشاهده برنامه کاروان‌ها &darr;
                </a>
                <a href="tracking.php" class="bg-[#082921] hover:bg-[#0c382e] text-amber-200 border border-amber-500/30 font-bold text-xs sm:text-sm px-6 py-3.5 rounded-2xl transition">
                    استعلام وضعیت پرونده ثبت‌نامی
                </a>
            </div>

            <!-- ویژگی‌های کوتاه کلیدی -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 max-w-3xl mx-auto pt-6 text-[11px] text-slate-300">
                <div class="bg-[#031712]/80 border border-amber-500/20 p-2.5 rounded-xl flex items-center justify-center gap-1.5">
                    <span>🏨</span> هتل‌های درجه الف نزدیک حرم
                </div>
                <div class="bg-[#031712]/80 border border-amber-500/20 p-2.5 rounded-xl flex items-center justify-center gap-1.5">
                    <span>🚌</span> ناوگان اتوبوس VIP ۲۵ نفره
                </div>
                <div class="bg-[#031712]/80 border border-amber-500/20 p-2.5 rounded-xl flex items-center justify-center gap-1.5">
                    <span>🛡️</span> بیمه حوادث و درمانی زائر
                </div>
                <div class="bg-[#031712]/80 border border-amber-500/20 p-2.5 rounded-xl flex items-center justify-center gap-1.5">
                    <span>🎙️</span> مداحان و راویان مجرب
                </div>
            </div>
        </div>
    </section>

    <!-- ۴. تابلو اعلانات و اطلاعیه‌های مهم کاروان -->
    <?php if (!empty($announcements)): ?>
        <section class="max-w-7xl mx-auto px-4 sm:px-6 -mt-6 relative z-20">
            <div class="rogh-card rounded-2xl p-4 border border-amber-500/40 space-y-2.5">
                <div class="flex items-center gap-2 text-amber-400 text-xs font-black">
                    <span class="animate-bounce">📢</span> آخرین اطلاعیه‌ها و هشدارهای مهم سفر:
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-2 text-xs">
                    <?php foreach ($announcements as $ann): ?>
                        <div class="bg-[#031712] p-3 rounded-xl border border-amber-500/15 flex items-start gap-2.5">
                            <span class="text-amber-400 text-base mt-0.5">•</span>
                            <div>
                                <strong class="text-white block font-bold"><?= safe($ann['title']) ?></strong>
                                <p class="text-[11px] text-slate-300 mt-1 leading-relaxed"><?= safe($ann['content']) ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <!-- ۵. نوار آماری کاروان و افتخارات -->
    <section class="max-w-7xl mx-auto px-4 sm:px-6 pt-12">
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
            <div class="rogh-card rounded-2xl p-5 text-center border border-amber-500/20 space-y-1">
                <span class="text-2xl sm:text-3xl font-black text-amber-300 font-mono"><?= number_format(max(150, $total_trips + 120)) ?>+</span>
                <span class="text-[11px] text-slate-400 block font-bold">کاروان اعزام‌شده</span>
            </div>
            <div class="rogh-card rounded-2xl p-5 text-center border border-amber-500/20 space-y-1">
                <span class="text-2xl sm:text-3xl font-black text-emerald-300 font-mono"><?= number_format(max(4500, $total_pilgrims + 3500)) ?>+</span>
                <span class="text-[11px] text-slate-400 block font-bold">زائر عتبات و مشهد</span>
            </div>
            <div class="rogh-card rounded-2xl p-5 text-center border border-amber-500/20 space-y-1">
                <span class="text-2xl sm:text-3xl font-black text-amber-300 font-mono">۱۰۰٪</span>
                <span class="text-[11px] text-slate-400 block font-bold">پوشش کامل بیمه زائران</span>
            </div>
            <div class="rogh-card rounded-2xl p-5 text-center border border-amber-500/20 space-y-1">
                <span class="text-2xl sm:text-3xl font-black text-teal-300 font-mono">۱۲ سال</span>
                <span class="text-[11px] text-slate-400 block font-bold">سابقه خدمت مخلصانه</span>
            </div>
        </div>
    </section>

    <!-- ۶. بخش نمایش کاروان‌های جامع و فعال -->
    <main id="caravans_section" class="max-w-7xl mx-auto px-4 sm:px-6 py-14 space-y-8">
        
        <div class="text-center space-y-2">
            <span class="text-amber-400 text-xs font-black tracking-wider bg-[#0b3328] px-3.5 py-1 rounded-full border border-amber-500/30 inline-block">ثبت‌نام آنلاین</span>
            <h2 class="text-2xl sm:text-3xl font-black text-white">برنامه کاروان‌های <span class="gold-gradient-text">در حال پذیرش</span></h2>
            <p class="text-xs text-slate-400 max-w-xl mx-auto">جهت مشاهده کامل جزئیات هتل‌ها و رزرو صندلی، روی دکمه ثبت‌نام کاروان مورد نظر کلیک فرمایید</p>
        </div>

        <?php if (empty($caravans)): ?>
            <div class="rogh-card rounded-3xl p-12 text-center space-y-4 max-w-lg mx-auto border border-amber-500/30">
                <span class="text-5xl block">⏳</span>
                <h3 class="text-base font-bold text-amber-300">در حال حاضر پذیرش کلیه کاروان‌ها به اتمام رسیده است.</h3>
                <p class="text-xs text-slate-400 leading-relaxed">به زودی تاریخ اعزام کاروان‌های جدید از طریق کانال‌های اطلاع‌رسانی اعلام می‌گردد. جهت رزرو سریع می‌توانید با دفتر کاروان تماس حاصل فرمایید.</p>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <?php foreach ($caravans as $c): ?>
                    <?php 
                        $rem_cap = (int)($c['remaining_capacity'] ?? 0);
                        $tot_cap = (int)($c['capacity'] ?? $rem_cap);
                        $percent = $tot_cap > 0 ? round((($tot_cap - $rem_cap) / $tot_cap) * 100) : 0;
                        
                        $itinerary = !empty($c['itinerary']) ? (json_decode($c['itinerary'], true) ?: []) : [];
                    ?>
                    <div class="rogh-card rounded-3xl p-6 border border-amber-500/30 flex flex-col justify-between space-y-5 hover:border-amber-400/60 transition duration-300">
                        
                        <!-- بالای کارت کاروان -->
                        <div class="space-y-3 pb-4 border-b border-amber-500/20">
                            <div class="flex flex-wrap justify-between items-start gap-2">
                                <span class="bg-[#041c16] text-amber-300 border border-amber-500/30 px-3 py-1 rounded-full text-[11px] font-bold">
                                    📍 <?= safe($c['destination']) ?>
                                </span>

                                <span class="text-xs font-mono font-bold px-2.5 py-1 rounded-xl <?= $rem_cap > 0 ? 'bg-emerald-950/80 text-emerald-300 border border-emerald-500/30' : 'bg-rose-950/80 text-rose-300 border border-rose-500/30' ?>">
                                    <?= $rem_cap > 0 ? "ظرفیت خالی: $rem_cap از $tot_cap نفر" : "ظرفیت تکمیل" ?>
                                </span>
                            </div>

                            <h3 class="text-lg sm:text-xl font-black text-white leading-snug">
                                <?= safe($c['title']) ?>
                            </h3>

                            <!-- نوار پیشرفت ظرفیت -->
                            <div class="w-full bg-[#031712] rounded-full h-2 overflow-hidden border border-white/5">
                                <div class="bg-gradient-to-r from-amber-500 to-emerald-500 h-full rounded-full" style="width: <?= min(100, $percent) ?>%"></div>
                            </div>

                            <!-- زمان‌بندی و ترانسفر -->
                            <div class="grid grid-cols-2 sm:grid-cols-3 gap-2 text-xs bg-[#031712] p-3 rounded-2xl border border-amber-500/15 font-mono">
                                <div>
                                    <span class="text-slate-400 block text-[10px]">تاریخ اعزام (رفت):</span>
                                    <strong class="text-amber-200"><?= safe($c['start_date']) ?></strong>
                                </div>
                                <div>
                                    <span class="text-slate-400 block text-[10px]">تاریخ بازگشت:</span>
                                    <strong class="text-slate-200"><?= safe($c['end_date'] ?: '—') ?></strong>
                                </div>
                                <div class="col-span-2 sm:col-span-1">
                                    <span class="text-slate-400 block text-[10px]">ساعت / نحوه اعزام:</span>
                                    <strong class="text-emerald-300"><?= safe($c['departure_time'] ?: $c['trip_type']) ?></strong>
                                </div>
                            </div>
                        </div>

                        <!-- برنامه اقامت در شهرها و هتل‌ها -->
                        <?php if (!empty($itinerary)): ?>
                            <div class="space-y-2">
                                <span class="text-[11px] font-bold text-amber-300 flex items-center gap-1">
                                    <span>🏨</span> هتل‌های محل اسکان و برنامه شهرها:
                                </span>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                    <?php foreach ($itinerary as $it): ?>
                                        <div class="bg-[#051c16] p-2.5 rounded-xl border border-white/5 space-y-0.5">
                                            <div class="flex justify-between items-center text-xs">
                                                <strong class="text-white">🏛️ <?= safe($it['city']) ?></strong>
                                                <span class="text-amber-300 text-[11px] font-mono"><?= safe($it['duration']) ?></span>
                                            </div>
                                            <?php if (!empty($it['hotel'])): ?>
                                                <span class="text-[11px] text-slate-300 block">هتل: <?= safe($it['hotel']) ?></span>
                                            <?php endif; ?>
                                            <?php if (!empty($it['notes'])): ?>
                                                <span class="text-[10px] text-slate-400 block"><?= safe($it['notes']) ?></span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- ناوگان حمل‌ونقل و عوامل اجرایی -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs bg-[#031712] p-3 rounded-2xl border border-amber-500/15">
                            <div>
                                <span class="text-slate-400 block text-[10px]">وسیله نقلیه رفت:</span>
                                <strong class="text-slate-200"><?= safe($c['transport_departure'] ?: $c['trip_type']) ?></strong>
                            </div>
                            <?php if (!empty($c['leader_name'])): ?>
                                <div>
                                    <span class="text-slate-400 block text-[10px]">مداح / سرپرست:</span>
                                    <strong class="text-amber-300"><?= safe($c['leader_name']) ?></strong>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($c['food_services'])): ?>
                                <div class="sm:col-span-2 text-[11px] text-slate-300 border-t border-white/5 pt-1.5 mt-0.5">
                                    🍽️ <span class="text-slate-400">خدمات تغذیه:</span> <?= safe($c['food_services']) ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- جدول قیمت‌گذاری و دکمه رزرو -->
                        <div class="pt-3 border-t border-amber-500/20 space-y-3">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <div>
                                    <span class="text-[11px] text-slate-400 block">نرخ بزرگسال (۱۲ سال به بالا):</span>
                                    <span class="text-lg font-black text-amber-300 font-mono">
                                        <?= number_format($c['price_adult']) ?> <span class="text-xs font-normal text-slate-400">تومان</span>
                                    </span>
                                </div>
                                <?php if (!empty($c['price_child']) && $c['price_child'] > 0): ?>
                                    <div class="text-left">
                                        <span class="text-[10px] text-slate-400 block">کودک (۲ تا ۱۲ سال):</span>
                                        <span class="text-xs font-bold text-slate-300 font-mono"><?= number_format($c['price_child']) ?> ت</span>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <?php if ($rem_cap > 0): ?>
                                <a href="register.php?caravan_id=<?= $c['id'] ?>" 
                                   class="block text-center w-full bg-gradient-to-r from-amber-500 via-amber-400 to-amber-500 hover:from-amber-400 text-slate-950 font-black py-3 rounded-2xl text-xs sm:text-sm transition duration-200 shadow-lg shadow-amber-500/20">
                                    رزرو و ثبت‌نام آنلاین در این کاروان &larr;
                                </a>
                            <?php else: ?>
                                <button disabled class="w-full bg-slate-800 text-slate-500 font-bold py-3 rounded-2xl text-xs cursor-not-allowed">
                                    ظرفیت این کاروان تکمیل شده است
                                </button>
                            <?php endif; ?>
                        </div>

                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    </main>

    <!-- ۷. کتیبه شکیل اطلاعات حساب‌های بانکی معتمد کاروان -->
    <?php if (!empty($settings['bank_card_number']) || !empty($settings['bank_shaba_number'])): ?>
        <section id="bank_info_section" class="max-w-7xl mx-auto px-4 sm:px-6 py-8">
            <div class="rogh-card rounded-3xl p-6 sm:p-8 border border-amber-500/40 space-y-4">
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center pb-3 border-b border-amber-500/20 gap-2">
                    <div class="flex items-center gap-2.5">
                        <span class="text-2xl">💳</span>
                        <div>
                            <h3 class="font-black text-sm sm:text-base text-white">حساب بانکی معتمد کاروان جهت واریز بیعانه و تسویه‌حساب</h3>
                            <p class="text-[11px] text-slate-400 mt-0.5">پس از واریز، فیش پرداختی را در سامانه ثبت‌نام یا پیگیری آپلود فرمایید</p>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4 text-xs pt-1">
                    <?php if (!empty($settings['bank_card_number'])): ?>
                        <div class="bg-[#031712] p-4 rounded-2xl border border-amber-500/20 space-y-1.5">
                            <span class="text-[10px] text-slate-400 block font-bold">شماره کارت (عضو شبکه شتاب):</span>
                            <div class="flex justify-between items-center">
                                <strong class="text-amber-200 font-mono text-sm tracking-widest"><?= safe($settings['bank_card_number']) ?></strong>
                                <button type="button" onclick="navigator.clipboard.writeText('<?= str_replace(['-', ' '], '', safe($settings['bank_card_number'])) ?>'); alert('شماره کارت در حافظه کپی شد.');" 
                                        class="text-[10px] bg-amber-500/10 hover:bg-amber-500/20 text-amber-300 border border-amber-500/30 px-2.5 py-1 rounded-lg transition">
                                    📋 کپی
                                </button>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($settings['bank_shaba_number'])): ?>
                        <div class="bg-[#031712] p-4 rounded-2xl border border-amber-500/20 space-y-1.5">
                            <span class="text-[10px] text-slate-400 block font-bold">شماره شبا (IBAN):</span>
                            <div class="flex justify-between items-center">
                                <strong class="text-slate-200 font-mono text-xs"><?= safe($settings['bank_shaba_number']) ?></strong>
                                <button type="button" onclick="navigator.clipboard.writeText('<?= safe($settings['bank_shaba_number']) ?>'); alert('شماره شبا در حافظه کپی شد.');" 
                                        class="text-[10px] bg-white/5 hover:bg-white/10 text-slate-300 border border-white/10 px-2.5 py-1 rounded-lg transition">
                                    📋 کپی
                                </button>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="bg-[#031712] p-4 rounded-2xl border border-amber-500/20 space-y-1.5">
                        <span class="text-[10px] text-slate-400 block font-bold">نام صاحب حساب و بانک:</span>
                        <strong class="text-white block text-xs">
                            <?= safe($settings['bank_owner_name'] ?? 'کاروان زیارتی محبان اهل‌بیت (ع)') ?> 
                            <?= !empty($settings['bank_name']) ? ' (' . safe($settings['bank_name']) . ')' : '' ?>
                        </strong>
                    </div>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <!-- ۸. بخش خدمات، تمایزها و ویژگی‌های کاروان (Why Us) -->
    <section id="services_section" class="max-w-7xl mx-auto px-4 sm:px-6 py-14 space-y-8 border-t border-amber-500/20">
        <div class="text-center space-y-2">
            <span class="text-amber-400 text-xs font-bold">خدمات در تراز زائران اهل‌بیت</span>
            <h2 class="text-2xl sm:text-3xl font-black text-white">چرا کاروان زیارتی ما را انتخاب کنید؟</h2>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 text-xs">
            <div class="rogh-card p-6 rounded-3xl border border-amber-500/20 space-y-3">
                <div class="w-12 h-12 rounded-2xl bg-amber-500/10 border border-amber-500/30 flex items-center justify-center text-2xl">
                    🏨
                </div>
                <h3 class="text-base font-bold text-white">اسکان در هتل‌های تمیز و نزدیک</h3>
                <p class="text-slate-400 leading-relaxed">
                    تمامی هتل‌های کاروان در کربلای معلی و نجف اشرف دارای درجه الف یا ب ممتاز، کمتر از ۵ دقیقه فاصله تا حرم، آسانسور و اینترنت بی‌سیم رایگان هستند.
                </p>
            </div>

            <div class="rogh-card p-6 rounded-3xl border border-amber-500/20 space-y-3">
                <div class="w-12 h-12 rounded-2xl bg-emerald-500/10 border border-emerald-500/30 flex items-center justify-center text-2xl">
                    🍲
                </div>
                <h3 class="text-base font-bold text-white">آشپزخانه مرکزی و غذای اصیل ایرانی</h3>
                <p class="text-slate-400 leading-relaxed">
                    وعده‌های غذایی زیر نظر کادر بهداشتی و آشپزان برجسته ایرانی طبخ شده و همراه با نوشیدنی، سالاد، ماست و میوه فصل در رستوران هتل سرو می‌شود.
                </p>
            </div>

            <div class="rogh-card p-6 rounded-3xl border border-amber-500/20 space-y-3">
                <div class="w-12 h-12 rounded-2xl bg-teal-500/10 border border-teal-500/30 flex items-center justify-center text-2xl">
                    🛡️
                </div>
                <h3 class="text-base font-bold text-white">بیمه کامل و کادر درمانی</h3>
                <p class="text-slate-400 leading-relaxed">
                    کلیه زائران از لحظه خروج از کشور تا بازگشت کامل به ایران تحت پوشش بیمه حوادث و درمان هلال‌احمر بوده و داروهای ضروری همراه کادر کاروان می‌باشد.
                </p>
            </div>
        </div>
    </section>

    <!-- ۹. دانشنامه زیارت و مقالات آموزشی -->
    <?php if (!empty($blog_posts)): ?>
        <section class="max-w-7xl mx-auto px-4 sm:px-6 py-12 border-t border-amber-500/20 space-y-6">
            <div class="flex justify-between items-end">
                <div>
                    <span class="text-amber-400 text-xs font-bold block mb-1">آموزش و احکام</span>
                    <h2 class="text-xl sm:text-2xl font-black text-white">دانشنامه معارف و راهنمای سفر زیارتی</h2>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <?php foreach ($blog_posts as $bp): ?>
                    <a href="single.php?id=<?= $bp['id'] ?>" class="rogh-card rounded-3xl p-5 border border-amber-500/20 hover:border-amber-500/50 transition duration-300 flex flex-col justify-between group space-y-4">
                        <div class="space-y-3">
                            <?php if (!empty($bp['image']) && file_exists(__DIR__ . '/uploads/blog/' . $bp['image'])): ?>
                                <div class="h-40 rounded-2xl overflow-hidden border border-amber-500/20">
                                    <img src="uploads/blog/<?= safe($bp['image']) ?>" alt="<?= safe($bp['title']) ?>" class="w-full h-full object-cover group-hover:scale-105 transition duration-300">
                                </div>
                            <?php endif; ?>
                            
                            <span class="bg-[#031712] text-amber-300 border border-amber-500/30 px-2.5 py-0.5 rounded-full text-[10px] font-bold inline-block">
                                <?= safe($bp['category'] ?? 'آداب زیارت') ?>
                            </span>

                            <h3 class="font-bold text-sm text-white group-hover:text-amber-300 transition leading-snug">
                                <?= safe($bp['title']) ?>
                            </h3>
                            <p class="text-xs text-slate-400 line-clamp-2 leading-relaxed">
                                <?= safe($bp['summary']) ?>
                            </p>
                        </div>

                        <div class="flex justify-between items-center text-[10px] text-slate-500 pt-3 border-t border-white/5 font-mono">
                            <span>⏱️ <?= safe($bp['read_time'] ?? '۵ دقیقه') ?></span>
                            <span class="text-amber-300 font-bold group-hover:underline">مطالعه مطلب &larr;</span>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <!-- ۱۰. بخش پرسش‌های متداول زائران (FAQ داینامیک از دیتابیس) -->
    <?php if (!empty($faqs_list)): ?>
        <section id="faq_section" class="max-w-4xl mx-auto px-4 sm:px-6 py-14 space-y-6 border-t border-amber-500/20">
            <div class="text-center space-y-2">
                <span class="text-amber-400 text-xs font-bold">پاسخ به سوالات شما</span>
                <h2 class="text-2xl font-black text-white">پرسش‌های متداول زائران گرامی</h2>
                <p class="text-xs text-slate-400">پاسخ مهم‌ترین پرسش‌های پیرامون گذرنامه، ثبت‌نام و خدمات سفر</p>
            </div>

            <div class="space-y-3 text-xs">
                <?php foreach ($faqs_list as $f_idx => $faq): ?>
                    <details class="rogh-card rounded-2xl p-4 border border-amber-500/20 group cursor-pointer transition">
                        <summary class="font-bold text-white flex justify-between items-center select-none">
                            <span class="text-sm font-black flex items-center gap-2">
                                <span class="text-amber-400 font-mono"><?= $f_idx + 1 ?>.</span>
                                <span><?= safe($faq['question']) ?></span>
                            </span>
                            <span class="text-amber-400 group-open:rotate-180 transition transform duration-200">▼</span>
                        </summary>
                        <div class="text-slate-300 mt-3 leading-relaxed border-t border-white/5 pt-3 text-[11px]">
                            <?= nl2br(safe($faq['answer'])) ?>
                        </div>
                    </details>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <!-- ۱۱. کتیبه تذهیب و فوتر رسمی سایت -->
    <div class="border-t border-amber-500/30 mt-auto"></div>
    <?php require_once 'footer.php'; ?>

</body>
</html>