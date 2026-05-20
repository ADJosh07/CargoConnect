<?php
/**
 * Shipments API Endpoint
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

function normalizeServiceType($value) {
    $value = strtolower(trim((string) $value));
    return in_array($value, ['door-to-door', 'door_to_door'], true) ? 'door_to_door' : 'hub_to_hub';
}

function normalizeHandlingType($value) {
    $value = strtolower(trim((string) $value));
    $allowed = ['standard', 'fragile', 'perishable', 'hazardous'];
    return in_array($value, $allowed, true) ? $value : 'standard';
}

function normalizeShipmentStatus($value) {
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

    $value = strtolower(trim((string) $value));
    return str_replace('-', '_', $value);
}

function displayStatus($value) {
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

function displayService($value) {
    return $value === 'door_to_door' ? 'Door-to-Door' : 'Hub-to-Hub';
}

function resolveCustomerId(PDO $pdo, $id) {
    $stmt = $pdo->prepare("
        SELECT customer_id
        FROM customers
        WHERE customer_id = :customer_id OR user_id = :user_id
        ORDER BY CASE WHEN user_id = :sort_user_id THEN 0 ELSE 1 END
        LIMIT 1
    ");
    $stmt->execute([
        'customer_id' => $id,
        'user_id' => $id,
        'sort_user_id' => $id
    ]);
    $customer = $stmt->fetch();

    if (!$customer) {
        throw new Exception('Customer profile not found for the logged-in user');
    }

    return (int) $customer['customer_id'];
}

function generateShipmentId(PDO $pdo) {
    for ($i = 0; $i < 25; $i++) {
        $id = 'CC' . random_int(1000, 9999);
        $stmt = $pdo->prepare("SELECT 1 FROM shipments WHERE shipment_id = :shipment_id");
        $stmt->execute(['shipment_id' => $id]);

        if (!$stmt->fetch()) {
            return $id;
        }
    }

    throw new Exception('Could not generate a unique shipment ID');
}

function calculateTotal($weight, $volume, $serviceType, $handlingType) {
    $total = 150 + ($weight * 25) + ($volume * 300);

    if ($serviceType === 'door_to_door') {
        $total += 200;
    }

    if ($handlingType === 'fragile') {
        $total += 100;
    } elseif ($handlingType === 'perishable') {
        $total += 150;
    } elseif ($handlingType === 'hazardous') {
        $total += 250;
    }

    return round($total, 2);
}

function getShipments($customerId = null, $shipmentId = null) {
    $pdo = getDBConnection();

    $params = [];
    $whereParts = [];

    if ($customerId) {
        $resolvedCustomerId = resolveCustomerId($pdo, $customerId);
        $whereParts[] = 's.customer_id = :customer_id';
        $params['customer_id'] = $resolvedCustomerId;
    }

    if ($shipmentId) {
        $whereParts[] = 's.shipment_id = :shipment_id';
        $params['shipment_id'] = $shipmentId;
    }

    $where = $whereParts ? 'WHERE ' . implode(' AND ', $whereParts) : '';

    $stmt = $pdo->prepare("
        SELECT
            s.shipment_id,
            s.customer_id,
            s.receiver_id,
            s.cargo_id,
            s.fleet_id,
            s.origin_location,
            s.destination_location,
            s.service_type,
            s.shipment_status,
            s.payment_status,
            s.special_instructions,
            s.booking_date,
            s.confirmed_at,
            s.assigned_at,
            s.in_transit_at,
            s.out_for_delivery_at,
            s.actual_delivery_date,
            c.full_name AS customer_name,
            c.email_address AS customer_email,
            c.phone_number AS customer_phone,
            r.full_name AS receiver_name,
            r.email_address AS receiver_email,
            r.phone_number AS receiver_phone,
            r.delivery_address AS receiver_address,
            ca.cargo_description,
            ca.weight_in_kg,
            ca.volume_in_cubic_meters,
            ca.handling_type,
            i.invoice_number,
            i.total_amount,
            i.amount_paid,
            p.payment_method,
            p.transaction_reference,
            p.paid_at
        FROM shipments s
        INNER JOIN customers c ON c.customer_id = s.customer_id
        INNER JOIN receivers r ON r.receiver_id = s.receiver_id
        INNER JOIN cargo ca ON ca.cargo_id = s.cargo_id
        LEFT JOIN invoices i ON i.invoice_id = s.invoice_id
        LEFT JOIN payments p ON p.invoice_id = i.invoice_id
        {$where}
        ORDER BY s.booking_date DESC
    ");
    $stmt->execute($params);

    $rows = $stmt->fetchAll();

    return array_map(function ($row) {
        $row['status'] = displayStatus($row['shipment_status']);
        $row['service'] = displayService($row['service_type']);
        return $row;
    }, $rows);
}

function createShipment($data) {
    $required = ['customer_id', 'receiver_name', 'receiver_phone', 'origin', 'destination', 'weight', 'volume', 'payment_method'];

    foreach ($required as $field) {
        if (!isset($data[$field]) || trim((string) $data[$field]) === '') {
            throw new Exception("Missing required field: {$field}");
        }
    }

    $weight = (float) $data['weight'];
    $volume = (float) $data['volume'];

    if ($weight <= 0 || $volume <= 0) {
        throw new Exception('Weight and volume must be greater than zero');
    }

    $pdo = getDBConnection();
    $pdo->beginTransaction();

    try {
        $customerId = resolveCustomerId($pdo, $data['customer_id']);
        $serviceType = normalizeServiceType($data['service_type'] ?? 'hub_to_hub');
        $handlingType = normalizeHandlingType($data['handling_type'] ?? 'standard');
        $shipmentId = generateShipmentId($pdo);
        $totalAmount = calculateTotal($weight, $volume, $serviceType, $handlingType);

        $stmt = $pdo->prepare("
            INSERT INTO receivers (full_name, email_address, phone_number, delivery_address)
            VALUES (:full_name, LOWER(TRIM(:email_address)), :phone_number, :delivery_address)
        ");
        $stmt->execute([
            'full_name' => trim($data['receiver_name']),
            'email_address' => trim($data['receiver_email'] ?? ''),
            'phone_number' => trim($data['receiver_phone']),
            'delivery_address' => trim($data['receiver_address'] ?? '')
        ]);
        $receiverId = $pdo->lastInsertId();

        $stmt = $pdo->prepare("
            INSERT INTO cargo (cargo_description, weight_in_kg, volume_in_cubic_meters, handling_type, declared_value, quantity)
            VALUES (:description, :weight, :volume, :handling_type, 0, 1)
        ");
        $stmt->execute([
            'description' => ucfirst($handlingType) . ' cargo',
            'weight' => $weight,
            'volume' => $volume,
            'handling_type' => $handlingType
        ]);
        $cargoId = $pdo->lastInsertId();

        $stmt = $pdo->prepare("
            INSERT INTO shipments (
                shipment_id, customer_id, receiver_id, cargo_id,
                origin_location, destination_location, service_type,
                shipment_status, payment_status, special_instructions
            )
            VALUES (
                :shipment_id, :customer_id, :receiver_id, :cargo_id,
                :origin, :destination, :service_type,
                'pending_confirmation', 'paid', :special_instructions
            )
        ");
        $stmt->execute([
            'shipment_id' => $shipmentId,
            'customer_id' => $customerId,
            'receiver_id' => $receiverId,
            'cargo_id' => $cargoId,
            'origin' => trim($data['origin']),
            'destination' => trim($data['destination']),
            'service_type' => $serviceType,
            'special_instructions' => trim($data['special_instructions'] ?? '')
        ]);

        $invoiceNumber = 'INV-' . date('Ymd') . '-' . random_int(10000, 99999);

        $stmt = $pdo->prepare("
            INSERT INTO invoices (shipment_id, customer_id, invoice_number, total_amount, amount_paid, due_date, invoice_status, paid_at)
            VALUES (:shipment_id, :customer_id, :invoice_number, :total_amount, :amount_paid, DATE_ADD(CURDATE(), INTERVAL 7 DAY), 'paid', NOW())
        ");
        $stmt->execute([
            'shipment_id' => $shipmentId,
            'customer_id' => $customerId,
            'invoice_number' => $invoiceNumber,
            'total_amount' => $totalAmount,
            'amount_paid' => $totalAmount
        ]);
        $invoiceId = $pdo->lastInsertId();

        $stmt = $pdo->prepare("UPDATE shipments SET invoice_id = :invoice_id WHERE shipment_id = :shipment_id");
        $stmt->execute(['invoice_id' => $invoiceId, 'shipment_id' => $shipmentId]);

        $stmt = $pdo->prepare("SELECT user_id FROM customers WHERE customer_id = :customer_id LIMIT 1");
        $stmt->execute(['customer_id' => $customerId]);
        $userId = $stmt->fetchColumn();

        if (!$userId) {
            throw new Exception('Customer account is not linked to a user');
        }

        $stmt = $pdo->prepare("
            INSERT INTO payments (shipment_id, invoice_id, user_id, payment_amount, payment_method, payment_status, transaction_reference)
            VALUES (:shipment_id, :invoice_id, :user_id, :payment_amount, :payment_method, 'completed', :transaction_reference)
        ");
        $stmt->execute([
            'shipment_id' => $shipmentId,
            'invoice_id' => $invoiceId,
            'user_id' => $userId,
            'payment_amount' => $totalAmount,
            'payment_method' => trim($data['payment_method']),
            'transaction_reference' => trim($data['transaction_ref'] ?? '')
        ]);

        $stmt = $pdo->prepare("
            INSERT INTO tracking (shipment_id, current_status, current_location, event_notes)
            VALUES (:shipment_id, 'pending_confirmation', :current_location, 'Shipment booked and awaiting admin confirmation.')
        ");
        $stmt->execute(['shipment_id' => $shipmentId, 'current_location' => trim($data['origin'])]);

        $pdo->commit();

        return [
            'success' => true,
            'message' => 'Shipment created successfully',
            'shipment_id' => $shipmentId,
            'total_amount' => $totalAmount,
            'invoice_number' => $invoiceNumber,
            'status' => 'Pending Confirmation'
        ];
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function updateShipment($shipmentId, $data) {
    $status = $data['status'] ?? $data['shipment_status'] ?? null;

    if (!$status) {
        throw new Exception('status is required');
    }

    $status = normalizeShipmentStatus($status);
    $allowed = ['pending_confirmation', 'approved', 'assigned', 'in_transit', 'out_for_delivery', 'delivered', 'cancelled', 'cancellation_requested'];

    if (!in_array($status, $allowed, true)) {
        throw new Exception('Invalid shipment status');
    }

    $pdo = getDBConnection();
    $pdo->beginTransaction();

    try {
        $stmt = $pdo->prepare("
            UPDATE shipments
            SET shipment_status = :status,
                row_version = row_version + 1
            WHERE shipment_id = :shipment_id
        ");
        $stmt->execute(['status' => $status, 'shipment_id' => $shipmentId]);

        if ($stmt->rowCount() === 0) {
            throw new Exception('Shipment not found');
        }

        $location = trim($data['current_location'] ?? 'system');
        $notes = trim($data['event_notes'] ?? ('Shipment status changed to ' . displayStatus($status)));

        $stmt = $pdo->prepare("
            INSERT INTO tracking (shipment_id, current_status, current_location, event_notes)
            VALUES (:shipment_id, :status, :location, :notes)
        ");
        $stmt->execute([
            'shipment_id' => $shipmentId,
            'status' => $status,
            'location' => $location,
            'notes' => $notes
        ]);

        $pdo->commit();

        return ['success' => true, 'message' => 'Shipment updated successfully'];
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $customerId = $_GET['customer_id'] ?? null;
        $shipmentId = $_GET['shipment_id'] ?? null;
        $shipments = getShipments($customerId, $shipmentId);

        if ($shipmentId) {
            sendJson([
                'success' => true,
                'shipment' => $shipments[0] ?? null,
                'shipments' => $shipments
            ]);
        }

        sendJson(['success' => true, 'shipments' => $shipments]);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        sendJson(createShipment(is_array($input) ? $input : []));
    }

    if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
        $input = json_decode(file_get_contents('php://input'), true);

        if (!isset($input['shipment_id'])) {
            sendJson(['success' => false, 'message' => 'shipment_id is required'], 400);
        }

        $shipmentId = $input['shipment_id'];
        unset($input['shipment_id']);

        sendJson(updateShipment($shipmentId, $input));
    }

    sendJson(['success' => false, 'message' => 'Method not allowed'], 405);
} catch (Throwable $e) {
    error_log('Shipments API error: ' . $e->getMessage());
    sendJson(['success' => false, 'message' => $e->getMessage()], 400);
}
?>
