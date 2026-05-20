/**
 * Shipment Tracking API Integration
 * Handles all tracking-related API calls and frontend logic
 * This file should be included in tracking pages (tracking.html, etc.)
 */

// API Base URL
const TRACKING_API_BASE = (window.API_CONFIG && window.API_CONFIG.API_BASE_URL) || '../backend/api';

/**
 * Make tracking API calls with proper error handling
 */
async function trackingApiCall(endpoint, method = 'GET', data = null) {
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

        const response = await fetch(`${TRACKING_API_BASE}/${endpoint}`, options);

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }

        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            throw new Error('Server returned non-JSON response');
        }

        return await response.json();
    } catch (error) {
        console.error('Tracking API Error:', error);
        throw error;
    }
}

/**
 * API wrapper functions
 */
async function getShipmentTracking(shipmentId) {
    return trackingApiCall(`tracking.php?shipment_id=${shipmentId}`, 'GET');
}

async function getCustomerTracking(customerId = null) {
    let endpoint = 'tracking.php';
    if (customerId) {
        endpoint += `?customer_id=${customerId}`;
    }
    return trackingApiCall(endpoint, 'GET');
}

async function updateShipmentTracking(shipmentId, data) {
    return trackingApiCall('tracking.php', 'POST', {
        shipment_id: shipmentId,
        ...data
    });
}

function normalizeTrackingStatusLabel(status) {
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

    return labels[status] || String(status || 'Unknown').replaceAll('-', ' ');
}

/**
 * Get all tracking info for current user
 */
async function getUserTracking() {
    try {
        const user = getCurrentUser();
        if (!user || !user.id) {
            throw new Error("User not logged in");
        }

        const response = await getCustomerTracking(user.id);
        if (!response.success) {
            throw new Error(response.message || 'Failed to fetch tracking');
        }

        return response.tracking || [];
    } catch (error) {
        console.error('Error getting user tracking:', error);
        throw error;
    }
}

/**
 * Get tracking for specific shipment
 */
async function getShipmentTrackingInfo(shipmentId) {
    try {
        const response = await getShipmentTracking(shipmentId);
        if (!response.success) {
            throw new Error(response.message || 'Failed to fetch tracking');
        }

        return response.tracking || [];
    } catch (error) {
        console.error('Error getting shipment tracking:', error);
        throw error;
    }
}

/**
 * Update tracking status (admin/system)
 */
async function updateTrackingStatus(shipmentId, location, status) {
    try {
        const response = await updateShipmentTracking(shipmentId, {
            current_location: location,
            status: status
        });

        if (!response.success) {
            throw new Error(response.message || 'Failed to update tracking');
        }

        return response;
    } catch (error) {
        console.error('Error updating tracking:', error);
        throw error;
    }
}

/**
 * Display tracking timeline
 */
function displayTrackingTimeline(tracking, containerId = 'trackingTimeline') {
    const container = document.getElementById(containerId);
    if (!container) {
        console.error('Container not found:', containerId);
        return;
    }

    if (!tracking || tracking.length === 0) {
        container.innerHTML = '<p>No tracking information available</p>';
        return;
    }

    // Sort by date descending (most recent first)
    const sorted = [...tracking].sort((a, b) => new Date(b.updated_at) - new Date(a.updated_at));

    container.innerHTML = sorted.map((item, index) => `
        <div class="tracking-event ${index === 0 ? 'current' : ''}">
            <div class="tracking-marker">
                <i class="fa-solid ${getTrackingIcon(item.status)}"></i>
            </div>
            <div class="tracking-content">
                <h5>${normalizeTrackingStatusLabel(item.status).toUpperCase()}</h5>
                <p><strong>Location:</strong> ${item.current_location}</p>
                <p><strong>Time:</strong> ${formatDate(item.updated_at)}</p>
            </div>
        </div>
    `).join('');
}

/**
 * Get icon for tracking status
 */
function getTrackingIcon(status) {
    const icons = {
        'pending': 'fa-clock',
        'pending_confirmation': 'fa-clock',
        'booked': 'fa-check-circle',
        'in-transit': 'fa-truck',
        'in_transit': 'fa-truck',
        'out-for-delivery': 'fa-box-open',
        'out_for_delivery': 'fa-box-open',
        'delivered': 'fa-check-double',
        'cancelled': 'fa-times-circle'
    };
    return icons[status] || 'fa-info-circle';
}

/**
 * Get tracking color for status
 */
function getTrackingColor(status) {
    const colors = {
        'pending': '#ffc107',
        'pending_confirmation': '#ffc107',
        'booked': '#17a2b8',
        'in-transit': '#007bff',
        'in_transit': '#007bff',
        'out-for-delivery': '#20c997',
        'out_for_delivery': '#20c997',
        'delivered': '#28a745',
        'cancelled': '#dc3545'
    };
    return colors[status] || '#6c757d';
}

/**
 * Display tracking list
 */
