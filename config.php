<?php
/**
 * Database Configuration File
 * This file contains the database connection settings for the CargoConnect application.
 * It establishes a PDO connection to the MySQL database hosted on phpMyAdmin.
 */

// Database configuration constants
define('DB_HOST', 'localhost'); // Hostname or IP address of the database server
define('DB_NAME', 'cargoconnect_db'); // Name of the database
define('DB_USER', 'root'); // Database username (default for phpMyAdmin)
define('DB_PASS', ''); // Database password (leave empty if no password set)

/**
 * Function to establish a database connection using PDO
 * @return PDO The PDO database connection object
 * @throws PDOException If connection fails
 */
function getDBConnection() {
    try {
        // Create a new PDO instance with the database credentials
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, // Enable exception mode for errors
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, // Fetch as associative array
                PDO::ATTR_EMULATE_PREPARES => false, // Use real prepared statements
            ]
        );
        return $pdo;
    } catch (PDOException $e) {
        // Log the error and throw a generic exception for security
        error_log("Database connection failed: " . $e->getMessage());
        throw new PDOException("Database connection error. Please try again later.");
    }
}

/**
 * Function to close the database connection
 * @param PDO $pdo The PDO connection object to close
 */
function closeDBConnection($pdo) {
    $pdo = null; // Close the connection by setting to null
}
?>