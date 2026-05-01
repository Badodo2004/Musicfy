<?php
// auth.php - Enhanced Authentication System with Authorization
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Start session for better authentication control
session_start();

// Handle preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Database configuration
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'musicfy');
define('DB_USER', 'root');
define('DB_PASS', '');

// Security configuration
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_TIME', 900); // 15 minutes
define('SESSION_TIMEOUT', 1800); // 30 minutes
define('MIN_PASSWORD_LENGTH', 8);

class AuthSystem {
    private $pdo;
    
    public function __construct() {
        try {
            $this->pdo = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
            
            $this->testConnection();
            
        } catch (PDOException $e) {
            $this->sendError('Database connection failed: ' . $e->getMessage());
        }
    }
    
    private function testConnection() {
        try {
            $this->pdo->query("SELECT 1 FROM user LIMIT 1");
        } catch (PDOException $e) {
            if ($e->getCode() == '42S02') {
                $this->createTables();
            } else {
                throw $e;
            }
        }
    }
    
    private function createTables() {
        try {
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS user (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    username VARCHAR(50) UNIQUE NOT NULL,
                    email VARCHAR(100) UNIQUE NOT NULL,
                    password_hash VARCHAR(255) NOT NULL,
                    full_name VARCHAR(100),
                    created_at DATETIME NOT NULL,
                    updated_at DATETIME NOT NULL,
                    last_login DATETIME,
                    login_attempts INT DEFAULT 0,
                    is_locked TINYINT DEFAULT 0,
                    locked_until DATETIME
                )
            ");
            
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS user_sessions (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    user_id INT NOT NULL,
                    session_token VARCHAR(64) UNIQUE NOT NULL,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    expires_at DATETIME NOT NULL,
                    ip_address VARCHAR(45),
                    user_agent VARCHAR(255),
                    FOREIGN KEY (user_id) REFERENCES user(id) ON DELETE CASCADE,
                    INDEX idx_session_token (session_token),
                    INDEX idx_expires_at (expires_at)
                )
            ");
        } catch (PDOException $e) {
            error_log("Table creation error: " . $e->getMessage());
            throw new Exception('Failed to initialize database tables');
        }
    }
    
