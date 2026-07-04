<?php
/**
 * ESP32 Dashboard Database Connection Manager
 * ==========================================
 * 
 * PURPOSE:
 * Provides secure, centralized database connectivity for ESP32 sensor data storage.
 * Uses PDO with connection pooling for optimal performance and security.
 * 
 * DATABASE SCHEMA:
 * - telemetry table: Stores all sensor readings from ESP32 devices
 * - Columns: device_id, timestamp, temperature, humidity, pressure, gas, air_quality
 * - Indexes: device_id, timestamp for efficient time-series queries
 * 
 * SECURITY FEATURES:
 * - PDO with prepared statements (prevents SQL injection)
 * - UTF-8 character set enforcement
 * - Exception handling for database errors
 * - Connection reuse (singleton pattern)
 * 
 * USAGE:
 * $pdo = db(); // Get database connection
 * $stmt = $pdo->prepare("SELECT * FROM telemetry WHERE device_id = ?");
 */

// ================== DATABASE CONFIGURATION ==================
// IMPORTANT: Update these credentials for your hosting environment
$DB_HOST = "localhost";                      // Database server hostname
$DB_NAME = "scsorla4_multi_sensors_esp32_v3";   // v3 database (BSEC2 schema)
$DB_USER = "scsorla4_nwaf";                  // Database username
$DB_PASS = "ksu1957@";                       // Database password

/**
 * Get database connection using singleton pattern
 * 
 * FEATURES:
 * - Connection reuse: Creates connection once, reuses for all requests
 * - Error handling: Throws exceptions on connection failures
 * - Security: UTF-8 charset, prepared statements enabled
 * - Performance: Optimized fetch mode for associative arrays
 * 
 * @return PDO Database connection object
 * @throws PDOException If connection fails
 */
function db(){
  global $DB_HOST, $DB_NAME, $DB_USER, $DB_PASS; 
  static $pdo = null; // Static variable maintains connection across calls
  
  // Create connection only if it doesn't exist (singleton pattern)
  if($pdo === null){
    // Build MySQL DSN with UTF-8 character set
    $dsn = "mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4";
    
    // Create PDO connection with security and performance options
    $pdo = new PDO($dsn, $DB_USER, $DB_PASS, [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,    // Throw exceptions on errors
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, // Return associative arrays
    ]);
  }
  
  return $pdo;
}
