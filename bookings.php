<?php
/**
 * Bookings compatibility endpoint.
 * The current schema stores bookings as rows in shipments plus related invoices,
 * payments, receivers, cargo, and tracking records. This endpoint exposes a
 * booking-shaped read/update API without referencing a removed bookings table.
 */

require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, PUT, OPTIONS');
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

function displayBookingStatus($value) {
    $map = [
        'pending_confirmation' => 'Pending Confirmation',
        'approved' => 'Approved',
        'assigned' => 'Assigned',
        'in_transit' => 'In Transit',
        'out_for_delivery' => 'Out for Delivery',
        'delivered' => 'Delivered',
        'cancelled' => 'Cancelled',
        'cancellation_requested' => 'Cancellation Requested'
    ];

    return $map[$value] ?? $value;
}

function normalizeBookingStatus($value) {
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

function getBookings($customerId = null) {
    $pdo = getDBConnection();
    $where = '';
    $params = [];

    if ($customerId) {
        $where = 'WHERE c.customer_id = :customer_id OR c.user_id = :user_id';
        $params['customer_id'] = $customerId;
        $params['user_id'] = $customerId;
    }

    $stmt = $pdo->prepare("
        SELECT
            s.shipment_id AS booking_id,
            s.customer_id,
            s.shipment_id,
            s.booking_date,
            i.total_amount AS total_cost,
            s.payment_status,
            s.shipment_status AS booking_status,
            s.origin_location,
            s.destination_location,
            s.service_type,
            c.full_name,
            c.email_address,
            r.full_name AS receiver_name
        FROM shipments s
        INNER JOIN customers c ON c.customer_id = s.customer_id
        INNER JOIN receivers r ON r.receiver_id = s.receiver_id
        LEFT JOIN invoices i ON i.invoice_id = s.invoice_id
        {$where}
        ORDER BY s.booking_date DESC
    ");
    $stmt->execute($params);

    $rows = $stmt->fetchAll();

    return array_map(function ($row) {
        $row['booking_status_label'] = displayBookingStatus($row['booking_status']);
        return $row;
    }, $rows);
}

function updateBooking($shipmentId, $data) {
    $allowedStatuses = ['pending_confirmation', 'approved', 'assigned', 'in_transit', 'out_for_delivery', 'delivered', 'cancelled', 'cancellation_requested'];
    $updates = [];
    $params = ['shipment_id' => $shipmentId];

    if (isset($data['booking_status'])) {
        $status = normalizeBookingStatus($data['booking_status']);

        if (!in_array($status, $allowedStatuses, true)) {
            throw new Exception('Invalid booking status');
        }

        $updates[] = 'shipment_status = :shipment_status';
        $params['shipment_status'] = $status;
    }

    if (isset($data['payment_status'])) {
        $paymentStatus = strtolower(trim($data['payment_status']));
        $allowedPaymentStatuses = ['unpaid', 'pending_verification', 'partial', 'paid', 'refunded'];

        if (!in_array($paymentStatus, $allowedPaymentStatuses, true)) {
            throw new Exception('Invalid payment status');
        }

        $updates[] = 'payment_status = :payment_status';
        $params['payment_status'] = $paymentStatus;
    }

    if (empty($updates)) {
        throw new Exception('No valid fields to update');
    }

    $pdo = getDBConnection();
    $stmt = $pdo->prepare("
        UPDATE shipments
        SET " . implode(', ', $updates) . ",
            row_version = row_version + 1
        WHERE shipment_id = :shipment_id
    ");
    $stmt->execute($params);

    if ($stmt->rowCount() === 0) {
        throw new Exception('Booking not found or no changes were made');
    }

    return ['success' => true, 'message' => 'Booking updated successfully'];
}

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $customerId = $_GET['customer_id'] ?? null;
        sendJson(['success' => true, 'bookings' => getBookings($customerId)]);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
        $input = json_decode(file_get_contents('php://input'), true);

        if (empty($input['booking_id']) && empty($input['shipment_id'])) {
            sendJson(['success' => false, 'message' => 'booking_id or shipment_id is required'], 400);
        }

        $shipmentId = $input['shipment_id'] ?? $input['booking_id'];
        unset($input['shipment_id'], $input['booking_id']);

        sendJson(updateBooking($shipmentId, is_array($input) ? $input : []));
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        sendJson([
            'success' => false,
            'message' => 'Use POST /backend/api/shipments.php to create a booking in the current schema'
        ], 405);
    }

    sendJson(['success' => false, 'message' => 'Method not allowed'], 405);
} catch (Throwable $e) {
    error_log('Bookings API error: ' . $e->getMessage());
    sendJson(['success' => false, 'message' => $e->getMessage()], 400);
}
?>
