// ==========================================
// AUTHENTICATION SYSTEM - FRONTEND INTEGRATION
// ==========================================
// This file handles user authentication by communicating with the backend API
// Instead of localStorage, it now uses the PHP backend for permanent storage
// ==========================================

const API_BASE_URL = '../backend/api'; // Base URL for API endpoints

/**
 * Makes an API call to the backend
 * @param {string} endpoint - API endpoint (e.g., 'login.php')
 * @param {object} data - Data to send in the request body
 * @returns {Promise} - Promise that resolves to the API response
 */
async function apiCall(endpoint, data) {
    try {
        const response = await fetch(`${API_BASE_URL}/${endpoint}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(data)
        });

        const result = await response.json();

        if (!result.success) {
            throw new Error(result.message || 'API call failed');
        }

        return result;
    } catch (error) {
        console.error('API Error:', error);
        throw error;
    }
}

/**
 * Handles user registration by calling the backend API
 * @param {Event} event - Form submit event
 */
async function registerUser(event) {
    event.preventDefault();

    const fullName = document.getElementById("regName").value.trim();
    const email = document.getElementById("regEmail").value.trim();
    const contactNumber = document.getElementById("regContact").value.trim();
    const completeAddress = document.getElementById("regAddress").value.trim();
    const password = document.getElementById("regPassword").value;
    const confirmPassword = document.getElementById("regConfirmPassword").value;

    // Client-side validation
    if (!fullName || !email || !contactNumber || !completeAddress || !password || !confirmPassword) {
        alert("Please fill in all fields.");
        return;
    }

    if (!/^09\d{9}$/.test(contactNumber)) {
        alert("Please enter a valid PH mobile number, like 09123456789.");
        return;
    }

    if (password.length < 6) {
        alert("Password must be at least 6 characters.");
        return;
    }

    if (password !== confirmPassword) {
        alert("Passwords do not match.");
        return;
    }

    try {
        // Call backend registration API
        const result = await apiCall('register.php', {
            full_name: fullName,
            email: email,
            phone: contactNumber,
            address: completeAddress,
            password: password
        });

        alert("Registration successful! You can now log in.");
        window.location.href = "login.html";

    } catch (error) {
        alert("Registration failed: " + error.message);
    }
}

/**
 * Handles user login by calling the backend API
 * @param {Event} event - Form submit event
 */
async function loginUser(event) {
    event.preventDefault();

    const usernameOrEmail = document.getElementById("username").value.trim();
    const password = document.getElementById("password").value;

    if (!usernameOrEmail || !password) {
        alert("Please enter both username/email and password.");
        return;
    }

    try {
        // Call backend login API
        const result = await apiCall('login.php', {
            email: usernameOrEmail, // Backend expects 'email' field
            password: password
        });

        // Store user session in localStorage (temporary client-side session)
        // This is separate from the permanent SQL storage
        localStorage.setItem('cargoConnectCurrentUser', JSON.stringify({
            id: result.user.user_id,
            fullName: result.user.full_name,
            email: result.user.email_address,
            role: result.user.user_role,
            loginAt: new Date().toISOString()
        }));

        alert("Login successful!");

        // Redirect based on user role
        if (result.user.user_role === 'admin') {
            window.location.href = "dashboard.html";
        } else {
            window.location.href = "home.html";
        }

    } catch (error) {
        alert("Login failed: " + error.message);
    }
}

/**
 * Gets the current logged-in user from localStorage session
 * @returns {object|null} - Current user object or null
 */
function getCurrentUser() {
    try {
        return JSON.parse(localStorage.getItem('cargoConnectCurrentUser'));
    } catch {
        return null;
    }
}

/**
 * Logs out the current user by clearing the session
 */
function logoutUser() {
    localStorage.removeItem('cargoConnectCurrentUser');
    window.location.href = "login.html";
}

// ==========================================
// EVENT LISTENERS - Attach to forms when DOM loads
// ==========================================
document.addEventListener('DOMContentLoaded', function() {
    const registerForm = document.getElementById("registerForm");
    const loginForm = document.getElementById("loginForm");

    if (registerForm) {
        registerForm.addEventListener("submit", registerUser);
    }

    if (loginForm) {
        loginForm.addEventListener("submit", loginUser);
    }
});

