/**
 * Fleet Management API Integration
 * Handles all fleet-related API calls and frontend logic
 * This file should be included in fleet management pages (fleet.html, admin pages, etc.)
 */

// API Base URL
const FLEET_API_BASE = (window.API_CONFIG && window.API_CONFIG.API_BASE_URL) || '../backend/api';

/**
 * Make fleet API calls with proper error handling
 */
async function fleetApiCall(endpoint, method = 'GET', data = null) {
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

        const response = await fetch(`${FLEET_API_BASE}/${endpoint}`, options);

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }

        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            throw new Error('Server returned non-JSON response');
        }

        return await response.json();
    } catch (error) {
        console.error('Fleet API Error:', error);
        throw error;
    }
}

/**
 * API wrapper functions
 */
async function getAllFleet() {
    return fleetApiCall('fleet.php', 'GET');
}

async function getFleetVehicle(fleetId) {
    return fleetApiCall(`fleet.php?fleet_id=${fleetId}`, 'GET');
}

async function createFleetVehicle(data) {
    return fleetApiCall('fleet.php', 'POST', data);
}

async function updateFleetVehicle(fleetId, data) {
    return fleetApiCall('fleet.php', 'PUT', {
        fleet_id: fleetId,
        ...data
    });
}

/**
 * Get all fleet vehicles
 */
async function loadFleetVehicles() {
    try {
        const response = await getAllFleet();
        if (!response.success) {
            throw new Error(response.message || 'Failed to fetch fleet');
        }

        return response.fleet || [];
    } catch (error) {
        console.error('Error loading fleet:', error);
        throw error;
    }
}

/**
 * Add new vehicle to fleet
 */
async function addVehicleToFleet(vehicleData) {
    try {
        // Validate required fields
        if (!vehicleData.vehicleType || !vehicleData.weightCapacity || !vehicleData.currentHubLocation) {
            throw new Error("Missing required fields");
        }

        const response = await createFleetVehicle({
            fleet_id: vehicleData.fleetId || vehicleData.vehiclePlate || undefined,
            vehicle_type: vehicleData.vehicleType,
            weight_capacity_kg: vehicleData.weightCapacity,
            volume_capacity_cubic: vehicleData.volumeCapacity,
            current_hub_location: vehicleData.currentHubLocation,
            next_destination: vehicleData.nextDestination || null,
            operational_status: vehicleData.operationalStatus || 'available'
        });

        if (!response.success) {
            throw new Error(response.message || 'Failed to create vehicle');
        }

        return response;
    } catch (error) {
        console.error('Error adding vehicle:', error);
        throw error;
    }
}

/**
 * Update vehicle information
 */
async function updateFleetVehicleInfo(fleetId, updates) {
    try {
        const response = await updateFleetVehicle(fleetId, updates);

        if (!response.success) {
            throw new Error(response.message || 'Failed to update vehicle');
        }

        return response;
    } catch (error) {
        console.error('Error updating vehicle:', error);
        throw error;
    }
}

/**
 * Display fleet vehicles in a table
 */
function displayFleetTable(vehicles, containerId = 'fleetTable') {
    const container = document.getElementById(containerId);
    if (!container) {
        console.error('Container not found:', containerId);
        return;
    }

    if (!vehicles || vehicles.length === 0) {
        container.innerHTML = '<tr><td colspan="7">No vehicles in fleet</td></tr>';
        return;
    }

    container.innerHTML = vehicles.map(vehicle => `
        <tr>
            <td>${vehicle.fleet_id}</td>
            <td>${vehicle.vehicle_type}</td>
            <td>${vehicle.current_hub_location || 'N/A'}</td>
            <td>${vehicle.weight_capacity_kg} kg</td>
            <td>${vehicle.volume_capacity_cubic} m³</td>
            <td>
                <span class="badge ${vehicle.status === 'available' ? 'bg-success' : 'bg-warning'}">
                    ${vehicle.status}
                </span>
            </td>
            <td>
                <button class="btn btn-sm btn-info" onclick="editFleetVehicle('${vehicle.fleet_id}')">
                    <i class="fa-solid fa-edit"></i> Edit
                </button>
                <button class="btn btn-sm btn-warning" onclick="changeVehicleStatus('${vehicle.fleet_id}')">
                    <i class="fa-solid fa-toggle-on"></i> Toggle
                </button>
            </td>
        </tr>
    `).join('');
}

