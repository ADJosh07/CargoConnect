/**
 * Profile page API integration.
 * Persists profile updates to MySQL through backend/api/users.php and loads
 * booking history from backend/api/shipments.php with localStorage as a fallback.
 */

const PROFILE_API_BASE = '../backend/api';
const PROFILE_CURRENT_USER_KEY = 'cargoConnectCurrentUser';

let profileCurrentUser = null;
let profileBookings = [];

function profileSafeParseStorage(key) {
    try {
        const item = localStorage.getItem(key);
        return item ? JSON.parse(item) : null;
    } catch {
        return null;
    }
}

function getCurrentUser() {
    return profileSafeParseStorage(PROFILE_CURRENT_USER_KEY);
}

function profileNormalize(value) {
    return String(value || '').trim().toLowerCase();
}

function esc(value) {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

function money(value) {
    return 'PHP ' + Number(value || 0).toLocaleString();
}

async function profileApiCall(endpoint, method = 'GET', data = null) {
    const options = {
        method,
        headers: { 'Content-Type': 'application/json' }
    };

    if (data) {
        options.body = JSON.stringify(data);
    }

    const response = await fetch(`${PROFILE_API_BASE}/${endpoint}`, options);
    const contentType = response.headers.get('content-type') || '';
    const payload = contentType.includes('application/json')
        ? await response.json()
        : { success: false, message: await response.text() };

    if (!response.ok || payload.success === false) {
        throw new Error(payload.message || `HTTP ${response.status}`);
    }

    return payload;
}

function showValidation(message, type = 'error') {
    const card = document.getElementById('profileValidationCard');
    if (!card) {
        alert(message);
        return;
    }

    card.className = 'validation-card ' + type;
    card.innerHTML = esc(message);
    card.style.display = 'block';

    setTimeout(() => {
        card.style.display = 'none';
    }, 4500);
}

function getInitials(name) {
    const parts = String(name || 'Cargo Customer').trim().split(/\s+/);
    const first = parts[0] ? parts[0][0] : 'C';
    const second = parts.length > 1 ? parts[1][0] : 'C';
    return (first + second).toUpperCase();
}

function normalizeProfile(apiUser) {
    return {
        ...profileCurrentUser,
        id: apiUser.user_id,
        customerId: apiUser.customer_id,
        fullName: apiUser.full_name,
        email: apiUser.email_address,
        role: apiUser.user_role,
        contactNumber: apiUser.phone_number || '',
        completeAddress: apiUser.home_address || ''
    };
}

async function fetchUserProfile(userId) {
    const result = await profileApiCall(`users.php?user_id=${encodeURIComponent(userId)}`);
    return result.user;
}

async function updateUserProfile(userId, data) {
    return profileApiCall('users.php', 'PUT', {
        user_id: userId,
        full_name: data.fullName,
        phone_number: data.contactNumber,
        home_address: data.completeAddress
    });
}

function renderProfile() {
    const user = profileCurrentUser || getCurrentUser() || {};

    const mappings = {
        avatarInitials: getInitials(user.fullName),
        displayName: user.fullName || 'Customer',
        displayEmail: user.email || '---',
        infoName: user.fullName || '---',
        infoEmail: user.email || '---',
        infoContact: user.contactNumber || '---',
        infoAddress: user.completeAddress || '---'
    };

    Object.entries(mappings).forEach(([id, value]) => {
        const el = document.getElementById(id);
        if (el) el.innerText = value;
    });

    const formMappings = {
        editName: user.fullName || '',
        editEmail: user.email || '',
        editContact: user.contactNumber || '',
        editAddress: user.completeAddress || ''
    };

    Object.entries(formMappings).forEach(([id, value]) => {
        const el = document.getElementById(id);
        if (el) el.value = value;
    });

    const emailInput = document.getElementById('editEmail');
    if (emailInput) emailInput.disabled = true;
}

function normalizeShipmentRow(row) {
    return {
        key: row.shipment_id,
        data: {
            id: row.shipment_id,
            origin: row.origin_location,
            destination: row.destination_location,
            receiver: row.receiver_name,
            status: row.status || row.shipment_status,
            service: row.service || row.service_type,
            paymentStatus: row.payment_status,
            paymentAmount: row.total_amount,
            estimatedCost: row.total_amount,
            createdAt: row.booking_date,
            invoiceNumber: row.invoice_number
        }
    };
}

async function loadDatabaseBookings() {
    if (!profileCurrentUser || !profileCurrentUser.id) return [];

    const result = await profileApiCall(`shipments.php?customer_id=${encodeURIComponent(profileCurrentUser.id)}`);
    return (result.shipments || []).map(normalizeShipmentRow);
}

function belongsToCurrentUser(data) {
    if (!profileCurrentUser || !data) return false;

    const userEmail = profileNormalize(profileCurrentUser.email);
    const bookingEmail = profileNormalize(data.customerEmail);
    const userId = String(profileCurrentUser.id || '');
    const bookingUserId = String(data.customerUserId || '');

    if (userEmail && bookingEmail && userEmail === bookingEmail) return true;
    if (userId && bookingUserId && userId === bookingUserId) return true;

    const sameName =
        profileNormalize(data.customerAccountName) === profileNormalize(profileCurrentUser.fullName) ||
        profileNormalize(data.sender) === profileNormalize(profileCurrentUser.fullName);

    const sameContact =
        String(data.contact || '').trim() === String(profileCurrentUser.contactNumber || '').trim();

    return sameName && sameContact;
}

function loadLocalBookings() {
    const localBookings = [];

    for (let i = 0; i < localStorage.length; i++) {
        const key = localStorage.key(i);
        if (!key || !key.startsWith('CC')) continue;

        const data = profileSafeParseStorage(key);
        if (!data || !data.id) continue;

        if (belongsToCurrentUser(data)) {
            localBookings.push({ key, data });
        }
    }

    return localBookings;
}

async function loadBookings() {
    try {
        profileBookings = await loadDatabaseBookings();
    } catch (error) {
        console.error('Database booking load failed:', error);
        profileBookings = loadLocalBookings();
        showValidation('Could not load database bookings. Showing local cached bookings only.', 'error');
    }

    profileBookings.sort((a, b) => {
        const aTime = new Date(a.data.createdAt || a.data.timestamp || 0).getTime();
        const bTime = new Date(b.data.createdAt || b.data.timestamp || 0).getTime();
        return bTime - aTime;
    });
}

function renderStats() {
    const total = profileBookings.length;
    const delivered = profileBookings.filter(item => item.data.status === 'Delivered').length;
    const active = total - delivered;
    const totalPaid = profileBookings.reduce((sum, item) => {
        return sum + Number(item.data.paymentAmount || item.data.estimatedCost || 0);
    }, 0);

    const mappings = {
        totalBookings: total,
        activeBookings: active,
        deliveredBookings: delivered,
        totalPaid: money(totalPaid)
    };

    Object.entries(mappings).forEach(([id, value]) => {
        const el = document.getElementById(id);
        if (el) el.innerText = value;
    });
}

function getStatusBadge(status) {
    const value = status || 'Unknown';

    if (value === 'Delivered') {
        return `<span class="badge-status badge-delivered">${esc(value)}</span>`;
    }

    if (value === 'Pending Confirmation' || value === 'pending_confirmation') {
        return `<span class="badge-status badge-pending">${esc(value)}</span>`;
    }

    return `<span class="badge-status">${esc(value)}</span>`;
}

function renderHistory() {
    const tbody = document.getElementById('historyTableBody');
    if (!tbody) return;

    const searchEl = document.getElementById('historySearch');
    const filterEl = document.getElementById('statusFilter');
    const search = profileNormalize(searchEl ? searchEl.value : '');
    const filter = filterEl ? filterEl.value : 'all';

    const filtered = profileBookings.filter(item => {
        const data = item.data;
        const haystack = profileNormalize([
            data.id,
            data.origin,
            data.destination,
            data.receiver,
            data.status,
            data.service
        ].join(' '));

        const matchesSearch = !search || haystack.includes(search);
        let matchesFilter = true;

        if (filter === 'active') {
            matchesFilter = data.status !== 'Delivered';
        } else if (filter !== 'all') {
            matchesFilter = data.status === filter;
        }

        return matchesSearch && matchesFilter;
    });

    if (filtered.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="6">
                    <div class="empty-state">No bookings found for this profile.</div>
                </td>
            </tr>
        `;
        return;
    }

    tbody.innerHTML = filtered.map(item => {
        const data = item.data;
        const paymentAmount = data.paymentAmount || data.estimatedCost || 0;

        return `
            <tr>
                <td><span class="tracking-id">${esc(data.id || item.key)}</span></td>
                <td>${esc(data.origin || '---')} -> ${esc(data.destination || '---')}</td>
                <td>${esc(data.receiver || '---')}</td>
                <td>${getStatusBadge(data.status)}</td>
                <td>
                    <strong>${money(paymentAmount)}</strong><br>
                    <small class="text-muted">${esc(data.paymentStatus || 'paid')}</small>
                </td>
                <td>
                    <button class="btn btn-dark btn-sm fw-bold" onclick="openDetails('${esc(item.key)}')">Details</button>
                    <button class="btn btn-warning btn-sm fw-bold" onclick="openWaybill('${esc(data.id || item.key)}')">Waybill</button>
                </td>
            </tr>
        `;
    }).join('');
}

function openDetails(key) {
    localStorage.setItem('activeID', key);
    window.location.href = 'details.html';
}

function openWaybill(id) {
    window.open('waybill.html?id=' + encodeURIComponent(id), '_blank');
}

async function saveProfile() {
    const fullName = document.getElementById('editName')?.value.trim() || '';
    const contactNumber = document.getElementById('editContact')?.value.trim() || '';
    const completeAddress = document.getElementById('editAddress')?.value.trim() || '';

    if (!profileCurrentUser || !profileCurrentUser.id) {
        showValidation('User session not found. Please log in again.', 'error');
        window.location.href = 'login.html';
        return;
    }

    if (!fullName || !contactNumber || !completeAddress) {
        showValidation('Please fill in all profile fields.', 'error');
        return;
    }

    if (!/^09\d{9}$/.test(contactNumber)) {
        showValidation('Please enter a valid PH mobile number, like 09123456789.', 'error');
        return;
    }

    const button = document.querySelector('button[onclick="saveProfile()"]');
    if (button) {
        button.disabled = true;
        button.innerHTML = 'Saving...';
    }

    try {
        const result = await updateUserProfile(profileCurrentUser.id, {
            fullName,
            contactNumber,
            completeAddress
        });

        profileCurrentUser = normalizeProfile(result.user);
        localStorage.setItem(PROFILE_CURRENT_USER_KEY, JSON.stringify(profileCurrentUser));

        renderProfile();
        await loadBookings();
        renderStats();
        renderHistory();

        showValidation('Profile updated successfully.', 'success');
    } catch (error) {
        console.error('Profile save failed:', error);
        showValidation('Failed to save profile: ' + error.message, 'error');
    } finally {
        if (button) {
            button.disabled = false;
            button.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Save Profile';
        }
    }
}

function confirmLogout(event) {
    event.preventDefault();

    if (confirm('Are you sure you want to log out?')) {
        localStorage.removeItem(PROFILE_CURRENT_USER_KEY);
        window.location.href = 'index.html';
    }

    return false;
}

async function initializeProfilePage() {
    profileCurrentUser = getCurrentUser();

    if (!profileCurrentUser || (profileCurrentUser.role !== 'customer' && profileCurrentUser.role !== 'user')) {
        alert('Please log in as a customer first.');
        window.location.href = 'login.html';
        return;
    }

    try {
        const apiUser = await fetchUserProfile(profileCurrentUser.id);
        profileCurrentUser = normalizeProfile(apiUser);
        localStorage.setItem(PROFILE_CURRENT_USER_KEY, JSON.stringify(profileCurrentUser));
    } catch (error) {
        console.error('Profile load failed:', error);
        showValidation('Could not load database profile. Showing local session data only.', 'error');
    }

    renderProfile();
    await loadBookings();
    renderStats();
    renderHistory();
}

window.addEventListener('load', initializeProfilePage);
