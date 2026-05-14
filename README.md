# CargoConnect Backend API

This is the backend API for the CargoConnect cargo management system. It provides RESTful endpoints to interact with the MySQL database via phpMyAdmin.

## Setup Instructions

### 1. Database Setup
- Import `../database/schema.sql` into phpMyAdmin to create the database structure
- The database includes tables, stored procedures, triggers, and initial admin user

### 2. Web Server Setup
- Install XAMPP/WAMP or any PHP server
- Place the entire project in the web server's document root
- Ensure PHP has PDO extension enabled
- Update database credentials in `config.php` if needed

### 3. CORS Configuration
- For development, the API allows all origins (`*`)
- In production, restrict to your domain only

### 4. Testing the API
- Start your web server
- Test endpoints at: `http://localhost/backend/api/login.php`
- Use tools like Postman or browser dev tools

## API Endpoints

### Authentication
- `POST /backend/api/login.php` - User login
- `POST /backend/api/register.php` - User registration

### Shipments
- `GET /backend/api/shipments.php?customer_id=X` - Get user shipments
- `GET /backend/api/shipments.php?admin=true` - Get all shipments (admin)
- `POST /backend/api/shipments.php` - Create new shipment

### Tracking
- `GET /backend/api/tracking.php?shipment_id=X` - Get shipment tracking

### Fleet Management
- `GET /backend/api/fleet.php` - Get all fleet vehicles
- `POST /backend/api/fleet.php` - Update fleet status

### Admin Operations
- `POST /backend/api/admin.php` - Admin actions (confirm, dispatch, etc.)

### User Profile
- `GET /backend/api/users.php?user_id=X` - Get user profile
- `PUT /backend/api/users.php` - Update user profile

## Integration with Frontend

The `auth.js` file now uses these API endpoints:

```javascript
// Login example
const result = await apiCall('login.php', {
    email: 'user@example.com',
    password: 'password123'
});

// Registration example
const result = await apiCall('register.php', {
    full_name: 'John Doe',
    email: 'john@example.com',
    password: 'securepass'
});
```

## Security Notes

- Passwords are hashed using bcrypt in the database
- Input validation on both frontend and backend
- Use HTTPS in production
- Implement proper session management for production use

## API Endpoints

### Authentication

#### POST /backend/api/login.php
Authenticate a user.
- **Request Body:**
  ```json
  {
    "email": "user@example.com",
    "password": "password123"
  }
  ```
- **Response:**
  ```json
  {
    "success": true,
    "message": "Login successful",
    "user": {
      "user_id": 1,
      "full_name": "John Doe",
      "email_address": "user@example.com",
      "user_role": "customer",
      "account_status": "active"
    }
  }
  ```

#### POST /backend/api/register.php
Register a new customer user.
- **Request Body:**
  ```json
  {
    "full_name": "John Doe",
    "email": "user@example.com",
    "password": "password123",
    "phone": "+1234567890",
    "address": "123 Main St"
  }
  ```
- **Response:**
  ```json
  {
    "success": true,
    "message": "Registration successful",
    "user_id": 1
  }
  ```

### Shipments

#### GET /backend/api/shipments.php?customer_id=1
Get shipments for a specific customer.
- **Response:**
  ```json
  {
    "success": true,
    "shipments": [...]
  }
  ```

#### GET /backend/api/shipments.php?admin=true
Get all shipments (admin only).
- **Response:**
  ```json
  {
    "success": true,
    "shipments": [...]
  }
  ```

#### POST /backend/api/shipments.php
Create a new shipment.
- **Request Body:** (See stored procedure parameters)
- **Response:**
  ```json
  {
    "success": true,
    "message": "Shipment created successfully",
    "shipment_id": "CC1234",
    "total_amount": 500.00,
    "invoice_number": "INV-20231201-12345"
  }
  ```

### Tracking

#### GET /backend/api/tracking.php?shipment_id=CC1234
Get tracking history for a shipment.
- **Response:**
  ```json
  {
    "success": true,
    "tracking": [...]
  }
  ```

### Fleet Management

#### GET /backend/api/fleet.php
Get all fleet vehicles.
- **Response:**
  ```json
  {
    "success": true,
    "fleet": [...]
  }
  ```

#### POST /backend/api/fleet.php
Update fleet status.
- **Request Body:**
  ```json
  {
    "fleet_id": "VHC-1001",
    "action": "assign",
    "admin_name": "Admin User",
    "additional_data": {
      "shipment_id": "CC1234"
    }
  }
  ```

### Admin Operations

#### POST /backend/api/admin.php
Perform admin actions like confirming shipments.
- **Request Body:**
  ```json
  {
    "action": "confirm_shipment",
    "shipment_id": "CC1234",
    "admin_name": "Admin User"
  }
  ```

### User Profile

#### GET /backend/api/users.php?user_id=1
Get user profile.
- **Response:**
  ```json
  {
    "success": true,
    "user": {...}
  }
  ```

#### PUT /backend/api/users.php
Update user profile.
- **Request Body:**
  ```json
  {
    "user_id": 1,
    "full_name": "Updated Name",
    "phone_number": "+1234567890"
  }
  ```

## Security Notes

- In production, implement proper authentication (JWT tokens).
- Use HTTPS for all requests.
- Validate and sanitize all inputs.
- Implement rate limiting.
- Log security events.

## Error Handling

All endpoints return JSON responses with a `success` boolean and a `message` string. Check `success` to determine if the operation was successful.