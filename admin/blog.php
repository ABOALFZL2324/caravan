<?php
ob_start();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/auth.php';

require_roles(['super_admin', 'registrar']);

$msg = '';
$err = '';$upload_dir = __DIR__ . '/../uploads/blog/';
if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

// ۱. حذف مقاله
if (isset($_GET['action']) &&$_GET['action'] === 'delete') {
    $del_id = (int)($_GET['id'] ?? 0);
    if ($del_id > 0) {
        $stmt_img =$conn->prepare("SELECT image FROM blog_posts WHERE id = ?");
        $stmt_img->execute([$del_id]);
        $old_img =$stmt_img->fetchColumn();

        if ($old_img && file_exists($upload_dir .$old_img)) {
            @unlink($upload_dir .$old_img);
        }

        $conn->prepare("DELETE FROM blog_posts WHERE id = ?")->execute([$del_id]);$msg = 'مقاله مورد نظر با موفقیت حذف گردید.';
    }
}

// ۲. ذخیره یا ویرایش مقاله
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_post'])) {
    $post_id = (int)($_POST['post_id'] ?? 0);
    $title = safe($_POST['title'] ?? '');
    $category = safe($_POST['category'] ?? 'آداب زیارت');
    $read_time = safe($_POST['read_time'] ?? '۵ دقیقه');
    $status = in_array($_POST['status'] ?? '', ['published', 'draft']) ?$_POST['status'] : 'published';
    $summary = trim($_POST['summary'] ?? '');
    // محتوای ادیتور نباید safe() شود تا تگ‌های مجاز HTML باقی بمانند
    $content = trim($_POST['content'] ?? '');

    if (empty($title) || empty($content)) {$err = 'عنوان مقاله و محتوای اصلی نمی‌توانند خالی باشند.';
    } else {
        // مدیریت آپلود تصویر شاخص
        $image_name = null;
        if (isset($_FILES['featured_image']) &&$_FILES['featured_image']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['featured_image']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
                $image_name = 'blog_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                move_uploaded_file($_FILES['featured_image']['tmp_name'], $upload_dir .$image_name);
            } else {
                $err = 'فرمت تصویر شاخص نامعتبر است (تنها JPG, PNG, WebP مجاز است).';
            }
        }

        if (empty($err)) {
            if ($post_id > 0) {
                // ویرایش مقاله موجود
                if ($image_name) {
                    // حذف عکس قبلی
                    $stmt_old =$conn->prepare("SELECT image FROM blog_posts WHERE id = ?");
                    $stmt_old->execute([$post_id]);
                    $prev_img =$stmt_old->fetchColumn();
                    if ($prev_img && file_exists($upload_dir .$prev_img)) @unlink($upload_dir .$prev_img);

                    $stmt_u =$conn->prepare("UPDATE blog_posts SET title = ?, category = ?, summary = ?, content = ?, image = ?, read_time = ?, status = ? WHERE id = ?");
                    $stmt_u->execute([$title,$category, $summary,$content, $image_name,$read_time, $status,$post_id]);
                } else {
                    $stmt_u =$conn->prepare("UPDATE blog_posts SET title = ?, category = ?, summary = ?, content = ?, read_time = ?, status = ? WHERE id = ?");
                    $stmt_u->execute([$title, $category,$summary, $content,$read_time, $status,$post_id]);
                }
                $msg = 'مقاله با موفقیت ویرایش و به‌روزرسانی شد.';
            } else {
                // درج مقاله جدید
                $stmt_i =$conn->prepare("INSERT INTO blog_posts (title, category, summary, content, image, read_time, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt_i->execute([$title,$category, $summary,$content, $image_name,$read_time, $status]);$msg = 'مقاله جدید با موفقیت منتشر گردید.';
            }
        }
    }
}

// واکشی مقاله جهت ویرایش (در صورت کلیک روی ویرایش)
$edit_post = null;
if (isset($_GET['action']) &&$_GET['action'] === 'edit') {
    $edit_id = (int)($_GET['id'] ?? 0);
    if ($edit_id > 0) {
        $stmt_e =$conn->prepare("SELECT * FROM blog_posts WHERE id = ?");
        $stmt_e->execute([$edit_id]);
        $edit_post =$stmt_e->fetch();
    }
}

