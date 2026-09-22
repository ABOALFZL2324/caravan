<?php
ob_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../sms_helper.php';

require_roles(['super_admin', 'registrar', 'finance', 'leader']);

$msg = '';
$err = '';
$settings = get_settings($conn);

// ۱. پردازش ثبت‌نام دستی زائر (حضوری - شامل تاریخ تولد، رده سنی و گذرنامه)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['manual_register_pilgrim'])) {
    $caravan_id = (int)($_POST['caravan_id'] ?? 0);
    $fullname = safe($_POST['fullname'] ?? '');
    $national_code = safe($_POST['national_code'] ?? '');
    $phone = safe($_POST['phone'] ?? '');
    $emergency_phone = safe($_POST['emergency_phone'] ?? '');
    $gender = in_array($_POST['gender'] ?? '', ['male', 'female']) ? $_POST['gender'] : 'male';
    $age_group = in_array($_POST['age_group'] ?? '', ['adult', 'child', 'infant']) ? $_POST['age_group'] : 'adult';
    $birth_date = safe($_POST['birth_date'] ?? '');
    $medical_notes = safe($_POST['medical_notes'] ?? '');
    $passport_number = safe($_POST['passport_number'] ?? '');
    $passport_expiry = safe($_POST['passport_expiry'] ?? '');
    $paid_amount = (float)str_replace(',', '', $_POST['paid_amount'] ?? 0);
    $payment_ref = safe($_POST['payment_ref'] ?? '');
    $initial_status = safe($_POST['initial_status'] ?? 'verified');

    if ($caravan_id <= 0 || empty($fullname) || empty($national_code) || empty($phone)) {
        $err = 'لطفاً کاروان مقصد، نام، کد ملی و شماره همراه مسافر را وارد کنید.';
    } elseif (strlen($national_code) !== 10 || !ctype_digit($national_code)) {
        $err = 'کد ملی باید دقیقاً ۱۰ رقم باشد.';
    } else {
        $stmt_c = $conn->prepare("SELECT * FROM caravans WHERE id = ? LIMIT 1");
        $stmt_c->execute([$caravan_id]);
        $caravan = $stmt_c->fetch();

        if (!$caravan) {
            $err = 'کاروان انتخابی معتبر نیست.';
        } else {
            $final_price = (float)$caravan['price_adult'];
            if ($age_group === 'child') $final_price = (float)($caravan['price_child'] ?: $caravan['price_adult']);
            elseif ($age_group === 'infant') $final_price = (float)($caravan['price_infant'] ?: 0);

            $upload_dir = __DIR__ . '/../uploads/documents/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            $max_size = 5 * 1024 * 1024; // ۵ مگابایت

            $national_card_image = null;
            if (isset($_FILES['national_card_file']) && $_FILES['national_card_file']['error'] === UPLOAD_ERR_OK) {
                if ($_FILES['national_card_file']['size'] <= $max_size) {
                    $ext = strtolower(pathinfo($_FILES['national_card_file']['name'], PATHINFO_EXTENSION));
                    $national_card_image = 'nid_' . time() . '.' . $ext;
                    move_uploaded_file($_FILES['national_card_file']['tmp_name'], $upload_dir . $national_card_image);
                }
            }

            $passport_image = null;
            if (isset($_FILES['passport_file']) && $_FILES['passport_file']['error'] === UPLOAD_ERR_OK) {
                if ($_FILES['passport_file']['size'] <= $max_size) {
                    $ext = strtolower(pathinfo($_FILES['passport_file']['name'], PATHINFO_EXTENSION));
                    $passport_image = 'pass_' . time() . '.' . $ext;
                    move_uploaded_file($_FILES['passport_file']['tmp_name'], $upload_dir . $passport_image);
                }
            }

            $payment_receipt_image = null;
            if (isset($_FILES['receipt_file']) && $_FILES['receipt_file']['error'] === UPLOAD_ERR_OK) {
                if ($_FILES['receipt_file']['size'] <= $max_size) {
                    $ext = strtolower(pathinfo($_FILES['receipt_file']['name'], PATHINFO_EXTENSION));
                    $payment_receipt_image = 'receipt_' . time() . '.' . $ext;
                    move_uploaded_file($_FILES['receipt_file']['tmp_name'], $upload_dir . $payment_receipt_image);
                }
            }

            try {
                $conn->beginTransaction();

                $stmt_ins = $conn->prepare("INSERT INTO pilgrims 
                    (caravan_id, fullname, national_code, phone, emergency_phone, gender, age_group, birth_date, 
                     medical_notes, passport_number, passport_expiry, national_card_image, passport_image, payment_receipt_image, 
                     final_price, paid_amount, payment_ref, status, rules_accepted, registered_by) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 'admin')");
                
                $stmt_ins->execute([
                    $caravan_id, $fullname, $national_code, $phone, $emergency_phone, $gender, $age_group,
                    $birth_date ?: null, $medical_notes ?: null, $passport_number ?: null, $passport_expiry ?: null,
                    $national_card_image, $passport_image, $payment_receipt_image,
                    $final_price, $paid_amount, $payment_ref, $initial_status
                ]);

                $conn->prepare("UPDATE caravans SET remaining_capacity = GREATEST(0, remaining_capacity - 1) WHERE id = ?")->execute([$caravan_id]);
                $conn->commit();

                $track_url = "http://" . $_SERVER['HTTP_HOST'] . "/caravan/tracking.php?national_code=" . $national_code;
                send_sms_notification($conn, $phone, $fullname, "زائر گرامی {$fullname}\nپرونده ثبت‌نام شما در کاروان {$caravan['title']} ثبت شد.\n{$track_url}", 'register');

                $msg = 'مسافر جدید با موفقیت ثبت‌نام شد.';
            } catch (Exception $e) {
                if ($conn->inTransaction()) $conn->rollBack();
                $err = 'خطا در ثبت پایگاه داده: ' . $e->getMessage();
            }
        }
    }
}

