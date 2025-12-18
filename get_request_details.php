<?php
// admin/shifts/get_request_details.php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_admin();

header('Content-Type: application/json');

if (!isset($_GET['id'])) {
    echo json_encode(['error' => 'No request ID provided']);
    exit();
}

$request_id = (int)$_GET['id'];

$stmt = $pdo->prepare("SELECT r.*, 
                      u.full_name as requester_name, 
                      u.username as requester_username,
                      s.shift_name,
                      a.full_name as approver_name
                      FROM EASYSALLES_SHIFT_REQUESTS r
                      LEFT JOIN EASYSALLES_USERS u ON r.user_id = u.user_id
                      LEFT JOIN EASYSALLES_SHIFTS s ON r.shift_id = s.shift_id
                      LEFT JOIN EASYSALLES_USERS a ON r.approved_by = a.user_id
                      WHERE r.request_id = ?");
$stmt->execute([$request_id]);
$request = $stmt->fetch(PDO::FETCH_ASSOC);

if ($request) {
    echo json_encode($request);
} else {
    echo json_encode(['error' => 'Request not found']);
}