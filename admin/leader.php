<?php
// ابتدا فقط فایل احراز هویت را لود می‌کنیم (بدون ارسال HTML)
require_once __DIR__ . '/auth.php';

// دسترسی برای مدیر ارشد، مسئول ثبت‌نام و سرپرست کاروان
require_roles(['super_admin', 'registrar', 'leader']);

// بررسی وجود ستون حضور و غیاب در دیتابیس
try {
    $conn->query("SELECT is_present FROM pilgrims LIMIT 1");
} catch (Exception $e) {
    $conn->query("ALTER TABLE pilgrims ADD COLUMN is_present TINYINT(1) DEFAULT 0 AFTER status");
}

// پردازش ثبت حضور و غیاب به صورت AJAX (باید قبل از هدر باشد)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_attendance'])) {
    header('Content-Type: application/json; charset=utf-8');

    $pilgrim_id = (int)($_POST['pilgrim_id'] ?? 0);
    $status = (int)($_POST['status'] ?? 0);

    if ($pilgrim_id > 0) {
        $stmt_up = $conn->prepare("UPDATE pilgrims SET is_present = ? WHERE id = ?");
        $stmt_up->execute([$status, $pilgrim_id]);
        echo json_encode(['success' => true, 'is_present' => $status]);
    } else {
        echo json_encode(['success' => false, 'message' => 'شناسه زائر نامعتبر است.']);
    }
    exit;
}

// حالا که درخواست ایجکس نیست، هدر اصلی را لود می‌کنیم
require_once __DIR__ . '/header.php';

// واکشی لیست کاروان‌ها برای انتخاب
$caravans = $conn->query("SELECT id, title, destination, start_date FROM caravans ORDER BY id DESC")->fetchAll();

$selected_caravan = (int)($_GET['caravan_id'] ?? (!empty($caravans) ? $caravans[0]['id'] : 0));

// واکشی مسافران قطعی کاروان انتخابی
$pilgrims = [];
$stats = ['total' => 0, 'present' => 0, 'absent' => 0];

if ($selected_caravan > 0) {
    $stmt_p = $conn->prepare("SELECT * FROM pilgrims WHERE caravan_id = ? AND status != 'cancelled' ORDER BY fullname ASC");
    $stmt_p->execute([$selected_caravan]);
    $pilgrims = $stmt_p->fetchAll();

    $stats['total'] = count($pilgrims);
    foreach ($pilgrims as $p) {
        if (!empty($p['is_present'])) {
            $stats['present']++;
        } else {
            $stats['absent']++;
        }
    }
}
?>

