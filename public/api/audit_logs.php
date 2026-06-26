<?php


header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Start session
session_start();

if (!isset($_SESSION['user']) || strtoupper($_SESSION['user']['role'] ?? '') !== 'ADMIN') {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized access. Admin privileges required.'
    ]);
    exit;
}

// Include database and functions
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions_audit.php';

// Get action parameter
$action = isset($_GET['action']) ? $_GET['action'] : 'fetch';


if ($action === 'fetch') {
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $per_page = isset($_GET['per_page']) ? max(1, min(100, (int)$_GET['per_page'])) : 50;
    
    // Build filters
    $filters = [];
    if (!empty($_GET['role'])) $filters['role'] = $_GET['role'];
    if (!empty($_GET['action'])) $filters['action'] = $_GET['action'];
    if (!empty($_GET['from'])) $filters['from'] = $_GET['from'];
    if (!empty($_GET['to'])) $filters['to'] = $_GET['to'];
    
    // Fetch logs
    $result = fetch_paginated_audit_logs($page, $per_page, $filters);
    

    foreach ($result['logs'] as &$log) {
    $log['action_formatted'] = function_exists('format_action_name') ? format_action_name($log['action']) : ucfirst($log['action']);
    $log['action_icon'] = function_exists('get_action_icon') ? get_action_icon($log['action']) : '';
    $log['role_badge'] = function_exists('get_role_badge_class') ? get_role_badge_class($log['role']) : '';
    $log['timestamp_formatted'] = date('M d, Y g:i A', strtotime($log['created_at']));
}
unset($log); 
    
    echo json_encode([
        'success' => true,
        'logs' => $result['logs'],
        'pagination' => [
            'current_page' => $result['current_page'],
            'per_page' => $result['per_page'],
            'total' => $result['total'],
            'total_pages' => $result['pages']
        ]
    ]);
    exit;
}


if ($action === 'quick_stats') {
    try {
        // Today's activity
        $stmt = $pdo->query('SELECT COUNT(*) as count FROM audit_logs WHERE DATE(created_at) = CURDATE()');
        $today_count = (int)$stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        // Total Logs
        $stmt = $pdo->query('SELECT COUNT(*) as count FROM audit_logs');
        $total_count = (int)$stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        // Failed logins today
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM audit_logs WHERE action LIKE '%failed%' AND DATE(created_at) = CURDATE()");
        $failed_today = (int)$stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        echo json_encode([
            'success' => true,
            'quick_stats' => [
                'today_count' => $today_count,
                'total_count' => $total_count,
                'failed_today' => $failed_today,
                'filtered_count' => 0
            ]
        ]);
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}


if ($action === 'action_types') {
    try {
        $stmt = $pdo->query('SELECT DISTINCT action FROM audit_logs ORDER BY action ASC');
        $actions = $stmt->fetchAll(PDO::FETCH_COLUMN);
        echo json_encode(['success' => true, 'actions' => $actions]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to fetch action types']);
    }
    exit;
}

if ($action === 'export_data') {
    $limit = isset($_GET['limit']) ? min((int)$_GET['limit'], 500) : 500;
    $logs = fetch_recent_audit_logs($limit);
    
    echo json_encode([
        'success' => true,
        'data' => $logs,
        'generated_at' => date('Y-m-d H:i:s')
    ]);
    exit;
}

// Default response
http_response_code(404);
echo json_encode(['success' => false, 'message' => 'Unknown action']);
?>
