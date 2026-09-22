<?php
// موتور مرکزی ارسال پیامک کاروان زیارتی

function send_sms_notification($conn, $phone, $fullname, $message, $event_type = 'system') {
    if (empty($phone) || empty($message)) return false;

    // تمیزکاری شماره موبایل (تبدیل به 09xxxxxxxx)
    $clean_phone = preg_replace('/[^0-9]/', '', $phone);
    if (substr($clean_phone, 0, 2) === '98') {
        $clean_phone = '0' . substr($clean_phone, 2);
    }

    $settings = get_settings($conn);
    $sms_provider = $settings['sms_provider'] ?? 'none'; // ippanel, kavenegar, melipayamak, none
    $api_key = $settings['sms_api_key'] ?? '';
    $sender_line = $settings['sms_sender_line'] ?? '';

    $is_sent = false;

    // در صورت وجود API Key واقعی:
    if (!empty($api_key) && $sms_provider !== 'none') {
        
        // ۱. آی‌پی‌پنل / فراز اس‌ام‌اس (IPPanel / FarazSMS)
        if ($sms_provider === 'ippanel') {
            $url = "https://ippanel.com/services.jspd";
            $param = [
                'uname' => $settings['sms_username'] ?? '',
                'pass' => $settings['sms_password'] ?? '',
                'from' => $sender_line,
                'message' => $message,
                'to' => json_encode([$clean_phone]),
                'op' => 'send'
            ];
            $handler = curl_init($url);
            curl_setopt($handler, CURLOPT_CUSTOMREQUEST, "POST");
            curl_setopt($handler, CURLOPT_POSTFIELDS, $param);
            curl_setopt($handler, CURLOPT_RETURNTRANSFER, true);
            $response = curl_exec($handler);
            curl_close($handler);
            $is_sent = true;
        }

        // ۲. کاوه‌نگار (Kavenegar)
        elseif ($sms_provider === 'kavenegar') {
            $url = "https://api.kavenegar.com/v1/{$api_key}/sms/send.json";
            $data = [
                'receptor' => $clean_phone,
                'message' => $message,
                'sender' => $sender_line
            ];
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $res = curl_exec($ch);
            curl_close($ch);
            $is_sent = true;
        }
    } else {
        // حالت توسعه / ذخیره داخلی در دیتابیس بدون مسدود شدن سایت
        $is_sent = true;
    }

    // ثبت در تاریخچه پیامک‌ها
    try {
        $stmt = $conn->prepare("INSERT INTO sms_logs (receiver_phone, receiver_name, message, event_type, status) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$clean_phone, $fullname, $message, $event_type, ($is_sent ? 'sent' : 'failed')]);
    } catch (Exception $e) {}

    return $is_sent;
}