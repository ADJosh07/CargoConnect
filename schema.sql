-- Active: 1778343016316@@127.0.0.1@3306@cargoconnect_db
-- CargoConnect Database Schema
-- This file contains the complete database structure including tables, stored procedures, triggers, and initial data.

CREATE DATABASE IF NOT EXISTS cargoconnect_db;
USE cargoconnect_db;

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS audit_logs;
DROP TABLE IF EXISTS cancellation_requests;
DROP TABLE IF EXISTS payments;
DROP TABLE IF EXISTS invoices;
DROP TABLE IF EXISTS tracking;
DROP TABLE IF EXISTS shipments;
DROP TABLE IF EXISTS cargo;
DROP TABLE IF EXISTS fleet;
DROP TABLE IF EXISTS receivers;
DROP TABLE IF EXISTS customers;
DROP TABLE IF EXISTS users;

SET FOREIGN_KEY_CHECKS = 1;

-- TABLE: users
-- System-level login accounts for both admins and customers
CREATE TABLE users (
    user_id          INT          AUTO_INCREMENT PRIMARY KEY,
    full_name        VARCHAR(150) NOT NULL,
    email_address    VARCHAR(150) NOT NULL UNIQUE,
    password_hash    VARCHAR(255) NOT NULL,
    user_role        ENUM('customer', 'admin') NOT NULL DEFAULT 'customer',
    account_status   ENUM('active', 'disabled')  NOT NULL DEFAULT 'active',
    phone_number     VARCHAR(50),
    home_address     VARCHAR(255),
    created_at       TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- TABLE: customers
-- Customer profiles linked to a user login account
-- user_id SET NULL so profile survives if login is removed
CREATE TABLE customers (
    customer_id    INT  AUTO_INCREMENT PRIMARY KEY,
    user_id INT,  full_name VARCHAR(150) NOT NULL,
    company_name   VARCHAR(150),
    email_address  VARCHAR(150) NOT NULL UNIQUE,
    phone_number   VARCHAR(50),
    home_address   VARCHAR(255),
    created_at     TIMESTAMP  DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- TABLE: receivers
-- Consignees / people or businesses receiving cargo
CREATE TABLE receivers (
    receiver_id       INT          AUTO_INCREMENT PRIMARY KEY,
    full_name         VARCHAR(150) NOT NULL,
    email_address     VARCHAR(150),
    phone_number      VARCHAR(50),
    delivery_address  VARCHAR(255),
    created_at        TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- TABLE: fleet
-- Fleet vehicles available for cargo transportation
CREATE TABLE fleet (
    fleet_id               VARCHAR(50)    PRIMARY KEY,
    vehicle_type           VARCHAR(50)    NOT NULL,
    weight_capacity_kg     DECIMAL(12,2)  NOT NULL,
    volume_capacity_cubic  DECIMAL(12,2)  DEFAULT 0,
    current_hub_location   VARCHAR(100)   NOT NULL,
    next_destination       VARCHAR(100),
    operational_status     ENUM( 'available','assigned','dispatched','maintenance' ) NOT NULL DEFAULT 'available',
    last_service_date      DATE,
    last_updated_at        TIMESTAMP      DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    row_version     BIGINT   NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- TABLE: cargo
-- Cargo details for shipments
CREATE TABLE cargo (
    cargo_id               INT            AUTO_INCREMENT PRIMARY KEY,
    cargo_description      TEXT           NOT NULL,
    weight_in_kg           DECIMAL(10,2)  NOT NULL,
    volume_in_cubic_meters DECIMAL(10,2)  DEFAULT 0,
    handling_type          ENUM(
                               'standard',
                               'fragile',
                               'perishable',
                               'hazardous'
                           ) NOT NULL DEFAULT 'standard',
    declared_value         DECIMAL(12,2)  DEFAULT 0,
    quantity               INT            DEFAULT 1,
    created_at             TIMESTAMP      DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- TABLE: shipments
-- Main shipment records
CREATE TABLE shipments (
    shipment_id              VARCHAR(20)   PRIMARY KEY,
    customer_id              INT           NOT NULL,
    receiver_id              INT           NOT NULL,
    cargo_id                 INT           NOT NULL,
    fleet_id                 VARCHAR(50),
    origin_location          VARCHAR(100)  NOT NULL,
    destination_location     VARCHAR(100)  NOT NULL,
    service_type   ENUM('hub_to_hub', 'door_to_door' )
 NOT NULL DEFAULT 'hub_to_hub',
    shipment_status     ENUM( 'pending_confirmation',
 'approved','assigned','in_transit', 'out_for_delivery', 'delivered', 'cancelled', 'cancellation_requested' ) NOT NULL DEFAULT
 'pending_confirmation',payment_status ENUM
('unpaid','pending_verification', 'partial','paid','refunded'
 ) NOT NULL DEFAULT 'unpaid',
    special_instructions     TEXT,
    booking_date             TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    confirmed_at             DATETIME      NULL,
    assigned_at              DATETIME      NULL,
    in_transit_at            DATETIME      NULL,
    out_for_delivery_at      DATETIME      NULL,
    actual_delivery_date     DATETIME      NULL,
    invoice_id               INT           DEFAULT NULL,
    row_version              BIGINT        NOT NULL DEFAULT 1,
    FOREIGN KEY (customer_id) REFERENCES customers(customer_id) ON DELETE RESTRICT,
    FOREIGN KEY (receiver_id) REFERENCES receivers(receiver_id) ON DELETE RESTRICT,
    FOREIGN KEY (cargo_id)    REFERENCES cargo(cargo_id)        ON DELETE CASCADE,
    FOREIGN KEY (fleet_id)    REFERENCES fleet(fleet_id)        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- TABLE: tracking
-- Tracking events for shipments
CREATE TABLE tracking (
    tracking_id       INT          AUTO_INCREMENT PRIMARY KEY,
    shipment_id       VARCHAR(20)  NOT NULL,
    current_status    VARCHAR(50)  NOT NULL,
    current_location  VARCHAR(100) NOT NULL,
    event_notes       TEXT,
    event_timestamp   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (shipment_id) REFERENCES shipments(shipment_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- TABLE: invoices
-- Invoice records for shipments
CREATE TABLE invoices (
    invoice_id      INT            AUTO_INCREMENT PRIMARY KEY,
    shipment_id     VARCHAR(20)    NOT NULL UNIQUE,
    customer_id     INT            NOT NULL,
    invoice_number  VARCHAR(40)    NOT NULL UNIQUE,
    total_amount    DECIMAL(12,2)  NOT NULL,
    amount_paid     DECIMAL(12,2)  NOT NULL DEFAULT 0,
    due_date        DATE           NOT NULL,
    invoice_status  ENUM(
                        'unpaid',  'partial', 'paid','overdue','void') NOT NULL DEFAULT 'unpaid',
    created_at TIMESTAMP   DEFAULT CURRENT_TIMESTAMP,
    paid_at         DATETIME       NULL,
    row_version     BIGINT         NOT NULL DEFAULT 1,
    FOREIGN KEY (shipment_id) REFERENCES shipments(shipment_id) ON DELETE CASCADE,
    FOREIGN KEY (customer_id) REFERENCES customers(customer_id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Back-reference: shipments.invoice_id → invoices.invoice_id
-- Added after invoices table is created to avoid circular FK issue
ALTER TABLE shipments
    ADD CONSTRAINT fk_shipments_invoice
    FOREIGN KEY (invoice_id) REFERENCES invoices(invoice_id) ON DELETE SET NULL;

-- TABLE: payments
-- Individual payment transactions against an invoice
-- Supports partial payments and refunds
CREATE TABLE payments (
    payment_id             INT            AUTO_INCREMENT PRIMARY KEY,
    shipment_id            VARCHAR(20)    NOT NULL,
    invoice_id             INT            NOT NULL,
    user_id                INT            NOT NULL,
    payment_amount         DECIMAL(12,2)  NOT NULL,
    payment_method         VARCHAR(50)    NOT NULL,
    payment_status         ENUM(
                               'pending',
                               'completed',
                               'failed',
                               'refunded'
                           ) NOT NULL DEFAULT 'completed',
    transaction_reference  VARCHAR(100),
    card_last_four_digits  VARCHAR(4)     DEFAULT NULL,
    paid_at                TIMESTAMP      DEFAULT CURRENT_TIMESTAMP,
    row_version            BIGINT         NOT NULL DEFAULT 1,
    FOREIGN KEY (shipment_id) REFERENCES shipments(shipment_id) ON DELETE CASCADE,
    FOREIGN KEY (invoice_id)  REFERENCES invoices(invoice_id)   ON DELETE CASCADE,
    FOREIGN KEY (user_id)     REFERENCES users(user_id)         ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- TABLE: audit_logs
-- Audit trail for system changes
CREATE TABLE audit_logs (
    audit_log_id      INT          AUTO_INCREMENT PRIMARY KEY,
    entity_type       VARCHAR(50)  NOT NULL,
    entity_id         VARCHAR(50)  NOT NULL,
    action_performed  VARCHAR(100) NOT NULL,
    changed_by        VARCHAR(150),
    change_details    TEXT,
    created_at        TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- TABLE: cancellation_requests
-- Cancellation requests for shipments
CREATE TABLE cancellation_requests (
    cancellation_id     INT          AUTO_INCREMENT PRIMARY KEY,
    shipment_id         VARCHAR(20)  NOT NULL,
    requested_by        VARCHAR(150) NOT NULL,
    cancellation_reason TEXT         NOT NULL,
    request_status      ENUM(
                            'pending',
                            'approved',
                            'rejected'
                        ) NOT NULL DEFAULT 'pending',
    requested_at        TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    resolved_at         TIMESTAMP    NULL,
    FOREIGN KEY (shipment_id) REFERENCES shipments(shipment_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DELIMITER $$

-- STORED PROCEDURE: sp_create_customer_user
-- Creates a new customer user account and associated customer profile
CREATE PROCEDURE sp_create_customer_user(
    IN p_full_name     VARCHAR(150),
    IN p_email_address VARCHAR(150),
    IN p_password_hash VARCHAR(255),
    IN p_phone_number  VARCHAR(50),
    IN p_home_address  VARCHAR(255)
)
BEGIN
    DECLARE v_new_user_id INT;

    IF EXISTS (
        SELECT 1 FROM users
        WHERE email_address = LOWER(TRIM(p_email_address))
    ) THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Email address is already registered.';
    END IF;

    INSERT INTO users (
        full_name, email_address, password_hash,
        phone_number, home_address, user_role
    )
    VALUES (
        p_full_name,
        LOWER(TRIM(p_email_address)),
        p_password_hash,
        p_phone_number,
        p_home_address,
        'customer'
    );

    SET v_new_user_id = LAST_INSERT_ID();

    INSERT INTO customers (
        user_id, full_name, email_address,
        phone_number, home_address
    )
    VALUES (
        v_new_user_id,
        p_full_name,
        LOWER(TRIM(p_email_address)),
        p_phone_number,
        p_home_address
    );

    SELECT v_new_user_id AS new_user_id;
END$$

-- STORED PROCEDURE: sp_get_user_by_email
-- Retrieves user information by email address for authentication
CREATE PROCEDURE sp_get_user_by_email(
    IN p_email_address VARCHAR(150)
)
BEGIN
    SELECT
        user_id,
        full_name,
        email_address,
        password_hash,
        user_role,
        account_status,
        phone_number,
        home_address
    FROM users
    WHERE email_address = LOWER(TRIM(p_email_address))
    LIMIT 1;
END$$

-- STORED PROCEDURE: sp_create_receiver
-- Creates a new receiver record for shipment delivery
CREATE PROCEDURE sp_create_receiver(
    IN p_full_name        VARCHAR(150),
    IN p_email_address    VARCHAR(150),
    IN p_phone_number     VARCHAR(50),
    IN p_delivery_address VARCHAR(255)
)
BEGIN
    INSERT INTO receivers (
        full_name, email_address,
        phone_number, delivery_address
    )
    VALUES (
        p_full_name,
        LOWER(TRIM(p_email_address)),
        p_phone_number,
        p_delivery_address
    );

    SELECT LAST_INSERT_ID() AS new_receiver_id;
END$$

-- STORED PROCEDURE: sp_create_shipment
-- Creates a complete shipment with all related records (receiver, cargo, invoice, payment, tracking)
CREATE PROCEDURE sp_create_shipment(
    IN p_customer_id            INT,
    IN p_receiver_full_name     VARCHAR(150),
    IN p_receiver_email_address VARCHAR(150),
    IN p_receiver_phone_number  VARCHAR(50),
    IN p_receiver_delivery_address VARCHAR(255),
    IN p_origin_location        VARCHAR(100),
    IN p_destination_location   VARCHAR(100),
    IN p_service_type           VARCHAR(50),
    IN p_handling_type          VARCHAR(50),
    IN p_special_instructions   TEXT,
    IN p_weight_in_kg           DECIMAL(10,2),
    IN p_volume_in_cubic_meters DECIMAL(10,2),
    IN p_payment_method         VARCHAR(50),
    IN p_transaction_reference  VARCHAR(100)
)
BEGIN
    DECLARE v_new_shipment_id   VARCHAR(20);
    DECLARE v_new_receiver_id   INT;
    DECLARE v_new_cargo_id      INT;
    DECLARE v_new_invoice_id    INT;
    DECLARE v_invoice_number    VARCHAR(40);
    DECLARE v_total_amount      DECIMAL(12,2);
    DECLARE v_attempt_count     INT DEFAULT 0;
    DECLARE v_user_id           INT;

    -- Calculate total amount based on weight, volume, service type, and handling type
    SET v_total_amount = 150
        + (p_weight_in_kg           * 25)
        + (p_volume_in_cubic_meters * 300);

    IF p_service_type = 'door_to_door' THEN
        SET v_total_amount = v_total_amount + 200;
    END IF;

    IF p_handling_type = 'fragile'    THEN SET v_total_amount = v_total_amount + 100; END IF;
    IF p_handling_type = 'perishable' THEN SET v_total_amount = v_total_amount + 150; END IF;
    IF p_handling_type = 'hazardous'  THEN SET v_total_amount = v_total_amount + 250; END IF;

    -- Generate unique shipment ID with collision retry
    generate_id: LOOP
        SET v_new_shipment_id = CONCAT(
            'CC', LPAD(FLOOR(RAND() * 8999) + 1000, 4, '0')
        );

        IF NOT EXISTS (
            SELECT 1 FROM shipments
            WHERE shipment_id = v_new_shipment_id
        ) THEN
            LEAVE generate_id;
        END IF;

        SET v_attempt_count = v_attempt_count + 1;

        IF v_attempt_count > 20 THEN
            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Could not generate a unique shipment ID. Please try again.';
        END IF;
    END LOOP;

    -- Insert receiver
    INSERT INTO receivers (
        full_name, email_address,
        phone_number, delivery_address
    )
    VALUES (
        p_receiver_full_name,
        LOWER(TRIM(p_receiver_email_address)),
        p_receiver_phone_number,
        p_receiver_delivery_address
    );
    SET v_new_receiver_id = LAST_INSERT_ID();

    -- Insert cargo
    INSERT INTO cargo (
        cargo_description, weight_in_kg,
        volume_in_cubic_meters, handling_type
    )
    VALUES (
        CONCAT(p_handling_type, ' cargo'),
        p_weight_in_kg,
        p_volume_in_cubic_meters,
        p_handling_type
    );
    SET v_new_cargo_id = LAST_INSERT_ID();

    -- Insert shipment
    INSERT INTO shipments (
        shipment_id, customer_id, receiver_id, cargo_id,
        origin_location, destination_location,
        service_type, shipment_status,
        payment_status, special_instructions
    )
    VALUES (
        v_new_shipment_id,
        p_customer_id,
        v_new_receiver_id,
        v_new_cargo_id,
        p_origin_location,
        p_destination_location,
        p_service_type,
        'pending_confirmation',
        'paid',
        p_special_instructions
    );

    -- Generate invoice number
    SET v_invoice_number = CONCAT(
        'INV-',
        DATE_FORMAT(NOW(), '%Y%m%d'),
        '-',
        LPAD(FLOOR(RAND() * 89999) + 10000, 5, '0')
    );

    -- Insert invoice
    INSERT INTO invoices (
        shipment_id, customer_id, invoice_number,
        total_amount, amount_paid,
        due_date, invoice_status, paid_at
    )
    VALUES (
        v_new_shipment_id,
        p_customer_id,
        v_invoice_number,
        v_total_amount,
        v_total_amount,
        DATE_ADD(CURDATE(), INTERVAL 7 DAY),
        'paid',
        NOW()
    );
    SET v_new_invoice_id = LAST_INSERT_ID();

    -- Link invoice back to shipment
    UPDATE shipments
    SET invoice_id = v_new_invoice_id
    WHERE shipment_id = v_new_shipment_id;

    -- Insert payment record
    SELECT u.user_id INTO v_user_id
    FROM customers c
    JOIN users u ON c.user_id = u.user_id
    WHERE c.customer_id = p_customer_id
    LIMIT 1;

    INSERT INTO payments (
        shipment_id, invoice_id, user_id,
        payment_amount, payment_method,
        payment_status, transaction_reference
    )
    VALUES (
        v_new_shipment_id,
        v_new_invoice_id,
        v_user_id,
        v_total_amount,
        p_payment_method,
        'completed',
        p_transaction_reference
    );

    -- First tracking event
    INSERT INTO tracking (
        shipment_id, current_status,
        current_location, event_notes
    )
    VALUES (
        v_new_shipment_id,
        'pending_confirmation',
        p_origin_location,
        'Shipment booked and awaiting admin confirmation.'
    );

    -- Audit log
    INSERT INTO audit_logs (
        entity_type, entity_id, action_performed,
        changed_by, change_details
    )
    VALUES (
        'shipment',
        v_new_shipment_id,
        'shipment booked',
        NULL,
        CONCAT(
            'origin=', p_origin_location,
            ', destination=', p_destination_location,
            ', total_amount=', v_total_amount
        )
    );

    SELECT
        v_new_shipment_id AS new_shipment_id,
        v_total_amount    AS total_amount,
        v_invoice_number  AS invoice_number;
END$$

-- STORED PROCEDURE: sp_confirm_shipment
-- Confirms a shipment after payment verification
CREATE PROCEDURE sp_confirm_shipment(
    IN p_shipment_id  VARCHAR(20),
    IN p_admin_name   VARCHAR(150)
)
BEGIN
    DECLARE v_payment_status   VARCHAR(50);
    DECLARE v_shipment_status  VARCHAR(50);
    DECLARE v_origin_location  VARCHAR(100);

    SELECT payment_status, shipment_status, origin_location
    INTO v_payment_status, v_shipment_status, v_origin_location
    FROM shipments
    WHERE shipment_id = p_shipment_id
    LIMIT 1;

    IF v_payment_status IS NULL THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Shipment not found.';
    END IF;

    IF v_payment_status <> 'paid' THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Cannot confirm shipment: payment has not been completed.';
    END IF;

    IF v_shipment_status <> 'pending_confirmation' THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Shipment cannot be confirmed from its current status.';
    END IF;

    UPDATE shipments
    SET shipment_status = 'approved',
        confirmed_at    = NOW(),
        row_version     = row_version + 1
    WHERE shipment_id = p_shipment_id;

    INSERT INTO tracking (
        shipment_id, current_status,
        current_location, event_notes
    )
    VALUES (
        p_shipment_id,
        'approved',
        v_origin_location,
        'Shipment confirmed by admin. Awaiting fleet assignment.'
    );

    INSERT INTO audit_logs (
        entity_type, entity_id, action_performed,
        changed_by, change_details
    )
    VALUES (
        'shipment',
        p_shipment_id,
        'shipment confirmed',
        p_admin_name,
        'shipment status set to approved'
    );
END$$

-- STORED PROCEDURE: sp_assign_fleet
-- Assigns a fleet vehicle to a shipment
CREATE PROCEDURE sp_assign_fleet(
    IN p_shipment_id  VARCHAR(20),
    IN p_fleet_id     VARCHAR(50),
    IN p_admin_name   VARCHAR(150)
)
BEGIN
    DECLARE v_shipment_status VARCHAR(50);

    SELECT shipment_status INTO v_shipment_status
    FROM shipments
    WHERE shipment_id = p_shipment_id
    LIMIT 1;

    IF v_shipment_status IS NULL THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Shipment not found.';
    END IF;

    IF v_shipment_status <> 'approved' THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Only approved shipments can be assigned a fleet vehicle.';
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM fleet
        WHERE fleet_id = p_fleet_id
          AND operational_status = 'available'
    ) THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Fleet vehicle is not available for assignment.';
    END IF;

    UPDATE shipments
    SET fleet_id         = p_fleet_id,
        shipment_status  = 'assigned',
        assigned_at      = NOW(),
        row_version      = row_version + 1
    WHERE shipment_id = p_shipment_id;

    UPDATE fleet
    SET operational_status = 'assigned',
        row_version        = row_version + 1
    WHERE fleet_id = p_fleet_id;

    INSERT INTO tracking (
        shipment_id, current_status,
        current_location, event_notes
    )
    SELECT
        p_shipment_id,
        'assigned',
        current_hub_location,
        CONCAT('Fleet vehicle ', p_fleet_id, ' assigned to shipment.')
    FROM fleet
    WHERE fleet_id = p_fleet_id;

    INSERT INTO audit_logs (
        entity_type, entity_id, action_performed,
        changed_by, change_details
    )
    VALUES (
        'shipment',
        p_shipment_id,
        'fleet vehicle assigned',
        p_admin_name,
        CONCAT('fleet_id=', p_fleet_id)
    );
END$$

-- STORED PROCEDURE: sp_dispatch_fleet
-- Dispatches a fleet vehicle with all its assigned shipments
CREATE PROCEDURE sp_dispatch_fleet(
    IN p_fleet_id    VARCHAR(50),
    IN p_admin_name  VARCHAR(150)
)
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM fleet WHERE fleet_id = p_fleet_id
    ) THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Fleet vehicle not found.';
    END IF;

    UPDATE fleet
    SET operational_status = 'dispatched',
        row_version        = row_version + 1
    WHERE fleet_id = p_fleet_id;

    UPDATE shipments
    SET shipment_status = 'in_transit',
        in_transit_at   = IFNULL(in_transit_at, NOW()),
        row_version     = row_version + 1
    WHERE fleet_id      = p_fleet_id
      AND shipment_status = 'assigned';

    INSERT INTO tracking (
        shipment_id, current_status,
        current_location, event_notes
    )
    SELECT
        s.shipment_id,
        'in_transit',
        s.origin_location,
        CONCAT('Fleet vehicle ', p_fleet_id, ' dispatched. Shipment is now in transit.')
    FROM shipments s
    WHERE s.fleet_id        = p_fleet_id
      AND s.shipment_status = 'in_transit';

    INSERT INTO audit_logs (
        entity_type, entity_id, action_performed,
        changed_by, change_details
    )
    VALUES (
        'fleet',
        p_fleet_id,
        'fleet vehicle dispatched',
        p_admin_name,
        'all assigned shipments set to in_transit'
    );
END$$

-- STORED PROCEDURE: sp_mark_out_for_delivery
-- Marks shipments as out for delivery (door-to-door service)
CREATE PROCEDURE sp_mark_out_for_delivery(
    IN p_shipment_id  VARCHAR(20),
    IN p_admin_name   VARCHAR(150)
)
BEGIN
    DECLARE v_destination_location VARCHAR(100);
    DECLARE v_service_type         VARCHAR(50);

    SELECT destination_location, service_type
    INTO v_destination_location, v_service_type
    FROM shipments
    WHERE shipment_id = p_shipment_id
    LIMIT 1;

    IF v_service_type <> 'door_to_door' THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Out for delivery status applies only to door-to-door shipments.';
    END IF;

    UPDATE shipments
    SET shipment_status      = 'out_for_delivery',
        out_for_delivery_at  = NOW(),
        row_version          = row_version + 1
    WHERE shipment_id        = p_shipment_id
      AND shipment_status    = 'in_transit';

    IF ROW_COUNT() = 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Shipment cannot be marked as out for delivery from its current status.';
    END IF;

    INSERT INTO tracking (
        shipment_id, current_status,
        current_location, event_notes
    )
    VALUES (
        p_shipment_id,
        'out_for_delivery',
        v_destination_location,
        'Shipment arrived at destination hub and is out for final delivery.'
    );

    INSERT INTO audit_logs (
        entity_type, entity_id, action_performed,
        changed_by, change_details
    )
    VALUES (
        'shipment',
        p_shipment_id,
        'shipment out for delivery',
        p_admin_name,
        'door-to-door final delivery leg started'
    );
END$$

-- STORED PROCEDURE: sp_mark_delivered
-- Marks a shipment as delivered and releases the fleet vehicle
CREATE PROCEDURE sp_mark_delivered(
    IN p_shipment_id  VARCHAR(20),
    IN p_admin_name   VARCHAR(150)
)
BEGIN
    DECLARE v_fleet_id              VARCHAR(50);
    DECLARE v_destination_location  VARCHAR(100);

    SELECT fleet_id, destination_location
    INTO v_fleet_id, v_destination_location
    FROM shipments
    WHERE shipment_id = p_shipment_id
    LIMIT 1;

    UPDATE shipments
    SET shipment_status      = 'delivered',
        actual_delivery_date = NOW(),
        row_version          = row_version + 1
    WHERE shipment_id     = p_shipment_id
      AND shipment_status IN ('in_transit', 'out_for_delivery');

    IF ROW_COUNT() = 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Shipment cannot be marked as delivered from its current status.';
    END IF;

    INSERT INTO tracking (
        shipment_id, current_status,
        current_location, event_notes
    )
    VALUES (
        p_shipment_id,
        'delivered',
        v_destination_location,
        'Shipment successfully delivered to receiver.'
    );

    -- Release fleet only if it has no remaining active shipments
    IF v_fleet_id IS NOT NULL
       AND NOT EXISTS (
           SELECT 1 FROM shipments
           WHERE fleet_id        = v_fleet_id
             AND shipment_status IN ('assigned', 'in_transit', 'out_for_delivery')
       )
    THEN
        UPDATE fleet
        SET operational_status   = 'available',
            current_hub_location = v_destination_location,
            next_destination     = NULL,
            row_version          = row_version + 1
        WHERE fleet_id = v_fleet_id;
    END IF;

    INSERT INTO audit_logs (
        entity_type, entity_id, action_performed,
        changed_by, change_details
    )
    VALUES (
        'shipment',
        p_shipment_id,
        'shipment delivered',
        p_admin_name,
        'shipment status set to delivered'
    );
END$$

-- STORED PROCEDURE: sp_request_cancellation
-- Submits a cancellation request for a shipment
CREATE PROCEDURE sp_request_cancellation(
    IN p_shipment_id          VARCHAR(20),
    IN p_requested_by         VARCHAR(150),
    IN p_cancellation_reason  TEXT
)
BEGIN
    INSERT INTO cancellation_requests (
        shipment_id, requested_by,
        cancellation_reason, request_status
    )
    VALUES (
        p_shipment_id,
        p_requested_by,
        p_cancellation_reason,
        'pending'
    );

    UPDATE shipments
    SET shipment_status = 'cancellation_requested',
        row_version     = row_version + 1
    WHERE shipment_id   = p_shipment_id
      AND shipment_status = 'pending_confirmation';

    INSERT INTO tracking (
        shipment_id, current_status,
        current_location, event_notes
    )
    VALUES (
        p_shipment_id,
        'cancellation_requested',
        'system',
        CONCAT('Cancellation requested by ', p_requested_by)
    );

    INSERT INTO audit_logs (
        entity_type, entity_id, action_performed,
        changed_by, change_details
    )
    VALUES (
        'cancellation',
        p_shipment_id,
        'cancellation request submitted',
        p_requested_by,
        p_cancellation_reason
    );

    SELECT LAST_INSERT_ID() AS new_cancellation_id;
END$$

-- STORED PROCEDURE: sp_get_shipment_tracking
-- Retrieves tracking history for a shipment
CREATE PROCEDURE sp_get_shipment_tracking(
    IN p_shipment_id VARCHAR(20)
)
BEGIN
    SELECT
        tracking_id,
        shipment_id,
        current_status,
        current_location,
        event_notes,
        event_timestamp
    FROM tracking
    WHERE shipment_id = p_shipment_id
    ORDER BY event_timestamp ASC;
END$$

DELIMITER ;

DELIMITER $$

-- TRIGGER: trigger_shipments_after_update
-- Logs changes to shipment status
CREATE TRIGGER trigger_shipments_after_update
AFTER UPDATE ON shipments
FOR EACH ROW
BEGIN
    IF NEW.shipment_status <> OLD.shipment_status THEN
        INSERT INTO audit_logs (
            entity_type, entity_id, action_performed,
            changed_by, change_details
        )
        VALUES (
            'shipment',
            NEW.shipment_id,
            'shipment status changed',
            NULL,
            CONCAT(
                'previous_status=', OLD.shipment_status,
                ', new_status=', NEW.shipment_status
            )
        );
    END IF;
END$$

-- TRIGGER: trigger_payments_after_insert
-- Updates invoice and shipment payment status after payment insertion
CREATE TRIGGER trigger_payments_after_insert
AFTER INSERT ON payments
FOR EACH ROW
BEGIN
    DECLARE v_total_paid         DECIMAL(12,2) DEFAULT 0;
    DECLARE v_invoice_total      DECIMAL(12,2) DEFAULT 0;
    DECLARE v_new_invoice_status VARCHAR(50);

    SELECT total_amount INTO v_invoice_total
    FROM invoices
    WHERE invoice_id = NEW.invoice_id;

    SELECT SUM(payment_amount) INTO v_total_paid
    FROM payments
    WHERE invoice_id      = NEW.invoice_id
      AND payment_status  = 'completed';

    IF v_total_paid >= v_invoice_total THEN
        SET v_new_invoice_status = 'paid';
    ELSE
        SET v_new_invoice_status = 'partial';
    END IF;

    UPDATE invoices
    SET amount_paid    = v_total_paid,
        invoice_status = v_new_invoice_status,
        paid_at        = IF(v_new_invoice_status = 'paid', NOW(), paid_at),
        row_version    = row_version + 1
    WHERE invoice_id = NEW.invoice_id;

    UPDATE shipments
    SET payment_status = v_new_invoice_status,
        row_version    = row_version + 1
    WHERE shipment_id  = NEW.shipment_id;

    INSERT INTO audit_logs (
        entity_type, entity_id, action_performed,
        changed_by, change_details
    )
    VALUES (
        'payment',
        NEW.shipment_id,
        'payment transaction recorded',
        CONCAT('user_id=', NEW.user_id),
        CONCAT(
            'payment_amount=', NEW.payment_amount,
            ', invoice_id=', NEW.invoice_id,
            ', new_invoice_status=', v_new_invoice_status
        )
    );
END$$

-- TRIGGER: trigger_cancellation_requests_after_insert
-- Updates shipment status and logs cancellation request creation
CREATE TRIGGER trigger_cancellation_requests_after_insert
AFTER INSERT ON cancellation_requests
FOR EACH ROW
BEGIN
    UPDATE shipments
    SET shipment_status = 'cancellation_requested',
        row_version     = row_version + 1
    WHERE shipment_id = NEW.shipment_id;

    INSERT INTO audit_logs (
        entity_type, entity_id, action_performed,
        changed_by, change_details
    )
    VALUES (
        'cancellation',
        NEW.shipment_id,
        'cancellation request created',
        NEW.requested_by,
        NEW.cancellation_reason
    );
END$$

-- TRIGGER: trigger_fleet_after_update
-- Logs changes to fleet operational status and location
CREATE TRIGGER trigger_fleet_after_update
AFTER UPDATE ON fleet
FOR EACH ROW
BEGIN
    IF NEW.operational_status   <> OLD.operational_status
    OR NEW.current_hub_location <> OLD.current_hub_location
    OR COALESCE(NEW.next_destination, '') <> COALESCE(OLD.next_destination, '')
    THEN
        INSERT INTO audit_logs (
            entity_type, entity_id, action_performed,
            changed_by, change_details
        )
        VALUES (
            'fleet',
            NEW.fleet_id,
            'fleet record updated',
            NULL,
            CONCAT(
                'previous_status=', OLD.operational_status,
                ', new_status=', NEW.operational_status,
                ', previous_hub=', OLD.current_hub_location,
                ', new_hub=', NEW.current_hub_location,
                ', next_destination=', COALESCE(NEW.next_destination, 'none')
            )
        );
    END IF;
END$$

DELIMITER ;

