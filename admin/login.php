<?php
ob_start();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/auth.php';

// اگر کاربر از قبل وارد شده باشد، مستقیماً به پیشخوان هدایت می‌شود
if (is_logged_in()) {
    header('Location: dashboard.php');
    exit;
}

$err = '';
$msg = '';

// نمایش پیام خروج موفقیت‌آمیز در صورت خروج از سیستم
if (isset($_GET['logged_out']) && $_GET['logged_out'] == '1') {
    $msg = 'شما با موفقیت از حساب کاربری خود خارج شدید.';
}

// پردازش فرم لاگین
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = safe($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $err = 'لطفاً نام کاربری و رمز عبور را وارد فرمایید.';
    } else {
        $stmt = $conn->prepare("SELECT * FROM admins WHERE username = ? LIMIT 1");
        $stmt->execute([$username]);
        $admin = $stmt->fetch();

        // بررسی رمز عبور (پشتیبانی از password_hash)
        if ($admin && password_verify($password, $admin['password'])) {
            // تولید شناسه جدید برای جلوگیری از Session Fixation
            session_regenerate_id(true);

            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_username'] = $admin['username'];
            $_SESSION['admin_fullname'] = $admin['fullname'];
            $_SESSION['admin_role'] = $admin['role'];

            // هدایت مستقیم به پیشخوان مدیریت
            header('Location: dashboard.php');
            exit;
        } else {
            $err = 'نام کاربری یا رمز عبور واردشده نادرست است.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ورود خادمین و مدیران کاروان زیارتی</title>
    
    <!-- آیکون سایت -->
    <link rel="icon" href="../img/icon.png">

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- قلم وزیرمتن -->
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
            background: linear-gradient(180deg, rgba(13, 44, 38, 0.96) 0%, rgba(6, 26, 22, 0.98) 100%); 
            border: 1px solid rgba(212, 175, 55, 0.35); 
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.8);
        }
        .site-logo-img {
            max-width: 46px !important;
            max-height: 46px !important;
            width: auto;
            height: auto;
            object-fit: contain !important;
        }
    </style>
</head>
<body class="islami-bg min-h-screen flex items-center justify-center p-4 selection:bg-amber-500 selection:text-black">

    <div class="rogh-card rounded-3xl max-w-sm w-full p-8 sm:p-10 space-y-6 text-center relative border border-amber-500/30">
        
        <!-- لوگوی مجموعه -->
        <div class="w-16 h-16 rounded-2xl bg-gradient-to-br from-amber-400 via-emerald-600 to-amber-600 p-[2px] mx-auto shadow-lg shadow-amber-500/20">
            <div class="w-full h-full bg-[#031914] rounded-[14px] flex items-center justify-center p-1.5 overflow-hidden">
                <?php 
                    $settings = get_settings($conn);
                    if (!empty($settings['site_logo']) && file_exists(__DIR__ . '/../uploads/' . $settings['site_logo'])): 
                ?>
                    <img src="../uploads/<?= safe($settings['site_logo']) ?>" alt="لوگو" class="site-logo-img">
                <?php else: ?>
                    <span class="text-3xl text-amber-400">🕌</span>
                <?php endif; ?>
            </div>
        </div>

        <!-- عنوان -->
        <div class="space-y-1">
            <h1 class="text-lg sm:text-xl font-black text-white">ورود خادمین و مدیران</h1>
            <p class="text-xs text-amber-200/80">
                <?= safe($settings['site_title'] ?? 'کاروان زیارتی محبان اهل‌بیت (ع)') ?>
            </p>
        </div>

        <!-- پیام خروج موفقیت‌آمیز -->
        <?php if ($msg): ?>
            <div class="p-3 bg-emerald-950/80 border border-emerald-500/50 text-emerald-200 text-xs rounded-xl font-bold flex items-center justify-center gap-1.5 shadow-sm">
                <span>✅</span>
                <span><?= safe($msg) ?></span>
            </div>
        <?php endif; ?>

        <!-- پیام خطا -->
        <?php if ($err): ?>
            <div class="p-3 bg-rose-950/80 border border-rose-500/50 text-rose-200 text-xs rounded-xl font-bold flex items-center justify-center gap-1.5 shadow-sm">
                <span>❌</span>
                <span><?= safe($err) ?></span>
            </div>
        <?php endif; ?>

        <!-- فرم ورود -->
        <form action="login.php" method="POST" class="space-y-4 text-right">
            <div>
                <label class="block text-xs font-bold text-slate-200 mb-1.5">نام کاربری:</label>
                <input type="text" name="username" required placeholder="admin" autofocus
                       value="<?= safe($_POST['username'] ?? '') ?>"
                       class="w-full bg-[#031712] border border-amber-500/30 rounded-xl p-3 text-xs text-white font-mono focus:outline-none focus:border-amber-400 transition">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-200 mb-1.5">کلمه عبور:</label>
                <input type="password" name="password" required placeholder="••••••••" 
                       class="w-full bg-[#031712] border border-amber-500/30 rounded-xl p-3 text-xs text-white font-mono focus:outline-none focus:border-amber-400 transition">
            </div>

            <button type="submit" class="w-full bg-gradient-to-r from-amber-500 via-amber-400 to-amber-500 hover:from-amber-400 text-slate-950 font-black py-3 rounded-xl text-xs sm:text-sm transition duration-200 shadow-xl shadow-amber-500/20">
                ورود به سامانه مدیریت &larr;
            </button>
        </form>

        <!-- لینک بازگشت به سایت -->
        <div class="pt-2 border-t border-white/5">
            <a href="../index.php" class="text-[11px] text-slate-400 hover:text-amber-300 transition block">
                &larr; بازگشت به صفحه اصلی سایت
            </a>
        </div>

    </div>

</body>
</html>
<?php
ob_end_flush();
?>