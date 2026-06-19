<?php
$is_production = isset($_ENV['RENDER']) || getenv('RENDER') || strpos($_SERVER['HTTP_HOST'], 'onrender') !== false;

if ($is_production) {
    define('DB_HOST', getenv('DB_HOST'));
    define('DB_NAME', getenv('DB_NAME'));
    define('DB_USER', getenv('DB_USER'));
    define('DB_PASS', getenv('DB_PASS'));
    define('DB_CHARSET', 'utf8mb4');

    define('APP_NAME', 'PawAdopt');
    define('APP_URL', 'https://pawadopt-xt8a.onrender.com'); 
    define('UPLOAD_PATH', '/opt/render/project/uploads/');
    define('UPLOAD_URL', APP_URL . '/uploads/');
} else {
    define('DB_HOST', '127.0.0.1:3307');
    define('DB_NAME', 'pawadopt');
    define('DB_USER', 'root');
    define('DB_PASS', '');
    define('DB_CHARSET', 'utf8mb4');

    define('APP_NAME', 'PawAdopt');
    define('APP_URL', 'http://localhost:8080/PawAdopt');
    define('UPLOAD_PATH', __DIR__ . '/../uploads/');
    define('UPLOAD_URL', APP_URL . '/uploads/');
}

define('MAX_FILE_SIZE', 5 * 1024 * 1024);

class Database {
    private static $instance = null;
    private $pdo;

    private function __construct() {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
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