    public function register($username, $email, $password, $fullName) {
        if (empty($username) || empty($email) || empty($password)) {
            $this->sendError('All fields are required');
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->sendError('Invalid email format');
        }
        
        if (strlen($password) < MIN_PASSWORD_LENGTH) {
            $this->sendError('Password must be at least ' . MIN_PASSWORD_LENGTH . ' characters long');
        }
        
        if ($this->userExists($username, $email)) {
            $this->sendError('Username or email already exists');
        }
        
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $currentTime = date('Y-m-d H:i:s');
        
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO user (username, email, password_hash, full_name, created_at, updated_at) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$username, $email, $passwordHash, $fullName, $currentTime, $currentTime]);
            
            $userId = $this->pdo->lastInsertId();
            
            return [
                'success' => true,
                'message' => 'Registration successful! You can now login.',
                'user_id' => $userId
            ];
            
        } catch (PDOException $e) {
            error_log("Registration error: " . $e->getMessage());
            
            if ($e->getCode() == '23000') {
                $this->sendError('Username or email already exists');
            } else {
                $this->sendError('Registration failed. Please try again.');
            }
        }
    }
    
    public function login($username, $password) {
        if (empty($username) || empty($password)) {
            $this->sendError('Username and password are required');
        }
        
        $user = $this->getUserByUsername($username);
        if (!$user) {
            $this->recordFailedAttempt($username);
            $this->sendError('Invalid username or password');
        }
        
        if ($this->isAccountLocked($user)) {
            $this->sendError('Account temporarily locked. Please try again later.');
        }
        
        if (password_verify($password, $user['password_hash'])) {
            if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
                $this->updatePasswordHash($user['id'], $password);
            }
            
            $this->handleSuccessfulLogin($user['id']);
            $sessionToken = $this->createSession($user['id']);
            
            // Set session variable for server-side authentication
            $_SESSION['user_authenticated'] = true;
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            
            return [
                'success' => true,
                'message' => 'Login successful',
                'session_token' => $sessionToken,
                'user' => [
                    'id' => $user['id'],
                    'username' => $user['username'],
                    'email' => $user['email'],
                    'full_name' => $user['full_name']
                ],
                'redirect_url' => 'dashboard.php' // Added redirect URL
            ];
        } else {
            $this->recordFailedAttempt($username);
            $remaining = MAX_LOGIN_ATTEMPTS - ($user['login_attempts'] + 1);
            
            if ($remaining <= 0) {
                $this->sendError('Account has been locked due to too many failed attempts. Please try again in 30 minutes.');
            } else {
                $this->sendError('Invalid username or password. ' . $remaining . ' attempt(s) remaining.');
            }
        }
    }
    
    public function validateSession($sessionToken) {
        try {
            $this->cleanupExpiredSessions();
            
            $stmt = $this->pdo->prepare("
                SELECT us.*, u.username, u.email, u.full_name 
                FROM user_sessions us 
                JOIN user u ON us.user_id = u.id 
                WHERE us.session_token = ? AND us.expires_at > NOW()
            ");
            $stmt->execute([$sessionToken]);
            $session = $stmt->fetch();
            
            if ($session) {
                // Update session expiry
                $newExpiry = date('Y-m-d H:i:s', time() + SESSION_TIMEOUT);
                $stmt = $this->pdo->prepare("
                    UPDATE user_sessions SET expires_at = ? WHERE session_token = ?
                ");
                $stmt->execute([$newExpiry, $sessionToken]);
                
                // Update server session
                $_SESSION['user_authenticated'] = true;
                $_SESSION['user_id'] = $session['user_id'];
                $_SESSION['username'] = $session['username'];
                
                return [
                    'valid' => true,
                    'user' => [
                        'id' => $session['user_id'],
                        'username' => $session['username'],
                        'email' => $session['email'],
                        'full_name' => $session['full_name']
                    ]
                ];
            }
            
            // Clear server session if invalid
            $this->clearServerSession();
            return ['valid' => false];
        } catch (PDOException $e) {
            error_log("Session validation error: " . $e->getMessage());
            $this->clearServerSession();
            return ['valid' => false];
        }
    }
    
    public function logout($sessionToken) {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM user_sessions WHERE session_token = ?");
            $stmt->execute([$sessionToken]);
            
            // Clear server session
            $this->clearServerSession();
            
            return true;
        } catch (PDOException $e) {
            error_log("Logout error: " . $e->getMessage());
            // Still clear server session even if DB operation fails
            $this->clearServerSession();
            return false;
        }
    }
    
    // NEW: Force logout all user sessions
    public function forceLogoutAllSessions($userId) {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM user_sessions WHERE user_id = ?");
            $stmt->execute([$userId]);
            
            // Clear server session if it's the current user
            if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $userId) {
                $this->clearServerSession();
            }
            
            return true;
        } catch (PDOException $e) {
            error_log("Force logout error: " . $e->getMessage());
            return false;
        }
    }
    
    // NEW: Check if user is authenticated (for dashboard access)
    public function isUserAuthenticated() {
        return isset($_SESSION['user_authenticated']) && $_SESSION['user_authenticated'] === true;
    }
    
    // NEW: Clear server session
    private function clearServerSession() {
        $_SESSION = array();
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        session_destroy();
    }
    
    private function userExists($username, $email) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT id FROM user WHERE username = ? OR email = ?
            ");
            $stmt->execute([$username, $email]);
            return $stmt->fetch() !== false;
        } catch (PDOException $e) {
            error_log("User exists check error: " . $e->getMessage());
            return false;
        }
    }
    
    private function getUserByUsername($username) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT id, username, email, password_hash, full_name, login_attempts, is_locked, locked_until 
                FROM user 
                WHERE username = ? OR email = ?
            ");
            $stmt->execute([$username, $username]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("Get user error: " . $e->getMessage());
            return false;
        }
    }
    
    private function isAccountLocked($user) {
        if (!$user['is_locked']) {
            return false;
        }
        
        if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
            return true;
        }
        
        $this->handleSuccessfulLogin($user['id']);
        return false;
    }
    
    private function recordFailedAttempt($username) {
        try {
            $currentTime = date('Y-m-d H:i:s');
            $lockUntil = date('Y-m-d H:i:s', time() + LOCKOUT_TIME);
            
            $stmt = $this->pdo->prepare("
                UPDATE user 
                SET login_attempts = login_attempts + 1,
                    is_locked = CASE 
                        WHEN login_attempts + 1 >= ? THEN 1 
                        ELSE is_locked 
                    END,
                    locked_until = CASE 
                        WHEN login_attempts + 1 >= ? THEN ? 
                        ELSE locked_until 
                    END,
                    updated_at = ?
                WHERE username = ? OR email = ?
            ");
            $stmt->execute([MAX_LOGIN_ATTEMPTS, MAX_LOGIN_ATTEMPTS, $lockUntil, $currentTime, $username, $username]);
        } catch (PDOException $e) {
            error_log("Record failed attempt error: " . $e->getMessage());
        }
    }
    
    private function handleSuccessfulLogin($userId) {
        try {
            $currentTime = date('Y-m-d H:i:s');
            $stmt = $this->pdo->prepare("
                UPDATE user 
                SET login_attempts = 0, 
                    is_locked = 0, 
                    locked_until = NULL,
                    last_login = ?,
                    updated_at = ? 
                WHERE id = ?
            ");
            $stmt->execute([$currentTime, $currentTime, $userId]);
        } catch (PDOException $e) {
            error_log("Handle successful login error: " . $e->getMessage());
        }
    }
    
    private function updatePasswordHash($userId, $password) {
        try {
            $newHash = password_hash($password, PASSWORD_DEFAULT);
            $currentTime = date('Y-m-d H:i:s');
            $stmt = $this->pdo->prepare("UPDATE user SET password_hash = ?, updated_at = ? WHERE id = ?");
            $stmt->execute([$newHash, $currentTime, $userId]);
        } catch (PDOException $e) {
            error_log("Update password hash error: " . $e->getMessage());
        }
    }
    
    private function createSession($userId) {
        try {
            $sessionToken = bin2hex(random_bytes(32));
            $expiresAt = date('Y-m-d H:i:s', time() + SESSION_TIMEOUT);
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
            
            $this->cleanupExpiredSessions();
            
            $stmt = $this->pdo->prepare("
                INSERT INTO user_sessions (user_id, session_token, expires_at, ip_address, user_agent) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$userId, $sessionToken, $expiresAt, $ipAddress, $userAgent]);
            
            return $sessionToken;
        } catch (PDOException $e) {
            error_log("Create session error: " . $e->getMessage());
            return bin2hex(random_bytes(32));
        }
    }
    
    private function cleanupExpiredSessions() {
        try {
            $currentTime = date('Y-m-d H:i:s');
            $stmt = $this->pdo->prepare("DELETE FROM user_sessions WHERE expires_at < ?");
            $stmt->execute([$currentTime]);
        } catch (PDOException $e) {
            error_log("Cleanup sessions error: " . $e->getMessage());
        }
    }
    
    private function sendError($message) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $message]);
        exit();
    }
}

