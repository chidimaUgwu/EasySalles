<?php
// admin/shifts/requests.php
ob_start(); // Start output buffering

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_admin();

$page_title = "Shift Requests";
require_once ROOT_PATH . 'admin/includes/header.php';

// Handle request approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['approve_request'])) {
        $request_id = $_POST['request_id'];
        $admin_notes = $_POST['admin_notes'];
        
        // Get request details
        $stmt = $pdo->prepare("SELECT * FROM EASYSALLES_SHIFT_REQUESTS WHERE request_id = ?");
        $stmt->execute([$request_id]);
        $request = $stmt->fetch();
        
        if ($request) {
            // Update request status
            $stmt = $pdo->prepare("UPDATE EASYSALLES_SHIFT_REQUESTS 
                                  SET status = 'approved', 
                                      admin_notes = ?,
                                      approved_by = ?,
                                      approved_at = NOW()
                                  WHERE request_id = ?");
            $stmt->execute([$admin_notes, $_SESSION['user_id'], $request_id]);
            
            // Handle based on request type
            if ($request['request_type'] === 'swap' && $request['requested_shift_id']) {
                // Implement shift swap logic
                // This would update the user's shift assignment
            }
            
            $_SESSION['success'] = "Request approved successfully!";
        }
    }
    
    if (isset($_POST['reject_request'])) {
        $request_id = $_POST['request_id'];
        $admin_notes = $_POST['admin_notes'];
        
        $stmt = $pdo->prepare("UPDATE EASYSALLES_SHIFT_REQUESTS 
                              SET status = 'rejected', 
                                  admin_notes = ?,
                                  approved_by = ?,
                                  approved_at = NOW()
                              WHERE request_id = ?");
        $stmt->execute([$admin_notes, $_SESSION['user_id'], $request_id]);
        
        $_SESSION['success'] = "Request rejected!";
    }
    
    // JavaScript redirect to avoid header issues
    echo '<script>window.location.href = "requests.php";</script>';
    exit();
}

