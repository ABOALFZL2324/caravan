<?php
require_once __DIR__ . '/header.php';

// دسترسی برای مدیر ارشد و مسئول پذیرش
require_roles(['super_admin', 'registrar']);

$msg = '';
$err = '';

// تغییر وضعیت فعال / غیرفعال
if (isset($_GET['action']) && $_GET['action'] === 'toggle') {
    $a_id = (int)($_GET['id'] ?? 0);
    if ($a_id > 0) {
        $stmt_t = $conn->prepare("UPDATE announcements SET is_active = IF(is_active = 1, 0, 1) WHERE id = ?");
        $stmt_t->execute([$a_id]);
        $msg = 'وضعیت نمایش اطلاعیه با موفقیت تغییر کرد.';
    }
}

// حذف اطلاعیه
if (isset($_GET['action']) && $_GET['action'] === 'delete') {
    $a_id = (int)($_GET['id'] ?? 0);
    if ($a_id > 0) {
        $stmt_d = $conn->prepare("DELETE FROM announcements WHERE id = ?");
        $stmt_d->execute([$a_id]);
        $msg = 'اطلاعیه با موفقیت حذف گردید.';
    }
}

// ثبت اطلاعیه جدید
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_announcement'])) {
    $title = safe($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $badge_type = safe($_POST['badge_type'] ?? 'info');

    if (empty($title) || empty($content)) {
        $err = 'لطفاً عنوان و متن اطلاعیه را وارد فرمایید.';
    } else {
        try {
            $stmt_ins = $conn->prepare("INSERT INTO announcements (title, content, badge_type, is_active, created_at) VALUES (?, ?, ?, 1, NOW())");
            $stmt_ins->execute([$title, $content, $badge_type]);
            $msg = 'اطلاعیه جدید با موفقیت منتشر شد و در صفحه اصلی به نمایش درآمد.';
        } catch (Exception $e) {
            $err = 'خطا در ثبت اطلاعیه: ' . $e->getMessage();
        }
    }
}

// واکشی همه اطلاعیه‌ها
$announcements = $conn->query("SELECT * FROM announcements ORDER BY id DESC")->fetchAll();
?>

