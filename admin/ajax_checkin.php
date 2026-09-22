<?php
require_once __DIR__ . '/auth.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'درخواست غیرمجاز']);
    exit;
}

$pilgrim_id = (int)($_POST['pilgrim_id'] ?? 0);
$checked_in = isset($_POST['is_checked_in']) ? (int)$_POST['is_checked_in'] : 0;

if (!$pilgrim_id) {
    echo json_encode(['success' => false, 'message' => 'شناسه زائر نامعتبر است.']);
    exit;
}

// بررسی دسترسی مسئول کاروان به این زائر
if ($_SESSION['admin_role'] === 'leader' && !empty($_SESSION['admin_caravan_id'])) {
    $stmt_check = $conn->prepare("SELECT id FROM pilgrims WHERE id = ? AND caravan_id = ?");
    $stmt_check->execute([$pilgrim_id, $_SESSION['admin_caravan_id']]);
    if (!$stmt_check->fetch()) {
        echo json_encode(['success' => false, 'message' => 'شما دسترسی به این زائر ندارید.']);
        exit;
    }
}

try {
    $stmt = $conn->prepare("UPDATE pilgrims SET is_checked_in = ? WHERE id = ?");
    $stmt->execute([$checked_in, $pilgrim_id]);

    echo json_encode([
        'success' => true, 
        'is_checked_in' => $checked_in,
        'message' => $checked_in ? 'حضور زائر تأیید شد.' : 'عدم حضور زائر ثبت شد.'
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'خطا در ثبت وضعیت.']);
}