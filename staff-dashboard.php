<?php
// staff-dashboard.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require 'includes/auth.php';
require_login();

// Redirect admin to admin dashboard
if (isset($_SESSION['role']) && $_SESSION['role'] == 1) {
    header('Location: admin-dashboard.php');
    exit();
}

$page_title = 'Staff Dashboard';
include 'includes/header.php';

require 'config/db.php';
$user_id = $_SESSION['user_id'];
$today = date('Y-m-d');
$now = date('H:i:s');

// Get user shift info
$shift_info = [];
$current_shift = null;

try {
    $stmt = $pdo->prepare("SELECT * FROM EASYSALLES_USERS WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    $shift_start = $user['shift_start'] ?? '09:00:00';
    $shift_end = $user['shift_end'] ?? '17:00:00';
    
    // Get today's attendance status
    $stmt = $pdo->prepare("SELECT * FROM EASYSALLES_ATTENDANCE WHERE user_id = ? AND date = ?");
    $stmt->execute([$user_id, $today]);
    $attendance = $stmt->fetch();
    
    // Get active session (if clocked in)
    $active_session = null;
    if ($attendance) {
        $stmt = $pdo->prepare("SELECT * FROM EASYSALLES_ATTENDANCE_SESSIONS WHERE attendance_id = ? AND clock_out IS NULL ORDER BY session_id DESC LIMIT 1");
        $stmt->execute([$attendance['attendance_id']]);
        $active_session = $stmt->fetch();
    }
    
} catch (PDOException $e) {
    // Tables might not exist yet
}

// Process clock in/out
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['clock_in'])) {
        handleClockIn($pdo, $user_id, $user, $today);
        header('Location: staff-dashboard.php');
        exit();
    } elseif (isset($_POST['clock_out'])) {
        handleClockOut($pdo, $user_id, $today);
        header('Location: staff-dashboard.php');
        exit();
    }
}

function handleClockIn($pdo, $user_id, $user, $today) {
    try {
        // Check if can clock in
        $shift_start = $user['shift_start'] ?? '09:00:00';
        $shift_end = $user['shift_end'] ?? '17:00:00';
        
        // Allow 15 minutes grace period
        $grace_start = date('H:i:s', strtotime("$shift_start - 15 minutes"));
        $grace_end = date('H:i:s', strtotime("$shift_end + 15 minutes"));
        
        $current_time = date('H:i:s');
        
        if ($current_time < $grace_start || $current_time > $grace_end) {
            $_SESSION['error'] = "Cannot clock in outside shift hours ($shift_start - $shift_end)";
            return;
        }
        
        // Check existing attendance for today
        $stmt = $pdo->prepare("SELECT * FROM EASYSALLES_ATTENDANCE WHERE user_id = ? AND date = ?");
        $stmt->execute([$user_id, $today]);
        $attendance = $stmt->fetch();
        
        if (!$attendance) {
            // Create new attendance record
            $stmt = $pdo->prepare("INSERT INTO EASYSALLES_ATTENDANCE (user_id, date, scheduled_start, scheduled_end, status, clock_in) VALUES (?, ?, ?, ?, 'present', ?)");
            $stmt->execute([$user_id, $today, $shift_start, $shift_end, $current_time]);
            $attendance_id = $pdo->lastInsertId();
        } else {
            $attendance_id = $attendance['attendance_id'];
        }
        
        // Check if already has active session
        $stmt = $pdo->prepare("SELECT * FROM EASYSALLES_ATTENDANCE_SESSIONS WHERE attendance_id = ? AND clock_out IS NULL");
        $stmt->execute([$attendance_id]);
        $active_session = $stmt->fetch();
        
        if ($active_session) {
            $_SESSION['error'] = "You already have an active session. Please clock out first.";
            return;
        }
        
        // Start new session
        $stmt = $pdo->prepare("INSERT INTO EASYSALLES_ATTENDANCE_SESSIONS (attendance_id, clock_in, session_type) VALUES (?, NOW(), 'work')");
        $stmt->execute([$attendance_id]);
        
        // Update attendance clock_in time
        if (!$attendance || !$attendance['clock_in']) {
            $stmt = $pdo->prepare("UPDATE EASYSALLES_ATTENDANCE SET clock_in = ? WHERE attendance_id = ?");
            $stmt->execute([$current_time, $attendance_id]);
        }
        
        $_SESSION['success'] = "Clocked in successfully at " . date('h:i A');
        
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error: " . $e->getMessage();
    }
}

