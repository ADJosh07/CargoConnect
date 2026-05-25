<?php
/**
 * Fleet API Endpoint
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

/** Outputs JSON and exits. */
function sendJson($payload, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($payload);
    exit;
}

/** Validates and normalizes fleet operational_status values. */
function normalizeFleetStatus($status) {
    $status = strtolower(trim((string) $status));
    $allowed = ['available', 'assigned', 'dispatched', 'maintenance'];
    return in_array($status, $allowed, true) ? $status : 'available';
}

/** Returns one vehicle or the full fleet list from the database. */
function getFleet($fleetId = null) {
    $pdo = getDBConnection();

    if ($fleetId) {
        $stmt = $pdo->prepare("
            SELECT
                fleet_id,
                vehicle_type,
                weight_capacity_kg,
                volume_capacity_cubic,
                current_hub_location,
                next_destination,
                operational_status,
                operational_status AS status,
                last_service_date,
                last_updated_at,
                row_version
            FROM fleet
            WHERE fleet_id = :fleet_id
        ");
        $stmt->execute(['fleet_id' => $fleetId]);
        return $stmt->fetch();
    }

    $stmt = $pdo->query("
        SELECT
            fleet_id,
            vehicle_type,
            weight_capacity_kg,
            volume_capacity_cubic,
            current_hub_location,
            next_destination,
            operational_status,
            operational_status AS status,
            last_service_date,
            last_updated_at,
            row_version
        FROM fleet
        ORDER BY last_updated_at DESC
    ");

    return $stmt->fetchAll();
}

/** Inserts a new fleet row (POST handler). */
function createFleetVehicle($data) {
    $required = ['vehicle_type', 'weight_capacity_kg', 'current_hub_location'];

    foreach ($required as $field) {
        if (!isset($data[$field]) || trim((string) $data[$field]) === '') {
            throw new Exception("Missing required field: {$field}");
        }
    }

    $fleetId = trim($data['fleet_id'] ?? ('FLT-' . random_int(1000, 9999)));
    $pdo = getDBConnection();

    $stmt = $pdo->prepare("
        INSERT INTO fleet (
            fleet_id,
            vehicle_type,
            weight_capacity_kg,
            volume_capacity_cubic,
            current_hub_location,
            next_destination,
            operational_status,
            last_service_date
        )
        VALUES (
            :fleet_id,
            :vehicle_type,
            :weight_capacity_kg,
            :volume_capacity_cubic,
            :current_hub_location,
            :next_destination,
            :operational_status,
            :last_service_date
        )
    ");
    $stmt->execute([
        'fleet_id' => $fleetId,
        'vehicle_type' => trim($data['vehicle_type']),
        'weight_capacity_kg' => (float) $data['weight_capacity_kg'],
        'volume_capacity_cubic' => (float) ($data['volume_capacity_cubic'] ?? 0),
        'current_hub_location' => trim($data['current_hub_location']),
        'next_destination' => trim($data['next_destination'] ?? '') ?: null,
        'operational_status' => normalizeFleetStatus($data['operational_status'] ?? 'available'),
        'last_service_date' => trim($data['last_service_date'] ?? '') ?: null
    ]);

    return ['success' => true, 'message' => 'Fleet vehicle created successfully', 'fleet_id' => $fleetId];
}

/** Updates allowed fleet fields such as operational_status (PUT handler). */
function updateFleetVehicle($fleetId, $data) {
    $allowed = [
        'vehicle_type',
        'weight_capacity_kg',
        'volume_capacity_cubic',
        'current_hub_location',
        'next_destination',
        'operational_status',
        'last_service_date'
    ];

    $updates = [];
    $params = ['fleet_id' => $fleetId];

    foreach ($allowed as $field) {
        if (array_key_exists($field, $data)) {
            $updates[] = "{$field} = :{$field}";
            $params[$field] = $field === 'operational_status'
                ? normalizeFleetStatus($data[$field])
                : ($data[$field] === '' ? null : $data[$field]);
        }
    }

    if (isset($data['status']) && !isset($data['operational_status'])) {
        $updates[] = 'operational_status = :operational_status';
        $params['operational_status'] = normalizeFleetStatus($data['status']);
    }

    if (empty($updates)) {
        throw new Exception('No valid fields to update');
    }

    $pdo = getDBConnection();
    $stmt = $pdo->prepare("
        UPDATE fleet
        SET " . implode(', ', $updates) . ",
            row_version = row_version + 1
        WHERE fleet_id = :fleet_id
    ");
    $stmt->execute($params);

    if ($stmt->rowCount() === 0) {
        throw new Exception('Fleet vehicle not found or no changes were made');
    }

    return ['success' => true, 'message' => 'Fleet vehicle updated successfully'];
}

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $fleetId = $_GET['fleet_id'] ?? null;
        $fleet = getFleet($fleetId);

        if ($fleetId) {
            sendJson($fleet
                ? ['success' => true, 'vehicle' => $fleet]
                : ['success' => false, 'message' => 'Vehicle not found'],
                $fleet ? 200 : 404
            );
        }

        sendJson(['success' => true, 'fleet' => $fleet]);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        sendJson(createFleetVehicle(is_array($input) ? $input : []));
    }

    if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
        $input = json_decode(file_get_contents('php://input'), true);

        if (empty($input['fleet_id'])) {
            sendJson(['success' => false, 'message' => 'fleet_id is required'], 400);
        }

        $fleetId = $input['fleet_id'];
        unset($input['fleet_id']);

        sendJson(updateFleetVehicle($fleetId, is_array($input) ? $input : []));
    }

    sendJson(['success' => false, 'message' => 'Method not allowed'], 405);
} catch (Throwable $e) {
    error_log('Fleet API error: ' . $e->getMessage());
    sendJson(['success' => false, 'message' => $e->getMessage()], 400);
}
?>