/**
 * Display fleet vehicles as cards
 */
function displayFleetCards(vehicles, containerId = 'fleetCards') {
    const container = document.getElementById(containerId);
    if (!container) {
        console.error('Container not found:', containerId);
        return;
    }

    if (!vehicles || vehicles.length === 0) {
        container.innerHTML = '<p>No vehicles in fleet</p>';
        return;
    }

    container.innerHTML = vehicles.map(vehicle => `
        <div class="fleet-card">
            <div class="fleet-header">
                <h4>${vehicle.vehicle_type}</h4>
                <span class="badge ${vehicle.status === 'available' ? 'bg-success' : 'bg-warning'}">
                    ${vehicle.status}
                </span>
            </div>
            <div class="fleet-body">
                <p><strong>Current Hub:</strong> ${vehicle.current_hub_location || 'N/A'}</p>
                <p><strong>Next Destination:</strong> ${vehicle.next_destination || 'N/A'}</p>
                <p><strong>Weight Capacity:</strong> ${vehicle.weight_capacity_kg} kg</p>
                <p><strong>Volume Capacity:</strong> ${vehicle.volume_capacity_cubic} m³</p>
                <p><strong>Last Updated:</strong> ${formatDate(vehicle.last_updated_at)}</p>
            </div>
            <div class="fleet-footer">
                <button class="btn btn-sm btn-primary" onclick="editFleetVehicle('${vehicle.fleet_id}')">
                    Edit
                </button>
                <button class="btn btn-sm btn-warning" onclick="changeVehicleStatus('${vehicle.fleet_id}')">
                    Toggle Status
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
        return date.toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric'
        });
    } catch (e) {
        return dateString;
    }
}

/**
 * Edit fleet vehicle
 */
async function editFleetVehicle(fleetId) {
    try {
        const response = await getFleetVehicle(fleetId);
        
        if (!response.success || !response.vehicle) {
            throw new Error("Vehicle not found");
        }

        const vehicle = response.vehicle;

        // Show edit modal (implement with your modal system)
        const editForm = document.getElementById('editFleetForm');
        if (editForm) {
            // Populate form
            document.getElementById('editFleetId').value = fleetId;
            document.getElementById('editVehicleType').value = vehicle.vehicle_type;
            if (document.getElementById('editVehiclePlate')) {
                document.getElementById('editVehiclePlate').value = vehicle.fleet_id || '';
            }
            if (document.getElementById('editCurrentHub')) {
                document.getElementById('editCurrentHub').value = vehicle.current_hub_location || '';
            }
            document.getElementById('editWeightCapacity').value = vehicle.weight_capacity_kg;
            document.getElementById('editVolumeCapacity').value = vehicle.volume_capacity_cubic;

            // Show modal
            const modal = new bootstrap.Modal(document.getElementById('editFleetModal'));
            modal.show();
        }
    } catch (error) {
        alert("Failed to load vehicle: " + error.message);
    }
}

/**
 * Change vehicle status (active/inactive)
 */
async function changeVehicleStatus(fleetId) {
    try {
        const response = await getFleetVehicle(fleetId);
        if (!response.success) {
            throw new Error("Vehicle not found");
        }

        const vehicle = response.vehicle;
        const newStatus = vehicle.status === 'available' ? 'maintenance' : 'available';

        const updateResponse = await updateFleetVehicleInfo(fleetId, {
            operational_status: newStatus
        });

        if (updateResponse.success) {
            alert(`Vehicle status changed to: ${newStatus}`);
            // Reload fleet
            await loadAndDisplayFleet();
        }
    } catch (error) {
        alert("Failed to update vehicle status: " + error.message);
    }
}

/**
 * Submit edited fleet vehicle
 */
async function submitEditFleetVehicle(event) {
    if (event) {
        event.preventDefault();
    }

    try {
        const fleetId = document.getElementById('editFleetId')?.value;
        if (!fleetId) {
            throw new Error("Fleet ID not found");
        }

        const updates = {
            vehicle_type: document.getElementById('editVehicleType')?.value,
            weight_capacity_kg: document.getElementById('editWeightCapacity')?.value,
            volume_capacity_cubic: document.getElementById('editVolumeCapacity')?.value,
            current_hub_location: document.getElementById('editCurrentHub')?.value
        };

        const response = await updateFleetVehicleInfo(fleetId, updates);

        if (response.success) {
            alert("Vehicle updated successfully");
            // Close modal
            const modal = bootstrap.Modal.getInstance(document.getElementById('editFleetModal'));
            if (modal) modal.hide();
            // Reload fleet
            await loadAndDisplayFleet();
        }
    } catch (error) {
        alert("Failed to update vehicle: " + error.message);
    }
}

/**
 * Submit new vehicle form
 */
async function submitNewVehicle(event) {
    if (event) {
        event.preventDefault();
    }

    try {
        const vehicleData = {
            vehicleType: document.getElementById('newVehicleType')?.value,
            fleetId: document.getElementById('newFleetId')?.value,
            vehiclePlate: document.getElementById('newVehiclePlate')?.value,
            weightCapacity: document.getElementById('newWeightCapacity')?.value,
            volumeCapacity: document.getElementById('newVolumeCapacity')?.value,
            currentHubLocation: document.getElementById('newCurrentHub')?.value || document.getElementById('newHubLocation')?.value
        };

        // Validate
        if (!vehicleData.vehicleType || !vehicleData.weightCapacity || !vehicleData.currentHubLocation) {
            alert("Please fill in all required fields");
            return;
        }

        const submitBtn = event?.target?.querySelector('button[type="submit"]');
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.textContent = 'Adding...';
        }

        const response = await addVehicleToFleet(vehicleData);

        if (response.success) {
            alert("Vehicle added successfully!");
            if (event) {
                event.target.reset();
            }
            // Reload fleet
            await loadAndDisplayFleet();
        }
    } catch (error) {
        alert("Failed to add vehicle: " + error.message);
    } finally {
        const submitBtn = event?.target?.querySelector('button[type="submit"]');
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.textContent = 'Add Vehicle';
        }
    }
}

/**
 * Load and display fleet
 */
async function loadAndDisplayFleet() {
    try {
        const vehicles = await loadFleetVehicles();
        
        // Display in table if available
        if (document.getElementById('fleetTable')) {
            displayFleetTable(vehicles);
        }
        
        // Display as cards if available
        if (document.getElementById('fleetCards')) {
            displayFleetCards(vehicles);
        }
    } catch (error) {
        alert("Failed to load fleet: " + error.message);
        console.error(error);
    }
}

/**
 * Display fleet statistics
 */
async function displayFleetStats() {
    try {
        const vehicles = await loadFleetVehicles();
        
        const stats = {
            totalVehicles: vehicles.length,
            activeVehicles: vehicles.filter(v => v.status === 'available').length,
            totalWeightCapacity: vehicles.reduce((sum, v) => sum + (v.weight_capacity_kg || 0), 0),
            totalVolumeCapacity: vehicles.reduce((sum, v) => sum + (v.volume_capacity_cubic || 0), 0)
        };

        const statsContainer = document.getElementById('fleetStats');
        if (statsContainer) {
            statsContainer.innerHTML = `
                <div class="stat">
                    <h5>${stats.totalVehicles}</h5>
                    <p>Total Vehicles</p>
                </div>
                <div class="stat">
                    <h5>${stats.activeVehicles}</h5>
                    <p>Active Vehicles</p>
                </div>
                <div class="stat">
                    <h5>${stats.totalWeightCapacity.toLocaleString()} kg</h5>
                    <p>Total Weight Capacity</p>
                </div>
                <div class="stat">
                    <h5>${stats.totalVolumeCapacity.toLocaleString()} m³</h5>
                    <p>Total Volume Capacity</p>
                </div>
            `;
        }

        return stats;
    } catch (error) {
        console.error('Error displaying fleet stats:', error);
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    // Check if user is logged in (typically admin for fleet)
    const user = getCurrentUser();
    
    if (user && user.role === 'admin') {
        // Load fleet if on fleet page
        if (document.getElementById('fleetTable') || document.getElementById('fleetCards')) {
            loadAndDisplayFleet();
        }

        // Display stats if available
        if (document.getElementById('fleetStats')) {
            displayFleetStats();
        }

        // Attach form handlers
        const newVehicleForm = document.getElementById('newVehicleForm');
        if (newVehicleForm) {
            newVehicleForm.addEventListener('submit', submitNewVehicle);
        }

        const editFleetForm = document.getElementById('editFleetForm');
        if (editFleetForm) {
            editFleetForm.addEventListener('submit', submitEditFleetVehicle);
        }
    }
});
