<?php
session_start();
require_once 'auth_check.php';
require_once '../includes/db.php';

$page_title = 'Comments Management';
$db = new Database();

// Filtros
$status = $_GET['status'] ?? 'pending';
$project = $_GET['project'] ?? '';

// Construir query
$where = '1=1';
$params = [];

if ($status === 'pending') {
    $where .= ' AND c.is_approved = 0';
} elseif ($status === 'approved') {
    $where .= ' AND c.is_approved = 1';
}

if ($project) {
    $where .= ' AND c.project_id = :project';
    $params[':project'] = $project;
}

// Obtener comentarios
$comments = $db->query(
    "SELECT c.*, v.full_name, v.email, v.company 
     FROM comments c 
     JOIN visitors v ON c.visitor_id = v.id 
     WHERE $where 
     ORDER BY c.created_at DESC",
    $params
)->fetchAll();

// Proyectos únicos
$projects = $db->query(
    "SELECT DISTINCT project_id FROM comments WHERE project_id != '' ORDER BY project_id"
)->fetchAll();

include 'header.php';
?>

<div class="dashboard-container">
    <!-- Filtros -->
    <div class="filters-section">
        <div class="filter-tabs">
            <a href="?status=pending" class="filter-tab <?php echo $status === 'pending' ? 'active' : ''; ?>">
                Pending (<?php echo $db->count('comments', 'is_approved = 0'); ?>)
            </a>
            <a href="?status=approved" class="filter-tab <?php echo $status === 'approved' ? 'active' : ''; ?>">
                Approved (<?php echo $db->count('comments', 'is_approved = 1'); ?>)
            </a>
            <a href="?status=all" class="filter-tab <?php echo $status === 'all' ? 'active' : ''; ?>">
                All Comments
            </a>
        </div>

        <?php if (!empty($projects)): ?>
            <form method="GET" class="filters-form" style="margin-top: 16px;">
                <input type="hidden" name="status" value="<?php echo htmlspecialchars($status); ?>">
                <div class="filter-group">
                    <select name="project" class="filter-select">
                        <option value="">All Projects</option>
                        <?php foreach ($projects as $p): ?>
                            <option value="<?php echo htmlspecialchars($p['project_id']); ?>"
                                <?php echo $project === $p['project_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($p['project_id']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">Filter</button>
            </form>
        <?php endif; ?>
    </div>

    <!-- Comments List -->
    <div class="comments-list">
        <?php if (empty($comments)): ?>
            <div class="empty-state">
                <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1">
                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" />
                </svg>
                <p>No comments found</p>
            </div>
        <?php else: ?>
            <?php foreach ($comments as $comment): ?>
                <div class="comment-card <?php echo $comment['is_approved'] ? 'approved' : 'pending'; ?>">
                    <div class="comment-header">
                        <div class="comment-author">
                            <div class="author-avatar">
                                <?php echo strtoupper(substr($comment['full_name'], 0, 1)); ?>
                            </div>
                            <div class="author-info">
                                <strong><?php echo htmlspecialchars($comment['full_name']); ?></strong>
                                <div class="author-meta">
                                    <?php echo htmlspecialchars($comment['email']); ?>
                                    <?php if ($comment['company']): ?>
                                        • <?php echo htmlspecialchars($comment['company']); ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="comment-info">
                            <?php if ($comment['rating']): ?>
                                <div class="comment-rating">
                                    <?php echo str_repeat('⭐', $comment['rating']); ?>
                                </div>
                            <?php endif; ?>
                            <div class="comment-date">
                                <?php echo date('M d, Y H:i', strtotime($comment['created_at'])); ?>
                            </div>
                        </div>
                    </div>

                    <?php if ($comment['project_id']): ?>
                        <div class="comment-project">
                            📁 Project: <?php echo htmlspecialchars($comment['project_id']); ?>
                        </div>
                    <?php endif; ?>

                    <div class="comment-text">
                        <?php echo nl2br(htmlspecialchars($comment['comment_text'])); ?>
                    </div>

                    <div class="comment-actions">
                        <?php if (!$comment['is_approved']): ?>
                            <button onclick="approveComment(<?php echo $comment['id']; ?>)" class="btn btn-success btn-sm">
                                ✓ Approve
                            </button>
                        <?php else: ?>
                            <button onclick="unapproveComment(<?php echo $comment['id']; ?>)" class="btn btn-warning btn-sm">
                                ↶ Unapprove
                            </button>
                        <?php endif; ?>

                        <button onclick="deleteComment(<?php echo $comment['id']; ?>)" class="btn btn-danger btn-sm">
                            🗑 Delete
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<script>
    async function approveComment(commentId) {
        try {
            const response = await fetch('../api/comments.php', {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    comment_id: commentId,
                    is_approved: 1
                })
            });

            const result = await response.json();

            if (result.success) {
                location.reload();
            } else {
                alert('Error: ' + result.message);
            }
        } catch (error) {
            alert('An error occurred');
        }
    }

    async function unapproveComment(commentId) {
        if (!confirm('Unapprove this comment?')) return;

        try {
            const response = await fetch('../api/comments.php', {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    comment_id: commentId,
                    is_approved: 0
                })
            });

            const result = await response.json();

            if (result.success) {
                location.reload();
            } else {
                alert('Error: ' + result.message);
            }
        } catch (error) {
            alert('An error occurred');
        }
    }

    async function deleteComment(commentId) {
        if (!confirm('Delete this comment permanently?')) return;

        try {
            const response = await fetch('../api/comments.php?id=' + commentId, {
                method: 'DELETE'
            });

            const result = await response.json();

            if (result.success) {
                location.reload();
            } else {
                alert('Error: ' + result.message);
            }
        } catch (error) {
            alert('An error occurred');
        }
    }
