<?php
session_start();
require_once 'auth_check.php';
require_once '../includes/db.php';

$page_title = 'Appointments Management';
$db = new Database();

// Filtros
$status = $_GET['status'] ?? '';
$dateFilter = $_GET['date_filter'] ?? 'upcoming';

// Construir query
$where = '1=1';
$params = [];

if ($status) {
    $where .= ' AND a.status = :status';
    $params[':status'] = $status;
}

switch ($dateFilter) {
    case 'today':
        $where .= ' AND a.appointment_date = CURDATE()';
        break;
    case 'week':
        $where .= ' AND a.appointment_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)';
        break;
    case 'upcoming':
        $where .= ' AND a.appointment_date >= CURDATE()';
        break;
    case 'past':
        $where .= ' AND a.appointment_date < CURDATE()';
        break;
}

// Obtener citas
$appointments = $db->query(
    "SELECT a.*, v.full_name, v.email, v.phone, v.company 
     FROM appointments a 
     JOIN visitors v ON a.visitor_id = v.id 
     WHERE $where 
     ORDER BY a.appointment_date ASC, a.appointment_time ASC",
    $params
)->fetchAll();

include 'header.php';
?>

<div class="dashboard-container">
    <!-- Filtros -->
    <div class="filters-section">
        <form method="GET" class="filters-form">
            <div class="filter-group">
                <select name="date_filter" class="filter-select">
                    <option value="upcoming" <?php echo $dateFilter === 'upcoming' ? 'selected' : ''; ?>>Upcoming</option>
                    <option value="today" <?php echo $dateFilter === 'today' ? 'selected' : ''; ?>>Today</option>
                    <option value="week" <?php echo $dateFilter === 'week' ? 'selected' : ''; ?>>This Week</option>
                    <option value="past" <?php echo $dateFilter === 'past' ? 'selected' : ''; ?>>Past</option>
                    <option value="all" <?php echo $dateFilter === 'all' ? 'selected' : ''; ?>>All</option>
                </select>
            </div>

            <div class="filter-group">
                <select name="status" class="filter-select">
                    <option value="">All Statuses</option>
                    <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="confirmed" <?php echo $status === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                    <option value="completed" <?php echo $status === 'completed' ? 'selected' : ''; ?>>Completed</option>
                    <option value="cancelled" <?php echo $status === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                </select>
            </div>

            <button type="submit" class="btn btn-primary">Apply Filters</button>
            <a href="appointments.php" class="btn btn-secondary">Clear</a>
        </form>
    </div>

    <!-- Appointments List -->
    <div class="appointments-grid">
        <?php if (empty($appointments)): ?>
            <div class="empty-state">
                <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1">
                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2" />
                    <line x1="16" y1="2" x2="16" y2="6" />
                    <line x1="8" y1="2" x2="8" y2="6" />
                    <line x1="3" y1="10" x2="21" y2="10" />
                </svg>
                <p>No appointments found</p>
            </div>
        <?php else: ?>
            <?php foreach ($appointments as $apt): ?>
                <div class="appointment-card status-<?php echo $apt['status']; ?>">
                    <div class="appointment-header">
                        <div class="appointment-date">
                            <div class="date-day"><?php echo date('d', strtotime($apt['appointment_date'])); ?></div>
                            <div class="date-month"><?php echo date('M', strtotime($apt['appointment_date'])); ?></div>
                        </div>
                        <div class="appointment-time">
                            <?php echo date('g:i A', strtotime($apt['appointment_time'])); ?>
                        </div>
                        <div class="appointment-status status-<?php echo $apt['status']; ?>">
                            <?php echo ucfirst($apt['status']); ?>
                        </div>
                    </div>

                    <div class="appointment-body">
                        <h4><?php echo htmlspecialchars($apt['full_name']); ?></h4>
                        <div class="appointment-meta">
                            <span>📧 <?php echo htmlspecialchars($apt['email']); ?></span>
                            <?php if ($apt['phone']): ?>
                                <span>📱 <?php echo htmlspecialchars($apt['phone']); ?></span>
                            <?php endif; ?>
                            <?php if ($apt['company']): ?>
                                <span>🏢 <?php echo htmlspecialchars($apt['company']); ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="appointment-type">
                            <strong>Type:</strong> <?php echo ucfirst(str_replace('_', ' ', $apt['meeting_type'])); ?>
                        </div>

                        <?php if ($apt['description']): ?>
                            <div class="appointment-description">
                                <strong>Details:</strong>
                                <p><?php echo nl2br(htmlspecialchars($apt['description'])); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="appointment-actions">
                        <?php if ($apt['status'] === 'pending'): ?>
                            <button onclick="updateStatus(<?php echo $apt['id']; ?>, 'confirmed')" class="btn btn-success btn-sm">
                                ✓ Confirm
                            </button>
                            <button onclick="updateStatus(<?php echo $apt['id']; ?>, 'cancelled')" class="btn btn-danger btn-sm">
                                ✗ Cancel
                            </button>
                        <?php elseif ($apt['status'] === 'confirmed'): ?>
                            <button onclick="updateStatus(<?php echo $apt['id']; ?>, 'completed')" class="btn btn-primary btn-sm">
                                ✓ Mark as Completed
                            </button>
                            <button onclick="updateStatus(<?php echo $apt['id']; ?>, 'cancelled')" class="btn btn-danger btn-sm">
                                Cancel
                            </button>
                        <?php endif; ?>

                        <button onclick="sendReminder(<?php echo $apt['id']; ?>)" class="btn btn-secondary btn-sm">
                            📧 Send Reminder
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<script>
    async function updateStatus(appointmentId, newStatus) {
        const confirmMessages = {
            'confirmed': 'Confirm this appointment?',
            'cancelled': 'Cancel this appointment?',
            'completed': 'Mark this appointment as completed?'
        };

        if (!confirm(confirmMessages[newStatus])) return;

        try {
            const response = await fetch('../api/appointments.php', {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    appointment_id: appointmentId,
                    status: newStatus
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

    async function sendReminder(appointmentId) {
        if (!confirm('Send reminder email to the client?')) return;

        try {
            const response = await fetch(`send_reminder.php?id=${appointmentId}`);
            const result = await response.json();

            if (result.success) {
                alert('Reminder sent successfully!');
            } else {
                alert('Error: ' + result.message);
            }
        } catch (error) {
            alert('An error occurred');
        }
    }
</script>

<style>
    .appointments-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
        gap: 24px;
    }

    .appointment-card {
        background: var(--bg-secondary);
        border: 1px solid var(--border-color);
        border-left: 4px solid var(--border-color);
        border-radius: 12px;
        overflow: hidden;
        transition: all 0.2s;
    }

    .appointment-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
    }

    .appointment-card.status-pending {
        border-left-color: var(--warning);
    }

    .appointment-card.status-confirmed {
        border-left-color: var(--success);
    }

    .appointment-card.status-completed {
        border-left-color: var(--accent-blue);
    }

    .appointment-card.status-cancelled {
        border-left-color: var(--error);
        opacity: 0.7;
    }

    .appointment-header {
        display: flex;
        align-items: center;
        gap: 16px;
        padding: 20px;
        background: var(--bg-primary);
        border-bottom: 1px solid var(--border-color);
    }

    .appointment-date {
        display: flex;
        flex-direction: column;
        align-items: center;
        background: var(--bg-secondary);
        padding: 12px;
        border-radius: 8px;
        min-width: 60px;
    }

    .date-day {
        font-size: 24px;
        font-weight: 700;
        color: var(--text-primary);
        line-height: 1;
    }

    .date-month {
        font-size: 12px;
        color: var(--text-secondary);
        text-transform: uppercase;
    }

    .appointment-time {
        font-size: 16px;
        font-weight: 600;
        color: var(--text-primary);
    }

    .appointment-status {
        margin-left: auto;
        padding: 6px 12px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 600;
    }

    .appointment-status.status-pending {
        background: rgba(245, 158, 11, 0.1);
        color: var(--warning);
    }

    .appointment-status.status-confirmed {
        background: rgba(16, 185, 129, 0.1);
        color: var(--success);
    }

    .appointment-status.status-completed {
        background: rgba(0, 112, 243, 0.1);
        color: var(--accent-blue);
    }

    .appointment-status.status-cancelled {
        background: rgba(239, 68, 68, 0.1);
        color: var(--error);
    }

    .appointment-body {
        padding: 20px;
    }

    .appointment-body h4 {
        font-size: 18px;
        margin-bottom: 12px;
        color: var(--text-primary);
    }

    .appointment-meta {
        display: flex;
        flex-direction: column;
        gap: 6px;
        margin-bottom: 12px;
    }

    .appointment-meta span {
        font-size: 13px;
        color: var(--text-secondary);
    }

    .appointment-type {
        font-size: 14px;
        color: var(--text-secondary);
        margin-bottom: 12px;
    }

    .appointment-description {
        margin-top: 12px;
        padding-top: 12px;
        border-top: 1px solid var(--border-color);
    }

    .appointment-description strong {
        display: block;
        font-size: 13px;
        color: var(--text-secondary);
        margin-bottom: 6px;
    }

    .appointment-description p {
        font-size: 14px;
        color: var(--text-primary);
        margin: 0;
    }

    .appointment-actions {
        display: flex;
        gap: 8px;
        padding: 16px 20px;
        border-top: 1px solid var(--border-color);
        background: var(--bg-primary);
    }

    .btn-sm {
        padding: 6px 12px;
        font-size: 13px;
    }

    .btn-success {
        background: var(--success);
        color: white;
    }

    .btn-success:hover {
        background: #0D9B6D;
    }
</style>

<?php include 'footer.php'; ?>