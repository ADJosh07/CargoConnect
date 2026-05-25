<?php
/**
 * Users API Endpoint
 * Handles customer profile retrieval and updates.
 */

require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

/** Outputs a JSON response and stops script execution. */
function sendJson($payload, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($payload);
    exit;
}

/** Loads user + customer profile joined by user_id. */
function getProfile($userId) {
    $pdo = getDBConnection();

    $stmt = $pdo->prepare("
        SELECT
            u.user_id,
            c.customer_id,
            u.full_name,
            u.email_address,
            u.user_role,
            u.account_status,
            u.phone_number,
            u.home_address,
            u.created_at,
            u.updated_at
        FROM users u
        LEFT JOIN customers c ON c.user_id = u.user_id
        WHERE u.user_id = :user_id
        LIMIT 1
    ");
    $stmt->execute(['user_id' => $userId]);

    return $stmt->fetch();
}

/** Updates name, phone, and address on both users and customers tables. */
function updateProfile($data) {
    if (empty($data['user_id'])) {
        return ['success' => false, 'message' => 'user_id is required'];
    }

    $fullName = trim($data['full_name'] ?? '');
    $phoneNumber = trim($data['phone_number'] ?? '');
    $homeAddress = trim($data['home_address'] ?? '');

    if ($fullName === '' || $phoneNumber === '' || $homeAddress === '') {
        return ['success' => false, 'message' => 'Full name, phone number, and address are required'];
    }

    if (!preg_match('/^09\d{9}$/', $phoneNumber)) {
        return ['success' => false, 'message' => 'Phone number must use PH mobile format 09xxxxxxxxx'];
    }

    $pdo = getDBConnection();
    $pdo->beginTransaction();

    try {
        $stmt = $pdo->prepare("
            UPDATE users
            SET full_name = :full_name,
                phone_number = :phone_number,
                home_address = :home_address
            WHERE user_id = :user_id
              AND user_role = 'customer'
        ");
        $stmt->execute([
            'full_name' => $fullName,
            'phone_number' => $phoneNumber,
            'home_address' => $homeAddress,
            'user_id' => $data['user_id']
        ]);

        if ($stmt->rowCount() === 0 && !getProfile($data['user_id'])) {
            throw new Exception('User profile not found');
        }

        $stmt = $pdo->prepare("
            UPDATE customers
            SET full_name = :full_name,
                phone_number = :phone_number,
                home_address = :home_address
            WHERE user_id = :user_id
        ");
        $stmt->execute([
            'full_name' => $fullName,
            'phone_number' => $phoneNumber,
            'home_address' => $homeAddress,
            'user_id' => $data['user_id']
        ]);

        $pdo->commit();

        return [
            'success' => true,
            'message' => 'Profile updated successfully',
            'user' => getProfile($data['user_id'])
        ];
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log('Update profile error: ' . $e->getMessage());
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $userId = $_GET['user_id'] ?? null;

        if (!$userId) {
            sendJson(['success' => false, 'message' => 'user_id is required'], 400);
        }

        $profile = getProfile($userId);

        if (!$profile) {
            sendJson(['success' => false, 'message' => 'User profile not found'], 404);
        }

        sendJson(['success' => true, 'user' => $profile]);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
        $input = json_decode(file_get_contents('php://input'), true);
        $result = updateProfile(is_array($input) ? $input : []);
        sendJson($result, $result['success'] ? 200 : 400);
    }

    sendJson(['success' => false, 'message' => 'Method not allowed'], 405);
} catch (Throwable $e) {
    error_log('Users API error: ' . $e->getMessage());
    sendJson(['success' => false, 'message' => 'Server error while processing user profile'], 500);
}
?>