// ۲. پردازش ویرایش اطلاعات زائر (شامل تاریخ تولد و رده سنی)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_pilgrim_info'])) {
    $p_id = (int)($_POST['pilgrim_id'] ?? 0);
    $fullname = safe($_POST['fullname'] ?? '');
    $national_code = safe($_POST['national_code'] ?? '');
    $phone = safe($_POST['phone'] ?? '');
    $birth_date = safe($_POST['birth_date'] ?? '');
    $age_group = in_array($_POST['age_group'] ?? '', ['adult', 'child', 'infant']) ? $_POST['age_group'] : 'adult';
    $passport_number = safe($_POST['passport_number'] ?? '');
    $passport_expiry = safe($_POST['passport_expiry'] ?? '');
    $paid_amount = (float)str_replace(',', '', $_POST['paid_amount'] ?? 0);
    $payment_ref = safe($_POST['payment_ref'] ?? '');
    $medical_notes = safe($_POST['medical_notes'] ?? '');

    if ($p_id > 0 && !empty($fullname)) {
        $stmt_up_info = $conn->prepare("UPDATE pilgrims SET fullname = ?, national_code = ?, phone = ?, birth_date = ?, age_group = ?, passport_number = ?, passport_expiry = ?, paid_amount = ?, payment_ref = ?, medical_notes = ? WHERE id = ?");
        $stmt_up_info->execute([$fullname, $national_code, $phone, $birth_date, $age_group, $passport_number, $passport_expiry, $paid_amount, $payment_ref, $medical_notes, $p_id]);
        $msg = 'اطلاعات زائر با موفقیت ویرایش شد.';
    }
}

