<?php
/**
 * Admin Registration API Endpoint
 * Handles admin account creation for the CargoConnect application.
 * This endpoint is restricted and should only be accessible by existing admins.
 * Accepts POST requests with admin details and creates a new admin user.
 */

require_once __DIR__ . '/config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

/**
 * Function to register a new admin user
 * @param array $adminData Array containing admin registration data
 * @return array Result array with success status and message
 */
function registerAdmin($adminData) {
    try {
        $pdo = getDBConnection();

        // Start transaction
        $pdo->beginTransaction();

        // Check if email already exists
        $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email_address = LOWER(TRIM(:email)) LIMIT 1");
        $stmt->execute(['email' => $adminData['email']]);
        if ($stmt->fetch()) {
            $pdo->rollBack();
            return ['success' => false, 'message' => 'Email address is already registered'];
        }

        // Hash the password
        $passwordHash = password_hash($adminData['password'], PASSWORD_DEFAULT);

        // Insert into users table with 'admin' role
        $stmt = $pdo->prepare("
            INSERT INTO users (full_name, email_address, password_hash, phone_number, home_address, user_role, account_status)
            VALUES (:full_name, LOWER(TRIM(:email)), :password_hash, :phone, :address, 'admin', 'active')
        ");
        $stmt->execute([
            'full_name' => $adminData['full_name'],
            'email' => $adminData['email'],
            'password_hash' => $passwordHash,
            'phone' => $adminData['phone'] ?? null,
            'address' => $adminData['address'] ?? null
        ]);

        $userId = $pdo->lastInsertId();

        // Commit transaction
        $pdo->commit();

        return ['success' => true, 'message' => 'Admin account created successfully', 'user_id' => $userId];

    } catch (PDOException $e) {
        if ($pdo) $pdo->rollBack();
        error_log("Admin registration error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Admin registration failed. Please try again.'];
    } finally {
        closeDBConnection($pdo);
    }
}

// Handle only POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);

    // Validate required fields
    $requiredFields = ['full_name', 'email', 'password'];
    $missingFields = [];
    foreach ($requiredFields as $field) {
        if (!isset($input[$field]) || empty(trim($input[$field]))) {
            $missingFields[] = $field;
        }
    }

    if (!empty($missingFields)) {
        echo json_encode([
            'success' => false,
            'message' => 'Missing required fields: ' . implode(', ', $missingFields)
        ]);
        exit;
    }

    // Register the admin
    $result = registerAdmin([
        'full_name' => trim($input['full_name']),
        'email' => trim($input['email']),
        'password' => $input['password'],
        'phone' => isset($input['phone']) ? trim($input['phone']) : null,
        'address' => isset($input['address']) ? trim($input['address']) : null
    ]);

    echo json_encode($result);

} else {
    // Method not allowed
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed'
    ]);
}
?>