// واکشی تمام مقالات
$posts =$conn->query("SELECT * FROM blog_posts ORDER BY id DESC")->fetchAll();

require_once __DIR__ . '/header.php';
?>

<!-- فراخوانی ویرایشگر پیشرفته TinyMCE -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/tinymce/6.8.2/tinymce.min.js"></script>

<div class="space-y-8">

    <!-- نوار عنوان -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center pb-4 border-b border-amber-500/20 gap-3">
        <div>
            <h1 class="text-xl sm:text-2xl font-black text-white flex items-center gap-2">
                <span>📝</span> دانشنامه زیارت و تحریریه مقالات
            </h1>
            <p class="text-xs text-amber-200/80 mt-1">تولید محتوای آموزشی، آداب سفر، راهنمای اماکن مقدسه و اخبار کاروان با ویرایشگر پیشرفته</p>
        </div>
        
        <?php if ($edit_post): ?>
            <a href="blog.php" class="bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-bold px-3.5 py-2 rounded-xl transition border border-white/10 flex items-center gap-1">
                <span>➕</span> انصراف از ویرایش و نوشتن مقاله جدید
            </a>
        <?php endif; ?>
    </div>

    <?php if ($msg): ?>
        <div class="p-3.5 bg-emerald-950/60 border border-emerald-500/40 text-emerald-200 text-xs rounded-2xl font-bold flex items-center gap-2">
            <span>✅</span> <?= safe($msg) ?>
        </div>
    <?php endif; ?>

    <?php if ($err): ?>
        <div class="p-3.5 bg-rose-950/60 border border-rose-500/40 text-rose-200 text-xs rounded-2xl font-bold flex items-center gap-2">
            <span>❌</span> <?= safe($err) ?>
        </div>
    <?php endif; ?>

    <!-- فرم حرفه‌ای ایجاد / ویرایش مقاله -->
    <div class="rogh-card rounded-3xl p-6 sm:p-8 border border-amber-500/30 space-y-6">
        <div class="flex items-center gap-2 pb-3 border-b border-amber-500/20">
            <span class="text-amber-400 text-sm">✦</span>
            <h2 class="font-black text-sm text-white">
                <?= $edit_post ? 'ویرایش مقاله: ' . safe($edit_post['title']) : 'نگارش مقاله جدید در دانشنامه' ?>
            </h2>
        </div>

        <form action="blog.php" method="POST" enctype="multipart/form-data" class="space-y-5">
            <input type="hidden" name="save_post" value="1">
            <input type="hidden" name="post_id" value="<?= $edit_post['id'] ?? 0 ?>">

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="md:col-span-2">
                    <label class="block text-xs font-bold text-slate-200 mb-1.5">عنوان جذاب مقاله: <span class="text-rose-400">*</span></label>
                    <input type="text" name="title" required placeholder="مثال: راهنمای جامع زیارت نیابتی و آداب ورود به حرم امام حسین (ع)" 
                           value="<?= safe($edit_post['title'] ?? '') ?>" 
                           class="w-full bg-[#031712] border border-amber-500/30 rounded-xl p-3 text-xs text-white focus:outline-none focus:border-amber-400">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-200 mb-1.5">دسته‌بندی موضوعی:</label>
                    <select name="category" class="w-full bg-[#031712] border border-amber-500/30 rounded-xl p-3 text-xs text-white">
                        <?php 
                            $cats = ['آداب زیارت', 'احکام و معارف', 'راهنمای سفر و هتل‌ها', 'تاریخ و اماکن مقدسه', 'وسایل مورد نیاز', 'اطلاعیه‌های کاروان'];
                            $cur_cat =$edit_post['category'] ?? 'آداب زیارت';
                            foreach ($cats as$c):
                        ?>
                            <option value="<?= $c ?>" <?= $cur_cat === $c ? 'selected' : '' ?>><?= $c ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-200 mb-1.5">مدت‌زمان تقریبی مطالعه:</label>
                    <input type="text" name="read_time" placeholder="مثال: ۵ دقیقه" 
                           value="<?= safe($edit_post['read_time'] ?? '۵ دقیقه') ?>" 
                           class="w-full bg-[#031712] border border-amber-500/30 rounded-xl p-2.5 text-xs text-amber-300 font-mono">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-200 mb-1.5">وضعیت انتشار:</label>
                    <select name="status" class="w-full bg-[#031712] border border-amber-500/30 rounded-xl p-2.5 text-xs text-white">
                        <option value="published" <?= ($edit_post['status'] ?? '') === 'published' ? 'selected' : '' ?>>عمومی و منتشرشده</option>
                        <option value="draft" <?= ($edit_post['status'] ?? '') === 'draft' ? 'selected' : '' ?>>پیش‌نویس (مخفی)</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-200 mb-1.5">تصویر شاخص (کاور مقاله):</label>
                    <input type="file" name="featured_image" accept="image/*" 
                           class="w-full text-xs text-slate-400 file:mr-2 file:py-1.5 file:px-3 file:rounded-xl file:border-0 file:bg-amber-500/20 file:text-amber-300 cursor-pointer">
                </div>
            </div>

            <?php if (!empty($edit_post['image']) && file_exists($upload_dir .$edit_post['image'])): ?>
                <div class="flex items-center gap-3 p-3 bg-[#031712] rounded-xl border border-amber-500/20 w-fit">
                    <img src="../uploads/blog/<?= safe($edit_post['image']) ?>" class="w-16 h-12 rounded-lg object-cover border border-amber-500/30">
                    <span class="text-[11px] text-slate-400">تصویر شاخص فعلی مقاله</span>
                </div>
            <?php endif; ?>

            <div>
                <label class="block text-xs font-bold text-slate-200 mb-1.5">خلاصه کوتاه مقاله (جهت نمایش در کارت‌ها و سئو):</label>
                <textarea name="summary" rows="2" placeholder="توضیحی کوتاه در ۱ یا ۲ جمله که در صفحه اول نمایش داده می‌شود..." 
                          class="w-full bg-[#031712] border border-amber-500/30 rounded-xl p-3 text-xs text-slate-200 leading-relaxed"><?= safe($edit_post['summary'] ?? '') ?></textarea>
            </div>

            <!-- ادیتور متنی پیشرفته TinyMCE -->
            <div>
                <div class="flex justify-between items-center mb-1.5">
                    <label class="block text-xs font-bold text-amber-300">محتوای کامل مقاله (با امکان درج عکس، لینک، جدول و ویدیو): <span class="text-rose-400">*</span></label>
                    <span class="text-[10px] text-slate-400">محیط نگارش غنی و چندرسانه‌ای</span>
                </div>
                <textarea id="article_editor" name="content" rows="16" class="w-full"><?= $edit_post['content'] ?? '' ?></textarea>
            </div>

            <div class="pt-2">
                <button type="submit" class="w-full bg-gradient-to-r from-amber-500 via-amber-400 to-amber-500 hover:from-amber-400 text-slate-950 font-black py-3.5 rounded-2xl text-xs sm:text-sm transition duration-200 shadow-xl shadow-amber-500/20 flex items-center justify-center gap-2">
                    <span>💾</span> <?= $edit_post ? 'ذخیره تغییرات مقاله' : 'انتشار رسمی مقاله در دانشنامه زیارت' ?>
                </button>
            </div>
        </form>
    </div>

    <!-- جدول مقالات ثبت‌شده -->
    <div class="rogh-card rounded-2xl overflow-hidden border border-amber-500/30">
        <div class="p-4 border-b border-amber-500/20 flex justify-between items-center bg-[#031511]">
            <h2 class="font-black text-xs sm:text-sm text-white flex items-center gap-2">
                <span>📚</span> فهرست مقالات دانشنامه
            </h2>
            <span class="text-xs text-amber-200/80 font-mono">تعداد: <?= count($posts) ?> مطلب</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-right border-collapse text-xs">
                <thead>
                    <tr class="bg-[#02100d] text-amber-200/80 border-b border-amber-500/10 whitespace-nowrap">
                        <th class="p-3.5" style="width: 40px;">ردیف</th>
                        <th class="p-3.5">تصویر</th>
                        <th class="p-3.5">عنوان مقاله</th>
                        <th class="p-3.5">دسته‌بندی</th>
                        <th class="p-3.5 text-center">زمان مطالعه</th>
                        <th class="p-3.5 text-center">وضعیت</th>
                        <th class="p-3.5 text-center">عملیات</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-amber-500/10">
                    <?php if (empty($posts)): ?>
                        <tr><td colspan="7" class="text-center py-10 text-slate-500">هنوز مقاله‌ای منتشر نشده است. از فرم بالا اولین مقاله را بنویسید.</td></tr>
                    <?php else: ?>
                        <?php foreach ($posts as $idx =>$p): ?>
                            <tr class="hover:bg-white/5 transition">
                                <td class="p-3.5 text-slate-400 font-mono text-center"><?= $idx + 1 ?></td>
                                
                                <td class="p-3.5">
                                    <div class="w-12 h-10 rounded-lg overflow-hidden bg-black/40 border border-white/10 flex items-center justify-center shrink-0">
                                        <?php if (!empty($p['image']) && file_exists($upload_dir .$p['image'])): ?>
                                            <img src="../uploads/blog/<?= safe($p['image']) ?>" class="w-full h-full object-cover">
                                        <?php else: ?>
                                            <span class="text-slate-600 text-xs">بدون کاور</span>
                                        <?php endif; ?>
                                    </div>
                                </td>

                                <td class="p-3.5">
                                    <div class="font-bold text-white text-sm"><?= safe($p['title']) ?></div>
                                    <div class="text-[11px] text-slate-400 line-clamp-1 mt-0.5"><?= safe($p['summary']) ?></div>
                                </td>

                                <td class="p-3.5">
                                    <span class="bg-[#031712] text-amber-300 border border-amber-500/30 px-2.5 py-1 rounded-full text-[10px] font-bold">
                                        <?= safe($p['category']) ?>
                                    </span>
                                </td>

                                <td class="p-3.5 text-center font-mono text-slate-300">
                                    <?= safe($p['read_time']) ?>
                                </td>

                                <td class="p-3.5 text-center">
                                    <?php if ($p['status'] === 'published'): ?>
                                        <span class="bg-emerald-500/20 text-emerald-300 border border-emerald-500/40 px-2.5 py-0.5 rounded-full text-[10px] font-bold">منتشرشده</span>
                                    <?php else: ?>
                                        <span class="bg-slate-700 text-slate-300 px-2.5 py-0.5 rounded-full text-[10px]">پیش‌نویس</span>
                                    <?php endif; ?>
                                </td>

                                <td class="p-3.5 text-center whitespace-nowrap">
                                    <div class="inline-flex items-center gap-2">
                                        <a href="../single.php?id=<?= $p['id'] ?>" target="_blank" class="text-emerald-400 hover:underline">مشاهده</a>
                                        <span class="text-slate-600">|</span>
                                        <a href="blog.php?action=edit&id=<?= $p['id'] ?>" class="text-amber-300 hover:underline">ویرایش</a>
                                        <span class="text-slate-600">|</span>
                                        <a href="blog.php?action=delete&id=<?= $p['id'] ?>" onclick="return confirm('آیا از حذف این مقاله اطمینان دارید؟');" class="text-rose-400 hover:underline">حذف</a>
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