<div class="space-y-6">

    <!-- نوار عنوان صفحه -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center pb-4 border-b border-amber-500/20 gap-3">
        <div>
            <h1 class="text-xl sm:text-2xl font-black text-white flex items-center gap-2">
                <span>📋</span> پنل حضور و غیاب میدانی سرپرست کاروان
            </h1>
            <p class="text-xs text-amber-200/80 mt-1">بررسی لحظه‌ای حضور زائران در اتوبوس، مرز، هتل‌ها و صحن حرمین شریفین</p>
        </div>

        <?php if ($selected_caravan > 0): ?>
            <a href="print_pdf.php?caravan_id=<?= $selected_caravan ?>" target="_blank"
               class="bg-rose-900/60 hover:bg-rose-800 text-rose-200 border border-rose-500/40 text-xs font-bold px-3.5 py-2.5 rounded-xl shadow-sm flex items-center gap-1.5 transition">
                <span>📄</span> چاپ مانیفست فیزیکی مسافران
            </a>
        <?php endif; ?>
    </div>

    <!-- نوار انتخاب کاروان و جستجوی سریع -->
    <div class="rogh-card p-4 sm:p-5 rounded-2xl border border-amber-500/20 flex flex-col md:flex-row justify-between items-stretch md:items-center gap-4">
        
        <form method="GET" action="leader.php" class="flex flex-col sm:flex-row items-start sm:items-center gap-2.5">
            <label class="text-xs font-bold text-amber-300">انتخاب کاروان جهت حضور و غیاب:</label>
            <select name="caravan_id" onchange="this.form.submit()" 
                    class="bg-[#031712] border border-amber-500/30 rounded-xl px-3 py-2 text-xs text-white focus:outline-none focus:border-amber-400 w-full sm:w-auto">
                <?php foreach ($caravans as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= $selected_caravan == $c['id'] ? 'selected' : '' ?>>
                        <?= safe($c['title']) ?> (حرکت: <?= safe($c['start_date']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </form>

        <!-- جعبه جستجوی درجا در صفحه بدون رفرش -->
        <div class="relative">
            <input type="text" id="live_search" placeholder="جستجوی سریع نام یا کدملی..." 
                   class="w-full md:w-64 bg-[#031712] border border-amber-500/30 rounded-xl px-3 py-2 text-xs text-white placeholder:text-slate-500 focus:outline-none focus:border-amber-400">
            <span class="absolute left-3 top-2 text-xs text-slate-400">🔍</span>
        </div>

    </div>

    <!-- شمسه‌های آمار وضعیت حاضرین و غایبین -->
    <div class="grid grid-cols-3 gap-3 sm:gap-4 text-center">
        <div class="rogh-card p-4 rounded-2xl border border-amber-500/20">
            <span class="text-[11px] text-slate-400 block font-bold">کل مسافران کاروان</span>
            <span class="text-xl sm:text-2xl font-black text-white font-mono mt-1 block" id="stat_total"><?= $stats['total'] ?></span>
        </div>

        <div class="rogh-card p-4 rounded-2xl border border-emerald-500/40 bg-emerald-950/20">
            <span class="text-[11px] text-emerald-300 block font-bold">زائران حاضر (تأییدشده)</span>
            <span class="text-xl sm:text-2xl font-black text-emerald-400 font-mono mt-1 block" id="stat_present"><?= $stats['present'] ?></span>
        </div>

        <div class="rogh-card p-4 rounded-2xl border border-rose-500/40 bg-rose-950/20">
            <span class="text-[11px] text-rose-300 block font-bold">افراد ثبت‌نشده / غایب</span>
            <span class="text-xl sm:text-2xl font-black text-rose-400 font-mono mt-1 block" id="stat_absent"><?= $stats['absent'] ?></span>
        </div>
    </div>

    <!-- لیست زائران جهت حضور و غیاب سریع -->
    <div class="rogh-card rounded-2xl overflow-hidden border border-amber-500/30">
        <div class="p-4 border-b border-amber-500/20 flex justify-between items-center bg-[#031511]">
            <div class="flex items-center gap-2">
                <span class="text-amber-400 font-bold text-sm">✦</span>
                <h2 class="font-black text-xs sm:text-sm text-white">لیست مسافران کاروان برای ثبت حضور</h2>
            </div>
            <span class="text-[11px] text-amber-200/80">لمس دکمه وضعیت، حضور را به صورت آنی ذخیره می‌کند.</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-right border-collapse text-xs">
                <thead>
                    <tr class="bg-[#02100d] text-amber-200/80 border-b border-amber-500/10">
                        <th class="p-3.5 text-center" style="width: 45px;">ردیف</th>
                        <th class="p-3.5">نام و نام خانوادگی</th>
                        <th class="p-3.5">کد ملی / گذرنامه</th>
                        <th class="p-3.5">تماس مستقیم با زائر</th>
                        <th class="p-3.5">تماس اضطراری</th>
                        <th class="p-3.5">ملاحظات پزشکی</th>
                        <th class="p-3.5 text-center" style="width: 170px;">ثبت وضعیت حضور</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-amber-500/10" id="pilgrims_table_body">
                    <?php if (empty($pilgrims)): ?>
                        <tr><td colspan="7" class="text-center py-8 text-slate-500">هیچ مسافری برای این کاروان یافت نشد.</td></tr>
                    <?php else: ?>
                        <?php foreach ($pilgrims as $idx => $p): ?>
                            <?php $is_pres = !empty($p['is_present']); ?>
                            <tr class="hover:bg-white/5 transition pilgrim-row <?= $is_pres ? 'bg-emerald-950/20' : '' ?>" 
                                id="row-<?= $p['id'] ?>"
                                data-name="<?= safe($p['fullname']) ?>" 
                                data-code="<?= safe($p['national_code']) ?>">
                                
                                <td class="p-3.5 text-center text-slate-400 font-mono"><?= $idx + 1 ?></td>
                                
                                <td class="p-3.5">
                                    <div class="font-bold text-white text-sm"><?= safe($p['fullname']) ?></div>
                                    <div class="text-[10px] text-slate-400 mt-0.5">
                                        <?= $p['gender'] === 'male' ? 'آقا' : 'خانم' ?> 
                                        (<?= $p['age_group'] === 'adult' ? 'بزرگسال' : ($p['age_group'] === 'child' ? 'کودک' : 'نوزاد') ?>)
                                    </div>
                                </td>

                                <td class="p-3.5 font-mono text-slate-300">
                                    <div>کدملی: <span class="text-amber-200"><?= safe($p['national_code']) ?></span></div>
                                    <div class="text-[10px] text-slate-400 mt-0.5">پاسپورت: <?= safe($p['passport_number'] ?: '—') ?></div>
                                </td>

                                <td class="p-3.5 font-mono">
                                    <a href="tel:<?= safe($p['phone']) ?>" 
                                       class="inline-flex items-center gap-1.5 bg-amber-500/10 hover:bg-amber-500/20 text-amber-300 border border-amber-500/30 px-2.5 py-1.5 rounded-xl font-bold transition">
                                        <span>📞</span> <?= safe($p['phone']) ?>
                                    </a>
                                </td>

                                <td class="p-3.5 font-mono">
                                    <?php if (!empty($p['emergency_phone'])): ?>
                                        <a href="tel:<?= safe($p['emergency_phone']) ?>" class="text-slate-300 hover:text-white hover:underline text-[11px]">
                                            <?= safe($p['emergency_phone']) ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-slate-500 text-[10px]">ثبت نشده</span>
                                    <?php endif; ?>
                                </td>

                                <td class="p-3.5">
                                    <?php if (!empty($p['medical_notes'])): ?>
                                        <span class="text-[10px] font-bold text-rose-300 bg-rose-950/50 border border-rose-500/30 px-2 py-1 rounded-lg block">
                                            ⚠️ <?= safe($p['medical_notes']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-slate-500 text-[10px]">سالم</span>
                                    <?php endif; ?>
                                </td>

                                <td class="p-3.5 text-center">
                                    <div class="inline-flex rounded-xl p-1 bg-[#02100d] border border-amber-500/20" id="btn-group-<?= $p['id'] ?>">
                                        <button type="button" 
                                                onclick="toggleAttendance(<?= $p['id'] ?>, 1)" 
                                                class="px-3 py-1.5 rounded-lg text-xs font-black transition <?= $is_pres ? 'bg-emerald-600 text-white shadow' : 'text-slate-400 hover:text-white' ?>" 
                                                id="btn-present-<?= $p['id'] ?>">
                                            حاضر ✓
                                        </button>
                                        <button type="button" 
                                                onclick="toggleAttendance(<?= $p['id'] ?>, 0)" 
                                                class="px-3 py-1.5 rounded-lg text-xs font-black transition <?= !$is_pres ? 'bg-rose-700 text-white shadow' : 'text-slate-400 hover:text-white' ?>" 
                                                id="btn-absent-<?= $p['id'] ?>">
                                            غایب ✕
                                        </button>
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
    function toggleAttendance(pilgrimId, status) {
        const btnPresent = document.getElementById('btn-present-' + pilgrimId);
        const btnAbsent = document.getElementById('btn-absent-' + pilgrimId);
        const row = document.getElementById('row-' + pilgrimId);

        const formData = new FormData();
        formData.append('ajax_attendance', '1');
        formData.append('pilgrim_id', pilgrimId);
        formData.append('status', status);

        fetch('leader.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                if (status === 1) {
                    btnPresent.className = 'px-3 py-1.5 rounded-lg text-xs font-black transition bg-emerald-600 text-white shadow';
                    btnAbsent.className = 'px-3 py-1.5 rounded-lg text-xs font-black transition text-slate-400 hover:text-white';
                    row.classList.add('bg-emerald-950/20');
                } else {
                    btnPresent.className = 'px-3 py-1.5 rounded-lg text-xs font-black transition text-slate-400 hover:text-white';
                    btnAbsent.className = 'px-3 py-1.5 rounded-lg text-xs font-black transition bg-rose-700 text-white shadow';
                    row.classList.remove('bg-emerald-950/20');
                }
                updateCounters();
            } else {
                alert('خطا: ' + data.message);
            }
        })
        .catch(err => {
            alert('خطا در ارتباط با سرور.');
        });
    }

    function updateCounters() {
        let presentCount = 0;
        let absentCount = 0;
        const rows = document.querySelectorAll('.pilgrim-row');
        
        rows.forEach(r => {
            if (r.classList.contains('bg-emerald-950/20')) {
                presentCount++;
            } else {
                absentCount++;
            }
        });

        document.getElementById('stat_present').textContent = presentCount;
        document.getElementById('stat_absent').textContent = absentCount;
    }

    // جستجوی زنده بدون رفرش صفحه
    document.getElementById('live_search').addEventListener('input', function(e) {
        const term = e.target.value.trim().toLowerCase();
        const rows = document.querySelectorAll('.pilgrim-row');

        rows.forEach(row => {
            const name = (row.getAttribute('data-name') || '').toLowerCase();
            const code = (row.getAttribute('data-code') || '').toLowerCase();
            if (name.includes(term) || code.includes(term)) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    });
</script>

<?php require_once __DIR__ . '/footer.php'; ?>