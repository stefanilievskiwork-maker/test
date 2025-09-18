<?php
require_once 'config.php';

// Redirect if already logged in
if (isLoggedIn()) {
    redirect('/dashboard');
}

$error = '';
$success = '';

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitizeInput($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $csrf_token = $_POST['csrf_token'] ?? '';
    $remember = isset($_POST['remember']);
    
    // Validate CSRF token
    if (!verifyCSRFToken($csrf_token)) {
        $error = 'Invalid security token. Please try again.';
    } elseif (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
    } else {
        try {
            $db = getDB();
            
            // Check login attempts
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
            $stmt = $db->prepare("
                SELECT COUNT(*) as attempts 
                FROM login_attempts 
                WHERE ip_address = ? AND created_at > DATE_SUB(NOW(), INTERVAL ? SECOND)
            ");
            $stmt->execute([$ip, LOGIN_LOCKOUT_TIME]);
            $attempts = $stmt->fetch()['attempts'];
            
            if ($attempts >= MAX_LOGIN_ATTEMPTS) {
                $error = 'Too many failed login attempts. Please try again in ' . (LOGIN_LOCKOUT_TIME / 60) . ' minutes.';
            } else {
                // Get user
                $stmt = $db->prepare("
                    SELECT id, username, email, password, role, is_active, created_at 
                    FROM users 
                    WHERE (username = ? OR email = ?) AND is_active = 1
                ");
                $stmt->execute([$username, $username]);
                $user = $stmt->fetch();
                
                if ($user && verifyPassword($password, $user['password'])) {
                    // Successful login
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_authenticated'] = true;
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['user_role'] = $user['role'];
                    
                    // Update last login
                    $stmt = $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                    $stmt->execute([$user['id']]);
                    
                    // Set remember me cookie
                    if ($remember) {
                        $token = generateRandomString(64);
                        $expires = time() + (30 * 24 * 60 * 60); // 30 days
                        
                        setcookie('remember_token', $token, $expires, '/', '', true, true);
                        
                        $stmt = $db->prepare("
                            INSERT INTO remember_tokens (user_id, token, expires_at, created_at) 
                            VALUES (?, ?, FROM_UNIXTIME(?), NOW())
                            ON DUPLICATE KEY UPDATE token = VALUES(token), expires_at = VALUES(expires_at)
                        ");
                        $stmt->execute([$user['id'], hash('sha256', $token), $expires]);
                    }
                    
                    // Log activity
                    logActivity($user['id'], 'login', 'User logged in successfully');
                    
                    // Clear failed attempts
                    $stmt = $db->prepare("DELETE FROM login_attempts WHERE ip_address = ?");
                    $stmt->execute([$ip]);
                    
                    redirect('/dashboard');
                } else {
                    // Failed login
                    $error = 'Invalid username or password.';
                    
                    // Log failed attempt
                    $stmt = $db->prepare("
                        INSERT INTO login_attempts (ip_address, username, user_agent, created_at) 
                        VALUES (?, ?, ?, NOW())
                    ");
                    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
                    $stmt->execute([$ip, $username, $userAgent]);
                }
            }
        } catch (Exception $e) {
            if (DEBUG) {
                $error = 'Database error: ' . $e->getMessage();
            } else {
                $error = 'Login failed. Please try again.';
            }
        }
    }
}

// Handle remember me token
if (!isLoggedIn() && isset($_COOKIE['remember_token'])) {
    try {
        $db = getDB();
        $token_hash = hash('sha256', $_COOKIE['remember_token']);
        
        $stmt = $db->prepare("
            SELECT u.id, u.username, u.email, u.role 
            FROM users u 
            JOIN remember_tokens rt ON u.id = rt.user_id 
            WHERE rt.token = ? AND rt.expires_at > NOW() AND u.is_active = 1
        ");
        $stmt->execute([$token_hash]);
        $user = $stmt->fetch();
        
        if ($user) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_authenticated'] = true;
            $_SESSION['username'] = $user['username'];
            $_SESSION['user_role'] = $user['role'];
            
            // Update last login
            $stmt = $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
            $stmt->execute([$user['id']]);
            
            logActivity($user['id'], 'auto_login', 'User logged in via remember token');
            redirect('/dashboard');
        }
    } catch (Exception $e) {
        // Invalid token, clear cookie
        setcookie('remember_token', '', time() - 3600, '/', '', true, true);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?= SITE_NAME ?></title>
    <meta name="description" content="Login to access your movie database dashboard">
    <meta name="robots" content="noindex, nofollow">
    
    <link rel="stylesheet" href="css/auth.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="shortcut icon" href="favicon.ico" type="image/x-icon">
</head>
<body class="auth-page">
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <div class="auth-logo">
                    <i class="fas fa-film"></i>
                    <h1>Movie Dashboard</h1>
                </div>
                <p class="auth-subtitle">Sign in to your account</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <span><?= htmlspecialchars($success) ?></span>
                </div>
            <?php endif; ?>
            
            <form method="POST" class="auth-form" id="loginForm">
                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                
                <div class="form-group">
                    <label for="username">
                        <i class="fas fa-user"></i>
                        Username or Email
                    </label>
                    <input 
                        type="text" 
                        id="username" 
                        name="username" 
                        class="form-control" 
                        required 
                        autocomplete="username"
                        value="<?= htmlspecialchars($username ?? '') ?>"
                        placeholder="Enter your username or email"
                    >
                </div>
                
                <div class="form-group">
                    <label for="password">
                        <i class="fas fa-lock"></i>
                        Password
                    </label>
                    <div class="password-input">
                        <input 
                            type="password" 
                            id="password" 
                            name="password" 
                            class="form-control" 
                            required 
                            autocomplete="current-password"
                            placeholder="Enter your password"
                        >
                        <button type="button" class="password-toggle" id="passwordToggle">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
                
                <div class="form-options">
                    <label class="checkbox-label">
                        <input type="checkbox" name="remember" id="remember">
                        <span class="checkmark"></span>
                        Remember me for 30 days
                    </label>
                    
                    <a href="forgot-password" class="forgot-link">
                        Forgot password?
                    </a>
                </div>
                
                <button type="submit" class="btn btn-primary btn-full" id="loginBtn">
                    <i class="fas fa-sign-in-alt"></i>
                    <span>Sign In</span>
                </button>
            </form>
            
            <div class="auth-footer">
                <p>Don't have an account? <a href="register">Create one</a></p>
            </div>
        </div>
        
        <div class="auth-bg">
            <div class="bg-pattern"></div>
        </div>
    </div>
    
    <script src="js/auth.js"></script>
</body>
</html>
