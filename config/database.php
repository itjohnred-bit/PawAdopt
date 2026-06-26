<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;

$envFile = file_exists('/../secrets/.env')
    ? '/etc/secrets/.env'
    : __DIR__ . '/../.env';

$dotenv = Dotenv::createImmutable(dirname($envFile));
$dotenv->safeLoad();

if (is_file($envFile)) {
    $lines = @file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (is_array($lines)) {
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') continue;
            if (!str_contains($line, '=')) continue;
            [$name, $value] = explode('=', $line, 2);
            $name  = trim($name);
            $value = trim($value);
            putenv("$name=$value");
            $_ENV[$name] = $value;
        }
    }
}

/* -------- 2. Render detection -------- */
$is_production = isset($_ENV['RENDER'])
    || getenv('RENDER')
    || (strpos($_SERVER['HTTP_HOST'] ?? '', 'onrender.com') !== false);

/* -------- 3. Protocol + URL helpers (unchanged) -------- */
function detectProtocol(): string {
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') return 'https';
    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) &&
        strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') return 'https';
    if (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) &&
        strtolower($_SERVER['HTTP_X_FORWARDED_SSL']) === 'on') return 'https';
    if (($_SERVER['SERVER_PORT'] ?? 80) == 443) return 'https';
    return 'http';
}

$protocol   = detectProtocol();
$host       = $_SERVER['HTTP_HOST'] ?? 'localhost';
$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';

if (preg_match('#^/(PawAdopt|PAWAdopt|pawadopt)(/|$)#', $scriptName, $m)) {
    $appRoot = '/' . $m[1];
} else {
    $appRoot = '';
}

if (!function_exists('app_url')) {
    function app_url(string $path = ''): string {
        $base = defined('APP_URL') ? APP_URL : '';
        return $base . '/' . ltrim($path, '/');
    }
}
if (!defined('APP_URL')) {
    define('APP_URL', "$protocol://$host$appRoot");
}

/* -------- 4. DB credentials -------- */
if ($is_production) {
    define('DB_HOST', getenv('DB_HOST'));
    define('DB_PORT', getenv('DB_PORT') ?: '13404');
    define('DB_NAME', getenv('DB_NAME'));
    define('DB_USER', getenv('DB_USER'));
    define('DB_PASS', getenv('DB_PASS'));
} else {
    define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
    define('DB_PORT', '3307');
    define('DB_NAME', 'pawadopt');
    define('DB_USER', getenv('DB_USER') ?: 'root');
    define('DB_PASS', getenv('DB_PASS') ?: '');
}

/* -------- 5. SSL_CA — env-overridable, multi-path, safe if absent -------- */
$sslCand = [
    getenv('MYSQL_SSL_CA') ?: null,
    '/etc/secrets/ca.pem',                                   // Render Secret Files
    '/var/www/html/ca.pem',                                  // side-loaded
    __DIR__ . '/../ca.pem',
];
$sslCa = null;
foreach ($sslCand as $c) {
    if (is_string($c) && $c !== '' && file_exists($c)) { $sslCa = $c; break; }
}
define('SSL_CA', $sslCa);

define('DB_CHARSET', 'utf8mb4');
define('UPLOAD_PATH', $is_production ? '/opt/render/project/uploads/' : __DIR__ . '/../uploads/');
define('UPLOAD_URL',  APP_URL . '/uploads/');

/* -------- 6. Database singleton -------- */
class Database {
    private static $instance = null;
    private $pdo;

    private function __construct() {
        $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT
             . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        if (is_string(SSL_CA) && SSL_CA !== '' && file_exists(SSL_CA)) {
            $options[PDO::MYSQL_ATTR_SSL_CA]               = SSL_CA;
            $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = true;
        }

        try {
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log('Database connection failed: ' . $e->getMessage());
            if (getenv('APP_DEBUG') === '1') {
                die(json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]));
            }
            http_response_code(500);
            die('Database connection failed.');
        }
    }

    public static function getInstance(): Database {
        if (self::$instance === null) self::$instance = new Database();
        return self::$instance;
    }
    public function getConnection(): PDO                 { return $this->pdo; }
    public function query(string $sql, array $p = []): PDOStatement {
        $stmt = $this->pdo->prepare($sql); $stmt->execute($p); return $stmt;
    }
    public function fetchAll(string $sql, array $p = []): array    { return $this->query($sql, $p)->fetchAll(); }
    public function fetch(string $sql, array $p = []): ?array      { return $this->query($sql, $p)->fetch() ?: null; }
    public function execute(string $sql, array $p = []): bool      { return $this->query($sql, $p)->rowCount() > 0; }
    public function lastInsertId(): string                         { return $this->pdo->lastInsertId(); }
}

$pdo = Database::getInstance()->getConnection();
