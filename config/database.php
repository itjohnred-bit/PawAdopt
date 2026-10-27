<?php
/**
 * PawAdopt — database configuration.
 *
 * ALL credentials come from environment variables (Render dashboard, Docker
 * `-e` flags, or a local `.env` file loaded by config/env.php).
 * No credentials are hardcoded in this repository.
 *
 * Required variables:
 *   DB_HOST   MySQL/MariaDB hostname            (e.g. your-db-host.aivencloud.com)
 *   DB_PORT   MySQL/MariaDB port                (default 3306)
 *   DB_NAME   Database name                     (default pawadopt)
 *   DB_USER   Database username                 (required — no default)
 *   DB_PASS   Database password                 (required for production)
 *
 * Optional:
 *   APP_URL      Public base URL of the app (default: derived from the request host)
 *   UPLOAD_PATH  Absolute writable path for uploads (default: ../uploads)
 *   APP_ENV      "production" enables strict mode; anything else = development
 */

require_once __DIR__ . '/env.php';

define('APP_NAME', 'PawAdopt');
define('DB_CHARSET', 'utf8mb4');
define('MAX_FILE_SIZE', 5 * 1024 * 1024);

$pa_env = static function (string $key, ?string $default = ''): ?string {
    $value = getenv($key);
    if ($value === false) {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? null;
    }
    if ($value === null || $value === '') {
        return $default;
    }
    return $value;
};

define('DB_HOST', (string) $pa_env('DB_HOST', ''));           // required — no default host
if (DB_HOST === '') {
    error_log('PawAdopt: DB_HOST is not configured. Set DB_HOST/DB_PORT/DB_NAME/DB_USER/DB_PASS in the environment or a local .env file (see .env.example).');
    http_response_code(500);
    die(json_encode(['error' => 'Database is not configured. Set the DB_* environment variables (see .env.example / DEPLOY.md).']));
}
define('DB_PORT', (string) $pa_env('DB_PORT', '3306'));
define('DB_NAME', (string) $pa_env('DB_NAME', 'pawadopt'));
define('DB_USER', (string) $pa_env('DB_USER', ''));            // no default user
define('DB_PASS', (string) $pa_env('DB_PASS', ''));            // no default password

// Public base URL — must NOT end with a slash.
$pa_app_url = $pa_env('APP_URL', null);
if ($pa_app_url === null || $pa_app_url === '') {
    $pa_scheme = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
        ? 'https' : 'http';
    $pa_host   = $_SERVER['HTTP_HOST'] ?? 'localhost:8080';
    $pa_app_url = $pa_scheme . '://' . $pa_host;
}
define('APP_URL', rtrim((string) $pa_app_url, '/'));

// Writable uploads directory. On Render this must be a mounted disk path
// (free plans have an ephemeral filesystem — see DEPLOY.md).
define('UPLOAD_PATH', rtrim((string) $pa_env('UPLOAD_PATH', __DIR__ . '/../uploads/'), '/') . '/');
define('UPLOAD_URL', APP_URL . '/uploads/');

define('APP_ENV', (string) $pa_env('APP_ENV', 'production'));

class Database {
    private static $instance = null;
    private $pdo;

    private function __construct() {
        if (DB_USER === '' || DB_HOST === '') {
            // Fail fast with a clear, safe message — never echo credentials.
            error_log('PawAdopt: DB_USER is not configured. Set DB_HOST/DB_PORT/DB_NAME/DB_USER/DB_PASS in the environment.');
            http_response_code(500);
            die(json_encode(['error' => 'Database is not configured. Set the DB_* environment variables (see .env.example / DEPLOY.md).']));
        }

        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            // Timeouts so a dead database fails fast instead of hanging requests.
            PDO::ATTR_TIMEOUT            => 5,
        ];
        try {
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // Log internally (server logs only) — return a generic error to clients.
            error_log('PawAdopt database connection failed: ' . $e->getMessage());
            http_response_code(500);
            die(json_encode(['error' => 'Database connection failed. Check your DB_* configuration and that the database server is reachable.']));
        }
    }

    public static function getInstance(): Database {
        if (self::$instance === null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }

    public function getConnection(): PDO {
        return $this->pdo;
    }

    public function query(string $sql, array $params = []): PDOStatement {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public function fetchAll(string $sql, array $params = []): array {
        return $this->query($sql, $params)->fetchAll();
    }

    public function fetch(string $sql, array $params = []): ?array {
        $result = $this->query($sql, $params)->fetch();
        return $result ?: null;
    }

    public function execute(string $sql, array $params = []): bool {
        return $this->query($sql, $params)->rowCount() > 0;
    }

    public function lastInsertId(): string {
        return $this->pdo->lastInsertId();
    }
}

$pdo = Database::getInstance()->getConnection();
?>
