<?php

/**
 * admin/settings.php — Configuración del sistema
 * Compatible con tabla settings real:
 * id, setting_key, setting_value, description, updated_at
 */
session_start();
require_once 'auth_check.php';

if (!function_exists('getAdminName')) {
    function getAdminName(): string
    {
        if (!empty($_SESSION['admin_name'])) return $_SESSION['admin_name'];
        if (!empty($_SESSION['admin_user'])) return $_SESSION['admin_user'];
        return 'Admin';
    }
}

require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/db.php';
// Cargar helpers opcionales
if (file_exists(__DIR__ . '/../includes/whatsapp.php')) {
    require_once __DIR__ . '/../includes/whatsapp.php';
}
if (file_exists(__DIR__ . '/../includes/email.php')) {
    require_once __DIR__ . '/../includes/email.php';
}

$db         = new Database();
$page_title = 'Settings';
$saved      = false;
$error      = null;

// ── HANDLE POST ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'save_settings';

    if ($action === 'save_settings') {
        $fields = [
            'site_name'       => $_POST['site_name']       ?? '',
            'contact_email'   => $_POST['contact_email']   ?? '',
            'whatsapp_number' => $_POST['whatsapp_number'] ?? '',
            'admin_name'      => $_POST['admin_name']      ?? '',
            'timezone'        => $_POST['timezone']        ?? 'America/Denver',
            'chatbot_enabled' => isset($_POST['chatbot_enabled']) ? '1' : '0',
            'maintenance_mode' => isset($_POST['maintenance_mode']) ? '1' : '0',
            'score_alert'     => (int)($_POST['score_alert'] ?? 70),
        ];

        try {
            foreach ($fields as $key => $value) {
                // Upsert: actualizar si existe, insertar si no
                $existing = $db->fetchOne(
                    "SELECT id FROM settings WHERE setting_key = ?",
                    [$key]
                );
                if ($existing) {
                    $db->query(
                        "UPDATE settings SET setting_value = ?, updated_at = NOW() WHERE setting_key = ?",
                        [$value, $key]
                    );
                } else {
                    $db->insert('settings', [
                        'setting_key'   => $key,
                        'setting_value' => $value,
                        'description'   => $key,
                        'updated_at'    => date('Y-m-d H:i:s'),
                    ]);
                }
            }
            $saved = true;
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }

    if ($action === 'change_password') {
        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password']     ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if (!$current || !$new || !$confirm) {
            $error = 'All password fields are required.';
        } elseif ($new !== $confirm) {
            $error = 'New passwords do not match.';
        } elseif (strlen($new) < 8) {
            $error = 'Password must be at least 8 characters.';
        } else {
            $admin = $db->fetchOne(
                "SELECT id, password_hash FROM admin_users WHERE username = ? LIMIT 1",
                [$_SESSION['admin_user'] ?? 'admin']
            );
            if ($admin && password_verify($current, $admin['password_hash'])) {
                $db->query(
                    "UPDATE admin_users SET password_hash = ? WHERE id = ?",
                    [password_hash($new, PASSWORD_BCRYPT), $admin['id']]
                );
                $saved = true;
            } else {
                $error = 'Current password is incorrect.';
            }
        }
    }

    if ($action === 'test_whatsapp') {
        require_once __DIR__ . '/../includes/whatsapp.php';
        $phone = $_POST['test_phone'] ?? '';
        if ($phone) {
            try {
                sendWhatsAppMessage(
                    'whatsapp:' . $phone,
                    "✅ Test message from your Portfolio Admin — " . date('H:i')
                );
                $saved = true;
            } catch (Exception $e) {
                $error = 'WhatsApp test failed: ' . $e->getMessage();
            }
        }
    }

    if ($action === 'test_email') {
        require_once __DIR__ . '/../includes/email.php';
        $toEmail = $_POST['test_email_addr'] ?? '';
        if ($toEmail) {
            try {
                sendEmail(
                    $toEmail,
                    'Test — Portfolio Admin',
                    '<p>✅ Email system working correctly.</p><p>Sent: ' . date('Y-m-d H:i:s') . '</p>'
                );
                $saved = true;
            } catch (Exception $e) {
                $error = 'Email test failed: ' . $e->getMessage();
            }
        }
    }
}

