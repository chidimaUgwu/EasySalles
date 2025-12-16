<?php
// admin/attendance/requests.php
$page_title = "Attendance Requests";
require_once '../includes/header.php';

$status_filter = $_GET['status'] ?? 'pending';
$type_filter = $_GET['type'] ?? 'all';
$date_from = $_GET['date_from'] ?? date('Y-m-01');
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$user_id = $_GET['user_id'] ?? '';

// Get all attendance requests
$requests = [];
try {
    $query = "SELECT 
        ar.*,
        u.full_name,
        u.username,
        u.email,
        a.full_name as approver_name
        FROM EASYSALLES_ATTENDANCE_REQUESTS ar
        LEFT JOIN EASYSALLES_USERS u ON ar.user_id = u.user_id
        LEFT JOIN EASYSALLES_USERS a ON ar.approved_by = a.user_id
        WHERE ar.start_date BETWEEN ? AND ?";
    
    $params = [$date_from, $date_to];
    
    if ($status_filter !== 'all') {
        $query .= " AND ar.status = ?";
        $params[] = $status_filter;
    }
    
    if ($type_filter !== 'all') {
        $query .= " AND ar.request_type = ?";
        $params[] = $type_filter;
    }
    
    if (!empty($user_id)) {
        $query .= " AND ar.user_id = ?";
        $params[] = $user_id;
    }
    
    $query .= " ORDER BY ar.created_at DESC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $requests = $stmt->fetchAll();
} catch (PDOException $e) {
    // Table might not exist
    $requests = [];
}

// Get all staff for filter
$all_staff = [];
try {
    $stmt = $pdo->query("SELECT user_id, username, full_name FROM EASYSALLES_USERS WHERE role = 2 ORDER BY full_name");
    $all_staff = $stmt->fetchAll();
} catch (PDOException $e) {
    // Handle error
}

// Calculate stats
$stats = [
    'total' => count($requests),
    'pending' => count(array_filter($requests, fn($r) => $r['status'] === 'pending')),
    'approved' => count(array_filter($requests, fn($r) => $r['status'] === 'approved')),
    'rejected' => count(array_filter($requests, fn($r) => $r['status'] === 'rejected')),
    'cancelled' => count(array_filter($requests, fn($r) => $r['status'] === 'cancelled')),
    'leave' => count(array_filter($requests, fn($r) => $r['request_type'] === 'leave')),
    'early_departure' => count(array_filter($requests, fn($r) => $r['request_type'] === 'early_departure')),
    'late_arrival' => count(array_filter($requests, fn($r) => $r['request_type'] === 'late_arrival'))
];
?>

<div class="page-header">
    <div class="page-title">
        <h2>Attendance Requests</h2>
        <p>Manage leave requests, early departures, and attendance adjustments</p>
    </div>
    <div class="page-actions">
        <button onclick="showNewRequestModal()" class="btn btn-primary">
            <i class="fas fa-plus"></i> New Request
        </button>
        <button onclick="exportRequests()" class="btn btn-outline" style="margin-left: 0.5rem;">
            <i class="fas fa-download"></i> Export
        </button>
    </div>
</div>