<div class="space-y-6">

    <!-- نوار عنوان صفحه -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center pb-4 border-b border-amber-500/20 gap-3">
        <div>
            <h1 class="text-xl sm:text-2xl font-black text-white flex items-center gap-2">
                <span>📢</span> مدیریت تابلو اعلانات و اطلاعیه‌های کاروان
            </h1>
            <p class="text-xs text-amber-200/80 mt-1">اطلاع‌رسانی فوری در خصوص تاریخ حرکت، وضعیت گذرنامه، شرایط مرزی و بخشنامه‌ها</p>
        </div>

        <button onclick="toggleAnnounceForm()" 
                class="bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-400 hover:to-amber-500 text-slate-950 font-black text-xs px-4 py-2.5 rounded-xl transition shadow-md shadow-amber-600/20 flex items-center gap-2">
            <span id="btn_ann_icon">➕</span>
            <span id="btn_ann_label">انتشار اطلاعیه جدید</span>
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

    <!-- فرم افزودن اطلاعیه جدید -->
    <div id="new_announcement_box" class="hidden rogh-card rounded-3xl p-6 sm:p-8 space-y-5 border border-amber-500/30 transition">
        <div class="pb-3 border-b border-amber-500/20 flex justify-between items-center">
            <h2 class="font-black text-sm text-amber-300 flex items-center gap-2">
                <span>✦</span> نگارش و انتشار اطلاعیه در صفحه اصلی
            </h2>
            <button onclick="toggleAnnounceForm()" class="text-slate-400 hover:text-amber-300 text-xs font-bold">
                ✕ بستن
            </button>
        </div>

        <form action="announcements.php" method="POST" class="space-y-4">
            <input type="hidden" name="add_announcement" value="1">

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="sm:col-span-2">
                    <label class="block text-xs font-bold text-slate-200 mb-1.5">عنوان اطلاعیه: <span class="text-rose-400">*</span></label>
                    <input type="text" name="title" required placeholder="مثال: تغییر ساعت حرکت اتوبوس کاروان عتبات عالیات" 
                           class="w-full bg-[#031712] border border-amber-500/30 rounded-xl p-3 text-xs text-white focus:outline-none focus:border-amber-400 focus:ring-1 focus:ring-amber-400">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-200 mb-1.5">نوع و اولویت نمایش:</label>
                    <select name="badge_type" class="w-full bg-[#031712] border border-amber-500/30 rounded-xl p-3 text-xs text-white focus:outline-none focus:border-amber-400">
                        <option value="info">📌 عادی / اطلاع‌رسانی عمومی</option>
                        <option value="urgent">🚨 فوری / هشدار مهم مرزی یا حرکت</option>
                        <option value="success">✅ خوش‌خبری / تایید اعزام</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-200 mb-1.5">متن کامل اطلاعیه: <span class="text-rose-400">*</span></label>
                <textarea name="content" rows="4" required placeholder="متن هشدار، محل سوار شدن زائران یا توضیحات گذرنامه را با دقت درج فرمایید..." 
                          class="w-full bg-[#031712] border border-amber-500/30 rounded-xl p-3 text-xs text-slate-200 leading-relaxed focus:outline-none focus:border-amber-400 focus:ring-1 focus:ring-amber-400"></textarea>
            </div>

            <div class="flex items-center gap-3 pt-2">
                <button type="submit" class="bg-gradient-to-r from-amber-500 via-amber-400 to-amber-500 hover:from-amber-400 hover:to-amber-300 text-slate-950 font-black py-3 px-8 rounded-xl text-xs transition duration-200 shadow-lg shadow-amber-500/20">
                    انتشار رسمی در سایت
                </button>
                <button type="button" onclick="toggleAnnounceForm()" class="bg-[#031712] text-slate-300 hover:text-white border border-amber-500/20 font-bold py-3 px-6 rounded-xl text-xs transition">
                    انصراف
                </button>
            </div>
        </form>
    </div>

    <!-- جدول لیست اطلاعیه‌های ثبت‌شده -->
    <div class="rogh-card rounded-2xl overflow-hidden border border-amber-500/30">
        <div class="p-4 border-b border-amber-500/20 flex justify-between items-center bg-[#031511]">
            <div class="flex items-center gap-2">
                <span class="text-amber-400 font-bold text-sm">✦</span>
                <h2 class="font-black text-xs sm:text-sm text-white">لیست کلیه اطلاعیه‌ها</h2>
            </div>
            <span class="text-xs text-amber-200/80 font-mono">تعداد: <?= count($announcements) ?> پیام</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-right border-collapse text-xs">
                <thead>
                    <tr class="bg-[#02100d] text-amber-200/80 border-b border-amber-500/10">
                        <th class="p-3.5" style="width: 50px;">ردیف</th>
                        <th class="p-3.5">عنوان و نشان</th>
                        <th class="p-3.5">متن اطلاعیه</th>
                        <th class="p-3.5">تاریخ ثبت</th>
                        <th class="p-3.5 text-center">وضعیت انتشار</th>
                        <th class="p-3.5 text-center">عملیات</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-amber-500/10">
                    <?php if (empty($announcements)): ?>
                        <tr><td colspan="6" class="text-center py-8 text-slate-500">هنوز اطلاعیه‌ای در سامانه ثبت نشده است.</td></tr>
                    <?php else: ?>
                        <?php foreach ($announcements as $idx => $a): ?>
                            <tr class="hover:bg-white/5 transition">
                                <td class="p-3.5 text-slate-400 font-mono text-center"><?= $idx + 1 ?></td>
                                <td class="p-3.5 font-bold text-white whitespace-nowrap">
                                    <div class="flex items-center gap-2">
                                        <?php if ($a['badge_type'] === 'urgent'): ?>
                                            <span class="bg-rose-500/20 text-rose-300 border border-rose-500/40 px-2 py-0.5 rounded-lg text-[10px] font-black">
                                                🚨 فوری
                                            </span>
                                        <?php elseif ($a['badge_type'] === 'success'): ?>
                                            <span class="bg-emerald-500/20 text-emerald-300 border border-emerald-500/40 px-2 py-0.5 rounded-lg text-[10px] font-black">
                                                ✅ خوش‌خبری
                                            </span>
                                        <?php else: ?>
                                            <span class="bg-amber-500/20 text-amber-300 border border-amber-500/40 px-2 py-0.5 rounded-lg text-[10px] font-black">
                                                📌 عمومی
                                            </span>
                                        <?php endif; ?>
                                        <span><?= safe($a['title']) ?></span>
                                    </div>
                                </td>
                                <td class="p-3.5 text-slate-300 max-w-md leading-relaxed">
                                    <?= nl2br(safe($a['content'])) ?>
                                </td>
                                <td class="p-3.5 text-slate-400 font-mono text-[11px] whitespace-nowrap">
                                    <?= substr($a['created_at'], 0, 16) ?>
                                </td>
                                <td class="p-3.5 text-center whitespace-nowrap">
                                    <a href="announcements.php?action=toggle&id=<?= $a['id'] ?>" 
                                       class="px-2.5 py-1 rounded-xl text-[10px] font-bold transition <?= $a['is_active'] ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/30 hover:bg-emerald-500/30' : 'bg-slate-700/60 text-slate-400 border border-slate-600 hover:bg-slate-700' ?>">
                                        <?= $a['is_active'] ? 'در حال نمایش در سایت' : 'مخفی / غیرفعال' ?>
                                    </a>
                                </td>
                                <td class="p-3.5 text-center whitespace-nowrap">
                                    <a href="announcements.php?action=delete&id=<?= $a['id'] ?>" 
                                       onclick="return confirm('آیا از حذف این اطلاعیه مطمئن هستید؟')" 
                                       class="text-rose-400 hover:text-rose-300 font-bold hover:underline text-xs">
                                        حذف
                                    </a>
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
    function toggleAnnounceForm() {
        const box = document.getElementById('new_announcement_box');
        const icon = document.getElementById('btn_ann_icon');
        const label = document.getElementById('btn_ann_label');
        if (box.classList.contains('hidden')) {
            box.classList.remove('hidden');
            icon.textContent = '✕';
            label.textContent = 'بستن فرم اطلاعیه';
            box.scrollIntoView({ behavior: 'smooth' });
        } else {
            box.classList.add('hidden');
            icon.textContent = '➕';
            label.textContent = 'انتشار اطلاعیه جدید';
        }
    }
</script>

<?php require_once __DIR__ . '/footer.php'; ?>