<!-- اسکریپت کانفیگ حرفه‌ای TinyMCE -->
<script>
    tinymce.init({
        selector: '#article_editor',
        directionality: 'rtl',
        language: 'fa',
        height: 480,
        menubar: 'file edit view insert format tools table help',
        plugins: [
            'advlist', 'autolink', 'lists', 'link', 'image', 'charmap', 'preview',
            'anchor', 'searchreplace', 'visualblocks', 'code', 'fullscreen',
            'insertdatetime', 'media', 'table', 'help', 'wordcount', 'directionality'
        ],
        toolbar: 'undo redo | blocks fontfamily fontsize | bold italic underline strikethrough | forecolor backcolor | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | ltr rtl | link image media table | removeformat code fullscreen',
        content_style: `
            @import url('https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css');
            body { font-family: 'Vazirmatn', Tahoma, sans-serif; font-size: 14px; line-height: 1.8; direction: rtl; color: #1e293b; padding: 15px; }
            img { max-width: 100%; height: auto; border-radius: 12px; margin: 10px 0; }
            a { color: #d97706; text-decoration: underline; }
            blockquote { border-right: 4px solid #d97706; padding-right: 12px; margin: 10px 0; color: #475569; font-style: italic; }
        `,
        image_title: true,
        automatic_uploads: true,
        file_picker_types: 'image',
        paste_data_images: true // امکان Paste مستقیم عکس از کلیپ‌بورد
    });
</script>

<?php require_once __DIR__ . '/footer.php'; ?>