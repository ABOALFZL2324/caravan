<?php
require_once __DIR__ . '/header.php';

// فقط مدیر ارشد مجاز به مدیریت و ویرایش خادمین است
require_roles(['super_admin']);

$msg = '';
$err = '';
$current_admin_id = (int)($_SESSION['admin_id'] ?? 0);

// حذف حساب خادم (با جلوگیری از حذف حساب خود ادمین لاگین‌شده)
if (isset($_GET['action']) && $_GET['action'] === 'delete') {
    $del_id = (int)($_GET['id'] ?? 0);

    if ($del_id === $current_admin_id) {
        $err = 'شما نمی‌توانید حساب کاربری فعال خودتان را حذف نمایید!';
    } elseif ($del_id > 0) {
        $stmt_del = $conn->prepare("DELETE FROM admins WHERE id = ?");
        $stmt_del->execute([$del_id]);
        $msg = 'حساب کاربری خادم مورد نظر با موفقیت حذف گردید.';
    }
}

// ثبت حساب خادم جدید
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
    $fullname = safe($_POST['fullname'] ?? '');
    $username = safe($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = safe($_POST['role'] ?? 'registrar');

    if (empty($fullname) || empty($username) || empty($password)) {
        $err = 'لطفاً نام و نام خانوادگی، نام کاربری و کلمه عبور را تکمیل فرمایید.';
    } else {
        $stmt_chk = $conn->prepare("SELECT id FROM admins WHERE username = ?");
        $stmt_chk->execute([$username]);
        if ($stmt_chk->fetch()) {
            $err = 'این نام کاربری قبلاً در سامانه ثبت شده است. لطفاً نام دیگری برگزینید.';
        } else {
            $hashed_pass = password_hash($password, PASSWORD_DEFAULT);
            try {
                $stmt_ins = $conn->prepare("INSERT INTO admins (username, password, fullname, role, created_at) VALUES (?, ?, ?, ?, NOW())");
                $stmt_ins->execute([$username, $hashed_pass, $fullname, $role]);
                $msg = "خادم گرامی «{$fullname}» با موفقیت در سامانه تعریف شد.";
            } catch (Exception $e) {
                $err = 'خطا در ثبت کاربر: ' . $e->getMessage();
            }
        }
    }
}

// ویرایش مشخصات خادم/ادمین
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_user'])) {
    $edit_id = (int)($_POST['edit_id'] ?? 0);
    $fullname = safe($_POST['fullname'] ?? '');
    $username = safe($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = safe($_POST['role'] ?? 'registrar');

    if ($edit_id <= 0 || empty($fullname) || empty($username)) {
        $err = 'اطلاعات ارسالی برای ویرایش ناقص است.';
    } else {
        // بررسی تکراری نبودن نام کاربری جدید با سایر کاربران
        $stmt_chk = $conn->prepare("SELECT id FROM admins WHERE username = ? AND id != ?");
        $stmt_chk->execute([$username, $edit_id]);
        if ($stmt_chk->fetch()) {
            $err = 'این نام کاربری توسط کاربر دیگری استفاده شده است.';
        } else {
            // جلوگیری از تنزل نقش حساب شخصی مدیر لاگین‌شده
            if ($edit_id === $current_admin_id && $role !== 'super_admin') {
                $role = 'super_admin';
            }

            try {
                if (!empty($password)) {
                    // در صورت وارد کردن رمز عبور جدید
                    $hashed_pass = password_hash($password, PASSWORD_DEFAULT);
                    $stmt_up = $conn->prepare("UPDATE admins SET fullname = ?, username = ?, role = ?, password = ? WHERE id = ?");
                    $stmt_up->execute([$fullname, $username, $role, $hashed_pass, $edit_id]);
                } else {
                    // در صورت خالی گذاشتن کادر رمز (حفظ رمز قبلی)
                    $stmt_up = $conn->prepare("UPDATE admins SET fullname = ?, username = ?, role = ? WHERE id = ?");
                    $stmt_up->execute([$fullname, $username, $role, $edit_id]);
                }

                // اگر مدیر مشخصات خودش را ویرایش کرد، نشست جاری هم به‌روزرسانی شود
                if ($edit_id === $current_admin_id) {
                    $_SESSION['admin_fullname'] = $fullname;
                    $_SESSION['admin_username'] = $username;
                }

                $msg = 'مشخصات خادم با موفقیت به‌روزرسانی شد.';
            } catch (Exception $e) {
                $err = 'خطا در ذخیره‌سازی ویرایش: ' . $e->getMessage();
            }
        }
    }
}

