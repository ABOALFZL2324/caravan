<?php
// فراخوانی بررسی نشست و احراز هویت خادمین
require_once __DIR__ . '/auth.php';

// فقط مدیر ارشد و مسئول پذیرش مجاز به تغییر وضعیت زائران هستند
require_roles(['super_admin', 'registrar']);

header('Content-Type: application/json; charset=utf-8');

// پردازش تغییر وضعیت پرونده زائر
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $pilgrim_id = (int)($_POST['pilgrim_id'] ?? 0);
    $status = safe($_POST['status'] ?? '');
    $rejection_reason = trim($_POST['rejection_reason'] ?? '');

    $allowed_statuses = ['pending', 'verified', 'rejected', 'cancelled'];

    if ($pilgrim_id <= 0 || !in_array($status, $allowed_statuses, true)) {
        echo json_encode([
            'success' => false,
            'message' => 'شناسه زائر یا وضعیت انتخابی نامعتبر است.'
        ]);
        exit;
    }

    try {
        // واکشی اطلاعات قبلی پرونده زائر
        $stmt_old = $conn->prepare("SELECT id, status, caravan_id FROM pilgrims WHERE id = ? LIMIT 1");
        $stmt_old->execute([$pilgrim_id]);
        $old_data = $stmt_old->fetch();

        if (!$old_data) {
            echo json_encode([
                'success' => false,
                'message' => 'پرونده زائر مورد نظر در سیستم یافت نشد.'
            ]);
            exit;
        }

        $old_status = $old_data['status'];
        $caravan_id = (int)$old_data['caravan_id'];

        $conn->beginTransaction();

        // اگر وضعیت «رد مدارک» بود، دلیل رد ثبت می‌شود وگرنه null قرار می‌گیرد
        $final_reason = ($status === 'rejected' && !empty($rejection_reason)) ? safe($rejection_reason) : null;

        // به‌روزرسانی وضعیت و خاموش کردن نشان هشدار ویرایش
        $stmt_up = $conn->prepare("UPDATE pilgrims SET 
            status = ?, 
            rejection_reason = ?, 
            has_update_alert = 0 
            WHERE id = ?");
        $stmt_up->execute([$status, $final_reason, $pilgrim_id]);

        // مدیریت ظرفیت کاروان:
        // ۱. اگر پرونده کنسل شد و قبلاً کنسل نبوده، ظرفیت به کاروان برگردانده شود
        if ($old_status !== 'cancelled' && $status === 'cancelled') {
            $conn->prepare("UPDATE caravans SET remaining_capacity = remaining_capacity + 1 WHERE id = ?")->execute([$caravan_id]);
        }
        // ۲. اگر پرونده از حالت کنسل به حالت دیگری رفت، یک ظرفیت کسر شود
        elseif ($old_status === 'cancelled' && $status !== 'cancelled') {
            $conn->prepare("UPDATE caravans SET remaining_capacity = GREATEST(0, remaining_capacity - 1) WHERE id = ?")->execute([$caravan_id]);
        }

        $conn->commit();

        echo json_encode([
            'success' => true,
            'message' => 'وضعیت پرونده زائر با موفقیت به‌روزرسانی شد.',
            'status' => $status
        ]);
        exit;

    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        echo json_encode([
            'success' => false,
            'message' => 'خطای دیتابیس در به‌روزرسانی پرونده: ' . $e->getMessage()
        ]);
        exit;
    }
}

// در صورت ارسال درخواست ناشناخته
echo json_encode([
    'success' => false,
    'message' => 'درخواست ارسالی نامعتبر است.'
]);
exit;