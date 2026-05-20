<?php
/**
 * Tracking API Endpoint
 * Matches backend/database/schema.sql.
 */

require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

function sendJson($payload, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($payload);
    exit;
}

function normalizeTrackingStatus($value) {
    $map = [
        'Pending Confirmation' => 'pending_confirmation',
        'Approved' => 'approved',
        'Assigned' => 'assigned',
        'In Transit' => 'in_transit',
        'Out for Delivery' => 'out_for_delivery',
        'Delivered' => 'delivered',
        'Cancelled' => 'cancelled',
        'Cancellation Requested' => 'cancellation_requested'
    ];

    if (isset($map[$value])) {
        return $map[$value];
    }

    return str_replace('-', '_', strtolower(trim((string) $value)));
}

function getTracking($shipmentId) {
    $pdo = getDBConnection();

    $stmt = $pdo->prepare("
        SELECT
            t.tracking_id,
            t.shipment_id,
            t.current_status,
            t.current_status AS status,
            t.current_location,
            t.event_notes,
            t.event_timestamp,
            t.event_timestamp AS updated_at,
            s.origin_location,
            s.destination_location,
            s.service_type,
            s.shipment_status,
            c.full_name,
            c.email_address
        FROM tracking t
        INNER JOIN shipments s ON t.shipment_id = s.shipment_id
        INNER JOIN customers c ON s.customer_id = c.customer_id
        WHERE t.shipment_id = :shipment_id
        ORDER BY t.event_timestamp DESC
    ");
    $stmt->execute(['shipment_id' => $shipmentId]);

    return $stmt->fetchAll();
}

function getCustomerTracking($customerId) {
    $pdo = getDBConnection();

    $stmt = $pdo->prepare("
        SELECT
            t.tracking_id,
            t.shipment_id,
            t.current_status,
            t.current_status AS status,
            t.current_location,
            t.event_notes,
            t.event_timestamp,
            t.event_timestamp AS updated_at,
            s.origin_location,
            s.destination_location,
            s.service_type,
            s.booking_date
        FROM tracking t
        INNER JOIN shipments s ON t.shipment_id = s.shipment_id
        INNER JOIN customers c ON s.customer_id = c.customer_id
        WHERE c.customer_id = :customer_id OR c.user_id = :user_id
        ORDER BY t.event_timestamp DESC
    ");
    $stmt->execute(['customer_id' => $customerId, 'user_id' => $customerId]);

    return $stmt->fetchAll();
}

function addTrackingEvent($shipmentId, $data) {
    if (empty($data['current_location']) || empty($data['status'])) {
        throw new Exception('current_location and status are required');
    }

    $status = normalizeTrackingStatus($data['status']);
    $pdo = getDBConnection();
    $pdo->beginTransaction();

    try {
        $stmt = $pdo->prepare("SELECT shipment_id FROM shipments WHERE shipment_id = :shipment_id");
        $stmt->execute(['shipment_id' => $shipmentId]);

        if (!$stmt->fetch()) {
            throw new Exception('Shipment not found');
        }

        $stmt = $pdo->prepare("
            INSERT INTO tracking (shipment_id, current_status, current_location, event_notes)
            VALUES (:shipment_id, :status, :location, :notes)
        ");
        $stmt->execute([
            'shipment_id' => $shipmentId,
            'status' => $status,
            'location' => trim($data['current_location']),
            'notes' => trim($data['event_notes'] ?? '')
        ]);

        $stmt = $pdo->prepare("
            UPDATE shipments
            SET shipment_status = :status,
                row_version = row_version + 1
            WHERE shipment_id = :shipment_id
        ");
        $stmt->execute(['status' => $status, 'shipment_id' => $shipmentId]);

        $pdo->commit();

        return ['success' => true, 'message' => 'Tracking updated successfully'];
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $shipmentId = $_GET['shipment_id'] ?? null;
        $customerId = $_GET['customer_id'] ?? null;

        if ($shipmentId) {
            sendJson(['success' => true, 'tracking' => getTracking($shipmentId)]);
        }

        if ($customerId) {
            sendJson(['success' => true, 'tracking' => getCustomerTracking($customerId)]);
        }

        sendJson(['success' => false, 'message' => 'shipment_id or customer_id is required'], 400);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' || $_SERVER['REQUEST_METHOD'] === 'PUT') {
        $input = json_decode(file_get_contents('php://input'), true);

        if (empty($input['shipment_id'])) {
            sendJson(['success' => false, 'message' => 'shipment_id is required'], 400);
        }

        $shipmentId = $input['shipment_id'];
        unset($input['shipment_id']);

        sendJson(addTrackingEvent($shipmentId, is_array($input) ? $input : []));
    }

    sendJson(['success' => false, 'message' => 'Method not allowed'], 405);
} catch (Throwable $e) {
    error_log('Tracking API error: ' . $e->getMessage());
    sendJson(['success' => false, 'message' => $e->getMessage()], 400);
}
?>