<!-- Filters -->
<div class="card" style="margin-bottom: 2rem;">
    <div class="card-header">
        <h3 class="card-title">Request Filters</h3>
    </div>
    <div style="padding: 1.5rem;">
        <form method="GET" action="" class="row">
            <div class="col-3">
                <div class="form-group">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-control">
                        <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>All Status</option>
                        <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="approved" <?php echo $status_filter == 'approved' ? 'selected' : ''; ?>>Approved</option>
                        <option value="rejected" <?php echo $status_filter == 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                        <option value="cancelled" <?php echo $status_filter == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                    </select>
                </div>
            </div>
            
            <div class="col-3">
                <div class="form-group">
                    <label class="form-label">Request Type</label>
                    <select name="type" class="form-control">
                        <option value="all" <?php echo $type_filter == 'all' ? 'selected' : ''; ?>>All Types</option>
                        <option value="leave" <?php echo $type_filter == 'leave' ? 'selected' : ''; ?>>Leave</option>
                        <option value="early_departure" <?php echo $type_filter == 'early_departure' ? 'selected' : ''; ?>>Early Departure</option>
                        <option value="late_arrival" <?php echo $type_filter == 'late_arrival' ? 'selected' : ''; ?>>Late Arrival</option>
                        <option value="remote_work" <?php echo $type_filter == 'remote_work' ? 'selected' : ''; ?>>Remote Work</option>
                        <option value="other" <?php echo $type_filter == 'other' ? 'selected' : ''; ?>>Other</option>
                    </select>
                </div>
            </div>
            
            <div class="col-3">
                <div class="form-group">
                    <label class="form-label">Staff Member</label>
                    <select name="user_id" class="form-control">
                        <option value="">All Staff</option>
                        <?php foreach ($all_staff as $staff): ?>
                        <option value="<?php echo $staff['user_id']; ?>" 
                            <?php echo $user_id == $staff['user_id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($staff['full_name'] ?: $staff['username']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="col-3">
                <div class="form-group">
                    <label class="form-label">Date Range</label>
                    <input type="date" 
                           name="date_from" 
                           class="form-control" 
                           value="<?php echo htmlspecialchars($date_from); ?>"
                           style="margin-bottom: 0.5rem;">
                    <input type="date" 
                           name="date_to" 
                           class="form-control" 
                           value="<?php echo htmlspecialchars($date_to); ?>">
                </div>
            </div>
            
            <div class="col-12" style="margin-top: 1rem; display: flex; gap: 0.5rem; justify-content: flex-end;">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-filter"></i> Apply Filters
                </button>
                <button type="button" onclick="window.location.href='requests.php'" class="btn btn-outline">
                    <i class="fas fa-redo"></i> Reset
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Request Statistics -->
<div class="stats-grid" style="margin-bottom: 2rem;">
    <div class="stat-card">
        <div class="stat-icon" style="background: var(--primary-light); color: var(--primary);">
            <i class="fas fa-inbox"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo $stats['total']; ?></h3>
            <p>Total Requests</p>
            <small class="text-muted">
                <?php echo $stats['leave']; ?> leave, <?php echo $stats['early_departure']; ?> early departures
            </small>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon" style="background: var(--warning-light); color: var(--warning);">
            <i class="fas fa-clock"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo $stats['pending']; ?></h3>
            <p>Pending</p>
            <small class="text-muted" style="color: var(--warning);">
                Needs review
            </small>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon" style="background: var(--success-light); color: var(--success);">
            <i class="fas fa-check-circle"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo $stats['approved']; ?></h3>
            <p>Approved</p>
            <small class="text-muted">
                <?php echo $stats['total'] > 0 ? round(($stats['approved'] / $stats['total']) * 100) : 0; ?>% approval rate
            </small>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon" style="background: var(--error-light); color: var(--error);">
            <i class="fas fa-times-circle"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo $stats['rejected']; ?></h3>
            <p>Rejected</p>
            <small class="text-muted">
                <?php echo date('M d', strtotime($date_from)); ?> - <?php echo date('M d', strtotime($date_to)); ?>
            </small>
        </div>
    </div>
</div>

<!-- Requests Table -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Attendance Requests</h3>
        <div class="card-actions">
            <span class="text-muted">
                Showing <?php echo count($requests); ?> request(s)
            </span>
        </div>
    </div>
    
    <div class="table-container">
        <?php if (empty($requests)): ?>
            <div style="text-align: center; padding: 3rem;">
                <i class="fas fa-inbox" style="font-size: 3rem; color: var(--border); margin-bottom: 1rem;"></i>
                <h4 style="color: var(--text-light); margin-bottom: 0.5rem;">No Requests Found</h4>
                <p class="text-muted">No attendance requests match the selected filters</p>
                <button onclick="showNewRequestModal()" class="btn btn-primary" style="margin-top: 1rem;">
                    <i class="fas fa-plus"></i> Create First Request
                </button>
            </div>
        <?php else: ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>Staff</th>
                        <th>Type</th>
                        <th>Date Range</th>
                        <th>Duration</th>
                        <th>Reason</th>
                        <th>Status</th>
                        <th>Submitted</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($requests as $request): 
                        $type_icons = [
                            'leave' => 'umbrella-beach',
                            'early_departure' => 'sign-out-alt',
                            'late_arrival' => 'clock',
                            'remote_work' => 'laptop-house',
                            'other' => 'question-circle'
                        ];
                        
                        $type_colors = [
                            'leave' => 'var(--info)',
                            'early_departure' => 'var(--warning)',
                            'late_arrival' => 'var(--accent)',
                            'remote_work' => 'var(--primary)',
                            'other' => 'var(--text)'
                        ];
                        
                        $start = new DateTime($request['start_date']);
                        $end = new DateTime($request['end_date']);
                        $duration = $start->diff($end)->days + 1;
                    ?>
                    <tr>
                        <td>
                            <div style="display: flex; align-items: center; gap: 0.8rem;">
                                <div class="user-avatar">
                                    <?php echo strtoupper(substr($request['username'], 0, 1)); ?>
                                </div>
                                <div>
                                    <strong><?php echo htmlspecialchars($request['full_name']); ?></strong><br>
                                    <small class="text-muted"><?php echo htmlspecialchars($request['email']); ?></small>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div style="display: flex; align-items: center; gap: 0.5rem;">
                                <i class="fas fa-<?php echo $type_icons[$request['request_type']] ?? 'question-circle'; ?>" 
                                   style="color: <?php echo $type_colors[$request['request_type']] ?? 'var(--text)'; ?>;"></i>
                                <span><?php echo ucfirst(str_replace('_', ' ', $request['request_type'])); ?></span>
                            </div>
                        </td>
                        <td>
                            <?php echo date('M d', strtotime($request['start_date'])); ?> - 
                            <?php echo date('M d, Y', strtotime($request['end_date'])); ?>
                        </td>
                        <td>
                            <?php echo $duration; ?> day<?php echo $duration > 1 ? 's' : ''; ?>
                        </td>
                        <td>
                            <small title="<?php echo htmlspecialchars($request['reason']); ?>">
                                <?php echo strlen($request['reason']) > 50 ? substr($request['reason'], 0, 50) . '...' : $request['reason']; ?>
                            </small>
                        </td>
                        <td>
                            <span class="status-badge status-<?php echo $request['status']; ?>">
                                <?php echo ucfirst($request['status']); ?>
                            </span>
                        </td>
                        <td>
                            <?php echo date('M d, H:i', strtotime($request['created_at'])); ?>
                        </td>
                        <td>
                            <div style="display: flex; gap: 0.3rem;">
                                <?php if ($request['status'] == 'pending'): ?>
                                <button onclick="approveRequest(<?php echo $request['request_id']; ?>)" 
                                        class="btn btn-sm btn-success" 
                                        title="Approve">
                                    <i class="fas fa-check"></i>
                                </button>
                                <button onclick="rejectRequest(<?php echo $request['request_id']; ?>)" 
                                        class="btn btn-sm btn-error" 
                                        title="Reject">
                                    <i class="fas fa-times"></i>
                                </button>
                                <?php endif; ?>
                                <button onclick="viewRequestDetails(<?php echo $request['request_id']; ?>)" 
                                        class="btn btn-sm btn-outline" 
                                        title="View Details">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button onclick="editRequest(<?php echo $request['request_id']; ?>)" 
                                        class="btn btn-sm btn-outline" 
                                        title="Edit">
                                    <i class="fas fa-edit"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<!-- Request Analytics -->
<div class="row" style="margin-top: 2rem;">
    <div class="col-8">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Request Analytics</h3>
            </div>
            <div style="padding: 1.5rem;">
                <div style="height: 300px;">
                    <canvas id="requestsChart"></canvas>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-4">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Quick Actions</h3>
            </div>
            <div style="padding: 1.5rem;">
                <div style="display: flex; flex-direction: column; gap: 0.8rem;">
                    <button onclick="showBulkApproveModal()" class="btn btn-outline" style="width: 100%; text-align: left;">
                        <i class="fas fa-check-double"></i> Bulk Approve Requests
                    </button>
                    
                    <button onclick="showAutoResponseRules()" class="btn btn-outline" style="width: 100%; text-align: left;">
                        <i class="fas fa-robot"></i> Auto-Response Rules
                    </button>
                    
                    <button onclick="showLeaveBalance()" class="btn btn-outline" style="width: 100%; text-align: left;">
                        <i class="fas fa-calendar-check"></i> View Leave Balances
                    </button>
                    
                    <button onclick="showRequestTemplates()" class="btn btn-outline" style="width: 100%; text-align: left;">
                        <i class="fas fa-file-alt"></i> Request Templates
                    </button>
                    
                    <hr style="margin: 0.5rem 0;">
                    
                    <div>
                        <h4 style="margin-bottom: 0.5rem;">Notification Settings</h4>
                        <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                            <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                                <input type="checkbox" checked>
                                Email notifications for new requests
                            </label>
                            <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                                <input type="checkbox" checked>
                                Notify when request is pending for 48h+
                            </label>
                            <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                                <input type="checkbox">
                                Auto-approve 1-day leave requests
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.status-badge {
    display: inline-block;
    padding: 0.2rem 0.6rem;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 500;
}

.status-pending { background: var(--warning-light); color: var(--warning); }
.status-approved { background: var(--success-light); color: var(--success); }
.status-rejected { background: var(--error-light); color: var(--error); }
.status-cancelled { background: var(--text-light); color: var(--text); }

.btn-success { background: var(--success); color: white; border: none; }
.btn-error { background: var(--error); color: white; border: none; }

.btn-success:hover { background: var(--success-dark); }
.btn-error:hover { background: var(--error-dark); }
</style>

<script>
let requestsChart = null;

function initializeRequestsChart() {
    const ctx = document.getElementById('requestsChart');
    if (!ctx) return;
    
    const typeData = {
        'leave': <?php echo $stats['leave']; ?>,
        'early_departure': <?php echo $stats['early_departure']; ?>,
        'late_arrival': <?php echo $stats['late_arrival']; ?>,
        'other': <?php echo $stats['total'] - $stats['leave'] - $stats['early_departure'] - $stats['late_arrival']; ?>
    };
    
    requestsChart = new Chart(ctx, {
        type: 'pie',
        data: {
            labels: ['Leave', 'Early Departure', 'Late Arrival', 'Other'],
            datasets: [{
                data: Object.values(typeData),
                backgroundColor: [
                    'var(--info)',
                    'var(--warning)',
                    'var(--accent)',
                    'var(--text-light)'
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'right'
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = Math.round((context.raw / total) * 100);
                            return `${context.label}: ${context.raw} (${percentage}%)`;
                        }
                    }
                }
            }
        }
    });
}

