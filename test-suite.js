/**
 * CargoConnect System Testing Suite
 * Comprehensive tests for all APIs and frontend integration
 * 
 * Usage: Include this file in a test page or run tests via console
 */

// Configuration
const TEST_CONFIG = {
    baseUrl: 'http://localhost',
    apiBase: 'http://localhost/backend/api',
    testUser: {
        email: 'test@cargoconnect.com',
        password: 'Test@123456',
        role: 'customer'
    },
    testAdmin: {
        email: 'admin@cargoconnect.com',
        password: 'Admin@123456',
        role: 'admin'
    },
    verbose: true
};

// Test results tracker
const testResults = {
    total: 0,
    passed: 0,
    failed: 0,
    errors: [],
    details: []
};

// Logging utility
function log(message, type = 'info') {
    const timestamp = new Date().toLocaleTimeString();
    const prefix = {
        'info': '📝',
        'success': '✅',
        'error': '❌',
        'warning': '⚠️',
        'test': '🧪'
    }[type] || '•';
    
    console.log(`[${timestamp}] ${prefix} ${message}`);
}

// Test execution function
async function runTest(name, testFn) {
    testResults.total++;
    try {
        log(`Running: ${name}`, 'test');
        await testFn();
        testResults.passed++;
        testResults.details.push({ name, status: 'PASS' });
        log(`${name}`, 'success');
    } catch (error) {
        testResults.failed++;
        testResults.errors.push({ test: name, error: error.message });
        testResults.details.push({ name, status: 'FAIL', error: error.message });
        log(`${name}: ${error.message}`, 'error');
    }
}

// Assert utilities
function assert(condition, message) {
    if (!condition) throw new Error(message);
}

function assertEquals(actual, expected, message) {
    if (actual !== expected) {
        throw new Error(`${message || 'Assertion failed'}: expected ${expected}, got ${actual}`);
    }
}

function assertExists(value, message) {
    if (!value) throw new Error(message || 'Value does not exist');
}

function assertHasProperty(obj, prop, message) {
    if (!(prop in obj)) throw new Error(message || `Missing property: ${prop}`);
}

// ============================================
// TEST SUITE 1: API Connectivity Tests
// ============================================

async function testAPIConnectivity() {
    log('\n=== TEST SUITE 1: API Connectivity ===', 'info');
    
    await runTest('API Server responds to GET request', async () => {
        const response = await fetch(`${TEST_CONFIG.apiBase}/shipments.php`, {
            method: 'GET'
        });
        assertExists(response, 'No response from server');
        assert(response.ok || response.status === 200 || response.status === 400, 
            `Server returned ${response.status}`);
    });
    
    await runTest('API returns JSON content-type', async () => {
        const response = await fetch(`${TEST_CONFIG.apiBase}/shipments.php`, {
            method: 'GET'
        });
        const contentType = response.headers.get('content-type');
        assert(contentType && contentType.includes('application/json'), 
            `Wrong content-type: ${contentType}`);
    });
    
    await runTest('API has CORS headers', async () => {
        const response = await fetch(`${TEST_CONFIG.apiBase}/shipments.php`, {
            method: 'GET'
        });
        const corsHeader = response.headers.get('Access-Control-Allow-Origin');
        assertExists(corsHeader, 'Missing CORS header');
    });
}

// ============================================
// TEST SUITE 2: Shipments API Tests
// ============================================

