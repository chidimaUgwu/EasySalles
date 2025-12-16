<?php
// admin/schedule/requests.php
$page_title = "Shift Requests & Swaps";
require_once '../includes/header.php';

$filter_status = $_GET['status'] ?? 'pending';
$filter_type = $_GET['type'] ?? 'all'; // all, swap, timeoff, cover
$date_from = $_GET['date_from'] ?? date('Y-m-d');
$date_to = $_GET['date_to'] ?? date('Y-m-d', strtotime('+30 days'));

// Get all shift requests
$requests = [];
try {
    // Note: You would need to create EASYSALLES_SHIFT_REQUESTS table
    // For now, we'll use sample data
    $sample_requests = [
        [
            'request_id' => 1,
            'user_id' => 2,
            'request_type' => 'swap',
            'shift_id' => 101,
            'requested_shift_id' => 102,
            'request_date' => '2025-12-15',
            'reason' => 'Doctor appointment',
            'status' => 'pending',
            'created_at' => '2025-12-13 10:30:00',
            'full_name' => 'John Doe',
            'username' => 'johndoe'
        ],
        [
            'request_id' => 2,
            'user_id' => 3,
            'request_type' => 'timeoff',
            'shift_id' => 103,
            'requested_shift_id' => null,
            'request_date' => '2025-12-20',
            'reason' => 'Family event',
            'status' => 'approved',
            'created_at' => '2025-12-12 14:20:00',
            'full_name' => 'Jane Smith',
            'username' => 'janesmith'
        ],
        [
            'request_id' => 3,
            'user_id' => 4,
            'request_type' => 'cover',
            'shift_id' => 104,
            'requested_shift_id' => null,
            'request_date' => '2025-12-18',
            'reason' => 'Emergency',
            'status' => 'rejected',
            'created_at' => '2025-12-11 09:15:00',
            'full_name' => 'Bob Johnson',
            'username' => 'bobjohnson'
        ]
    ];
    
    // Filter requests
    $requests = array_filter($sample_requests, function($req) use ($filter_status, $filter_type) {
        $status_match = $filter_status === 'all' || $req['status'] === $filter_status;
        $type_match = $filter_type === 'all' || $req['request_type'] === $filter_type;
        return $status_match && $type_match;
    });
    
} catch (PDOException $e) {
    // Handle error
}

// Get stats
$stats = [
    'total' => count($requests),
    'pending' => count(array_filter($requests, fn($r) => $r['status'] === 'pending')),
    'approved' => count(array_filter($requests, fn($r) => $r['status'] === 'approved')),
    'rejected' => count(array_filter($requests, fn($r) => $r['status'] === 'rejected')),
    'swaps' => count(array_filter($requests, fn($r) => $r['request_type'] === 'swap')),
    'timeoff' => count(array_filter($requests, fn($r) => $r['request_type'] === 'timeoff')),
];

// Get all staff for dropdowns
$all_staff = [];
try {
    $stmt = $pdo->query("SELECT user_id, username, full_name FROM EASYSALLES_USERS WHERE role = 2 AND status = 'active' ORDER BY full_name");
    $all_staff = $stmt->fetchAll();
} catch (PDOException $e) {
    // Handle error
}
?>