function showNewRequestModal() {
    showModal('New Attendance Request', `
        <div class="form-group">
            <label class="form-label">Staff Member</label>
            <select class="form-control">
                <option value="">Select staff member</option>
                <?php foreach ($all_staff as $staff): ?>
                <option value="<?php echo $staff['user_id']; ?>">
                    <?php echo htmlspecialchars($staff['full_name'] ?: $staff['username']); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="form-group">
            <label class="form-label">Request Type</label>
            <select class="form-control">
                <option value="leave">Leave</option>
                <option value="early_departure">Early Departure</option>
                <option value="late_arrival">Late Arrival</option>
                <option value="remote_work">Remote Work</option>
                <option value="other">Other</option>
            </select>
        </div>
        
        <div class="row">
            <div class="col-6">
                <div class="form-group">
                    <label class="form-label">Start Date</label>
                    <input type="date" class="form-control" value="<?php echo date('Y-m-d'); ?>">
                </div>
            </div>
            <div class="col-6">
                <div class="form-group">
                    <label class="form-label">End Date</label>
                    <input type="date" class="form-control" value="<?php echo date('Y-m-d'); ?>">
                </div>
            </div>
        </div>
        
        <div class="form-group">
            <label class="form-label">Reason</label>
            <textarea class="form-control" rows="3" placeholder="Please explain the reason for this request..." required></textarea>
        </div>
        
        <div class="form-group">
            <label class="form-label">Priority</label>
            <div style="display: flex; gap: 1rem;">
                <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                    <input type="radio" name="priority" value="low" checked>
                    <span style="color: var(--success);">Low</span>
                </label>
                <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                    <input type="radio" name="priority" value="medium">
                    <span style="color: var(--warning);">Medium</span>
                </label>
                <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                    <input type="radio" name="priority" value="high">
                    <span style="color: var(--error);">High (Urgent)</span>
                </label>
            </div>
        </div>
    `, 'Submit Request', 'submitNewRequest');
}