async function testShipmentsAPI() {
    log('\n=== TEST SUITE 2: Shipments API ===', 'info');
    
    let testShipmentId = null;
    
    await runTest('GET /shipments.php - returns valid response', async () => {
        const response = await fetch(`${TEST_CONFIG.apiBase}/shipments.php`, {
            method: 'GET'
        });
        const data = await response.json();
        assertHasProperty(data, 'success', 'Response missing success property');
        assertHasProperty(data, 'message', 'Response missing message property');
    });
    
    await runTest('POST /shipments.php - creates shipment', async () => {
        const shipmentData = {
            customer_id: 1,
            origin_location: 'Manila, Philippines',
            destination_location: 'Cebu, Philippines',
            service_type: 'standard',
            booking_date: new Date().toISOString().split('T')[0]
        };
        
        const response = await fetch(`${TEST_CONFIG.apiBase}/shipments.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(shipmentData)
        });
        
        const data = await response.json();
        assertHasProperty(data, 'success', 'Response missing success property');
        
        if (data.success && data.shipment_id) {
            testShipmentId = data.shipment_id;
            log(`Created test shipment ID: ${testShipmentId}`, 'info');
        }
    });
    
    await runTest('GET /shipments.php?customer_id=1 - filters by customer', async () => {
        const response = await fetch(`${TEST_CONFIG.apiBase}/shipments.php?customer_id=1`, {
            method: 'GET'
        });
        const data = await response.json();
        assertHasProperty(data, 'success', 'Response missing success property');
        assertHasProperty(data, 'shipments', 'Response missing shipments array');
        assert(Array.isArray(data.shipments), 'Shipments is not an array');
    });
    
    if (testShipmentId) {
        await runTest('PUT /shipments.php - updates shipment status', async () => {
            const updateData = {
                shipment_id: testShipmentId,
                status: 'in-transit'
            };
            
            const response = await fetch(`${TEST_CONFIG.apiBase}/shipments.php`, {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(updateData)
            });
            
            const data = await response.json();
            assertHasProperty(data, 'success', 'Response missing success property');
        });
    }
}

// ============================================
// TEST SUITE 3: Tracking API Tests
// ============================================

async function testTrackingAPI() {
    log('\n=== TEST SUITE 3: Tracking API ===', 'info');
    
    await runTest('GET /tracking.php - returns valid response', async () => {
        const response = await fetch(`${TEST_CONFIG.apiBase}/tracking.php`, {
            method: 'GET'
        });
        const data = await response.json();
        assertHasProperty(data, 'success', 'Response missing success property');
    });
    
    await runTest('GET /tracking.php?shipment_id=1 - gets shipment tracking', async () => {
        const response = await fetch(`${TEST_CONFIG.apiBase}/tracking.php?shipment_id=1`, {
            method: 'GET'
        });
        const data = await response.json();
        assertHasProperty(data, 'success', 'Response missing success property');
    });
    
    await runTest('GET /tracking.php?customer_id=1 - gets customer tracking', async () => {
        const response = await fetch(`${TEST_CONFIG.apiBase}/tracking.php?customer_id=1`, {
            method: 'GET'
        });
        const data = await response.json();
        assertHasProperty(data, 'success', 'Response missing success property');
        assertHasProperty(data, 'tracking', 'Response missing tracking array');
    });
    
    await runTest('POST /tracking.php - updates tracking', async () => {
        const trackingData = {
            shipment_id: 1,
            current_location: 'Quezon City, Philippines',
            status: 'in-transit'
        };
        
        const response = await fetch(`${TEST_CONFIG.apiBase}/tracking.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(trackingData)
        });
        
        const data = await response.json();
        assertHasProperty(data, 'success', 'Response missing success property');
    });
}

// ============================================
// TEST SUITE 4: Fleet API Tests
// ============================================