// ── LOAD SETTINGS ─────────────────────────────────────────────
$settings = [];
try {
    $rows = $db->fetchAll("SELECT setting_key, setting_value FROM settings");
    foreach ($rows as $row) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
} catch (Exception $e) {
    $error = 'Could not load settings: ' . $e->getMessage();
}

function s(array $settings, string $key, string $default = ''): string
{
    return htmlspecialchars($settings[$key] ?? $default);
}

// ── SYSTEM INFO ───────────────────────────────────────────────
$phpVersion  = PHP_VERSION;
$mysqlVersion = '';
try {
    $row = $db->fetchOne("SELECT VERSION() as v");
    $mysqlVersion = $row['v'] ?? '—';
} catch (Exception $e) {
}

$tableCount = 0;
try {
    $tables = $db->fetchAll(
        "SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()"
    );
    $tableCount = count($tables);
} catch (Exception $e) {
}

include 'header.php';
?>

<div style="max-width:900px;margin:0 auto">

    <?php if ($saved): ?>
        <div style="background:rgba(72,187,120,.1);border:1px solid #48BB78;color:#48BB78;
            padding:14px 18px;border-radius:10px;margin-bottom:20px;font-weight:600">
            ✓ Settings saved successfully
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div style="background:rgba(252,129,129,.1);border:1px solid #FC8181;color:#FC8181;
            padding:14px 18px;border-radius:10px;margin-bottom:20px">
            ⚠️ <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <!-- GENERAL SETTINGS -->
    <div style="background:#1A1A1A;border:1px solid #2D2D2D;border-radius:12px;
            overflow:hidden;margin-bottom:20px">
        <div style="padding:16px 20px;border-bottom:1px solid #2D2D2D;background:#111">
            <h3 style="color:#EDEDED;font-size:15px;font-weight:700">⚙️ General Settings</h3>
        </div>
        <form method="POST" style="padding:20px;display:flex;flex-direction:column;gap:14px">
            <input type="hidden" name="action" value="save_settings">

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
                <div>
                    <label style="display:block;font-size:12px;font-weight:600;
                               color:#A0AEC0;margin-bottom:5px">Site Name</label>
                    <input type="text" name="site_name"
                        value="<?= s($settings, 'site_name', 'Edison Guamialama Portfolio') ?>"
                        style="width:100%;background:#111;border:1px solid #333;border-radius:8px;
                              padding:9px 12px;color:#EDEDED;font-size:13px">
                </div>
                <div>
                    <label style="display:block;font-size:12px;font-weight:600;
                               color:#A0AEC0;margin-bottom:5px">Admin Name</label>
                    <input type="text" name="admin_name"
                        value="<?= s($settings, 'admin_name', 'Edison Nicolas Guamialama') ?>"
                        style="width:100%;background:#111;border:1px solid #333;border-radius:8px;
                              padding:9px 12px;color:#EDEDED;font-size:13px">
                </div>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
                <div>
                    <label style="display:block;font-size:12px;font-weight:600;
                               color:#A0AEC0;margin-bottom:5px">Contact Email</label>
                    <input type="email" name="contact_email"
                        value="<?= s($settings, 'contact_email', '') ?>"
                        style="width:100%;background:#111;border:1px solid #333;border-radius:8px;
                              padding:9px 12px;color:#EDEDED;font-size:13px">
                </div>
                <div>
                    <label style="display:block;font-size:12px;font-weight:600;
                               color:#A0AEC0;margin-bottom:5px">WhatsApp Number</label>
                    <input type="text" name="whatsapp_number"
                        value="<?= s($settings, 'whatsapp_number', '') ?>"
                        placeholder="+1234567890"
                        style="width:100%;background:#111;border:1px solid #333;border-radius:8px;
                              padding:9px 12px;color:#EDEDED;font-size:13px">
                </div>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
                <div>
                    <label style="display:block;font-size:12px;font-weight:600;
                               color:#A0AEC0;margin-bottom:5px">Timezone</label>
                    <select name="timezone"
                        style="width:100%;background:#111;border:1px solid #333;border-radius:8px;
                               padding:9px 12px;color:#EDEDED;font-size:13px">
                        <?php
                        $tzones = [
                            'America/Denver' => 'Mountain Time (MT)',
                            'America/New_York' => 'Eastern Time (ET)',
                            'America/Chicago' => 'Central Time (CT)',
                            'America/Los_Angeles' => 'Pacific Time (PT)'
                        ];
                        foreach ($tzones as $val => $label):
                            $sel = ($settings['timezone'] ?? 'America/Denver') === $val ? 'selected' : '';
                        ?>
                            <option value="<?= $val ?>" <?= $sel ?>><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label style="display:block;font-size:12px;font-weight:600;
                               color:#A0AEC0;margin-bottom:5px">Alert Score Threshold</label>
                    <input type="number" name="score_alert" min="0" max="100"
                        value="<?= s($settings, 'score_alert', '70') ?>"
                        style="width:100%;background:#111;border:1px solid #333;border-radius:8px;
                              padding:9px 12px;color:#EDEDED;font-size:13px">
                    <div style="font-size:11px;color:#4A5568;margin-top:4px">
                        WhatsApp alert when visitor score ≥ this value
                    </div>
                </div>
            </div>

            <div style="display:flex;gap:20px">
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13px;color:#A0AEC0">
                    <input type="checkbox" name="chatbot_enabled"
                        <?= ($settings['chatbot_enabled'] ?? '1') === '1' ? 'checked' : '' ?>>
                    Enable Chatbot
                </label>
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13px;color:#A0AEC0">
                    <input type="checkbox" name="maintenance_mode"
                        <?= ($settings['maintenance_mode'] ?? '0') === '1' ? 'checked' : '' ?>>
                    Maintenance Mode
                </label>
            </div>

            <button type="submit"
                style="background:#0070F3;border:none;color:#fff;padding:10px 20px;
                       border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;
                       align-self:flex-start">
                💾 Save Settings
            </button>
        </form>
    </div>

    <!-- CHANGE PASSWORD -->
    <div style="background:#1A1A1A;border:1px solid #2D2D2D;border-radius:12px;
            overflow:hidden;margin-bottom:20px">
        <div style="padding:16px 20px;border-bottom:1px solid #2D2D2D;background:#111">
            <h3 style="color:#EDEDED;font-size:15px;font-weight:700">🔐 Change Password</h3>
        </div>
        <form method="POST" style="padding:20px;display:flex;flex-direction:column;gap:14px">
            <input type="hidden" name="action" value="change_password">
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px">
                <div>
                    <label style="display:block;font-size:12px;font-weight:600;
                               color:#A0AEC0;margin-bottom:5px">Current Password</label>
                    <input type="password" name="current_password"
                        style="width:100%;background:#111;border:1px solid #333;border-radius:8px;
                              padding:9px 12px;color:#EDEDED;font-size:13px">
                </div>
                <div>
                    <label style="display:block;font-size:12px;font-weight:600;
                               color:#A0AEC0;margin-bottom:5px">New Password</label>
                    <input type="password" name="new_password"
                        style="width:100%;background:#111;border:1px solid #333;border-radius:8px;
                              padding:9px 12px;color:#EDEDED;font-size:13px">
                </div>
                <div>
                    <label style="display:block;font-size:12px;font-weight:600;
                               color:#A0AEC0;margin-bottom:5px">Confirm Password</label>
                    <input type="password" name="confirm_password"
                        style="width:100%;background:#111;border:1px solid #333;border-radius:8px;
                              padding:9px 12px;color:#EDEDED;font-size:13px">
                </div>
            </div>
            <button type="submit"
                style="background:#2D2D2D;border:1px solid #444;color:#EDEDED;padding:9px 18px;
                       border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;
                       align-self:flex-start">
                🔑 Update Password
            </button>
        </form>
    </div>

    <!-- TEST NOTIFICATIONS -->
    <div style="background:#1A1A1A;border:1px solid #2D2D2D;border-radius:12px;
            overflow:hidden;margin-bottom:20px">
        <div style="padding:16px 20px;border-bottom:1px solid #2D2D2D;background:#111">
            <h3 style="color:#EDEDED;font-size:15px;font-weight:700">🔔 Test Notifications</h3>
        </div>
        <div style="padding:20px;display:grid;grid-template-columns:1fr 1fr;gap:20px">
            <!-- Test WhatsApp -->
            <form method="POST">
                <input type="hidden" name="action" value="test_whatsapp">
                <label style="display:block;font-size:12px;font-weight:600;
                           color:#A0AEC0;margin-bottom:5px">Send test WhatsApp</label>
                <div style="display:flex;gap:8px">
                    <input type="text" name="test_phone"
                        placeholder="+1234567890"
                        value="<?= s($settings, 'whatsapp_number', '') ?>"
                        style="flex:1;background:#111;border:1px solid #333;border-radius:8px;
                              padding:8px 12px;color:#EDEDED;font-size:13px">
                    <button type="submit"
                        style="background:#25D366;border:none;color:#000;padding:8px 14px;
                               border-radius:8px;font-size:12px;font-weight:700;cursor:pointer">
                        📱 Test
                    </button>
                </div>
                <div style="font-size:11px;color:#4A5568;margin-top:4px">
                    Requires Twilio configured in api/config.php
                </div>
            </form>

            <!-- Test Email -->
            <form method="POST">
                <input type="hidden" name="action" value="test_email">
                <label style="display:block;font-size:12px;font-weight:600;
                           color:#A0AEC0;margin-bottom:5px">Send test email</label>
                <div style="display:flex;gap:8px">
                    <input type="email" name="test_email_addr"
                        placeholder="your@email.com"
                        value="<?= s($settings, 'contact_email', '') ?>"
                        style="flex:1;background:#111;border:1px solid #333;border-radius:8px;
                              padding:8px 12px;color:#EDEDED;font-size:13px">
                    <button type="submit"
                        style="background:#63B3ED;border:none;color:#000;padding:8px 14px;
                               border-radius:8px;font-size:12px;font-weight:700;cursor:pointer">
                        📧 Test
                    </button>
                </div>
                <div style="font-size:11px;color:#4A5568;margin-top:4px">
                    Requires Gmail App Password in api/config.php
                </div>
            </form>
        </div>
    </div>

    <!-- SYSTEM INFO -->
    <div style="background:#1A1A1A;border:1px solid #2D2D2D;border-radius:12px;overflow:hidden">
        <div style="padding:16px 20px;border-bottom:1px solid #2D2D2D;background:#111">
            <h3 style="color:#EDEDED;font-size:15px;font-weight:700">🖥️ System Info</h3>
        </div>
        <div style="padding:20px;display:grid;grid-template-columns:repeat(3,1fr);gap:16px">
            <?php
            $items = [
                ['PHP Version',    $phpVersion,             '#48BB78'],
                ['MySQL Version',  $mysqlVersion,           '#63B3ED'],
                ['DB Tables',      $tableCount . ' tables', '#F6AD55'],
                ['Portfolio DB',   'portfolio_db',          '#A0AEC0'],
                ['Server',         $_SERVER['SERVER_SOFTWARE'] ?? 'XAMPP', '#A0AEC0'],
                ['PHP Memory',     ini_get('memory_limit'),  '#A0AEC0'],
            ];
            foreach ($items as [$label, $value, $color]):
            ?>
                <div style="background:#111;border:1px solid #2D2D2D;border-radius:8px;padding:12px">
                    <div style="font-size:10px;color:#4A5568;text-transform:uppercase;
                        letter-spacing:1px;margin-bottom:4px"><?= $label ?></div>
                    <div style="font-size:14px;font-weight:700;color:<?= $color ?>;
                        font-family:monospace"><?= htmlspecialchars($value) ?></div>
                </div>
            <?php endforeach; ?>
        </div>
        <div style="padding:0 20px 20px">
            <a href="../index.html" target="_blank"
                style="display:inline-flex;align-items:center;gap:6px;background:#0070F3;
                  color:#fff;padding:9px 16px;border-radius:8px;font-size:13px;
                  font-weight:600;text-decoration:none">
                🌐 View Live Portfolio
            </a>
            <a href="http://localhost/phpmyadmin" target="_blank"
                style="display:inline-flex;align-items:center;gap:6px;background:#2D2D2D;
                  color:#EDEDED;padding:9px 16px;border-radius:8px;font-size:13px;
                  font-weight:600;text-decoration:none;margin-left:8px">
                🗄️ phpMyAdmin
            </a>
        </div>
    </div>

</div>
<?php include 'footer.php'; ?>