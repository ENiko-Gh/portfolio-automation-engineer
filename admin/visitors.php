<?php
session_start();
require_once 'auth_check.php';
require_once '../includes/db.php';

$page_title = 'Visitors Management';
$db = new Database();

// Filtros
$search = $_GET['search'] ?? '';
$country = $_GET['country'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';

// Construir query con filtros
$where = '1=1';
$params = [];

if ($search) {
    $where .= ' AND (full_name LIKE :search OR email LIKE :search OR company LIKE :search)';
    $params[':search'] = "%$search%";
}

if ($country) {
    $where .= ' AND country = :country';
    $params[':country'] = $country;
}

if ($dateFrom) {
    $where .= ' AND DATE(first_visit) >= :date_from';
    $params[':date_from'] = $dateFrom;
}

if ($dateTo) {
    $where .= ' AND DATE(first_visit) <= :date_to';
    $params[':date_to'] = $dateTo;
}

// Paginación
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Obtener visitantes
$visitors = $db->query(
    "SELECT v.*, 
            (SELECT COUNT(*) FROM comments WHERE visitor_id = v.id) as comment_count,
            (SELECT COUNT(*) FROM likes WHERE visitor_id = v.id) as like_count,
            (SELECT COUNT(*) FROM appointments WHERE visitor_id = v.id) as appointment_count
     FROM visitors v 
     WHERE $where 
     ORDER BY last_visit DESC 
     LIMIT $perPage OFFSET $offset",
    $params
)->fetchAll();

// Total de registros para paginación
$totalVisitors = $db->count('visitors', $where, $params);
$totalPages = ceil($totalVisitors / $perPage);

// Obtener países únicos para filtro
$countries = $db->query(
    "SELECT DISTINCT country FROM visitors WHERE country != '' ORDER BY country"
)->fetchAll();

include 'header.php';
?>

<div class="dashboard-container">
    <!-- Filtros -->
    <div class="filters-section">
        <form method="GET" class="filters-form">
            <div class="filter-group">
                <input
                    type="text"
                    name="search"
                    placeholder="Search by name, email, or company..."
                    value="<?php echo htmlspecialchars($search); ?>"
                    class="filter-input">
            </div>

            <div class="filter-group">
                <select name="country" class="filter-select">
                    <option value="">All Countries</option>
                    <?php foreach ($countries as $c): ?>
                        <option value="<?php echo htmlspecialchars($c['country']); ?>"
                            <?php echo $country === $c['country'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($c['country']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-group">
                <input
                    type="date"
                    name="date_from"
                    value="<?php echo htmlspecialchars($dateFrom); ?>"
                    class="filter-input"
                    placeholder="From date">
            </div>

            <div class="filter-group">
                <input
                    type="date"
                    name="date_to"
                    value="<?php echo htmlspecialchars($dateTo); ?>"
                    class="filter-input"
                    placeholder="To date">
            </div>

            <button type="submit" class="btn btn-primary">Apply Filters</button>
            <a href="visitors.php" class="btn btn-secondary">Clear</a>
        </form>
    </div>

    <!-- Export Button -->
    <div class="actions-bar">
        <div class="results-count">
            Showing <?php echo count($visitors); ?> of <?php echo $totalVisitors; ?> visitors
        </div>
        <button onclick="exportToCSV()" class="btn btn-secondary">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                <polyline points="7 10 12 15 17 10" />
                <line x1="12" y1="15" x2="12" y2="3" />
            </svg>
            Export to CSV
        </button>
    </div>

    <!-- Visitors Table -->
    <div class="data-table">
        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Company</th>
                    <th>Country</th>
                    <th>First Visit</th>
                    <th>Visits</th>
                    <th>Engagement</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($visitors as $visitor): ?>
                    <tr>
                        <td>
                            <div class="visitor-name">
                                <strong><?php echo htmlspecialchars($visitor['full_name']); ?></strong>
                                <?php if ($visitor['job_title']): ?>
                                    <small><?php echo htmlspecialchars($visitor['job_title']); ?></small>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <a href="mailto:<?php echo htmlspecialchars($visitor['email']); ?>">
                                <?php echo htmlspecialchars($visitor['email']); ?>
                            </a>
                        </td>
                        <td><?php echo htmlspecialchars($visitor['company'] ?: '-'); ?></td>
                        <td><?php echo htmlspecialchars($visitor['country'] ?: '-'); ?></td>
                        <td><?php echo date('M d, Y', strtotime($visitor['first_visit'])); ?></td>
                        <td>
                            <span class="badge"><?php echo $visitor['visit_count']; ?> visits</span>
                        </td>
                        <td>
                            <div class="engagement-metrics">
                                <?php if ($visitor['comment_count'] > 0): ?>
                                    <span class="metric" title="Comments">💬 <?php echo $visitor['comment_count']; ?></span>
                                <?php endif; ?>
                                <?php if ($visitor['like_count'] > 0): ?>
                                    <span class="metric" title="Likes">❤️ <?php echo $visitor['like_count']; ?></span>
                                <?php endif; ?>
                                <?php if ($visitor['appointment_count'] > 0): ?>
                                    <span class="metric" title="Appointments">📅 <?php echo $visitor['appointment_count']; ?></span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <button onclick="viewVisitor(<?php echo $visitor['id']; ?>)" class="btn-icon" title="View Details">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                                    <circle cx="12" cy="12" r="3" />
                                </svg>
                            </button>
                            <button onclick="deleteVisitor(<?php echo $visitor['id']; ?>)" class="btn-icon btn-danger" title="Delete">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="3 6 5 6 21 6" />
                                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" />
                                </svg>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Paginación -->
    <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="?page=<?php echo $page - 1; ?>&<?php echo http_build_query($_GET); ?>" class="pagination-link">← Previous</a>
            <?php endif; ?>

            <span class="pagination-info">Page <?php echo $page; ?> of <?php echo $totalPages; ?></span>

            <?php if ($page < $totalPages): ?>
                <a href="?page=<?php echo $page + 1; ?>&<?php echo http_build_query($_GET); ?>" class="pagination-link">Next →</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Modal de Detalles del Visitante -->
<div id="visitorModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Visitor Details</h3>
            <button class="modal-close" onclick="closeModal()">&times;</button>
        </div>
        <div id="visitorDetails" class="modal-body">
            <!-- Se cargará dinámicamente -->
        </div>
    </div>
</div>

<script>
    async function viewVisitor(visitorId) {
        const modal = document.getElementById('visitorModal');
        const detailsDiv = document.getElementById('visitorDetails');

        detailsDiv.innerHTML = '<div class="loading">Loading...</div>';
        modal.style.display = 'flex';

        try {
            const response = await fetch(`../api/visitors.php?id=${visitorId}`);
            const result = await response.json();

            if (result.success) {
                const visitor = result.data;
                detailsDiv.innerHTML = `
                <div class="visitor-detail-grid">
                    <div class="detail-item">
                        <label>Full Name:</label>
                        <value>${visitor.full_name}</value>
                    </div>
                    <div class="detail-item">
                        <label>Email:</label>
                        <value><a href="mailto:${visitor.email}">${visitor.email}</a></value>
                    </div>
                    <div class="detail-item">
                        <label>Phone:</label>
                        <value>${visitor.phone || '-'}</value>
                    </div>
                    <div class="detail-item">
                        <label>Company:</label>
                        <value>${visitor.company || '-'}</value>
                    </div>
                    <div class="detail-item">
                        <label>Job Title:</label>
                        <value>${visitor.job_title || '-'}</value>
                    </div>
                    <div class="detail-item">
                        <label>Country:</label>
                        <value>${visitor.country || '-'}</value>
                    </div>
                    <div class="detail-item">
                        <label>Interest:</label>
                        <value>${visitor.interest || '-'}</value>
                    </div>
                    <div class="detail-item">
                        <label>IP Address:</label>
                        <value>${visitor.ip_address}</value>
                    </div>
                    <div class="detail-item">
                        <label>First Visit:</label>
                        <value>${new Date(visitor.first_visit).toLocaleString()}</value>
                    </div>
                    <div class="detail-item">
                        <label>Last Visit:</label>
                        <value>${new Date(visitor.last_visit).toLocaleString()}</value>
                    </div>
                    <div class="detail-item">
                        <label>Total Visits:</label>
                        <value>${visitor.visit_count}</value>
                    </div>
                    <div class="detail-item">
                        <label>Language:</label>
                        <value>${visitor.language.toUpperCase()}</value>
                    </div>
                </div>
            `;
            }
        } catch (error) {
            detailsDiv.innerHTML = '<div class="error">Error loading visitor details</div>';
        }
    }

    function closeModal() {
        document.getElementById('visitorModal').style.display = 'none';
    }

    async function deleteVisitor(visitorId) {
        if (!confirm('Are you sure you want to delete this visitor? This will also delete all their comments, likes, and appointments.')) {
            return;
        }

        try {
            const response = await fetch(`../api/visitors.php?id=${visitorId}`, {
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

    function exportToCSV() {
        const searchParams = new URLSearchParams(window.location.search);
        searchParams.set('export', 'csv');
        window.location.href = 'export_visitors.php?' + searchParams.toString();
    }

    // Cerrar modal al hacer click fuera
    window.onclick = function(event) {
        const modal = document.getElementById('visitorModal');
        if (event.target === modal) {
            closeModal();
        }
    }
</script>

<style>
    /* Estilos adicionales para visitors.php */
    .filters-section {
        background: var(--bg-secondary);
        border: 1px solid var(--border-color);
        border-radius: 12px;
        padding: 24px;
        margin-bottom: 24px;
    }

    .filters-form {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 16px;
        align-items: end;
    }

    .filter-input,
    .filter-select {
        width: 100%;
        padding: 10px 12px;
        background: var(--bg-primary);
        border: 1px solid var(--border-color);
        border-radius: 6px;
        color: var(--text-primary);
        font-size: 14px;
    }

    .filter-input:focus,
    .filter-select:focus {
        outline: none;
        border-color: var(--accent-blue);
    }

    .actions-bar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 16px;
    }

    .results-count {
        font-size: 14px;
        color: var(--text-secondary);
    }

    .visitor-name {
        display: flex;
        flex-direction: column;
    }

    .visitor-name small {
        color: var(--text-muted);
        font-size: 12px;
    }

    .badge {
        padding: 4px 8px;
        background: var(--bg-tertiary);
        border-radius: 4px;
        font-size: 12px;
        color: var(--text-secondary);
    }

    .engagement-metrics {
        display: flex;
        gap: 8px;
    }

    .metric {
        font-size: 12px;
    }

    .btn-icon {
        padding: 6px;
        background: transparent;
        border: 1px solid var(--border-color);
        border-radius: 4px;
        cursor: pointer;
        color: var(--text-secondary);
        margin-right: 4px;
        transition: all 0.2s;
    }

    .btn-icon:hover {
        background: var(--bg-tertiary);
        color: var(--text-primary);
    }

    .btn-icon.btn-danger:hover {
        background: rgba(239, 68, 68, 0.1);
        color: var(--error);
        border-color: var(--error);
    }

    .pagination {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 16px;
        margin-top: 24px;
    }

    .pagination-link {
        padding: 8px 16px;
        background: var(--bg-secondary);
        border: 1px solid var(--border-color);
        border-radius: 6px;
        color: var(--text-primary);
        text-decoration: none;
        font-size: 14px;
        transition: all 0.2s;
    }

    .pagination-link:hover {
        background: var(--bg-tertiary);
    }

    .pagination-info {
        font-size: 14px;
        color: var(--text-secondary);
    }

    /* Modal */
    .modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.8);
        align-items: center;
        justify-content: center;
        z-index: 1000;
    }

    .modal-content {
        background: var(--bg-secondary);
        border: 1px solid var(--border-color);
        border-radius: 12px;
        width: 90%;
        max-width: 600px;
        max-height: 80vh;
        overflow-y: auto;
    }

    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 24px;
        border-bottom: 1px solid var(--border-color);
    }

    .modal-header h3 {
        margin: 0;
        font-size: 18px;
    }

    .modal-close {
        background: none;
        border: none;
        font-size: 24px;
        color: var(--text-secondary);
        cursor: pointer;
        padding: 0;
        width: 30px;
        height: 30px;
    }

    .modal-close:hover {
        color: var(--text-primary);
    }

    .modal-body {
        padding: 24px;
    }

    .visitor-detail-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 16px;
    }

    .detail-item {
        display: flex;
        flex-direction: column;
        gap: 4px;
    }

    .detail-item label {
        font-size: 12px;
        color: var(--text-muted);
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .detail-item value {
        font-size: 14px;
        color: var(--text-primary);
    }

    .loading,
    .error {
        text-align: center;
        padding: 48px;
        color: var(--text-secondary);
    }

    .error {
        color: var(--error);
    }
</style>

<?php include 'footer.php'; ?>