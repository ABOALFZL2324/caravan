<?php
require_once __DIR__ . '/header.php';
require_roles(['super_admin', 'registrar', 'finance', 'leader']);

$msg = '';
$err = '';
$current_role = $_SESSION['admin_role'] ?? '';

// ۱. پردازش تغییر وضعیت پرونده (تأیید، رد، لغو)
if (isset($_POST['change_status_action'])) {
    $p_id = (int)($_POST['pilgrim_id'] ?? 0);
    $new_status = safe($_POST['new_status'] ?? '');
    $reject_reason = safe($_POST['reject_reason'] ?? '');

    if ($p_id > 0 && in_array($new_status, ['pending', 'verified', 'rejected', 'cancelled'])) {
        // دریافت اطلاعات فعلی جهت مدیریت ظرفیت کاروان
        $stmt_cur = $conn->prepare("SELECT caravan_id, status FROM pilgrims WHERE id = ?");
        $stmt_cur->execute([$p_id]);
        $curr = $stmt_cur->fetch();

        if ($curr) {
            $old_status = $curr['status'];
            $c_id = (int)$curr['caravan_id'];

            $stmt_up = $conn->prepare("UPDATE pilgrims SET status = ?, rejection_reason = ? WHERE id = ?");
            $stmt_up->execute([$new_status, ($new_status === 'rejected' ? $reject_reason : null), $p_id]);

            // اگر وضعیت به لغو تغییر کرد، ۱ صندلی به کاروان بازگردد
            if ($old_status !== 'cancelled' && $new_status === 'cancelled') {
                $conn->prepare("UPDATE caravans SET remaining_capacity = remaining_capacity + 1 WHERE id = ?")->execute([$c_id]);
            }
            // اگر قبلاً لغو شده بود و مجدداً فعال شد، ۱ صندلی کسر گردد
            elseif ($old_status === 'cancelled' && $new_status !== 'cancelled') {
                $conn->prepare("UPDATE caravans SET remaining_capacity = GREATEST(0, remaining_capacity - 1) WHERE id = ?")->execute([$c_id]);
            }

            $msg = 'وضعیت پرونده زائر با موفقیت به‌روزرسانی شد.';
        }
    }
}

// ۲. پردازش ثبت یا ویرایش وضعیت مالی و پرداختی زائر
if (isset($_POST['update_payment_action'])) {
    $p_id = (int)($_POST['pilgrim_id'] ?? 0);
    $paid_amount = (float)str_replace(',', '', $_POST['paid_amount'] ?? 0);
    $payment_ref = safe($_POST['payment_ref'] ?? '');

    if ($p_id > 0) {
        $stmt_pay = $conn->prepare("UPDATE pilgrims SET paid_amount = ?, payment_ref = ? WHERE id = ?");
        $stmt_pay->execute([$paid_amount, $payment_ref, $p_id]);
        $msg = 'اطلاعات مالی و پرداختی زائر با موفقیت ثبت شد.';
    }
}

// ۳. حذف پرونده زائر (فقط مدیر ارشد)
if (isset($_GET['action']) && $_GET['action'] === 'delete' && has_role(['super_admin'])) {
    $del_id = (int)($_GET['id'] ?? 0);
    if ($del_id > 0) {
        $stmt_f = $conn->prepare("SELECT caravan_id, status, national_card_image, passport_image, receipt_image FROM pilgrims WHERE id = ?");
        $stmt_f->execute([$del_id]);
        $p_data = $stmt_f->fetch();

        if ($p_data) {
            // حذف فایل‌ها از هاست
            $docs_dir = __DIR__ . '/../uploads/documents/';
            foreach (['national_card_image', 'passport_image', 'receipt_image'] as $img_col) {
                if (!empty($p_data[$img_col]) && file_exists($docs_dir . $p_data[$img_col])) {
                    @unlink($docs_dir . $p_data[$img_col]);
                }
            }

            // بازگشت صندلی در صورت فعال بودن
            if ($p_data['status'] !== 'cancelled') {
                $conn->prepare("UPDATE caravans SET remaining_capacity = remaining_capacity + 1 WHERE id = ?")->execute([$p_data['caravan_id']]);
            }

            $conn->prepare("DELETE FROM pilgrims WHERE id = ?")->execute([$del_id]);
            $msg = 'پرونده زائر و تمامی مدارک متصل به آن حذف گردید.';
        }
    }
}

