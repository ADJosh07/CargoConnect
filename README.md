# CargoConnect Database

This folder contains the complete database schema for the CargoConnect cargo management system.

## Files

- `schema.sql` - Complete database schema including tables, stored procedures, triggers, and initial data

## Setup Instructions

1. **Open phpMyAdmin** in your web browser
2. **Create a new database** named `cargoconnect_db` (or update the name in the schema if needed)
3. **Import the schema.sql file**:
   - Go to the Import tab
   - Select the `schema.sql` file
   - Click Go to execute the SQL

## Database Components

### Tables
- `users` - User accounts for customers and admins
- `customers` - Customer profile information
- `receivers` - Delivery recipient information
- `fleet` - Fleet vehicles and their status
- `cargo` - Cargo details for shipments
- `shipments` - Main shipment records
- `tracking` - Shipment tracking events
- `invoices` - Invoice records
- `payments` - Payment transactions
- `audit_logs` - System audit trail
- `cancellation_requests` - Shipment cancellation requests

### Stored Procedures
- `sp_create_customer_user` - Creates new customer user accounts
- `sp_get_user_by_email` - Retrieves user by email for authentication
- `sp_create_receiver` - Creates receiver records
- `sp_create_shipment` - Creates complete shipments with all related records
- `sp_confirm_shipment` - Confirms shipments after payment
- `sp_assign_fleet` - Assigns fleet vehicles to shipments
- `sp_dispatch_fleet` - Dispatches fleet vehicles
- `sp_mark_out_for_delivery` - Marks shipments for final delivery
- `sp_mark_delivered` - Marks shipments as delivered
- `sp_request_cancellation` - Submits cancellation requests
- `sp_get_shipment_tracking` - Retrieves tracking history

### Triggers
- `trigger_shipments_after_update` - Logs shipment status changes
- `trigger_payments_after_insert` - Updates payment status after transactions
- `trigger_cancellation_requests_after_insert` - Handles cancellation requests
- `trigger_fleet_after_update` - Logs fleet status changes

### Initial Data
- Default admin user (admin@cargoconnect.com / admin123)
- Sample fleet vehicles across Philippine ports

## Notes

- All stored procedures include detailed comments explaining their functionality
- Triggers automatically maintain audit logs and update related records
- Foreign key constraints ensure data integrity
- Row versioning prevents concurrent update conflicts
- Passwords are hashed using bcrypt for security