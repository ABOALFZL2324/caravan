<?php
ob_start();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/auth.php';

// بررسی ورود کاربر؛ در صورت عدم ورود، هدایت به صفحه لاگین
if (!is_logged_in()) {
    header('Location: login.php');
    exit;
}

$settings = get_settings($conn);
$admin_name = $_SESSION['admin_fullname'] ?? 'خادم محترم';
$admin_role = $_SESSION['admin_role'] ?? '';
$cur_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مدیریت کاروان زیارتی | <?= safe($settings['site_title'] ?? 'کاروان محبان اهل‌بیت (ع)') ?></title>
    
    <!-- آیکون تب مرورگر -->
    <link rel="icon" href="../img/icon.png">

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- قلم اصیل وزیرمتن -->
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet">

    <!-- تقویم و انتخاب‌گر تاریخ شمسی -->
    <link rel="stylesheet" href="https://unpkg.com/persian-datepicker@1.2.0/dist/css/persian-datepicker.min.css"/>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://unpkg.com/persian-date@1.1.0/dist/persian-date.min.js"></script>
    <script src="https://unpkg.com/persian-datepicker@1.2.0/dist/js/persian-datepicker.min.js"></script>

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
            background: linear-gradient(180deg, rgba(13, 44, 38, 0.95) 0%, rgba(6, 26, 22, 0.98) 100%); 
            border: 1px solid rgba(212, 175, 55, 0.28); 
            box-shadow: 0 15px 35px -10px rgba(0, 0, 0, 0.65);
        }

        /* کنترل و مهار قطعی ابعاد لوگو */
        .site-logo-img {
            max-width: 38px !important;
            max-height: 38px !important;
            width: auto;
            height: auto;
            object-fit: contain !important;
        }
    </style>
