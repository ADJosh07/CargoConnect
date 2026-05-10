<?php
/**
 * Shipments API Endpoint
 * Handles shipment-related operations for the CargoConnect application.
 * Supports GET (retrieve shipments) and POST (create new shipment) requests.
 */

require_once __DIR__ . '/config.php'; // Include the database configuration

header('Content-Type: application/json'); // Set response content type to JSON
header('Access-Control-Allow-Origin: *'); // Allow cross-origin requests (adjust for production)
header('Access-Control-Allow-Methods: GET, POST'); // Allow GET and POST methods
header('Access-Control-Allow-Headers: Content-Type, Authorization'); // Allow Content-Type and Authorization headers

/**
 * Function to get shipments for a customer
 * @param int $customerId The customer ID
 * @return array Array of shipment data
 */
function getShipments($customerId) {
    try {
        $pdo = getDBConnection(); // Get database connection

        // Prepare SQL query to fetch shipments for the customer
        $stmt = $pdo->prepare("
            SELECT
                s.shipment_id,
                s.origin_location,
                s.destination_location,
                s.service_type,
                s.shipment_status,
                s.payment_status,
                s.booking_date,
                s.actual_delivery_date,
                c.cargo_description,
                c.weight_in_kg,
                c.volume_in_cubic_meters,
                c.handling_type,
                r.full_name as receiver_name,
                r.delivery_address,
                i.total_amount,
                i.invoice_number
            FROM shipments s
            JOIN cargo c ON s.cargo_id = c.cargo_id
            JOIN receivers r ON s.receiver_id = r.receiver_id
            LEFT JOIN invoices i ON s.invoice_id = i.invoice_id
            WHERE s.customer_id = :customer_id
            ORDER BY s.booking_date DESC
        ");
        $stmt->execute(['customer_id' => $customerId]);
        $shipments = $stmt->fetchAll();

        return $shipments;

    } catch (PDOException $e) {
        error_log("Get shipments error: " . $e->getMessage());
        return [];
    } finally {
        closeDBConnection($pdo); // Close the database connection
    }
}

/**
 * Function to create a new shipment using stored procedure
 * @param array $shipmentData Array containing shipment details
 * @return array Result array with success status and shipment info
 */
function createShipment($shipmentData) {
    try {
        $pdo = getDBConnection(); // Get database connection

        // Prepare call to stored procedure
        $stmt = $pdo->prepare("CALL sp_create_shipment(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $shipmentData['customer_id'],
            $shipmentData['receiver_name'],
            $shipmentData['receiver_email'],
            $shipmentData['receiver_phone'],
            $shipmentData['receiver_address'],
            $shipmentData['origin'],
            $shipmentData['destination'],
            $shipmentData['service_type'],
            $shipmentData['handling_type'],
            $shipmentData['special_instructions'],
            $shipmentData['weight'],
            $shipmentData['volume'],
            $shipmentData['payment_method'],
            $shipmentData['transaction_ref']
        ]);

        $result = $stmt->fetch();

        return [
            'success' => true,
            'message' => 'Shipment created successfully',
            'shipment_id' => $result['new_shipment_id'],
            'total_amount' => $result['total_amount'],
            'invoice_number' => $result['invoice_number']
        ];

    } catch (PDOException $e) {
        error_log("Create shipment error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to create shipment. Please try again.'];
    } finally {
        closeDBConnection($pdo); // Close the database connection
    }
}

/**
 * Function to get all shipments (for admin)
 * @return array Array of all shipment data
 */
function getAllShipments() {
    try {
        $pdo = getDBConnection(); // Get database connection

        // Prepare SQL query to fetch all shipments
        $stmt = $pdo->query("
            SELECT
                s.shipment_id,
                s.customer_id,
                s.origin_location,
                s.destination_location,
                s.service_type,
                s.shipment_status,
                s.payment_status,
                s.booking_date,
                c.full_name as customer_name,
                r.full_name as receiver_name,
                i.total_amount
            FROM shipments s
            JOIN customers c ON s.customer_id = c.customer_id
            JOIN receivers r ON s.receiver_id = r.receiver_id
            LEFT JOIN invoices i ON s.invoice_id = i.invoice_id
            ORDER BY s.booking_date DESC
        ");
        $shipments = $stmt->fetchAll();

        return $shipments;

    } catch (PDOException $e) {
        error_log("Get all shipments error: " . $e->getMessage());
        return [];
    } finally {
        closeDBConnection($pdo); // Close the database connection
    }
}

// Handle requests based on method
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Check if customer_id is provided (for customer view) or admin token
    $customerId = $_GET['customer_id'] ?? null;
    $isAdmin = isset($_GET['admin']) && $_GET['admin'] === 'true'; // Simple admin check, improve with JWT in production

    if ($isAdmin) {
        $shipments = getAllShipments();
    } elseif ($customerId) {
        $shipments = getShipments($customerId);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Customer ID required or admin access needed'
        ]);
        exit;
    }

    echo json_encode([
        'success' => true,
        'shipments' => $shipments
    ]);

} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);

    // Validate required fields
    $requiredFields = [
        'customer_id', 'receiver_name', 'receiver_email', 'receiver_phone', 'receiver_address',
        'origin', 'destination', 'service_type', 'handling_type', 'weight', 'volume',
        'payment_method', 'transaction_ref'
    ];
    $missingFields = [];
    foreach ($requiredFields as $field) {
        if (!isset($input[$field])) {
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

    // Create the shipment
    $result = createShipment($input);
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