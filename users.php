<?php
/**
 * Users API Endpoint
 * Handles user-related operations for the CargoConnect application.
 * Supports GET (retrieve user profile) and PUT (update profile) requests.
 */

require_once __DIR__ . '/config.php'; // Include the database configuration

header('Content-Type: application/json'); // Set response content type to JSON
header('Access-Control-Allow-Origin: *'); // Allow cross-origin requests (adjust for production)
header('Access-Control-Allow-Methods: GET, PUT'); // Allow GET and PUT methods
header('Access-Control-Allow-Headers: Content-Type, Authorization'); // Allow Content-Type and Authorization headers

/**
 * Function to get user profile
 * @param int $userId The user ID
 * @return array|null User profile data or null if not found
 */
function getUserProfile($userId) {
    try {
        $pdo = getDBConnection(); // Get database connection

        // Prepare SQL query to fetch user and customer data
        $stmt = $pdo->prepare("
            SELECT
                u.user_id,
                u.full_name,
                u.email_address,
                u.user_role,
                u.account_status,
                u.phone_number,
                u.home_address,
                u.created_at,
                c.customer_id,
                c.company_name
            FROM users u
            LEFT JOIN customers c ON u.user_id = c.user_id
            WHERE u.user_id = :user_id
            LIMIT 1
        ");
        $stmt->execute(['user_id' => $userId]);
        $user = $stmt->fetch();

        return $user;

    } catch (PDOException $e) {
        error_log("Get user profile error: " . $e->getMessage());
        return null;
    } finally {
        closeDBConnection($pdo); // Close the database connection
    }
}

/**
 * Function to update user profile
 * @param int $userId The user ID
 * @param array $updateData Array of fields to update
 * @return array Result array with success status
 */
function updateUserProfile($userId, $updateData) {
    try {
        $pdo = getDBConnection(); // Get database connection

        // Start transaction
        $pdo->beginTransaction();

        // Update users table
        $userFields = ['full_name', 'phone_number', 'home_address'];
        $userUpdates = [];
        $userParams = ['user_id' => $userId];

        foreach ($userFields as $field) {
            if (isset($updateData[$field])) {
                $userUpdates[] = "$field = :$field";
                $userParams[$field] = $updateData[$field];
            }
        }

        if (!empty($userUpdates)) {
            $stmt = $pdo->prepare("
                UPDATE users
                SET " . implode(', ', $userUpdates) . ", updated_at = NOW()
                WHERE user_id = :user_id
            ");
            $stmt->execute($userParams);
        }

        // Update customers table if customer
        $customerFields = ['company_name'];
        $customerUpdates = [];
        $customerParams = ['user_id' => $userId];

        foreach ($customerFields as $field) {
            if (isset($updateData[$field])) {
                $customerUpdates[] = "$field = :$field";
                $customerParams[$field] = $updateData[$field];
            }
        }

        if (!empty($customerUpdates)) {
            $stmt = $pdo->prepare("
                UPDATE customers
                SET " . implode(', ', $customerUpdates) . ", updated_at = NOW()
                WHERE user_id = :user_id
            ");
            $stmt->execute($customerParams);
        }

        // Commit transaction
        $pdo->commit();

        return ['success' => true, 'message' => 'Profile updated successfully'];

    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Update user profile error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to update profile'];
    } finally {
        closeDBConnection($pdo); // Close the database connection
    }
}

// Handle requests based on method
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Get user_id from query parameters
    $userId = $_GET['user_id'] ?? null;

    if (!$userId) {
        echo json_encode([
            'success' => false,
            'message' => 'User ID is required'
        ]);
        exit;
    }

    $user = getUserProfile($userId);

    if ($user) {
        echo json_encode([
            'success' => true,
            'user' => $user
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'User not found'
        ]);
    }

} elseif ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);

    // Validate user_id     
    if (!isset($input['user_id'])) {
        echo json_encode([
            'success' => false,
            'message' => 'User ID is required'
        ]);
        exit;
    }

    $userId = $input['user_id'];
    unset($input['user_id']); // Remove user_id from update data

    // Update the profile
    $result = updateUserProfile($userId, $input);
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