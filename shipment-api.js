/**
 * Shipment Management API Integration
 * Handles all shipment-related API calls and frontend logic
 * This file should be included in shipment pages (shipments.html, book.html, etc.)
 */

// API Base URL
const SHIPMENT_API_BASE = (window.API_CONFIG && window.API_CONFIG.API_BASE_URL) || '../backend/api';

/**
 * Make shipment API calls with proper error handling
 */
async function shipmentApiCall(endpoint, method = 'GET', data = null) {
    try {
        const options = {
            method: method,
            headers: {
                'Content-Type': 'application/json',
            }
        };

        if (data) {
            options.body = JSON.stringify(data);
        }

        const response = await fetch(`${SHIPMENT_API_BASE}/${endpoint}`, options);

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }

        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            throw new Error('Server returned non-JSON response');
        }

        return await response.json();
    } catch (error) {
        console.error('Shipment API Error:', error);
        throw error;
    }
}

/**
 * API wrapper functions
 */
async function getShipments(customerId = null) {
    let endpoint = 'shipments.php';
    if (customerId) {
        endpoint += `?customer_id=${customerId}`;
    }
    return shipmentApiCall(endpoint, 'GET');
}

async function createShipment(data) {
    return shipmentApiCall('shipments.php', 'POST', data);
}

async function updateShipment(shipmentId, data) {
    return shipmentApiCall('shipments.php', 'PUT', {
        shipment_id: shipmentId,
        ...data
    });
}

/**
 * Get shipments for current user
 */
async function getUserShipments() {
    try {
        const user = getCurrentUser();
        if (!user || !user.id) {
            throw new Error("User not logged in");
        }

        const response = await getShipments(user.id);
        if (!response.success) {
            throw new Error(response.message || 'Failed to fetch shipments');
        }

        return response.shipments || [];
    } catch (error) {
        console.error('Error getting user shipments:', error);
        throw error;
    }
}

/**
 * Create a new shipment for current user
 */
async function bookNewShipment(formData) {
    try {
        const user = getCurrentUser();
        if (!user || !user.id) {
            throw new Error("User not logged in");
        }

        // Validate required fields
        if (!formData.origin || !formData.destination || !formData.serviceType) {
            throw new Error("Missing required fields");
        }

        const response = await createShipment({
            customer_id: user.id,
            receiver_name: formData.receiverName,
            receiver_email: formData.receiverEmail || "",
            receiver_phone: formData.receiverPhone,
            receiver_address: formData.receiverAddress || "",
            origin: formData.origin,
            destination: formData.destination,
            service_type: formData.serviceType,
            handling_type: formData.handlingType || "Standard",
            weight: formData.weight,
            volume: formData.volume,
            special_instructions: formData.specialInstructions || "",
            payment_method: formData.paymentMethod,
            transaction_ref: formData.transactionRef || ""
        });

        if (!response.success) {
            throw new Error(response.message || 'Failed to create shipment');
        }

        return response;
    } catch (error) {
        console.error('Error booking shipment:', error);
        throw error;
    }
}

/**
 * Update shipment status (admin only typically)
 */
async function updateShipmentStatus(shipmentId, newStatus) {
    try {
        const response = await updateShipment(shipmentId, {
            status: newStatus
        });

        if (!response.success) {
            throw new Error(response.message || 'Failed to update shipment');
        }

        return response;
    } catch (error) {
        console.error('Error updating shipment:', error);
        throw error;
    }
}

/**
 * Display shipments in a table
 */
function displayShipments(shipments, containerId = 'shipmentsTable') {
    const container = document.getElementById(containerId);
    if (!container) {
        console.error('Container not found:', containerId);
        return;
    }

    if (!shipments || shipments.length === 0) {
        container.innerHTML = '<tr><td colspan="7">No shipments found</td></tr>';
        return;
    }

    container.innerHTML = shipments.map(shipment => `
        <tr>
            <td>#${shipment.shipment_id}</td>
            <td>${shipment.origin_location}</td>
            <td>${shipment.destination_location}</td>
            <td>${shipment.service || shipment.service_type}</td>
            <td>${shipment.booking_date || 'N/A'}</td>
            <td>
                <span class="badge ${getStatusBadgeClass(shipment.status)}">
                    ${shipment.status}
                </span>
            </td>
            <td>
                <button class="btn btn-sm btn-info" onclick="viewShipmentDetails(${shipment.shipment_id})">
                    <i class="fa-solid fa-eye"></i> View
                </button>
            </td>
        </tr>
    `).join('');
}