// Main execution
try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input || !isset($input['action'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid input data or action is required']);
            exit();
        }
        
        $authSystem = new AuthSystem();
        
        switch ($input['action']) {
            case 'register':
                if (!isset($input['username']) || !isset($input['email']) || !isset($input['password'])) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'All fields are required']);
                    exit();
                }
                
                $fullName = isset($input['full_name']) ? $input['full_name'] : '';
                $result = $authSystem->register(
                    trim($input['username']),
                    trim($input['email']),
                    $input['password'],
                    trim($fullName)
                );
                break;
                
            case 'login':
                if (!isset($input['username']) || !isset($input['password'])) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Username and password are required']);
                    exit();
                }
                
                $result = $authSystem->login(
                    trim($input['username']),
                    $input['password']
                );
                break;
                
            case 'validate_session':
                $sessionToken = $input['session_token'] ?? '';
                if (empty($sessionToken)) {
                    echo json_encode(['valid' => false, 'message' => 'No session token']);
                    exit();
                }
                $result = $authSystem->validateSession($sessionToken);
                break;
                
            case 'logout':
                $sessionToken = $input['session_token'] ?? '';
                if (!empty($sessionToken)) {
                    $authSystem->logout($sessionToken);
                }
                $result = ['success' => true, 'message' => 'Logged out successfully', 'redirect_url' => 'index.php'];
                break;
                
            case 'force_logout_all':
                $userId = $input['user_id'] ?? '';
                $sessionToken = $input['session_token'] ?? '';
                if (!empty($userId) && !empty($sessionToken)) {
                    // Validate session first
                    $sessionValid = $authSystem->validateSession($sessionToken);
                    if ($sessionValid['valid']) {
                        $authSystem->forceLogoutAllSessions($userId);
                        $result = ['success' => true, 'message' => 'All sessions logged out'];
                    } else {
                        $result = ['success' => false, 'message' => 'Invalid session'];
                    }
                } else {
                    $result = ['success' => false, 'message' => 'User ID and session token required'];
                }
                break;
                
            case 'check_auth':
                // Check if user is authenticated (for dashboard access)
                $result = ['authenticated' => $authSystem->isUserAuthenticated()];
                break;
                
            default:
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Invalid action']);
                exit();
        }
        
        echo json_encode($result);
    } else {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    }
} catch (Exception $e) {
    error_log("Main execution error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error occurred. Please try again.']);
}
?>