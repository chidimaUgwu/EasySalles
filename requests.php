<?php
// admin/shifts/requests.php
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
    
    header("Location: requests.php");
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
?>

<div class="page-header">
    <div class="page-title">
        <h2>Shift Requests</h2>
        <p>Review and manage staff shift change requests</p>
    </div>
</div>

<?php if (isset($_SESSION['success'])): ?>
    <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
<?php endif; ?>

<div class="row">
    <div class="col-8">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Pending Requests</h3>
                <span class="badge badge-primary"><?php echo count($pending_requests); ?> pending</span>
            </div>
            <div class="table-container">
                <?php if (empty($pending_requests)): ?>
                    <div style="text-align: center; padding: 3rem;">
                        <i class="fas fa-check-circle" style="font-size: 3rem; color: var(--success);"></i>
                        <p style="margin-top: 1rem; font-size: 1.1rem;">No pending requests</p>
                        <p class="text-muted">All shift requests have been processed</p>
                    </div>
                <?php else: ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Staff</th>
                                <th>Type</th>
                                <th>Date(s)</th>
                                <th>Reason</th>
                                <th>Priority</th>
                                <th>Requested</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pending_requests as $request): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($request['requester_name'] ?: $request['requester_username']); ?></strong>
                                </td>
                                <td>
                                    <?php
                                    $type_labels = [
                                        'swap' => '<span class="badge badge-info">Swap</span>',
                                        'timeoff' => '<span class="badge badge-warning">Time Off</span>',
                                        'cover' => '<span class="badge badge-secondary">Cover</span>',
                                        'change' => '<span class="badge badge-primary">Change</span>'
                                    ];
                                    echo $type_labels[$request['request_type']] ?? $request['request_type'];
                                    ?>
                                </td>
                                <td>
                                    <?php if ($request['start_date']): ?>
                                        <?php echo date('M j', strtotime($request['start_date'])); ?>
                                        <?php if ($request['end_date'] && $request['end_date'] != $request['start_date']): ?>
                                            - <?php echo date('j', strtotime($request['end_date'])); ?>
                                        <?php endif; ?>
                                        <?php if ($request['shift_name']): ?>
                                            <br><small class="text-muted"><?php echo $request['shift_name']; ?></small>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                        <?php echo htmlspecialchars($request['reason']); ?>
                                    </div>
                                </td>
                                <td>
                                    <?php
                                    $priority_class = [
                                        'high' => 'badge-danger',
                                        'medium' => 'badge-warning',
                                        'low' => 'badge-success'
                                    ];
                                    ?>
                                    <span class="badge <?php echo $priority_class[$request['priority']] ?? 'badge-secondary'; ?>">
                                        <?php echo ucfirst($request['priority']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('M j', strtotime($request['created_at'])); ?></td>
                                <td>
                                    <div class="btn-group">
                                        <button type="button" class="btn btn-sm btn-success" 
                                                onclick="reviewRequest(<?php echo $request['request_id']; ?>, 'approve')">
                                            <i class="fas fa-check"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-danger"
                                                onclick="reviewRequest(<?php echo $request['request_id']; ?>, 'reject')">
                                            <i class="fas fa-times"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline"
                                                onclick="viewRequestDetails(<?php echo $request['request_id']; ?>)">
                                            <i class="fas fa-eye"></i>
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
    </div>
    
    <div class="col-4">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Recent Decisions</h3>
            </div>
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Staff</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Decided</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_decisions as $decision): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($decision['requester_name']); ?></td>
                            <td>
                                <?php
                                $type_short = [
                                    'swap' => 'SW',
                                    'timeoff' => 'TO',
                                    'cover' => 'CV',
                                    'change' => 'CH'
                                ];
                                echo $type_short[$decision['request_type']] ?? $decision['request_type'];
                                ?>
                            </td>
                            <td>
                                <?php if ($decision['status'] == 'approved'): ?>
                                    <span class="badge badge-success">Approved</span>
                                <?php else: ?>
                                    <span class="badge badge-danger">Rejected</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo date('M j', strtotime($decision['approved_at'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <div class="card" style="margin-top: 1.5rem;">
            <div class="card-header">
                <h3 class="card-title">Request Statistics</h3>
            </div>
            <div class="card-body">
                <?php
                // Get request stats
                $stmt = $pdo->query("SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
                    SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
                    SUM(CASE WHEN priority = 'high' THEN 1 ELSE 0 END) as high_priority
                    FROM EASYSALLES_SHIFT_REQUESTS 
                    WHERE DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)");
                $stats = $stmt->fetch();
                ?>
                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem;">
                    <div style="text-align: center; padding: 1rem; background: var(--warning-light); border-radius: 10px;">
                        <div style="font-size: 2rem; font-weight: 700; color: var(--warning);">
                            <?php echo $stats['pending'] ?? 0; ?>
                        </div>
                        <div style="color: var(--text-muted); font-size: 0.9rem;">Pending</div>
                    </div>
                    
                    <div style="text-align: center; padding: 1rem; background: var(--success-light); border-radius: 10px;">
                        <div style="font-size: 2rem; font-weight: 700; color: var(--success);">
                            <?php echo $stats['approved'] ?? 0; ?>
                        </div>
                        <div style="color: var(--text-muted); font-size: 0.9rem;">Approved</div>
                    </div>
                    
                    <div style="text-align: center; padding: 1rem; background: var(--danger-light); border-radius: 10px;">
                        <div style="font-size: 2rem; font-weight: 700; color: var(--danger);">
                            <?php echo $stats['rejected'] ?? 0; ?>
                        </div>
                        <div style="color: var(--text-muted); font-size: 0.9rem;">Rejected</div>
                    </div>
                    
                    <div style="text-align: center; padding: 1rem; background: var(--primary-light); border-radius: 10px;">
                        <div style="font-size: 2rem; font-weight: 700; color: var(--primary);">
                            <?php echo $stats['high_priority'] ?? 0; ?>
                        </div>
                        <div style="color: var(--text-muted); font-size: 0.9rem;">High Priority</div>
                    </div>
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
            <button type="button" class="close" onclick="closeReviewModal()">&times;</button>
        </div>
        <form method="POST" id="reviewForm">
            <div class="modal-body">
                <input type="hidden" name="request_id" id="review_request_id">
                <input type="hidden" name="action_type" id="action_type">
                
                <div id="requestDetails" style="margin-bottom: 1.5rem; padding: 1rem; background: #f8f9fa; border-radius: 5px;">
                    <!-- Request details will be loaded here -->
                </div>
                
                <div class="form-group">
                    <label for="admin_notes">Admin Notes</label>
                    <textarea class="form-control" id="admin_notes" name="admin_notes" rows="3" 
                              placeholder="Add notes about your decision..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeReviewModal()">Cancel</button>
                <button type="submit" class="btn" id="submitBtn">Submit</button>
            </div>
        </form>
    </div>
</div>

<!-- Details Modal -->
<div class="modal" id="detailsModal">
    <div class="modal-content">
        <div class="modal-header">
            <h4>Request Details</h4>
            <button type="button" class="close" onclick="closeDetailsModal()">&times;</button>
        </div>
        <div class="modal-body" id="detailsContent">
            <!-- Details will be loaded here -->
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline" onclick="closeDetailsModal()">Close</button>
        </div>
    </div>
</div>

<script>
function reviewRequest(requestId, action) {
    document.getElementById('review_request_id').value = requestId;
    document.getElementById('action_type').value = action;
    
    // Set form action and button text
    if (action === 'approve') {
        document.getElementById('reviewTitle').textContent = 'Approve Request';
        document.getElementById('submitBtn').className = 'btn btn-success';
        document.getElementById('submitBtn').name = 'approve_request';
        document.getElementById('submitBtn').textContent = 'Approve Request';
    } else {
        document.getElementById('reviewTitle').textContent = 'Reject Request';
        document.getElementById('submitBtn').className = 'btn btn-danger';
        document.getElementById('submitBtn').name = 'reject_request';
        document.getElementById('submitBtn').textContent = 'Reject Request';
    }
    
    // Load request details via AJAX
    fetch(`get_request_details.php?id=${requestId}`)
        .then(response => response.json())
        .then(data => {
            document.getElementById('requestDetails').innerHTML = `
                <strong>Staff:</strong> ${data.requester_name}<br>
                <strong>Type:</strong> ${data.request_type}<br>
                <strong>Date:</strong> ${data.start_date} ${data.end_date ? ' to ' + data.end_date : ''}<br>
                <strong>Reason:</strong> ${data.reason}<br>
                <strong>Priority:</strong> ${data.priority}<br>
                <strong>Requested:</strong> ${new Date(data.created_at).toLocaleDateString()}
            `;
        })
        .catch(error => {
            document.getElementById('requestDetails').innerHTML = 'Error loading request details';
        });
    
    document.getElementById('reviewModal').style.display = 'block';
}

function viewRequestDetails(requestId) {
    // Load full request details via AJAX
    fetch(`get_request_details.php?id=${requestId}&full=1`)
        .then(response => response.json())
        .then(data => {
            const typeLabels = {
                'swap': 'Shift Swap',
                'timeoff': 'Time Off',
                'cover': 'Shift Cover',
                'change': 'Shift Change'
            };
            
            const statusLabels = {
                'pending': '<span class="badge badge-warning">Pending</span>',
                'approved': '<span class="badge badge-success">Approved</span>',
                'rejected': '<span class="badge badge-danger">Rejected</span>',
                'cancelled': '<span class="badge badge-secondary">Cancelled</span>'
            };
            
            document.getElementById('detailsContent').innerHTML = `
                <div style="margin-bottom: 1.5rem;">
                    <h5>Request Information</h5>
                    <table class="table">
                        <tr>
                            <td><strong>Staff Member:</strong></td>
                            <td>${data.requester_name}</td>
                        </tr>
                        <tr>
                            <td><strong>Request Type:</strong></td>
                            <td>${typeLabels[data.request_type] || data.request_type}</td>
                        </tr>
                        <tr>
                            <td><strong>Status:</strong></td>
                            <td>${statusLabels[data.status] || data.status}</td>
                        </tr>
                        <tr>
                            <td><strong>Date Range:</strong></td>
                            <td>${data.start_date} ${data.end_date ? ' to ' + data.end_date : ''}</td>
                        </tr>
                        <tr>
                            <td><strong>Priority:</strong></td>
                            <td>${data.priority}</td>
                        </tr>
                        <tr>
                            <td><strong>Submitted:</strong></td>
                            <td>${new Date(data.created_at).toLocaleString()}</td>
                        </tr>
                    </table>
                </div>
                
                <div style="margin-bottom: 1.5rem;">
                    <h5>Reason</h5>
                    <div style="padding: 1rem; background: #f8f9fa; border-radius: 5px;">
                        ${data.reason || 'No reason provided'}
                    </div>
                </div>
                
                ${data.admin_notes ? `
                <div>
                    <h5>Admin Notes</h5>
                    <div style="padding: 1rem; background: #e8f4f8; border-radius: 5px;">
                        ${data.admin_notes}
                    </div>
                </div>
                ` : ''}
            `;
        })
        .catch(error => {
            document.getElementById('detailsContent').innerHTML = 'Error loading request details';
        });
    
    document.getElementById('detailsModal').style.display = 'block';
}

function closeReviewModal() {
    document.getElementById('reviewModal').style.display = 'none';
}

function closeDetailsModal() {
    document.getElementById('detailsModal').style.display = 'none';
}

// Add keyboard shortcuts
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeReviewModal();
        closeDetailsModal();
    }
});
</script>

<?php require_once ROOT_PATH . 'admin/includes/footer.php'; ?>