/**
 * Get status badge class for display
 */
function getStatusBadgeClass(status) {
    const statusClasses = {
        'Pending Confirmation': 'bg-warning',
        'Approved': 'bg-info',
        'Assigned': 'bg-secondary',
        'In Transit': 'bg-primary',
        'Out for Delivery': 'bg-info',
        'delivered': 'bg-success',
        'Delivered': 'bg-success',
        'cancelled': 'bg-danger',
        'Cancelled': 'bg-danger'
    };
    return statusClasses[status] || 'bg-secondary';
}

/**
 * Load and display user's shipments
 */
async function loadUserShipments() {
    try {
        const shipments = await getUserShipments();
        displayShipments(shipments);
    } catch (error) {
        alert("Failed to load shipments: " + error.message);
        console.error(error);
    }
}

/**
 * Handle shipment booking form submission
 */
async function submitShipmentBooking(event) {
    if (event) {
        event.preventDefault();
    }

    try {
        // Get form data
        const formData = {
            origin: document.getElementById('shipmentOrigin')?.value,
            destination: document.getElementById('shipmentDestination')?.value,
            serviceType: document.getElementById('shipmentService')?.value,
            receiverName: document.getElementById('receiverName')?.value,
            receiverPhone: document.getElementById('receiverPhone')?.value,
            receiverAddress: document.getElementById('receiverAddress')?.value,
            handlingType: document.getElementById('handlingType')?.value,
            weight: document.getElementById('shipmentWeight')?.value,
            volume: document.getElementById('shipmentVolume')?.value,
            paymentMethod: document.getElementById('paymentMethod')?.value,
            transactionRef: document.getElementById('transactionRef')?.value
        };

        // Validate
        if (!formData.origin || !formData.destination || !formData.serviceType) {
            alert("Please fill in all required fields");
            return;
        }

        // Disable button
        const submitBtn = event?.target?.querySelector('button[type="submit"]');
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.textContent = 'Booking...';
        }

        // Submit
        const response = await bookNewShipment(formData);
        alert("Shipment booked successfully! ID: " + response.shipment_id);

        // Reset form
        if (event) {
            event.target.reset();
        }

        // Reload shipments
        await loadUserShipments();

    } catch (error) {
        alert("Failed to book shipment: " + error.message);
    } finally {
        const submitBtn = event?.target?.querySelector('button[type="submit"]');
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.textContent = 'Book Shipment';
        }
    }
}

/**
 * View shipment details modal (to be implemented in HTML)
 */
async function viewShipmentDetails(shipmentId) {
    try {
        const shipments = await getUserShipments();
        const shipment = shipments.find(s => s.shipment_id == shipmentId);
        
        if (!shipment) {
            alert("Shipment not found");
            return;
        }

        // Create modal HTML
        const modalContent = `
            <h5>Shipment #${shipment.shipment_id}</h5>
            <p><strong>From:</strong> ${shipment.origin_location}</p>
            <p><strong>To:</strong> ${shipment.destination_location}</p>
            <p><strong>Service:</strong> ${shipment.service_type}</p>
            <p><strong>Status:</strong> <span class="badge ${getStatusBadgeClass(shipment.status)}">${shipment.status}</span></p>
            <p><strong>Booked:</strong> ${shipment.booking_date || 'N/A'}</p>
            <p><strong>Delivered:</strong> ${shipment.actual_delivery_date || 'N/A'}</p>
        `;

        // Show in alert or modal (implement as needed)
        alert(modalContent);
    } catch (error) {
        alert("Failed to load shipment details: " + error.message);
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    // Check if user is logged in
    if (getCurrentUser()) {
        // Load shipments if on shipments page
        if (document.getElementById('shipmentsTable')) {
            loadUserShipments();
        }
        
        // Attach form handlers if on booking page
        const shipmentForm = document.getElementById('shipmentBookingForm');
        if (shipmentForm) {
            shipmentForm.addEventListener('submit', submitShipmentBooking);
        }
    }
});
