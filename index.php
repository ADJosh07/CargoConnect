<?php
/**
 * Backend API Router
 * This file redirects requests appropriately or returns API error
 */

// Set content type to JSON for API responses
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

// If no specific API endpoint is requested, return an error
http_response_code(400);
echo json_encode([
    'success' => false,
    'message' => 'Invalid API endpoint. Please use /backend/api/login.php or /backend/api/register.php'
]);
?>
