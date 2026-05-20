<?php
/**
 * Standardized JSON response helper for API endpoints
 */
function send_json($success, $message = '', $data = null, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');

    $payload = ['success' => (bool)$success, 'message' => (string)$message];
    if (!is_null($data)) $payload['data'] = $data;

    echo json_encode($payload);
    exit();
}
