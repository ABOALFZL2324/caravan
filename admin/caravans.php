<?php
require_once __DIR__ . '/header.php';

// دسترسی برای مدیر ارشد و مسئول پذیرش
require_roles(['super_admin', 'registrar']);

$msg = '';
$err = '';

// تغییر وضعیت پذیرش کاروان (فعال / متوقف)
if (isset($_GET['action']) && $_GET['action'] === 'toggle') {
    $c_id = (int)($_GET['id'] ?? 0);
    if ($c_id > 0) {
        $stmt_t = $conn->prepare("UPDATE caravans SET status = IF(status = 'active', 'inactive', 'active') WHERE id = ? AND status != 'completed'");
        $stmt_t->execute([$c_id]);
        $msg = 'وضعیت پذیرش کاروان با موفقیت تغییر یافت.';
    }
}

// بایگانی کردن کاروان
if (isset($_GET['action']) && $_GET['action'] === 'archive') {
    $c_id = (int)($_GET['id'] ?? 0);
    if ($c_id > 0) {
        $stmt_arc = $conn->prepare("UPDATE caravans SET status = 'completed' WHERE id = ?");
        $stmt_arc->execute([$c_id]);
        $msg = 'کاروان با موفقیت به بخش سفرهای برگزارشده (بایگانی) منتقل شد.';
    }
}

// بازگردانی از بایگانی به حالت فعال
if (isset($_GET['action']) && $_GET['action'] === 'restore') {
    $c_id = (int)($_GET['id'] ?? 0);
    if ($c_id > 0) {
        $stmt_res = $conn->prepare("UPDATE caravans SET status = 'active' WHERE id = ?");
        $stmt_res->execute([$c_id]);
        $msg = 'کاروان مجدداً فعال و آماده پذیرش شد.';
    }
}

// حذف کاروان
if (isset($_GET['action']) && $_GET['action'] === 'delete') {
    $c_id = (int)($_GET['id'] ?? 0);
    if ($c_id > 0) {
        $stmt_chk = $conn->prepare("SELECT COUNT(*) FROM pilgrims WHERE caravan_id = ?");
        $stmt_chk->execute([$c_id]);
        if ($stmt_chk->fetchColumn() > 0) {
            $err = 'به دلیل ثبت سوابق زائران، حذف این کاروان مقدور نیست. می‌توانید آن را «بایگانی» فرمایید.';
        } else {
            $stmt_del = $conn->prepare("DELETE FROM caravans WHERE id = ?");
            $stmt_del->execute([$c_id]);
            $msg = 'کاروان با موفقیت حذف گردید.';
        }
    }
}

