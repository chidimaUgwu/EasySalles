<?php
require_once 'db.php';

// Generate unique user ID
function generateUserId() {
    $year = date('Y');
    $lastUser = Database::query("SELECT user_id FROM easysalles_users ORDER BY id DESC LIMIT 1")->fetch();
    
    if ($lastUser) {
        $lastNumber = intval(substr($lastUser['user_id'], -3));
        $newNumber = str_pad($lastNumber + 1, 3, '0', STR_PAD_LEFT);
    } else {
        $newNumber = '001';
    }
    
    return "SP-{$year}-{$newNumber}";
}

// Generate transaction ID
function generateTransactionId() {
    $date = date('Ymd');
    $lastSale = Database::query("SELECT transaction_id FROM easysalles_sales WHERE DATE(created_at) = CURDATE() ORDER BY id DESC LIMIT 1")->fetch();
    
    if ($lastSale) {
        $lastNumber = intval(substr($lastSale['transaction_id'], -3));
        $newNumber = str_pad($lastNumber + 1, 3, '0', STR_PAD_LEFT);
    } else {
        $newNumber = '001';
    }
    
    return "TXN-{$date}-{$newNumber}";
}
// Check if user can login based on shift
function canLogin($userId) {
    if (!SHIFT_ENFORCEMENT) return true;
    
    $user = Database::query("SELECT shift_start, shift_end, shift_days FROM easysalles_users WHERE id = ?", [$userId])->fetch();
    
    if (!$user) return false;
    
    $currentTime = date('H:i:s');
    $currentDay = date('N'); // 1=Monday, 7=Sunday
    
    // Check if user has 24/7 access (00:00:00 to 23:59:00)
    if ($user['shift_start'] == '00:00:00' && $user['shift_end'] == '23:59:00') {
        return true;
    }
    
    // Check time
    if ($currentTime < $user['shift_start'] || $currentTime > $user['shift_end']) {
        return false;
    }
    
    // Check days - only if shift_days is not null
    if (!empty($user['shift_days'])) {
        $shiftDays = explode(',', $user['shift_days']);
        if (!in_array($currentDay, $shiftDays)) {
            return false;
        }
    }
    
    return true;
}
// // Check if user can login based on shift
// function canLogin($userId) {
//     if (!SHIFT_ENFORCEMENT) return true;
    
//     $user = Database::query("SELECT shift_start, shift_end, shift_days FROM easysalles_users WHERE id = ?", [$userId])->fetch();
    
//     if (!$user) return false;
    
//     $currentTime = date('H:i:s');
//     $currentDay = date('N'); // 1=Monday, 7=Sunday
    
//     // Check time
//     if ($currentTime < $user['shift_start'] || $currentTime > $user['shift_end']) {
//         return false;
//     }
    
//     // Check days
//     $shiftDays = explode(',', $user['shift_days']);
//     if (!in_array($currentDay, $shiftDays)) {
//         return false;
//     }
    
//     return true;
// }

// Format currency
function formatCurrency($amount) {
    return '$' . number_format($amount, 2);
}

// Get user initials for avatar
function getInitials($name) {
    $words = explode(' ', $name);
    $initials = '';
    foreach ($words as $word) {
        $initials .= strtoupper(substr($word, 0, 1));
    }
    return substr($initials, 0, 2);
}

// Get random avatar color
function getAvatarColor($string) {
    $colors = [
        'bg-purple-100 text-purple-800 border-purple-300',
        'bg-pink-100 text-pink-800 border-pink-300',
        'bg-cyan-100 text-cyan-800 border-cyan-300',
        'bg-blue-100 text-blue-800 border-blue-300',
        'bg-green-100 text-green-800 border-green-300',
        'bg-yellow-100 text-yellow-800 border-yellow-300'
    ];
    
    $index = crc32($string) % count($colors);
    return $colors[$index];
}
?>
