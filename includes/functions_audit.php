<?php


if (!function_exists('build_filter_query')) {

    function build_filter_query($filters) {
        $query = '';
        foreach ($filters as $key => $value) {
            if (!empty($value)) {
                $query .= '&' . urlencode($key) . '=' . urlencode($value);
            }
        }
        return $query;
    }
}

if (!function_exists('get_client_ip')) {

    function get_client_ip() {
        $ip_keys = [
            'HTTP_CF_CONNECTING_IP', 
            'HTTP_X_FORWARDED_FOR', 
            'HTTP_X_REAL_IP', 
            'HTTP_X_CLIENT_IP', 
            'HTTP_CLIENT_IP', 
            'REMOTE_ADDR'
        ];
        
        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
         
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        return '0.0.0.0';
    }
}

if (!function_exists('get_user_agent')) {

    function get_user_agent() {
        return isset($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'], 0, 255) : 'Unknown';
    }
}

if (!function_exists('log_action')) {
  
    function log_action($user_id, $action, $details, $role = null, $username = null) {
        global $pdo;
        
        // --- ADMIN EXCLUSION GUARD ---
        $checkRole = $role ?? $_SESSION['role'] ?? $_SESSION['user']['role'] ?? '';
        if (strtoupper($checkRole) === 'ADMIN') {
            return true;
        }

        if (empty($action)) {
            error_log('Audit Log Error: Empty action parameter');
            return false;
        }
        
        if ($username === null) {
            $username = isset($_SESSION['username']) ? $_SESSION['username'] : 'system';
        }
        if ($role === null) {
            $role = isset($_SESSION['role']) ? $_SESSION['role'] : 'guest';
        }
        
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO audit_logs (user_id, username, role, action, details, ip_address, user_agent, created_at) 
                 VALUES (:user_id, :username, :role, :action, :details, :ip_address, :user_agent, NOW())'
            );
            return $stmt->execute([
                ':user_id'   => $user_id,
                ':username'  => $username,
                ':role'      => $role,
                ':action'    => $action,
                ':details'   => $details,
                ':ip_address'=> get_client_ip(),
                ':user_agent'=> get_user_agent()
            ]);
        } catch (PDOException $e) {
            error_log('Audit Log Error: ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('fetch_paginated_audit_logs')) {
  
    function fetch_paginated_audit_logs($page = 1, $per_page = 25, $filters = []) {
        global $pdo;
        
        $page = max(1, (int)$page);
        $per_page = max(1, min(100, (int)$per_page));
        $offset = ($page - 1) * $per_page;
        
        $result = [
            'logs' => [], 
            'total' => 0, 
            'pages' => 0, 
            'current_page' => $page, 
            'per_page' => $per_page
        ];
        
        try {
            $conditions = ["role != 'ADMIN'"];
            $params = [];
            
            if (!empty($filters['role'])) {
                $conditions[] = 'role = :role';
                $params[':role'] = $filters['role'];
            }
            if (!empty($filters['action'])) {
                $conditions[] = 'action LIKE :action';
                $params[':action'] = $filters['action'] . '%';
            }
            if (!empty($filters['from'])) {
                $conditions[] = 'created_at >= :from';
                $params[':from'] = $filters['from'] . ' 00:00:00';
            }
            if (!empty($filters['to'])) {
                $conditions[] = 'created_at <= :to';
                $params[':to'] = $filters['to'] . ' 23:59:59';
            }
            
            $where_clause = 'WHERE ' . implode(' AND ', $conditions);
            
            $count_sql = 'SELECT COUNT(*) as total FROM audit_logs ' . $where_clause;
            $count_stmt = $pdo->prepare($count_sql);
            foreach ($params as $key => $value) {
                $count_stmt->bindValue($key, $value);
            }
            $count_stmt->execute();
            $result['total'] = (int)$count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
            $result['pages'] = $result['total'] > 0 ? ceil($result['total'] / $per_page) : 1;
            
            // Fetch records
            $sql = 'SELECT * FROM audit_logs ' . $where_clause . ' ORDER BY created_at DESC LIMIT :limit OFFSET :offset';
            $stmt = $pdo->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            $result['logs'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Fetch Paginated Audit Logs Error: ' . $e->getMessage());
        }
        
        return $result;
    }
}

if (!function_exists('fetch_recent_audit_logs')) {

    function fetch_recent_audit_logs($limit = 100, $role = null, $action = null, $from = null, $to = null) {
        global $pdo;
        try {
            $sql = "SELECT * FROM audit_logs WHERE role != 'ADMIN'";
            $params = [];
            
            if ($role !== null) { $sql .= ' AND role = :role'; $params[':role'] = $role; }
            if ($action !== null) { $sql .= ' AND action LIKE :action'; $params[':action'] = $action . '%'; }
            if ($from !== null) { $sql .= ' AND created_at >= :from'; $params[':from'] = $from . ' 00:00:00'; }
            if ($to !== null) { $sql .= ' AND created_at <= :to'; $params[':to'] = $to . ' 23:59:59'; }
            
            $sql .= ' ORDER BY created_at DESC LIMIT :limit';
            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
            foreach ($params as $key => $value) { $stmt->bindValue($key, $value); }
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Fetch Recent Audit Logs Error: ' . $e->getMessage());
            return [];
        }
    }
}

if (!function_exists('get_audit_statistics')) {

    function get_audit_statistics($days = 30) {
        global $pdo;
        $stats = [
            'total_logs' => 0, 
            'today_logs' => 0, 
            'by_role' => [], 
            'by_action' => [], 
            'top_users' => [], 
            'failed_logins' => 0
        ];
        
        try {
            // Total Logs (Non-Admin)
            $stmt = $pdo->query("SELECT COUNT(*) as total FROM audit_logs WHERE role != 'ADMIN'");
            $stats['total_logs'] = (int)$stmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            // Today's Activity
            $stmt = $pdo->query("SELECT COUNT(*) as total FROM audit_logs WHERE role != 'ADMIN' AND DATE(created_at) = CURDATE()");
            $stats['today_logs'] = (int)$stmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            // Failed Logins count
            $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM audit_logs WHERE action LIKE :action AND role != 'ADMIN' AND created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)");
            $stmt->execute([':action' => '%failed%', ':days' => $days]);
            $stats['failed_logins'] = (int)$stmt->fetch(PDO::FETCH_ASSOC)['total'];

            // Distribution by Role
            $stmt = $pdo->prepare("SELECT role, COUNT(*) as count FROM audit_logs WHERE role != 'ADMIN' AND created_at >= DATE_SUB(NOW(), INTERVAL :days DAY) GROUP BY role ORDER BY count DESC");
            $stmt->execute([':days' => $days]);
            $stats['by_role'] = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

            // Distribution by Action Type
            $stmt = $pdo->prepare("SELECT action, COUNT(*) as count FROM audit_logs WHERE role != 'ADMIN' AND created_at >= DATE_SUB(NOW(), INTERVAL :days DAY) GROUP BY action ORDER BY count DESC LIMIT 10");
            $stmt->execute([':days' => $days]);
            $stats['by_action'] = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        } catch (PDOException $e) {
            error_log('Get Audit Statistics Error: ' . $e->getMessage());
        }
        
        return $stats;
    }
}

if (!function_exists('format_action_name')) {

    function format_action_name($action) {
        return ucwords(str_replace(['_', '-'], ' ', $action));
    }
}

if (!function_exists('get_action_icon')) {
   
    function get_action_icon($action) {
        $icons = [
            'login' => 'fa-sign-in-alt', 'logout' => 'fa-sign-out-alt', 'failed' => 'fa-exclamation-triangle',
            'user' => 'fa-user', 'pet' => 'fa-paw', 'shelter' => 'fa-building', 'application' => 'fa-paper-plane',
            'report' => 'fa-chart-bar', 'export' => 'fa-file-pdf', 'dashboard' => 'fa-th-large'
        ];
        
        foreach ($icons as $key => $icon) {
            if (stripos($action, $key) !== false) return $icon;
        }
        return 'fa-history';
    }
}

if (!function_exists('get_role_badge_class')) {
 
    function get_role_badge_class($role) {
        $classes = [
            'admin' => 'badge-danger', 
            'shelter' => 'badge-success', 
            'adopter' => 'badge-info', 
            'guest' => 'badge-secondary', 
            'system' => 'badge-dark'
        ];
        return $classes[strtolower($role)] ?? 'badge-secondary';
    }
}

if (!function_exists('cleanup_old_audit_logs')) {

    function cleanup_old_audit_logs($days = 90) {
        global $pdo;
        try {
            $stmt = $pdo->prepare('DELETE FROM audit_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL :days DAY)');
            $stmt->execute([':days' => (int)$days]);
            return $stmt->rowCount();
        } catch (PDOException $e) {
            error_log('Cleanup Old Audit Logs Error: ' . $e->getMessage());
            return 0;
        }
    }
}

if (!function_exists('get_logs_for_export')) {

    function get_logs_for_export($limit = 500, $filters = []) {
        return fetch_recent_audit_logs(
            $limit,
            $filters['role'] ?? null,
            $filters['action'] ?? null,
            $filters['from'] ?? null,
            $filters['to'] ?? null
        );
    }
}
