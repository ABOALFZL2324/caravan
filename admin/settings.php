<?php
require_once __DIR__ . '/header.php';

// فقط مدیر ارشد به تنظیمات کلی سامانه دسترسی دارد
require_roles(['super_admin']);

$msg = '';$err = '';

// بارگذاری تنظیمات فعلی
$settings = get_settings($conn);

// پردازش ذخیره تنظیمات عمومی
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {$fields = [
        'site_title', 'site_tagline', 'site_description', 
        'phone_number', 'mobile_number', 'address', 
        'eitaa_channel', 'telegram_channel', 'bale_channel', 'instagram_page',
        'bank_card_number', 'bank_shaba_number', 'bank_owner_name', 'bank_name',
        'rules_and_conditions'
    ];

    // پردازش سوالات متداول (FAQ) به صورت JSON
    $faq_questions = $_POST['faq_question'] ?? [];$faq_answers = $_POST['faq_answer'] ?? [];$faqs_data = [];

    if (is_array($faq_questions)) {
        for ($i = 0; $i < count($faq_questions);$i++) {
            $q = trim($faq_questions[$i]);$a = trim($faq_answers[$i] ?? '');
            if (!empty($q) && !empty($a)) {$faqs_data[] = [
                    'question' => safe($q),
                    'answer' => safe($a)
                ];
            }
        }
    }
    $faqs_json = !empty($faqs_data) ? json_encode($faqs_data, JSON_UNESCAPED_UNICODE) : '[]';

    try {
        $conn->beginTransaction();

        $stmt_save =$conn->prepare("INSERT INTO settings (setting_key, setting_value) 
                                     VALUES (?, ?) 
                                     ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");

        foreach ($fields as$field) {
            $val = trim($_POST[$field] ?? '');$stmt_save->execute([$field,$val]);
        }

        // ذخیره پرسش و پاسخ‌ها
        $stmt_save->execute(['faqs_list',$faqs_json]);

        // پردازش آپلود لوگوی اصلی سایت
        $upload_dir = __DIR__ . '/../uploads/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

        if (isset($_FILES['site_logo']) &&$_FILES['site_logo']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['site_logo']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['png', 'jpg', 'jpeg', 'svg', 'webp'])) {
                $new_logo_name = 'logo_' . time() . '.' . $ext;
                if (move_uploaded_file($_FILES['site_logo']['tmp_name'], $upload_dir .$new_logo_name)) {
                    $stmt_save->execute(['site_logo',$new_logo_name]);
                }
            } else {
                $err = 'فرمت لوگو نامعتبر است (تنها PNG, JPG, SVG, WebP مجاز است).';
            }
        }

        // پردازش آپلود فاوآیکون (img/icon.png)
        $img_dir = __DIR__ . '/../img/';
        if (!is_dir($img_dir)) mkdir($img_dir, 0755, true);

        if (isset($_FILES['site_favicon']) &&$_FILES['site_favicon']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['site_favicon']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['png', 'ico'])) {
                move_uploaded_file($_FILES['site_favicon']['tmp_name'],$img_dir . 'icon.png');
            }
        }

        $conn->commit();$msg = 'تنظیمات کلی سامانه و سوالات متداول با موفقیت به‌روزرسانی شد.';
        $settings = get_settings($conn);

    } catch (Exception $e) {
        if ($conn->inTransaction())$conn->rollBack();
        $err = 'خطا در ذخیره‌سازی تنظیمات: ' . $e->getMessage();
    }
}

// بازخوانی پرسش‌های متداول ذخیره‌شده
$current_faqs = [];
if (!empty($settings['faqs_list'])) {
    $current_faqs = json_decode($settings['faqs_list'], true) ?: [];
}
?>

