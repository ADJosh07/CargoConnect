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
 * @param {string} method - HTTP method (POST, GET, PUT, DELETE)
 * @param {object} data - Data to send in the request body
 * @returns {Promise} - Promise that resolves to the API response
 */
async function apiCall(endpoint, method = 'POST', data = null) {
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

        const response = await fetch(`${API_BASE_URL}/${endpoint}`, options);

        // Check if response is ok
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }

        // Check content type to ensure we're getting JSON
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            const text = await response.text();
            console.error('Non-JSON response received:', text);
            throw new Error('Server returned non-JSON response. This usually means the API endpoint is not found or the server returned an error page.');
        }

        return await response.json();
    } catch (error) {
        console.error('API Error:', error);
        throw error;
    }
}

/**
 * Wrapper for POST requests
 */
async function apiPost(endpoint, data) {
    return apiCall(endpoint, 'POST', data);
}

/**
 * Wrapper for GET requests
 */
async function apiGet(endpoint) {
    return apiCall(endpoint, 'GET');
}

/**
 * Wrapper for PUT requests
 */
async function apiPut(endpoint, data) {
    return apiCall(endpoint, 'PUT', data);
}

/**
 * Gets current user profile from database via API
 */
async function fetchUserProfile(userId) {
    try {
const result = await apiGet(`users.php?user_id=${userId}`);
        return result.user || null;
    } catch (error) {
        console.error('Failed to fetch user profile:', error);
        return null;
    }
}

/**
 * Updates user profile in database via API
 */
async function updateUserProfile(userId, profileData) {
    try {
        const result = await apiPut('users.php', {
            user_id: userId,
            ...profileData
        });
        return result;
    } catch (error) {
        console.error('Failed to update user profile:', error);
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
const result = await apiPost('register.php', {
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
const result = await apiPost('login.php', {
            email: usernameOrEmail, // Backend expects 'email' field
            password: password
        });

        if (!result.success) {
            throw new Error(result.message || 'Login failed');
        }

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

