<?php
// includes/functions.php
require_once 'config.php';
require_once 'db.php';

// Helper functions

// Get user initials for avatar
function getInitials($full_name) {
    $words = explode(' ', $full_name);
    $initials = '';
    
    foreach ($words as $word) {
        if (!empty($word)) {
            $initials .= strtoupper($word[0]);
            if (strlen($initials) >= 2) break;
        }
    }
    
    return $initials;
}

// Get avatar color based on name
function getAvatarColor($full_name) {
    $colors = ['purple', 'pink', 'cyan', 'green', 'orange', 'blue'];
    $hash = crc32($full_name);
    $index = abs($hash) % count($colors);
    return $colors[$index];
}

// Format currency
function formatCurrency($amount) {
    return '$' . number_format($amount, 2);
}

// Check if user can login based on shift hours
function canLogin($user_id) {
    if (!SHIFT_ENFORCEMENT) {
        return true;
    }
    
    try {
        $user = Database::query(
            "SELECT shift_start, shift_end, shift_days FROM easysalles_users WHERE id = ?",
            [$user_id]
        )->fetch();
        
        if (!$user || empty($user['shift_start']) || empty($user['shift_end'])) {
            return true; // No shift restrictions
        }
        
        $current_time = date('H:i:s');
        $current_day = date('N'); // 1 (Monday) through 7 (Sunday)
        
        // Check if today is a shift day
        if (!empty($user['shift_days'])) {
            $shift_days = explode(',', $user['shift_days']);
            if (!in_array($current_day, $shift_days)) {
                return false;
            }
        }
        
        // Check if current time is within shift hours
        if ($current_time >= $user['shift_start'] && $current_time <= $user['shift_end']) {
            return true;
        }
        
        return false;
    } catch (Exception $e) {
        error_log("Shift check error: " . $e->getMessage());
        return true; // Allow login on error
    }
}

// Check if EasySalles tables exist
function checkEasySallesTables() {
    try {
        $tables = [
            'easysalles_users',
            'easysalles_products',
            'easysalles_sales',
            'easysalles_categories',
            'easysalles_customers'
        ];
        
        foreach ($tables as $table) {
            if (Database::tableExists($table)) {
                return true; // At least one table exists
            }
        }
        
        return false; // No tables found
    } catch (Exception $e) {
        error_log("Table check error: " . $e->getMessage());
        return false;
    }
}
?>
