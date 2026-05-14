<?php
/**
 * Updated Profile Management JavaScript Helpers
 * Handles profile display and updates via backend API
 * This file should be included in profile.html
 */
?>

<script>
// ==========================================
// PROFILE API INTEGRATION
// ==========================================

/**
 * Get current logged-in user from localStorage session
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
 * Loads current user and validates access
 * @returns {boolean} - true if user is valid customer, false otherwise
 */
function loadCurrentUser() {
    const currentUser = getCurrentUser();

    if (!currentUser) {
        alert("Please log in first.");
        window.location.href = "login.html";
        return false;
    }

    if (currentUser.role !== 'customer') {
        alert("This page is for customers only.");
        window.location.href = "login.html";
        return false;
    }

    return true;
}

/**
 * Fetch user profile from database
 * @param {number} userId - User ID
 * @returns {Promise<object>} - User profile data
 */
async function fetchUserProfile(userId) {
    try {
        const response = await apiGet(`users.php?user_id=${userId}`);
        
        if (!response.success) {
            throw new Error(response.message || 'Failed to fetch profile');
        }

        return response.user;
    } catch (error) {
        console.error('Error fetching profile:', error);
        alert("Could not load profile: " + error.message);
        return null;
    }
}

/**
 * Update user profile in database
 * @param {number} userId - User ID
 * @param {object} data - Profile data to update
 * @returns {Promise<object>} - API response
 */
async function updateUserProfile(userId, data) {
    try {
        const response = await apiPut('users.php', {
            user_id: userId,
            full_name: data.fullName,
            phone_number: data.phone,
            home_address: data.address
        });

        if (!response.success) {
            throw new Error(response.message || 'Failed to update profile');
        }

        // Update localStorage with new data
        const user = getCurrentUser();
        const updatedUser = {
            ...user,
            fullName: data.fullName,
            email: user.email, // Email is read-only
        };
        localStorage.setItem('cargoConnectCurrentUser', JSON.stringify(updatedUser));

        return response;
    } catch (error) {
        console.error('Error updating profile:', error);
        throw error;
    }
}

/**
 * Displays profile information on the page
 * @param {object} profile - Profile data from API
 */
function displayProfile(profile) {
    const user = getCurrentUser();

    if (!profile) {
        console.error('No profile data to display');
        return;
    }

    // Update display elements
    const fullName = profile.full_name || user.fullName || 'Customer';
    const email = profile.email_address || user.email || 'No email';
    const phone = profile.phone_number || 'Not provided';
    const address = profile.home_address || 'Not provided';

    // Avatar initials
    const initials = getInitials(fullName);
    const avatarEl = document.getElementById('profileAvatar');
    if (avatarEl) avatarEl.textContent = initials;

    // Profile header
    const nameEl = document.getElementById('profileName');
    if (nameEl) nameEl.textContent = fullName;
    
    const emailEl = document.getElementById('profileEmail');
    if (emailEl) emailEl.textContent = email;

    // Profile details
    const detailsMap = {
        'fullNameDisplay': fullName,
        'emailDisplay': email,
        'phoneDisplay': phone,
        'addressDisplay': address
    };

    Object.entries(detailsMap).forEach(([id, value]) => {
        const el = document.getElementById(id);
        if (el) el.textContent = value;
    });

    // Form fields (for editing)
    const formMap = {
        'editName': fullName,
        'editEmail': email,
        'editPhone': phone,
        'editAddress': address
    };

    Object.entries(formMap).forEach(([id, value]) => {
        const el = document.getElementById(id);
        if (el) {
            if (id === 'editEmail') {
                el.disabled = true; // Email should be read-only
            }
            el.value = value;
        }
    });
}

/**
 * Get initials from a name
 * @param {string} name - Full name
 * @returns {string} - Initials (e.g., "JD" for "John Doe")
 */
function getInitials(name) {
    const parts = String(name || "Customer").trim().split(/\s+/);
    const first = parts[0] ? parts[0][0].toUpperCase() : 'C';
    const second = parts.length > 1 ? parts[1][0].toUpperCase() : 'C';
    return first + second;
}

/**
 * Validate profile form data
 * @param {object} data - Form data
 * @returns {string|null} - Error message or null if valid
 */
function validateProfileForm(data) {
    if (!data.fullName || !data.fullName.trim()) {
        return "Full name is required";
    }

    if (!data.phone || !data.phone.trim()) {
        return "Phone number is required";
    }

    if (!/^09\d{9}$/.test(data.phone)) {
        return "Please enter a valid PH mobile number (09xxxxxxxxx)";
    }

    if (!data.address || !data.address.trim()) {
        return "Address is required";
    }

    return null;
}

/**
 * Save profile changes to database
 */
async function saveProfileChanges() {
    try {
        const user = getCurrentUser();
        
        if (!user || !user.id) {
            alert("User not found. Please log in again.");
            window.location.href = "login.html";
            return;
        }

        const formData = {
            fullName: document.getElementById('editName').value.trim(),
            phone: document.getElementById('editPhone').value.trim(),
            address: document.getElementById('editAddress').value.trim()
        };

        // Validate
        const error = validateProfileForm(formData);
        if (error) {
            alert(error);
            return;
        }

        // Show loading state
        const saveBtn = document.getElementById('saveProfileBtn');
        if (saveBtn) {
            saveBtn.disabled = true;
            saveBtn.textContent = 'Saving...';
        }

        // Update profile
        const response = await updateUserProfile(user.id, formData);

        alert("Profile updated successfully!");

        // Reload profile display
        const profile = await fetchUserProfile(user.id);
        displayProfile(profile);

    } catch (error) {
        alert("Failed to save profile: " + error.message);
    } finally {
        const saveBtn = document.getElementById('saveProfileBtn');
        if (saveBtn) {
            saveBtn.disabled = false;
            saveBtn.textContent = 'Save Profile';
        }
    }
}

/**
 * Initialize profile page on load
 */
async function initializeProfile() {
    // Validate user is logged in
    const user = getCurrentUser();
    if (!user || user.role !== 'customer') {
        alert("Please log in as a customer");
        window.location.href = "login.html";
        return;
    }

    try {
        // Fetch and display profile
        const profile = await fetchUserProfile(user.id);
        if (profile) {
            displayProfile(profile);
        } else {
            // Fallback to localStorage if API fails
            displayProfile({
                full_name: user.fullName,
                email_address: user.email,
                phone_number: '',
                home_address: ''
            });
        }
    } catch (error) {
        console.error('Failed to initialize profile:', error);
        alert("Error loading profile. Some features may not work.");
    }
}

/**
 * Logout current user
 */
function logoutUser() {
    if (confirm("Are you sure you want to log out?")) {
        localStorage.removeItem('cargoConnectCurrentUser');
        window.location.href = "login.html";
    }
}

// Initialize profile on page load
document.addEventListener('DOMContentLoaded', initializeProfile);

// ==========================================
// END PROFILE API INTEGRATION
// ==========================================
</script>
