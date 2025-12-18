<?php
// my-shifts.php
// Turn on full error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

ob_start(); // Start output buffering

// Include configuration and authentication
require 'config.php'; // This should be in the same directory as my-shifts.php
require 'includes/auth.php';

// Check if user is logged in
require_login();

// Redirect admin to admin dashboard
if (isset($_SESSION['role']) && $_SESSION['role'] == 1) {
    header('Location: admin-dashboard.php');
    exit();
}

// Database connection - ADD THIS LINE
require 'config/db.php'; // Make sure this path is correct

$page_title = 'My Shifts';
include 'includes/header.php';

$user_id = $_SESSION['user_id'];

// Handle shift request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_shift_change'])) {
    $request_type = $_POST['request_type'];
    $shift_id = $_POST['shift_id'] ?? null;
    $requested_shift_id = $_POST['requested_shift_id'] ?? null;
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'] ?? $start_date;
    $reason = $_POST['reason'];
    $priority = $_POST['priority'];
    
    $stmt = $pdo->prepare("INSERT INTO EASYSALLES_SHIFT_REQUESTS 
                          (user_id, request_type, shift_id, requested_shift_id, start_date, end_date, reason, priority) 
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$user_id, $request_type, $shift_id, $requested_shift_id, $start_date, $end_date, $reason, $priority]);
    
    $_SESSION['success'] = "Shift request submitted successfully!";
    header("Location: my-shifts.php");
    exit();
}