// ذخیره‌سازی فرم (ثبت جدید یا ویرایش کاروان)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_caravan'])) {
    $caravan_id = (int)($_POST['caravan_id'] ?? 0);
    $title = safe($_POST['title'] ?? '');
    $destination = safe($_POST['destination'] ?? '');
    $trip_type = safe($_POST['trip_type'] ?? 'زمینی');
    $transport_departure = safe($_POST['transport_departure'] ?? '');
    $transport_return = safe($_POST['transport_return'] ?? '');
    $transport_internal = safe($_POST['transport_internal'] ?? '');
    $departure_place = safe($_POST['departure_place'] ?? '');
    $departure_time = safe($_POST['departure_time'] ?? '');
    
    // پردازش مقاصد و هتل‌های داینامیک
    $cities = $_POST['dest_city'] ?? [];
    $durations = $_POST['dest_duration'] ?? [];
    $hotels = $_POST['dest_hotel'] ?? [];
    $notes = $_POST['dest_notes'] ?? [];

    $itinerary_data = [];
    if (is_array($cities)) {
        for ($i = 0; $i < count($cities); $i++) {
            $city_name = trim($cities[$i]);
            if (!empty($city_name)) {
                $itinerary_data[] = [
                    'city' => safe($city_name),
                    'duration' => safe($durations[$i] ?? ''),
                    'hotel' => safe($hotels[$i] ?? ''),
                    'notes' => safe($notes[$i] ?? '')
                ];
            }
        }
    }
    $itinerary_json = !empty($itinerary_data) ? json_encode($itinerary_data, JSON_UNESCAPED_UNICODE) : null;

    $special_pilgrimages = safe($_POST['special_pilgrimages'] ?? '');
    $start_date = safe($_POST['start_date'] ?? '');
    $end_date = safe($_POST['end_date'] ?? '');
    $new_capacity = (int)($_POST['capacity'] ?? 0);

    $price_adult = (float)($_POST['price_adult'] ?? 0);
    $price_child = (float)($_POST['price_child'] ?? 0);
    $price_infant = (float)($_POST['price_infant'] ?? 0);
    $deposit_amount = (float)($_POST['deposit_amount'] ?? 0);
    
    $food_services = safe($_POST['food_services'] ?? '');
    $leader_name = safe($_POST['leader_name'] ?? '');
    $cleric_name = safe($_POST['cleric_name'] ?? '');
    $requires_passport = isset($_POST['requires_passport']) ? 1 : 0;
    $description = trim($_POST['description'] ?? '');

    if (empty($title) || empty($destination) || empty($start_date) || $new_capacity <= 0 || $price_adult <= 0) {
        $err = 'لطفاً فیلدهای الزامی (عنوان، مقصد، تاریخ اعزام، ظرفیت و نرخ بزرگسال) را تکمیل فرمایید.';
    } else {
        try {
            if ($caravan_id > 0) {
                // دریافت ظرفیت قبلی جهت تنظیم هوشمند باقی‌مانده
                $stmt_cur = $conn->prepare("SELECT capacity, remaining_capacity FROM caravans WHERE id = ?");
                $stmt_cur->execute([$caravan_id]);
                $curr = $stmt_cur->fetch();
                
                $diff = $new_capacity - (int)$curr['capacity'];
                $new_remaining = max(0, (int)$curr['remaining_capacity'] + $diff);

                $stmt_up = $conn->prepare("UPDATE caravans SET 
                    title = ?, destination = ?, itinerary = ?, special_pilgrimages = ?, trip_type = ?, 
                    transport_departure = ?, transport_return = ?, transport_internal = ?, departure_place = ?, departure_time = ?, 
                    requires_passport = ?, start_date = ?, end_date = ?, capacity = ?, remaining_capacity = ?, price_adult = ?, 
                    price_child = ?, price_infant = ?, deposit_amount = ?, food_services = ?, leader_name = ?, cleric_name = ?, description = ? 
                    WHERE id = ?");
                
                $stmt_up->execute([
                    $title, $destination, $itinerary_json, $special_pilgrimages,
                    $trip_type, $transport_departure, $transport_return, $transport_internal, $departure_place,
                    $departure_time, $requires_passport, $start_date, $end_date ?: null, $new_capacity, $new_remaining,
                    $price_adult, $price_child, $price_infant, $deposit_amount, $food_services, $leader_name,
                    $cleric_name, $description, $caravan_id
                ]);

                $msg = 'اطلاعات کاروان با موفقیت ویرایش و به‌روزرسانی شد.';
            } else {
                // ثبت جدید
                $stmt_ins = $conn->prepare("INSERT INTO caravans 
                    (title, destination, itinerary, special_pilgrimages, trip_type, 
                     transport_departure, transport_return, transport_internal, departure_place, departure_time, 
                     requires_passport, start_date, end_date, capacity, remaining_capacity, price_adult, 
                     price_child, price_infant, deposit_amount, food_services, leader_name, cleric_name, description, status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')");
                
                $stmt_ins->execute([
                    $title, $destination, $itinerary_json, $special_pilgrimages,
                    $trip_type, $transport_departure, $transport_return, $transport_internal, $departure_place,
                    $departure_time, $requires_passport, $start_date, $end_date ?: null, $new_capacity, $new_capacity,
                    $price_adult, $price_child, $price_infant, $deposit_amount, $food_services, $leader_name,
                    $cleric_name, $description
                ]);

                $msg = 'کاروان جدید با موفقیت ذخیره و منتشر شد.';
            }
        } catch (Exception $e) {
            $err = 'خطا در ذخیره‌سازی: ' . $e->getMessage();
        }
    }
}

// بررسی حالت ویرایش (واکشی اطلاعات کاروان جهت پر کردن فرم)
$edit_caravan = null;
$edit_itinerary = [];
if (isset($_GET['action']) && $_GET['action'] === 'edit') {
    $edit_id = (int)($_GET['id'] ?? 0);
    if ($edit_id > 0) {
        $stmt_e = $conn->prepare("SELECT * FROM caravans WHERE id = ?");
        $stmt_e->execute([$edit_id]);
        $edit_caravan = $stmt_e->fetch();
        if ($edit_caravan && !empty($edit_caravan['itinerary'])) {
            $edit_itinerary = json_decode($edit_caravan['itinerary'], true) ?: [];
        }
    }
}

// تب جاری
$view_tab = in_array($_GET['tab'] ?? '', ['active', 'archived']) ? $_GET['tab'] : 'active';

if ($view_tab === 'archived') {
    $caravans = $conn->query("SELECT * FROM caravans WHERE status = 'completed' ORDER BY id DESC")->fetchAll();
} else {
    $caravans = $conn->query("SELECT * FROM caravans WHERE status != 'completed' ORDER BY id DESC")->fetchAll();
}

$count_active = $conn->query("SELECT COUNT(*) FROM caravans WHERE status != 'completed'")->fetchColumn() ?: 0;
$count_archived = $conn->query("SELECT COUNT(*) FROM caravans WHERE status = 'completed'")->fetchColumn() ?: 0;
?>

<div class="space-y-6 text-xs">

    <!-- نوار عنوان -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center pb-4 border-b border-amber-500/20 gap-4">
        <div>
            <h1 class="text-xl sm:text-2xl font-black text-white flex items-center gap-2">
                <span>🧭</span> مدیریت و زمان‌بندی کاروان‌های زیارتی
            </h1>
            <p class="text-xs text-amber-200/80 mt-1">تعریف، ویرایش مقاصد و هتل‌ها، ظرفیت‌بندی، ناوگان ترانسفر و بایگانی سفرها</p>
        </div>

        <button onclick="toggleForm()" 
                class="bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-400 text-slate-950 font-black text-xs px-4 py-2.5 rounded-xl transition shadow-md shadow-amber-600/20 flex items-center gap-2">
            <span id="btn_icon"><?= $edit_caravan ? '✏️' : '➕' ?></span>
            <span id="btn_label"><?= $edit_caravan ? 'ویرایش این کاروان' : 'تعریف کاروان جدید' ?></span>
        </button>
    </div>

    <?php if ($msg): ?>
        <div class="p-3.5 bg-emerald-950/60 border border-emerald-500/40 text-emerald-200 rounded-2xl font-bold">
            ✅ <?= safe($msg) ?>
        </div>
    <?php endif; ?>

    <?php if ($err): ?>
        <div class="p-3.5 bg-rose-950/60 border border-rose-500/40 text-rose-200 rounded-2xl font-bold">
            ❌ <?= safe($err) ?>
        </div>
    <?php endif; ?>

    <!-- تب‌های تفکیک جاری و بایگانی -->
    <div class="flex items-center gap-3 border-b border-amber-500/20 pb-2">
        <a href="caravans.php?tab=active" 
           class="px-4 py-2 rounded-xl text-xs font-black transition flex items-center gap-2 <?= $view_tab === 'active' ? 'bg-amber-500 text-slate-950 shadow-md shadow-amber-500/20' : 'bg-[#031712] text-slate-400 hover:text-white border border-amber-500/20' ?>">
            <span>🕌</span> کاروان‌های جاری و در حال پذیرش
            <span class="px-2 py-0.5 rounded-full text-[10px] <?= $view_tab === 'active' ? 'bg-slate-950 text-amber-300' : 'bg-white/10 text-slate-300' ?>">
                <?= $count_active ?>
            </span>
        </a>

        <a href="caravans.php?tab=archived" 
           class="px-4 py-2 rounded-xl text-xs font-black transition flex items-center gap-2 <?= $view_tab === 'archived' ? 'bg-amber-500 text-slate-950 shadow-md shadow-amber-500/20' : 'bg-[#031712] text-slate-400 hover:text-white border border-amber-500/20' ?>">
            <span>📦</span> بایگانی سفرهای برگزارشده
            <span class="px-2 py-0.5 rounded-full text-[10px] <?= $view_tab === 'archived' ? 'bg-slate-950 text-amber-300' : 'bg-white/10 text-slate-300' ?>">
                <?= $count_archived ?>
            </span>
        </a>
    </div>

    <!-- فرم جامع ثبت و ویرایش کاروان -->
    <div id="new_caravan_box" class="<?= $edit_caravan ? '' : 'hidden' ?> rogh-card rounded-3xl p-6 sm:p-8 space-y-6 border border-amber-500/30 transition">
        <div class="pb-3 border-b border-amber-500/20 flex justify-between items-center">
            <h2 class="font-black text-sm text-amber-300 flex items-center gap-2">
                <span>✦</span> <?= $edit_caravan ? 'ویرایش مشخصات کاروان: ' . safe($edit_caravan['title']) : 'ثبت پکیج سفر زیارتی جدید' ?>
            </h2>
            <?php if ($edit_caravan): ?>
                <a href="caravans.php?tab=<?= $view_tab ?>" class="text-slate-400 hover:text-amber-300 text-xs font-bold">✕ انصراف از ویرایش</a>
            <?php else: ?>
                <button type="button" onclick="toggleForm()" class="text-slate-400 hover:text-amber-300 text-xs font-bold">✕ بستن</button>
            <?php endif; ?>
        </div>
        
        <form action="caravans.php?tab=<?= $view_tab ?>" method="POST" class="space-y-6">
            <input type="hidden" name="save_caravan" value="1">
            <input type="hidden" name="caravan_id" value="<?= $edit_caravan['id'] ?? 0 ?>">

            <!-- بخش ۱: اطلاعات پایه -->
            <div class="space-y-3">
                <span class="text-xs font-black text-amber-400 flex items-center gap-1.5">
                    <span>۱.</span> مشخصات کلی کاروان:
                </span>
                <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
                    <div class="md:col-span-2">
                        <label class="block text-xs font-bold text-slate-200 mb-1">عنوان کاروان: <span class="text-rose-400">*</span></label>
                        <input type="text" name="title" required 
                               value="<?= safe($edit_caravan['title'] ?? '') ?>"
                               placeholder="مثال: تور زیارتی عتبات عالیات ویژه" 
                               class="w-full bg-[#031712] border border-amber-500/30 rounded-xl p-2.5 text-xs text-white focus:outline-none focus:border-amber-400">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-200 mb-1">عنوان کلی مقصد: <span class="text-rose-400">*</span></label>
                        <input type="text" name="destination" required 
                               value="<?= safe($edit_caravan['destination'] ?? '') ?>"
                               placeholder="عراق (عتبات) / مشهد مقدس" 
                               class="w-full bg-[#031712] border border-amber-500/30 rounded-xl p-2.5 text-xs text-white focus:outline-none focus:border-amber-400">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-200 mb-1">ظرفیت کل کاروان (نفر): <span class="text-rose-400">*</span></label>
                        <input type="number" name="capacity" min="1" required 
                               value="<?= (int)($edit_caravan['capacity'] ?? 40) ?>"
                               class="w-full bg-[#031712] border border-amber-500/30 rounded-xl p-2.5 text-xs text-white font-mono focus:outline-none focus:border-amber-400">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-slate-200 mb-1">تاریخ حرکت (شمسی): <span class="text-rose-400">*</span></label>
                        <input type="text" id="caravan_start_date" name="start_date" required 
                               value="<?= safe($edit_caravan['start_date'] ?? '') ?>"
                               placeholder="۱۴۰۵/۰۵/۱۰" 
                               class="w-full bg-[#031712] border border-amber-500/30 rounded-xl p-2.5 text-xs text-amber-300 font-mono cursor-pointer">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-200 mb-1">تاریخ بازگشت (شمسی):</label>
                        <input type="text" id="caravan_end_date" name="end_date" 
                               value="<?= safe($edit_caravan['end_date'] ?? '') ?>"
                               placeholder="۱۴۰۵/۰۵/۱۸" 
                               class="w-full bg-[#031712] border border-amber-500/30 rounded-xl p-2.5 text-xs text-amber-300 font-mono cursor-pointer">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-200 mb-1">ساعت حرکت / پرواز:</label>
                        <input type="text" name="departure_time" 
                               value="<?= safe($edit_caravan['departure_time'] ?? '') ?>"
                               placeholder="مثال: ساعت ۰۵:۳۰ صبح" 
                               class="w-full bg-[#031712] border border-amber-500/30 rounded-xl p-2.5 text-xs text-white font-mono">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-200 mb-1">محل تجمع و حرکت:</label>
                        <input type="text" name="departure_place" 
                               value="<?= safe($edit_caravan['departure_place'] ?? '') ?>"
                               placeholder="مثال: ترمینال غرب / فرودگاه امام (ره)" 
                               class="w-full bg-[#031712] border border-amber-500/30 rounded-xl p-2.5 text-xs text-white">
                    </div>
                </div>
            </div>

            <!-- بخش ۲: برنامه تفکیکی اقامت در شهرهای زیارتی و هتل‌ها (داینامیک) -->
            <div class="p-5 bg-[#031712] rounded-2xl border border-amber-500/30 space-y-4">
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center pb-2 border-b border-amber-500/20 gap-2">
                    <div>
                        <span class="text-xs font-black text-amber-300 flex items-center gap-1.5">
                            <span>۲.</span> برنامه تفکیکی اقامت در شهرهای زیارتی و هتل‌ها:
                        </span>
                        <p class="text-[11px] text-slate-400 mt-0.5">می‌توانید هر تعداد شهر زیارتی، مدت اقامت و نام هتل را اضافه یا حذف فرمایید:</p>
                    </div>

                    <button type="button" onclick="addDestinationRow()" 
                            class="bg-amber-500/20 hover:bg-amber-500/30 text-amber-300 border border-amber-500/40 px-3 py-1.5 rounded-xl text-xs font-bold transition flex items-center gap-1">
                        <span>➕</span> افزودن شهر زیارتی جدید
                    </button>
                </div>

                <div id="destinations_container" class="space-y-3">
                    <!-- ردیف‌های مقاصد توسط جاوااسکریپت بارگذاری می‌شوند -->
                </div>

                <div>
                    <label class="block text-[11px] font-bold text-slate-300 mb-1">اماکن و زیارت‌های دوره‌ای (اختیاری):</label>
                    <input type="text" name="special_pilgrimages" 
                           value="<?= safe($edit_caravan['special_pilgrimages'] ?? '') ?>"
                           placeholder="مثال: مسجد کوفه، مسجد سهله، وادی‌السلام، طفلان مسلم" 
                           class="w-full bg-[#051c16] border border-amber-500/30 rounded-xl p-2.5 text-xs text-white">
                </div>
            </div>

            <!-- بخش ۳: ناوگان حمل و نقل و ترانسفر -->
            <div class="p-4 bg-[#031712] rounded-2xl border border-amber-500/20 space-y-3">
                <span class="text-xs font-black text-amber-300 flex items-center gap-1.5">
                    <span>۳.</span> ناوگان حمل‌ونقل و ترانسفر سفر:
                </span>
                
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-3">
                    <div>
                        <label class="block text-[11px] font-bold text-slate-300 mb-1">نوع کلی اعزام:</label>
                        <select name="trip_type" class="w-full bg-[#051c16] border border-amber-500/30 rounded-xl p-2.5 text-xs text-white">
                            <?php 
                            $types = ['زمینی (اتوبوس VIP ۲۵ نفره)', 'هوایی (پرواز رفت و برگشت)', 'ترکیبی (پرواز رفت / اتوبوس برگشت)', 'ریلی (قطار ۶ تخته / ۴ تخته)'];
                            $sel_type = $edit_caravan['trip_type'] ?? 'زمینی (اتوبوس VIP ۲۵ نفره)';
                            foreach ($types as $tt): ?>
                                <option value="<?= $tt ?>" <?= $sel_type === $tt ? 'selected' : '' ?>><?= $tt ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-[11px] font-bold text-slate-300 mb-1">وسیله نقلیه رفت (از ایران):</label>
                        <input type="text" name="transport_departure" 
                               value="<?= safe($edit_caravan['transport_departure'] ?? 'اتوبوس ۲۵ نفره تخت‌شو VIP') ?>" 
                               class="w-full bg-[#051c16] border border-amber-500/30 rounded-xl p-2.5 text-xs text-white">
                    </div>

                    <div>
                        <label class="block text-[11px] font-bold text-slate-300 mb-1">وسیله نقلیه برگشت (به ایران):</label>
                        <input type="text" name="transport_return" 
                               value="<?= safe($edit_caravan['transport_return'] ?? 'اتوبوس ۲۵ نفره تخت‌شو VIP') ?>" 
                               class="w-full bg-[#051c16] border border-amber-500/30 rounded-xl p-2.5 text-xs text-white">
                    </div>

                    <div>
                        <label class="block text-[11px] font-bold text-slate-300 mb-1">ترانسفر داخلی در مقصد:</label>
                        <input type="text" name="transport_internal" 
                               value="<?= safe($edit_caravan['transport_internal'] ?? 'اتوبوس توریستی عراقی (کولردار)') ?>" 
                               class="w-full bg-[#051c16] border border-amber-500/30 rounded-xl p-2.5 text-xs text-white">
                    </div>
                </div>
            </div>

            <!-- بخش ۴: قیمت‌گذاری رده‌های سنی و بیعانه -->
            <div class="p-4 bg-[#031712] rounded-2xl border border-amber-500/20 space-y-3">
                <span class="text-xs font-black text-amber-300 flex items-center gap-1.5">
                    <span>۴.</span> نرخ‌گذاری و تسویه‌حساب (به تومان):
                </span>
                
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-3">
                    <div>
                        <label class="block text-[11px] text-slate-300 mb-1">نرخ بزرگسال (۱۲ سال به بالا): <span class="text-rose-400">*</span></label>
                        <input type="number" name="price_adult" required 
                               value="<?= (float)($edit_caravan['price_adult'] ?? '') ?>"
                               placeholder="۹۵۰۰۰۰۰" 
                               class="w-full bg-[#051c16] border border-amber-500/30 rounded-xl p-2.5 text-xs text-amber-300 font-mono">
                    </div>
                    <div>
                        <label class="block text-[11px] text-slate-300 mb-1">نرخ کودک (۲ تا ۱۲ سال):</label>
                        <input type="number" name="price_child" 
                               value="<?= (float)($edit_caravan['price_child'] ?? '') ?>"
                               placeholder="۷۰۰۰۰۰۰" 
                               class="w-full bg-[#051c16] border border-amber-500/30 rounded-xl p-2.5 text-xs text-white font-mono">
                    </div>
                    <div>
                        <label class="block text-[11px] text-slate-300 mb-1">نرخ نوزاد (زیر ۲ سال - بیمه):</label>
                        <input type="number" name="price_infant" 
                               value="<?= (float)($edit_caravan['price_infant'] ?? '') ?>"
                               placeholder="۱۵۰۰۰۰۰" 
                               class="w-full bg-[#051c16] border border-amber-500/30 rounded-xl p-2.5 text-xs text-white font-mono">
                    </div>
                    <div>
                        <label class="block text-[11px] text-slate-300 mb-1">حداقل بیعانه رزرو:</label>
                        <input type="number" name="deposit_amount" 
                               value="<?= (float)($edit_caravan['deposit_amount'] ?? '') ?>"
                               placeholder="۳۰۰۰۰۰۰" 
                               class="w-full bg-[#051c16] border border-amber-500/30 rounded-xl p-2.5 text-xs text-white font-mono">
                    </div>
                </div>
            </div>

            <!-- بخش ۵: عوامل اجرایی و خدمات تغذیه -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                <div>
                    <label class="block text-xs font-bold text-slate-200 mb-1">نام مداح / سرپرست کاروان:</label>
                    <input type="text" name="leader_name" 
                           value="<?= safe($edit_caravan['leader_name'] ?? '') ?>"
                           placeholder="مثال: حاج مهدی رسولی" 
                           class="w-full bg-[#031712] border border-amber-500/30 rounded-xl p-2.5 text-xs text-white">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-200 mb-1">نام روحانی / کارشناس معارف:</label>
                    <input type="text" name="cleric_name" 
                           value="<?= safe($edit_caravan['cleric_name'] ?? '') ?>"
                           placeholder="مثال: حجت‌الاسلام حسینی" 
                           class="w-full bg-[#031712] border border-amber-500/30 rounded-xl p-2.5 text-xs text-white">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-200 mb-1">خدمات پذیرایی و تغذیه:</label>
                    <input type="text" name="food_services" 
                           value="<?= safe($edit_caravan['food_services'] ?? 'صبحانه، ناهار، شام به همراه میوه و نوشیدنی هتلی') ?>" 
                           class="w-full bg-[#031712] border border-amber-500/30 rounded-xl p-2.5 text-xs text-white">
                </div>
            </div>

            <!-- الزامی بودن گذرنامه -->
            <div class="flex items-center gap-2.5 p-3 bg-[#031712] rounded-xl border border-amber-500/20">
                <input type="checkbox" name="requires_passport" id="req_pass" value="1" 
                       <?= (!isset($edit_caravan) || !empty($edit_caravan['requires_passport'])) ? 'checked' : '' ?>
                       class="w-4 h-4 text-amber-500 rounded cursor-pointer">
                <label for="req_pass" class="text-xs font-bold text-amber-200 cursor-pointer select-none">
                    این سفر نیازمند گذرنامه دارای حداقل ۶ ماه اعتبار است (ویژه عتبات و خارج از کشور)
                </label>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-200 mb-1.5">توضیحات تکمیلی و شرایط سفر:</label>
                <textarea name="description" rows="3" placeholder="توضیحات بیشتر برای زائرین، اینترنت در هتل، عوارض خروج از کشور و..." 
                          class="w-full bg-[#031712] border border-amber-500/30 rounded-xl p-3 text-xs text-slate-200 leading-relaxed focus:outline-none focus:border-amber-400"><?= htmlspecialchars($edit_caravan['description'] ?? '') ?></textarea>
            </div>

            <div class="flex items-center gap-3 pt-2">
                <button type="submit" class="bg-gradient-to-r from-amber-500 via-amber-400 to-amber-500 hover:from-amber-400 text-slate-950 font-black py-3 px-8 rounded-xl text-xs transition duration-200 shadow-lg shadow-amber-500/20">
                    <?= $edit_caravan ? '💾 ذخیره تغییرات ویرایش کاروان' : 'ذخیره و انتشار کاروان زیارتی' ?>
                </button>
                <?php if ($edit_caravan): ?>
                    <a href="caravans.php?tab=<?= $view_tab ?>" class="bg-[#031712] text-slate-300 hover:text-white border border-amber-500/20 font-bold py-3 px-6 rounded-xl text-xs transition">
                        انصراف
                    </a>
                <?php else: ?>
                    <button type="button" onclick="toggleForm()" class="bg-[#031712] text-slate-300 hover:text-white border border-amber-500/20 font-bold py-3 px-6 rounded-xl text-xs transition">
                        انصراف
                    </button>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- جدول کاروان‌ها -->
    <div class="rogh-card rounded-2xl overflow-hidden border border-amber-500/30">
        <div class="p-4 border-b border-amber-500/20 flex justify-between items-center bg-[#031511]">
            <div class="flex items-center gap-2">
                <span class="text-amber-400 font-bold text-sm">✦</span>
                <h2 class="font-black text-xs sm:text-sm text-white">
                    <?= $view_tab === 'archived' ? 'لیست کاروان‌های برگزارشده (بایگانی)' : 'لیست کاروان‌های فعال و در حال پذیرش' ?>
                </h2>
            </div>
            <span class="text-xs text-amber-200/80 font-mono">مجموع: <?= count($caravans) ?> کاروان</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-right border-collapse text-xs">
                <thead>
                    <tr class="bg-[#02100d] text-amber-200/80 border-b border-amber-500/10">
                        <th class="p-3.5">عنوان کاروان</th>
                        <th class="p-3.5">برنامه مقاصد و هتل‌ها</th>
                        <th class="p-3.5">ناوگان حمل‌ونقل</th>
                        <th class="p-3.5">تاریخ اعزام</th>
                        <th class="p-3.5">ظرفیت</th>
                        <th class="p-3.5 text-center">وضعیت</th>
                        <th class="p-3.5 text-center">مدیریت سفر</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-amber-500/10">
                    <?php if (empty($caravans)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-8 text-slate-500">
                                <?= $view_tab === 'archived' ? 'هنوز سفری به بایگانی منتقل نشده است.' : 'هیچ کاروان فعالی در این بخش وجود ندارد.' ?>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($caravans as $c): ?>
                            <?php 
                                $rem_cap = (int)($c['remaining_capacity'] ?? 0);
                                $tot_cap = (int)($c['capacity'] ?? $rem_cap);
                                $is_archived = ($c['status'] === 'completed');

                                $stmt_pc = $conn->prepare("SELECT COUNT(*) FROM pilgrims WHERE caravan_id = ? AND status != 'cancelled'");
                                $stmt_pc->execute([$c['id']]);
                                $pilgrims_count = $stmt_pc->fetchColumn() ?: 0;

                                $itin = [];
                                if (!empty($c['itinerary'])) {
                                    $itin = json_decode($c['itinerary'], true) ?: [];
                                }
                            ?>
                            <tr class="hover:bg-white/5 transition <?= $is_archived ? 'opacity-80 bg-black/20' : '' ?>">
                                <td class="p-3.5">
                                    <div class="font-bold text-white text-sm"><?= safe($c['title']) ?></div>
                                    <div class="text-[11px] text-amber-300/90 mt-0.5">
                                        📍 حوزه: <?= safe($c['destination']) ?>
                                    </div>
                                    <?php if (!empty($c['leader_name'])): ?>
                                        <div class="text-[10px] text-slate-400 mt-0.5">سرپرست/مداح: <?= safe($c['leader_name']) ?></div>
                                    <?php endif; ?>
                                </td>

                                <td class="p-3.5 text-slate-300">
                                    <?php if (!empty($itin)): ?>
                                        <div class="space-y-1">
                                            <?php foreach ($itin as $it): ?>
                                                <div class="text-[11px]">
                                                    <span class="text-amber-300 font-bold">🏛️ <?= safe($it['city']) ?>:</span>
                                                    <span><?= safe($it['duration']) ?></span>
                                                    <?php if (!empty($it['hotel'])): ?>
                                                        <span class="text-slate-400 text-[10px]">(هتل: <?= safe($it['hotel']) ?>)</span>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-slate-500 text-[11px]">برنامه تفکیکی ثبت نشده</span>
                                    <?php endif; ?>
                                </td>

                                <td class="p-3.5 text-[11px] text-slate-300">
                                    <span class="bg-[#031712] border border-amber-500/20 px-2 py-0.5 rounded text-amber-300 block w-fit mb-1">
                                        <?= safe($c['trip_type']) ?>
                                    </span>
                                    <?php if (!empty($c['transport_departure'])): ?>
                                        <div class="text-[10px] text-slate-400">رفت: <?= safe($c['transport_departure']) ?></div>
                                    <?php endif; ?>
                                </td>

                                <td class="p-3.5 font-mono text-slate-300">
                                    <div>رفت: <span class="text-amber-200"><?= safe($c['start_date']) ?></span></div>
                                    <div class="text-[10px] text-slate-400">برگشت: <?= safe($c['end_date'] ?: '—') ?></div>
                                </td>

                                <td class="p-3.5 font-mono">
                                    <?php if ($is_archived): ?>
                                        <span class="text-slate-400 text-[11px]">پایان‌یافته</span>
                                    <?php else: ?>
                                        <span class="font-bold <?= $rem_cap > 0 ? 'text-emerald-400' : 'text-rose-400' ?>">
                                            <?= $rem_cap ?>
                                        </span>
                                        <span class="text-slate-400">از <?= $tot_cap ?></span>
                                    <?php endif; ?>
                                    <a href="pilgrims.php?caravan_id=<?= $c['id'] ?>" class="text-amber-300 hover:underline block text-[10px] mt-0.5">
                                        <?= $pilgrims_count ?> ثبت‌نامی
                                    </a>
                                </td>

                                <!-- قابلیت تغییر وضعیت کاروان (دست‌نخورده طبق خواسته شما) -->
                                <td class="p-3.5 text-center whitespace-nowrap">
                                    <?php if ($is_archived): ?>
                                        <span class="px-2.5 py-1 rounded-xl text-[10px] font-black bg-purple-950/60 text-purple-300 border border-purple-500/40">
                                            📦 بایگانی
                                        </span>
                                    <?php else: ?>
                                        <a href="caravans.php?action=toggle&id=<?= $c['id'] ?>&tab=active" 
                                           class="px-2.5 py-1 rounded-xl text-[10px] font-bold transition <?= $c['status'] === 'active' ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/30 hover:bg-emerald-500/30' : 'bg-slate-700/60 text-slate-300 border border-slate-600 hover:bg-slate-700' ?>">
                                            <?= $c['status'] === 'active' ? 'در حال پذیرش' : 'متوقف' ?>
                                        </a>
                                    <?php endif; ?>
                                </td>

                                <td class="p-3.5 text-center whitespace-nowrap">
                                    <div class="inline-flex items-center gap-2">
                                        <a href="caravans.php?action=edit&id=<?= $c['id'] ?>&tab=<?= $view_tab ?>" 
                                           class="text-amber-400 hover:text-amber-300 font-bold hover:underline text-xs flex items-center gap-0.5">
                                            ✏️ ویرایش
                                        </a>

                                        <span class="text-slate-600">|</span>

                                        <a href="export_center.php?caravan_id=<?= $c['id'] ?>" target="_blank" 
                                           class="text-emerald-400 hover:text-emerald-300 font-bold hover:underline text-xs" title="مانیفست زائران">
                                            📄 مانیفست
                                        </a>

                                        <span class="text-slate-600">|</span>

                                        <?php if ($is_archived): ?>
                                            <a href="caravans.php?action=restore&id=<?= $c['id'] ?>&tab=archived" 
                                               onclick="return confirm('کاروان مجدداً فعال شود؟')" 
                                               class="text-amber-400 hover:text-amber-300 font-bold hover:underline text-xs">
                                                ♻️ بازگردانی
                                            </a>
                                        <?php else: ?>
                                            <a href="caravans.php?action=archive&id=<?= $c['id'] ?>&tab=active" 
                                               onclick="return confirm('کاروان به بایگانی سفرهای گذشته منتقل شود؟')" 
                                               class="text-indigo-300 hover:text-indigo-200 font-bold hover:underline text-xs">
                                                📦 بایگانی
                                            </a>
                                        <?php endif; ?>

                                        <?php if ($pilgrims_count == 0): ?>
                                            <span class="text-slate-600">|</span>
                                            <a href="caravans.php?action=delete&id=<?= $c['id'] ?>&tab=<?= $view_tab ?>" 
                                               onclick="return confirm('کاروان حذف شود؟')" 
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

<script>
    function toggleForm() {
        const box = document.getElementById('new_caravan_box');
        const icon = document.getElementById('btn_icon');
        const label = document.getElementById('btn_label');
        if (box.classList.contains('hidden')) {
            box.classList.remove('hidden');
            icon.textContent = '✕';
            label.textContent = 'بستن فرم کاروان';
            box.scrollIntoView({ behavior: 'smooth' });
        } else {
            box.classList.add('hidden');
            icon.textContent = '➕';
            label.textContent = 'تعریف کاروان جدید';
        }
    }

    let destRowIndex = 0;
    function addDestinationRow(cityVal = '', durVal = '', hotelVal = '', noteVal = '') {
        const container = document.getElementById('destinations_container');
        const rowId = 'dest_row_' + destRowIndex++;

        const row = document.createElement('div');
        row.id = rowId;
        row.className = 'p-3 rounded-xl bg-[#051c16] border border-amber-500/20 grid grid-cols-1 sm:grid-cols-12 gap-2.5 items-end transition';

        row.innerHTML = `
            <div class="sm:col-span-3">
                <label class="block text-[10px] text-slate-300 mb-1">نام شهر زیارتی:</label>
                <input type="text" name="dest_city[]" value="${cityVal}" placeholder="مثال: نجف اشرف" required 
                       class="w-full bg-[#031712] border border-amber-500/30 rounded-lg p-2 text-xs text-white">
            </div>

            <div class="sm:col-span-3">
                <label class="block text-[10px] text-slate-300 mb-1">مدت / تاریخ اقامت:</label>
                <input type="text" name="dest_duration[]" value="${durVal}" placeholder="مثال: ۲ شب اقامت" 
                       class="w-full bg-[#031712] border border-amber-500/30 rounded-lg p-2 text-xs text-amber-300 font-mono">
            </div>

            <div class="sm:col-span-3">
                <label class="block text-[10px] text-slate-300 mb-1">نام هتل یا محل اسکان:</label>
                <input type="text" name="dest_hotel[]" value="${hotelVal}" placeholder="مثال: هتل قصرالضیافه" 
                       class="w-full bg-[#031712] border border-amber-500/30 rounded-lg p-2 text-xs text-white">
            </div>

            <div class="sm:col-span-2">
                <label class="block text-[10px] text-slate-300 mb-1">توضیحات / فاصله تا حرم:</label>
                <input type="text" name="dest_notes[]" value="${noteVal}" placeholder="مثال: ۳ دقیقه تا حرم" 
                       class="w-full bg-[#031712] border border-amber-500/30 rounded-lg p-2 text-xs text-slate-200">
            </div>

            <div class="sm:col-span-1 text-center">
                <button type="button" onclick="removeDestinationRow('${rowId}')" 
                        class="w-full bg-rose-950/60 hover:bg-rose-900 text-rose-300 border border-rose-500/30 py-2 rounded-lg text-xs font-bold transition" title="حذف این مقصد">
                    🗑️
                </button>
            </div>
        `;

        container.appendChild(row);
    }

    function removeDestinationRow(rowId) {
        const row = document.getElementById(rowId);
        if (row) row.remove();
    }

    $(document).ready(function() {$('#caravan_start_date, #caravan_end_date').persianDatepicker({
            format: 'YYYY/MM/DD',
            autoClose: true,
            initialValue: false
        });

        <?php if (!empty($edit_itinerary)): ?>
            <?php foreach ($edit_itinerary as$it): ?>
                addDestinationRow(
                    <?= json_encode($it['city'] ?? '', JSON_UNESCAPED_UNICODE) ?>,
                    <?= json_encode($it['duration'] ?? '', JSON_UNESCAPED_UNICODE) ?>,
                    <?= json_encode($it['hotel'] ?? '', JSON_UNESCAPED_UNICODE) ?>,
                    <?= json_encode($it['notes'] ?? '', JSON_UNESCAPED_UNICODE) ?>
                );
            <?php endforeach; ?>
        <?php elseif (!$edit_caravan): ?>
            addDestinationRow('نجف اشرف', '۲ شب', 'هتل قصرالضیافه', 'فاصله تا حرم ۲۰۰ متر');
            addDestinationRow('کربلای معلی', '۴ شب', 'هتل بارون / الاماره', 'دارای سرویس رفت‌وبرگشت');
        <?php endif; ?>
    });
</script>

<?php require_once __DIR__ . '/footer.php'; ?>