// ۳. پردازش تغییر وضعیت مستقیم (شامل انصراف و لغو)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['inline_change_status'])) {
    $p_id = (int)($_POST['pilgrim_id'] ?? 0);
    $new_status = safe($_POST['new_status'] ?? '');
    $reject_reason = trim($_POST['reject_reason'] ?? '');

    if ($p_id > 0 && in_array($new_status, ['pending', 'verified', 'rejected', 'cancelled'])) {
        $stmt_cur = $conn->prepare("SELECT pilgrims.*, caravans.title AS caravan_title FROM pilgrims JOIN caravans ON pilgrims.caravan_id = caravans.id WHERE pilgrims.id = ?");
        $stmt_cur->execute([$p_id]);
        $curr = $stmt_cur->fetch();

        if ($curr) {
            $old_status = $curr['status'];
            $c_id = (int)$curr['caravan_id'];

            $stmt_up = $conn->prepare("UPDATE pilgrims SET status = ?, rejection_reason = ? WHERE id = ?");
            $stmt_up->execute([$new_status, ($new_status === 'rejected' ? $reject_reason : ''), $p_id]);

            if ($old_status !== 'cancelled' && $new_status === 'cancelled') {
                $conn->prepare("UPDATE caravans SET remaining_capacity = remaining_capacity + 1 WHERE id = ?")->execute([$c_id]);
            } elseif ($old_status === 'cancelled' && $new_status !== 'cancelled') {
                $conn->prepare("UPDATE caravans SET remaining_capacity = GREATEST(0, remaining_capacity - 1) WHERE id = ?")->execute([$c_id]);
            }

            $p_phone = $curr['phone'];
            $p_name = $curr['fullname'];
            $c_title = $curr['caravan_title'];
            $track_url = "http://" . $_SERVER['HTTP_HOST'] . "/caravan/tracking.php?national_code=" . $curr['national_code'];

            if ($new_status === 'verified') {
                send_sms_notification($conn, $p_phone, $p_name, "زائر گرامی {$p_name}\nمدارک شما در کاروان {$c_title} تأیید شد.\n{$track_url}", 'verified');
            } elseif ($new_status === 'rejected') {
                send_sms_notification($conn, $p_phone, $p_name, "زائر گرامی {$p_name}\nپرونده شما نیاز به اصلاح دارد:\n{$reject_reason}\n{$track_url}", 'rejected');
            } elseif ($new_status === 'cancelled') {
                send_sms_notification($conn, $p_phone, $p_name, "زائر گرامی {$p_name}\nثبت‌نام شما در کاروان {$c_title} لغو/منصرف شد.", 'cancelled');
            }

            $msg = 'وضعیت پرونده زائر به‌روزرسانی شد.';
        }
    }
}

// ۴. حذف پرونده
if (isset($_GET['action']) && $_GET['action'] === 'delete' && has_role(['super_admin'])) {
    $del_id = (int)($_GET['id'] ?? 0);
    if ($del_id > 0) {
        $stmt_del = $conn->prepare("SELECT caravan_id, status FROM pilgrims WHERE id = ?");
        $stmt_del->execute([$del_id]);
        $p_info = $stmt_del->fetch();
        if ($p_info) {
            if ($p_info['status'] !== 'cancelled') {
                $conn->prepare("UPDATE caravans SET remaining_capacity = remaining_capacity + 1 WHERE id = ?")->execute([$p_info['caravan_id']]);
            }
            $conn->prepare("DELETE FROM pilgrims WHERE id = ?")->execute([$del_id]);
            $msg = 'پرونده زائر حذف گردید.';
        }
    }
}

// فیلترها
$filter_caravan = (int)($_GET['caravan_id'] ?? 0);
$filter_status = safe($_GET['status'] ?? 'all');
$filter_search = trim($_GET['search'] ?? '');

$conditions = [];
$params = [];
if ($filter_caravan > 0) { $conditions[] = "pilgrims.caravan_id = ?"; $params[] = $filter_caravan; }
if ($filter_status !== 'all') { $conditions[] = "pilgrims.status = ?"; $params[] = $filter_status; }
if (!empty($filter_search)) {
    $conditions[] = "(pilgrims.fullname LIKE ? OR pilgrims.national_code LIKE ? OR pilgrims.phone LIKE ?)";
    $like = "%{$filter_search}%";
    $params[] = $like; $params[] = $like; $params[] = $like;
}

$where = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";
$stmt = $conn->prepare("SELECT pilgrims.*, caravans.title AS caravan_title, caravans.start_date FROM pilgrims JOIN caravans ON pilgrims.caravan_id = caravans.id $where ORDER BY pilgrims.id DESC");
$stmt->execute($params);
$pilgrims = $stmt->fetchAll();
$all_caravans = $conn->query("SELECT id, title, remaining_capacity FROM caravans WHERE status = 'active' ORDER BY id DESC")->fetchAll();

require_once __DIR__ . '/header.php';
?>