// Get pending requests
$stmt = $pdo->query("SELECT r.*, 
                    u.full_name as requester_name, 
                    u.username as requester_username,
                    s.shift_name,
                    a.full_name as approver_name
                    FROM EASYSALLES_SHIFT_REQUESTS r
                    LEFT JOIN EASYSALLES_USERS u ON r.user_id = u.user_id
                    LEFT JOIN EASYSALLES_SHIFTS s ON r.shift_id = s.shift_id
                    LEFT JOIN EASYSALLES_USERS a ON r.approved_by = a.user_id
                    WHERE r.status = 'pending'
                    ORDER BY 
                    CASE r.priority 
                        WHEN 'high' THEN 1
                        WHEN 'medium' THEN 2
                        WHEN 'low' THEN 3
                    END, r.created_at DESC");
$pending_requests = $stmt->fetchAll();

// Get recent decisions
$stmt = $pdo->query("SELECT r.*, 
                    u.full_name as requester_name,
                    s.shift_name
                    FROM EASYSALLES_SHIFT_REQUESTS r
                    LEFT JOIN EASYSALLES_USERS u ON r.user_id = u.user_id
                    LEFT JOIN EASYSALLES_SHIFTS s ON r.shift_id = s.shift_id
                    WHERE r.status IN ('approved', 'rejected')
                    ORDER BY r.approved_at DESC
                    LIMIT 10");
$recent_decisions = $stmt->fetchAll();

// Get request stats - FIXED THE SQL ERROR
$stmt = $pdo->query("SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count,
    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_count,
    SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_count,
    SUM(CASE WHEN priority = 'high' THEN 1 ELSE 0 END) as high_priority_count
    FROM EASYSALLES_SHIFT_REQUESTS 
    WHERE DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)");
$stats = $stmt->fetch();
?>

<style>
/* Dashboard Specific Styles */
.requests-dashboard {
    max-width: 1400px;
    margin: 0 auto;
    padding: 2rem;
    animation: fadeIn 0.6s ease-out;
}

.dashboard-header {
    margin-bottom: 3rem;
    animation: fadeInDown 0.6s ease-out;
}

.welcome-section {
    text-align: center;
    margin-bottom: 2rem;
}

.welcome-text h1 {
    font-family: 'Poppins', sans-serif;
    font-size: 2.5rem;
    font-weight: 700;
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    margin-bottom: 0.5rem;
}

.welcome-text p {
    color: #64748b;
    font-size: 1.1rem;
    max-width: 600px;
    margin: 0 auto;
}

/* Requests Grid */
.requests-grid {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 2rem;
    margin-bottom: 3rem;
}

@media (max-width: 1024px) {
    .requests-grid {
        grid-template-columns: 1fr;
    }
}

/* Request Cards */
.request-card {
    background: var(--card-bg);
    border-radius: 20px;
    padding: 2rem;
    box-shadow: 0 4px 25px rgba(0, 0, 0, 0.05);
    border: 1px solid var(--border);
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.request-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 12px 40px rgba(124, 58, 237, 0.15);
}

.request-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 5px;
    height: 100%;
    background: linear-gradient(135deg, var(--primary), var(--secondary));
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
}

.card-header h2 {
    font-family: 'Poppins', sans-serif;
    font-size: 1.8rem;
    font-weight: 600;
    color: var(--text);
    margin: 0;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.card-header i {
    color: var(--primary);
}

.pending-count {
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    color: white;
    padding: 0.5rem 1.25rem;
    border-radius: 20px;
    font-family: 'Poppins', sans-serif;
    font-weight: 700;
    font-size: 1.2rem;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 3rem;
    color: #64748b;
}

.empty-state i {
    font-size: 3rem;
    color: var(--border);
    margin-bottom: 1rem;
}

.empty-state h3 {
    font-family: 'Poppins', sans-serif;
    font-size: 1.5rem;
    margin-bottom: 0.5rem;
    color: var(--text);
}

/* Requests Table */
.requests-table {
    width: 100%;
    border-collapse: collapse;
}

.requests-table thead tr {
    background: linear-gradient(135deg, rgba(124, 58, 237, 0.1), rgba(236, 72, 153, 0.05));
}

.requests-table th {
    padding: 1rem 1.5rem;
    text-align: left;
    font-family: 'Poppins', sans-serif;
    font-weight: 600;
    color: var(--text);
    border-bottom: 1px solid var(--border);
}

.requests-table td {
    padding: 1rem 1.5rem;
    border-bottom: 1px solid var(--border);
}

.requests-table tbody tr {
    transition: all 0.3s ease;
}

.requests-table tbody tr:hover {
    background: linear-gradient(135deg, rgba(124, 58, 237, 0.05), rgba(236, 72, 153, 0.02));
    transform: translateX(5px);
}

/* Badge Styles */
.badge {
    display: inline-block;
    padding: 0.35rem 0.75rem;
    border-radius: 20px;
    font-size: 0.85rem;
    font-weight: 600;
    text-transform: uppercase;
}

.badge-swap {
    background: linear-gradient(135deg, #3B82F6, #1D4ED8);
    color: white;
}

.badge-timeoff {
    background: linear-gradient(135deg, #F59E0B, #D97706);
    color: white;
}

.badge-cover {
    background: linear-gradient(135deg, #6B7280, #4B5563);
    color: white;
}

.badge-change {
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    color: white;
}

.badge-priority-high {
    background: linear-gradient(135deg, #EF4444, #DC2626);
    color: white;
}

.badge-priority-medium {
    background: linear-gradient(135deg, #F59E0B, #D97706);
    color: white;
}

.badge-priority-low {
    background: linear-gradient(135deg, #10B981, #059669);
    color: white;
}

.badge-status-approved {
    background: linear-gradient(135deg, #10B981, #059669);
    color: white;
}

.badge-status-rejected {
    background: linear-gradient(135deg, #EF4444, #DC2626);
    color: white;
}

/* Action Buttons */
.action-buttons {
    display: flex;
    gap: 0.5rem;
}

.btn-action {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: none;
    cursor: pointer;
    transition: all 0.3s ease;
    font-size: 0.9rem;
}

.btn-approve {
    background: linear-gradient(135deg, #10B981, #059669);
    color: white;
}

.btn-approve:hover {
    transform: scale(1.05);
    box-shadow: 0 5px 15px rgba(16, 185, 129, 0.4);
}

.btn-reject {
    background: linear-gradient(135deg, #EF4444, #DC2626);
    color: white;
}

.btn-reject:hover {
    transform: scale(1.05);
    box-shadow: 0 5px 15px rgba(239, 68, 68, 0.4);
}

.btn-view {
    background: rgba(59, 130, 246, 0.1);
    color: #3B82F6;
}

.btn-view:hover {
    background: #3B82F6;
    color: white;
    transform: scale(1.05);
}

/* Statistics Cards */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1rem;
    margin-top: 1rem;
}

.stat-card {
    background: var(--bg);
    border-radius: 15px;
    padding: 1.5rem;
    text-align: center;
    border: 1px solid var(--border);
    transition: all 0.3s ease;
}

.stat-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
}

.stat-value {
    font-family: 'Poppins', sans-serif;
    font-size: 2rem;
    font-weight: 700;
    margin-bottom: 0.5rem;
}

.stat-label {
    font-size: 0.9rem;
    color: #64748b;
    font-weight: 500;
}

.stat-pending { color: #F59E0B; }
.stat-approved { color: #10B981; }
.stat-rejected { color: #EF4444; }
.stat-high { color: var(--primary); }

/* Recent Decisions */
.recent-decisions-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.decision-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem;
    border-bottom: 1px solid var(--border);
    transition: all 0.3s ease;
}

.decision-item:hover {
    background: rgba(124, 58, 237, 0.05);
    border-radius: 10px;
}

.decision-item:last-child {
    border-bottom: none;
}

.decision-info {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.staff-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
}

.staff-details h4 {
    font-weight: 600;
    margin: 0 0 0.25rem 0;
    color: var(--text);
}

.staff-details p {
    font-size: 0.85rem;
    color: #64748b;
    margin: 0;
}

.decision-status {
    font-size: 0.85rem;
    font-weight: 600;
}

/* Alert Messages */
.alert {
    padding: 1rem 1.5rem;
    border-radius: 12px;
    margin-bottom: 2rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    animation: slideDown 0.3s ease;
}

.alert-success {
    background: linear-gradient(135deg, rgba(16, 185, 129, 0.1), rgba(5, 150, 105, 0.05));
    border-left: 4px solid #10B981;
    color: #065F46;
}

/* Modal Styles */
.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    backdrop-filter: blur(5px);
    z-index: 1000;
    animation: fadeIn 0.3s ease;
}

.modal-content {
    background: var(--card-bg);
    border-radius: 20px;
    width: 90%;
    max-width: 600px;
    margin: 2rem auto;
    position: relative;
    animation: slideDown 0.4s ease;
    border: 1px solid var(--border);
}

.modal-header {
    padding: 1.5rem 2rem;
    border-bottom: 1px solid var(--border);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h4 {
    font-family: 'Poppins', sans-serif;
    font-size: 1.5rem;
    font-weight: 600;
    margin: 0;
    color: var(--text);
}

.close-modal {
    background: none;
    border: none;
    font-size: 1.5rem;
    color: #64748b;
    cursor: pointer;
    transition: color 0.3s ease;
}

.close-modal:hover {
    color: var(--text);
}

.modal-body {
    padding: 2rem;
}

.modal-footer {
    padding: 1.5rem 2rem;
    border-top: 1px solid var(--border);
    display: flex;
    justify-content: flex-end;
    gap: 1rem;
}

.btn-outline {
    background: transparent;
    border: 2px solid var(--border);
    color: var(--text);
    padding: 0.75rem 1.5rem;
    border-radius: 12px;
    font-family: 'Poppins', sans-serif;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-outline:hover {
    border-color: var(--primary);
    color: var(--primary);
    transform: translateY(-2px);
}

.btn-primary {
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    color: white;
    border: none;
    padding: 0.75rem 1.5rem;
    border-radius: 12px;
    font-family: 'Poppins', sans-serif;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(124, 58, 237, 0.3);
}

.btn-success {
    background: linear-gradient(135deg, #10B981, #059669);
    color: white;
    border: none;
    padding: 0.75rem 1.5rem;
    border-radius: 12px;
    font-family: 'Poppins', sans-serif;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-success:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(16, 185, 129, 0.3);
}

.btn-danger {
    background: linear-gradient(135deg, #EF4444, #DC2626);
    color: white;
    border: none;
    padding: 0.75rem 1.5rem;
    border-radius: 12px;
    font-family: 'Poppins', sans-serif;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-danger:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(239, 68, 68, 0.3);
}

/* Responsive Design */
@media (max-width: 768px) {
    .requests-dashboard {
        padding: 1.5rem;
    }
    
    .dashboard-header h1 {
        font-size: 2rem;
    }
    
    .requests-grid {
        gap: 1.5rem;
    }
    
    .request-card {
        padding: 1.5rem;
    }
    
    .card-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
    }
    
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .requests-table {
        display: block;
        overflow-x: auto;
    }
}

@media (max-width: 480px) {
    .requests-dashboard {
        padding: 1rem;
    }
    
    .action-buttons {
        flex-direction: column;
        width: 100%;
    }
    
    .btn-action {
        width: 100%;
        margin-bottom: 0.5rem;
    }
}

/* Animations */
@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes fadeInDown {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
</style>

<div class="requests-dashboard">
    <div class="dashboard-header">
        <div class="welcome-section">
            <div class="welcome-text">
                <h1>Shift Requests</h1>
                <p>Review and manage staff shift change requests</p>
            </div>
        </div>
    </div>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
        </div>
    <?php endif; ?>

    <div class="requests-grid">
        <!-- Pending Requests -->
        <div class="request-card">
            <div class="card-header">
                <h2><i class="fas fa-clock"></i> Pending Requests</h2>
                <div class="pending-count">
                    <?php echo count($pending_requests); ?> pending
                </div>
            </div>
            
            <?php if (empty($pending_requests)): ?>
                <div class="empty-state">
                    <i class="fas fa-check-circle"></i>
                    <h3>No Pending Requests</h3>
                    <p>All shift requests have been processed</p>
                </div>
            <?php else: ?>
                <div style="overflow-x: auto;">
                    <table class="requests-table">
                        <thead>
                            <tr>
                                <th>Staff</th>
                                <th>Type</th>
                                <th>Date(s)</th>
                                <th>Priority</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pending_requests as $request): 
                                $type_class = 'badge-' . $request['request_type'];
                                $priority_class = 'badge-priority-' . $request['priority'];
                            ?>
                            <tr>
                                <td>
                                    <div style="font-weight: 600;"><?php echo htmlspecialchars($request['requester_name'] ?: $request['requester_username']); ?></div>
                                    <div style="font-size: 0.85rem; color: #64748b;">
                                        <?php echo date('M j', strtotime($request['created_at'])); ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge <?php echo $type_class; ?>">
                                        <?php 
                                        $type_labels = [
                                            'swap' => 'Swap',
                                            'timeoff' => 'Time Off',
                                            'cover' => 'Cover',
                                            'change' => 'Change'
                                        ];
                                        echo $type_labels[$request['request_type']] ?? ucfirst($request['request_type']);
                                        ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($request['start_date']): ?>
                                        <?php echo date('M j', strtotime($request['start_date'])); ?>
                                        <?php if ($request['end_date'] && $request['end_date'] != $request['start_date']): ?>
                                            - <?php echo date('j', strtotime($request['end_date'])); ?>
                                        <?php endif; ?>
                                        <?php if ($request['shift_name']): ?>
                                            <div style="font-size: 0.85rem; color: #64748b;"><?php echo $request['shift_name']; ?></div>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge <?php echo $priority_class; ?>">
                                        <?php echo ucfirst($request['priority']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <button type="button" class="btn-action btn-approve" 
                                                onclick="reviewRequest(<?php echo $request['request_id']; ?>, 'approve', this)">
                                            <i class="fas fa-check"></i>
                                        </button>
                                        <button type="button" class="btn-action btn-reject"
                                                onclick="reviewRequest(<?php echo $request['request_id']; ?>, 'reject', this)">
                                            <i class="fas fa-times"></i>
                                        </button>
                                        <button type="button" class="btn-action btn-view"
                                                onclick="viewRequestDetails(<?php echo $request['request_id']; ?>, this)">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Right Sidebar -->
        <div>
            <!-- Recent Decisions -->
            <div class="request-card" style="margin-bottom: 1.5rem;">
                <div class="card-header">
                    <h2><i class="fas fa-history"></i> Recent Decisions</h2>
                </div>
                
                <?php if (empty($recent_decisions)): ?>
                    <div style="text-align: center; padding: 2rem; color: #64748b;">
                        <i class="fas fa-history" style="font-size: 2rem; opacity: 0.3;"></i>
                        <p style="margin-top: 1rem;">No recent decisions</p>
                    </div>
                <?php else: ?>
                    <div class="recent-decisions-list">
                        <?php foreach ($recent_decisions as $decision): 
                            $initials = substr($decision['requester_name'] ?: $decision['requester_username'], 0, 2);
                        ?>
                        <div class="decision-item">
                            <div class="decision-info">
                                <div class="staff-avatar">
                                    <?php echo strtoupper($initials); ?>
                                </div>
                                <div class="staff-details">
                                    <h4><?php echo htmlspecialchars($decision['requester_name'] ?: $decision['requester_username']); ?></h4>
                                    <p>
                                        <?php 
                                        $type_short = [
                                            'swap' => 'SW',
                                            'timeoff' => 'TO',
                                            'cover' => 'CV',
                                            'change' => 'CH'
                                        ];
                                        echo $type_short[$decision['request_type']] ?? substr($decision['request_type'], 0, 2);
                                        ?>
                                        • <?php echo date('M j', strtotime($decision['approved_at'])); ?>
                                    </p>
                                </div>
                            </div>
                            <div class="decision-status">
                                <?php if ($decision['status'] == 'approved'): ?>
                                    <span class="badge badge-status-approved">Approved</span>
                                <?php else: ?>
                                    <span class="badge badge-status-rejected">Rejected</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Request Statistics -->
            <div class="request-card">
                <div class="card-header">
                    <h2><i class="fas fa-chart-bar"></i> Request Statistics</h2>
                </div>
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-value stat-pending"><?php echo $stats['pending_count'] ?? 0; ?></div>
                        <div class="stat-label">Pending</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-value stat-approved"><?php echo $stats['approved_count'] ?? 0; ?></div>
                        <div class="stat-label">Approved</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-value stat-rejected"><?php echo $stats['rejected_count'] ?? 0; ?></div>
                        <div class="stat-label">Rejected</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-value stat-high"><?php echo $stats['high_priority_count'] ?? 0; ?></div>
                        <div class="stat-label">High Priority</div>
                    </div>
                </div>
                <div style="text-align: center; margin-top: 1rem; font-size: 0.85rem; color: #64748b;">
                    Last 30 days
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Review Modal -->
<div class="modal" id="reviewModal">
    <div class="modal-content">
        <div class="modal-header">
            <h4 id="reviewTitle">Review Request</h4>
            <button type="button" class="close-modal">&times;</button>
        </div>
        <form method="POST" id="reviewForm">
            <div class="modal-body">
                <input type="hidden" name="request_id" id="review_request_id">
                
                <div id="requestDetails" style="margin-bottom: 1.5rem; padding: 1.5rem; background: linear-gradient(135deg, rgba(124, 58, 237, 0.05), rgba(236, 72, 153, 0.02)); border-radius: 12px;">
                    <!-- Request details will be loaded here -->
                </div>
                
                <div class="form-group">
                    <label for="admin_notes" style="display: block; margin-bottom: 0.5rem; font-weight: 600;">
                        <i class="fas fa-sticky-note"></i> Admin Notes
                    </label>
                    <textarea class="form-control" id="admin_notes" name="admin_notes" rows="3" 
                              placeholder="Add notes about your decision..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-outline close-modal">Cancel</button>
                <button type="submit" name="approve_request" class="btn-success" id="approveBtn" style="display: none;">
                    <i class="fas fa-check"></i> Approve
                </button>
                <button type="submit" name="reject_request" class="btn-danger" id="rejectBtn" style="display: none;">
                    <i class="fas fa-times"></i> Reject
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Details Modal -->
<div class="modal" id="detailsModal">
    <div class="modal-content">
        <div class="modal-header">
            <h4>Request Details</h4>
            <button type="button" class="close-modal">&times;</button>
        </div>
        <div class="modal-body" id="detailsContent">
            <!-- Details will be loaded here -->
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-outline close-modal">Close</button>
        </div>
    </div>
</div>

<script>
// Store request data for quick access
const requestsData = <?php echo json_encode($pending_requests); ?>;
const recentData = <?php echo json_encode($recent_decisions); ?>;

function reviewRequest(requestId, action, button) {
    // Find the request in our data
    const request = requestsData.find(r => r.request_id == requestId);
    if (!request) return;
    
    // Set modal title and buttons
    document.getElementById('review_request_id').value = requestId;
    
    if (action === 'approve') {
        document.getElementById('reviewTitle').textContent = 'Approve Request';
        document.getElementById('approveBtn').style.display = 'inline-flex';
        document.getElementById('rejectBtn').style.display = 'none';
    } else {
        document.getElementById('reviewTitle').textContent = 'Reject Request';
        document.getElementById('approveBtn').style.display = 'none';
        document.getElementById('rejectBtn').style.display = 'inline-flex';
    }
    
    // Populate request details
    const typeLabels = {
        'swap': 'Shift Swap',
        'timeoff': 'Time Off',
        'cover': 'Shift Cover',
        'change': 'Shift Change'
    };
    
    const priorityLabels = {
        'high': 'High',
        'medium': 'Medium',
        'low': 'Low'
    };
    
    document.getElementById('requestDetails').innerHTML = `
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
            <div>
                <strong>Staff Member:</strong><br>
                ${request.requester_name || request.requester_username}
            </div>
            <div>
                <strong>Request Type:</strong><br>
                <span class="badge badge-${request.request_type}">
                    ${typeLabels[request.request_type] || request.request_type}
                </span>
            </div>
        </div>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
            <div>
                <strong>Date Range:</strong><br>
                ${request.start_date ? new Date(request.start_date).toLocaleDateString() : 'N/A'}
                ${request.end_date && request.end_date !== request.start_date ? 
                    ' to ' + new Date(request.end_date).toLocaleDateString() : ''}
            </div>
            <div>
                <strong>Priority:</strong><br>
                <span class="badge badge-priority-${request.priority}">
                    ${priorityLabels[request.priority]}
                </span>
            </div>
        </div>
        <div>
            <strong>Reason:</strong><br>
            <div style="margin-top: 0.5rem; padding: 0.75rem; background: rgba(255,255,255,0.5); border-radius: 8px;">
                ${request.reason || 'No reason provided'}
            </div>
        </div>
    `;
    
    // Show modal
    document.getElementById('reviewModal').style.display = 'block';
}

function viewRequestDetails(requestId, button) {
    // Try to find in pending requests first
    let request = requestsData.find(r => r.request_id == requestId);
    
    // If not found in pending, try recent decisions
    if (!request) {
        request = recentData.find(r => r.request_id == requestId);
    }
    
    if (!request) {
        // Try to fetch from server if not in local data
        fetchRequestDetails(requestId);
        return;
    }
    
    // Populate details
    populateDetailsModal(request);
}

function fetchRequestDetails(requestId) {
    // This would be an AJAX call to get full details
    // For now, we'll show a loading message
    document.getElementById('detailsContent').innerHTML = `
        <div style="text-align: center; padding: 2rem;">
            <i class="fas fa-spinner fa-spin"></i>
            <p style="margin-top: 1rem;">Loading request details...</p>
        </div>
    `;
    document.getElementById('detailsModal').style.display = 'block';
    
    // Simulate API call (in production, this would be a real AJAX call)
    setTimeout(() => {
        // This is a fallback - in real implementation, you'd fetch from server
        populateDetailsModal({
            requester_name: 'Unknown User',
            request_type: 'unknown',
            status: 'unknown',
            reason: 'Details not available'
        });
    }, 1000);
}

function populateDetailsModal(request) {
    const typeLabels = {
        'swap': 'Shift Swap',
        'timeoff': 'Time Off',
        'cover': 'Shift Cover',
        'change': 'Shift Change'
    };
    
    const statusLabels = {
        'pending': '<span class="badge badge-warning">Pending</span>',
        'approved': '<span class="badge badge-status-approved">Approved</span>',
        'rejected': '<span class="badge badge-status-rejected">Rejected</span>',
        'cancelled': '<span class="badge badge-secondary">Cancelled</span>'
    };
    
    document.getElementById('detailsContent').innerHTML = `
        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem; margin-bottom: 1.5rem;">
            <div style="background: linear-gradient(135deg, rgba(124, 58, 237, 0.1), rgba(236, 72, 153, 0.05)); padding: 1rem; border-radius: 10px;">
                <div style="font-size: 0.85rem; color: #64748b;">Staff Member</div>
                <div style="font-weight: 600; margin-top: 0.25rem;">${request.requester_name || request.requester_username || 'Unknown'}</div>
            </div>
            <div style="background: linear-gradient(135deg, rgba(59, 130, 246, 0.1), rgba(37, 99, 235, 0.05)); padding: 1rem; border-radius: 10px;">
                <div style="font-size: 0.85rem; color: #64748b;">Request Type</div>
                <div style="font-weight: 600; margin-top: 0.25rem;">${typeLabels[request.request_type] || request.request_type}</div>
            </div>
            <div style="background: linear-gradient(135deg, rgba(16, 185, 129, 0.1), rgba(5, 150, 105, 0.05)); padding: 1rem; border-radius: 10px;">
                <div style="font-size: 0.85rem; color: #64748b;">Status</div>
                <div style="font-weight: 600; margin-top: 0.25rem;">${statusLabels[request.status] || request.status}</div>
            </div>
            <div style="background: linear-gradient(135deg, rgba(245, 158, 11, 0.1), rgba(217, 119, 6, 0.05)); padding: 1rem; border-radius: 10px;">
                <div style="font-size: 0.85rem; color: #64748b;">Priority</div>
                <div style="font-weight: 600; margin-top: 0.25rem; text-transform: capitalize;">${request.priority}</div>
            </div>
        </div>
        
        <div style="margin-bottom: 1.5rem;">
            <div style="font-weight: 600; margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.5rem;">
                <i class="fas fa-calendar"></i> Date Information
            </div>
            <div style="padding: 1rem; background: #f8f9fa; border-radius: 8px;">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div>
                        <strong>Start Date:</strong><br>
                        ${request.start_date ? new Date(request.start_date).toLocaleDateString() : 'Not specified'}
                    </div>
                    <div>
                        <strong>End Date:</strong><br>
                        ${request.end_date ? new Date(request.end_date).toLocaleDateString() : 'Not specified'}
                    </div>
                </div>
                ${request.shift_name ? `
                <div style="margin-top: 0.5rem;">
                    <strong>Shift:</strong><br>
                    ${request.shift_name}
                </div>
                ` : ''}
            </div>
        </div>
        
        <div style="margin-bottom: 1.5rem;">
            <div style="font-weight: 600; margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.5rem;">
                <i class="fas fa-comment"></i> Reason
            </div>
            <div style="padding: 1rem; background: linear-gradient(135deg, rgba(124, 58, 237, 0.05), rgba(236, 72, 153, 0.02)); border-radius: 8px;">
                ${request.reason || 'No reason provided'}
            </div>
        </div>
        
        ${request.admin_notes ? `
        <div>
            <div style="font-weight: 600; margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.5rem;">
                <i class="fas fa-sticky-note"></i> Admin Notes
            </div>
            <div style="padding: 1rem; background: rgba(6, 182, 212, 0.1); border-radius: 8px;">
                ${request.admin_notes}
            </div>
        </div>
        ` : ''}
    `;
    
    document.getElementById('detailsModal').style.display = 'block';
}

// Close modals
document.querySelectorAll('.close-modal').forEach(btn => {
    btn.addEventListener('click', () => {
        document.getElementById('reviewModal').style.display = 'none';
        document.getElementById('detailsModal').style.display = 'none';
    });
});

// Close modals when clicking outside
window.addEventListener('click', (e) => {
    if (e.target.classList.contains('modal')) {
        e.target.style.display = 'none';
    }
});

// Add keyboard shortcuts
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.getElementById('reviewModal').style.display = 'none';
        document.getElementById('detailsModal').style.display = 'none';
    }
});

// Initialize tooltips
document.addEventListener('DOMContentLoaded', function() {
    // Add hover effects to action buttons
    document.querySelectorAll('.btn-action').forEach(btn => {
        btn.addEventListener('mouseenter', function() {
            const action = this.classList.contains('btn-approve') ? 'Approve' : 
                         this.classList.contains('btn-reject') ? 'Reject' : 'View';
            this.setAttribute('title', action);
        });
    });
});
</script>

<?php 
ob_flush(); // Flush output buffer
require_once ROOT_PATH . 'admin/includes/footer.php'; 
?>