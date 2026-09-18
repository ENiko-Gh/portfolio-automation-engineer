<?php
/**
 * admin/login.php — CORREGIDO
 * Fixes:
 * 1. Consulta BD en lugar de string hardcodeado
 * 2. session_regenerate_id() post-login (previene session fixation)
 * 3. Sin credenciales visibles en HTML
 * 4. Brute force protection (3 intentos / 15 min)
 */
session_start();

if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: dashboard.php');
    exit;
}

require_once __DIR__ . '/../includes/db.php';

$error      = '';
$maxAttempts = 3;
$lockTime    = 900; // 15 minutos

// ── BRUTE FORCE PROTECTION ────────────────────────────────────
$ip          = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$attemptKey  = 'login_attempts_' . md5($ip);
$lockKey     = 'login_locked_'   . md5($ip);
$tmpDir      = sys_get_temp_dir();

$locked   = false;
$lockData = @file_get_contents($tmpDir . '/' . $lockKey . '.json');
if ($lockData) {
    $lock = json_decode($lockData, true);
    if ($lock && $lock['until'] > time()) {
        $locked   = true;
        $waitMins = ceil(($lock['until'] - time()) / 60);
        $error    = "Too many failed attempts. Try again in {$waitMins} minute(s).";
    } else {
        @unlink($tmpDir . '/' . $lockKey . '.json');
    }
}

// ── PROCESS LOGIN ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$locked) {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password']     ?? '';

    if (!$username || !$password) {
        $error = 'Please enter username and password.';
    } else {
        try {
            $db    = new Database();

            // Buscar en BD — usa password_hash/bcrypt
            $admin = $db->fetchOne(
                "SELECT id, username, password_hash, full_name, email
                 FROM admin_users WHERE username = ? LIMIT 1",
                [$username]
            );

            // Fallback: si la columna se llama 'password' (versión antigua)
            if (!$admin) {
                $admin = $db->fetchOne(
                    "SELECT id, username, password, full_name, email
                     FROM admin_users WHERE username = ? LIMIT 1",
                    [$username]
                );
                if ($admin) $admin['password_hash'] = $admin['password'];
            }

            $valid = false;
            if ($admin) {
                $hash = $admin['password_hash'] ?? '';
                // Verificar bcrypt
                if (password_verify($password, $hash)) {
                    $valid = true;
                }
                // Fallback MD5 legacy (solo si no es bcrypt)
                elseif (strlen($hash) === 32 && md5($password) === $hash) {
                    $valid = true;
                    // Upgrade a bcrypt
                    $db->query(
                        "UPDATE admin_users SET password_hash=? WHERE id=?",
                        [password_hash($password, PASSWORD_BCRYPT), $admin['id']]
                    );
                }
            }

            if ($valid) {
                // FIX: regenerar session ID (previene session fixation)
                session_regenerate_id(true);

                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_id']        = $admin['id'];
                $_SESSION['admin_username']  = $admin['username'];
                $_SESSION['admin_name']      = $admin['full_name'] ?? $admin['username'];
                $_SESSION['admin_email']     = $admin['email']     ?? '';
                $_SESSION['login_time']      = time();

                // Limpiar intentos fallidos
                @unlink($tmpDir . '/' . $attemptKey . '.json');

                // Actualizar last_login en BD
                try {
                    $db->query(
                        "UPDATE admin_users SET last_login=NOW() WHERE id=?",
                        [$admin['id']]
                    );
                } catch(Exception $e) {}

                header('Location: dashboard.php');
                exit;

            } else {
                // Registrar intento fallido
                $attData = @file_get_contents($tmpDir . '/' . $attemptKey . '.json');
                $att     = $attData ? json_decode($attData, true) : ['count'=>0,'first'=>time()];
                $att['count']++;

                if ($att['count'] >= $maxAttempts) {
                    @file_put_contents(
                        $tmpDir . '/' . $lockKey . '.json',
                        json_encode(['until' => time() + $lockTime])
                    );
                    @unlink($tmpDir . '/' . $attemptKey . '.json');
                    $error = "Too many failed attempts. Try again in 15 minutes.";
                } else {
                    @file_put_contents($tmpDir . '/' . $attemptKey . '.json', json_encode($att));
                    $remaining = $maxAttempts - $att['count'];
                    $error = "Invalid credentials. {$remaining} attempt(s) remaining.";
                }
            }
        } catch (Exception $e) {
            error_log('Login error: ' . $e->getMessage());
            $error = 'System error. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login — Portfolio</title>
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #0A0A0A 0%, #1A1A1A 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #EDEDED;
        }
        .login-container {
            background: #1A1A1A;
            border: 1px solid #2A2A2A;
            border-radius: 16px;
            padding: 48px;
            width: 100%;
            max-width: 420px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.5);
        }
        .login-header { text-align:center; margin-bottom:32px; }
        .login-header h1 { font-size:28px; margin-bottom:8px; }
        .login-header p { color:#A0A0A0; font-size:14px; }
        .form-group { margin-bottom:24px; }
        .form-group label {
            display:block; font-size:14px; font-weight:600; margin-bottom:8px;
        }
        .form-group input {
            width:100%; padding:12px 16px;
            background:#0A0A0A; border:1px solid #2A2A2A;
            border-radius:8px; font-size:15px; color:#EDEDED;
            transition: border-color .2s;
        }
        .form-group input:focus { outline:none; border-color:#0070F3; }
        .error-message {
            background:rgba(239,68,68,.1); border:1px solid #EF4444;
            color:#EF4444; padding:12px 16px; border-radius:8px;
            font-size:14px; margin-bottom:24px;
        }
        .btn-login {
            width:100%; padding:14px;
            background:#0070F3; border:none;
            border-radius:8px; font-size:15px;
            font-weight:600; color:#fff; cursor:pointer;
            transition: background .2s;
        }
        .btn-login:hover { background:#0060D9; }
        .btn-login:disabled { background:#333; cursor:not-allowed; }
        .login-footer {
            text-align:center; margin-top:24px;
            font-size:13px; color:#6B6B6B;
        }
        .login-footer a { color:#0070F3; text-decoration:none; }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h1>🔐 Admin Panel</h1>
            <p>Portfolio Management System</p>
        </div>

        <?php if ($error): ?>
        <div class="error-message">
            <?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>

        <?php if (!$locked): ?>
        <form method="POST" autocomplete="off">
            <!-- CSRF token -->
            <?php
                if (empty($_SESSION['csrf_token'])) {
                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                }
            ?>
            <input type="hidden" name="csrf_token"
                   value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username"
                       required autofocus autocomplete="username"
                       value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password"
                       required autocomplete="current-password">
            </div>

            <button type="submit" class="btn-login">Sign In →</button>
        </form>
        <?php endif; ?>

        <div class="login-footer">
            <!-- Sin credenciales por defecto visibles -->
            <a href="../index.html">← Back to Portfolio</a>
        </div>
    </div>
</body>
</html>