</head>
<body class="islami-bg min-h-screen flex flex-col selection:bg-amber-500 selection:text-black">

    <!-- نوار اصلی بالای پنل مدیریت -->
    <header class="bg-[#020f0c]/95 backdrop-blur-md border-b border-amber-500/25 sticky top-0 z-40 shadow-xl">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 h-18 py-2.5 flex justify-between items-center gap-4">
            
            <!-- لوگو و نام کاروان -->
            <div class="flex items-center gap-4">
                <a href="dashboard.php" class="flex items-center gap-3 group">
                    <div class="w-10 h-10 max-w-[40px] max-h-[40px] shrink-0 rounded-xl bg-gradient-to-br from-amber-400 to-amber-600 p-[1.5px] shadow-md shadow-amber-500/20 group-hover:scale-105 transition">
                        <div class="w-full h-full bg-[#031914] rounded-[10px] flex items-center justify-center p-1 overflow-hidden">
                            <?php if (!empty($settings['site_logo']) && file_exists(__DIR__ . '/../uploads/' . $settings['site_logo'])): ?>
                                <img src="../uploads/<?= safe($settings['site_logo']) ?>" alt="لوگو" class="site-logo-img">
                            <?php else: ?>
                                <span class="text-xl text-amber-400">🕌</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="hidden sm:block">
                        <span class="font-black text-white text-xs sm:text-sm block tracking-tight group-hover:text-amber-300 transition">
                            <?= safe($settings['site_title'] ?? 'کاروان زیارتی محبان اهل‌بیت (ع)') ?>
                        </span>
                        <span class="text-[10px] text-amber-200/70 font-mono">پنل جامع مدیریت عتبات</span>
                    </div>
                </a>

                <!-- منوی ناوبری دسکتاپ (شامل تمامی فایل‌های موجود در پوشه admin) -->
                <nav class="hidden xl:flex items-center gap-1 text-xs font-bold">
                    <a href="dashboard.php" 
                       class="px-2.5 py-1.5 rounded-xl transition flex items-center gap-1 <?= $cur_page === 'dashboard.php' ? 'bg-amber-500 text-slate-950 font-black shadow-md' : 'text-slate-300 hover:text-white hover:bg-white/5' ?>">
                        <span>📊</span> پیشخوان
                    </a>

                    <a href="pilgrims.php" 
                       class="px-2.5 py-1.5 rounded-xl transition flex items-center gap-1 <?= $cur_page === 'pilgrims.php' ? 'bg-amber-500 text-slate-950 font-black shadow-md' : 'text-slate-300 hover:text-white hover:bg-white/5' ?>">
                        <span>👥</span> زائران
                    </a>

                    <a href="caravans.php" 
                       class="px-2.5 py-1.5 rounded-xl transition flex items-center gap-1 <?= $cur_page === 'caravans.php' ? 'bg-amber-500 text-slate-950 font-black shadow-md' : 'text-slate-300 hover:text-white hover:bg-white/5' ?>">
                        <span>🧭</span> کاروان‌ها
                    </a>

                    <!-- دسترسی مستقیم به مدیریت فایل‌ها و مدارک -->
                    <a href="files.php" 
                       class="px-2.5 py-1.5 rounded-xl transition flex items-center gap-1 <?= $cur_page === 'files.php' ? 'bg-amber-500 text-slate-950 font-black shadow-md' : 'text-slate-300 hover:text-white hover:bg-white/5' ?>">
                        <span>🗄️</span> مدیر فایل‌ها
                    </a>

                    <a href="export_center.php" 
                       class="px-2.5 py-1.5 rounded-xl transition flex items-center gap-1 <?= $cur_page === 'export_center.php' ? 'bg-amber-500 text-slate-950 font-black shadow-md' : 'text-slate-300 hover:text-white hover:bg-white/5' ?>">
                        <span>📥</span> مانیفست
                    </a>

                    <!-- دسترسی به اعلانات و دانشنامه/مقالات -->
                    <a href="announcements.php" 
                       class="px-2.5 py-1.5 rounded-xl transition flex items-center gap-1 <?= $cur_page === 'announcements.php' ? 'bg-amber-500 text-slate-950 font-black shadow-md' : 'text-slate-300 hover:text-white hover:bg-white/5' ?>">
                        <span>📢</span> اعلانات
                    </a>

                    <a href="blog.php" 
                       class="px-2.5 py-1.5 rounded-xl transition flex items-center gap-1 <?= $cur_page === 'blog.php' ? 'bg-amber-500 text-slate-950 font-black shadow-md' : 'text-slate-300 hover:text-white hover:bg-white/5' ?>">
                        <span>📝</span> دانشنامه
                    </a>

                    <?php if (has_role(['super_admin'])): ?>
                        <a href="sms.php" 
                           class="px-2.5 py-1.5 rounded-xl transition flex items-center gap-1 <?= $cur_page === 'sms.php' ? 'bg-amber-500 text-slate-950 font-black shadow-md' : 'text-slate-300 hover:text-white hover:bg-white/5' ?>">
                            <span>📱</span> پیامک
                        </a>

                        <a href="users.php" 
                           class="px-2.5 py-1.5 rounded-xl transition flex items-center gap-1 <?= $cur_page === 'users.php' ? 'bg-amber-500 text-slate-950 font-black shadow-md' : 'text-slate-300 hover:text-white hover:bg-white/5' ?>">
                            <span>👤</span> خادمین
                        </a>

                        <a href="settings.php" 
                           class="px-2.5 py-1.5 rounded-xl transition flex items-center gap-1 <?= $cur_page === 'settings.php' ? 'bg-amber-500 text-slate-950 font-black shadow-md' : 'text-slate-300 hover:text-white hover:bg-white/5' ?>">
                            <span>⚙️</span> تنظیمات
                        </a>
                    <?php endif; ?>
                </nav>
            </div>

            <!-- بخش کاربری و دکمه خروج -->
            <div class="flex items-center gap-2 text-xs">
                <span class="text-slate-300 hidden md:inline">
                    خادم: <strong class="text-amber-300"><?= safe($admin_name) ?></strong>
                </span>

                <a href="../index.php" target="_blank" 
                   class="bg-[#041c16] hover:bg-white/10 text-slate-300 hover:text-white border border-amber-500/20 px-2.5 py-1.5 rounded-xl transition hidden sm:flex items-center gap-1">
                    <span>🌐</span> سایت
                </a>

                <a href="logout.php" 
                   onclick="return confirm('آیا برای خروج از حساب کاربری اطمینان دارید؟');" 
                   class="bg-rose-950/70 hover:bg-rose-900 text-rose-300 border border-rose-500/40 px-3 py-1.5 rounded-xl font-bold transition flex items-center gap-1 shadow-sm">
                    <span>🚪</span>
                    <span>خروج</span>
                </a>
            </div>

        </div>

        <!-- نوار زیرین اسکرول‌شونده ویژه موبایل و تبلت -->
        <div class="xl:hidden bg-[#031511] border-t border-amber-500/10 px-4 py-2 overflow-x-auto flex items-center gap-2 text-xs font-bold scrollbar-none">
            <a href="dashboard.php" class="px-2.5 py-1 rounded-lg shrink-0 <?= $cur_page === 'dashboard.php' ? 'bg-amber-500 text-slate-950' : 'text-slate-300' ?>">📊 پیشخوان</a>
            <a href="pilgrims.php" class="px-2.5 py-1 rounded-lg shrink-0 <?= $cur_page === 'pilgrims.php' ? 'bg-amber-500 text-slate-950' : 'text-slate-300' ?>">👥 زائران</a>
            <a href="caravans.php" class="px-2.5 py-1 rounded-lg shrink-0 <?= $cur_page === 'caravans.php' ? 'bg-amber-500 text-slate-950' : 'text-slate-300' ?>">🧭 کاروان‌ها</a>
            <a href="files.php" class="px-2.5 py-1 rounded-lg shrink-0 <?= $cur_page === 'files.php' ? 'bg-amber-500 text-slate-950' : 'text-slate-300' ?>">🗄️ مدیر فایل‌ها</a>
            <a href="export_center.php" class="px-2.5 py-1 rounded-lg shrink-0 <?= $cur_page === 'export_center.php' ? 'bg-amber-500 text-slate-950' : 'text-slate-300' ?>">📥 مانیفست</a>
            <a href="announcements.php" class="px-2.5 py-1 rounded-lg shrink-0 <?= $cur_page === 'announcements.php' ? 'bg-amber-500 text-slate-950' : 'text-slate-300' ?>">📢 اعلانات</a>
            <a href="blog.php" class="px-2.5 py-1 rounded-lg shrink-0 <?= $cur_page === 'blog.php' ? 'bg-amber-500 text-slate-950' : 'text-slate-300' ?>">📝 دانشنامه</a>
            <?php if (has_role(['super_admin'])): ?>
                <a href="sms.php" class="px-2.5 py-1 rounded-lg shrink-0 <?= $cur_page === 'sms.php' ? 'bg-amber-500 text-slate-950' : 'text-slate-300' ?>">📱 پیامک</a>
                <a href="users.php" class="px-2.5 py-1 rounded-lg shrink-0 <?= $cur_page === 'users.php' ? 'bg-amber-500 text-slate-950' : 'text-slate-300' ?>">👤 خادمین</a>
                <a href="settings.php" class="px-2.5 py-1 rounded-lg shrink-0 <?= $cur_page === 'settings.php' ? 'bg-amber-500 text-slate-950' : 'text-slate-300' ?>">⚙️ تنظیمات</a>
            <?php endif; ?>
        </div>
    </header>

    <!-- کانتینر اصلی صفحات مدیریت -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 py-6 w-full flex-grow">