async function testFleetAPI() {
    log('\n=== TEST SUITE 4: Fleet API ===', 'info');
    
    let testFleetId = null;
    
    await runTest('GET /fleet.php - returns valid response', async () => {
        const response = await fetch(`${TEST_CONFIG.apiBase}/fleet.php`, {
            method: 'GET'
        });
        const data = await response.json();
        assertHasProperty(data, 'success', 'Response missing success property');
    });
    
    await runTest('GET /fleet.php - returns fleet array', async () => {
        const response = await fetch(`${TEST_CONFIG.apiBase}/fleet.php`, {
            method: 'GET'
        });
        const data = await response.json();
        assertHasProperty(data, 'fleet', 'Response missing fleet array');
        assert(Array.isArray(data.fleet), 'Fleet is not an array');
    });
    
    await runTest('POST /fleet.php - creates vehicle', async () => {
        const vehicleData = {
            vehicle_type: 'Van',
            weight_capacity_kg: 2000,
            volume_capacity_cubic: 15,
            vehicle_plate: 'TEST-001'
        };
        
        const response = await fetch(`${TEST_CONFIG.apiBase}/fleet.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(vehicleData)
        });
        
        const data = await response.json();
        assertHasProperty(data, 'success', 'Response missing success property');
        
        if (data.success && data.fleet_id) {
            testFleetId = data.fleet_id;
            log(`Created test fleet ID: ${testFleetId}`, 'info');
        }
    });
    
    if (testFleetId) {
        await runTest('GET /fleet.php?fleet_id=X - gets single vehicle', async () => {
            const response = await fetch(`${TEST_CONFIG.apiBase}/fleet.php?fleet_id=${testFleetId}`, {
                method: 'GET'
            });
            const data = await response.json();
            assertHasProperty(data, 'success', 'Response missing success property');
            assertHasProperty(data, 'vehicle', 'Response missing vehicle property');
        });
        
        await runTest('PUT /fleet.php - updates vehicle', async () => {
            const updateData = {
                fleet_id: testFleetId,
                status: 'inactive'
            };
            
            const response = await fetch(`${TEST_CONFIG.apiBase}/fleet.php`, {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(updateData)
            });
            
            const data = await response.json();
            assertHasProperty(data, 'success', 'Response missing success property');
        });
    }
}

// ============================================
// TEST SUITE 5: Bookings API Tests
// ============================================

async function testBookingsAPI() {
    log('\n=== TEST SUITE 5: Bookings API ===', 'info');
    
    await runTest('GET /bookings.php - returns valid response', async () => {
        const response = await fetch(`${TEST_CONFIG.apiBase}/bookings.php`, {
            method: 'GET'
        });
        const data = await response.json();
        assertHasProperty(data, 'success', 'Response missing success property');
    });
    
    await runTest('GET /bookings.php?customer_id=1 - filters by customer', async () => {
        const response = await fetch(`${TEST_CONFIG.apiBase}/bookings.php?customer_id=1`, {
            method: 'GET'
        });
        const data = await response.json();
        assertHasProperty(data, 'success', 'Response missing success property');
        assertHasProperty(data, 'bookings', 'Response missing bookings array');
    });
}

// ============================================
// TEST SUITE 6: Error Handling Tests
// ============================================

async function testErrorHandling() {
    log('\n=== TEST SUITE 6: Error Handling ===', 'info');
    
    await runTest('Invalid JSON in POST request', async () => {
        const response = await fetch(`${TEST_CONFIG.apiBase}/shipments.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: 'invalid json'
        });
        // Should not throw, but handle gracefully
        const data = await response.json();
        assert(typeof data === 'object', 'Response is not valid JSON');
    });
    
    await runTest('Missing required fields in POST', async () => {
        const response = await fetch(`${TEST_CONFIG.apiBase}/shipments.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ customer_id: 1 }) // missing required fields
        });
        const data = await response.json();
        // Should return error response
        assert(typeof data.success !== 'undefined', 'Response missing success property');
    });
    
    await runTest('Invalid HTTP method returns proper response', async () => {
        const response = await fetch(`${TEST_CONFIG.apiBase}/shipments.php`, {
            method: 'DELETE'
        });
        // Should handle gracefully
        const data = await response.json();
        assert(typeof data === 'object', 'Response is not valid JSON');
    });
}

// ============================================
// TEST SUITE 7: Database Persistence Tests
// ============================================

async function testDatabasePersistence() {
    log('\n=== TEST SUITE 7: Database Persistence ===', 'info');
    
    await runTest('Create shipment and verify in database', async () => {
        const shipmentData = {
            customer_id: 1,
            origin_location: 'Test Origin',
            destination_location: 'Test Destination',
            service_type: 'express'
        };
        
        const createResponse = await fetch(`${TEST_CONFIG.apiBase}/shipments.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(shipmentData)
        });
        
        const createData = await createResponse.json();
        if (!createData.success) {
            throw new Error('Failed to create shipment');
        }
        
        // Verify it can be retrieved
        const getResponse = await fetch(`${TEST_CONFIG.apiBase}/shipments.php?customer_id=1`, {
            method: 'GET'
        });
        const getData = await getResponse.json();
        
        assert(getData.shipments && getData.shipments.length > 0, 
            'Shipment not found in database after creation');
    });
    
    await runTest('Update data and verify persistence', async () => {
        // First get a shipment
        const getResponse = await fetch(`${TEST_CONFIG.apiBase}/shipments.php?customer_id=1`, {
            method: 'GET'
        });
        const getData = await getResponse.json();
        
        if (!getData.shipments || getData.shipments.length === 0) {
            throw new Error('No shipments available for update test');
        }
        
        const shipmentId = getData.shipments[0].shipment_id;
        
        // Update it
        const updateResponse = await fetch(`${TEST_CONFIG.apiBase}/shipments.php`, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                shipment_id: shipmentId,
                status: 'delivered'
            })
        });
        
        const updateData = await updateResponse.json();
        assert(updateData.success, 'Failed to update shipment');
    });
}

