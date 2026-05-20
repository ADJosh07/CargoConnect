<?php
/**
 * Admin workflow API.
 * Uses the schema's shipment/fleet/cancellation/audit tables as permanent state.
 */

require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

/**
 * Build an absolute waybill URL from the current host.
 * Used for emails.
 */
function buildWaybillUrl($shipmentId) {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host . '/frontend/waybill.html?id=' . rawurlencode((string)$shipmentId);
}

/**
 * Best-effort email sender.
 * If the host cannot send mail, we fail gracefully (do not break shipment updates).
 */
function sendShipmentEmailBestEffort(array $shipment, string $action, string $waybillUrl) : array {
    $to = trim((string)($shipment['customer_email'] ?? ''));
    if ($to === '') {
        return ['email_sent' => false, 'reason' => 'Missing customer_email'];
    }

    $customerName = trim((string)($shipment['customer_name'] ?? 'Customer'));
    $sender = trim((string)($shipment['receiver_email'] ?? ''));

    $shipmentId = (string)($shipment['shipment_id'] ?? $shipment['shipment_id'] ?? '');
    if ($shipmentId === '') {
        // admin.php getShipment() selects by shipment_id, but normalize just in case
        $shipmentId = (string)($shipment['shipment_id'] ?? '');
    }

    $route = trim((string)($shipment['origin_location'] ?? '')) . ' → ' . trim((string)($shipment['destination_location'] ?? ''));
    $route = trim($route, " \t\n\r\0\x0B→");

    $when = '';
    if ($action === 'out_for_delivery') {
        $when = (string)($shipment['out_for_delivery_at'] ?? '');
        if ($when === '') $when = 'now';
    } elseif ($action === 'delivered') {
        $when = (string)($shipment['actual_delivery_date'] ?? '');
        if ($when === '') $when = 'now';
    }

    $subject = '';
    $body = '';

    if ($action === 'out_for_delivery') {
        $subject = "CargoConnect: Shipment Out for Delivery ($shipmentId)";
        $body = "Hi {$customerName},\n\n".
            "Good news! Your shipment is now OUT FOR DELIVERY.\n".
            "Shipment ID: {$shipmentId}\n".
            ($route !== '' ? "Route: {$route}\n" : '').
            "Time: {$when}\n\n".
            "Waybill: {$waybillUrl}\n\n".
            "Thank you for choosing CargoConnect.\n";
    } elseif ($action === 'delivered') {
        $subject = "CargoConnect: Shipment Delivered ($shipmentId)";
        $body = "Hi {$customerName},\n\n".
            "Your shipment has been DELIVERED successfully.\n".
            "Shipment ID: {$shipmentId}\n".
            ($route !== '' ? "Route: {$route}\n" : '').
            "Time: {$when}\n\n".
            "Waybill: {$waybillUrl}\n\n".
            "Thank you for choosing CargoConnect.\n";
    } else {
        return ['email_sent' => false, 'reason' => 'Unsupported email action'];
    }

    $headers = "MIME-Version: 1.0\r\n".
        "Content-type: text/plain; charset=UTF-8\r\n".
        "From: CargoConnect <no-reply@" . ($host = ($_SERVER['HTTP_HOST'] ?? 'localhost')) . ">\r\n";

    // Do not suppress errors; mail() result is returned in $ok.
    $ok = mail($to, $subject, $body, $headers);

    // Debug: log attempts/results
    error_log('ShipmentEmailResult: to=' . $to . ' shipment_id=' . $shipmentId . ' action=' . $action . ' ok=' . ($ok ? '1' : '0'));

    return [
        'email_sent' => (bool)$ok,
        'reason' => $ok ? 'mail() returned true' : 'mail() returned false'
    ];

}


if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

function sendJson($payload, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($payload);
    exit;
}

function normalizeActionStatus($value) {
    $map = [
        'Pending Confirmation' => 'pending_confirmation',
        'Approved' => 'approved',
        'Assigned' => 'assigned',
        'At Hub' => 'out_for_delivery',
        'In Hub' => 'out_for_delivery',
        'In Transit' => 'in_transit',
        'Out for Delivery' => 'out_for_delivery',
        'Delivered' => 'delivered',
        'Cancelled' => 'cancelled',
        'Cancellation Requested' => 'cancellation_requested'
    ];

    if (isset($map[$value])) return $map[$value];
    return str_replace('-', '_', strtolower(trim((string) $value)));
}