<div class="page-header">
    <div class="page-title">
        <h2>Shift Requests & Swaps</h2>
        <p>Manage staff shift change requests, time-off, and cover requests</p>
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
                    <select name="status" class="form-control" onchange="this.form.submit()">
                        <option value="all" <?php echo $filter_status == 'all' ? 'selected' : ''; ?>>All Status</option>
                        <option value="pending" <?php echo $filter_status == 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="approved" <?php echo $filter_status == 'approved' ? 'selected' : ''; ?>>Approved</option>
                        <option value="rejected" <?php echo $filter_status == 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                        <option value="cancelled" <?php echo $filter_status == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                    </select>
                </div>
            </div>
            
            <div class="col-3">
                <div class="form-group">
                    <label class="form-label">Request Type</label>
                    <select name="type" class="form-control" onchange="this.form.submit()">
                        <option value="all" <?php echo $filter_type == 'all' ? 'selected' : ''; ?>>All Types</option>
                        <option value="swap" <?php echo $filter_type == 'swap' ? 'selected' : ''; ?>>Shift Swap</option>
                        <option value="timeoff" <?php echo $filter_type == 'timeoff' ? 'selected' : ''; ?>>Time Off</option>
                        <option value="cover" <?php echo $filter_type == 'cover' ? 'selected' : ''; ?>>Cover Request</option>
                        <option value="change" <?php echo $filter_type == 'change' ? 'selected' : ''; ?>>Schedule Change</option>
                    </select>
                </div>
            </div>
            
            <div class="col-3">
                <div class="form-group">
                    <label class="form-label">Date From</label>
                    <input type="date" 
                           name="date_from" 
                           class="form-control" 
                           value="<?php echo htmlspecialchars($date_from); ?>"
                           max="<?php echo date('Y-m-d', strtotime('+365 days')); ?>">
                </div>
            </div>
            
            <div class="col-3">
                <div class="form-group">
                    <label class="form-label">Date To</label>
                    <input type="date" 
                           name="date_to" 
                           class="form-control" 
                           value="<?php echo htmlspecialchars($date_to); ?>"
                           max="<?php echo date('Y-m-d', strtotime('+365 days')); ?>">
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
            <i class="fas fa-exchange-alt"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo number_format($stats['total']); ?></h3>
            <p>Total Requests</p>
            <small class="text-muted">
                <?php echo $stats['swaps']; ?> swaps, <?php echo $stats['timeoff']; ?> time-off
            </small>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon" style="background: var(--warning-light); color: var(--warning);">
            <i class="fas fa-clock"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo number_format($stats['pending']); ?></h3>
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
            <h3><?php echo number_format($stats['approved']); ?></h3>
            <p>Approved</p>
            <small class="text-muted">
                <?php echo round(($stats['approved'] / max($stats['total'], 1)) * 100); ?>% approval rate
            </small>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon" style="background: var(--error-light); color: var(--error);">
            <i class="fas fa-times-circle"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo number_format($stats['rejected']); ?></h3>
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
        <h3 class="card-title">Shift Requests</h3>
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
                <p class="text-muted">No shift requests match the selected filters</p>
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
                        <th>Details</th>
                        <th>Date</th>
                        <th>Reason</th>
                        <th>Status</th>
                        <th>Submitted</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($requests as $request): 
                        $type_icons = [
                            'swap' => 'exchange-alt',
                            'timeoff' => 'calendar-times',
                            'cover' => 'user-clock',
                            'change' => 'edit'
                        ];
                        
                        $type_colors = [
                            'swap' => 'var(--primary)',
                            'timeoff' => 'var(--warning)',
                            'cover' => 'var(--accent)',
                            'change' => 'var(--info)'
                        ];
                    ?>
                    <tr>
                        <td>
                            <div style="display: flex; align-items: center; gap: 0.8rem;">
                                <div class="user-avatar">
                                    <?php echo strtoupper(substr($request['username'], 0, 1)); ?>
                                </div>
                                <div>
                                    <strong><?php echo htmlspecialchars($request['full_name']); ?></strong><br>
                                    <small class="text-muted">@<?php echo htmlspecialchars($request['username']); ?></small>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div style="display: flex; align-items: center; gap: 0.5rem;">
                                <i class="fas fa-<?php echo $type_icons[$request['request_type']] ?? 'question-circle'; ?>" 
                                   style="color: <?php echo $type_colors[$request['request_type']] ?? 'var(--text)'; ?>;"></i>
                                <span><?php echo ucfirst($request['request_type']); ?></span>
                            </div>
                        </td>
                        <td>
                            <?php if ($request['request_type'] == 'swap'): ?>
                                <small>Swap shift with another staff</small>
                            <?php elseif ($request['request_type'] == 'timeoff'): ?>
                                <small>Request time off</small>
                            <?php elseif ($request['request_type'] == 'cover'): ?>
                                <small>Need shift coverage</small>
                            <?php else: ?>
                                <small>Schedule change</small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php echo date('M d, Y', strtotime($request['request_date'])); ?>
                        </td>
                        <td>
                            <small><?php echo htmlspecialchars($request['reason']); ?></small>
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
                                <button onclick="deleteRequest(<?php echo $request['request_id']; ?>)" 
                                        class="btn btn-sm btn-outline btn-danger" 
                                        title="Delete">
                                    <i class="fas fa-trash"></i>
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

