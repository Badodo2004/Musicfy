<?php
// register.php - Musicfy Registration System
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Handle preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Database configuration for your setup
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'musicfy');
define('DB_USER', 'root');  // Change if different
define('DB_PASS', '');      // Change if you have a password

class MusicfyRegistration {
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
        } catch (PDOException $e) {
            $this->sendError('Database connection failed: ' . $e->getMessage());
        }
    }
    
    public function registerUser($username, $email, $password, $fullName) {
        // Input validation
        if (empty($username) || empty($email) || empty($password)) {
            $this->sendError('Username, email, and password are required');
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->sendError('Invalid email format');
        }
        
        if (strlen($password) < 8) {
            $this->sendError('Password must be at least 8 characters long');
        }
        
        if (!$this->isStrongPassword($password)) {
            $this->sendError('Password must include uppercase letters, lowercase letters, numbers, and special characters');
        }
        
        // Check if user already exists
        if ($this->userExists($username, $email)) {
            $this->sendError('Username or email already exists');
        }
        
        // Create user
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO users (username, email, password_hash, full_name) 
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$username, $email, $passwordHash, $fullName]);
            
            $userId = $this->pdo->lastInsertId();
            
            return [
                'success' => true,
                'message' => 'Registration successful! Welcome to Musicfy.',
                'user_id' => $userId,
                'username' => $username
            ];
            
        } catch (PDOException $e) {
            error_log("Registration error: " . $e->getMessage());
            $this->sendError('Registration failed. Please try again.');
        }
    }
    
    private function userExists($username, $email) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT id FROM users WHERE username = ? OR email = ?
            ");
            $stmt->execute([$username, $email]);
            return $stmt->fetch() !== false;
        } catch (PDOException $e) {
            error_log("User exists check error: " . $e->getMessage());
            return false;
        }
    }
    
    private function isStrongPassword($password) {
        $hasUppercase = preg_match('/[A-Z]/', $password);
        $hasLowercase = preg_match('/[a-z]/', $password);
        $hasNumber = preg_match('/[0-9]/', $password);
        $hasSpecial = preg_match('/[^A-Za-z0-9]/', $password);
        
        return $hasUppercase && $hasLowercase && $hasNumber && $hasSpecial;
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
        
        if (!isset($input['username']) || !isset($input['email']) || !isset($input['password'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Username, email, and password are required']);
            exit();
        }
        
        $fullName = isset($input['full_name']) ? $input['full_name'] : '';
        
        $registration = new MusicfyRegistration();
        $result = $registration->registerUser(
            $input['username'],
            $input['email'],
            $input['password'],
            $fullName
        );
        
        echo json_encode($result);
        
    } else {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    }
} catch (Exception $e) {
    error_log("Main execution error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Musicfy - Create Your Account</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            color: #fff;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .register-container {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 40px;
            width: 100%;
            max-width: 500px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .logo {
            text-align: center;
            margin-bottom: 30px;
        }

        .logo i {
            font-size: 48px;
            color: #1DB954;
            margin-bottom: 10px;
        }

        .logo h1 {
            font-size: 32px;
            font-weight: 700;
            color: #1DB954;
        }

        .logo p {
            color: rgba(255, 255, 255, 0.7);
            margin-top: 5px;
        }

        .form-group {
            margin-bottom: 20px;
            position: relative;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: rgba(255, 255, 255, 0.9);
        }

        .input-with-icon {
            position: relative;
        }

        .input-with-icon i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: rgba(255, 255, 255, 0.6);
        }

        .form-control {
            width: 100%;
            padding: 15px 15px 15px 45px;
            background: rgba(255, 255, 255, 0.1);
            border: 2px solid rgba(255, 255, 255, 0.2);
            border-radius: 10px;
            color: white;
            font-size: 16px;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: #1DB954;
            background: rgba(255, 255, 255, 0.15);
            box-shadow: 0 0 0 3px rgba(29, 185, 84, 0.1);
        }

        .password-toggle {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: rgba(255, 255, 255, 0.6);
            cursor: pointer;
            font-size: 16px;
            transition: color 0.3s ease;
        }

        .password-toggle:hover {
            color: #1DB954;
        }

        .security-indicators {
            margin-top: 10px;
        }

        .security-strength {
            height: 4px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 2px;
            margin-bottom: 5px;
            overflow: hidden;
        }

        .strength-bar {
            height: 100%;
            width: 0%;
            border-radius: 2px;
            transition: all 0.3s ease;
        }

        .strength-weak { background: #ff4757; }
        .strength-fair { background: #ffa502; }
        .strength-good { background: #2ed573; }
        .strength-strong { background: #1DB954; }

        .strength-text {
            font-size: 12px;
            color: rgba(255, 255, 255, 0.7);
        }

        .password-requirements {
            font-size: 12px;
            color: rgba(255, 255, 255, 0.6);
            margin-top: 5px;
        }

        .requirement {
            display: flex;
            align-items: center;
            gap: 5px;
            margin-bottom: 2px;
        }

        .requirement.met {
            color: #2ed573;
        }

        .requirement.unmet {
            color: #ff4757;
        }

        .btn {
            width: 100%;
            padding: 15px;
            background: #1DB954;
            border: none;
            border-radius: 10px;
            color: white;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .btn:hover {
            background: #1ed760;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(29, 185, 84, 0.3);
        }

        .btn:active {
            transform: translateY(0);
        }

        .btn:disabled {
            background: #666;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .alert {
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            display: none;
            animation: slideDown 0.3s ease;
        }

        .alert-error {
            background: rgba(255, 71, 87, 0.2);
            border: 1px solid rgba(255, 71, 87, 0.3);
            color: #ff6b81;
        }

        .alert-success {
            background: rgba(46, 213, 115, 0.2);
            border: 1px solid rgba(46, 213, 115, 0.3);
            color: #2ed573;
        }

        .login-link {
            text-align: center;
            margin-top: 20px;
        }

        .login-link a {
            color: #1DB954;
            text-decoration: none;
            transition: color 0.3s ease;
        }

        .login-link a:hover {
            color: #1ed760;
            text-decoration: underline;
        }

        .security-features {
            margin-top: 25px;
            padding: 15px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 10px;
            border-left: 4px solid #1DB954;
        }

        .security-features h3 {
            font-size: 14px;
            margin-bottom: 10px;
            color: #1DB954;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .security-features ul {
            list-style: none;
            font-size: 12px;
            color: rgba(255, 255, 255, 0.7);
        }

        .security-features li {
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .security-features i {
            color: #1DB954;
            font-size: 10px;
        }

        @media (max-width: 480px) {
            .register-container {
                padding: 30px 20px;
            }
            
            .logo h1 {
                font-size: 28px;
            }
        }

        .shake {
            animation: shake 0.5s ease-in-out;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-10px); }
            75% { transform: translateX(10px); }
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .loading-spinner {
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="logo">
            <i class="fab fa-spotify"></i>
            <h1>Musicfy</h1>
            <p>Create Your Account</p>
        </div>

        <div id="alert" class="alert"></div>

        <form id="registerForm">
            <div class="form-group">
                <label for="fullName">Full Name</label>
                <div class="input-with-icon">
                    <i class="fas fa-user"></i>
                    <input type="text" id="fullName" class="form-control" placeholder="Enter your full name">
                </div>
            </div>

            <div class="form-group">
                <label for="username">Username *</label>
                <div class="input-with-icon">
                    <i class="fas fa-at"></i>
                    <input type="text" id="username" class="form-control" placeholder="Choose a username" required>
                </div>
            </div>

            <div class="form-group">
                <label for="email">Email Address *</label>
                <div class="input-with-icon">
                    <i class="fas fa-envelope"></i>
                    <input type="email" id="email" class="form-control" placeholder="Enter your email" required>
                </div>
            </div>

            <div class="form-group">
                <label for="password">Password *</label>
                <div class="input-with-icon">
                    <i class="fas fa-lock"></i>
                    <input type="password" id="password" class="form-control" placeholder="Create a strong password" required>
                    <button type="button" class="password-toggle" data-target="password">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
                <div class="security-indicators">
                    <div class="security-strength">
                        <div class="strength-bar" id="strengthBar"></div>
                    </div>
                    <div class="strength-text" id="strengthText">Password strength</div>
                </div>
                <div class="password-requirements" id="passwordRequirements">
                    <div class="requirement unmet" id="reqLength">
                        <i class="fas fa-circle"></i> At least 8 characters
                    </div>
                    <div class="requirement unmet" id="reqUpper">
                        <i class="fas fa-circle"></i> Uppercase letter
                    </div>
                    <div class="requirement unmet" id="reqLower">
                        <i class="fas fa-circle"></i> Lowercase letter
                    </div>
                    <div class="requirement unmet" id="reqNumber">
                        <i class="fas fa-circle"></i> Number
                    </div>
                    <div class="requirement unmet" id="reqSpecial">
                        <i class="fas fa-circle"></i> Special character
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label for="confirmPassword">Confirm Password *</label>
                <div class="input-with-icon">
                    <i class="fas fa-lock"></i>
                    <input type="password" id="confirmPassword" class="form-control" placeholder="Confirm your password" required>
                    <button type="button" class="password-toggle" data-target="confirmPassword">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn" id="registerBtn">
                <i class="fas fa-user-plus"></i> Create Musicfy Account
            </button>
        </form>

        <div class="login-link">
            Already have an account? <a href="login.html">Sign in here</a>
        </div>

        <div class="security-features">
            <h3><i class="fas fa-shield-alt"></i> Secure Registration</h3>
            <ul>
                <li><i class="fas fa-check"></i> Password hashing with bcrypt</li>
                <li><i class="fas fa-check"></i> SQL injection prevention</li>
                <li><i class="fas fa-check"></i> Input validation & sanitization</li>
                <li><i class="fas fa-check"></i> Duplicate user detection</li>
            </ul>
        </div>
    </div>

    <script>
        // Configuration
        const API_BASE_URL = 'register.php';

        // DOM Elements
        const registerForm = document.getElementById('registerForm');
        const alertDiv = document.getElementById('alert');
        const registerBtn = document.getElementById('registerBtn');

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            setupEventListeners();
            document.getElementById('fullName').focus();
        });

        function setupEventListeners() {
            // Password toggles
            document.querySelectorAll('.password-toggle').forEach(toggle => {
                toggle.addEventListener('click', function() {
                    const targetId = this.dataset.target;
                    const input = document.getElementById(targetId);
                    const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
                    input.setAttribute('type', type);
                    this.innerHTML = type === 'password' ? '<i class="fas fa-eye"></i>' : '<i class="fas fa-eye-slash"></i>';
                });
            });

            // Password strength checking
            document.getElementById('password').addEventListener('input', function() {
                checkPasswordStrength(this.value);
                validatePasswordMatch();
            });

            // Confirm password validation
            document.getElementById('confirmPassword').addEventListener('input', validatePasswordMatch);

            // Form submission
            registerForm.addEventListener('submit', handleRegister);
        }

        function checkPasswordStrength(password) {
            const requirements = {
                length: password.length >= 8,
                upper: /[A-Z]/.test(password),
                lower: /[a-z]/.test(password),
                number: /[0-9]/.test(password),
                special: /[^A-Za-z0-9]/.test(password)
            };

            // Update requirement indicators
            Object.keys(requirements).forEach(req => {
                const element = document.getElementById('req' + req.charAt(0).toUpperCase() + req.slice(1));
                if (requirements[req]) {
                    element.classList.add('met');
                    element.classList.remove('unmet');
                    element.innerHTML = '<i class="fas fa-check"></i> ' + element.textContent.replace('●', '').trim();
                } else {
                    element.classList.add('unmet');
                    element.classList.remove('met');
                    element.innerHTML = '<i class="fas fa-circle"></i> ' + element.textContent.replace('✓', '').trim();
                }
            });

            // Calculate strength score
            const metCount = Object.values(requirements).filter(Boolean).length;
            const totalCount = Object.keys(requirements).length;
            const strength = (metCount / totalCount) * 100;

            updateStrengthIndicator(strength);
        }

        function updateStrengthIndicator(strength) {
            const strengthBar = document.getElementById('strengthBar');
            const strengthText = document.getElementById('strengthText');
            
            strengthBar.className = 'strength-bar';
            strengthBar.style.width = strength + '%';
            
            if (strength === 0) {
                strengthText.textContent = 'Password strength';
                strengthText.style.color = 'rgba(255, 255, 255, 0.7)';
            } else if (strength < 40) {
                strengthBar.classList.add('strength-weak');
                strengthText.textContent = 'Weak password';
                strengthText.style.color = '#ff4757';
            } else if (strength < 70) {
                strengthBar.classList.add('strength-fair');
                strengthText.textContent = 'Fair password';
                strengthText.style.color = '#ffa502';
            } else if (strength < 90) {
                strengthBar.classList.add('strength-good');
                strengthText.textContent = 'Good password';
                strengthText.style.color = '#2ed573';
            } else {
                strengthBar.classList.add('strength-strong');
                strengthText.textContent = 'Strong password';
                strengthText.style.color = '#1DB954';
            }
        }

        function validatePasswordMatch() {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirmPassword').value;
            const confirmInput = document.getElementById('confirmPassword');

            if (confirmPassword && password !== confirmPassword) {
                confirmInput.style.borderColor = '#ff4757';
                return false;
            } else if (confirmPassword) {
                confirmInput.style.borderColor = '#1DB954';
                return true;
            } else {
                confirmInput.style.borderColor = 'rgba(255, 255, 255, 0.2)';
                return false;
            }
        }

        async function handleRegister(e) {
            e.preventDefault();
            
            const fullName = document.getElementById('fullName').value.trim();
            const username = document.getElementById('username').value.trim();
            const email = document.getElementById('email').value.trim();
            const password = document.getElementById('password').value.trim();
            const confirmPassword = document.getElementById('confirmPassword').value.trim();

            // Validation
            if (!username || !email || !password) {
                showAlert('Please fill in all required fields', 'error');
                return;
            }

            if (password.length < 8) {
                showAlert('Password must be at least 8 characters long', 'error');
                return;
            }

            // Check password requirements
            const requirements = {
                upper: /[A-Z]/.test(password),
                lower: /[a-z]/.test(password),
                number: /[0-9]/.test(password),
                special: /[^A-Za-z0-9]/.test(password)
            };

            const unmetRequirements = Object.values(requirements).filter(req => !req).length;
            if (unmetRequirements > 0) {
                showAlert('Password does not meet all requirements', 'error');
                return;
            }

            if (password !== confirmPassword) {
                showAlert('Passwords do not match', 'error');
                return;
            }

            registerBtn.disabled = true;
            registerBtn.innerHTML = '<i class="fas fa-spinner loading-spinner"></i> Creating account...';

            try {
                const response = await fetch(API_BASE_URL, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        username: username,
                        email: email,
                        password: password,
                        full_name: fullName
                    })
                });

                const data = await response.json();

                if (data.success) {
                    showAlert(data.message, 'success');
                    // Clear form
                    registerForm.reset();
                    document.getElementById('strengthBar').style.width = '0%';
                    document.getElementById('strengthText').textContent = 'Password strength';
                    
                    // Redirect to login after successful registration
                    setTimeout(() => {
                        window.location.href = 'login.html?username=' + encodeURIComponent(username);
                    }, 2000);
                } else {
                    handleFailedRegistration(data.message);
                }
            } catch (error) {
                console.error('Registration error:', error);
                handleFailedRegistration('Network error. Please check if the server is running.');
            } finally {
                registerBtn.disabled = false;
                registerBtn.innerHTML = '<i class="fas fa-user-plus"></i> Create Musicfy Account';
            }
        }

        function handleFailedRegistration(message) {
            showAlert(message, 'error');
            shakeForm();
        }

        function showAlert(message, type) {
            alertDiv.textContent = message;
            alertDiv.className = `alert alert-${type}`;
            alertDiv.style.display = 'block';
            
            setTimeout(() => {
                alertDiv.style.display = 'none';
            }, 5000);
        }

        function shakeForm() {
            registerForm.classList.add('shake');
            setTimeout(() => {
                registerForm.classList.remove('shake');
            }, 500);
        }

        // Enter key to submit form
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && !registerBtn.disabled) {
                registerForm.dispatchEvent(new Event('submit'));
            }
        });
    </script>
</body>
</html>