<div class="space-y-6 max-w-5xl mx-auto text-xs">

    <!-- نوار عنوان -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center pb-4 border-b border-amber-500/20 gap-3">
        <div>
            <h1 class="text-xl sm:text-2xl font-black text-white flex items-center gap-2">
                <span>⚙️</span> تنظیمات عمومی، هویت بصری و سوالات متداول
            </h1>
            <p class="text-xs text-amber-200/80 mt-1">مدیریت مشخصات کاروان، لوگو، حساب‌های بانکی و پرسش‌های متداول زائران</p>
        </div>
        <a href="sms.php" class="text-xs text-amber-300 font-bold bg-[#031712] hover:bg-[#08261e] px-3.5 py-2 rounded-xl border border-amber-500/30 transition flex items-center gap-1.5">
            <span>📱</span> رفتن به مرکز پیامک
        </a>
    </div>

    <?php if ($msg): ?>
        <div class="p-3.5 bg-emerald-950/60 border border-emerald-500/40 text-emerald-200 rounded-2xl font-bold flex items-center gap-2">
            <span>✅</span> <?= safe($msg) ?>
        </div>
    <?php endif; ?>

    <?php if ($err): ?>
        <div class="p-3.5 bg-rose-950/60 border border-rose-500/40 text-rose-200 rounded-2xl font-bold flex items-center gap-2">
            <span>❌</span> <?= safe($err) ?>
        </div>
    <?php endif; ?>

    <form action="settings.php" method="POST" enctype="multipart/form-data" class="space-y-6">
        <input type="hidden" name="save_settings" value="1">

        <!-- بخش ۱: هویت، نام و تصاویر سامانه -->
        <div class="rogh-card rounded-3xl p-6 sm:p-8 space-y-5 border border-amber-500/30">
            <div class="flex items-center gap-2 pb-3 border-b border-amber-500/20">
                <span class="text-amber-400 text-sm">✦</span>
                <h2 class="font-black text-sm text-white">۱. نام مجموعه، هویت بصری و لوگو</h2>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block font-bold text-slate-200 mb-1.5">عنوان رسمی کاروان / سایت: <span class="text-rose-400">*</span></label>
                    <input type="text" name="site_title" required 
                           value="<?= safe($settings['site_title'] ?? 'کاروان زیارتی محبان اهل‌بیت (ع)') ?>" 
                           class="w-full bg-[#031712] border border-amber-500/30 rounded-xl p-3 text-white focus:outline-none focus:border-amber-400">
                </div>

                <div>
                    <label class="block font-bold text-slate-200 mb-1.5">شعار یا زیرعنوان کاروان:</label>
                    <input type="text" name="site_tagline" 
                           value="<?= safe($settings['site_tagline'] ?? 'مجری تخصصی تورهای عتبات عالیات و مشهد مقدس') ?>" 
                           class="w-full bg-[#031712] border border-amber-500/30 rounded-xl p-3 text-white focus:outline-none focus:border-amber-400">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 pt-2">
                <!-- آپلود لوگوی اصلی -->
                <div class="p-4 bg-[#031712] rounded-2xl border border-amber-500/20 space-y-2">
                    <label class="block font-bold text-amber-300">لوگوی اصلی سایت:</label>
                    <div class="flex items-center gap-4">
                        <div class="w-16 h-16 bg-black/40 rounded-xl border border-amber-500/30 p-1 flex items-center justify-center shrink-0 overflow-hidden">
                            <?php if (!empty($settings['site_logo']) && file_exists(__DIR__ . '/../uploads/' .$settings['site_logo'])): ?>
                                <img src="../uploads/<?= safe($settings['site_logo']) ?>" class="w-full h-full object-contain">
                            <?php else: ?>
                                <span class="text-2xl">🕌</span>
                            <?php endif; ?>
                        </div>
                        <div class="flex-1">
                            <input type="file" name="site_logo" accept="image/*" class="text-[11px] text-slate-400 file:mr-2 file:py-1 file:px-2.5 file:rounded-xl file:border-0 file:bg-amber-500/20 file:text-amber-300 cursor-pointer">
                            <span class="text-[10px] text-slate-500 block mt-1">فرمت مجاز: PNG, JPG, SVG</span>
                        </div>
                    </div>
                </div>

                <!-- آپلود فاوآیکون -->
                <div class="p-4 bg-[#031712] rounded-2xl border border-amber-500/20 space-y-2">
                    <label class="block font-bold text-amber-300">آیکون تب مرورگر (Favicon):</label>
                    <div class="flex items-center gap-4">
                        <div class="w-16 h-16 bg-black/40 rounded-xl border border-amber-500/30 p-1 flex items-center justify-center shrink-0 overflow-hidden">
                            <?php if (file_exists(__DIR__ . '/../img/icon.png')): ?>
                                <img src="../img/icon.png" class="w-full h-full object-contain">
                            <?php else: ?>
                                <span class="text-2xl">⭐</span>
                            <?php endif; ?>
                        </div>
                        <div class="flex-1">
                            <input type="file" name="site_favicon" accept=".png,.ico" class="text-[11px] text-slate-400 file:mr-2 file:py-1 file:px-2.5 file:rounded-xl file:border-0 file:bg-amber-500/20 file:text-amber-300 cursor-pointer">
                            <span class="text-[10px] text-slate-500 block mt-1">فایل PNG یا ICO</span>
                        </div>
                    </div>
                </div>
            </div>

            <div>
                <label class="block font-bold text-slate-200 mb-1.5">توضیحات کوتاه معرف مجموعه (سئو):</label>
                <textarea name="site_description" rows="2" 
                          class="w-full bg-[#031712] border border-amber-500/30 rounded-xl p-3 text-slate-200 leading-relaxed"><?= safe($settings['site_description'] ?? '') ?></textarea>
            </div>
        </div>

        <!-- بخش ۲: اطلاعات تماس و کانال‌های اطلاع‌رسانی -->
        <div class="rogh-card rounded-3xl p-6 sm:p-8 space-y-5 border border-amber-500/30">
            <div class="flex items-center gap-2 pb-3 border-b border-amber-500/20">
                <span class="text-amber-400 text-sm">✦</span>
                <h2 class="font-black text-sm text-white">۲. خطوط ارتباطی و شبکه‌های اجتماعی</h2>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
                <div>
                    <label class="block font-bold text-slate-200 mb-1.5">شماره تلفن ثابت دفتر:</label>
                    <input type="text" name="phone_number" value="<?= safe($settings['phone_number'] ?? '') ?>" placeholder="۰۲۱۱۲۳۴۵۶۷۸" 
                           class="w-full bg-[#031712] border border-amber-500/30 rounded-xl p-2.5 text-white font-mono">
                </div>

                <div>
                    <label class="block font-bold text-slate-200 mb-1.5">شماره همراه مدیر:</label>
                    <input type="text" name="mobile_number" value="<?= safe($settings['mobile_number'] ?? '') ?>" placeholder="۰۹۱۲۳۴۵۶۷۸۹" 
                           class="w-full bg-[#031712] border border-amber-500/30 rounded-xl p-2.5 text-white font-mono">
                </div>

                <div>
                    <label class="block font-bold text-slate-200 mb-1.5">کانال ایتا (Eitaa):</label>
                    <input type="text" name="eitaa_channel" value="<?= safe($settings['eitaa_channel'] ?? '') ?>" placeholder="eitaa.com/karavan" 
                           class="w-full bg-[#031712] border border-amber-500/30 rounded-xl p-2.5 text-amber-300 font-mono">
                </div>

                <div>
                    <label class="block font-bold text-slate-200 mb-1.5">کانال بله (Bale):</label>
                    <input type="text" name="bale_channel" value="<?= safe($settings['bale_channel'] ?? '') ?>" placeholder="ble.ir/karavan" 
                           class="w-full bg-[#031712] border border-amber-500/30 rounded-xl p-2.5 text-emerald-300 font-mono">
                </div>

                <div>
                    <label class="block font-bold text-slate-200 mb-1.5">کانال تلگرام:</label>
                    <input type="text" name="telegram_channel" value="<?= safe($settings['telegram_channel'] ?? '') ?>" placeholder="t.me/karavan" 
                           class="w-full bg-[#031712] border border-amber-500/30 rounded-xl p-2.5 text-teal-300 font-mono">
                </div>

                <div>
                    <label class="block font-bold text-slate-200 mb-1.5">صفحه اینستاگرام:</label>
                    <input type="text" name="instagram_page" value="<?= safe($settings['instagram_page'] ?? '') ?>" placeholder="instagram.com/karavan" 
                           class="w-full bg-[#031712] border border-amber-500/30 rounded-xl p-2.5 text-rose-300 font-mono">
                </div>
            </div>

            <div>
                <label class="block font-bold text-slate-200 mb-1.5">نشانی پستی دفتر کاروان:</label>
                <input type="text" name="address" value="<?= safe($settings['address'] ?? '') ?>" 
                       class="w-full bg-[#031712] border border-amber-500/30 rounded-xl p-2.5 text-white">
            </div>
        </div>

        <!-- بخش ۳: شماره حساب‌های واریز بیعانه -->
        <div class="rogh-card rounded-3xl p-6 sm:p-8 space-y-5 border border-amber-500/30">
            <div class="flex items-center gap-2 pb-3 border-b border-amber-500/20">
                <span class="text-amber-400 text-sm">✦</span>
                <h2 class="font-black text-sm text-white">۳. حساب بانکی جهت واریز بیعانه زائران</h2>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">
                <div>
                    <label class="block font-bold text-slate-200 mb-1.5">شماره کارت (۱۶ رقمی):</label>
                    <input type="text" name="bank_card_number" maxlength="19" value="<?= safe($settings['bank_card_number'] ?? '') ?>" 
                           class="w-full bg-[#031712] border border-amber-500/30 rounded-xl p-2.5 text-amber-300 font-mono text-center">
                </div>

                <div>
                    <label class="block font-bold text-slate-200 mb-1.5">شماره شبا (IBAN):</label>
                    <input type="text" name="bank_shaba_number" value="<?= safe($settings['bank_shaba_number'] ?? '') ?>" 
                           class="w-full bg-[#031712] border border-amber-500/30 rounded-xl p-2.5 text-white font-mono text-center">
                </div>

                <div>
                    <label class="block font-bold text-slate-200 mb-1.5">نام صاحب حساب:</label>
                    <input type="text" name="bank_owner_name" value="<?= safe($settings['bank_owner_name'] ?? '') ?>" 
                           class="w-full bg-[#031712] border border-amber-500/30 rounded-xl p-2.5 text-white">
                </div>

                <div>
                    <label class="block font-bold text-slate-200 mb-1.5">نام بانک:</label>
                    <input type="text" name="bank_name" value="<?= safe($settings['bank_name'] ?? '') ?>" 
                           class="w-full bg-[#031712] border border-amber-500/30 rounded-xl p-2.5 text-white">
                </div>
            </div>
        </div>

        <!-- بخش ۴: مدیریت داینامیک پرسش و پاسخ‌های متداول (FAQ) -->
        <div class="rogh-card rounded-3xl p-6 sm:p-8 space-y-5 border border-amber-500/30">
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center pb-3 border-b border-amber-500/20 gap-2">
                <div>
                    <div class="flex items-center gap-2">
                        <span class="text-amber-400 text-sm">✦</span>
                        <h2 class="font-black text-sm text-white">۴. پرسش و پاسخ‌های متداول زائران (FAQ صفحه اصلی)</h2>
                    </div>
                    <p class="text-[11px] text-slate-400 mt-0.5">افزودن یا ویرایش سوالات متداول جهت نمایش در صفحه اول سایت:</p>
                </div>
                
                <button type="button" onclick="addFaqRow()" 
                        class="bg-amber-500/20 hover:bg-amber-500/30 text-amber-300 border border-amber-500/40 px-3.5 py-2 rounded-xl text-xs font-bold transition flex items-center gap-1">
                    <span>➕</span> افزودن پرسش جدید
                </button>
            </div>

            <div id="faqs_container" class="space-y-3">
                <!-- توسط جاوااسکریپت بارگذاری می‌شود -->
            </div>
        </div>

        <!-- بخش ۵: متن قوانین و تعهدنامه سفر -->
        <div class="rogh-card rounded-3xl p-6 sm:p-8 space-y-4 border border-amber-500/30">
            <div class="flex items-center gap-2 pb-3 border-b border-amber-500/20">
                <span class="text-amber-400 text-sm">✦</span>
                <h2 class="font-black text-sm text-white">۵. متن شرایط و تعهدنامه ثبت‌نام زائر</h2>
            </div>

            <div>
                <textarea name="rules_and_conditions" rows="4" 
                          class="w-full bg-[#031712] border border-amber-500/30 rounded-xl p-3 text-slate-200 leading-relaxed"><?= safe($settings['rules_and_conditions'] ?? '') ?></textarea>
            </div>
        </div>

        <div class="pt-2">
            <button type="submit" class="w-full bg-gradient-to-r from-amber-500 via-amber-400 to-amber-500 hover:from-amber-400 text-slate-950 font-black py-4 rounded-2xl text-xs sm:text-sm transition duration-200 shadow-xl shadow-amber-500/20 flex items-center justify-center gap-2">
                <span>💾</span> ذخیره‌سازی کلیه تنظیمات و سوالات متداول
            </button>
        </div>

    </form>

