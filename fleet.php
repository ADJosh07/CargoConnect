<?php
/**
 * Fleet API Endpoint
 * Handles fleet management operations for the CargoConnect application.
 * Supports GET (retrieve fleet) and POST (update fleet status) requests.
 * Admin access required for modifications.
 */

require_once __DIR__ . '/config.php'; // Include the database configuration

header('Content-Type: application/json'); // Set response content type to JSON
header('Access-Control-Allow-Origin: *'); // Allow cross-origin requests (adjust for production)
header('Access-Control-Allow-Methods: GET, POST'); // Allow GET and POST methods
header('Access-Control-Allow-Headers: Content-Type, Authorization'); // Allow Content-Type and Authorization headers

/**
 * Function to get all fleet vehicles
 * @return array Array of fleet data
 */
function getFleet() {
    try {
        $pdo = getDBConnection(); // Get database connection

        // Prepare SQL query to fetch all fleet vehicles
        $stmt = $pdo->query("
            SELECT
                fleet_id,
                vehicle_type,
                weight_capacity_kg,
                volume_capacity_cubic,
                current_hub_location,
                next_destination,
                operational_status,
                last_service_date,
                last_updated_at
            FROM fleet
            ORDER BY fleet_id
        ");
        $fleet = $stmt->fetchAll();

        return $fleet;

    } catch (PDOException $e) {
        error_log("Get fleet error: " . $e->getMessage());
        return [];
    } finally {
        closeDBConnection($pdo); // Close the database connection
    }
}

/**
 * Function to update fleet vehicle status
 * @param string $fleetId The fleet ID
 * @param string $action The action to perform (assign, dispatch, etc.)
 * @param string $adminName The admin performing the action
 * @param array $additionalData Additional data for the action
 * @return array Result array with success status
 */
function updateFleetStatus($fleetId, $action, $adminName, $additionalData = []) {
    try {
        $pdo = getDBConnection(); // Get database connection

        switch ($action) {
            case 'assign':
                // Assign fleet to shipment
                $stmt = $pdo->prepare("CALL sp_assign_fleet(?, ?, ?)");
                $stmt->execute([$additionalData['shipment_id'], $fleetId, $adminName]);
                break;

            case 'dispatch':
                // Dispatch fleet
                $stmt = $pdo->prepare("CALL sp_dispatch_fleet(?, ?)");
                $stmt->execute([$fleetId, $adminName]);
                break;

            case 'maintenance':
                // Set to maintenance
                $stmt = $pdo->prepare("
                    UPDATE fleet
                    SET operational_status = 'maintenance', row_version = row_version + 1
                    WHERE fleet_id = ?
                ");
                $stmt->execute([$fleetId]);
                break;

            case 'available':
                // Set to available
                $stmt = $pdo->prepare("
                    UPDATE fleet
                    SET operational_status = 'available', row_version = row_version + 1
                    WHERE fleet_id = ?
                ");
                $stmt->execute([$fleetId]);
                break;

            default:
                return ['success' => false, 'message' => 'Invalid action'];
        }

        return ['success' => true, 'message' => 'Fleet status updated successfully'];

    } catch (PDOException $e) {
        error_log("Update fleet status error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to update fleet status'];
    } finally {
        closeDBConnection($pdo); // Close the database connection
    }
}

// Handle requests based on method
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $fleet = getFleet();

    echo json_encode([
        'success' => true,
        'fleet' => $fleet
    ]);

} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);

    // Validate required fields
    if (!isset($input['fleet_id']) || !isset($input['action'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Fleet ID and action are required'
        ]);
        exit;
    }

    $adminName = $input['admin_name'] ?? 'System Admin'; // Default admin name
    $additionalData = $input['additional_data'] ?? [];

    // Update fleet status
    $result = updateFleetStatus($input['fleet_id'], $input['action'], $adminName, $additionalData);
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