function handleClockOut($pdo, $user_id, $today) {
    try {
        // Get attendance for today
        $stmt = $pdo->prepare("SELECT * FROM EASYSALLES_ATTENDANCE WHERE user_id = ? AND date = ?");
        $stmt->execute([$user_id, $today]);
        $attendance = $stmt->fetch();
        
        if (!$attendance) {
            $_SESSION['error'] = "No active attendance record found";
            return;
        }
        
        // Get active session
        $stmt = $pdo->prepare("SELECT * FROM EASYSALLES_ATTENDANCE_SESSIONS WHERE attendance_id = ? AND clock_out IS NULL ORDER BY session_id DESC LIMIT 1");
        $stmt->execute([$attendance['attendance_id']]);
        $active_session = $stmt->fetch();
        
        if (!$active_session) {
            $_SESSION['error'] = "No active session found";
            return;
        }
        
        $current_time = date('H:i:s');
        
        // End the session
        $stmt = $pdo->prepare("UPDATE EASYSALLES_ATTENDANCE_SESSIONS SET clock_out = NOW(), duration_minutes = TIMESTAMPDIFF(MINUTE, clock_in, NOW()) WHERE session_id = ?");
        $stmt->execute([$active_session['session_id']]);
        
        // Update attendance total hours
        $stmt = $pdo->prepare("
            UPDATE EASYSALLES_ATTENDANCE 
            SET clock_out = ?, 
                total_hours = (
                    SELECT SUM(duration_minutes) / 60.0 
                    FROM EASYSALLES_ATTENDANCE_SESSIONS 
                    WHERE attendance_id = ?
                )
            WHERE attendance_id = ?
        ");
        $stmt->execute([$current_time, $attendance['attendance_id'], $attendance['attendance_id']]);
        
        $_SESSION['success'] = "Clocked out successfully at " . date('h:i A');
        
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error: " . $e->getMessage();
    }
}

// Get today's sales stats
$today_stats = [];
$weekly_data = [];
$recent_sales = [];

try {
    $sql = "SELECT COUNT(*) as total_sales, 
                   SUM(final_amount) as total_revenue,
                   AVG(final_amount) as avg_sale
            FROM EASYSALLES_SALES 
            WHERE staff_id = ? AND DATE(sale_date) = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id, $today]);
    $today_stats = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}
?>
<style>
/* STAFF DASHBOARD SPECIFIC STYLES */
.clock-container {
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    border-radius: 20px;
    padding: 2rem;
    color: white;
    margin-bottom: 2rem;
    position: relative;
    overflow: hidden;
}

.clock-container::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.1'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
}

.clock-content {
    position: relative;
    z-index: 1;
    text-align: center;
}

.clock-status {
    font-size: 1.2rem;
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
}

.clock-status.active {
    background: rgba(255, 255, 255, 0.2);
    padding: 0.5rem 1rem;
    border-radius: 50px;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.clock-time {
    font-size: 3.5rem;
    font-weight: 700;
    margin-bottom: 1rem;
    font-family: 'Poppins', sans-serif;
}

.clock-shift {
    font-size: 1.1rem;
    opacity: 0.9;
    margin-bottom: 2rem;
}

.clock-actions {
    display: flex;
    justify-content: center;
    gap: 1rem;
    margin-top: 1rem;
}

.clock-btn {
    padding: 1rem 3rem;
    font-size: 1.2rem;
    font-weight: 600;
    border: none;
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 0.8rem;
    min-width: 180px;
    justify-content: center;
}

.clock-btn:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
}

.clock-in-btn {
    background: white;
    color: var(--primary);
}

.clock-out-btn {
    background: rgba(255, 255, 255, 0.2);
    color: white;
    border: 2px solid white;
}

.clock-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.session-history {
    background: var(--card-bg);
    border-radius: 15px;
    padding: 1.5rem;
    margin-top: 1.5rem;
}

.session-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.8rem;
    border-bottom: 1px solid var(--border);
}

.session-item:last-child {
    border-bottom: none;
}

.session-time {
    font-weight: 600;
    color: var(--primary);
}

.session-duration {
    color: var(--text-light);
    font-size: 0.9rem;
}