function approveRequest(requestId) {
    showModal('Approve Request', `
        <div class="form-group">
            <label class="form-label">Approval Notes (Optional)</label>
            <textarea class="form-control" rows="3" placeholder="Add approval notes..."></textarea>
        </div>
        
        <div class="form-group">
            <label class="form-label">Notify Staff</label>
            <div style="display: flex; gap: 1rem;">
                <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                    <input type="radio" name="notify" value="email" checked>
                    <span>Email Notification</span>
                </label>
                <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                    <input type="radio" name="notify" value="both">
                    <span>Email & In-App</span>
                </label>
            </div>
        </div>
    `, 'Approve Request', `confirmApproveRequest(${requestId})`);
}

function rejectRequest(requestId) {
    showModal('Reject Request', `
        <div class="form-group">
            <label class="form-label">Rejection Reason (Required)</label>
            <textarea class="form-control" rows="3" placeholder="Please explain why this request is being rejected..." required></textarea>
        </div>
        
        <div class="form-group">
            <label class="form-label">Notify Staff</label>
            <div style="display: flex; gap: 1rem;">
                <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                    <input type="radio" name="notify" value="email" checked>
                    <span>Email Notification</span>
                </label>
                <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                    <input type="radio" name="notify" value="both">
                    <span>Email & In-App</span>
                </label>
            </div>
        </div>
    `, 'Reject Request', `confirmRejectRequest(${requestId})`);
}

