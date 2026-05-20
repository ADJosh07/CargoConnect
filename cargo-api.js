const CARGO_API_BASE = (window.API_CONFIG && window.API_CONFIG.API_BASE_URL) || '../backend/api';

async function cargoApi(endpoint, method = 'GET', data = null) {
    const options = {
        method,
        headers: { 'Content-Type': 'application/json' }
    };

    if (data) {
        options.body = JSON.stringify(data);
    }

    const response = await fetch(`${CARGO_API_BASE}/${endpoint}`, options);
    const contentType = response.headers.get('content-type') || '';
    const payload = contentType.includes('application/json')
        ? await response.json()
        : { success: false, message: await response.text() };

    if (!response.ok || payload.success === false) {
        throw new Error(payload.message || `HTTP ${response.status}`);
    }

    return payload;
}

function cargoCurrentUser() {
    try {
        return JSON.parse(localStorage.getItem('cargoConnectCurrentUser'));
    } catch {
        return null;
    }
}

function cargoStatusLabel(status) {
    const labels = {
        pending_confirmation: 'Pending Confirmation',
        approved: 'Approved',
        assigned: 'Assigned',
        in_transit: 'In Transit',
        out_for_delivery: 'Out for Delivery',
        delivered: 'Delivered',
        cancelled: 'Cancelled',
        cancellation_requested: 'Cancellation Requested'
    };

    return labels[status] || status || 'Unknown';
}

function cargoServiceLabel(service) {
    return service === 'door_to_door' ? 'Door-to-Door' : 'Hub-to-Hub';
}

function cargoNormalizeShipment(row) {
    return {
        id: row.shipment_id,
        shipment_id: row.shipment_id,
        sender: row.customer_name,
        receiver: row.receiver_name,
        contact: row.receiver_phone || row.customer_phone,
        customerEmail: row.customer_email,
        origin: row.origin_location,
        destination: row.destination_location,
        service: row.service || cargoServiceLabel(row.service_type),
        status: row.status || cargoStatusLabel(row.shipment_status),
        paymentStatus: row.payment_status === 'paid' ? 'Paid' : cargoStatusLabel(row.payment_status),
        paymentAmount: row.total_amount || row.amount_paid || 0,
        estimatedCost: row.total_amount || 0,
        paymentMethod: row.payment_method || '',
        paymentReference: row.transaction_reference || '',
        paidAt: row.paid_at || '',
        vehicleID: row.fleet_id || '',
        cargoType: row.handling_type || 'standard',
        weight: row.weight_in_kg || 0,
        cubicMeters: row.volume_in_cubic_meters || 0,
        address: row.receiver_address || '',
        instructions: row.special_instructions || '',
        timestamp: row.booking_date,
        createdAt: row.booking_date,
        confirmedAt: row.confirmed_at,
        inTransitAt: row.in_transit_at,
        outForDeliveryAt: row.out_for_delivery_at,
        deliveredAt: row.actual_delivery_date,
        invoiceNumber: row.invoice_number || ''
    };
}

async function cargoGetShipments(customerId = null) {
    const endpoint = customerId
        ? `shipments.php?customer_id=${encodeURIComponent(customerId)}`
        : 'shipments.php';
    const result = await cargoApi(endpoint);
    return (result.shipments || []).map(cargoNormalizeShipment);
}

async function cargoGetShipment(shipmentId) {
    const result = await cargoApi(`shipments.php?shipment_id=${encodeURIComponent(shipmentId)}`);
    const row = result.shipment || (result.shipments || [])[0];
    return row ? cargoNormalizeShipment(row) : null;
}

async function cargoGetFleet() {
    const result = await cargoApi('fleet.php');
    return result.fleet || [];
}

async function cargoGetVehicle(fleetId) {
    const result = await cargoApi(`fleet.php?fleet_id=${encodeURIComponent(fleetId)}`);
    return result.vehicle || null;
}

async function cargoAdmin(action, data = {}) {
    const user = cargoCurrentUser();
    return cargoApi('admin.php', 'POST', {
        action,
        admin_name: user ? user.fullName || user.email || 'Admin' : 'Admin',
        ...data
    });
}

async function cargoGetTracking(shipmentId) {
    const result = await cargoApi(`tracking.php?shipment_id=${encodeURIComponent(shipmentId)}`);
    return result.tracking || [];
}

async function cargoGetCancellations() {
    const result = await cargoApi('admin.php?resource=cancellations');
    return result.cancellations || [];
}