.todays-summary {
    background: var(--card-bg);
    border-radius: 15px;
    padding: 1.5rem;
    margin-top: 1.5rem;
}

.summary-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1rem;
    margin-top: 1rem;
}

.summary-item {
    text-align: center;
    padding: 1rem;
    background: rgba(124, 58, 237, 0.05);
    border-radius: 10px;
}

.summary-value {
    font-size: 1.8rem;
    font-weight: 700;
    color: var(--primary);
    margin-bottom: 0.3rem;
}

.summary-label {
    font-size: 0.9rem;
    color: var(--text-light);
}

/* Responsive */
@media (max-width: 768px) {
    .clock-actions {
        flex-direction: column;
        align-items: center;
    }
    
    .clock-btn {
        width: 100%;
        max-width: 300px;
    }
    
    .summary-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="dashboard">
    <!-- Messages -->
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success" style="margin-bottom: 1.5rem;">
            <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-error" style="margin-bottom: 1.5rem;">
            <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
        </div>
    <?php endif; ?>
    
    <!-- Clock In/Out Section -->
    <div class="clock-container">
        <div class="clock-content">
            <div class="clock-status <?php echo $active_session ? 'active' : ''; ?>">
                <?php if ($active_session): ?>
                    <i class="fas fa-circle" style="color: #10B981; font-size: 0.8rem;"></i>
                    <span>Currently Clocked In</span>
                <?php else: ?>
                    <i class="fas fa-circle" style="color: #EF4444; font-size: 0.8rem;"></i>
                    <span>Currently Clocked Out</span>
                <?php endif; ?>
            </div>
            
            <div class="clock-time" id="liveTime"><?php echo date('h:i:s A'); ?></div>
            <div class="clock-shift">
                <i class="fas fa-clock"></i>
                Today's Shift: <?php echo date('h:i A', strtotime($shift_start ?? '09:00:00')); ?> - 
                <?php echo date('h:i A', strtotime($shift_end ?? '17:00:00')); ?>
            </div>
            
            <form method="POST" class="clock-actions">
                <?php if (!$active_session): ?>
                    <button type="submit" name="clock_in" class="clock-btn clock-in-btn">
                        <i class="fas fa-sign-in-alt"></i>
                        Clock In
                    </button>
                <?php else: ?>
                    <button type="submit" name="clock_out" class="clock-btn clock-out-btn">
                        <i class="fas fa-sign-out-alt"></i>
                        Clock Out
                    </button>
                <?php endif; ?>
            </form>
            
            <!-- Today's summary -->
            <div class="todays-summary">
                <h4 style="margin-bottom: 1rem; color: var(--text);">
                    <i class="fas fa-chart-bar"></i> Today's Summary
                </h4>
                <div class="summary-grid">
                    <?php
                    // Get today's sessions summary
                    $today_hours = 0;
                    $session_count = 0;
                    if ($attendance) {
                        $stmt = $pdo->prepare("SELECT COUNT(*) as count, SUM(duration_minutes) as total_minutes FROM EASYSALLES_ATTENDANCE_SESSIONS WHERE attendance_id = ?");
                        $stmt->execute([$attendance['attendance_id']]);
                        $sessions_summary = $stmt->fetch();
                        $session_count = $sessions_summary['count'] ?? 0;
                        $today_hours = $sessions_summary['total_minutes'] ? round($sessions_summary['total_minutes'] / 60, 2) : 0;
                    }
                    ?>
                    <div class="summary-item">
                        <div class="summary-value"><?php echo $today_hours; ?>h</div>
                        <div class="summary-label">Hours Today</div>
                    </div>
                    <div class="summary-item">
                        <div class="summary-value"><?php echo $session_count; ?></div>
                        <div class="summary-label">Sessions</div>
                    </div>
                    <div class="summary-item">
                        <div class="summary-value">$<?php echo number_format($today_stats['total_revenue'] ?? 0, 2); ?></div>
                        <div class="summary-label">Sales Today</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Session History -->
    <?php if ($attendance): ?>
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-history"></i> Today's Sessions
            </h3>
        </div>
        <div class="session-history">
            <?php
            try {
                $stmt = $pdo->prepare("SELECT * FROM EASYSALLES_ATTENDANCE_SESSIONS WHERE attendance_id = ? ORDER BY clock_in DESC");
                $stmt->execute([$attendance['attendance_id']]);
                $sessions = $stmt->fetchAll();
                
                if (empty($sessions)): ?>
                    <div style="text-align: center; padding: 2rem;">
                        <i class="fas fa-clock" style="font-size: 2rem; color: var(--border);"></i>
                        <p style="margin-top: 1rem;">No sessions recorded today</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($sessions as $session): ?>
                        <div class="session-item">
                            <div>
                                <div class="session-time">
                                    <?php echo date('h:i A', strtotime($session['clock_in'])); ?>
                                    <?php if ($session['clock_out']): ?>
                                        → <?php echo date('h:i A', strtotime($session['clock_out'])); ?>
                                    <?php else: ?>
                                        → <span style="color: var(--success);">Active</span>
                                    <?php endif; ?>
                                </div>
                                <small class="text-muted">
                                    <?php echo ucfirst($session['session_type']); ?>
                                    <?php if ($session['clock_out'] && $session['duration_minutes']): ?>
                                        • <?php echo floor($session['duration_minutes'] / 60); ?>h <?php echo $session['duration_minutes'] % 60; ?>m
                                    <?php endif; ?>
                                </small>
                            </div>
                            <div>
                                <?php if ($session['clock_out']): ?>
                                    <span class="badge badge-success">Completed</span>
                                <?php else: ?>
                                    <span class="badge badge-primary">Active</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif;
            } catch (PDOException $e) {
                echo "<p style='color: var(--error); padding: 1rem;'>Error loading sessions</p>";
            }
            ?>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Quick Stats -->
    <div class="stats-grid" style="margin-top: 2rem;">
        <div class="stat-card">
            <div class="stat-icon" style="background: var(--primary-light); color: var(--primary);">
                <i class="fas fa-calendar-day"></i>
            </div>
            <div class="stat-content">
                <h3><?php echo $today_stats['total_sales'] ?? 0; ?></h3>
                <p>Today's Sales</p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon" style="background: var(--success-light); color: var(--success);">
                <i class="fas fa-money-bill-wave"></i>
            </div>
            <div class="stat-content">
                <h3>$<?php echo number_format($today_stats['total_revenue'] ?? 0, 2); ?></h3>
                <p>Today's Revenue</p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon" style="background: var(--warning-light); color: var(--warning);">
                <i class="fas fa-clock"></i>
            </div>
            <div class="stat-content">
                <h3><?php echo floor(($today_hours ?? 0)); ?>h</h3>
                <p>Hours Worked</p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon" style="background: var(--info-light); color: var(--info);">
                <i class="fas fa-chart-line"></i>
            </div>
            <div class="stat-content">
                <h3><?php echo $session_count; ?></h3>
                <p>Total Sessions</p>
            </div>
        </div>
    </div>
    
    <!-- Quick Actions -->
    <div class="quick-actions" style="margin-top: 2rem;">
        <a href="sale-record.php" class="action-card">
            <div class="action-icon record-sale">
                <i class="fas fa-cash-register"></i>
            </div>
            <div class="action-title">Record New Sale</div>
            <div class="action-desc">Add a new sale transaction</div>
        </a>
        
        <a href="sales-list.php" class="action-card">
            <div class="action-icon view-sales">
                <i class="fas fa-list"></i>
            </div>
            <div class="action-title">View My Sales</div>
            <div class="action-desc">Check your sales history</div>
        </a>
        
        <a href="attendance-history.php" class="action-card">
            <div class="action-icon profile">
                <i class="fas fa-history"></i>
            </div>
            <div class="action-title">Attendance History</div>
            <div class="action-desc">View your attendance records</div>
        </a>
        
        <a href="profile.php" class="action-card">
            <div class="action-icon profile">
                <i class="fas fa-user-cog"></i>
            </div>
            <div class="action-title">Profile Settings</div>
            <div class="action-desc">Update your information</div>
        </a>
    </div>
</div>

<script>
// Live Time Update
function updateTime() {
    const now = new Date();
    const timeElement = document.getElementById('liveTime');
    const options = { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true };
    timeElement.textContent = now.toLocaleTimeString('en-US', options);
}

// Auto-refresh every second
setInterval(updateTime, 1000);

// Auto-refresh page every 30 seconds to update status
setTimeout(() => {
    window.location.reload();
}, 30000);
</script>