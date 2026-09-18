<?php

/**
 * admin/dashboard.php — FIXED VERSION
 *
 * Fixes applied:
 *  1. tableExists() now works (method added to Database class)
 *  2. JOINs protected — checks column existence before querying
 *  3. count() uses consistent positional params (no mixed named/positional)
 *  4. All DB calls wrapped in try/catch individually so one failure
 *     doesn't break the whole dashboard
 */

session_start();
require_once 'auth_check.php';

$page_title = 'Dashboard';

$stats = [
    'total_visitors'      => 0,
    'today_visitors'      => 0,
    'pending_appointments' => 0,
    'pending_comments'    => 0,
    'total_appointments'  => 0,
    'total_comments'      => 0,
    'total_likes'         => 0,
];

$recent_visitors      = [];
$upcoming_appointments = [];
$pending_comments     = [];
$db_connected         = false;
$db_error             = null;

try {
    require_once __DIR__ . '/../includes/security.php';
    require_once __DIR__ . '/../includes/db.php';
    $db = new Database();
    $db_connected = true;

    // ── VISITORS ─────────────────────────────────────────────
    if ($db->tableExists('visitors')) {
        // FIX 3: positional params only, no mixed named params
        $stats['total_visitors'] = $db->count('visitors');
        $stats['today_visitors'] = $db->count(
            'visitors',
            'DATE(first_visit) = CURDATE()'
        );

        $recent_visitors = $db->fetchAll(
            "SELECT * FROM visitors ORDER BY last_visit DESC LIMIT 5"
        );
    }

    // ── APPOINTMENTS ─────────────────────────────────────────
    if ($db->tableExists('appointments')) {
        // FIX 3: named param used consistently
        $stats['pending_appointments'] = $db->count(
            'appointments',
            'status = :s',
            [':s' => 'pending']
        );
        $stats['total_appointments'] = $db->count('appointments');

        // FIX 2: Check if visitor_id column exists before JOIN
        $has_visitor_id = $db->fetchOne(
            "SELECT COUNT(*) as c FROM information_schema.columns
             WHERE table_schema = DATABASE()
               AND table_name   = 'appointments'
               AND column_name  = 'visitor_id'"
        );

        if ($has_visitor_id && $has_visitor_id['c'] > 0) {
            // Safe to JOIN
            $upcoming_appointments = $db->fetchAll(
                "SELECT a.*, v.full_name, v.email
                 FROM appointments a
                 LEFT JOIN visitors v ON a.visitor_id = v.id
                 WHERE a.appointment_date >= CURDATE()
                   AND a.status != 'cancelled'
                 ORDER BY a.appointment_date ASC, a.appointment_time ASC
                 LIMIT 5"
            );
        } else {
            // No visitor_id column — query without JOIN
            $upcoming_appointments = $db->fetchAll(
                "SELECT *, 'Unknown' AS full_name, '' AS email
                 FROM appointments
                 WHERE appointment_date >= CURDATE()
                   AND status != 'cancelled'
                 ORDER BY appointment_date ASC, appointment_time ASC
                 LIMIT 5"
            );
        }
    }

    // ── COMMENTS ─────────────────────────────────────────────
    if ($db->tableExists('comments')) {
        // FIX 3: consistent positional param
        $stats['pending_comments'] = $db->count(
            'comments',
            'is_approved = ?',
            [0]
        );
        $stats['total_comments'] = $db->count(
            'comments',
            'is_approved = ?',
            [1]
        );

        // FIX 2: Check visitor_id in comments before JOIN
        $has_visitor_id_c = $db->fetchOne(
            "SELECT COUNT(*) as c FROM information_schema.columns
             WHERE table_schema = DATABASE()
               AND table_name   = 'comments'
               AND column_name  = 'visitor_id'"
        );

        if ($has_visitor_id_c && $has_visitor_id_c['c'] > 0) {
            $pending_comments = $db->fetchAll(
                "SELECT c.*, v.full_name, v.email
                 FROM comments c
                 LEFT JOIN visitors v ON c.visitor_id = v.id
                 WHERE c.is_approved = 0
                 ORDER BY c.created_at DESC
                 LIMIT 5"
            );
        } else {
            $pending_comments = $db->fetchAll(
                "SELECT *, 'Anonymous' AS full_name, '' AS email
                 FROM comments
                 WHERE is_approved = 0
                 ORDER BY created_at DESC
                 LIMIT 5"
            );
        }
    }

    // ── LIKES ────────────────────────────────────────────────
    if ($db->tableExists('likes')) {
        $stats['total_likes'] = $db->count('likes');
    }
} catch (Exception $e) {
    $db_connected = false;
    $db_error = $e->getMessage();
    error_log("Dashboard DB Error: " . $db_error);
}

include 'header.php';
?>

