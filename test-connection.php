<?php
/**
 * Database Connection Test
 * This script tests if the database connection works properly
 */

header('Content-Type: application/json');

require_once __DIR__ . '/config.php';

try {
    $pdo = getDBConnection();
    
    // Try a simple query
    $stmt = $pdo->query("SELECT COUNT(*) as user_count FROM users");
    $result = $stmt->fetch();
    
    echo json_encode([
        'success' => true,
        'message' => 'Database connection successful',
        'user_count' => $result['user_count'],
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
    closeDBConnection($pdo);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed',
        'error' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}
?>
