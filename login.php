<?php
/**
 * Login API Endpoint
 * Handles user authentication for the CargoConnect application.
 * Accepts POST requests with email and password, verifies against the database,
 * and returns user information if authentication is successful.
 */

require_once __DIR__ . '/config.php'; // Include the database configuration

header('Content-Type: application/json'); // Set response content type to JSON
header('Access-Control-Allow-Origin: *'); // Allow cross-origin requests (adjust for production)
header('Access-Control-Allow-Methods: POST'); // Allow POST method
header('Access-Control-Allow-Headers: Content-Type'); // Allow Content-Type header

/**
 * Function to authenticate a user
 * @param string $email The user's email address
 * @param string $password The user's password (plain text, will be hashed for comparison)
 * @return array|null User data array if authentication successful, null otherwise
 */
function authenticateUser($email, $password) {
    try {
        $pdo = getDBConnection(); // Get database connection

        // Prepare SQL query to fetch user by email
        $stmt = $pdo->prepare("
            SELECT user_id, full_name, email_address, password_hash, user_role, account_status
            FROM users
            WHERE email_address = LOWER(TRIM(:email))
            LIMIT 1
        ");
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            // Password matches, return user data (exclude password hash)
            return [
                'user_id' => $user['user_id'],
                'full_name' => $user['full_name'],
                'email_address' => $user['email_address'],
                'user_role' => $user['user_role'],
                'account_status' => $user['account_status']
            ];
        }

        return null; // Authentication failed
    } catch (PDOException $e) {
        error_log("Authentication error: " . $e->getMessage());
        return null;
    } finally {
        closeDBConnection($pdo); // Close the database connection
    }
}

// Handle only POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);

    if (isset($input['email']) && isset($input['password'])) {
        $email = trim($input['email']);
        $password = $input['password'];

        $user = authenticateUser($email, $password);

        if ($user) {
            // Authentication successful
            echo json_encode([
                'success' => true,
                'message' => 'Login successful',
                'user' => $user
            ]);
        } else {
            // Authentication failed
            echo json_encode([
                'success' => false,
                'message' => 'Invalid email or password'
            ]);
        }
    } else {
        // Missing required fields
        echo json_encode([
            'success' => false,
            'message' => 'Email and password are required'
        ]);
    }
} else {
    // Method not allowed
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed'
    ]);
}
?>