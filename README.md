# CargoConnect Backend API

The backend is a PHP/PDO API for the MySQL database defined in `backend/database/schema.sql`.

## Setup

1. Import `backend/database/schema.sql` into MySQL/phpMyAdmin.
2. Update credentials in `backend/config.php` if your MySQL user differs from `root` with an empty password.
3. Serve the project through XAMPP/WAMP/Apache so frontend requests can reach `backend/api/*.php`.

## Endpoints

### Authentication

- `POST /backend/api/register.php`
- `POST /backend/api/login.php`

Registration creates linked rows in `users` and `customers`. Login returns the `users.user_id`; frontend code passes that ID back to profile and shipment endpoints.

### Users

- `GET /backend/api/users.php?user_id=1`
- `PUT /backend/api/users.php`

`PUT` body:

```json
{
  "user_id": 1,
  "full_name": "Juan Dela Cruz",
  "phone_number": "09123456789",
  "home_address": "Complete address"
}
```

Updates are written to both `users` and `customers`.

### Shipments

- `GET /backend/api/shipments.php`
- `GET /backend/api/shipments.php?customer_id=1`
- `POST /backend/api/shipments.php`
- `PUT /backend/api/shipments.php`

`customer_id` may be either `customers.customer_id` or the logged-in `users.user_id`; the API resolves it to the customer profile.

`POST` body:

```json
{
  "customer_id": 1,
  "receiver_name": "Receiver Name",
  "receiver_email": "",
  "receiver_phone": "09123456789",
  "receiver_address": "Delivery address",
  "origin": "Manila Port",
  "destination": "Cebu Port",
  "service_type": "Door-to-Door",
  "handling_type": "Fragile",
  "weight": 10,
  "volume": 1.5,
  "special_instructions": "",
  "payment_method": "GCash",
  "transaction_ref": "PAY-123456"
}
```

Creates `receivers`, `cargo`, `shipments`, `invoices`, `payments`, and initial `tracking` rows.

### Tracking

- `GET /backend/api/tracking.php?shipment_id=CC1234`
- `GET /backend/api/tracking.php?customer_id=1`
- `POST /backend/api/tracking.php`
- `PUT /backend/api/tracking.php`

`POST`/`PUT` body:

```json
{
  "shipment_id": "CC1234",
  "status": "In Transit",
  "current_location": "Manila Port",
  "event_notes": "Shipment departed origin hub."
}
```

### Fleet

- `GET /backend/api/fleet.php`
- `GET /backend/api/fleet.php?fleet_id=FLT-1001`
- `POST /backend/api/fleet.php`
- `PUT /backend/api/fleet.php`

Uses `operational_status` values from the schema: `available`, `assigned`, `dispatched`, `maintenance`.

### Admin Workflow

- `POST /backend/api/admin.php`
- `GET /backend/api/admin.php?resource=cancellations`

Supported `action` values:

- `confirm_shipment`
- `assign_fleet`
- `dispatch_fleet`
- `mark_in_transit`
- `mark_out_for_delivery`
- `mark_delivered`
- `request_cancellation`
- `resolve_cancellation`

Example:

```json
{
  "action": "dispatch_fleet",
  "fleet_id": "FLT-1234",
  "admin_name": "Admin User"
}
```

Dispatching a fleet automatically moves assigned shipments to `in_transit`. Marking a shipment delivered sets `actual_delivery_date` and releases the fleet when it has no more active shipments.

Cancellation requests are stored in `cancellation_requests`, status movement is stored in `tracking`, and every important operation is written to `audit_logs`.

### Bookings Compatibility

- `GET /backend/api/bookings.php`
- `GET /backend/api/bookings.php?customer_id=1`
- `PUT /backend/api/bookings.php`

There is no `bookings` table in the current schema. Booking records are represented by `shipments` and related invoice/payment/tracking tables. Use `POST /backend/api/shipments.php` to create new bookings.

## Error Format

All API endpoints return JSON:

```json
{
  "success": false,
  "message": "Error details"
}
```

## Verification Notes

PHP CLI was not available in the current shell, so PHP linting could not be run here. JavaScript syntax checks passed for `frontend/cargo-api.js` and the edited inline page scripts.
