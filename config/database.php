<?php
$is_production = isset($_ENV['RENDER']) || getenv('RENDER') || strpos($_SERVER['HTTP_HOST'], 'onrender') !== false;
// Auto-detect base URL for the current environment
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';

// Strip any subfolder path from SCRIPT_NAME (e.g., /pages/adopter/browse.php -> /pages)
$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
// We want the "APP_ROOT" — the directory where index.php lives.
// If the current script is /pages/adopter/browse.php, app root is "" (root) on Render,
// or "/PawAdopt" on local XAMPP if deployed at htdocs/PawAdopt/.

// Heuristic: find "PAWAdopt" or "PawAdopt" in path, else use root
if (preg_match('#(/[Pp][Aa][Ww][Aa]dopt)#', $scriptName, $m)) {
    $appRoot = $m[1];
} else {
    $appRoot = '';  // Production: app at domain root
}

if ($is_production) {

    define('DB_HOST', getenv('DB_HOST'));
    define('DB_PORT', getenv('DB_PORT') ?: '13404');
    define('DB_NAME', getenv('DB_NAME'));
    define('DB_USER', getenv('DB_USER'));
    define('DB_PASS', getenv('DB_PASS'));
    define('SSL_CA', __DIR__ . '/../ca.pem'); 
    define('APP_URL', "$protocol://$host$appRoot");
} else {

    define('DB_HOST', getenv('DB_HOST'));;
    define('DB_PORT', '3307');
    define('DB_NAME', 'pawadopt');
    define('DB_USER', getenv('DB_USER'));
    define('DB_PASS', getenv('DB_PASS'));
    define('SSL_CA', null);
    
}




define('DB_CHARSET', 'utf8mb4');
define('UPLOAD_PATH', $is_production ? '/opt/render/project/uploads/' : __DIR__ . '/../uploads/');
define('UPLOAD_URL', APP_URL . '/uploads/');
class Database {
    private static $instance = null;
    private $pdo;

    private function __construct() {
        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        // IMPORTANT: Aiven requires SSL
        if (SSL_CA && file_exists(SSL_CA)) {
            $options[PDO::MYSQL_ATTR_SSL_CA] = SSL_CA;
            $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
        }

        try {
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            die(json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]));
        }
    }

    public static function getInstance(): Database {
        if (self::$instance === null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }

    public function getConnection(): PDO { return $this->pdo; }
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