// فیلترها و جستجو
$filter_caravan = (int)($_GET['caravan_id'] ?? 0);
$filter_status = safe($_GET['status'] ?? 'all');
$filter_search = trim($_GET['search'] ?? '');

$conditions = [];
$params = [];

if ($filter_caravan > 0) {
    $conditions[] = "pilgrims.caravan_id = ?";
    $params[] = $filter_caravan;
}

if ($filter_status !== 'all') {
    $conditions[] = "pilgrims.status = ?";
    $params[] = $filter_status;
}

if (!empty($filter_search)) {
    $conditions[] = "(pilgrims.fullname LIKE ? OR pilgrims.national_code LIKE ? OR pilgrims.phone LIKE ?)";
    $like_val = "%{$filter_search}%";
    $params[] = $like_val;
    $params[] = $like_val;
    $params[] = $like_val;
}

$where_sql = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";

$sql = "SELECT pilgrims.*, caravans.title AS caravan_title, caravans.start_date, caravans.deposit_amount 
        FROM pilgrims 
        JOIN caravans ON pilgrims.caravan_id = caravans.id 
        $where_sql 
        ORDER BY pilgrims.id DESC";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$pilgrims = $stmt->fetchAll();

// لیست تمام کاروان‌ها برای منوی کشویی
$all_caravans = $conn->query("SELECT id, title, start_date FROM caravans ORDER BY id DESC")->fetchAll();
?>

