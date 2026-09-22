<?php
require_once 'db.php';
$settings = get_settings($conn);

$post_id = (int)($_GET['id'] ?? 0);
if ($post_id <= 0) {
    header('Location: index.php');
    exit;
}

// واکشی مقاله
$stmt = $conn->prepare("SELECT * FROM blog_posts WHERE id = ? AND status = 'published' LIMIT 1");
$stmt->execute([$post_id]);
$post = $stmt->fetch();

if (!$post) {
    die('<div style="direction:rtl;font-family:Tahoma;padding:40px;text-align:center;color:#f43f5e;background:#041411;">مطلب مورد نظر یافت نشد یا در حالت پیش‌نویس قرار دارد. <a href="index.php" style="color:#fbbf24;">بازگشت به صفحه اصلی</a></div>');
}

// افزایش بازدید
$conn->prepare("UPDATE blog_posts SET views = views + 1 WHERE id = ?")->execute([$post_id]);

// مقالات مرتبط
$stmt_rel = $conn->prepare("SELECT id, title, image, read_time FROM blog_posts WHERE id != ? AND status = 'published' ORDER BY id DESC LIMIT 3");
$stmt_rel->execute([$post_id]);
$related_posts = $stmt_rel->fetchAll();
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= safe($post['title']) ?> | <?= safe($settings['site_title'] ?? 'کاروان زیارتی') ?></title>
    
    <link rel="icon" href="img/icon.png">
    <script src="https://cdn.tailwindcss.com"></script>
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
            border: 1px solid rgba(212, 175, 55, 0.3); 
            box-shadow: 0 20px 40px -15px rgba(0, 0, 0, 0.7);
        }
        /* استایل اختصاصی برای محتوای غنی ادیتور */
        .article-content {
            font-size: 13.5px;
            line-height: 2.1;
            color: #e2e8f0;
        }
        .article-content h1, .article-content h2, .article-content h3 {
            color: #fef08a;
            font-weight: 800;
            margin-top: 1.8rem;
            margin-bottom: 0.8rem;
        }
        .article-content h2 { font-size: 1.25rem; border-bottom: 1px solid rgba(212, 175, 55, 0.2); padding-bottom: 6px; }
        .article-content h3 { font-size: 1.05rem; }
        .article-content p { margin-bottom: 1.2rem; text-align: justify; }
        .article-content img {
            border-radius: 16px;
            max-width: 100%;
            height: auto;
            margin: 20px auto;
            box-shadow: 0 10px 25px rgba(0,0,0,0.5);
            border: 1px solid rgba(212, 175, 55, 0.25);
        }
        .article-content a {
            color: #fbbf24;
            text-decoration: underline;
            font-weight: bold;
        }
        .article-content ul, .article-content ol {
            margin-right: 1.5rem;
            margin-bottom: 1.2rem;
        }
        .article-content ul { list-style-type: disc; }
        .article-content ol { list-style-type: decimal; }
        .article-content blockquote {
            border-right: 4px solid #d4af37;
            background: rgba(3, 23, 18, 0.6);
            padding: 12px 18px;
            margin: 18px 0;
            border-radius: 0 12px 12px 0;
            color: #cbd5e1;
            font-style: italic;
        }
        .article-content table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        .article-content table, .article-content th, .article-content td {
            border: 1px solid rgba(212, 175, 55, 0.3);
            padding: 8px 12px;
        }
        .article-content th { background-color: rgba(212, 175, 55, 0.15); color: #fef08a; }
    </style>
</head>
<body class="islami-bg min-h-screen flex flex-col selection:bg-amber-500 selection:text-black">

    <!-- نوار بالا -->
    <header class="bg-[#051f19]/90 backdrop-blur-md border-b border-amber-500/30 sticky top-0 z-40">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 h-18 py-3 flex justify-between items-center">
            <a href="index.php" class="flex items-center gap-3">
                <span class="text-2xl">🕌</span>
                <span class="font-black text-white text-sm sm:text-base"><?= safe($settings['site_title'] ?? 'کاروان زیارتی') ?></span>
            </a>

            <a href="index.php" class="text-xs text-amber-300 hover:text-white font-bold flex items-center gap-1 transition">
                <span>&larr;</span> بازگشت به صفحه اصلی
            </a>
        </div>
    </header>

    <!-- بدنه اصلی مقاله -->
    <main class="max-w-4xl mx-auto w-full px-4 sm:px-6 py-10 space-y-8 flex-grow">
        
        <article class="rogh-card rounded-3xl p-6 sm:p-10 border border-amber-500/30 space-y-6">
            
            <!-- متادیتا -->
            <div class="space-y-3 pb-6 border-b border-amber-500/20">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="bg-[#031712] text-amber-300 border border-amber-500/30 px-3 py-1 rounded-full text-xs font-bold">
                        🏷️ <?= safe($post['category']) ?>
                    </span>
                    <span class="text-xs text-slate-400 font-mono">
                        ⏱️ مدت مطالعه: <?= safe($post['read_time']) ?>
                    </span>
                    <span class="text-slate-600">|</span>
                    <span class="text-xs text-slate-400 font-mono">
                        👁️ <?= number_format($post['views']) ?> بازدید
                    </span>
                </div>

                <h1 class="text-2xl sm:text-4xl font-black text-white leading-snug">
                    <?= safe($post['title']) ?>
                </h1>

                <?php if (!empty($post['summary'])): ?>
                    <p class="text-xs sm:text-sm text-slate-300 leading-relaxed font-medium bg-[#031712] p-4 rounded-2xl border border-amber-500/15">
                        <?= safe($post['summary']) ?>
                    </p>
                <?php endif; ?>
            </div>

            <!-- تصویر شاخص -->
            <?php if (!empty($post['image']) && file_exists(__DIR__ . '/uploads/blog/' . $post['image'])): ?>
                <div class="rounded-2xl overflow-hidden border border-amber-500/25 max-h-[450px]">
                    <img src="uploads/blog/<?= safe($post['image']) ?>" alt="<?= safe($post['title']) ?>" class="w-full h-full object-cover">
                </div>
            <?php endif; ?>

            <!-- متن اصلی با قالب‌بندی غنی -->
            <div class="article-content pt-2">
                <?= $post['content'] ?>
            </div>

            <!-- اشتراک‌گذاری و کانال‌ها -->
            <div class="pt-6 border-t border-amber-500/20 flex flex-col sm:flex-row justify-between items-center gap-3">
                <span class="text-xs text-slate-400">از همراهی شما با کاروان سپاسگزاریم.</span>
                <div class="flex gap-2">
                    <a href="index.php#caravans_section" class="bg-gradient-to-r from-amber-500 to-amber-600 text-slate-950 font-black px-4 py-2 rounded-xl text-xs">
                        مشاهده کاروان‌های در حال ثبت‌نام &larr;
                    </a>
                </div>
            </div>

        </article>

        <!-- مقالات مرتبط -->
        <?php if (!empty($related_posts)): ?>
            <div class="space-y-4 pt-4">
                <h3 class="font-black text-white text-base flex items-center gap-2">
                    <span>📚</span> سایر مطالب خواندنی دانشنامه:
                </h3>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <?php foreach ($related_posts as $rp): ?>
                        <a href="single.php?id=<?= $rp['id'] ?>" class="rogh-card p-4 rounded-2xl border border-amber-500/20 hover:border-amber-400 transition block space-y-2 group">
                            <?php if (!empty($rp['image']) && file_exists(__DIR__ . '/uploads/blog/' . $rp['image'])): ?>
                                <div class="h-28 rounded-xl overflow-hidden mb-2">
                                    <img src="uploads/blog/<?= safe($rp['image']) ?>" class="w-full h-full object-cover group-hover:scale-105 transition duration-300">
                                </div>
                            <?php endif; ?>
                            <h4 class="font-bold text-xs text-white group-hover:text-amber-300 line-clamp-2 leading-relaxed">
                                <?= safe($rp['title']) ?>
                            </h4>
                            <span class="text-[10px] text-slate-400 font-mono block">⏱️ <?= safe($rp['read_time']) ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

    </main>

    <?php require_once 'footer.php'; ?>

</body>
</html>