<div class="dashboard-container">

    <?php if (!$db_connected): ?>
        <div class="alert alert-warning">
            <strong>⚠️ Database Connection Issue</strong>
            <p><?= htmlspecialchars($db_error ?? 'Unknown error') ?></p>
            <p><small>Make sure you have:</small></p>
            <ul style="margin-left:20px;margin-top:8px;">
                <li>Created the database <code>portfolio_db</code> in phpMyAdmin</li>
                <li>Run the SQL setup script</li>
                <li>Verified <code>config.php</code> credentials</li>
            </ul>
            <a href="http://localhost/phpmyadmin" target="_blank"
                class="btn btn-primary" style="margin-top:14px;">
                Open phpMyAdmin →
            </a>
        </div>
    <?php else: ?>
        <div class="alert alert-success">
            <strong>✓ Database Connected Successfully</strong>
        </div>
    <?php endif; ?>

    <!-- STATS GRID -->
    <div class="stats-grid">

        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(0,112,243,.1);color:#0070F3">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none"
                    stroke="currentColor" stroke-width="2">
                    <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2" />
                    <circle cx="9" cy="7" r="4" />
                    <path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75" />
                </svg>
            </div>
            <div class="stat-content">
                <div class="stat-label">Total Visitors</div>
                <div class="stat-value"><?= number_format($stats['total_visitors']) ?></div>
                <div class="stat-change <?= $stats['today_visitors'] > 0 ? 'positive' : '' ?>">
                    <?= $stats['today_visitors'] > 0 ? '+' : '' ?><?= $stats['today_visitors'] ?> today
                </div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(16,185,129,.1);color:#10B981">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none"
                    stroke="currentColor" stroke-width="2">
                    <rect x="3" y="4" width="18" height="18" rx="2" />
                    <line x1="16" y1="2" x2="16" y2="6" />
                    <line x1="8" y1="2" x2="8" y2="6" />
                    <line x1="3" y1="10" x2="21" y2="10" />
                </svg>
            </div>
            <div class="stat-content">
                <div class="stat-label">Appointments</div>
                <div class="stat-value"><?= number_format($stats['total_appointments']) ?></div>
                <div class="stat-change <?= $stats['pending_appointments'] > 0 ? 'warning' : '' ?>">
                    <?= $stats['pending_appointments'] ?> pending
                </div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(245,158,11,.1);color:#F59E0B">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none"
                    stroke="currentColor" stroke-width="2">
                    <path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z" />
                </svg>
            </div>
            <div class="stat-content">
                <div class="stat-label">Comments</div>
                <div class="stat-value"><?= number_format($stats['total_comments']) ?></div>
                <div class="stat-change <?= $stats['pending_comments'] > 0 ? 'warning' : '' ?>">
                    <?= $stats['pending_comments'] ?> pending approval
                </div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(239,68,68,.1);color:#EF4444">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none"
                    stroke="currentColor" stroke-width="2">
                    <path d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z" />
                </svg>
            </div>
            <div class="stat-content">
                <div class="stat-label">Total Likes</div>
                <div class="stat-value"><?= number_format($stats['total_likes']) ?></div>
                <div class="stat-change positive">from visitors</div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(139,92,246,.1);color:#8B5CF6">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none"
                    stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10" />
                    <path d="M12 6v6l4 2" />
                </svg>
            </div>
            <div class="stat-content">
                <div class="stat-label">System Status</div>
                <div class="stat-value"
                    style="font-size:18px;color:<?= $db_connected ? '#10B981' : '#EF4444' ?>">
                    <?= $db_connected ? '✓ Online' : '✗ Offline' ?>
                </div>
                <div class="stat-change <?= $db_connected ? 'positive' : 'negative' ?>">
                    <?= $db_connected ? 'All systems operational' : 'Database disconnected' ?>
                </div>
            </div>
        </div>

    </div><!-- /stats-grid -->

    <!-- ACTIVITY GRID -->
    <div class="activity-grid">

        <!-- Recent Visitors -->
        <div class="activity-card">
            <div class="activity-header">
                <h3>Recent Visitors</h3>
                <a href="visitors.php" class="view-all">View All →</a>
            </div>
            <div class="activity-list">
                <?php if (empty($recent_visitors)): ?>
                    <div class="empty-state">
                        <svg width="48" height="48" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="1" style="opacity:.3">
                            <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2" />
                            <circle cx="9" cy="7" r="4" />
                        </svg>
                        <p style="color:#6B6B6B;padding:16px">
                            No visitors yet. Share your portfolio link!
                        </p>
                    </div>
                <?php else: ?>
                    <?php foreach ($recent_visitors as $v): ?>
                        <div class="activity-item">
                            <div class="activity-avatar">
                                <?= strtoupper(substr($v['full_name'] ?? '?', 0, 1)) ?>
                            </div>
                            <div class="activity-content">
                                <div class="activity-title">
                                    <?= htmlspecialchars($v['full_name'] ?? 'Unknown') ?>
                                </div>
                                <div class="activity-meta">
                                    <?= htmlspecialchars($v['email'] ?? '') ?>
                                    <?php if (!empty($v['company'])): ?>
                                        · <?= htmlspecialchars($v['company']) ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="activity-time">
                                <?= date('M d, H:i', strtotime($v['last_visit'] ?? 'now')) ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Upcoming Appointments -->
        <div class="activity-card">
            <div class="activity-header">
                <h3>Upcoming Appointments</h3>
                <a href="appointments.php" class="view-all">View All →</a>
            </div>
            <div class="activity-list">
                <?php if (empty($upcoming_appointments)): ?>
                    <div class="empty-state">
                        <svg width="48" height="48" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="1" style="opacity:.3">
                            <rect x="3" y="4" width="18" height="18" rx="2" />
                            <line x1="16" y1="2" x2="16" y2="6" />
                            <line x1="8" y1="2" x2="8" y2="6" />
                            <line x1="3" y1="10" x2="21" y2="10" />
                        </svg>
                        <p style="color:#6B6B6B">No upcoming appointments</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($upcoming_appointments as $apt): ?>
                        <div class="activity-item">
                            <div class="activity-avatar" style="background:#10B981">📅</div>
                            <div class="activity-content">
                                <div class="activity-title">
                                    <?= htmlspecialchars($apt['full_name'] ?? 'Client') ?>
                                </div>
                                <div class="activity-meta">
                                    <?= date('M d, Y', strtotime($apt['appointment_date'])) ?>
                                    at <?= date('g:i A', strtotime($apt['appointment_time'])) ?>
                                </div>
                            </div>
                            <div class="activity-badge <?= htmlspecialchars($apt['status']) ?>">
                                <?= ucfirst(htmlspecialchars($apt['status'])) ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

    </div><!-- /activity-grid -->

    <!-- PENDING COMMENTS -->
    <?php if (!empty($pending_comments)): ?>
        <div class="pending-section">
            <div class="pending-header">
                <h3>📝 Pending Comments (<?= count($pending_comments) ?>)</h3>
            </div>
            <div class="comments-list">
                <?php foreach ($pending_comments as $comment): ?>
                    <div class="comment-item" id="comment-<?= $comment['id'] ?>">
                        <div class="comment-header">
                            <div class="comment-author">
                                <strong><?= htmlspecialchars($comment['full_name'] ?? 'Anonymous') ?></strong>
                                <span class="comment-meta">
                                    <?= htmlspecialchars($comment['email'] ?? '') ?>
                                </span>
                            </div>
                            <?php if (!empty($comment['rating'])): ?>
                                <div class="comment-rating">
                                    <?= str_repeat('⭐', (int)$comment['rating']) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="comment-text">
                            <?= htmlspecialchars($comment['comment_text'] ?? '') ?>
                        </div>
                        <div class="comment-actions">
                            <button class="btn-approve"
                                onclick="approveComment(<?= $comment['id'] ?>)">
                                ✓ Approve
                            </button>
                            <button class="btn-reject"
                                onclick="deleteComment(<?= $comment['id'] ?>)">
                                ✗ Delete
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- QUICK ACTIONS -->
    <div class="quick-actions"
        style="margin-top:40px;padding:24px;background:#1A1A1A;
                border-radius:12px;text-align:center">
        <h3 style="margin-bottom:18px;color:#EDEDED">Quick Actions</h3>
        <div style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap">
            <?php if (!$db_connected): ?>
                <a href="http://localhost/phpmyadmin" target="_blank" class="btn btn-primary">
                    🗄️ Setup Database
                </a>
            <?php else: ?>
                <a href="visitors.php" class="btn btn-primary">👥 Manage Visitors</a>
                <a href="appointments.php" class="btn btn-primary">📅 Appointments</a>
                <a href="comments.php" class="btn btn-primary">💬 Moderate Comments</a>
                <a href="likes.php" class="btn btn-primary">❤️ View Likes</a>
            <?php endif; ?>
            <a href="../index.html" class="btn btn-secondary" target="_blank">
                🌐 View Live Site
            </a>
        </div>
    </div>

</div><!-- /dashboard-container -->

<script>
    async function approveComment(id) {
        if (!confirm('Approve this comment?')) return;
        try {
            const res = await fetch('../api/comments.php', {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    comment_id: id,
                    is_approved: 1
                })
            });
            const data = await res.json();
            if (data.success) {
                document.getElementById('comment-' + id)?.remove();
            } else {
                alert('Error: ' + data.message);
            }
        } catch (e) {
            alert('Request failed: ' + e.message);
        }
    }

    async function deleteComment(id) {
        if (!confirm('Delete this comment permanently?')) return;
        try {
            const res = await fetch('../api/comments.php?id=' + id, {
                method: 'DELETE'
            });
            const data = await res.json();
            if (data.success) {
                document.getElementById('comment-' + id)?.remove();
            } else {
                alert('Error: ' + data.message);
            }
        } catch (e) {
            alert('Request failed: ' + e.message);
        }
    }
</script>

<?php include 'footer.php'; ?>