<div class="space-y-6">

    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center pb-4 border-b border-amber-500/20 gap-3">
        <div>
            <h1 class="text-xl sm:text-2xl font-black text-white flex items-center gap-2">
                <span>👥</span> مدیریت ثبت‌نام و بررسی مدارک زائران
            </h1>
            <p class="text-xs text-amber-200/80 mt-1">مدیریت پرونده‌ها، تاریخ تولد، رده سنی و پذیرش حضوری زائران</p>
        </div>
        <div class="flex gap-2">
            <button onclick="document.getElementById('manual_reg_modal').classList.remove('hidden')" class="bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-400 text-slate-950 font-black text-xs px-4 py-2.5 rounded-xl transition shadow-md flex items-center gap-1.5">
                <span>➕</span> ثبت‌نام دستی مسافر (حضوری)
            </button>
            <a href="export_center.php" class="bg-[#031712] hover:bg-white/10 text-amber-300 border border-amber-500/30 font-bold text-xs px-4 py-2.5 rounded-xl transition">
                📄 خروجی مانیفست
            </a>
        </div>
    </div>

    <?php if ($msg): ?><div class="p-3.5 bg-emerald-950/60 border border-emerald-500/40 text-emerald-200 text-xs rounded-2xl font-bold">✅ <?= safe($msg) ?></div><?php endif; ?>
    <?php if ($err): ?><div class="p-3.5 bg-rose-950/60 border border-rose-500/40 text-rose-200 text-xs rounded-2xl font-bold">❌ <?= safe($err) ?></div><?php endif; ?>

    <!-- فیلترها -->
    <div class="rogh-card rounded-2xl p-4 sm:p-5 border border-amber-500/30">
        <form method="GET" class="grid grid-cols-1 sm:grid-cols-4 gap-3 text-xs">
            <select name="caravan_id" class="bg-[#031712] border border-amber-500/30 rounded-xl p-2.5 text-white">
                <option value="0">همه کاروان‌ها</option>
                <?php foreach ($all_caravans as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= $filter_caravan === (int)$c['id'] ? 'selected' : '' ?>><?= safe($c['title']) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="status" class="bg-[#031712] border border-amber-500/30 rounded-xl p-2.5 text-white">
                <option value="all">همه وضعیت‌ها</option>
                <option value="pending" <?= $filter_status==='pending'?'selected':'' ?>>در انتظار بررسی</option>
                <option value="verified" <?= $filter_status==='verified'?'selected':'' ?>>تأیید قطعی</option>
                <option value="rejected" <?= $filter_status==='rejected'?'selected':'' ?>>رد مدارک</option>
                <option value="cancelled" <?= $filter_status==='cancelled'?'selected':'' ?>>لغو / انصرافی</option>
            </select>
            <input type="text" name="search" value="<?= safe($filter_search) ?>" placeholder="جستجوی نام یا کدملی..." class="bg-[#031712] border border-amber-500/30 rounded-xl p-2.5 text-white">
            <button type="submit" class="bg-amber-500 text-slate-950 font-black py-2.5 rounded-xl transition">اعمال فیلتر</button>
        </form>
    </div>

    <!-- جدول زائران -->
    <div class="rogh-card rounded-2xl overflow-x-auto border border-amber-500/30 text-xs">
        <table class="w-full text-right border-collapse min-w-[750px]">
            <thead class="bg-[#02100d] text-amber-200 border-b border-amber-500/10">
                <tr>
                    <th class="p-3.5">ردیف</th>
                    <th class="p-3.5">مشخصات زائر و سن</th>
                    <th class="p-3.5">کاروان مقصد</th>
                    <th class="p-3.5 text-center">مشاهده مدارک</th>
                    <th class="p-3.5 text-center">تغییر وضعیت مستقیم</th>
                    <th class="p-3.5 text-center">عملیات</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-amber-500/10">
                <?php if (empty($pilgrims)): ?>
                    <tr><td colspan="6" class="text-center py-10 text-slate-500">هیچ پرونده‌ای یافت نشد.</td></tr>
                <?php else: ?>
                    <?php foreach ($pilgrims as $idx => $p): ?>
                    <tr class="hover:bg-white/5 transition align-top">
                        <td class="p-3.5 text-slate-400 font-mono"><?= $idx + 1 ?></td>
                        <td class="p-3.5">
                            <div class="font-bold text-white text-sm"><?= safe($p['fullname']) ?></div>
                            <div class="text-[11px] text-slate-400 font-mono">کدملی: <span class="text-amber-300"><?= safe($p['national_code']) ?></span> | همراه: <?= safe($p['phone']) ?></div>
                            <div class="text-[10px] text-amber-200/90 font-mono mt-0.5">
                                تولد: <?= safe($p['birth_date'] ?: '—') ?> | رده سنی: 
                                <span class="text-amber-400 font-bold"><?= $p['age_group'] === 'child' ? 'کودک' : ($p['age_group'] === 'infant' ? 'نوزاد' : 'بزرگسال') ?></span>
                            </div>
                            <?php if(!empty($p['passport_number'])): ?>
                                <div class="text-[10px] text-teal-300 font-mono mt-0.5">گذرنامه: <?= safe($p['passport_number']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="p-3.5"><?= safe($p['caravan_title']) ?></td>
                        <td class="p-3.5 text-center space-x-1 whitespace-nowrap">
                            <?php if(!empty($p['national_card_image'])): ?>
                                <button onclick="showImageModal('../uploads/documents/<?= safe($p['national_card_image']) ?>', 'کارت ملی - <?= safe($p['fullname']) ?>')" class="bg-amber-500/20 text-amber-300 px-2 py-1 rounded text-[10px] hover:bg-amber-500/30">کارت ملی</button>
                            <?php endif; ?>
                            <?php if(!empty($p['passport_image'])): ?>
                                <button onclick="showImageModal('../uploads/documents/<?= safe($p['passport_image']) ?>', 'گذرنامه - <?= safe($p['fullname']) ?>')" class="bg-teal-500/20 text-teal-300 px-2 py-1 rounded text-[10px] hover:bg-teal-500/30">گذرنامه</button>
                            <?php endif; ?>
                            <?php if(!empty($p['payment_receipt_image'])): ?>
                                <button onclick="showImageModal('../uploads/documents/<?= safe($p['payment_receipt_image']) ?>', 'فیش واریزی - <?= safe($p['fullname']) ?>')" class="bg-emerald-500/20 text-emerald-300 px-2 py-1 rounded text-[10px] hover:bg-emerald-500/30">فیش</button>
                            <?php endif; ?>
                        </td>
                        
                        <!-- تغییر وضعیت مستقیم درون‌خطی -->
                        <td class="p-3.5 text-center">
                            <form action="pilgrims.php" method="POST" class="inline-block space-y-1">
                                <input type="hidden" name="inline_change_status" value="1">
                                <input type="hidden" name="pilgrim_id" value="<?= $p['id'] ?>">
                                <select name="new_status" onchange="this.form.submit()" class="bg-[#031712] border <?= $p['status']==='verified'?'border-emerald-500 text-emerald-300':($p['status']==='rejected'?'border-rose-500 text-rose-300':($p['status']==='cancelled'?'border-purple-500 text-purple-300':'border-amber-500 text-amber-300')) ?> rounded-xl px-2 py-1 text-[11px] font-bold cursor-pointer">
                                    <option value="pending" <?= $p['status']==='pending'?'selected':'' ?>>⏳ در انتظار بررسی</option>
                                    <option value="verified" <?= $p['status']==='verified'?'selected':'' ?>>✅ تأیید قطعی</option>
                                    <option value="rejected" <?= $p['status']==='rejected'?'selected':'' ?>>❌ رد مدارک</option>
                                    <option value="cancelled" <?= $p['status']==='cancelled'?'selected':'' ?>>🚫 لغو / انصراف</option>
                                </select>
                            </form>
                            <?php if($p['status'] === 'rejected' && !empty($p['rejection_reason'])): ?>
                                <div class="text-[9px] text-rose-400 mt-1 max-w-[150px] truncate mx-auto" title="<?= safe($p['rejection_reason']) ?>">علت: <?= safe($p['rejection_reason']) ?></div>
                            <?php endif; ?>
                        </td>

                        <td class="p-3.5 text-center space-x-2 whitespace-nowrap">
                            <button onclick="openEditModal(<?= htmlspecialchars(json_encode($p), ENT_QUOTES, 'UTF-8') ?>)" class="text-amber-300 hover:underline font-bold">✏️ ویرایش</button>
                            <?php if(has_role(['super_admin'])): ?>
                                <a href="pilgrims.php?action=delete&id=<?= $p['id'] ?>" onclick="return confirm('آیا از حذف این پرونده اطمینان دارید؟');" class="text-rose-400 hover:underline font-bold">حذف</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- فرم ثبت‌نام دستی زائر (مودال) -->
<div id="manual_reg_modal" class="fixed inset-0 bg-black/85 backdrop-blur-sm hidden flex items-center justify-center p-4 z-50">
    <div class="rogh-card max-w-2xl w-full p-6 sm:p-8 rounded-3xl border border-amber-500/40 text-xs space-y-4 max-h-[90vh] overflow-y-auto">
        <div class="flex justify-between items-center pb-3 border-b border-amber-500/20">
            <h3 class="font-black text-white text-sm flex items-center gap-1.5"><span>➕</span> ثبت‌نام حضوری مسافر توسط مدیر</h3>
            <button onclick="document.getElementById('manual_reg_modal').classList.add('hidden')" class="text-slate-400 hover:text-rose-400 text-base font-bold">&times;</button>
        </div>

        <form action="pilgrims.php" method="POST" enctype="multipart/form-data" class="space-y-4">
            <input type="hidden" name="manual_register_pilgrim" value="1">

            <div>
                <label class="block font-bold text-slate-200 mb-1">انتخاب کاروان مقصد: *</label>
                <select name="caravan_id" required class="w-full bg-[#031712] border border-amber-500/30 rounded-xl p-2.5 text-white">
                    <option value="">انتخاب کنید...</option>
                    <?php foreach ($all_caravans as $c): ?>
                        <option value="<?= $c['id'] ?>"><?= safe($c['title']) ?> (ظرفیت: <?= $c['remaining_capacity'] ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div>
                    <label class="block font-bold text-slate-200 mb-1">نام و نام خانوادگی: *</label>
                    <input type="text" name="fullname" required class="w-full bg-[#031712] border border-amber-500/30 rounded-xl p-2.5 text-white">
                </div>
                <div>
                    <label class="block font-bold text-slate-200 mb-1">کد ملی (۱۰ رقم): *</label>
                    <input type="text" name="national_code" maxlength="10" required class="w-full bg-[#031712] border border-amber-500/30 rounded-xl p-2.5 text-amber-300 font-mono">
                </div>
                <div>
                    <label class="block font-bold text-slate-200 mb-1">شماره همراه: *</label>
                    <input type="tel" name="phone" required class="w-full bg-[#031712] border border-amber-500/30 rounded-xl p-2.5 text-white font-mono">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 p-3 bg-[#031712] rounded-2xl border border-amber-500/20">
                <div>
                    <label class="block font-bold text-slate-200 mb-1">شماره گذرنامه:</label>
                    <input type="text" name="passport_number" placeholder="مثال: A12345678" class="w-full bg-[#051c16] border border-amber-500/30 rounded-xl p-2 text-white font-mono">
                </div>
                <div>
                    <label class="block font-bold text-slate-200 mb-1">تاریخ انقضای گذرنامه:</label>
                    <input type="text" name="passport_expiry" placeholder="۱۴۰۵/۰۵/۲۰" class="w-full bg-[#051c16] border border-amber-500/30 rounded-xl p-2 text-amber-300 font-mono">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div>
                    <label class="block font-bold text-slate-200 mb-1">تاریخ تولد:</label>
                    <input type="text" name="birth_date" placeholder="۱۳۷۵/۰۲/۱۵" class="w-full bg-[#031712] border border-amber-500/30 rounded-xl p-2.5 text-amber-300 font-mono">
                </div>
                <div>
                    <label class="block font-bold text-slate-200 mb-1">رده سنی:</label>
                    <select name="age_group" class="w-full bg-[#031712] border border-amber-500/30 rounded-xl p-2.5 text-white">
                        <option value="adult">بزرگسال</option>
                        <option value="child">کودک</option>
                        <option value="infant">نوزاد</option>
                    </select>
                </div>
                <div>
                    <label class="block font-bold text-slate-200 mb-1">وضعیت اولیه پرونده:</label>
                    <select name="initial_status" class="w-full bg-[#031712] border border-amber-500/30 rounded-xl p-2.5 text-emerald-300 font-bold">
                        <option value="verified">✅ تأیید قطعی</option>
                        <option value="pending">⏳ در انتظار بررسی</option>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="block font-bold text-slate-200 mb-1">مبلغ واریزشده (تومان):</label>
                    <input type="number" name="paid_amount" placeholder="مثال: ۵۰۰۰۰۰۰" class="w-full bg-[#031712] border border-amber-500/30 rounded-xl p-2.5 text-amber-300 font-mono">
                </div>
                <div>
                    <label class="block font-bold text-slate-200 mb-1">شماره پیگیری فیش بانکی:</label>
                    <input type="text" name="payment_ref" placeholder="شماره ارجاع تراکنش" class="w-full bg-[#031712] border border-amber-500/30 rounded-xl p-2.5 text-white font-mono">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 p-3 bg-[#031712] rounded-2xl border border-amber-500/20">
                <div>
                    <label class="text-slate-300 block mb-1">آپلود کارت ملی:</label>
                    <input type="file" name="national_card_file" accept=".jpg,.jpeg,.png,.pdf" class="w-full text-slate-400 text-[11px]">
                </div>
                <div>
                    <label class="text-teal-300 block mb-1">آپلود گذرنامه:</label>
                    <input type="file" name="passport_file" accept=".jpg,.jpeg,.png,.pdf" class="w-full text-slate-400 text-[11px]">
                </div>
                <div>
                    <label class="text-emerald-300 block mb-1">آپلود فیش واریزی:</label>
                    <input type="file" name="receipt_file" accept=".jpg,.jpeg,.png,.pdf" class="w-full text-slate-400 text-[11px]">
                </div>
            </div>

            <div class="flex gap-2 pt-2">
                <button type="submit" class="flex-1 bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-400 text-slate-950 font-black py-3 rounded-xl transition">ثبت قطعی مسافر در کاروان &larr;</button>
                <button type="button" onclick="document.getElementById('manual_reg_modal').classList.add('hidden')" class="bg-slate-800 text-slate-300 px-5 py-3 rounded-xl">انصراف</button>
            </div>
        </form>
    </div>
</div>

<!-- مودال ویرایش اطلاعات زائر (شامل تاریخ تولد و رده سنی) -->
<div id="edit_pilgrim_modal" class="fixed inset-0 bg-black/85 backdrop-blur-sm hidden flex items-center justify-center p-4 z-50">
    <div class="rogh-card max-w-lg w-full p-6 rounded-3xl border border-amber-500/40 text-xs space-y-4">
        <div class="flex justify-between items-center pb-3 border-b border-amber-500/20">
            <h3 class="font-black text-white text-sm">ویرایش اطلاعات زائر: <span id="edit_p_fullname" class="text-amber-300"></span></h3>
            <button onclick="document.getElementById('edit_pilgrim_modal').classList.add('hidden')" class="text-slate-400 hover:text-rose-400 text-base font-bold">&times;</button>
        </div>

        <form action="pilgrims.php" method="POST" class="space-y-3">
            <input type="hidden" name="update_pilgrim_info" value="1">
            <input type="hidden" id="edit_p_id" name="pilgrim_id" value="">

            <div>
                <label class="block font-bold text-slate-200 mb-1">نام و نام خانوادگی:</label>
                <input type="text" id="edit_p_name" name="fullname" required class="w-full bg-[#031712] border border-amber-500/30 rounded-xl p-2.5 text-white">
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="block font-bold text-slate-200 mb-1">کد ملی:</label>
                    <input type="text" id="edit_p_national_code" name="national_code" maxlength="10" required class="w-full bg-[#031712] border border-amber-500/30 rounded-xl p-2.5 text-amber-300 font-mono">
                </div>
                <div>
                    <label class="block font-bold text-slate-200 mb-1">شماره همراه:</label>
                    <input type="tel" id="edit_p_phone" name="phone" required class="w-full bg-[#031712] border border-amber-500/30 rounded-xl p-2.5 text-white font-mono">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="block font-bold text-slate-200 mb-1">تاریخ تولد:</label>
                    <input type="text" id="edit_p_birth_date" name="birth_date" class="w-full bg-[#031712] border border-amber-500/30 rounded-xl p-2.5 text-amber-300 font-mono">
                </div>
                <div>
                    <label class="block font-bold text-slate-200 mb-1">رده سنی:</label>
                    <select id="edit_p_age_group" name="age_group" class="w-full bg-[#031712] border border-amber-500/30 rounded-xl p-2.5 text-white">
                        <option value="adult">بزرگسال</option>
                        <option value="child">کودک</option>
                        <option value="infant">نوزاد</option>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="block font-bold text-slate-200 mb-1">شماره گذرنامه:</label>
                    <input type="text" id="edit_p_passport_number" name="passport_number" class="w-full bg-[#031712] border border-amber-500/30 rounded-xl p-2.5 text-white font-mono">
                </div>
                <div>
                    <label class="block font-bold text-slate-200 mb-1">انقضای گذرنامه:</label>
                    <input type="text" id="edit_p_passport_expiry" name="passport_expiry" class="w-full bg-[#031712] border border-amber-500/30 rounded-xl p-2.5 text-amber-300 font-mono">
                </div>
            </div>

            <div>
                <label class="block font-bold text-slate-200 mb-1">ملاحظات پزشکی:</label>
                <input type="text" id="edit_p_medical_notes" name="medical_notes" class="w-full bg-[#031712] border border-amber-500/30 rounded-xl p-2.5 text-white">
            </div>

            <div class="flex gap-2 pt-2">
                <button type="submit" class="flex-1 bg-amber-500 text-slate-950 font-black py-2.5 rounded-xl transition">ذخیره تغییرات</button>
                <button type="button" onclick="document.getElementById('edit_pilgrim_modal').classList.add('hidden')" class="bg-slate-800 text-slate-300 px-4 py-2.5 rounded-xl">انصراف</button>
            </div>
        </form>
    </div>
</div>

<!-- مودال شناور پیش‌نمایش تصاویر -->
<div id="image_preview_modal" class="fixed inset-0 bg-black/85 backdrop-blur-sm hidden flex items-center justify-center p-4 z-50" onclick="this.classList.add('hidden')">
    <div class="rogh-card max-w-xl w-full p-5 rounded-3xl border border-amber-500/40 text-center space-y-3 relative" onclick="event.stopPropagation()">
        <div class="flex justify-between items-center pb-2 border-b border-amber-500/20">
            <h3 id="preview_img_title" class="font-black text-white text-xs">پیش‌نمایش مدرک</h3>
            <button onclick="document.getElementById('image_preview_modal').classList.add('hidden')" class="text-slate-400 hover:text-rose-400 text-base font-bold">&times;</button>
        </div>
        <div class="max-h-[70vh] overflow-auto flex items-center justify-center">
            <img id="preview_img_tag" src="" alt="مدرک" class="max-h-[65vh] rounded-xl object-contain border border-amber-500/30">
        </div>
    </div>
</div>

<script>
function showImageModal(src, title) {
    document.getElementById('preview_img_title').textContent = title;
    document.getElementById('preview_img_tag').src = src;
    document.getElementById('image_preview_modal').classList.remove('hidden');
}

function openEditModal(p) {
    document.getElementById('edit_p_id').value = p.id;
    document.getElementById('edit_p_fullname').textContent = p.fullname;
    document.getElementById('edit_p_name').value = p.fullname;
    document.getElementById('edit_p_national_code').value = p.national_code;
    document.getElementById('edit_p_phone').value = p.phone;
    document.getElementById('edit_p_birth_date').value = p.birth_date || '';
    document.getElementById('edit_p_age_group').value = p.age_group || 'adult';
    document.getElementById('edit_p_passport_number').value = p.passport_number || '';
    document.getElementById('edit_p_passport_expiry').value = p.passport_expiry || '';
    document.getElementById('edit_p_medical_notes').value = p.medical_notes || '';
    document.getElementById('edit_pilgrim_modal').classList.remove('hidden');
}
</script>

<?php require_once __DIR__ . '/footer.php'; ?>