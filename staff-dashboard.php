<?php
// staff-dashboard.php
// Turn on full error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


// Turn on full error reporting for debugging
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

// Database connection for stats
require 'config/db.php';

// Get today's sales stats for this staff member
$user_id = $_SESSION['user_id'];
$today = date('Y-m-d');

// Get today's sales stats using your table structure
$sql = "SELECT COUNT(*) as total_sales, 
               SUM(final_amount) as total_revenue,
               AVG(final_amount) as avg_sale
        FROM EASYSALLES_SALES 
        WHERE staff_id = ? AND DATE(sale_date) = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id, $today]);
$today_stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Get weekly performance
$week_start = date('Y-m-d', strtotime('-6 days'));
$sql_week = "SELECT DATE(sale_date) as date, 
                    COUNT(*) as sales_count,
                    SUM(final_amount) as daily_revenue
             FROM EASYSALLES_SALES 
             WHERE staff_id = ? AND sale_date >= ?
             GROUP BY DATE(sale_date)
             ORDER BY date ASC";
$stmt_week = $pdo->prepare($sql_week);
$stmt_week->execute([$user_id, $week_start]);
$weekly_data = $stmt_week->fetchAll(PDO::FETCH_ASSOC);

// Get recent sales with product names
$sql_recent = "SELECT s.*, si.quantity, p.product_name 
               FROM EASYSALLES_SALES s
               JOIN EASYSALLES_SALE_ITEMS si ON s.sale_id = si.sale_id
               JOIN EASYSALLES_PRODUCTS p ON si.product_id = p.product_id
               WHERE s.staff_id = ?
               ORDER BY s.sale_date DESC 
               LIMIT 5";
$stmt_recent = $pdo->prepare($sql_recent);
$stmt_recent->execute([$user_id]);
$recent_sales = $stmt_recent->fetchAll(PDO::FETCH_ASSOC);
?>

