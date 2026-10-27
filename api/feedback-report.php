<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';
if (file_exists(__DIR__ . '/../includes/functions_audit.php')) {
    require_once __DIR__ . '/../includes/functions_audit.php';
}

startSession();

$db = Database::getInstance();
$pdo = $db->getConnection();
$user = getCurrentUser();

header('Content-Type: application/json; charset=UTF-8');

function pa_json(bool $success, string $message, array $data = [], int $status = 200): void {
    http_response_code($status);
    echo json_encode(['success' => $success, 'message' => $message, 'data' => $data]);
    exit;
}

function pa_require_user(?array $user): array {
    if (!$user || empty($user['user_id'])) {
        pa_json(false, 'You must be signed in.', [], 401);
    }
    return $user;
}

function pa_clean(string $value, int $max): string {
    $value = trim($value);
    if (function_exists('mb_substr')) {
        return mb_substr($value, 0, $max);
    }
    return substr($value, 0, $max);
}

function pa_table_columns(PDO $pdo, string $table): array {
    static $cache = [];

    $key = $table;
    if (isset($cache[$key])) {
        return $cache[$key];
    }

    $dbName = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
    if ($dbName === '') {
        return $cache[$key] = [];
    }

    $stmt = $pdo->prepare(
        'SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?'
    );
    $stmt->execute([$dbName, $table]);

    $cols = [];
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $col) {
        $cols[(string) $col] = true;
    }

    return $cache[$key] = $cols;
}