function displayTrackingList(trackingData, containerId = 'trackingList') {
    const container = document.getElementById(containerId);
    if (!container) {
        console.error('Container not found:', containerId);
        return;
    }

    if (!trackingData || trackingData.length === 0) {
        container.innerHTML = '<p>No tracking data available</p>';
        return;
    }

    container.innerHTML = trackingData.map(item => `
        <div class="tracking-card">
            <div class="tracking-header">
                <h5>Shipment: ${item.shipment_id}</h5>
                <span class="badge" style="background-color: ${getTrackingColor(item.status)}">
                    ${normalizeTrackingStatusLabel(item.status)}
                </span>
            </div>
            <div class="tracking-body">
                <p><strong>From:</strong> ${item.origin_location || 'N/A'}</p>
                <p><strong>To:</strong> ${item.destination_location || 'N/A'}</p>
                <p><strong>Current:</strong> ${item.current_location}</p>
                <p><strong>Service:</strong> ${item.service_type || 'N/A'}</p>
                <p><strong>Last Update:</strong> ${formatDate(item.updated_at)}</p>
            </div>
            <div class="tracking-footer">
                <button class="btn btn-sm btn-primary" onclick="viewDetailedTracking(${item.shipment_id})">
                    <i class="fa-solid fa-map"></i> Details
                </button>
            </div>
        </div>
    `).join('');
}

/**
 * Format date string
 */
function formatDate(dateString) {
    if (!dateString) return 'N/A';
    try {
        const date = new Date(dateString);
        return date.toLocaleString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    } catch (e) {
        return dateString;
    }
}

/**
 * Load and display user's tracking
 */
async function loadUserTracking() {
    try {
        const tracking = await getUserTracking();
        displayTrackingList(tracking);
    } catch (error) {
        alert("Failed to load tracking: " + error.message);
        console.error(error);
    }
}

/**
 * View detailed tracking for a shipment
 */
async function viewDetailedTracking(shipmentId) {
    try {
        const tracking = await getShipmentTrackingInfo(shipmentId);
        
        // Display in modal or new view
        const modalContent = document.getElementById('trackingDetailModal');
        if (modalContent) {
            displayTrackingTimeline(tracking, 'trackingDetailTimeline');
            // Show modal (using Bootstrap or your modal system)
            const modal = new bootstrap.Modal(modalContent);
            modal.show();
        } else {
            // Fallback: show timeline in alert
            let content = `Tracking for Shipment #${shipmentId}\n\n`;
            tracking.forEach(item => {
                content += `${normalizeTrackingStatusLabel(item.status).toUpperCase()}\n`;
                content += `Location: ${item.current_location}\n`;
                content += `Time: ${formatDate(item.updated_at)}\n\n`;
            });
            alert(content);
        }
    } catch (error) {
        alert("Failed to load tracking details: " + error.message);
    }
}

/**
 * Handle tracking search/filter
 */
function filterTrackingByStatus(status) {
    try {
        const user = getCurrentUser();
        if (!user || !user.id) {
            alert("Please log in first");
            return;
        }

        getUserTracking().then(tracking => {
            const filtered = status ? 
                tracking.filter(t => t.status === status || normalizeTrackingStatusLabel(t.status) === status) :
                tracking;
            displayTrackingList(filtered);
        });
    } catch (error) {
        alert("Failed to filter tracking: " + error.message);
    }
}

/**
 * Display tracking statistics
 */
async function displayTrackingStats() {
    try {
        const tracking = await getUserTracking();
        
        const stats = {
            pending: tracking.filter(t => t.status === 'pending_confirmation' || t.status === 'pending').length,
            inTransit: tracking.filter(t => t.status === 'in_transit' || t.status === 'in-transit').length,
            outForDelivery: tracking.filter(t => t.status === 'out_for_delivery' || t.status === 'out-for-delivery').length,
            delivered: tracking.filter(t => t.status === 'delivered').length,
            cancelled: tracking.filter(t => t.status === 'cancelled').length
        };

        // Update stats display
        const statsContainer = document.getElementById('trackingStats');
        if (statsContainer) {
            statsContainer.innerHTML = `
                <div class="stat">
                    <strong>Pending:</strong> <span>${stats.pending}</span>
                </div>
                <div class="stat">
                    <strong>In Transit:</strong> <span>${stats.inTransit}</span>
                </div>
                <div class="stat">
                    <strong>Out for Delivery:</strong> <span>${stats.outForDelivery}</span>
                </div>
                <div class="stat">
                    <strong>Delivered:</strong> <span>${stats.delivered}</span>
                </div>
                <div class="stat">
                    <strong>Cancelled:</strong> <span>${stats.cancelled}</span>
                </div>
            `;
        }

        return stats;
    } catch (error) {
        console.error('Error displaying tracking stats:', error);
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    // Check if user is logged in
    if (getCurrentUser()) {
        // Load tracking if on tracking page
        if (document.getElementById('trackingList')) {
            loadUserTracking();
        }

        // Display stats if available
        if (document.getElementById('trackingStats')) {
            displayTrackingStats();
        }
    }
});
