<?php
/**
 * Tracking API Endpoint
 * Handles shipment tracking operations for the CargoConnect application.
 * Supports GET requests to retrieve tracking history for a specific shipment.
 */

require_once __DIR__ . '/config.php'; // Include the database configuration

header('Content-Type: application/json'); // Set response content type to JSON
header('Access-Control-Allow-Origin: *'); // Allow cross-origin requests (adjust for production)
header('Access-Control-Allow-Methods: GET'); // Allow GET method
header('Access-Control-Allow-Headers: Content-Type'); // Allow Content-Type header

/**
 * Function to get tracking history for a shipment
 * @param string $shipmentId The shipment ID
 * @return array Array of tracking events
 */
function getTracking($shipmentId) {
    try {
        $pdo = getDBConnection(); // Get database connection

        // Call stored procedure to get tracking data
        $stmt = $pdo->prepare("CALL sp_get_shipment_tracking(?)");
        $stmt->execute([$shipmentId]);
        $tracking = $stmt->fetchAll();

        return $tracking;

    } catch (PDOException $e) {
        error_log("Get tracking error: " . $e->getMessage());
        return [];
    } finally {
        closeDBConnection($pdo); // Close the database connection
    }
}

// Handle only GET requests
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Get shipment_id from query parameters
    $shipmentId = $_GET['shipment_id'] ?? null;

    if (!$shipmentId) {
        echo json_encode([
            'success' => false,
            'message' => 'Shipment ID is required'
        ]);
        exit;
    }

    $tracking = getTracking($shipmentId);

    echo json_encode([
        'success' => true,
        'tracking' => $tracking
    ]);

} else {
    // Method not allowed
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed'
    ]);
}
?>