<style>
    /* Dashboard Specific Styles */
    .dashboard {
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
    
    /* Stats Grid */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 1.5rem;
        margin-bottom: 3rem;
    }
    
    .stat-card {
        background: var(--card-bg);
        border-radius: 20px;
        padding: 1.5rem;
        box-shadow: 0 4px 25px rgba(0, 0, 0, 0.05);
        border: 1px solid var(--border);
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
    }
    
    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 12px 40px rgba(124, 58, 237, 0.15);
    }
    
    .stat-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 5px;
        height: 100%;
        background: linear-gradient(135deg, var(--primary), var(--secondary));
    }
    
    .stat-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1rem;
    }
    
    .stat-icon {
        width: 56px;
        height: 56px;
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        color: white;
    }
    
    .sales-icon { background: linear-gradient(135deg, #10B981, #059669); }
    .revenue-icon { background: linear-gradient(135deg, #3B82F6, #1D4ED8); }
    .average-icon { background: linear-gradient(135deg, #F59E0B, #D97706); }
    .target-icon { background: linear-gradient(135deg, #EC4899, #BE185D); }
    
    .stat-value {
        font-family: 'Poppins', sans-serif;
        font-size: 2.5rem;
        font-weight: 700;
        color: var(--text);
        margin: 0.5rem 0;
    }
    
    .stat-label {
        color: #64748b;
        font-size: 0.95rem;
        font-weight: 500;
    }
    
    .stat-trend {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        margin-top: 0.5rem;
        font-size: 0.9rem;
        font-weight: 600;
    }
    
    .trend-up { color: #10B981; }
    .trend-down { color: #EF4444; }
    
    /* Quick Actions */
    .quick-actions {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1.5rem;
        margin-bottom: 3rem;
    }
    
    .action-card {
        background: var(--card-bg);
        border-radius: 20px;
        padding: 2rem;
        text-align: center;
        text-decoration: none;
        color: var(--text);
        border: 2px solid transparent;
        transition: all 0.3s ease;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
    }
    
    .action-card:hover {
        border-color: var(--primary);
        transform: translateY(-5px);
        box-shadow: 0 12px 35px rgba(124, 58, 237, 0.15);
    }
    
    .action-icon {
        width: 70px;
        height: 70px;
        border-radius: 18px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 1rem;
        font-size: 1.8rem;
        color: white;
    }
    
    .record-sale { background: linear-gradient(135deg, var(--primary), #8B5CF6); }
    .view-sales { background: linear-gradient(135deg, #3B82F6, #2563EB); }
    .products { background: linear-gradient(135deg, #10B981, #059669); }
    .profile { background: linear-gradient(135deg, #F59E0B, #D97706); }
    
    .action-title {
        font-family: 'Poppins', sans-serif;
        font-size: 1.2rem;
        font-weight: 600;
        margin-bottom: 0.5rem;
    }
    
    .action-desc {
        color: #64748b;
        font-size: 0.9rem;
    }
    
    /* Charts & Recent Activity */
    .dashboard-grid {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 2rem;
        margin-bottom: 3rem;
    }
    
    @media (max-width: 1024px) {
        .dashboard-grid {
            grid-template-columns: 1fr;
        }
    }
    
    .chart-container, .recent-activity {
        background: var(--card-bg);
        border-radius: 20px;
        padding: 2rem;
        box-shadow: 0 4px 25px rgba(0, 0, 0, 0.05);
        border: 1px solid var(--border);
    }
    
    .section-title {
        font-family: 'Poppins', sans-serif;
        font-size: 1.5rem;
        font-weight: 600;
        margin-bottom: 1.5rem;
        color: var(--text);
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }
    
    .section-title i {
        color: var(--primary);
    }
    
    .chart-placeholder {
        height: 300px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: linear-gradient(135deg, rgba(124, 58, 237, 0.05), rgba(236, 72, 153, 0.05));
        border-radius: 15px;
        border: 2px dashed var(--border);
    }
    
    .sales-list {
        list-style: none;
        padding: 0;
        margin: 0;
    }
    
    .sale-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 1.2rem;
        border-bottom: 1px solid var(--border);
        transition: all 0.3s ease;
    }
    
    .sale-item:hover {
        background: linear-gradient(135deg, rgba(124, 58, 237, 0.05), rgba(236, 72, 153, 0.02));
        transform: translateX(5px);
        border-radius: 10px;
    }
    
    .sale-item:last-child {
        border-bottom: none;
    }
    
    .sale-info h4 {
        font-weight: 600;
        margin-bottom: 0.25rem;
        color: var(--text);
    }
    
    .sale-time {
        font-size: 0.85rem;
        color: #64748b;
    }
    
    .sale-amount {
        font-family: 'Poppins', sans-serif;
        font-weight: 700;
        color: var(--primary);
        font-size: 1.2rem;
    }
    
    /* Performance Summary */
    .performance-summary {
        background: linear-gradient(135deg, var(--primary), var(--secondary));
        border-radius: 25px;
        padding: 3rem;
        color: white;
        margin-bottom: 3rem;
        position: relative;
        overflow: hidden;
    }
    
    .performance-summary::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.05'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
    }
    
    .performance-content {
        position: relative;
        z-index: 1;
        text-align: center;
    }
    
    .performance-title {
        font-family: 'Poppins', sans-serif;
        font-size: 2rem;
        margin-bottom: 1rem;
    }
    
    .performance-metric {
        font-size: 3.5rem;
        font-weight: 800;
        margin-bottom: 0.5rem;
        text-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
    }
    
    .performance-subtitle {
        opacity: 0.9;
        margin-bottom: 2rem;
        font-size: 1.1rem;
    }
    
    .performance-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 2rem;
        margin-top: 2rem;
    }
    
    .performance-stat {
        text-align: center;
    }
    
    .performance-stat-value {
        font-size: 2rem;
        font-weight: 700;
        margin-bottom: 0.5rem;
    }
    
    .performance-stat-label {
        opacity: 0.8;
        font-size: 0.95rem;
    }
    
    /* Daily Tips */
    .daily-tip {
        background: linear-gradient(135deg, rgba(6, 182, 212, 0.1), rgba(124, 58, 237, 0.1));
        border-radius: 20px;
        padding: 2rem;
        margin-top: 3rem;
        border-left: 5px solid var(--accent);
    }
    
    .tip-header {
        display: flex;
        align-items: center;
        gap: 1rem;
        margin-bottom: 1rem;
    }
    
    .tip-header i {
        color: var(--accent);
        font-size: 1.5rem;
    }
    
    .tip-title {
        font-family: 'Poppins', sans-serif;
        font-weight: 600;
        color: var(--text);
    }
    
    .tip-content {
        color: #475569;
        line-height: 1.7;
    }
    
    /* Responsive Design */
    @media (max-width: 768px) {
        .dashboard {
            padding: 1.5rem;
        }
        
        .welcome-section {
            flex-direction: column;
            text-align: center;
        }
        
        .stats-grid {
            grid-template-columns: 1fr;
        }
        
        .quick-actions {
            grid-template-columns: repeat(2, 1fr);
        }
        
        .performance-summary {
            padding: 2rem 1.5rem;
        }
        
        .performance-metric {
            font-size: 2.5rem;
        }
    }
    
    @media (max-width: 480px) {
        .quick-actions {
            grid-template-columns: 1fr;
        }
        
        .performance-stats {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="dashboard">
    <!-- Welcome Section -->
    <div class="dashboard-header">
        <div class="welcome-section">
            <div class="welcome-text">
                <h1 id="greeting">Welcome back, <?php echo htmlspecialchars($_SESSION['username']); ?>! 👋</h1>
                <p>Here's your sales performance overview for today. Ready to make more sales?</p>
            </div>
            <div class="current-time">
                <div class="time-display" id="liveTime"><?php echo date('H:i:s'); ?></div>
                <div class="date-display" id="liveDate"><?php echo date('l, F j, Y'); ?></div>
            </div>
        </div>
    </div>
    
    <!-- Stats Grid -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-header">
                <div>
                    <div class="stat-label">Today's Sales</div>
                    <div class="stat-value"><?php echo $today_stats['total_sales'] ?? 0; ?></div>
                </div>
                <div class="stat-icon sales-icon">
                    <i class="fas fa-shopping-cart"></i>
                </div>
            </div>
            <div class="stat-trend trend-up">
                <i class="fas fa-arrow-up"></i>
                <span>+12% from yesterday</span>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-header">
                <div>
                    <div class="stat-label">Today's Revenue</div>
                    <div class="stat-value">$<?php echo number_format($today_stats['total_revenue'] ?? 0, 2); ?></div>
                </div>
                <div class="stat-icon revenue-icon">
                    <i class="fas fa-money-bill-wave"></i>
                </div>
            </div>
            <div class="stat-trend trend-up">
                <i class="fas fa-arrow-up"></i>
                <span>+8% from yesterday</span>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-header">
                <div>
                    <div class="stat-label">Average Sale</div>
                    <div class="stat-value">$<?php echo number_format($today_stats['avg_sale'] ?? 0, 2); ?></div>
                </div>
                <div class="stat-icon average-icon">
                    <i class="fas fa-chart-line"></i>
                </div>
            </div>
            <div class="stat-trend trend-down">
                <i class="fas fa-arrow-down"></i>
                <span>-3% from yesterday</span>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-header">
                <div>
                    <div class="stat-label">Daily Target</div>
                    <div class="stat-value">85%</div>
                </div>
                <div class="stat-icon target-icon">
                    <i class="fas fa-bullseye"></i>
                </div>
            </div>
            <div class="stat-trend trend-up">
                <i class="fas fa-arrow-up"></i>
                <span>15% to reach target</span>
            </div>
        </div>
    </div>
    
    <!-- Quick Actions -->
    <div class="quick-actions">
        <a href="sale-record.php" class="action-card">
            <div class="action-icon record-sale">
                <i class="fas fa-cash-register"></i>
            </div>
            <div class="action-title">Record New Sale</div>
            <div class="action-desc">Add a new sale transaction quickly</div>
        </a>
        
        <a href="sales-list.php" class="action-card">
            <div class="action-icon view-sales">
                <i class="fas fa-list"></i>
            </div>
            <div class="action-title">View All Sales</div>
            <div class="action-desc">Check your sales history</div>
        </a>
        
        <a href="products.php" class="action-card">
            <div class="action-icon products">
                <i class="fas fa-box"></i>
            </div>
            <div class="action-title">Products</div>
            <div class="action-desc">Browse available products</div>
        </a>
        
        <a href="profile.php" class="action-card">
            <div class="action-icon profile">
                <i class="fas fa-user-cog"></i>
            </div>
            <div class="action-title">Profile Settings</div>
            <div class="action-desc">Update your information</div>
        </a>
    </div>
    
    <!-- Charts & Recent Activity -->
    <div class="dashboard-grid">
        <div class="chart-container">
            <div class="section-title">
                <i class="fas fa-chart-bar"></i>
                <span>Weekly Performance</span>
            </div>
            <div class="chart-placeholder">
                <div style="text-align: center;">
                    <i class="fas fa-chart-line" style="font-size: 3rem; color: var(--primary); margin-bottom: 1rem;"></i>
                    <p>Weekly sales chart will be displayed here</p>
                    <small class="text-muted">(Requires chart library integration)</small>
                </div>
            </div>
        </div>
        
        <div class="recent-activity">
            <div class="section-title">
                <i class="fas fa-history"></i>
                <span>Recent Sales</span>
            </div>
            <ul class="sales-list">
                <?php if (empty($recent_sales)): ?>
                    <li class="sale-item">
                        <div class="sale-info">
                            <h4>No sales yet today</h4>
                            <div class="sale-time">Start recording sales!</div>
                        </div>
                    </li>
                <?php else: ?>
                    <?php foreach ($recent_sales as $sale): ?>
                        <li class="sale-item">
                            <div class="sale-info">
                                <h4><?php echo htmlspecialchars($sale['product_name'] ?? 'Product'); ?></h4>
                                <div class="sale-time">
                                    <?php echo date('H:i', strtotime($sale['sale_date'])); ?> • 
                                    Qty: <?php echo $sale['quantity']; ?>
                                </div>
                            </div>
                            <div class="sale-amount">
                                $<?php echo number_format($sale['final_amount'], 2); ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                <?php endif; ?>
            </ul>
        </div>
    </div>
    
    <!-- Performance Summary -->
    <div class="performance-summary">
        <div class="performance-content">
            <div class="performance-title">Your Performance This Week</div>
            <div class="performance-metric">
                <?php 
                $week_total = 0;
                foreach ($weekly_data as $day) {
                    $week_total += $day['sales_count'];
                }
                echo $week_total;
                ?>
            </div>
            <div class="performance-subtitle">Total Sales This Week</div>
            
            <div class="performance-stats">
                <div class="performance-stat">
                    <div class="performance-stat-value">
                        $<?php 
                        $week_revenue = 0;
                        foreach ($weekly_data as $day) {
                            $week_revenue += $day['daily_revenue'];
                        }
                        echo number_format($week_revenue, 2);
                        ?>
                    </div>
                    <div class="performance-stat-label">Weekly Revenue</div>
                </div>
                
                <div class="performance-stat">
                    <div class="performance-stat-value">
                        <?php echo count($weekly_data); ?>
                    </div>
                    <div class="performance-stat-label">Active Days</div>
                </div>
                
                <div class="performance-stat">
                    <div class="performance-stat-value">
                        $<?php echo $week_total > 0 ? number_format($week_revenue / $week_total, 2) : '0.00'; ?>
                    </div>
                    <div class="performance-stat-label">Avg per Sale</div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Daily Tip -->
    <div class="daily-tip">
        <div class="tip-header">
            <i class="fas fa-lightbulb"></i>
            <div class="tip-title">💡 Sales Tip of the Day</div>
        </div>
        <div class="tip-content">
            "Focus on understanding customer needs before presenting products. Ask open-ended questions to discover what they're really looking for. This approach increases average sale value by up to 35%!"
        </div>
    </div>
</div>

<script>
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
    
    // Add hover effect to stat cards
    document.querySelectorAll('.stat-card').forEach(card => {
        card.addEventListener('mouseenter', function() {
            const icon = this.querySelector('.stat-icon');
            if (icon) {
                icon.style.transform = 'scale(1.1) rotate(5deg)';
                icon.style.transition = 'transform 0.3s ease';
            }
        });
        
        card.addEventListener('mouseleave', function() {
            const icon = this.querySelector('.stat-icon');
            if (icon) {
                icon.style.transform = 'scale(1) rotate(0deg)';
            }
        });
    });
    
    // Quick action card effects
    document.querySelectorAll('.action-card').forEach(card => {
        card.addEventListener('mouseenter', function() {
            const icon = this.querySelector('.action-icon');
            if (icon) {
                icon.style.transform = 'translateY(-10px) scale(1.1)';
                icon.style.transition = 'transform 0.3s ease';
            }
        });
        
        card.addEventListener('mouseleave', function() {
            const icon = this.querySelector('.action-icon');
            if (icon) {
                icon.style.transform = 'translateY(0) scale(1)';
            }
        });
    });
</script>

<?php include 'includes/footer.php'; ?>