function viewRequestDetails(requestId) {
    showModal('Request Details', `
        <div style="text-align: center; padding: 2rem;">
            <i class="fas fa-spinner fa-spin" style="font-size: 2rem; color: var(--primary);"></i>
            <p>Loading request details...</p>
        </div>
    `, 'Close');
    
    setTimeout(() => {
        document.querySelector('.modal-content').innerHTML = `
            <h3 style="margin-bottom: 1.5rem;">Request Details</h3>
            <div style="margin-bottom: 1.5rem;">
                <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1rem;">
                    <div class="user-avatar" style="width: 50px; height: 50px; font-size: 1.2rem;">
                        JD
                    </div>
                    <div>
                        <h4 style="margin: 0;">John Doe</h4>
                        <p class="text-muted" style="margin: 0;">john.doe@example.com</p>
                    </div>
                </div>
                
                <div style="background: var(--bg); padding: 1rem; border-radius: 10px; margin-bottom: 1rem;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                        <span class="text-muted">Request Type:</span>
                        <span><strong>Leave Request</strong></span>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                        <span class="text-muted">Date Range:</span>
                        <span>Dec 20, 2025 - Dec 22, 2025 (3 days)</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                        <span class="text-muted">Status:</span>
                        <span class="status-badge status-pending">Pending</span>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span class="text-muted">Submitted:</span>
                        <span>Dec 18, 2025 10:30 AM</span>
                    </div>
                </div>
                
                <div>
                    <h4 style="margin-bottom: 0.5rem;">Reason</h4>
                    <p style="color: var(--text);">
                        Family vacation planned. Need to take 3 days off to spend time with family.
                    </p>
                </div>
                
                <div style="background: var(--bg-light); padding: 1rem; border-radius: 10px; margin-top: 1rem;">
                    <h4 style="margin-bottom: 0.5rem;">Admin Actions</h4>
                    <div class="row">
                        <div class="col-6">
                            <button onclick="approveRequest(${requestId})" class="btn btn-success" style="width: 100%;">
                                <i class="fas fa-check"></i> Approve
                            </button>
                        </div>
                        <div class="col-6">
                            <button onclick="rejectRequest(${requestId})" class="btn btn-error" style="width: 100%;">
                                <i class="fas fa-times"></i> Reject
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }, 1000);
}

function editRequest(requestId) {
    showModal('Edit Request', `
        <div class="form-group">
            <label class="form-label">Request Type</label>
            <select class="form-control">
                <option value="leave">Leave</option>
                <option value="early_departure">Early Departure</option>
                <option value="late_arrival">Late Arrival</option>
                <option value="remote_work">Remote Work</option>
                <option value="other">Other</option>
            </select>
        </div>
        
        <div class="row">
            <div class="col-6">
                <div class="form-group">
                    <label class="form-label">Start Date</label>
                    <input type="date" class="form-control" value="<?php echo date('Y-m-d'); ?>">
                </div>
            </div>
            <div class="col-6">
                <div class="form-group">
                    <label class="form-label">End Date</label>
                    <input type="date" class="form-control" value="<?php echo date('Y-m-d', strtotime('+2 days')); ?>">
                </div>
            </div>
        </div>
        
        <div class="form-group">
            <label class="form-label">Reason</label>
            <textarea class="form-control" rows="3" placeholder="Edit reason..."></textarea>
        </div>
        
        <div class="form-group">
            <label class="form-label">Status</label>
            <select class="form-control">
                <option value="pending">Pending</option>
                <option value="approved">Approved</option>
                <option value="rejected">Rejected</option>
                <option value="cancelled">Cancelled</option>
            </select>
        </div>
    `, 'Save Changes', `confirmEditRequest(${requestId})`);
}

function showBulkApproveModal() {
    showModal('Bulk Approve Requests', `
        <div class="form-group">
            <label class="form-label">Select Requests to Approve</label>
            <div style="max-height: 200px; overflow-y: auto; border: 1px solid var(--border); border-radius: 8px; padding: 1rem;">
                <label style="display: flex; align-items: center; gap: 0.5rem; padding: 0.5rem; cursor: pointer;">
                    <input type="checkbox">
                    <span>John Doe - Leave (Dec 20-22)</span>
                </label>
                <label style="display: flex; align-items: center; gap: 0.5rem; padding: 0.5rem; cursor: pointer;">
                    <input type="checkbox">
                    <span>Jane Smith - Early Departure (Dec 18)</span>
                </label>
                <label style="display: flex; align-items: center; gap: 0.5rem; padding: 0.5rem; cursor: pointer;">
                    <input type="checkbox">
                    <span>Bob Johnson - Late Arrival (Dec 19)</span>
                </label>
            </div>
        </div>
        
        <div class="form-group">
            <label class="form-label">Approval Note (Optional)</label>
            <textarea class="form-control" rows="2" placeholder="Add a note for the approved requests..."></textarea>
        </div>
        
        <div class="form-group">
            <label class="form-label">Notify Staff</label>
            <div style="display: flex; gap: 1rem;">
                <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                    <input type="radio" name="notify" value="email" checked>
                    <span>Email Notification</span>
                </label>
                <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                    <input type="radio" name="notify" value="both">
                    <span>Email & In-App</span>
                </label>
            </div>
        </div>
    `, 'Approve Selected', 'processBulkApprove');
}

function showAutoResponseRules() {
    showModal('Auto-Response Rules', `
        <div class="form-group">
            <label class="form-label">Rule Name</label>
            <input type="text" class="form-control" placeholder="e.g., Auto-approve 1-day leave">
        </div>
        
        <div class="form-group">
            <label class="form-label">Conditions</label>
            <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                    <input type="checkbox">
                    Request type is "Leave"
                </label>
                <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                    <input type="checkbox">
                    Duration is 1 day or less
                </label>
                <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                    <input type="checkbox">
                    Requested 3+ days in advance
                </label>
                <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                    <input type="checkbox">
                    Staff has sufficient leave balance
                </label>
            </div>
        </div>
        
        <div class="form-group">
            <label class="form-label">Action</label>
            <select class="form-control">
                <option value="auto_approve">Auto-Approve</option>
                <option value="auto_reject">Auto-Reject</option>
                <option value="escalate">Escalate to Manager</option>
            </select>
        </div>
        
        <div class="form-group">
            <label class="form-label">Auto-Response Message</label>
            <textarea class="form-control" rows="3" placeholder="Auto-generated response message..."></textarea>
        </div>
    `, 'Save Rule', 'saveAutoResponseRule');
}

function showLeaveBalance() {
    showModal('Staff Leave Balances', `
        <div style="max-height: 400px; overflow-y: auto;">
            <table style="width: 100%; font-size: 0.9rem;">
                <thead>
                    <tr>
                        <th>Staff</th>
                        <th>Annual Leave</th>
                        <th>Sick Leave</th>
                        <th>Used</th>
                        <th>Remaining</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>John Doe</td>
                        <td>20 days</td>
                        <td>10 days</td>
                        <td>5 days</td>
                        <td style="color: var(--success);">15 days</td>
                    </tr>
                    <tr>
                        <td>Jane Smith</td>
                        <td>20 days</td>
                        <td>10 days</td>
                        <td>18 days</td>
                        <td style="color: var(--warning);">2 days</td>
                    </tr>
                    <tr>
                        <td>Bob Johnson</td>
                        <td>20 days</td>
                        <td>10 days</td>
                        <td>20 days</td>
                        <td style="color: var(--error);">0 days</td>
                    </tr>
                </tbody>
            </table>
        </div>
    `, 'Close');
}

function showRequestTemplates() {
    showModal('Request Templates', `
        <div class="form-group">
            <label class="form-label">Template Name</label>
            <input type="text" class="form-control" placeholder="e.g., Annual Leave Request, Sick Leave">
        </div>
        
        <div class="form-group">
            <label class="form-label">Request Type</label>
            <select class="form-control">
                <option value="leave">Leave</option>
                <option value="early_departure">Early Departure</option>
                <option value="late_arrival">Late Arrival</option>
            </select>
        </div>
        
        <div class="form-group">
            <label class="form-label">Default Reason</label>
            <textarea class="form-control" rows="3" placeholder="Default reason text..."></textarea>
        </div>
        
        <div class="form-group">
            <label class="form-label">Default Duration</label>
            <div class="row">
                <div class="col-6">
                    <select class="form-control">
                        <option value="1">1 day</option>
                        <option value="2">2 days</option>
                        <option value="3">3 days</option>
                        <option value="5">5 days</option>
                        <option value="7">1 week</option>
                    </select>
                </div>
                <div class="col-6">
                    <select class="form-control">
                        <option value="full">Full Day</option>
                        <option value="half_am">Half Day (AM)</option>
                        <option value="half_pm">Half Day (PM)</option>
                    </select>
                </div>
            </div>
        </div>
    `, 'Save Template', 'saveRequestTemplate');
}

function exportRequests() {
    showToast('Exporting request data...', 'info');
    
    const requestData = {
        filters: {
            status: '<?php echo $status_filter; ?>',
            type: '<?php echo $type_filter; ?>',
            dateRange: '<?php echo $date_from; ?> to <?php echo $date_to; ?>'
        },
        requests: <?php echo json_encode($requests); ?>,
        stats: <?php echo json_encode($stats); ?>,
        generated: new Date().toISOString()
    };
    
    const dataStr = JSON.stringify(requestData, null, 2);
    const dataUri = 'data:application/json;charset=utf-8,'+ encodeURIComponent(dataStr);
    
    const link = document.createElement('a');
    link.href = dataUri;
    link.download = `attendance_requests_<?php echo date('Y-m-d'); ?>.json`;
    link.click();
    
    showToast('Request data exported', 'success');
}

function submitNewRequest() {
    showToast('Submitting request...', 'info');
    setTimeout(() => {
        showToast('Request submitted successfully', 'success');
        closeModal();
        setTimeout(() => location.reload(), 1000);
    }, 1500);
}

function confirmApproveRequest(requestId) {
    showToast('Approving request...', 'info');
    setTimeout(() => {
        showToast('Request approved successfully', 'success');
        closeModal();
        setTimeout(() => location.reload(), 1000);
    }, 1500);
}

function confirmRejectRequest(requestId) {
    showToast('Rejecting request...', 'info');
    setTimeout(() => {
        showToast('Request rejected', 'success');
        closeModal();
        setTimeout(() => location.reload(), 1000);
    }, 1500);
}

function confirmEditRequest(requestId) {
    showToast('Updating request...', 'info');
    setTimeout(() => {
        showToast('Request updated successfully', 'success');
        closeModal();
        setTimeout(() => location.reload(), 1000);
    }, 1500);
}

function processBulkApprove() {
    showToast('Processing bulk approval...', 'info');
    setTimeout(() => {
        showToast('Requests approved successfully', 'success');
        closeModal();
        setTimeout(() => location.reload(), 1000);
    }, 2000);
}

function saveAutoResponseRule() {
    showToast('Saving auto-response rule...', 'info');
    setTimeout(() => {
        showToast('Rule saved successfully', 'success');
        closeModal();
    }, 1500);
}

function saveRequestTemplate() {
    showToast('Saving request template...', 'info');
    setTimeout(() => {
        showToast('Template saved successfully', 'success');
        closeModal();
    }, 1500);
}

function showModal(title, content, actionText, actionFunction = null) {
    const modal = document.createElement('div');
    modal.className = 'modal-overlay';
    modal.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.5);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 10000;
        padding: 1rem;
    `;
    
    modal.innerHTML = `
        <div class="modal-content" style="background: white; padding: 2rem; border-radius: 20px; width: 500px; max-width: 90%; max-height: 90vh; overflow-y: auto;">
            <h3 style="margin-bottom: 1.5rem;">${title}</h3>
            ${content}
            <div style="margin-top: 2rem; display: flex; gap: 0.5rem; justify-content: flex-end;">
                <button onclick="closeModal()" class="btn btn-outline">
                    Cancel
                </button>
                ${actionFunction ? `<button onclick="${actionFunction}()" class="btn btn-primary">${actionText}</button>` : ''}
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
}

function closeModal() {
    const modal = document.querySelector('.modal-overlay');
    if (modal) {
        modal.remove();
    }
}

function showToast(message, type = 'info') {
    const toast = document.createElement('div');
    toast.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 1rem 1.5rem;
        background: ${type === 'success' ? 'var(--success)' : type === 'error' ? 'var(--error)' : 'var(--primary)'};
        color: white;
        border-radius: 10px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        z-index: 10001;
        animation: slideIn 0.3s ease;
    `;
    
    toast.innerHTML = `
        <div style="display: flex; align-items: center; gap: 0.5rem;">
            <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
            <span>${message}</span>
        </div>
    `;
    
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.style.animation = 'slideOut 0.3s ease';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    initializeRequestsChart();
    
    // Set date max values
    document.querySelectorAll('input[type="date"]').forEach(input => {
        const maxDate = new Date();
        maxDate.setFullYear(maxDate.getFullYear() + 1);
        input.max = maxDate.toISOString().split('T')[0];
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>