</script>

<style>
    .filter-tabs {
        display: flex;
        gap: 8px;
        border-bottom: 2px solid var(--border-color);
        padding-bottom: 0;
    }

    .filter-tab {
        padding: 12px 24px;
        color: var(--text-secondary);
        text-decoration: none;
        border-bottom: 2px solid transparent;
        margin-bottom: -2px;
        transition: all 0.2s;
        font-weight: 600;
    }

    .filter-tab:hover {
        color: var(--text-primary);
    }

    .filter-tab.active {
        color: var(--accent-blue);
        border-bottom-color: var(--accent-blue);
    }

    .comments-list {
        display: flex;
        flex-direction: column;
        gap: 16px;
    }

    .comment-card {
        background: var(--bg-secondary);
        border: 1px solid var(--border-color);
        border-left: 4px solid var(--border-color);
        border-radius: 12px;
        padding: 24px;
    }

    .comment-card.pending {
        border-left-color: var(--warning);
    }

    .comment-card.approved {
        border-left-color: var(--success);
    }

    .comment-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 16px;
    }

    .comment-author {
        display: flex;
        gap: 12px;
        align-items: center;
    }

    .author-avatar {
        width: 48px;
        height: 48px;
        background: var(--bg-tertiary);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 18px;
    }

    .author-info strong {
        display: block;
        font-size: 16px;
        color: var(--text-primary);
    }

    .author-meta {
        font-size: 13px;
        color: var(--text-secondary);
    }

    .comment-info {
        text-align: right;
    }

    .comment-rating {
        color: #F59E0B;
        margin-bottom: 4px;
    }

    .comment-date {
        font-size: 12px;
        color: var(--text-muted);
    }

    .comment-project {
        font-size: 13px;
        color: var(--text-secondary);
        background: var(--bg-primary);
        padding: 8px 12px;
        border-radius: 6px;
        display: inline-block;
        margin-bottom: 12px;
    }

    .comment-text {
        font-size: 15px;
        color: var(--text-primary);
        line-height: 1.6;
        margin-bottom: 16px;
    }

    .comment-actions {
        display: flex;
        gap: 8px;
        padding-top: 16px;
        border-top: 1px solid var(--border-color);
    }

    .btn-warning {
        background: var(--warning);
        color: white;
    }

    .btn-warning:hover {
        background: #D97706;
    }
</style>

<?php include 'footer.php'; ?>