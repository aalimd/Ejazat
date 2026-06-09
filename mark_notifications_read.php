<?php
require_once 'includes/config.php';

header('Content-Type: application/json');

if (isLoggedIn()) {
    try {
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        echo json_encode(['status' => 'success']);
    } catch (PDOException $e) {
        error_log('Mark notifications read error: ' . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Database error']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Not logged in']);
}
