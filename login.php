<?php
/**
 * Login API Endpoint
 * Handles user authentication for the CargoConnect application.
 * Accepts POST requests with email and password, verifies against the database,
 * and returns user information if authentication is successful.
 */

require_once __DIR__ . '/../config.php'; // Include the database configuration
require_once __DIR__ . '/response.php'; // Standardized response helper

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
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    // Preflight
    send_json(true, 'OK', null, 200);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json(false, 'Method not allowed', null, 405);
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    send_json(false, 'Invalid JSON payload', null, 400);
}

if (empty($input['email']) || empty($input['password'])) {
    send_json(false, 'Email and password are required', null, 400);
}

$email = trim($input['email']);
$password = $input['password'];

$user = authenticateUser($email, $password);
if ($user) {
    send_json(true, 'Login successful', ['user' => $user], 200);
} else {
    send_json(false, 'Invalid email or password', null, 401);
}
?>