function displayActionStatus($value) {
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

function audit(PDO $pdo, $type, $id, $action, $by, $details = '') {
    $stmt = $pdo->prepare("
        INSERT INTO audit_logs (entity_type, entity_id, action_performed, changed_by, change_details)
        VALUES (:entity_type, :entity_id, :action_performed, :changed_by, :change_details)
    ");
    $stmt->execute([
        'entity_type' => $type,
        'entity_id' => $id,
        'action_performed' => $action,
        'changed_by' => $by,
        'change_details' => $details
    ]);
}

function getShipment(PDO $pdo, $shipmentId) {
    $stmt = $pdo->prepare("
        SELECT
            s.*,
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
            i.amount_paid
        FROM shipments s
        INNER JOIN customers c ON c.customer_id = s.customer_id
        INNER JOIN receivers r ON r.receiver_id = s.receiver_id
        INNER JOIN cargo ca ON ca.cargo_id = s.cargo_id
        LEFT JOIN invoices i ON i.invoice_id = s.invoice_id
        WHERE s.shipment_id = :shipment_id
        LIMIT 1
    ");
    $stmt->execute(['shipment_id' => $shipmentId]);
    return $stmt->fetch();
}

function addTracking(PDO $pdo, $shipmentId, $status, $location, $notes) {
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
}

function setShipmentStatus(PDO $pdo, $shipmentId, $status, $location, $notes, $adminName) {
    $status = normalizeActionStatus($status);
    $allowed = ['pending_confirmation', 'approved', 'assigned', 'in_transit', 'out_for_delivery', 'delivered', 'cancelled', 'cancellation_requested'];

    if (!in_array($status, $allowed, true)) {
        throw new Exception('Invalid shipment status');
    }

    $timestampField = null;
    if ($status === 'approved') $timestampField = 'confirmed_at';
    if ($status === 'assigned') $timestampField = 'assigned_at';
    if ($status === 'in_transit') $timestampField = 'in_transit_at';
    if ($status === 'out_for_delivery') $timestampField = 'out_for_delivery_at';
    if ($status === 'delivered') $timestampField = 'actual_delivery_date';

    $sql = "
        UPDATE shipments
        SET shipment_status = :status,
            row_version = row_version + 1
    ";

    if ($timestampField) {
        $sql .= ", {$timestampField} = IFNULL({$timestampField}, NOW())";
    }

    $sql .= " WHERE shipment_id = :shipment_id";

    $stmt = $pdo->prepare($sql);
    $stmt->execute(['status' => $status, 'shipment_id' => $shipmentId]);

    if ($stmt->rowCount() === 0) {
        throw new Exception('Shipment not found or no status change was made');
    }

    addTracking($pdo, $shipmentId, $status, $location, $notes);
    audit($pdo, 'shipment', $shipmentId, 'shipment status updated', $adminName, 'new_status=' . $status);

    return ['success' => true, 'message' => 'Shipment updated', 'status' => displayActionStatus($status)];
}

function assignFleet(PDO $pdo, $shipmentId, $fleetId, $adminName) {
    $shipment = getShipment($pdo, $shipmentId);
    if (!$shipment) throw new Exception('Shipment not found');
    if ($shipment['shipment_status'] !== 'approved') throw new Exception('Only approved shipments can be assigned');

    $stmt = $pdo->prepare("SELECT * FROM fleet WHERE fleet_id = :fleet_id LIMIT 1");
    $stmt->execute(['fleet_id' => $fleetId]);
    $fleet = $stmt->fetch();

    if (!$fleet) throw new Exception('Fleet vehicle not found');
    if ($fleet['operational_status'] !== 'available') throw new Exception('Fleet vehicle is not available');

    $stmt = $pdo->prepare("
        UPDATE shipments
        SET fleet_id = :fleet_id,
            shipment_status = 'assigned',
            assigned_at = IFNULL(assigned_at, NOW()),
            row_version = row_version + 1
        WHERE shipment_id = :shipment_id
    ");
    $stmt->execute(['fleet_id' => $fleetId, 'shipment_id' => $shipmentId]);

    $stmt = $pdo->prepare("
        UPDATE fleet
        SET operational_status = 'assigned',
            next_destination = :next_destination,
            row_version = row_version + 1
        WHERE fleet_id = :fleet_id
    ");
    $stmt->execute(['next_destination' => $shipment['destination_location'], 'fleet_id' => $fleetId]);

    addTracking($pdo, $shipmentId, 'assigned', $fleet['current_hub_location'], 'Fleet vehicle ' . $fleetId . ' assigned.');
    audit($pdo, 'shipment', $shipmentId, 'fleet assigned', $adminName, 'fleet_id=' . $fleetId);

    return ['success' => true, 'message' => 'Fleet assigned'];
}

function dispatchFleet(PDO $pdo, $fleetId, $adminName) {
    $stmt = $pdo->prepare("SELECT * FROM fleet WHERE fleet_id = :fleet_id LIMIT 1");
    $stmt->execute(['fleet_id' => $fleetId]);
    $fleet = $stmt->fetch();

    if (!$fleet) throw new Exception('Fleet vehicle not found');

    $stmt = $pdo->prepare("
        UPDATE fleet
        SET operational_status = 'dispatched',
            row_version = row_version + 1
        WHERE fleet_id = :fleet_id
    ");
    $stmt->execute(['fleet_id' => $fleetId]);

    $stmt = $pdo->prepare("
        SELECT shipment_id, origin_location
        FROM shipments
        WHERE fleet_id = :fleet_id
          AND shipment_status IN ('assigned', 'approved')
    ");
    $stmt->execute(['fleet_id' => $fleetId]);
    $shipments = $stmt->fetchAll();

    foreach ($shipments as $shipment) {
        setShipmentStatus(
            $pdo,
            $shipment['shipment_id'],
            'in_transit',
            $fleet['current_hub_location'],
            'Fleet vehicle ' . $fleetId . ' dispatched. Shipment is now in transit.',
            $adminName
        );
    }

    audit($pdo, 'fleet', $fleetId, 'fleet dispatched', $adminName, 'shipments=' . count($shipments));

    return ['success' => true, 'message' => 'Fleet dispatched', 'updated_shipments' => count($shipments)];
}

function markDelivered(PDO $pdo, $shipmentId, $adminName) {
    $shipment = getShipment($pdo, $shipmentId);
    if (!$shipment) throw new Exception('Shipment not found');

    $result = setShipmentStatus(
        $pdo,
        $shipmentId,
        'delivered',
        $shipment['destination_location'],
        'Shipment successfully delivered.',
        $adminName
    );

    if (!empty($shipment['fleet_id'])) {
        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM shipments
            WHERE fleet_id = :fleet_id
              AND shipment_status IN ('assigned', 'in_transit', 'out_for_delivery')
        ");
        $stmt->execute(['fleet_id' => $shipment['fleet_id']]);

        if ((int) $stmt->fetchColumn() === 0) {
            $stmt = $pdo->prepare("
                UPDATE fleet
                SET operational_status = 'available',
                    current_hub_location = :location,
                    next_destination = NULL,
                    row_version = row_version + 1
                WHERE fleet_id = :fleet_id
            ");
            $stmt->execute(['location' => $shipment['destination_location'], 'fleet_id' => $shipment['fleet_id']]);
        }
    }

    return $result;
}

function requestCancellation(PDO $pdo, $shipmentId, $requestedBy, $reason) {
    $shipment = getShipment($pdo, $shipmentId);
    if (!$shipment) throw new Exception('Shipment not found');

    $stmt = $pdo->prepare("
        INSERT INTO cancellation_requests (shipment_id, requested_by, cancellation_reason, request_status)
        VALUES (:shipment_id, :requested_by, :reason, 'pending')
    ");
    $stmt->execute([
        'shipment_id' => $shipmentId,
        'requested_by' => $requestedBy,
        'reason' => trim($reason) ?: 'Customer requested cancellation'
    ]);

    $stmt = $pdo->prepare("
        UPDATE shipments
        SET shipment_status = 'cancellation_requested',
            row_version = row_version + 1
        WHERE shipment_id = :shipment_id
          AND shipment_status IN ('pending_confirmation', 'approved')
    ");
    $stmt->execute(['shipment_id' => $shipmentId]);

    addTracking($pdo, $shipmentId, 'cancellation_requested', 'system', 'Cancellation requested by ' . $requestedBy);
    audit($pdo, 'cancellation', $shipmentId, 'cancellation requested', $requestedBy, $reason);

    return ['success' => true, 'message' => 'Cancellation request saved'];
}

function resolveCancellation(PDO $pdo, $shipmentId, $status, $adminName) {
    $status = strtolower(trim($status));
    if (!in_array($status, ['approved', 'rejected'], true)) {
        throw new Exception('Cancellation resolution must be approved or rejected');
    }

    $stmt = $pdo->prepare("
        UPDATE cancellation_requests
        SET request_status = :status,
            resolved_at = NOW()
        WHERE shipment_id = :shipment_id
          AND request_status = 'pending'
    ");
    $stmt->execute(['status' => $status, 'shipment_id' => $shipmentId]);

    if ($stmt->rowCount() === 0) {
        throw new Exception('No pending cancellation request found');
    }

    if ($status === 'approved') {
        setShipmentStatus($pdo, $shipmentId, 'cancelled', 'system', 'Cancellation approved by admin.', $adminName);
    } else {
        setShipmentStatus($pdo, $shipmentId, 'approved', 'system', 'Cancellation rejected by admin.', $adminName);
    }

    audit($pdo, 'cancellation', $shipmentId, 'cancellation ' . $status, $adminName, '');

    return ['success' => true, 'message' => 'Cancellation ' . $status];
}

function getCancellations() {
    $pdo = getDBConnection();
    $stmt = $pdo->query("
        SELECT cr.*, s.shipment_status, s.origin_location, s.destination_location
        FROM cancellation_requests cr
        INNER JOIN shipments s ON s.shipment_id = cr.shipment_id
        ORDER BY cr.requested_at DESC
    ");
    return $stmt->fetchAll();
}

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $resource = $_GET['resource'] ?? '';

        if ($resource === 'cancellations') {
            sendJson(['success' => true, 'cancellations' => getCancellations()]);
        }

        sendJson(['success' => false, 'message' => 'Unknown resource'], 400);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendJson(['success' => false, 'message' => 'Method not allowed'], 405);
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) $input = [];

    $action = $input['action'] ?? '';
    $adminName = trim($input['admin_name'] ?? 'Admin');
    $pdo = getDBConnection();
    $pdo->beginTransaction();

    if ($action === 'confirm_shipment') {
        $result = setShipmentStatus($pdo, $input['shipment_id'] ?? '', 'approved', $input['current_location'] ?? 'system', 'Shipment confirmed by admin.', $adminName);
    } elseif ($action === 'assign_fleet') {
        $result = assignFleet($pdo, $input['shipment_id'] ?? '', $input['fleet_id'] ?? '', $adminName);
    } elseif ($action === 'dispatch_fleet') {
        $result = dispatchFleet($pdo, $input['fleet_id'] ?? '', $adminName);
    } elseif ($action === 'mark_in_transit') {
        $result = setShipmentStatus($pdo, $input['shipment_id'] ?? '', 'in_transit', $input['current_location'] ?? 'system', $input['event_notes'] ?? 'Shipment is in transit.', $adminName);
    } elseif ($action === 'mark_out_for_delivery') {
        $shipment = getShipment($pdo, $input['shipment_id'] ?? '');
        if (!$shipment) {
            throw new Exception('Shipment not found');
        }
        $result = setShipmentStatus($pdo, $input['shipment_id'] ?? '', 'out_for_delivery', $shipment['destination_location'] ?? 'system', 'Shipment is out for delivery.', $adminName);

        // Send customer email (best-effort, never break status update)
        try {
            $waybillUrl = buildWaybillUrl($input['shipment_id'] ?? '');
            $email = sendShipmentEmailBestEffort($shipment, 'out_for_delivery', $waybillUrl);
            $result['email_sent'] = $email['email_sent'] ?? false;
            if (!empty($email['reason'])) $result['email_reason'] = $email['reason'];
        } catch (Throwable $e) {
            error_log('Out-for-delivery email error: ' . $e->getMessage());
            $result['email_sent'] = false;
            $result['email_reason'] = $e->getMessage();
        }
    } elseif ($action === 'mark_delivered') {
        $shipment = getShipment($pdo, $input['shipment_id'] ?? '');
        if (!$shipment) {
            throw new Exception('Shipment not found');
        }
        $result = markDelivered($pdo, $input['shipment_id'] ?? '', $adminName);

        // Send customer email (best-effort, never break status update)
        try {
            $waybillUrl = buildWaybillUrl($input['shipment_id'] ?? '');
            $email = sendShipmentEmailBestEffort($shipment, 'delivered', $waybillUrl);
            $result['email_sent'] = $email['email_sent'] ?? false;
            if (!empty($email['reason'])) $result['email_reason'] = $email['reason'];
        } catch (Throwable $e) {
            error_log('Delivered email error: ' . $e->getMessage());
            $result['email_sent'] = false;
            $result['email_reason'] = $e->getMessage();
        }
    } elseif ($action === 'request_cancellation') {
        $result = requestCancellation($pdo, $input['shipment_id'] ?? '', $input['requested_by'] ?? 'Customer', $input['reason'] ?? '');
    } elseif ($action === 'resolve_cancellation') {
        $result = resolveCancellation($pdo, $input['shipment_id'] ?? '', $input['request_status'] ?? '', $adminName);
    } else {
        throw new Exception('Unknown admin action');
    }

    $pdo->commit();
    sendJson($result);

} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('Admin API error: ' . $e->getMessage());
    sendJson(['success' => false, 'message' => $e->getMessage()], 400);
}
?>