// ============================================
// TEST SUITE 8: Frontend Integration Tests
// ============================================

async function testFrontendIntegration() {
    log('\n=== TEST SUITE 8: Frontend Integration ===', 'info');
    
    await runTest('getCurrentUser function exists', async () => {
        assert(typeof getCurrentUser === 'function', 'getCurrentUser is not defined');
    });
    
    await runTest('auth.js API functions exist', async () => {
        assert(typeof apiCall === 'function', 'apiCall is not defined');
        assert(typeof apiGet === 'function', 'apiGet is not defined');
        assert(typeof apiPost === 'function', 'apiPost is not defined');
    });
    
    await runTest('shipment-api.js functions exist', async () => {
        assert(typeof getShipments === 'function', 'getShipments is not defined');
        assert(typeof createShipment === 'function', 'createShipment is not defined');
        assert(typeof updateShipment === 'function', 'updateShipment is not defined');
    });
    
    await runTest('tracking-api.js functions exist', async () => {
        assert(typeof getShipmentTracking === 'function', 'getShipmentTracking is not defined');
        assert(typeof getCustomerTracking === 'function', 'getCustomerTracking is not defined');
    });
    
    await runTest('fleet-api.js functions exist', async () => {
        assert(typeof getAllFleet === 'function', 'getAllFleet is not defined');
        assert(typeof getFleetVehicle === 'function', 'getFleetVehicle is not defined');
        assert(typeof createFleetVehicle === 'function', 'createFleetVehicle is not defined');
    });
}

// ============================================
// Main Test Runner
// ============================================

async function runAllTests() {
    log('🚀 Starting CargoConnect System Test Suite', 'info');
    log(`Timestamp: ${new Date().toLocaleString()}`, 'info');
    log(`Server: ${TEST_CONFIG.apiBase}`, 'info');
    
    try {
        // Run all test suites
        await testAPIConnectivity();
        await testShipmentsAPI();
        await testTrackingAPI();
        await testFleetAPI();
        await testBookingsAPI();
        await testErrorHandling();
        await testDatabasePersistence();
        await testFrontendIntegration();
        
    } catch (error) {
        log(`Test suite error: ${error.message}`, 'error');
    }
    
    // Print summary
    printTestSummary();
}

function printTestSummary() {
    log('\n' + '='.repeat(60), 'info');
    log('TEST SUMMARY', 'info');
    log('='.repeat(60), 'info');
    
    const passRate = testResults.total > 0 
        ? ((testResults.passed / testResults.total) * 100).toFixed(2) 
        : 0;
    
    console.log(`
Total Tests: ${testResults.total}
Passed: ${testResults.passed} ✅
Failed: ${testResults.failed} ❌
Pass Rate: ${passRate}%

${testResults.failed > 0 ? '--- FAILED TESTS ---' : '✅ ALL TESTS PASSED!'}
`);
    
    if (testResults.errors.length > 0) {
        testResults.errors.forEach(err => {
            console.log(`❌ ${err.test}: ${err.error}`);
        });
    }
    
    log('='.repeat(60), 'info');
    log('Test Summary exported to testResults object', 'success');
}

// Export for use
window.testConfig = TEST_CONFIG;
window.testResults = testResults;
window.runAllTests = runAllTests;

// Auto-run on page load if ?autotest=true
if (new URL(window.location.href).searchParams.get('autotest') === 'true') {
    runAllTests();
}
