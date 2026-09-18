<?php

/**
 * admin/crm.php — Compatible con tu BD real
 * Usa new Database() — NO getInstance()
 * Compatible con visitors real + columnas CRM agregadas por patch_bd_real.sql
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
require_once __DIR__ . '/../includes/whatsapp.php';

$db         = new Database();
$page_title = 'CRM — Contacts';

// ── HANDLE POST ACTIONS ───────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action     = $_POST['action']     ?? '';
    $visitor_id = (int)($_POST['visitor_id'] ?? 0);

    switch ($action) {
        case 'set_status':
            $status = in_array(
                $_POST['status'] ?? '',
                ['potential', 'follow_up', 'not_qualified', 'blacklisted']
            )
                ? $_POST['status'] : 'follow_up';
            try {
                $db->query(
                    "UPDATE visitors SET crm_status = ?, crm_updated_at = NOW() WHERE id = ?",
                    [$status, $visitor_id]
                );
            } catch (Exception $e) {
                error_log($e->getMessage());
            }
            break;

        case 'add_note':
            $note = trim(htmlspecialchars($_POST['note'] ?? ''));
            if ($note && $visitor_id && $db->tableExists('crm_notes')) {
                try {
                    $db->insert('crm_notes', [
                        'visitor_id' => $visitor_id,
                        'note'       => $note,
                        'added_by'   => $_SESSION['admin_user'] ?? 'admin',
                        'created_at' => date('Y-m-d H:i:s'),
                    ]);
                } catch (Exception $e) {
                    error_log($e->getMessage());
                }
            }
            break;

        case 'blacklist':
            $reason = trim(htmlspecialchars($_POST['reason'] ?? 'Manually blacklisted'));
            try {
                $db->query(
                    "UPDATE visitors SET is_blacklisted = 1, blacklist_reason = ?,
                     crm_status = 'blacklisted', crm_updated_at = NOW() WHERE id = ?",
                    [$reason, $visitor_id]
                );
            } catch (Exception $e) {
                error_log($e->getMessage());
            }
            break;

        case 'send_whatsapp':
            $phone   = trim($_POST['phone']   ?? '');
            $message = trim($_POST['message'] ?? '');
            if ($phone && $message) {
                try {
                    sendWhatsAppMessage('whatsapp:' . $phone, $message);
                } catch (Exception $e) {
                }
                if ($db->tableExists('crm_notes')) {
                    try {
                        $db->insert('crm_notes', [
                            'visitor_id' => $visitor_id,
                            'note'       => "📱 WhatsApp sent: " . substr($message, 0, 100),
                            'added_by'   => 'system',
                            'created_at' => date('Y-m-d H:i:s'),
                        ]);
                    } catch (Exception $e) {
                    }
                }
            }
            break;
    }

    header('Location: crm.php');
    exit;
}

// ── FILTERS ───────────────────────────────────────────────────
$filterStatus = $_GET['status'] ?? 'all';
$search       = trim($_GET['q'] ?? '');
$page         = max(1, (int)($_GET['page'] ?? 1));
$perPage      = 20;
$offset       = ($page - 1) * $perPage;

// ── CHECK CRM COLUMNS EXIST ───────────────────────────────────
// Si no se ha ejecutado el patch SQL, crm_status no existe
$crmColumnsExist = false;
try {
    $check = $db->fetchOne(
        "SELECT COLUMN_NAME FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'visitors'
         AND COLUMN_NAME = 'crm_status' LIMIT 1"
    );
    $crmColumnsExist = !empty($check);
} catch (Exception $e) {
}

// ── PIPELINE COUNTS ───────────────────────────────────────────
$stats = [
    'total'       => $db->count('visitors'),
    'this_week'   => $db->count('visitors', 'first_visit >= DATE_SUB(NOW(), INTERVAL 7 DAY)'),
    'potential'   => 0,
    'follow_up'   => 0,
    'not_qual'    => 0,
    'blacklisted' => 0,
];

if ($crmColumnsExist) {
    $stats['potential']   = $db->count('visitors', "crm_status = 'potential'");
    $stats['follow_up']   = $db->count('visitors', "crm_status = 'follow_up'");
    $stats['not_qual']    = $db->count('visitors', "crm_status = 'not_qualified'");
    $stats['blacklisted'] = $db->count('visitors', "crm_status = 'blacklisted'");
}

// ── BUILD QUERY ───────────────────────────────────────────────
$where  = ['1=1'];
$params = [];

if ($filterStatus !== 'all' && $crmColumnsExist) {
    $where[]  = 'v.crm_status = ?';
    $params[] = $filterStatus;
}
if ($search) {
    $where[]  = '(v.full_name LIKE ? OR v.email LIKE ?)';
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}
$whereStr = implode(' AND ', $where);

// ── CONTACTS ──────────────────────────────────────────────────
// Columnas compatibles con tu tabla visitors real
$crmSelect = $crmColumnsExist
    ? ", v.crm_status, v.is_blacklisted, v.contact_score, v.visitor_type"
    : ", NULL as crm_status, 0 as is_blacklisted, 0 as contact_score, 'unknown' as visitor_type";

$contacts = $db->fetchAll(
    "SELECT v.id, v.full_name, v.email, v.phone, v.company, v.job_title,
            v.country, v.interest, v.ip_address, v.visit_count,
            v.first_visit, v.last_visit, v.status
            {$crmSelect},
            (SELECT COUNT(*) FROM chat_logs cl WHERE cl.visitor_id = v.id) as chat_count,
            (SELECT COUNT(*) FROM appointments a WHERE a.visitor_id = v.id) as apt_count,
            (SELECT MAX(cl2.created_at) FROM chat_logs cl2 WHERE cl2.visitor_id = v.id) as last_chat
     FROM visitors v
     WHERE {$whereStr}
     ORDER BY v.last_visit DESC
     LIMIT {$perPage} OFFSET {$offset}",
    $params
);

$total = $db->count('visitors v', $whereStr, $params);

include 'header.php';
?>

<?php if (!$crmColumnsExist): ?>
    <div style="background:rgba(245,158,11,.15);border:1px solid #F59E0B;color:#F59E0B;
            padding:16px 20px;border-radius:10px;margin-bottom:20px">
        <strong>⚠️ Ejecuta el SQL patch primero</strong>
        <p style="color:#EDEDED;font-size:13px;margin-top:6px">
            Las columnas CRM no existen en tu BD todavía. Ejecuta
            <code>patch_bd_real.sql</code> en phpMyAdmin y recarga esta página.
        </p>
    </div>
<?php endif; ?>

<div style="max-width:1300px;margin:0 auto">

    <!-- PIPELINE -->
    <div style="display:grid;grid-template-columns:repeat(5,1fr);gap:14px;margin-bottom:24px">
        <?php
        $cards = [
            ['all',           '#A0AEC0', 'All', $stats['total'],       "+{$stats['this_week']} this week"],
            ['potential',     '#48BB78', '🟢 Potential',   $stats['potential'],   'High score'],
            ['follow_up',     '#F6AD55', '🟡 Follow Up',   $stats['follow_up'],   'Needs attention'],
            ['not_qualified', '#718096', '⚪ Not Qualified', $stats['not_qual'],    'Low engagement'],
            ['blacklisted',   '#FC8181', '🔴 Blocked',     $stats['blacklisted'], 'No contact'],
        ];
        foreach ($cards as [$val, $color, $label, $count, $sub]):
            $active = $filterStatus === $val ? "border:2px solid {$color}" : 'border:1px solid #2D2D2D';
        ?>
            <a href="crm.php?status=<?= $val ?>"
                style="display:block;background:#1A1A1A;<?= $active ?>;border-radius:12px;
              padding:16px;text-align:center;text-decoration:none;color:<?= $color ?>">
                <div style="font-size:28px;font-weight:700;font-family:monospace"><?= $count ?></div>
                <div style="font-size:11px;font-weight:600;margin-top:4px"><?= $label ?></div>
                <div style="font-size:10px;opacity:.7;margin-top:2px"><?= $sub ?></div>
            </a>
        <?php endforeach; ?>
    </div>

    <!-- FILTERS -->
    <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:20px;
      background:#1A1A1A;padding:14px;border-radius:10px;align-items:center">
        <input type="hidden" name="status" value="<?= htmlspecialchars($filterStatus) ?>">
        <input type="text" name="q" value="<?= htmlspecialchars($search) ?>"
            placeholder="🔍 Search name or email..."
            style="flex:1;min-width:180px;background:#111;border:1px solid #333;
                  border-radius:8px;padding:8px 12px;color:#EDEDED;font-size:13px">
        <button type="submit"
            style="background:#0070F3;border:none;color:#fff;padding:8px 16px;
                   border-radius:8px;font-size:13px;font-weight:600;cursor:pointer">
            Filter
        </button>
        <a href="crm.php" style="color:#6B6B6B;font-size:12px;text-decoration:none">Clear</a>
    </form>

    <!-- TABLE -->
    <div style="overflow-x:auto">
        <table style="width:100%;border-collapse:collapse;font-size:13px">
            <thead>
                <tr style="background:#1A1A1A;color:#A0AEC0;font-size:11px;
                   text-transform:uppercase;letter-spacing:.5px">
                    <th style="padding:10px 14px;text-align:left">Contact</th>
                    <th style="padding:10px 14px;text-align:left">Company / Role</th>
                    <th style="padding:10px 14px;text-align:left">Status</th>
                    <th style="padding:10px 14px;text-align:center">Chats</th>
                    <th style="padding:10px 14px;text-align:center">Apts</th>
                    <th style="padding:10px 14px;text-align:left">Last Visit</th>
                    <th style="padding:10px 14px;text-align:left">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($contacts)): ?>
                    <tr>
                        <td colspan="7" style="text-align:center;color:#6B6B6B;padding:40px">
                            No contacts yet. Visitors appear here once they interact with your portfolio.
                        </td>
                    </tr>
                    <?php else: foreach ($contacts as $c):
                        $initials  = strtoupper(substr($c['full_name'] ?: '?', 0, 1));
                        $crm       = $c['crm_status'] ?? null;
                        $statusMap = [
                            'potential'     => ['🟢 Potential',    '#48BB78', 'rgba(72,187,120,.15)'],
                            'follow_up'     => ['🟡 Follow Up',    '#F6AD55', 'rgba(246,173,85,.15)'],
                            'not_qualified' => ['⚪ Not Qualified', '#718096', 'rgba(113,128,150,.15)'],
                            'blacklisted'   => ['🔴 Blocked',      '#FC8181', 'rgba(252,129,129,.15)'],
                        ];
                        [$sLabel, $sColor, $sBg] = $statusMap[$crm] ?? ['❓ Unknown', '#A0AEC0', 'rgba(160,174,192,.1)'];
                    ?>
                        <tr style="border-bottom:1px solid #1A1A1A">
                            <td style="padding:12px 14px">
                                <div style="display:flex;align-items:center;gap:10px">
                                    <div style="width:36px;height:36px;border-radius:50%;background:#1A2640;
                            border:1px solid #2D3748;display:flex;align-items:center;
                            justify-content:center;font-weight:700;font-size:14px;
                            color:#63B3ED;flex-shrink:0">
                                        <?= $initials ?>
                                    </div>
                                    <div>
                                        <div style="font-weight:600;color:#EDEDED">
                                            <?= htmlspecialchars($c['full_name'] ?: 'Anonymous') ?>
                                        </div>
                                        <div style="font-size:11px;color:#6B6B6B">
                                            <?= htmlspecialchars($c['email'] ?: $c['ip_address'] ?? '—') ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td style="padding:12px 14px;color:#A0AEC0;font-size:12px">
                                <?= htmlspecialchars($c['company'] ?: '—') ?>
                                <?php if ($c['job_title']): ?>
                                    <div style="font-size:11px;color:#4A5568"><?= htmlspecialchars($c['job_title']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td style="padding:12px 14px">
                                <span style="display:inline-flex;align-items:center;gap:5px;padding:3px 10px;
                         border-radius:20px;font-size:11px;font-weight:700;
                         background:<?= $sBg ?>;color:<?= $sColor ?>">
                                    <?= $sLabel ?>
                                </span>
                            </td>
                            <td style="padding:12px 14px;text-align:center;font-family:monospace;color:#A0AEC0">
                                <?= $c['chat_count'] ?? 0 ?>
                            </td>
                            <td style="padding:12px 14px;text-align:center;font-family:monospace;color:#A0AEC0">
                                <?= $c['apt_count'] ?? 0 ?>
                            </td>
                            <td style="padding:12px 14px;font-size:11px;color:#6B6B6B">
                                <?= $c['last_visit'] ? date('M d, H:i', strtotime($c['last_visit'])) : '—' ?>
                            </td>
                            <td style="padding:12px 14px">
                                <div style="display:flex;gap:5px;flex-wrap:wrap">
                                    <?php if ($crmColumnsExist && ($c['crm_status'] ?? '') !== 'potential'): ?>
                                        <form method="POST" style="display:inline">
                                            <input type="hidden" name="visitor_id" value="<?= $c['id'] ?>">
                                            <input type="hidden" name="action" value="set_status">
                                            <input type="hidden" name="status" value="potential">
                                            <button type="submit"
                                                style="background:rgba(72,187,120,.15);color:#48BB78;border:none;
                                   padding:4px 8px;border-radius:6px;font-size:11px;
                                   font-weight:600;cursor:pointer">
                                                🟢 Qualify
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if ($crmColumnsExist && ($c['crm_status'] ?? '') !== 'blacklisted'): ?>
                                        <button onclick="confirmBlock(<?= $c['id'] ?>,'<?= addslashes($c['full_name'] ?? '') ?>')"
                                            style="background:rgba(252,129,129,.1);color:#FC8181;border:none;
                               padding:4px 8px;border-radius:6px;font-size:11px;
                               font-weight:600;cursor:pointer">
                                            🔴 Block
                                        </button>
                                    <?php endif; ?>
                                    <button onclick="toggleDetail(<?= $c['id'] ?>)"
                                        style="background:#1A2640;color:#63B3ED;border:none;
                               padding:4px 8px;border-radius:6px;font-size:11px;
                               font-weight:600;cursor:pointer">
                                        👁 View
                                    </button>
                                </div>
                            </td>
                        </tr>

                        <!-- Detail row -->
                        <tr id="detail-row-<?= $c['id'] ?>" style="display:none">
                            <td colspan="7" style="padding:0 14px 14px;background:#0A0A0A">
                                <div style="background:#111;border:1px solid #2D3748;border-radius:10px;
                        padding:20px;display:grid;grid-template-columns:1fr 1fr;gap:20px">
                                    <!-- Chat history -->
                                    <div>
                                        <strong style="font-size:11px;color:#A0AEC0;text-transform:uppercase;
                                   letter-spacing:1px">💬 Chat History</strong>
                                        <div id="chat-<?= $c['id'] ?>" style="margin-top:10px;max-height:250px;
                         overflow-y:auto;display:flex;flex-direction:column;gap:8px">
                                            <div style="color:#4A5568;font-size:12px">Click View to load...</div>
                                        </div>
                                    </div>
                                    <!-- Info + Notes -->
                                    <div>
                                        <strong style="font-size:11px;color:#A0AEC0;text-transform:uppercase;
                                   letter-spacing:1px">📋 Info & Notes</strong>
                                        <div style="margin-top:10px;font-size:12px;color:#A0AEC0;
                                display:flex;flex-direction:column;gap:4px">
                                            <?php if ($c['country']): ?>
                                                <div>🌍 <?= htmlspecialchars($c['country']) ?></div>
                                            <?php endif; ?>
                                            <?php if ($c['interest']): ?>
                                                <div>💡 <?= htmlspecialchars($c['interest']) ?></div>
                                            <?php endif; ?>
                                            <?php if ($c['phone']): ?>
                                                <div>📞 <?= htmlspecialchars($c['phone']) ?></div>
                                            <?php endif; ?>
                                            <div>🔢 <?= $c['visit_count'] ?> visits</div>
                                            <div>📅 First: <?= date('M d, Y', strtotime($c['first_visit'])) ?></div>
                                        </div>

                                        <?php if ($crmColumnsExist): ?>
                                            <!-- Change status -->
                                            <form method="POST" style="display:flex;gap:6px;margin-top:12px">
                                                <input type="hidden" name="visitor_id" value="<?= $c['id'] ?>">
                                                <input type="hidden" name="action" value="set_status">
                                                <select name="status"
                                                    style="flex:1;background:#1A1A1A;border:1px solid #333;
                                       border-radius:8px;padding:6px 10px;color:#EDEDED;font-size:12px">
                                                    <option value="potential" <?= $crm === 'potential'    ? 'selected' : '' ?>>🟢 Potential</option>
                                                    <option value="follow_up" <?= $crm === 'follow_up'    ? 'selected' : '' ?>>🟡 Follow Up</option>
                                                    <option value="not_qualified" <?= $crm === 'not_qualified' ? 'selected' : '' ?>>⚪ Not Qualified</option>
                                                    <option value="blacklisted" <?= $crm === 'blacklisted'  ? 'selected' : '' ?>>🔴 Blocked</option>
                                                </select>
                                                <button type="submit"
                                                    style="background:#0070F3;border:none;color:#fff;
                                       padding:6px 12px;border-radius:6px;font-size:12px;cursor:pointer">
                                                    Save
                                                </button>
                                            </form>
                                        <?php endif; ?>

                                        <!-- Add note -->
                                        <?php if ($db->tableExists('crm_notes')): ?>
                                            <form method="POST" style="display:flex;gap:8px;margin-top:10px">
                                                <input type="hidden" name="visitor_id" value="<?= $c['id'] ?>">
                                                <input type="hidden" name="action" value="add_note">
                                                <input type="text" name="note"
                                                    placeholder="Add a note..."
                                                    style="flex:1;background:#1A1A1A;border:1px solid #333;
                                      border-radius:8px;padding:7px 10px;color:#EDEDED;font-size:12px">
                                                <button type="submit"
                                                    style="background:#0070F3;border:none;color:#fff;
                                       padding:6px 14px;border-radius:8px;font-size:12px;cursor:pointer">
                                                    +
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                        </tr>
                <?php endforeach;
                endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($total > $perPage):
        $pages   = ceil($total / $perPage);
        $baseUrl = "crm.php?status={$filterStatus}&q=" . urlencode($search) . "&page=";
    ?>
        <div style="display:flex;gap:6px;justify-content:center;margin-top:24px">
            <?php for ($p = 1; $p <= $pages; $p++): ?>
                <a href="<?= $baseUrl . $p ?>"
                    style="padding:6px 12px;border-radius:6px;text-decoration:none;font-size:12px;
              <?= $p === $page
                    ? 'background:#0070F3;color:#fff;border:1px solid #0070F3'
                    : 'background:#1A1A1A;color:#A0AEC0;border:1px solid #333' ?>">
                    <?= $p ?>
                </a>
            <?php endfor; ?>
        </div>
    <?php endif; ?>

</div>

<script>
    function toggleDetail(id) {
        const row = document.getElementById('detail-row-' + id);
        const open = row.style.display !== 'none';
        row.style.display = open ? 'none' : 'table-row';
        if (!open) loadChats(id);
    }

    async function loadChats(visitorId) {
        const container = document.getElementById('chat-' + visitorId);
        try {
            const r = await fetch('../api/chatbot.php?visitor_id=' + visitorId);
            const d = await r.json();
            if (!d.messages?.length) {
                container.innerHTML = '<div style="color:#4A5568;font-size:12px">No chat history.</div>';
                return;
            }
            container.innerHTML = d.messages.map(m => `
            <div style="display:flex;gap:8px;font-size:12px">
                <div style="padding:6px 10px;border-radius:8px;max-width:85%;line-height:1.5;
                     background:${m.sender==='user'?'#1A2640':'rgba(99,179,237,.1)'};
                     color:${m.sender==='user'?'#A0AEC0':'#EDEDED'}">
                    ${escHtml(m.message)}
                </div>
                <div style="font-size:10px;color:#4A5568;flex-shrink:0;margin-top:4px">
                    ${new Date(m.created_at).toLocaleTimeString('en-US',{hour:'2-digit',minute:'2-digit'})}
                </div>
            </div>`).join('');
            container.scrollTop = container.scrollHeight;
        } catch (e) {
            container.innerHTML = '<div style="color:#FC8181;font-size:12px">Error loading history.</div>';
        }
    }

    function confirmBlock(id, name) {
        const reason = prompt(`Block "${name || 'contact'}"?\nReason (optional):`);
        if (reason === null) return;
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
        <input name="action"     value="blacklist">
        <input name="visitor_id" value="${id}">
        <input name="reason"     value="${reason || 'Manually blocked'}">`;
        document.body.appendChild(form);
        form.submit();
    }

    function escHtml(str) {
        const d = document.createElement('div');
        d.appendChild(document.createTextNode(String(str || '')));
        return d.innerHTML.replace(/\n/g, '<br>');
    }
</script>

<?php include 'footer.php'; ?>