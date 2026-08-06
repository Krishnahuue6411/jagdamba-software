<?php
// Jagdamba Electrical - Main Root / Login Page (index.php)
require_once __DIR__ . '/db_config.php';

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.gc_maxlifetime', 28800);
    session_set_cookie_params(28800);
    session_start();
}

// Handle Logout
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
    header("Location: index.php?msg=logged_out");
    exit;
}

// Redirect if already logged in
if (!empty($_SESSION['logged_in'])) {
    header("Location: dashboard.php");
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    if (defined('LOGIN_PASSWORD') && $password === LOGIN_PASSWORD) {
        $_SESSION['logged_in'] = true;
        header("Location: dashboard.php");
        exit;
    } else {
        $error = "Incorrect password. Please try again.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Jagdamba Electrical - Login</title>
    <link rel="stylesheet" href="style.css?v=4">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background-color: var(--bg-main);
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            font-family: var(--font-body);
        }

        .login-container {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            padding: 20px;
        }

        .login-card {
            background-color: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 40px;
            width: 100%;
            max-width: 400px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.03);
            text-align: center;
            animation: fadeIn 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(15px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .login-logo {
            font-size: 3rem;
            color: var(--primary);
            margin-bottom: 20px;
        }

        .login-title {
            font-family: var(--font-header);
            font-size: 1.8rem;
            font-weight: 800;
            margin-bottom: 8px;
            color: var(--text-highlight);
            letter-spacing: 0.5px;
        }

        .login-subtitle {
            font-size: 0.9rem;
            color: var(--text-muted);
            margin-bottom: 32px;
            line-height: 1.4;
        }

        .login-form {
            text-align: left;
        }

        .input-group {
            position: relative;
            margin-bottom: 24px;
        }

        .input-group i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: 1rem;
        }

        .input-group input {
            width: 100%;
            height: 48px;
            padding-left: 44px;
            padding-right: 14px;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            background-color: var(--bg-main);
            color: var(--text-main);
            font-size: 0.95rem;
            font-family: var(--font-body);
            transition: var(--transition);
        }

        .input-group input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px var(--primary-glow);
        }

        .btn-login {
            width: 100%;
            height: 48px;
            border-radius: 8px;
            border: none;
            background-color: var(--primary);
            color: white;
            font-family: var(--font-header);
            font-weight: 700;
            font-size: 1rem;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-login:hover {
            transform: translateY(-1px);
            background-color: var(--primary-dark);
        }

        .error-message {
            background-color: rgba(239, 68, 68, 0.12);
            border: 1px solid rgba(239, 68, 68, 0.2);
            color: var(--error);
            padding: 12px 14px;
            border-radius: 8px;
            font-size: 0.85rem;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 10px;
            line-height: 1.35;
        }

        .success-message {
            background-color: rgba(16, 185, 129, 0.12);
            border: 1px solid rgba(16, 185, 129, 0.2);
            color: var(--success);
            padding: 12px 14px;
            border-radius: 8px;
            font-size: 0.85rem;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 10px;
            line-height: 1.35;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-logo">
                <i class="fa-solid fa-bolt"></i>
            </div>
            <h2 class="login-title">Jagdamba Electrical</h2>
            <p class="login-subtitle">Inventory & Invoice Management System</p>

            <?php if ($error): ?>
                <div class="error-message">
                    <i class="fa-solid fa-circle-exclamation" style="font-size: 1.05rem;"></i>
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['msg']) && $_GET['msg'] === 'logged_out'): ?>
                <div class="success-message">
                    <i class="fa-solid fa-circle-check" style="font-size: 1.05rem;"></i>
                    <span>Logged out successfully.</span>
                </div>
            <?php endif; ?>

            <form class="login-form" method="POST" action="index.php">
                <div class="input-group">
                    <i class="fa-solid fa-lock"></i>
                    <input type="password" name="password" placeholder="Enter System Password" required autofocus>
                </div>
                <button type="submit" class="btn-login">
                    <span>Access System</span>
                    <i class="fa-solid fa-arrow-right"></i>
                </button>
            </form>
        </div>
    </div>
</body>
</html>
