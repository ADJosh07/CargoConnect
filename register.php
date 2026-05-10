<?php
/**
 * Register API Endpoint
 * Handles user registration for the CargoConnect application.
 * Accepts POST requests with user details, creates a new user and customer record,
 * and returns success or error messages.
 */

require_once __DIR__ . '/config.php'; // Include the database configuration

header('Content-Type: application/json'); // Set response content type to JSON
header('Access-Control-Allow-Origin: *'); // Allow cross-origin requests (adjust for production)
header('Access-Control-Allow-Methods: POST'); // Allow POST method
header('Access-Control-Allow-Headers: Content-Type'); // Allow Content-Type header

/**
 * Function to register a new customer user
 * @param array $userData Array containing user registration data
 * @return array Result array with success status and message
 */
function registerUser($userData) {
    try {
        $pdo = getDBConnection(); // Get database connection

        // Start transaction
        $pdo->beginTransaction();

        // Check if email already exists
        $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email_address = LOWER(TRIM(:email)) LIMIT 1");
        $stmt->execute(['email' => $userData['email']]);
        if ($stmt->fetch()) {
            $pdo->rollBack();
            return ['success' => false, 'message' => 'Email address is already registered'];
        }

        // Hash the password
        $passwordHash = password_hash($userData['password'], PASSWORD_DEFAULT);

        // Insert into users table
        $stmt = $pdo->prepare("
            INSERT INTO users (full_name, email_address, password_hash, phone_number, home_address, user_role)
            VALUES (:full_name, LOWER(TRIM(:email)), :password_hash, :phone, :address, 'customer')
        ");
        $stmt->execute([
            'full_name' => $userData['full_name'],
            'email' => $userData['email'],
            'password_hash' => $passwordHash,
            'phone' => $userData['phone'] ?? null,
            'address' => $userData['address'] ?? null
        ]);

        $userId = $pdo->lastInsertId();

        // Insert into customers table
        $stmt = $pdo->prepare("
            INSERT INTO customers (user_id, full_name, email_address, phone_number, home_address)
            VALUES (:user_id, :full_name, LOWER(TRIM(:email)), :phone, :address)
        ");
        $stmt->execute([
            'user_id' => $userId,
            'full_name' => $userData['full_name'],
            'email' => $userData['email'],
            'phone' => $userData['phone'] ?? null,
            'address' => $userData['address'] ?? null
        ]);

        // Commit transaction
        $pdo->commit();

        return ['success' => true, 'message' => 'Registration successful', 'user_id' => $userId];

    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Registration error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Registration failed. Please try again.'];
    } finally {
        closeDBConnection($pdo); // Close the database connection
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

    // Register the user
    $result = registerUser([
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