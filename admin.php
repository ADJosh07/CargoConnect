<?php
/**
 * Admin API Endpoint
 * Handles administrative operations for the CargoConnect application.
 * Supports POST requests for various admin actions like confirming shipments,
 * marking as delivered, etc. Admin authentication required.
 */

require_once __DIR__ . '/config.php'; // Include the database configuration

header('Content-Type: application/json'); // Set response content type to JSON
header('Access-Control-Allow-Origin: *'); // Allow cross-origin requests (adjust for production)
header('Access-Control-Allow-Methods: POST'); // Allow POST method
header('Access-Control-Allow-Headers: Content-Type, Authorization'); // Allow Content-Type and Authorization headers

/**
 * Function to confirm a shipment
 * @param string $shipmentId The shipment ID to confirm
 * @param string $adminName The admin performing the action
 * @return array Result array with success status
 */
function confirmShipment($shipmentId, $adminName) {
    try {
        $pdo = getDBConnection(); // Get database connection

        // Call stored procedure to confirm shipment
        $stmt = $pdo->prepare("CALL sp_confirm_shipment(?, ?)");
        $stmt->execute([$shipmentId, $adminName]);

        return ['success' => true, 'message' => 'Shipment confirmed successfully'];

    } catch (PDOException $e) {
        error_log("Confirm shipment error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to confirm shipment'];
    } finally {
        closeDBConnection($pdo); // Close the database connection
    }
}

/**
 * Function to mark shipment as out for delivery
 * @param string $shipmentId The shipment ID
 * @param string $adminName The admin performing the action
 * @return array Result array with success status
 */
function markOutForDelivery($shipmentId, $adminName) {
    try {
        $pdo = getDBConnection(); // Get database connection

        // Call stored procedure
        $stmt = $pdo->prepare("CALL sp_mark_out_for_delivery(?, ?)");
        $stmt->execute([$shipmentId, $adminName]);

        return ['success' => true, 'message' => 'Shipment marked as out for delivery'];

    } catch (PDOException $e) {
        error_log("Mark out for delivery error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to mark shipment as out for delivery'];
    } finally {
        closeDBConnection($pdo); // Close the database connection
    }
}

/**
 * Function to mark shipment as delivered
 * @param string $shipmentId The shipment ID
 * @param string $adminName The admin performing the action
 * @return array Result array with success status
 */
function markDelivered($shipmentId, $adminName) {
    try {
        $pdo = getDBConnection(); // Get database connection

        // Call stored procedure
        $stmt = $pdo->prepare("CALL sp_mark_delivered(?, ?)");
        $stmt->execute([$shipmentId, $adminName]);

        return ['success' => true, 'message' => 'Shipment marked as delivered'];

    } catch (PDOException $e) {
        error_log("Mark delivered error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to mark shipment as delivered'];
    } finally {
        closeDBConnection($pdo); // Close the database connection
    }
}

/**
 * Function to request cancellation
 * @param string $shipmentId The shipment ID
 * @param string $requestedBy The person requesting cancellation
 * @param string $reason The cancellation reason
 * @return array Result array with success status
 */
function requestCancellation($shipmentId, $requestedBy, $reason) {
    try {
        $pdo = getDBConnection(); // Get database connection

        // Call stored procedure
        $stmt = $pdo->prepare("CALL sp_request_cancellation(?, ?, ?)");
        $stmt->execute([$shipmentId, $requestedBy, $reason]);

        return ['success' => true, 'message' => 'Cancellation request submitted'];

    } catch (PDOException $e) {
        error_log("Request cancellation error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to submit cancellation request'];
    } finally {
        closeDBConnection($pdo); // Close the database connection
    }
}

// Handle only POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);

    // Validate action
    if (!isset($input['action'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Action is required'
        ]);
        exit;
    }

    $action = $input['action'];
    $adminName = $input['admin_name'] ?? 'System Admin';

    switch ($action) {
        case 'confirm_shipment':
            if (!isset($input['shipment_id'])) {
                echo json_encode(['success' => false, 'message' => 'Shipment ID required']);
                exit;
            }
            $result = confirmShipment($input['shipment_id'], $adminName);
            break;

        case 'mark_out_for_delivery':
            if (!isset($input['shipment_id'])) {
                echo json_encode(['success' => false, 'message' => 'Shipment ID required']);
                exit;
            }
            $result = markOutForDelivery($input['shipment_id'], $adminName);
            break;

        case 'mark_delivered':
            if (!isset($input['shipment_id'])) {
                echo json_encode(['success' => false, 'message' => 'Shipment ID required']);
                exit;
            }
            $result = markDelivered($input['shipment_id'], $adminName);
            break;

        case 'request_cancellation':
            if (!isset($input['shipment_id']) || !isset($input['reason'])) {
                echo json_encode(['success' => false, 'message' => 'Shipment ID and reason required']);
                exit;
            }
            $requestedBy = $input['requested_by'] ?? 'Customer';
            $result = requestCancellation($input['shipment_id'], $requestedBy, $input['reason']);
            break;

        default:
            $result = ['success' => false, 'message' => 'Invalid action'];
    }

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