function pa_insert_notification(PDO $pdo, int $targetUserId, string $title, string $message, string $type = 'report_update', string $link = ''): void {
    $columns = pa_table_columns($pdo, 'notifications');
    if (empty($columns)) {
        return;
    }

    $fieldMap = [];

    if (isset($columns['user_id'])) {
        $fieldMap['user_id'] = $targetUserId;
    } elseif (isset($columns['recipient_id'])) {
        $fieldMap['recipient_id'] = $targetUserId;
    } else {
        return;
    }

    if (isset($columns['title'])) {
        $fieldMap['title'] = $title;
    }
    if (isset($columns['message'])) {
        $fieldMap['message'] = $message;
    } elseif (isset($columns['content'])) {
        $fieldMap['content'] = $message;
    } elseif (isset($columns['body'])) {
        $fieldMap['body'] = $message;
    }

    if (isset($columns['type'])) {
        $fieldMap['type'] = $type;
    }
    if (isset($columns['link'])) {
        $fieldMap['link'] = $link;
    } elseif (isset($columns['url'])) {
        $fieldMap['url'] = $link;
    } elseif (isset($columns['action_url'])) {
        $fieldMap['action_url'] = $link;
    }

    if (isset($columns['is_read'])) {
        $fieldMap['is_read'] = 0;
    }
    if (isset($columns['read_status'])) {
        $fieldMap['read_status'] = 'UNREAD';
    }
    if (isset($columns['status'])) {
        $fieldMap['status'] = $fieldMap['status'] ?? 'UNREAD';
    }
    if (isset($columns['created_at'])) {
        $fieldMap['created_at'] = date('Y-m-d H:i:s');
    }
    if (isset($columns['updated_at'])) {
        $fieldMap['updated_at'] = date('Y-m-d H:i:s');
    }

    $names = array_keys($fieldMap);
    if (empty($names)) {
        return;
    }

    $placeholders = implode(', ', array_fill(0, count($names), '?'));
    $sql = 'INSERT INTO notifications (`' . implode('`, `', $names) . '`) VALUES (' . $placeholders . ')';
    $pdo->prepare($sql)->execute(array_values($fieldMap));
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'submit_feedback':
            $user = pa_require_user($user);
            if (strtoupper((string) ($user['role'] ?? '')) !== 'ADOPTER') {
                pa_json(false, 'Only adopters can submit feedback.', [], 403);
            }

            $rating = (int) ($_POST['rating'] ?? 0);
            $title = pa_clean((string) ($_POST['title'] ?? ''), 120);
            $message = pa_clean((string) ($_POST['message'] ?? ''), 800);
            $isPublic = ($_POST['is_public'] ?? '') === '1' ? 1 : 0;

            if ($rating < 1 || $rating > 5) {
                pa_json(false, 'Rating must be between 1 and 5.', [], 422);
            }
            if ($title === '' || $message === '') {
                pa_json(false, 'Title and feedback message are required.', [], 422);
            }

            $db->execute(
                "INSERT INTO user_feedback (user_id, rating, title, message, is_public, created_at)
                 VALUES (?, ?, ?, ?, ?, NOW())",
                [$user['user_id'], $rating, $title, $message, $isPublic]
            );

            if (function_exists('log_action')) {
                log_action((int) $user['user_id'], 'submit_feedback', 'Submitted dashboard feedback');
            }

            $publicEligible = $isPublic && $rating >= 4;
            pa_json(true, $publicEligible
                ? 'Feedback saved. Positive public feedback can appear on the landing page Stories tab.'
                : 'Feedback saved successfully.');

        case 'submit_report':
            $user = pa_require_user($user);
            $category = pa_clean((string) ($_POST['category'] ?? ''), 60);
            $subject = pa_clean((string) ($_POST['subject'] ?? ''), 150);
            $details = pa_clean((string) ($_POST['details'] ?? ''), 1000);
            $sourcePage = pa_clean((string) ($_POST['source_page'] ?? 'dashboard'), 80);
            $role = strtoupper((string) ($user['role'] ?? ''));

            if ($category === '' || $subject === '' || $details === '') {
                pa_json(false, 'Category, subject, and details are required.', [], 422);
            }
            if (!in_array($role, ['ADOPTER', 'SHELTER', 'VETERINARY'], true)) {
                pa_json(false, 'Only adopter or shelter accounts can send reports here.', [], 403);
            }

            $db->execute(
                "INSERT INTO admin_reports (user_id, role, category, subject, details, source_page, status, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, 'PENDING', NOW())",
                [$user['user_id'], $role, $category, $subject, $details, $sourcePage]
            );

            if (function_exists('log_action')) {
                log_action((int) $user['user_id'], 'submit_report', 'Submitted admin report from ' . $sourcePage, $role, (string) ($user['username'] ?? ''));
            }

            pa_json(true, 'Report sent to admins successfully.');

        case 'resolve_report':
            $user = pa_require_user($user);
            if (strtoupper((string) ($user['role'] ?? '')) !== 'ADMIN') {
                pa_json(false, 'Only admins can update reports.', [], 403);
            }

            $reportId = (int) ($_POST['report_id'] ?? 0);
            $status = strtoupper(pa_clean((string) ($_POST['status'] ?? ''), 20));
            $adminNote = pa_clean((string) ($_POST['admin_note'] ?? ''), 500);

            if ($reportId <= 0 || !in_array($status, ['RESOLVED', 'DISMISSED'], true)) {
                pa_json(false, 'Invalid report action.', [], 422);
            }

            $report = $db->fetch(
                "SELECT ar.*, u.username, u.user_id
                 FROM admin_reports ar
                 JOIN users u ON ar.user_id = u.user_id
                 WHERE ar.report_id = ?
                 LIMIT 1",
                [$reportId]
            );

            if (!$report) {
                pa_json(false, 'Report not found.', [], 404);
            }

            $db->execute(
                "UPDATE admin_reports
                 SET status = ?, admin_note = ?, reviewed_by = ?, reviewed_at = NOW()
                 WHERE report_id = ?",
                [$status, $adminNote, $user['user_id'], $reportId]
            );

            $statusLabel = $status === 'RESOLVED' ? 'resolved' : 'dismissed';
            $notificationTitle = 'Your report has been ' . $statusLabel;
            $notificationMessage = 'Admin reviewed your report "' . ($report['subject'] ?? 'Report') . '".';
            if ($adminNote !== '') {
                $notificationMessage .= ' Note: ' . $adminNote;
            }

            $roleDir = strtolower((string) ($report['role'] ?? 'adopter'));
            if ($roleDir === 'veterinary') {
                $roleDir = 'shelter';
            }

            pa_insert_notification(
                $pdo,
                (int) $report['user_id'],
                $notificationTitle,
                $notificationMessage,
                'report_update',
                APP_URL . '/pages/' . $roleDir . '/notifications.php'
            );

            if (function_exists('log_action')) {
                log_action((int) $user['user_id'], 'resolve_report', 'Updated admin report #' . $reportId . ' to ' . $status, 'ADMIN', (string) ($user['username'] ?? ''));
            }

            pa_json(true, 'Report updated successfully.');

        default:
            pa_json(false, 'Unknown action.', [], 400);
    }
} catch (Throwable $e) {
    pa_json(false, 'Request failed. Please check database tables and try again.', ['error' => $e->getMessage()], 500);
}
