<?php
/**PAWAdopt - Fixed Authentication System with Audit Logging | */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions_audit.php';
startSession();

$pdo = Database::getInstance()->getConnection();

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {

    // ─────────────────────────── LOGIN ─────────────────────────────
    case 'login':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { jsonError('Method not allowed', 405); break; }

        $username  = trim($_POST['username'] ?? '');
        $password  = $_POST['password'] ?? '';
        $roleInput = strtoupper(trim($_POST['role'] ?? ''));

        if (!$username || !$password || !$roleInput) { 
            jsonError("All fields are required.", 400); 
            break; 
        }

        $db = Database::getInstance();

        $user = $db->fetch(
            "SELECT * FROM users WHERE (username = ? OR email = ?) AND is_active = 1",
            [$username, $username]
        );

        if (!$user) {
            jsonError("Username or email not found."); 
            break;
        }

        // Check password
        if (!password_verify($password, $user['password_hash'])) {
            log_action($user['user_id'], 'failed_login', "Failed login attempt for user: " . $user['username'], strtoupper($user['role']), $user['username']);
            jsonError("Incorrect password."); 
            break;
        }

        // Role verification
        if (strtoupper($user['role']) !== $roleInput) {
            $actualRole = ucfirst(strtolower($user['role']));
            jsonError("Incorrect role selection. This account is registered as: $actualRole"); 
            break;
        }

        $_SESSION['user_id']  = $user['user_id'];
        $_SESSION['username'] = $user['username']; 
        $_SESSION['role']     = strtoupper($user['role']);
        $_SESSION['user']     = [
            'user_id'  => $user['user_id'],
            'username' => $user['username'],
            'role'     => strtoupper($user['role']),
        ];

        // LOG SUCCESSFUL LOGIN
        log_action($user['user_id'], 'login', 'Successful login from ' . $user['username']);

        // Redirection Logic
        $roleDir = strtolower($user['role']);
        if ($roleDir === 'veterinary') $roleDir = 'shelter';
        $redirect = APP_URL . "/pages/{$roleDir}/dashboard.php";

        jsonSuccess(['redirect' => $redirect], 'Login successful!');
        break;

    // ────────────────────────── REGISTER ───────────────────────────
    case 'register':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { jsonError('Method not allowed', 405); break; }
        
        $username = trim($_POST['username'] ?? '');
        $email    = strtolower(trim($_POST['email'] ?? ''));
        $password = $_POST['password'] ?? '';
        $role     = strtoupper(trim($_POST['role'] ?? 'ADOPTER'));

        $allowedRoles = ['ADOPTER', 'SHELTER', 'VETERINARY', 'ADMIN'];

        if (strlen($username) < 3)  { jsonError('Username must be at least 3 characters.'); break; }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { jsonError('Invalid email address.'); break; }
        if (strlen($password) < 8)  { jsonError('Password must be at least 8 characters.'); break; }
        if (!in_array($role, $allowedRoles)) { jsonError('Invalid role selection.'); break; }

        $db = Database::getInstance();
        $exists = $db->fetch("SELECT user_id FROM users WHERE username = ? OR email = ?", [$username, $email]);
        if ($exists) { jsonError('Username or email already registered.'); break; }

        $hash = password_hash($password, PASSWORD_BCRYPT);
        try {
            $db->execute("INSERT INTO users (role, username, email, password_hash) VALUES (?, ?, ?, ?)", [$role, $username, $email, $hash]);
            $userId = (int)$db->lastInsertId();
            
            // Profile Creation
            if ($role === 'ADOPTER') {
                $db->execute("INSERT INTO adopter_profiles (adopter_id) VALUES (?)", [$userId]);
            } elseif ($role === 'SHELTER' || $role === 'VETERINARY') {
                $db->execute("INSERT INTO shelter_profiles (shelter_id, shelter_name) VALUES (?, ?)", [$userId, $username . "'s " . ($role === 'VETERINARY' ? 'Clinic' : 'Shelter')]);
                $db->execute("INSERT INTO shelter_verifications (shelter_id) VALUES (?)", [$userId]);
            }

            // Set Session
            $_SESSION['user_id']  = $userId;
            $_SESSION['username'] = $username;
            $_SESSION['role']     = $role;
            $_SESSION['user']     = ['user_id' => $userId, 'username' => $username, 'email' => $email, 'role' => $role];
            
            // LOG REGISTRATION
            log_action($userId, 'register', 'New account registered: ' . $username);

            $roleDir = strtolower($role);
            if ($roleDir === 'veterinary') $roleDir = 'shelter';
            
            jsonSuccess(['redirect' => APP_URL . "/pages/{$roleDir}/dashboard.php"], 'Account created!');
        } catch (Exception $e) {
            jsonError('Registration failed.');
        }
        break;

    // ─────────────────────────── LOGOUT ────────────────────────────
    case 'logout':
        if (isset($_SESSION['user_id'])) {
            log_action($_SESSION['user_id'], 'logout', 'User logged out');
        }
        
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            jsonSuccess([], 'Logged out.');
        } else {
            header("Location: " . APP_URL . "/index.php");
            exit;
        }
        break;

    default:
        jsonError('Unknown action.', 400);
}