// واکشی لیست کلیه مدیران و خادمین
$admins = $conn->query("SELECT * FROM admins ORDER BY id DESC")->fetchAll();
?>

<div class="space-y-6">

    <!-- نوار عنوان و دکمه باز کردن فرم افزودن -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center pb-4 border-b border-amber-500/20 gap-3">
        <div>
            <h1 class="text-xl sm:text-2xl font-black text-white flex items-center gap-2">
                <span>👤</span> مدیریت دسترسی خادمین و مسئولین کاروان
            </h1>
            <p class="text-xs text-amber-200/80 mt-1">تعریف، ویرایش مشخصات، ریست رمز عبور و تعیین نقش‌های دسترسی به سامانه</p>
        </div>

        <button onclick="toggleUserForm()" 
                class="bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-400 hover:to-amber-500 text-slate-950 font-black text-xs px-4 py-2.5 rounded-xl transition shadow-md shadow-amber-600/20 flex items-center gap-2">
            <span id="btn_user_icon">➕</span>
            <span id="btn_user_label">تعریف خادم جدید</span>
        </button>
    </div>

    <?php if ($msg): ?>
        <div class="p-3.5 bg-emerald-950/60 border border-emerald-500/40 text-emerald-200 text-xs rounded-2xl font-bold">
            ✅ <?= safe($msg) ?>
        </div>
    <?php endif; ?>

    <?php if ($err): ?>
        <div class="p-3.5 bg-rose-950/60 border border-rose-500/40 text-rose-200 text-xs rounded-2xl font-bold">
            ❌ <?= safe($err) ?>
        </div>
    <?php endif; ?>

    <!-- کتیبه راهنمای جامع سطوح دسترسی -->
    <div class="rogh-card rounded-3xl p-6 border border-amber-500/30 space-y-4">
        <div class="flex items-center gap-2 pb-2 border-b border-amber-500/20">
            <span class="text-amber-400 text-sm">✦</span>
            <h2 class="font-black text-sm text-white">راهنمای سطوح دسترسی در سامانه</h2>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 text-xs">
            <div class="bg-[#031712] p-4 rounded-2xl border border-amber-500/20 space-y-2">
                <div class="flex items-center gap-2">
                    <span class="text-lg">👑</span>
                    <strong class="text-amber-300 font-black">مدیر ارشد (Super Admin)</strong>
                </div>
                <p class="text-slate-300 text-[11px] leading-relaxed">
                    اختیارات کامل: تعریف و ویرایش خادمین، تنظیمات سایت، مدیریت مالی، کاروان‌ها و پرونده‌ها.
                </p>
            </div>

            <div class="bg-[#031712] p-4 rounded-2xl border border-emerald-500/20 space-y-2">
                <div class="flex items-center gap-2">
                    <span class="text-lg">📑</span>
                    <strong class="text-emerald-300 font-black">مسئول پذیرش (Registrar)</strong>
                </div>
                <p class="text-slate-300 text-[11px] leading-relaxed">
                    اختیارات: بررسی مدارک، تأیید/رد پرونده‌ها، ثبت‌نام حضوری و صدور مانیفست.
                </p>
            </div>

            <div class="bg-[#031712] p-4 rounded-2xl border border-teal-500/20 space-y-2">
                <div class="flex items-center gap-2">
                    <span class="text-lg">📋</span>
                    <strong class="text-teal-300 font-black">سرپرست کاروان (Leader)</strong>
                </div>
                <p class="text-slate-300 text-[11px] leading-relaxed">
                    اختیارات میدانی: لیست مسافران، پنل حضور و غیاب، تماس با بستگان و ملاحظات پزشکی.
                </p>
            </div>

            <div class="bg-[#031712] p-4 rounded-2xl border border-blue-500/20 space-y-2">
                <div class="flex items-center gap-2">
                    <span class="text-lg">💳</span>
                    <strong class="text-blue-300 font-black">مسئول امور مالی (Finance)</strong>
                </div>
                <p class="text-slate-300 text-[11px] leading-relaxed">
                    اختیارات: مبالغ بیعانه، تسویه‌حساب‌ها، گزارش‌های مالی و خروجی‌های کاروان.
                </p>
            </div>
        </div>
    </div>

    <!-- فرم افزودن خادم جدید -->
    <div id="new_user_box" class="hidden rogh-card rounded-3xl p-6 sm:p-8 space-y-5 border border-amber-500/30 transition">
        <div class="pb-3 border-b border-amber-500/20 flex justify-between items-center">
            <h2 class="font-black text-sm text-amber-300 flex items-center gap-2">
                <span>✦</span> مشخصات حساب کاربری خادم جدید
            </h2>
            <button onclick="toggleUserForm()" class="text-slate-400 hover:text-amber-300 text-xs font-bold">
                ✕ بستن
            </button>
        </div>

        <form action="users.php" method="POST" class="space-y-4">
            <input type="hidden" name="add_user" value="1">

            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-200 mb-1.5">نام و نام خانوادگی: <span class="text-rose-400">*</span></label>
                    <input type="text" name="fullname" required placeholder="مثال: حاج رضا حسینی" 
                           class="w-full bg-[#031712] border border-amber-500/30 rounded-xl p-2.5 text-xs text-white focus:outline-none focus:border-amber-400">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-200 mb-1.5">نام کاربری ورود (انگلیسی): <span class="text-rose-400">*</span></label>
                    <input type="text" name="username" required placeholder="مثال: hosseini" 
                           class="w-full bg-[#031712] border border-amber-500/30 rounded-xl p-2.5 text-xs text-amber-300 font-mono focus:outline-none focus:border-amber-400">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-200 mb-1.5">کلمه عبور: <span class="text-rose-400">*</span></label>
                    <input type="password" name="password" required placeholder="حداقل ۶ کاراکتر" 
                           class="w-full bg-[#031712] border border-amber-500/30 rounded-xl p-2.5 text-xs text-white font-mono focus:outline-none focus:border-amber-400">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-200 mb-1.5">سطح دسترسی و سمت:</label>
                    <select name="role" class="w-full bg-[#031712] border border-amber-500/30 rounded-xl p-2.5 text-xs text-white focus:outline-none focus:border-amber-400">
                        <option value="registrar">مسئول پذیرش و ثبت‌نام</option>
                        <option value="leader">سرپرست و مداح کاروان</option>
                        <option value="finance">مسئول امور مالی</option>
                        <option value="super_admin">مدیر ارشد مجموعه</option>
                    </select>
                </div>
            </div>

            <div class="flex items-center gap-3 pt-2">
                <button type="submit" class="bg-gradient-to-r from-amber-500 via-amber-400 to-amber-500 hover:from-amber-400 text-slate-950 font-black py-3 px-8 rounded-xl text-xs transition duration-200 shadow-lg shadow-amber-500/20">
                    ثبت و فعال‌سازی دسترسی خادم
                </button>
                <button type="button" onclick="toggleUserForm()" class="bg-[#031712] text-slate-300 hover:text-white border border-amber-500/20 font-bold py-3 px-6 rounded-xl text-xs transition">
                    انصراف
                </button>
            </div>
        </form>
    </div>

    <!-- جدول لیست خادمین و مدیران فعال -->
    <div class="rogh-card rounded-2xl overflow-hidden border border-amber-500/30">
        <div class="p-4 border-b border-amber-500/20 flex justify-between items-center bg-[#031511]">
            <div class="flex items-center gap-2">
                <span class="text-amber-400 font-bold text-sm">✦</span>
                <h2 class="font-black text-xs sm:text-sm text-white">لیست خادمین و مدیران دارای دسترسی</h2>
            </div>
            <span class="text-xs text-amber-200/80 font-mono">مجموع: <?= count($admins) ?> کاربر</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-right border-collapse text-xs">
                <thead>
                    <tr class="bg-[#02100d] text-amber-200/80 border-b border-amber-500/10">
                        <th class="p-3.5" style="width: 45px;">ردیف</th>
                        <th class="p-3.5">نام و نام خانوادگی</th>
                        <th class="p-3.5">نام کاربری (ورود)</th>
                        <th class="p-3.5">سمت و سطح دسترسی</th>
                        <th class="p-3.5">تاریخ تعریف</th>
                        <th class="p-3.5 text-center">عملیات</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-amber-500/10">
                    <?php if (empty($admins)): ?>
                        <tr><td colspan="6" class="text-center py-8 text-slate-500">هیچ کاربری یافت نشد.</td></tr>
                    <?php else: ?>
                        <?php foreach ($admins as $idx => $u): ?>
                            <?php 
                                $is_self = ((int)$u['id'] === $current_admin_id);
                                $role_badge = '';
                                $role_text = '';

                                switch ($u['role']) {
                                    case 'super_admin':
                                        $role_badge = 'bg-amber-500/20 text-amber-300 border-amber-500/40';
                                        $role_text = '👑 مدیر ارشد مجموعه';
                                        break;
                                    case 'registrar':
                                        $role_badge = 'bg-emerald-500/20 text-emerald-300 border-emerald-500/40';
                                        $role_text = '📑 مسئول پذیرش و مدارک';
                                        break;
                                    case 'leader':
                                        $role_badge = 'bg-teal-500/20 text-teal-300 border-teal-500/40';
                                        $role_text = '📋 سرپرست و مداح کاروان';
                                        break;
                                    case 'finance':
                                        $role_badge = 'bg-blue-500/20 text-blue-300 border-blue-500/40';
                                        $role_text = '💳 مسئول امور مالی';
                                        break;
                                    default:
                                        $role_badge = 'bg-slate-700/50 text-slate-300 border-slate-600';
                                        $role_text = 'خادم کاروان';
                                }
                            ?>
                            <tr class="hover:bg-white/5 transition">
                                <td class="p-3.5 text-slate-400 font-mono text-center"><?= $idx + 1 ?></td>
                                <td class="p-3.5 font-bold text-white">
                                    <div class="flex items-center gap-2">
                                        <span><?= safe($u['fullname']) ?></span>
                                        <?php if ($is_self): ?>
                                            <span class="bg-amber-400 text-slate-950 text-[9px] font-black px-1.5 py-0.5 rounded">حساب شما</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="p-3.5 font-mono text-amber-200"><?= safe($u['username']) ?></td>
                                <td class="p-3.5 whitespace-nowrap">
                                    <span class="px-2.5 py-1 rounded-xl border text-[10px] font-bold <?= $role_badge ?>">
                                        <?= $role_text ?>
                                    </span>
                                </td>
                                <td class="p-3.5 text-slate-400 font-mono text-[11px]">
                                    <?= !empty($u['created_at']) ? substr($u['created_at'], 0, 10) : '—' ?>
                                </td>
                                <td class="p-3.5 text-center whitespace-nowrap">
                                    <div class="inline-flex items-center gap-2">
                                        <!-- دکمه ویرایش مشخصات و رمز -->
                                        <button onclick='openEditModal(<?= json_encode($u, JSON_UNESCAPED_UNICODE) ?>)' 
                                                class="text-amber-400 hover:text-amber-300 font-bold hover:underline text-xs flex items-center gap-1">
                                            <span>✏️</span> ویرایش
                                        </button>

                                        <?php if (!$is_self): ?>
                                            <span class="text-slate-600">|</span>
                                            <a href="users.php?action=delete&id=<?= $u['id'] ?>" 
                                               onclick="return confirm('آیا از حذف این حساب کاربری اطمینان کامل دارید؟')" 
                                               class="text-rose-400 hover:text-rose-300 font-bold hover:underline text-xs">
                                                حذف
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<!-- پنجره مودال ویرایش اطلاعات خادم / مدیر -->
<div id="edit_user_modal" class="fixed inset-0 bg-black/80 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
    <div class="rogh-card rounded-3xl max-w-lg w-full p-6 sm:p-7 border border-amber-500/40 shadow-2xl relative">
        <div class="flex justify-between items-center pb-3 border-b border-amber-500/20">
            <h3 class="font-black text-sm text-white flex items-center gap-2">
                <span>✏️</span> ویرایش مشخصات حساب کاربری خادم
            </h3>
            <button onclick="closeEditModal()" class="text-slate-400 hover:text-rose-400 font-bold text-lg">&times;</button>
        </div>

        <form action="users.php" method="POST" class="mt-4 space-y-4">
            <input type="hidden" name="edit_user" value="1">
            <input type="hidden" id="edit_user_id" name="edit_id" value="">

            <div>
                <label class="block text-xs font-bold text-slate-200 mb-1">نام و نام خانوادگی: <span class="text-rose-400">*</span></label>
                <input type="text" id="edit_fullname" name="fullname" required 
                       class="w-full bg-[#031712] border border-amber-500/30 rounded-xl p-2.5 text-xs text-white focus:outline-none focus:border-amber-400">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-200 mb-1">نام کاربری ورود (لاتین): <span class="text-rose-400">*</span></label>
                <input type="text" id="edit_username" name="username" required 
                       class="w-full bg-[#031712] border border-amber-500/30 rounded-xl p-2.5 text-xs text-amber-300 font-mono focus:outline-none focus:border-amber-400">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-200 mb-1">کلمه عبور جدید:</label>
                <input type="password" name="password" placeholder="اگر نمی‌خواهید رمز تغییر کند، این کادر را خالی بگذارید" 
                       class="w-full bg-[#031712] border border-amber-500/30 rounded-xl p-2.5 text-xs text-white font-mono focus:outline-none focus:border-amber-400">
                <span class="text-[10px] text-slate-400 block mt-1">تنها در صورتی پر کنید که قصد تعویض رمز عبور این کاربر را دارید.</span>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-200 mb-1">سطح دسترسی و نقش:</label>
                <select id="edit_role" name="role" class="w-full bg-[#031712] border border-amber-500/30 rounded-xl p-2.5 text-xs text-white focus:outline-none focus:border-amber-400">
                    <option value="registrar">مسئول پذیرش و ثبت‌نام</option>
                    <option value="leader">سرپرست و مداح کاروان</option>
                    <option value="finance">مسئول امور مالی</option>
                    <option value="super_admin">مدیر ارشد مجموعه</option>
                </select>
            </div>

            <div class="flex items-center gap-3 pt-3 border-t border-amber-500/20">
                <button type="submit" class="flex-1 bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-400 text-slate-950 font-black py-2.5 rounded-xl text-xs transition shadow-md">
                    ذخیره تغییرات حساب کاربری
                </button>
                <button type="button" onclick="closeEditModal()" class="bg-[#031712] text-slate-300 hover:text-white border border-amber-500/20 font-bold py-2.5 px-4 rounded-xl text-xs transition">
                    انصراف
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function toggleUserForm() {
        const box = document.getElementById('new_user_box');
        const icon = document.getElementById('btn_user_icon');
        const label = document.getElementById('btn_user_label');
        if (box.classList.contains('hidden')) {
            box.classList.remove('hidden');
            icon.textContent = '✕';
            label.textContent = 'بستن فرم خادم';
            box.scrollIntoView({ behavior: 'smooth' });
        } else {
            box.classList.add('hidden');
            icon.textContent = '➕';
            label.textContent = 'تعریف خادم جدید';
        }
    }

    function openEditModal(u) {
        document.getElementById('edit_user_id').value = u.id;
        document.getElementById('edit_fullname').value = u.fullname;
        document.getElementById('edit_username').value = u.username;
        document.getElementById('edit_role').value = u.role;
        document.getElementById('edit_user_modal').classList.remove('hidden');
    }

    function closeEditModal() {
        document.getElementById('edit_user_modal').classList.add('hidden');
    }
</script>

<?php require_once __DIR__ . '/footer.php'; ?>