<div class="space-y-6">

    <!-- نوار عنوان و خروجی سریع -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center pb-4 border-b border-amber-500/20 gap-3">
        <div>
            <h1 class="text-xl sm:text-2xl font-black text-white flex items-center gap-2">
                <span>👥</span> مدیریت پرونده‌ها و ثبت‌نام زائران
            </h1>
            <p class="text-xs text-amber-200/80 mt-1">بررسی و تأیید مدارک هویتی، کنترل فیش‌های بانکی، ثبت تسویه‌حساب و مدیریت مسافران</p>
        </div>

        <a href="export_center.php<?= $filter_caravan > 0 ? '?caravan_id='.$filter_caravan : '' ?>" 
           class="bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-400 text-slate-950 font-black text-xs px-4 py-2.5 rounded-xl transition shadow-md shadow-amber-600/20 flex items-center gap-1.5">
            <span>📄</span> دریافت مانیفست و خروجی اکسل
        </a>
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

    <!-- نوار فیلتر و جستجو -->
    <div class="rogh-card rounded-2xl p-4 sm:p-5 border border-amber-500/30">
        <form method="GET" action="pilgrims.php" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-3 text-xs">
            
            <div>
                <label class="block font-bold text-slate-300 mb-1">فیلتر بر اساس کاروان:</label>
                <select name="caravan_id" class="w-full bg-[#031712] border border-amber-500/30 rounded-xl p-2.5 text-white">
                    <option value="0">تمامی کاروان‌ها</option>
                    <?php foreach ($all_caravans as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $filter_caravan === (int)$c['id'] ? 'selected' : '' ?>>
                            <?= safe($c['title']) ?> (اعزام: <?= safe($c['start_date']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block font-bold text-slate-300 mb-1">وضعیت پرونده:</label>
                <select name="status" class="w-full bg-[#031712] border border-amber-500/30 rounded-xl p-2.5 text-white">
                    <option value="all" <?= $filter_status === 'all' ? 'selected' : '' ?>>همه وضعیت‌ها</option>
                    <option value="pending" <?= $filter_status === 'pending' ? 'selected' : '' ?>>⏳ در انتظار بررسی</option>
                    <option value="verified" <?= $filter_status === 'verified' ? 'selected' : '' ?>>✅ تأیید نهایی</option>
                    <option value="rejected" <?= $filter_status === 'rejected' ? 'selected' : '' ?>>❌ مدارک ردشده</option>
                    <option value="cancelled" <?= $filter_status === 'cancelled' ? 'selected' : '' ?>>🚫 لغوشده / انصرافی</option>
                </select>
            </div>

            <div>
                <label class="block font-bold text-slate-300 mb-1">جستجوی نام، کدملی یا تلفن:</label>
                <input type="text" name="search" value="<?= safe($filter_search) ?>" placeholder="نام یا کدملی را بنویسید..." 
                       class="w-full bg-[#031712] border border-amber-500/30 rounded-xl p-2.5 text-white placeholder:text-slate-500">
            </div>

            <div class="flex items-end gap-2">
                <button type="submit" class="w-full bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-400 text-slate-950 font-black py-2.5 rounded-xl transition">
                    اعمال فیلتر
                </button>
                <a href="pilgrims.php" class="bg-[#031712] hover:bg-white/10 text-slate-300 border border-amber-500/20 font-bold py-2.5 px-3 rounded-xl transition">
                    بازنشانی
                </a>
            </div>
        </form>
    </div>

    <!-- جدول پرونده‌ها -->
    <div class="rogh-card rounded-2xl overflow-hidden border border-amber-500/30">
        <div class="p-4 border-b border-amber-500/20 flex justify-between items-center bg-[#031511]">
            <h2 class="font-black text-xs sm:text-sm text-white flex items-center gap-2">
                <span>✦</span> لیست پرونده‌های ثبت‌نامی زائران
            </h2>
            <span class="text-xs text-amber-200/80 font-mono">تعداد: <?= count($pilgrims) ?> پرونده</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-right border-collapse text-xs">
                <thead>
                    <tr class="bg-[#02100d] text-amber-200/80 border-b border-amber-500/10 whitespace-nowrap">
                        <th class="p-3.5" style="width: 40px;">ردیف</th>
                        <th class="p-3.5">مشخصات زائر</th>
                        <th class="p-3.5">کاروان زیارتی</th>
                        <th class="p-3.5 text-center">مدارک هویتی</th>
                        <th class="p-3.5">وضعیت مالی و فیش</th>
                        <th class="p-3.5 text-center">وضعیت پرونده</th>
                        <th class="p-3.5 text-center">عملیات</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-amber-500/10">
                    <?php if (empty($pilgrims)): ?>
                        <tr><td colspan="7" class="text-center py-10 text-slate-500">هیچ پرونده‌ای با این مشخصات یافت نشد.</td></tr>
                    <?php else: ?>
                        <?php foreach ($pilgrims as $idx => $p): ?>
                            <?php 
                                $total_fee = (float)$p['final_price'];
                                $paid = (float)($p['paid_amount'] ?? 0);
                                $balance = $total_fee - $paid;

                                $nid_img = !empty($p['national_card_image']) ? $p['national_card_image'] : ($p['document_image'] ?? '');
                                $pass_img = $p['passport_image'] ?? '';
                                $receipt_img = $p['receipt_image'] ?? '';
                            ?>
                            <tr class="hover:bg-white/5 transition">
                                <td class="p-3.5 text-slate-400 font-mono text-center"><?= $idx + 1 ?></td>

                                <!-- مشخصات فردی -->
                                <td class="p-3.5">
                                    <div class="font-bold text-white text-sm"><?= safe($p['fullname']) ?></div>
                                    <div class="text-[11px] text-slate-400 font-mono mt-0.5">
                                        کدملی: <span class="text-amber-300"><?= safe($p['national_code']) ?></span> | همراه: <?= safe($p['phone']) ?>
                                    </div>
                                    <?php if (!empty($p['medical_notes'])): ?>
                                        <div class="text-[10px] text-rose-300 mt-1 bg-rose-950/40 border border-rose-500/20 px-2 py-0.5 rounded w-fit">
                                            🩺 ملاحظات: <?= safe($p['medical_notes']) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>

                                <!-- کاروان -->
                                <td class="p-3.5">
                                    <div class="font-bold text-slate-200"><?= safe($p['caravan_title']) ?></div>
                                    <span class="text-[10px] text-slate-400 font-mono">اعزام: <?= safe($p['start_date']) ?></span>
                                </td>

                                <!-- مدارک هویتی (کارت ملی و پاسپورت) -->
                                <td class="p-3.5 text-center whitespace-nowrap">
                                    <div class="inline-flex items-center gap-1.5">
                                        <?php if (!empty($nid_img)): ?>
                                            <button onclick="previewFile('../uploads/documents/<?= safe($nid_img) ?>', 'کارت ملی - <?= safe($p['fullname']) ?>')" 
                                                    class="bg-amber-500/20 text-amber-300 border border-amber-500/40 px-2 py-1 rounded-lg text-[10px] font-bold hover:bg-amber-500/30 transition">
                                                🆔 کارت ملی
                                            </button>
                                        <?php else: ?>
                                            <span class="text-slate-600 text-[10px]">بدون کارت</span>
                                        <?php endif; ?>

                                        <?php if (!empty($pass_img)): ?>
                                            <button onclick="previewFile('../uploads/documents/<?= safe($pass_img) ?>', 'گذرنامه - <?= safe($p['fullname']) ?>')" 
                                                    class="bg-teal-500/20 text-teal-300 border border-teal-500/40 px-2 py-1 rounded-lg text-[10px] font-bold hover:bg-teal-500/30 transition">
                                                📘 پاسپورت
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>

                                <!-- وضعیت مالی، پرداختی و فیش -->
                                <td class="p-3.5 font-mono text-xs">
                                    <div class="flex items-center justify-between gap-2">
                                        <span class="text-slate-400 text-[10px]">واریزشده:</span>
                                        <span class="font-bold <?= $paid > 0 ? 'text-emerald-400' : 'text-slate-500' ?>"><?= number_format($paid) ?> ت</span>
                                    </div>

                                    <div class="flex items-center justify-between gap-2 mt-0.5">
                                        <span class="text-slate-400 text-[10px]">مانده‌حساب:</span>
                                        <span class="font-bold <?= $balance <= 0 ? 'text-emerald-400' : 'text-amber-300' ?>">
                                            <?= $balance <= 0 ? 'تسویه کامل' : number_format($balance) . ' ت' ?>
                                        </span>
                                    </div>

                                    <div class="flex items-center gap-1.5 mt-1.5">
                                        <?php if (!empty($receipt_img)): ?>
                                            <button onclick="previewFile('../uploads/documents/<?= safe($receipt_img) ?>', 'فیش واریزی - <?= safe($p['fullname']) ?>')" 
                                                    class="bg-emerald-900/40 hover:bg-emerald-800/60 text-emerald-300 border border-emerald-500/40 px-2 py-0.5 rounded text-[10px] font-bold transition flex items-center gap-1">
                                                <span>🧾</span> مشاهده فیش
                                            </button>
                                        <?php endif; ?>

                                        <button onclick="openPaymentModal(<?= $p['id'] ?>, '<?= safe($p['fullname']) ?>', <?= $paid ?>, '<?= safe($p['payment_ref'] ?? '') ?>')" 
                                                class="text-[10px] text-slate-300 hover:text-amber-300 underline font-sans">
                                            ویرایش مالی
                                        </button>
                                    </div>
                                </td>

                                <!-- وضعیت پرونده و دکمه باز کردن تغییر وضعیت -->
                                <td class="p-3.5 text-center whitespace-nowrap">
                                    <?php 
                                        $badge_cls = '';
                                        $badge_txt = '';
                                        switch ($p['status']) {
                                            case 'verified':
                                                $badge_cls = 'bg-emerald-500/20 text-emerald-300 border-emerald-500/40';
                                                $badge_txt = '✅ تایید قطعی';
                                                break;
                                            case 'pending':
                                                $badge_cls = 'bg-amber-500/20 text-amber-300 border-amber-500/40';
                                                $badge_txt = '⏳ در انتظار بررسی';
                                                break;
                                            case 'rejected':
                                                $badge_cls = 'bg-rose-500/20 text-rose-300 border-rose-500/40';
                                                $badge_txt = '❌ رد مدارک';
                                                break;
                                            default:
                                                $badge_cls = 'bg-slate-700 text-slate-300 border-slate-600';
                                                $badge_txt = '🚫 لغوشده';
                                        }
                                    ?>
                                    <button onclick="openStatusModal(<?= $p['id'] ?>, '<?= safe($p['fullname']) ?>', '<?= $p['status'] ?>', '<?= safe($p['rejection_reason'] ?? '') ?>')" 
                                            class="px-2.5 py-1 rounded-xl border text-[10px] font-bold <?= $badge_cls ?> hover:opacity-80 transition cursor-pointer" title="کلیک جهت تغییر وضعیت">
                                        <?= $badge_txt ?>
                                    </button>
                                    <?php if (!empty($p['rejection_reason']) && $p['status'] === 'rejected'): ?>
                                        <div class="text-[9px] text-rose-400 mt-1 max-w-[130px] truncate" title="<?= safe($p['rejection_reason']) ?>">
                                            دلیل: <?= safe($p['rejection_reason']) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>

                                <!-- عملیات -->
                                <td class="p-3.5 text-center whitespace-nowrap">
                                    <div class="inline-flex items-center gap-2">
                                        <button onclick="openStatusModal(<?= $p['id'] ?>, '<?= safe($p['fullname']) ?>', '<?= $p['status'] ?>', '<?= safe($p['rejection_reason'] ?? '') ?>')" 
                                                class="text-amber-300 hover:text-amber-200 font-bold hover:underline text-xs">
                                            بررسی
                                        </button>
                                        <?php if (has_role(['super_admin'])): ?>
                                            <span class="text-slate-600">|</span>
                                            <a href="pilgrims.php?action=delete&id=<?= $p['id'] ?>" 
                                               onclick="return confirm('آیا از حذف کامل پرونده و کلیه فایل‌های این زائر اطمینان دارید؟');" 
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

<!-- مودال ۱: تغییر وضعیت پرونده (تأیید / رد با دلیل / لغو) -->
<div id="status_modal" class="fixed inset-0 bg-black/80 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
    <div class="rogh-card rounded-3xl max-w-md w-full p-6 border border-amber-500/40 shadow-2xl relative">
        <div class="flex justify-between items-center pb-3 border-b border-amber-500/20">
            <h3 class="font-black text-sm text-white flex items-center gap-1.5">
                <span>⚖️</span> بررسی پرونده: <span id="modal_pilgrim_name" class="text-amber-300"></span>
            </h3>
            <button onclick="closeStatusModal()" class="text-slate-400 hover:text-rose-400 text-lg font-bold">&times;</button>
        </div>

        <form action="pilgrims.php" method="POST" class="mt-4 space-y-4">
            <input type="hidden" name="change_status_action" value="1">
            <input type="hidden" id="modal_pilgrim_id" name="pilgrim_id" value="">

            <div>
                <label class="block text-xs font-bold text-slate-200 mb-1.5">تعیین وضعیت جدید:</label>
                <select id="modal_status_select" name="new_status" onchange="toggleRejectReason(this.value)" class="w-full bg-[#031712] border border-amber-500/30 rounded-xl p-2.5 text-xs text-white">
                    <option value="verified">✅ تأیید قطعی مدارک و واریزی (مسافر نهایی)</option>
                    <option value="pending">⏳ در انتظار بررسی مجدد</option>
                    <option value="rejected">❌ رد پرونده (به دلیل نقص مدارک یا واریزی)</option>
                    <option value="cancelled">🚫 لغو ثبت‌نام و انصراف مسافر (بازگشت صندلی)</option>
                </select>
            </div>

            <div id="reject_reason_box" class="hidden">
                <label class="block text-xs font-bold text-rose-300 mb-1">علت رد مدارک (به زائر پیامک یا نمایش داده می‌شود):</label>
                <textarea id="modal_reject_reason" name="reject_reason" rows="2" placeholder="مثال: تصویر گذرنامه ناخوانا است یا فیش واریزی ناخواناست." 
                          class="w-full bg-[#031712] border border-rose-500/30 rounded-xl p-2.5 text-xs text-white"></textarea>
            </div>

            <div class="flex items-center gap-2 pt-2 border-t border-amber-500/20">
                <button type="submit" class="flex-1 bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-400 text-slate-950 font-black py-2.5 rounded-xl text-xs transition">
                    ثبت وضعیت
                </button>
                <button type="button" onclick="closeStatusModal()" class="bg-[#031712] text-slate-300 px-4 py-2.5 rounded-xl text-xs font-bold">
                    انصراف
                </button>
            </div>
        </form>
    </div>
</div>

<!-- مودال ۲: ثبت و ویرایش اطلاعات مالی و واریزی -->
<div id="payment_modal" class="fixed inset-0 bg-black/80 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
    <div class="rogh-card rounded-3xl max-w-md w-full p-6 border border-emerald-500/40 shadow-2xl relative">
        <div class="flex justify-between items-center pb-3 border-b border-emerald-500/20">
            <h3 class="font-black text-sm text-white flex items-center gap-1.5">
                <span>💳</span> امور مالی و پرداختی: <span id="pay_modal_pilgrim_name" class="text-emerald-300"></span>
            </h3>
            <button onclick="closePaymentModal()" class="text-slate-400 hover:text-rose-400 text-lg font-bold">&times;</button>
        </div>

        <form action="pilgrims.php" method="POST" class="mt-4 space-y-4">
            <input type="hidden" name="update_payment_action" value="1">
            <input type="hidden" id="pay_modal_pilgrim_id" name="pilgrim_id" value="">

            <div>
                <label class="block text-xs font-bold text-slate-200 mb-1">مبلغ واریزشده (تومان):</label>
                <input type="number" id="pay_modal_amount" name="paid_amount" required 
                       class="w-full bg-[#031712] border border-amber-500/30 rounded-xl p-2.5 text-xs text-amber-300 font-mono">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-200 mb-1">شماره پیگیری / ارجاع فیش:</label>
                <input type="text" id="pay_modal_ref" name="payment_ref" placeholder="کد رهگیری فیش بانکی" 
                       class="w-full bg-[#031712] border border-amber-500/30 rounded-xl p-2.5 text-xs text-white font-mono">
            </div>

            <div class="flex items-center gap-2 pt-2 border-t border-amber-500/20">
                <button type="submit" class="flex-1 bg-gradient-to-r from-emerald-500 to-emerald-600 hover:from-emerald-400 text-slate-950 font-black py-2.5 rounded-xl text-xs transition">
                    ذخیره وضعیت مالی
                </button>
                <button type="button" onclick="closePaymentModal()" class="bg-[#031712] text-slate-300 px-4 py-2.5 rounded-xl text-xs font-bold">
                    انصراف
                </button>
            </div>
        </form>
    </div>
</div>

<!-- مودال ۳: پیش‌نمایش مدارک و فیش‌ها -->
<div id="file_preview_modal" class="fixed inset-0 bg-black/85 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
    <div class="rogh-card rounded-3xl max-w-2xl w-full p-5 border border-amber-500/40 shadow-2xl relative">
        <div class="flex justify-between items-center pb-3 border-b border-amber-500/20">
            <h3 id="preview_title" class="font-black text-sm text-white">پیش‌نمایش تصویر</h3>
            <button onclick="document.getElementById('file_preview_modal').classList.add('hidden')" class="text-slate-400 hover:text-rose-400 text-lg font-bold">&times;</button>
        </div>
        <div class="mt-4 text-center">
            <img id="preview_img" src="" alt="مدرک" class="max-h-[72vh] mx-auto rounded-xl border border-amber-500/20 object-contain">
        </div>
    </div>
</div>

<script>
    function previewFile(src, title) {
        document.getElementById('preview_title').textContent = title;
        document.getElementById('preview_img').src = src;
        document.getElementById('file_preview_modal').classList.remove('hidden');
    }

    function openStatusModal(id, name, status, reason) {
        document.getElementById('modal_pilgrim_id').value = id;
        document.getElementById('modal_pilgrim_name').textContent = name;
        document.getElementById('modal_status_select').value = status;
        document.getElementById('modal_reject_reason').value = reason || '';
        toggleRejectReason(status);
        document.getElementById('status_modal').classList.remove('hidden');
    }

    function closeStatusModal() {
        document.getElementById('status_modal').classList.add('hidden');
    }

    function toggleRejectReason(status) {
        const box = document.getElementById('reject_reason_box');
        if (status === 'rejected') {
            box.classList.remove('hidden');
        } else {
            box.classList.add('hidden');
        }
    }

    function openPaymentModal(id, name, paid, ref) {
        document.getElementById('pay_modal_pilgrim_id').value = id;
        document.getElementById('pay_modal_pilgrim_name').textContent = name;
        document.getElementById('pay_modal_amount').value = paid;
        document.getElementById('pay_modal_ref').value = ref || '';
        document.getElementById('payment_modal').classList.remove('hidden');
    }

    function closePaymentModal() {
        document.getElementById('payment_modal').classList.add('hidden');
    }
</script>

<?php require_once __DIR__ . '/footer.php'; ?>