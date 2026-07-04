<?php
/**
 * ESP32 Dashboard Login System - Secure Authentication
 * ===================================================
 * 
 * PURPOSE: Provides session-based authentication for ESP32 IoT dashboard
 * SECURITY: Protects device controls and sensor data from unauthorized access
 * 
 * AUTHENTICATION FLOW:
 * 1. Check existing session → redirect if valid, destroy if expired
 * 2. Process login form → validate password, create session
 * 3. Redirect to dashboard → user gains access to ESP32 controls
 * 4. Session management → automatic timeout, renewal on activity
 */

// Initialize PHP session for authentication state management
session_start();

// ================== AUTHENTICATION CONFIGURATION ==================
$DASHBOARD_PASSWORD = 'hatim1958'; // Main dashboard password (hint: name + 8591 reversed)
$SESSION_TIMEOUT = 3600; // Session timeout: 1 hour (3600 seconds)

// ================== EXISTING SESSION VALIDATION ==================
/**
 * Check if user already has valid authentication session
 * - If valid and active: renew session and redirect to dashboard
 * - If expired: destroy session and show login form
 */
if (isset($_SESSION['dashboard_authenticated']) && $_SESSION['dashboard_authenticated'] === true) {
    // Verify session hasn't timed out due to inactivity
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) < $SESSION_TIMEOUT) {
        // Session is valid - update activity timestamp and redirect
        $_SESSION['last_activity'] = time();
        header('Location: index.php');
        exit();
    } else {
        // Session expired - clean up and continue to login form
        session_destroy();
    }
}

// ================== USER FEEDBACK VARIABLES ==================
$login_error = '';      // Error message for invalid password
$logout_message = '';   // Success message after logout
$expired_message = '';  // Warning for session expiration

// ================== URL PARAMETER MESSAGE PROCESSING ==================
/**
 * Handle feedback messages from URL parameters:
 * ?logout=1 - User clicked logout (show success)
 * ?expired=1 - Session timed out (show warning)
 */
if (isset($_GET['logout']) && $_GET['logout'] == '1') {
    $logout_message = 'You have been logged out successfully.';
}
if (isset($_GET['expired']) && $_GET['expired'] == '1') {
    $expired_message = 'Your session has expired. Please login again.';
}

// ================== LOGIN FORM PROCESSING ==================
/**
 * Process password submission and create authenticated session
 * Flow: Validate password → Create session → Redirect to dashboard
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
    if ($_POST['password'] === $DASHBOARD_PASSWORD) {
        // Password correct - establish authenticated session
        $_SESSION['dashboard_authenticated'] = true;  // Authentication flag
        $_SESSION['last_activity'] = time();          // For timeout tracking
        $_SESSION['login_time'] = time();             // Session start time
        
        // Redirect to protected ESP32 dashboard
        header('Location: index.php');
        exit();
    } else {
        // Password incorrect - show error
        $login_error = 'Invalid password. Please try again.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ESP32 Dashboard - Login</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #333;
        }

        .login-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 400px;
            text-align: center;
        }

        .logo {
            font-size: 32px;
            font-weight: 700;
            background: linear-gradient(45deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 10px;
        }

        .subtitle {
            color: #666;
            font-size: 16px;
            margin-bottom: 30px;
        }

        .form-group {
            margin-bottom: 20px;
            text-align: left;
        }

        label {
            display: block;
            font-weight: 600;
            color: #555;
            margin-bottom: 8px;
        }

        input[type="password"] {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e1e5e9;
            border-radius: 10px;
            font-size: 16px;
            transition: all 0.2s ease;
            background: rgba(255, 255, 255, 0.9);
        }

        input[type="password"]:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
        }

        .login-btn {
            width: 100%;
            padding: 12px 24px;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
            margin-bottom: 15px;
        }

        .login-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
        }

        .login-btn:active {
            transform: translateY(0);
        }

        .error {
            background: #ffebee;
            border: 1px solid #ffcdd2;
            color: #c62828;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .success {
            background: #e8f5e8;
            border: 1px solid #c8e6c9;
            color: #2e7d32;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .warning {
            background: #fff8e1;
            border: 1px solid #ffecb3;
            color: #f57c00;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .hint {
            background: #fff3e0;
            border: 1px solid #ffcc02;
            color: #e65100;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .info {
            background: #e3f2fd;
            border: 1px solid #bbdefb;
            color: #1565c0;
            padding: 12px;
            border-radius: 8px;
            margin-top: 20px;
            font-size: 14px;
        }

        .shield-icon {
            font-size: 48px;
            color: #667eea;
            margin-bottom: 20px;
        }

        .hint-btn {
            background: none;
            border: none;
            color: #667eea;
            font-size: 14px;
            cursor: pointer;
            text-decoration: underline;
            margin-bottom: 15px;
        }

        .hint-btn:hover {
            color: #764ba2;
        }

        #hint-box {
            display: none;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="shield-icon">🔒</div>
        <div class="logo">ESP32 Dashboard</div>
        <div class="subtitle">Secure Access Required</div>
        
        <?php if ($logout_message): ?>
            <div class="success">✅ <?php echo htmlspecialchars($logout_message); ?></div>
        <?php endif; ?>
        
        <?php if ($expired_message): ?>
            <div class="warning">⏰ <?php echo htmlspecialchars($expired_message); ?></div>
        <?php endif; ?>
        
        <?php if ($login_error): ?>
            <div class="error">❌ <?php echo htmlspecialchars($login_error); ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" required autofocus>
            </div>
            
            <button type="submit" class="login-btn">🚀 Access Dashboard</button>
        </form>
        
        <button type="button" class="hint-btn" onclick="toggleHint()">💡 Need a hint?</button>
        
        <div id="hint-box" class="hint">
            <strong>🔑 Password Hint:</strong> Your name + <strong>8591</strong>
        </div>
        
        <div class="info">
            <strong>🛡️ Security Features:</strong><br>
            • Session timeout after 1 hour<br>
            • Secure password protection<br>
            • ESP32 remote control access
        </div>
    </div>

    <script>
        function toggleHint() {
            const hintBox = document.getElementById('hint-box');
            const hintBtn = document.querySelector('.hint-btn');
            
            if (hintBox.style.display === 'none' || hintBox.style.display === '') {
                hintBox.style.display = 'block';
                hintBtn.textContent = '🔒 Hide hint';
            } else {
                hintBox.style.display = 'none';
                hintBtn.textContent = '💡 Need a hint?';
            }
        }
    </script>
</body>
</html>