</div>

<script>
    let faqIndex = 0;

    function addFaqRow(question = '', answer = '') {
        const container = document.getElementById('faqs_container');
        const rowId = 'faq_row_' + faqIndex++;

        const row = document.createElement('div');
        row.id = rowId;
        row.className = 'p-4 rounded-2xl bg-[#031712] border border-amber-500/20 space-y-3 transition';

        row.innerHTML = `
            <div class="flex justify-between items-center pb-2 border-b border-white/5">
                <span class="text-[11px] font-bold text-amber-300">پرسش و پاسخ</span>
                <button type="button" onclick="removeFaqRow('${rowId}')" 
                        class="text-rose-400 hover:text-rose-300 text-xs font-bold flex items-center gap-1">
                    <span>🗑️</span> حذف این سوال
                </button>
            </div>

            <div>
                <label class="block text-[11px] text-slate-300 mb-1 font-bold">متن سوال:</label>
                <input type="text" name="faq_question[]" value="${question}" required placeholder="مثال: شرایط دریافت ارز مسافرتی چیست؟" 
                       class="w-full bg-[#051c16] border border-amber-500/30 rounded-xl p-2.5 text-white">
            </div>

            <div>
                <label class="block text-[11px] text-slate-300 mb-1 font-bold">پاسخ تشریحی:</label>
                <textarea name="faq_answer[]" rows="2" required placeholder="پاسخ کامل به این سوال را بنویسید..." 
                          class="w-full bg-[#051c16] border border-amber-500/30 rounded-xl p-2.5 text-slate-200 leading-relaxed">${answer}</textarea>
            </div>
        `;

        container.appendChild(row);
    }

    function removeFaqRow(rowId) {
        const el = document.getElementById(rowId);
        if (el) el.remove();
    }

    document.addEventListener('DOMContentLoaded', function() {
        <?php if (!empty($current_faqs)): ?>
            <?php foreach ($current_faqs as$fq): ?>
                addFaqRow(
                    <?= json_encode($fq['question'] ?? '', JSON_UNESCAPED_UNICODE) ?>,
                    <?= json_encode($fq['answer'] ?? '', JSON_UNESCAPED_UNICODE) ?>
                );
            <?php endforeach; ?>
        <?php else: ?>
            addFaqRow('گذرنامه باید چه میزان اعتبار داشته باشد؟', 'برای سفرهای عتبات عالیات (عراق)، گذرنامه از تاریخ روز اعزام باید حداقل ۶ ماه کامل اعتبار داشته باشد.');
            addFaqRow('شرایط پرداخت بیعانه و تسویه‌حساب به چه صورت است؟', 'جهت تثبیت صندلی، واریز مبلغ بیعانه اعلام‌شده در فرم الزامی است. مابقی هزینه سفر حداکثر تا ۷۲ ساعت قبل از اعزام باید تسویه گردد.');
            addFaqRow('آیا ارز مسافرتی به زائران این کاروان تعلق می‌گیرد؟', 'بله، پس از ثبت‌نام نهایی و صدور مانیفست رسمی، اطلاعات زائران ثبت شده و امکان دریافت ارز مسافرتی بانکی فراهم می‌گردد.');
        <?php endif; ?>
    });
</script>

<?php require_once __DIR__ . '/footer.php'; ?>