<!-- Request History & Analytics -->
<div class="row" style="margin-top: 2rem;">
    <div class="col-6">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Request Analytics</h3>
            </div>
            <div style="padding: 1.5rem;">
                <div style="height: 200px;">
                    <canvas id="requestsChart"></canvas>
                </div>
                
                <hr style="margin: 1.5rem 0;">
                
                <div>
                    <h4 style="margin-bottom: 1rem;">Quick Stats</h4>
                    <div class="row">
                        <div class="col-6">
                            <div style="background: var(--bg); padding: 1rem; border-radius: 10px; margin-bottom: 0.5rem;">
                                <small class="text-muted">Avg Response Time</small><br>
                                <strong>12 hours</strong>
                            </div>
                        </div>
                        <div class="col-6">
                            <div style="background: var(--bg); padding: 1rem; border-radius: 10px; margin-bottom: 0.5rem;">
                                <small class="text-muted">Approval Rate</small><br>
                                <strong>75%</strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-6">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Request Management</h3>
            </div>
            <div style="padding: 1.5rem;">
                <div style="display: flex; flex-direction: column; gap: 0.8rem;">
                    <button onclick="showBulkApproveModal()" class="btn btn-outline" style="width: 100%; text-align: left;">
                        <i class="fas fa-check-double"></i> Bulk Approve Requests
                    </button>
                    
                    <button onclick="showAutoAssignModal()" class="btn btn-outline" style="width: 100%; text-align: left;">
                        <i class="fas fa-robot"></i> Auto-Assign Cover Requests
                    </button>
                    
                    <button onclick="showRequestTemplate()" class="btn btn-outline" style="width: 100%; text-align: left;">
                        <i class="fas fa-file-alt"></i> Create Request Template
                    </button>
                    
                    <button onclick="viewRequestHistory()" class="btn btn-outline" style="width: 100%; text-align: left;">
                        <i class="fas fa-history"></i> View Request History
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
                                Notify when request is pending for 24h+
                            </label>
                            <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                                <input type="checkbox">
                                Auto-approve time-off requests
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
    
    requestsChart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Pending', 'Approved', 'Rejected', 'Cancelled'],
            datasets: [{
                data: [
                    <?php echo $stats['pending']; ?>,
                    <?php echo $stats['approved']; ?>,
                    <?php echo $stats['rejected']; ?>,
                    <?php echo max(0, $stats['total'] - $stats['pending'] - $stats['approved'] - $stats['rejected']); ?>
                ],
                backgroundColor: [
                    'var(--warning)',
                    'var(--success)',
                    'var(--error)',
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
    showModal('Create New Shift Request', `
        <div class="form-group">
            <label class="form-label">Request Type</label>
            <select class="form-control" id="requestType" onchange="toggleRequestFields()">
                <option value="swap">Shift Swap</option>
                <option value="timeoff">Time Off Request</option>
                <option value="cover">Cover Request</option>
                <option value="change">Schedule Change</option>
            </select>
        </div>
        
        <div id="swapFields" class="request-fields">
            <div class="row">
                <div class="col-6">
                    <div class="form-group">
                        <label class="form-label">Your Shift</label>
                        <select class="form-control">
                            <option value="">Select your shift</option>
                            <option value="101">Dec 15: Morning Shift (9AM-5PM)</option>
                            <option value="102">Dec 16: Evening Shift (2PM-10PM)</option>
                        </select>
                    </div>
                </div>
                <div class="col-6">
                    <div class="form-group">
                        <label class="form-label">Swap With Staff</label>
                        <select class="form-control">
                            <option value="">Select staff member</option>
                            <?php foreach ($all_staff as $staff): ?>
                            <option value="<?php echo $staff['user_id']; ?>">
                                <?php echo htmlspecialchars($staff['full_name'] ?: $staff['username']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
        </div>
        
        <div id="timeoffFields" class="request-fields" style="display: none;">
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
                        <input type="date" class="form-control" value="<?php echo date('Y-m-d', strtotime('+1 day')); ?>">
                    </div>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Time Off Type</label>
                <select class="form-control">
                    <option value="vacation">Vacation</option>
                    <option value="sick">Sick Leave</option>
                    <option value="personal">Personal Day</option>
                    <option value="emergency">Emergency</option>
                </select>
            </div>
        </div>
        
        <div id="coverFields" class="request-fields" style="display: none;">
            <div class="form-group">
                <label class="form-label">Shift Needing Cover</label>
                <select class="form-control">
                    <option value="">Select shift</option>
                    <option value="101">Dec 15: Morning Shift (9AM-5PM)</option>
                    <option value="102">Dec 16: Evening Shift (2PM-10PM)</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Preferred Cover Staff (Optional)</label>
                <select class="form-control">
                    <option value="">Any available staff</option>
                    <?php foreach ($all_staff as $staff): ?>
                    <option value="<?php echo $staff['user_id']; ?>">
                        <?php echo htmlspecialchars($staff['full_name'] ?: $staff['username']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
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

function toggleRequestFields() {
    const type = document.getElementById('requestType').value;
    document.querySelectorAll('.request-fields').forEach(field => {
        field.style.display = 'none';
    });
    document.getElementById(type + 'Fields').style.display = 'block';
}

function approveRequest(requestId) {
    if (confirm('Approve this shift request?')) {
        showToast('Approving request...', 'info');
        // In production, this would be an AJAX call
        setTimeout(() => {
            showToast('Request approved successfully', 'success');
            location.reload();
        }, 1000);
    }
}

function rejectRequest(requestId) {
    const reason = prompt('Please enter reason for rejection:', '');
    if (reason !== null) {
        showToast('Rejecting request...', 'info');
        // In production, this would be an AJAX call
        setTimeout(() => {
            showToast('Request rejected', 'success');
            location.reload();
        }, 1000);
    }
}

function viewRequestDetails(requestId) {
    showModal('Request Details', `
        <div style="text-align: center; padding: 2rem;">
            <i class="fas fa-spinner fa-spin" style="font-size: 2rem; color: var(--primary);"></i>
            <p>Loading request details...</p>
        </div>
    `, 'Close');
    
    // In production, this would be an AJAX call
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
                        <p class="text-muted" style="margin: 0;">Shift Swap Request</p>
                    </div>
                </div>
                
                <div style="background: var(--bg); padding: 1rem; border-radius: 10px; margin-bottom: 1rem;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                        <span class="text-muted">Current Shift:</span>
                        <span><strong>Dec 15: Morning Shift (9AM-5PM)</strong></span>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                        <span class="text-muted">Requested Swap:</span>
                        <span>With Jane Smith (Dec 16: Evening Shift)</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                        <span class="text-muted">Status:</span>
                        <span class="status-badge status-pending">Pending</span>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span class="text-muted">Submitted:</span>
                        <span>Dec 13, 2025 10:30 AM</span>
                    </div>
                </div>
                
                <div>
                    <h4 style="margin-bottom: 0.5rem;">Reason</h4>
                    <p style="color: var(--text);">
                        I have a doctor appointment scheduled for Dec 15 morning. Requesting to swap with Jane Smith who has the evening shift on Dec 16.
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

function deleteRequest(requestId) {
    if (confirm('Are you sure you want to delete this request?')) {
        showToast('Deleting request...', 'info');
        // In production, this would be an AJAX call
        setTimeout(() => {
            showToast('Request deleted', 'success');
            location.reload();
        }, 1000);
    }
}

function submitNewRequest() {
    showToast('Submitting request...', 'info');
    setTimeout(() => {
        showToast('Request submitted successfully', 'success');
        closeModal();
        setTimeout(() => location.reload(), 1000);
    }, 1500);
}

function showBulkApproveModal() {
    showModal('Bulk Approve Requests', `
        <div class="form-group">
            <label class="form-label">Select Requests to Approve</label>
            <div style="max-height: 200px; overflow-y: auto; border: 1px solid var(--border); border-radius: 8px; padding: 1rem;">
                <label style="display: flex; align-items: center; gap: 0.5rem; padding: 0.5rem; cursor: pointer;">
                    <input type="checkbox">
                    <span>John Doe - Shift Swap (Dec 15)</span>
                </label>
                <label style="display: flex; align-items: center; gap: 0.5rem; padding: 0.5rem; cursor: pointer;">
                    <input type="checkbox">
                    <span>Jane Smith - Time Off (Dec 20-21)</span>
                </label>
                <label style="display: flex; align-items: center; gap: 0.5rem; padding: 0.5rem; cursor: pointer;">
                    <input type="checkbox">
                    <span>Bob Johnson - Cover Request (Dec 18)</span>
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

function showAutoAssignModal() {
    showModal('Auto-Assign Cover Requests', `
        <div class="form-group">
            <label class="form-label">Available Cover Staff</label>
            <select class="form-control" multiple style="height: 150px;">
                <?php foreach ($all_staff as $staff): ?>
                <option value="<?php echo $staff['user_id']; ?>">
                    <?php echo htmlspecialchars($staff['full_name'] ?: $staff['username']); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="form-group">
            <label class="form-label">Assignment Rules</label>
            <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                    <input type="checkbox" checked>
                    Balance workload among staff
                </label>
                <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                    <input type="checkbox" checked>
                    Consider shift preferences
                </label>
                <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                    <input type="checkbox">
                    Allow overtime assignments
                </label>
            </div>
        </div>
        
        <div class="form-group">
            <label class="form-label">Date Range</label>
            <div class="row">
                <div class="col-6">
                    <input type="date" class="form-control" value="<?php echo date('Y-m-d'); ?>">
                </div>
                <div class="col-6">
                    <input type="date" class="form-control" value="<?php echo date('Y-m-d', strtotime('+7 days')); ?>">
                </div>
            </div>
        </div>
    `, 'Auto-Assign', 'processAutoAssign');
}

function showRequestTemplate() {
    showModal('Create Request Template', `
        <div class="form-group">
            <label class="form-label">Template Name</label>
            <input type="text" class="form-control" placeholder="e.g., Weekly Time Off, Shift Swap Request">
        </div>
        
        <div class="form-group">
            <label class="form-label">Request Type</label>
            <select class="form-control">
                <option value="timeoff">Time Off</option>
                <option value="swap">Shift Swap</option>
                <option value="cover">Cover Request</option>
            </select>
        </div>
        
        <div class="form-group">
            <label class="form-label">Default Reason</label>
            <textarea class="form-control" rows="3" placeholder="Default reason text..."></textarea>
        </div>
        
        <div class="form-group">
            <label class="form-label">Auto-Approval Rules</label>
            <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                    <input type="checkbox">
                    Auto-approve if requested 7+ days in advance
                </label>
                <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                    <input type="checkbox">
                    Auto-approve for specific staff members
                </label>
            </div>
        </div>
    `, 'Save Template', 'saveRequestTemplate');
}

function viewRequestHistory() {
    showModal('Request History', `
        <div style="text-align: center; padding: 2rem;">
            <i class="fas fa-spinner fa-spin" style="font-size: 2rem; color: var(--primary);"></i>
            <p>Loading request history...</p>
        </div>
    `, 'Close', null, true);
    
    setTimeout(() => {
        document.querySelector('.modal-content').innerHTML = `
            <h3 style="margin-bottom: 1.5rem;">Request History</h3>
            <div style="max-height: 400px; overflow-y: auto;">
                <div style="padding: 1rem; border-bottom: 1px solid var(--border);">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                        <strong>John Doe - Shift Swap</strong>
                        <span class="status-badge status-approved">Approved</span>
                    </div>
                    <small class="text-muted">Dec 15, 2025 • Approved by Admin</small>
                </div>
                <div style="padding: 1rem; border-bottom: 1px solid var(--border);">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                        <strong>Jane Smith - Time Off</strong>
                        <span class="status-badge status-approved">Approved</span>
                    </div>
                    <small class="text-muted">Dec 14, 2025 • Auto-approved</small>
                </div>
                <div style="padding: 1rem; border-bottom: 1px solid var(--border);">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                        <strong>Bob Johnson - Cover Request</strong>
                        <span class="status-badge status-rejected">Rejected</span>
                    </div>
                    <small class="text-muted">Dec 13, 2025 • Rejected: No available cover</small>
                </div>
            </div>
        `;
    }, 1000);
}

function exportRequests() {
    showToast('Exporting request data...', 'info');
    
    const requestData = {
        filters: {
            status: '<?php echo $filter_status; ?>',
            type: '<?php echo $filter_type; ?>',
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
    link.download = `shift_requests_<?php echo date('Y-m-d'); ?>.json`;
    link.click();
    
    showToast('Request data exported', 'success');
}

function processBulkApprove() {
    showToast('Processing bulk approval...', 'info');
    setTimeout(() => {
        showToast('Requests approved successfully', 'success');
        closeModal();
        setTimeout(() => location.reload(), 1000);
    }, 2000);
}

function processAutoAssign() {
    showToast('Auto-assigning cover requests...', 'info');
    setTimeout(() => {
        showToast('Cover requests assigned successfully', 'success');
        closeModal();
        setTimeout(() => location.reload(), 1000);
    }, 2000);
}

function saveRequestTemplate() {
    showToast('Saving request template...', 'info');
    setTimeout(() => {
        showToast('Template saved successfully', 'success');
        closeModal();
    }, 1500);
}

function showModal(title, content, actionText, actionFunction = null, large = false) {
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
    
    const modalSize = large ? '600px' : '500px';
    
    modal.innerHTML = `
        <div class="modal-content" style="background: white; padding: 2rem; border-radius: 20px; width: ${modalSize}; max-width: 90%; max-height: 90vh; overflow-y: auto;">
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
