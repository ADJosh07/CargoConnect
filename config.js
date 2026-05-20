// Frontend API configuration
// Exposes a global `API_CONFIG` object so pages can include this script
// and use `API_CONFIG.API_BASE_URL` and `API_CONFIG.ENDPOINTS`.
window.API_CONFIG = window.API_CONFIG || {
    API_BASE_URL: '../backend/api',
    ENDPOINTS: {
        login: 'login.php',
        register: 'register.php',
        users: 'users.php',
        bookings: 'bookings.php',
        fleet: 'fleet.php',
        shipments: 'shipments.php',
        tracking: 'tracking.php'
    }
};