// Get upcoming shifts
$current_date = date('Y-m-d');
$stmt = $pdo->prepare("SELECT us.*, s.shift_name, s.start_time, s.end_time, s.color 
                      FROM EASYSALLES_USER_SHIFTS us
                      JOIN EASYSALLES_SHIFTS s ON us.shift_id = s.shift_id
                      WHERE us.user_id = ? AND us.assigned_date >= ?
                      ORDER BY us.assigned_date, s.start_time");
$stmt->execute([$user_id, $current_date]);
$upcoming_shifts = $stmt->fetchAll();

// Get shift history
$stmt = $pdo->prepare("SELECT us.*, s.shift_name, s.start_time, s.end_time 
                      FROM EASYSALLES_USER_SHIFTS us
                      JOIN EASYSALLES_SHIFTS s ON us.shift_id = s.shift_id
                      WHERE us.user_id = ? AND us.assigned_date < ?
                      ORDER BY us.assigned_date DESC
                      LIMIT 20");
$stmt->execute([$user_id, $current_date]);
$shift_history = $stmt->fetchAll();

// Get my shift requests
$stmt = $pdo->prepare("SELECT * FROM EASYSALLES_SHIFT_REQUESTS 
                      WHERE user_id = ? 
                      ORDER BY created_at DESC");
$stmt->execute([$user_id]);
$my_requests = $stmt->fetchAll();

// Get available shifts for swapping
$stmt = $pdo->query("SELECT * FROM EASYSALLES_SHIFTS ORDER BY start_time");
$all_shifts = $stmt->fetchAll();

// Get other staff for cover requests
$stmt = $pdo->prepare("SELECT user_id, full_name, username FROM EASYSALLES_USERS 
                    WHERE role = 2 AND status = 'active' AND user_id != ?");
$stmt->execute([$user_id]);
$other_staff = $stmt->fetchAll();
?>
<style>
    /* My Shifts Dashboard */
    .my-shifts-dashboard {
        max-width: 1400px;
        margin: 0 auto;
        padding: 2rem;
    }
    
    .dashboard-header {
        margin-bottom: 3rem;
        animation: fadeInDown 0.6s ease-out;
    }
    
    .welcome-section {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 2rem;
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
    }
    
    .current-time {
        background: linear-gradient(135deg, rgba(124, 58, 237, 0.1), rgba(236, 72, 153, 0.05));
        padding: 1.5rem 2rem;
        border-radius: 20px;
        text-align: center;
        border: 1px solid rgba(124, 58, 237, 0.2);
    }
    
    .time-display {
        font-size: 2rem;
        font-weight: 700;
        color: var(--primary);
        margin-bottom: 0.5rem;
        font-family: 'Poppins', sans-serif;
    }
    
    .date-display {
        color: #64748b;
        font-weight: 500;
    }
    
    /* Shifts Grid */
    .shifts-grid {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 2rem;
        margin-bottom: 3rem;
    }
    .modal-content {
    background: var(--card-bg);
    border-radius: 20px;
    width: 90%;
    max-width: 600px;
    max-height: 90vh; /* Limit maximum height */
    margin: 2rem auto;
    position: relative;
    animation: slideDown 0.4s ease;
    border: 1px solid var(--border);
    overflow-y: auto; /* Make entire modal scrollable */
}

/* Scrollbar styling for the entire modal */
.modal-content::-webkit-scrollbar {
    width: 8px;
}

.modal-content::-webkit-scrollbar-track {
    background: rgba(0, 0, 0, 0.05);
    border-radius: 4px;
}

.modal-content::-webkit-scrollbar-thumb {
    background: var(--primary);
    border-radius: 4px;
}

.modal-content::-webkit-scrollbar-thumb:hover {
    background: var(--secondary);
}
    @media (max-width: 1024px) {
        .shifts-grid {
            grid-template-columns: 1fr;
        }
    }
    
    /* Shift Cards */
    .shift-card {
        background: var(--card-bg);
        border-radius: 20px;
        padding: 2rem;
        box-shadow: 0 4px 25px rgba(0, 0, 0, 0.05);
        border: 1px solid var(--border);
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
    }
    
    .shift-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 12px 40px rgba(124, 58, 237, 0.15);
    }
    
    .shift-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 5px;
        height: 100%;
        background: linear-gradient(135deg, var(--primary), var(--secondary));
    }
    /* Modal Styles - Add scrollability */
.modal-content {
    background: var(--card-bg);
    border-radius: 20px;
    width: 90%;
    max-width: 600px;
    max-height: 90vh; /* Limit maximum height */
    margin: 2rem auto;
    position: relative;
    animation: slideDown 0.4s ease;
    border: 1px solid var(--border);
    display: flex;
    flex-direction: column;
    overflow: hidden; /* Keep this to contain child scrolling */
}

.modal-header {
    padding: 1.5rem 2rem;
    border-bottom: 1px solid var(--border);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-shrink: 0; /* Prevent header from shrinking */
}

.modal-body {
    padding: 2rem;
    overflow-y: auto; /* Make body scrollable */
    flex-grow: 1; /* Allow body to grow */
    max-height: calc(90vh - 130px); /* Calculate max height (adjust based on header/footer height) */
}

.modal-footer {
    padding: 1.5rem 2rem;
    border-top: 1px solid var(--border);
    display: flex;
    justify-content: flex-end;
    gap: 1rem;
    flex-shrink: 0; /* Prevent footer from shrinking */
}

/* Optional: Add scrollbar styling for better appearance */
.modal-body::-webkit-scrollbar {
    width: 8px;
}

.modal-body::-webkit-scrollbar-track {
    background: rgba(0, 0, 0, 0.05);
    border-radius: 4px;
}

.modal-body::-webkit-scrollbar-thumb {
    background: var(--primary);
    border-radius: 4px;
}

.modal-body::-webkit-scrollbar-thumb:hover {
    background: var(--secondary);
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
    
    .btn-primary {
        background: linear-gradient(135deg, var(--primary), var(--secondary));
        color: white;
        border: none;
        padding: 0.875rem 1.5rem;
        border-radius: 12px;
        font-family: 'Poppins', sans-serif;
        font-weight: 600;
        font-size: 1rem;
        cursor: pointer;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 25px rgba(124, 58, 237, 0.3);
    }
    
    /* Shifts List */
    .shifts-list {
        list-style: none;
        padding: 0;
        margin: 0;
    }
    
    .shift-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 1.25rem;
        border-bottom: 1px solid var(--border);
        transition: all 0.3s ease;
    }
    
    .shift-item:hover {
        background: linear-gradient(135deg, rgba(124, 58, 237, 0.05), rgba(236, 72, 153, 0.02));
        transform: translateX(5px);
        border-radius: 10px;
    }
    
    .shift-item:last-child {
        border-bottom: none;
    }
    
    .shift-info {
        display: flex;
        align-items: center;
        gap: 1rem;
    }
    
    .shift-color {
        width: 50px;
        height: 50px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
        color: white;
    }
    
    .shift-details h4 {
        font-weight: 600;
        margin-bottom: 0.25rem;
        color: var(--text);
    }
    
    .shift-meta {
        display: flex;
        gap: 1rem;
        font-size: 0.9rem;
        color: #64748b;
    }
    
    .shift-time {
        font-weight: 600;
        color: var(--primary);
    }
    
    .shift-status {
        display: inline-block;
        padding: 0.35rem 1rem;
        border-radius: 20px;
        font-size: 0.85rem;
        font-weight: 600;
        text-transform: uppercase;
    }
    
    .status-scheduled {
        background: linear-gradient(135deg, rgba(59, 130, 246, 0.1), rgba(37, 99, 235, 0.05));
        color: #3B82F6;
    }
    
    .status-completed {
        background: linear-gradient(135deg, rgba(16, 185, 129, 0.1), rgba(5, 150, 105, 0.05));
        color: #10B981;
    }
    
    .status-absent {
        background: linear-gradient(135deg, rgba(245, 158, 11, 0.1), rgba(217, 119, 6, 0.05));
        color: #F59E0B;
    }
    
    .status-cancelled {
        background: linear-gradient(135deg, rgba(239, 68, 68, 0.1), rgba(220, 38, 38, 0.05));
        color: #EF4444;
    }
    
    .shift-actions {
        display: flex;
        gap: 0.5rem;
    }
    
    .btn-action {
        width: 40px;
        height: 40px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        border: none;
        cursor: pointer;
        transition: all 0.3s ease;
        font-size: 1rem;
    }
    
    .btn-swap {
        background: linear-gradient(135deg, rgba(139, 92, 246, 0.1), rgba(124, 58, 237, 0.05));
        color: #8B5CF6;
    }
    
    .btn-swap:hover {
        background: #8B5CF6;
        color: white;
        transform: scale(1.05);
    }
    
    /* Calendar View */
    .calendar-container {
        margin-top: 2rem;
    }
    
    .calendar-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1.5rem;
    }
    
    .calendar-nav {
        display: flex;
        align-items: center;
        gap: 1rem;
    }
    
    .btn-outline {
        background: transparent;
        border: 2px solid var(--border);
        color: var(--text);
        padding: 0.5rem 1rem;
        border-radius: 10px;
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
    
    .calendar-grid {
        display: grid;
        grid-template-columns: repeat(7, 1fr);
        gap: 0.5rem;
    }
    
    .calendar-day-header {
        text-align: center;
        padding: 1rem;
        font-weight: 600;
        color: var(--text);
        font-family: 'Poppins', sans-serif;
        background: linear-gradient(135deg, rgba(124, 58, 237, 0.1), rgba(236, 72, 153, 0.05));
        border-radius: 10px;
    }
    
    .calendar-day {
        min-height: 120px;
        padding: 0.75rem;
        border: 1px solid var(--border);
        border-radius: 10px;
        background: var(--bg);
        transition: all 0.3s ease;
    }
    
    .calendar-day:hover {
        transform: translateY(-3px);
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
    }
    
    .calendar-day.today {
        background: linear-gradient(135deg, rgba(124, 58, 237, 0.1), rgba(236, 72, 153, 0.05));
        border-color: var(--primary);
    }
    
    .day-number {
        font-weight: 600;
        margin-bottom: 0.5rem;
        text-align: right;
        color: var(--text);
    }
    
    .calendar-shift {
        background: var(--card-bg);
        padding: 0.5rem;
        border-radius: 6px;
        margin-bottom: 0.5rem;
        font-size: 0.8rem;
        border-left: 3px solid;
        transition: all 0.3s ease;
        cursor: pointer;
    }
    
    .calendar-shift:hover {
        transform: translateX(3px);
    }
    
    /* Stats Cards */
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
    
    .stat-total { color: var(--primary); }
    .stat-completed { color: #10B981; }
    .stat-absent { color: #F59E0B; }
    .stat-attendance { color: #3B82F6; }
    
    /* Requests List */
    .requests-list {
        list-style: none;
        padding: 0;
        margin: 0;
    }
    
    .request-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 1rem;
        border-bottom: 1px solid var(--border);
        transition: all 0.3s ease;
    }
    
    .request-item:hover {
        background: rgba(124, 58, 237, 0.05);
        border-radius: 10px;
    }
    
    .request-item:last-child {
        border-bottom: none;
    }
    
    .request-info h4 {
        font-weight: 600;
        margin: 0 0 0.25rem 0;
        color: var(--text);
    }
    
    .request-info p {
        font-size: 0.85rem;
        color: #64748b;
        margin: 0;
    }
    
    .request-status {
        padding: 0.35rem 0.75rem;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 600;
    }
    
    .status-pending {
        background: linear-gradient(135deg, rgba(245, 158, 11, 0.1), rgba(217, 119, 6, 0.05));
        color: #F59E0B;
    }
    
    .status-approved {
        background: linear-gradient(135deg, rgba(16, 185, 129, 0.1), rgba(5, 150, 105, 0.05));
        color: #10B981;
    }
    
    .status-rejected {
        background: linear-gradient(135deg, rgba(239, 68, 68, 0.1), rgba(220, 38, 38, 0.05));
        color: #EF4444;
    }
    
    /* Empty States */
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
    
    /* Form Elements */
    .form-group {
        margin-bottom: 1.5rem;
    }
    
    .form-group label {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        margin-bottom: 0.5rem;
        font-weight: 600;
        color: var(--text);
        font-family: 'Poppins', sans-serif;
    }
    
    .form-control {
        width: 100%;
        padding: 0.875rem 1rem;
        border: 1px solid var(--border);
        border-radius: 12px;
        background: var(--bg);
        color: var(--text);
        font-size: 1rem;
        transition: all 0.3s ease;
    }
    
    .form-control:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(124, 58, 237, 0.1);
    }
    
    /* Responsive Design */
    @media (max-width: 768px) {
        .my-shifts-dashboard {
            padding: 1.5rem;
        }
        
        .dashboard-header h1 {
            font-size: 2rem;
        }
        
        .shifts-grid {
            gap: 1.5rem;
        }
        
        .shift-card {
            padding: 1.5rem;
        }
        
        .calendar-grid {
            grid-template-columns: repeat(7, 1fr);
            font-size: 0.9rem;
        }
        
        .calendar-day {
            min-height: 100px;
            padding: 0.5rem;
        }
        
        .stats-grid {
            grid-template-columns: 1fr;
        }
        
        .shift-item {
            flex-direction: column;
            align-items: flex-start;
            gap: 1rem;
        }
        
        .shift-actions {
            width: 100%;
            justify-content: flex-end;
        }
    }
    
    @media (max-width: 480px) {
        .my-shifts-dashboard {
            padding: 1rem;
        }
        
        .calendar-grid {
            grid-template-columns: repeat(7, 1fr);
            font-size: 0.8rem;
        }
        
        .calendar-day {
            min-height: 80px;
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

<div class="my-shifts-dashboard">
    <!-- Welcome Section -->
    <div class="dashboard-header">
        <div class="welcome-section">
            <div class="welcome-text">
                <h1 id="greeting">My Shifts</h1>
                <p>View and manage your work schedule</p>
            </div>
            <div class="current-time">
                <div class="time-display" id="liveTime"><?php echo date('H:i:s'); ?></div>
                <div class="date-display" id="liveDate"><?php echo date('l, F j, Y'); ?></div>
            </div>
        </div>
    </div>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
        </div>
    <?php endif; ?>

    <div class="shifts-grid">
        <!-- Main Content -->
        <div>
            <!-- Upcoming Shifts -->
            <div class="shift-card">
                <div class="card-header">
                    <h2><i class="fas fa-calendar-alt"></i> Upcoming Shifts</h2>
                    <button class="btn-primary" onclick="openRequestModal('swap')">
                        <i class="fas fa-exchange-alt"></i> Request Change
                    </button>
                </div>
                
                <?php if (empty($upcoming_shifts)): ?>
                    <div class="empty-state">
                        <i class="fas fa-calendar-times"></i>
                        <h3>No Upcoming Shifts</h3>
                        <p>You have no shifts scheduled for the future</p>
                    </div>
                <?php else: ?>
                    <div class="shifts-list">
                        <?php foreach ($upcoming_shifts as $shift): 
                            $start = new DateTime($shift['start_time']);
                            $end = new DateTime($shift['end_time']);
                            $diff = $start->diff($end);
                            $duration = $diff->h . 'h';
                            if ($diff->i > 0) $duration .= ' ' . $diff->i . 'm';
                        ?>
                        <div class="shift-item">
                            <div class="shift-info">
                                <div class="shift-color" style="background: <?php echo $shift['color']; ?>;">
                                    <i class="fas fa-clock"></i>
                                </div>
                                <div class="shift-details">
                                    <h4><?php echo htmlspecialchars($shift['shift_name']); ?></h4>
                                    <div class="shift-meta">
                                        <span><?php echo date('D, M j', strtotime($shift['assigned_date'])); ?></span>
                                        <span class="shift-time">
                                            <?php echo date('h:i A', strtotime($shift['start_time'])); ?> - 
                                            <?php echo date('h:i A', strtotime($shift['end_time'])); ?>
                                        </span>
                                        <span>• <?php echo $duration; ?></span>
                                    </div>
                                </div>
                            </div>
                            <span class="shift-status status-<?php echo $shift['status']; ?>">
                                <?php echo ucfirst($shift['status']); ?>
                            </span>
                            <?php if ($shift['status'] == 'scheduled' && strtotime($shift['assigned_date']) > strtotime('+1 day')): ?>
                            <div class="shift-actions">
                                <button class="btn-action btn-swap" 
                                        onclick="requestForShift(<?php echo $shift['user_shift_id']; ?>, '<?php echo $shift['assigned_date']; ?>', <?php echo $shift['shift_id']; ?>)">
                                    <i class="fas fa-exchange-alt"></i>
                                </button>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Calendar View -->
            <div class="shift-card calendar-container">
                <div class="card-header">
                    <h2><i class="fas fa-calendar"></i> Shift Calendar</h2>
                    <div class="calendar-nav">
                        <button class="btn-outline" onclick="changeMonth(-1)">
                            <i class="fas fa-chevron-left"></i>
                        </button>
                        <span id="currentMonth" style="font-weight: 600; min-width: 150px; text-align: center;"></span>
                        <button class="btn-outline" onclick="changeMonth(1)">
                            <i class="fas fa-chevron-right"></i>
                        </button>
                    </div>
                </div>
                <div class="calendar-grid" id="calendarContainer">
                    <!-- Calendar will be generated by JavaScript -->
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div>
            <!-- My Requests -->
            <div class="shift-card">
                <div class="card-header">
                    <h2><i class="fas fa-paper-plane"></i> My Requests</h2>
                </div>
                
                <?php if (empty($my_requests)): ?>
                    <div style="text-align: center; padding: 2rem; color: #64748b;">
                        <i class="fas fa-inbox" style="font-size: 2rem; opacity: 0.3;"></i>
                        <p style="margin-top: 1rem;">No shift requests</p>
                    </div>
                <?php else: ?>
                    <div class="requests-list">
                        <?php foreach ($my_requests as $request): 
                            $type_labels = [
                                'swap' => 'Swap',
                                'timeoff' => 'Time Off',
                                'cover' => 'Cover',
                                'change' => 'Change'
                            ];
                        ?>
                        <div class="request-item">
                            <div class="request-info">
                                <h4><?php echo $type_labels[$request['request_type']] ?? $request['request_type']; ?></h4>
                                <p>
                                    <?php if ($request['start_date']): ?>
                                        <?php echo date('M j', strtotime($request['start_date'])); ?>
                                        <?php if ($request['end_date'] && $request['end_date'] != $request['start_date']): ?>
                                            - <?php echo date('j', strtotime($request['end_date'])); ?>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </p>
                            </div>
                            <span class="request-status status-<?php echo $request['status']; ?>">
                                <?php echo ucfirst($request['status']); ?>
                            </span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Quick Stats -->
            <div class="shift-card" style="margin-top: 1.5rem;">
                <div class="card-header">
                    <h2><i class="fas fa-chart-line"></i> Quick Stats</h2>
                </div>
                <div class="stats-grid">
                    <?php
                    $this_month = date('Y-m');
                    $stmt = $pdo->prepare("SELECT 
                        COUNT(*) as total_shifts,
                        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_shifts,
                        SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent_shifts
                        FROM EASYSALLES_USER_SHIFTS 
                        WHERE user_id = ? AND DATE_FORMAT(assigned_date, '%Y-%m') = ?");
                    $stmt->execute([$user_id, $this_month]);
                    $stats = $stmt->fetch();
                    
                    $attendance_rate = $stats['total_shifts'] > 0 
                        ? round(($stats['completed_shifts'] / $stats['total_shifts']) * 100, 1) 
                        : 0;
                    ?>
                    <div class="stat-card">
                        <div class="stat-value stat-total"><?php echo $stats['total_shifts'] ?? 0; ?></div>
                        <div class="stat-label">Total Shifts</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-value stat-completed"><?php echo $stats['completed_shifts'] ?? 0; ?></div>
                        <div class="stat-label">Completed</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-value stat-absent"><?php echo $stats['absent_shifts'] ?? 0; ?></div>
                        <div class="stat-label">Absences</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-value stat-attendance"><?php echo $attendance_rate; ?>%</div>
                        <div class="stat-label">Attendance</div>
                    </div>
                </div>
                <div style="text-align: center; margin-top: 1rem; font-size: 0.85rem; color: #64748b;">
                    This month
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Request Modal -->
<div class="modal" id="requestModal">
    <div class="modal-content">
        <div class="modal-header">
            <h4 id="modalTitle">Request Shift Change</h4>
            <button type="button" class="close" onclick="closeRequestModal()">&times;</button>
        </div>
        <form method="POST" id="requestForm">
            <div class="modal-body">
                <input type="hidden" name="request_shift_change" value="1">
                <input type="hidden" name="user_shift_id" id="request_user_shift_id">
                <input type="hidden" name="shift_date" id="request_shift_date">
                
                <div class="form-group">
                    <label for="request_type">Request Type</label>
                    <select class="form-control" id="request_type" name="request_type" required onchange="updateRequestForm()">
                        <option value="swap">Swap Shift</option>
                        <option value="timeoff">Time Off</option>
                        <option value="cover">Cover Request</option>
                        <option value="change">Shift Change</option>
                    </select>
                </div>
                
                <div id="shiftInfo" class="alert alert-info" style="display: none;">
                    Selected Shift: <span id="selectedShiftInfo"></span>
                </div>
                
                <div class="form-group" id="dateRangeGroup">
                    <label>Date Range</label>
                    <div class="row">
                        <div class="col-6">
                            <input type="date" class="form-control" id="start_date" name="start_date" required>
                        </div>
                        <div class="col-6">
                            <input type="date" class="form-control" id="end_date" name="end_date">
                            <small class="text-muted">Leave empty for single day</small>
                        </div>
                    </div>
                </div>
                
                <div class="form-group" id="shiftSelectionGroup" style="display: none;">
                    <label for="shift_id">Current Shift</label>
                    <select class="form-control" id="shift_id" name="shift_id">
                        <option value="">Select shift...</option>
                        <?php foreach ($all_shifts as $shift): ?>
                        <option value="<?php echo $shift['shift_id']; ?>">
                            <?php echo htmlspecialchars($shift['shift_name']); ?> 
                            (<?php echo date('h:i A', strtotime($shift['start_time'])); ?> - 
                            <?php echo date('h:i A', strtotime($shift['end_time'])); ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group" id="newShiftGroup" style="display: none;">
                    <label for="requested_shift_id">New Shift (Optional)</label>
                    <select class="form-control" id="requested_shift_id" name="requested_shift_id">
                        <option value="">Select new shift...</option>
                        <?php foreach ($all_shifts as $shift): ?>
                        <option value="<?php echo $shift['shift_id']; ?>">
                            <?php echo htmlspecialchars($shift['shift_name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group" id="staffGroup" style="display: none;">
                    <label for="requested_user_id">Request Cover From</label>
                    <select class="form-control" id="requested_user_id" name="requested_user_id">
                        <option value="">Select staff member...</option>
                        <?php foreach ($other_staff as $staff): ?>
                        <option value="<?php echo $staff['user_id']; ?>">
                            <?php echo htmlspecialchars($staff['full_name'] ?: $staff['username']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="reason">Reason</label>
                    <textarea class="form-control" id="reason" name="reason" rows="3" required 
                              placeholder="Please provide a reason for your request..."></textarea>
                </div>
                
                <div class="form-group">
                    <label for="priority">Priority</label>
                    <select class="form-control" id="priority" name="priority">
                        <option value="low">Low</option>
                        <option value="medium" selected>Medium</option>
                        <option value="high">High</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeRequestModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Submit Request</button>
            </div>
        </form>
    </div>
</div>

<script>
// Initialize calendar
let currentMonth = new Date().getMonth();
let currentYear = new Date().getFullYear();

// Live Time Update
function updateTime() {
    const now = new Date();
    const timeElement = document.getElementById('liveTime');
    const dateElement = document.getElementById('liveDate');
    
    const options = { 
        weekday: 'long', 
        year: 'numeric', 
        month: 'long', 
        day: 'numeric' 
    };
    
    timeElement.textContent = now.toLocaleTimeString('en-US', { hour12: false });
    dateElement.textContent = now.toLocaleDateString('en-US', options);
}

// Update time every second
setInterval(updateTime, 1000);

// Generate calendar
function generateCalendar(month, year) {
    const monthNames = ["January", "February", "March", "April", "May", "June",
        "July", "August", "September", "October", "November", "December"];
    
    document.getElementById('currentMonth').textContent = `${monthNames[month]} ${year}`;
    
    const container = document.getElementById('calendarContainer');
    container.innerHTML = '';
    
    // Add day headers
    const days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
    days.forEach(day => {
        const dayHeader = document.createElement('div');
        dayHeader.className = 'calendar-day-header';
        dayHeader.textContent = day;
        container.appendChild(dayHeader);
    });
    
    // Get first day of month
    const firstDay = new Date(year, month, 1).getDay();
    const daysInMonth = new Date(year, month + 1, 0).getDate();
    
    // Add empty days
    for (let i = 0; i < firstDay; i++) {
        const emptyDay = document.createElement('div');
        emptyDay.className = 'calendar-day';
        container.appendChild(emptyDay);
    }
    
    // Add days
    const today = new Date();
    const isCurrentMonth = today.getMonth() === month && today.getFullYear() === year;
    const shifts = <?php echo json_encode($upcoming_shifts); ?>;
    
    for (let day = 1; day <= daysInMonth; day++) {
        const dayDiv = document.createElement('div');
        dayDiv.className = 'calendar-day';
        
        if (isCurrentMonth && day === today.getDate()) {
            dayDiv.classList.add('today');
        }
        
        const dayNumber = document.createElement('div');
        dayNumber.className = 'day-number';
        dayNumber.textContent = day;
        dayDiv.appendChild(dayNumber);
        
        // Add shift events for this day
        const dateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
        
        shifts.forEach(shift => {
            if (shift.assigned_date === dateStr) {
                const shiftDiv = document.createElement('div');
                shiftDiv.className = 'calendar-shift';
                shiftDiv.style.borderLeftColor = shift.color;
                shiftDiv.style.background = shift.color + '20';
                shiftDiv.innerHTML = `
                    <div style="font-weight: 600; margin-bottom: 2px;">${shift.shift_name}</div>
                    <div style="font-size: 0.75rem;">${formatTime(shift.start_time)} - ${formatTime(shift.end_time)}</div>
                `;
                shiftDiv.addEventListener('click', () => {
                    requestForShift(shift.user_shift_id, shift.assigned_date, shift.shift_id);
                });
                dayDiv.appendChild(shiftDiv);
            }
        });
        
        container.appendChild(dayDiv);
    }
}

function formatTime(timeStr) {
    const [hours, minutes] = timeStr.split(':');
    const hour = parseInt(hours);
    const ampm = hour >= 12 ? 'PM' : 'AM';
    const displayHour = hour % 12 || 12;
    return `${displayHour}:${minutes} ${ampm}`;
}

function changeMonth(delta) {
    currentMonth += delta;
    if (currentMonth < 0) {
        currentMonth = 11;
        currentYear--;
    } else if (currentMonth > 11) {
        currentMonth = 0;
        currentYear++;
    }
    generateCalendar(currentMonth, currentYear);
}

function openRequestModal(type) {
    document.getElementById('requestModal').style.display = 'block';
    if (type) {
        document.getElementById('request_type').value = type;
        updateRequestForm();
    }
}

function requestForShift(userShiftId, shiftDate, shiftId) {
    openRequestModal('swap');
    document.getElementById('request_user_shift_id').value = userShiftId;
    document.getElementById('request_shift_date').value = shiftDate;
    
    // Find shift info
    const shifts = <?php echo json_encode($upcoming_shifts); ?>;
    const shift = shifts.find(s => s.user_shift_id == userShiftId);
    if (shift) {
        document.getElementById('shiftInfo').style.display = 'block';
        document.getElementById('selectedShiftInfo').textContent = 
            `${shift.shift_name} on ${shiftDate} (${formatTime(shift.start_time)} - ${formatTime(shift.end_time)})`;
        document.getElementById('start_date').value = shiftDate;
        document.getElementById('end_date').value = shiftDate;
        document.getElementById('shift_id').value = shiftId;
    }
}

function openRequestModal(type) {
    const modal = document.getElementById('requestModal');
    modal.style.display = 'block';
    
    // Scroll to top of modal content when opening
    setTimeout(() => {
        const modalContent = modal.querySelector('.modal-content');
        if (modalContent) {
            modalContent.scrollTop = 0;
        }
    }, 10);
    
    if (type) {
        document.getElementById('request_type').value = type;
        updateRequestForm();
    }
}

function closeRequestModal() {
    document.getElementById('requestModal').style.display = 'none';
    // Reset form
    document.getElementById('requestForm').reset();
    document.getElementById('request_user_shift_id').value = '';
    document.getElementById('shiftInfo').style.display = 'none';
    
    // Reset scroll position
    const modalContent = document.querySelector('.modal-content');
    if (modalContent) {
        modalContent.scrollTop = 0;
    }
}

function updateRequestForm() {
    const type = document.getElementById('request_type').value;
    
    // Hide all optional groups first
    document.getElementById('shiftSelectionGroup').style.display = 'none';
    document.getElementById('newShiftGroup').style.display = 'none';
    document.getElementById('staffGroup').style.display = 'none';
    
    // Show relevant groups based on type
    switch(type) {
        case 'swap':
            document.getElementById('newShiftGroup').style.display = 'block';
            document.getElementById('modalTitle').textContent = 'Request Shift Swap';
            break;
        case 'cover':
            document.getElementById('staffGroup').style.display = 'block';
            document.getElementById('modalTitle').textContent = 'Request Shift Cover';
            break;
        case 'change':
            document.getElementById('newShiftGroup').style.display = 'block';
            document.getElementById('modalTitle').textContent = 'Request Shift Change';
            break;
        case 'timeoff':
            document.getElementById('modalTitle').textContent = 'Request Time Off';
            break;
    }
    
    // Show shift selection for non-specific requests
    if (!document.getElementById('request_user_shift_id').value) {
        document.getElementById('shiftSelectionGroup').style.display = 'block';
    }
}

function closeRequestModal() {
    document.getElementById('requestModal').style.display = 'none';
    // Reset form
    document.getElementById('requestForm').reset();
    document.getElementById('request_user_shift_id').value = '';
    document.getElementById('shiftInfo').style.display = 'none';
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    updateTime();
    setInterval(updateTime, 1000);
    
    generateCalendar(currentMonth, currentYear);
    
    // Set default dates in modal
    const today = new Date().toISOString().split('T')[0];
    document.getElementById('start_date').value = today;
});

// Display greeting based on time of day
document.addEventListener('DOMContentLoaded', function() {
    const hour = new Date().getHours();
    let greeting = '';
    
    if (hour < 12) greeting = 'Good morning';
    else if (hour < 18) greeting = 'Good afternoon';
    else greeting = 'Good evening';
    
    const greetingElement = document.getElementById('greeting');
    if (greetingElement) {
        greetingElement.innerHTML = `${greeting}, <?php echo htmlspecialchars($_SESSION['username']); ?>! 👋`;
    }
});
</script>

<?php 
ob_end_flush(); // Flush output buffer
include 'includes/footer.php'; 
?>