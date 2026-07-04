/**
 * ESP32 Dashboard Configuration - Communication Settings
 * ======================================================
 * 
 * PURPOSE:
 * Centralizes all communication endpoints and credentials for ESP32 dashboard.
 * Manages connections between web dashboard, MQTT broker, and database API.
 * 
 * COMMUNICATION ARCHITECTURE:
 * 
 * [Web Dashboard] ←→ [MQTT Broker] ←→ [ESP32 Device]
 *       ↓
 * [Database API] ← [Historical Data]
 * 
 * SECURITY NOTES:
 * - MQTT credentials allow command publishing to ESP32
 * - WebSocket connections use TLS for HTTPS sites
 * - API endpoints use relative paths for flexibility
 * 
 * CONFIGURATION SECTIONS:
 * 1. MQTT WebSocket Connection (real-time bidirectional communication)
 * 2. Device Identification (site and device hierarchy)
 * 3. MQTT Topic Structure (organized message routing)
 * 4. Database API Endpoints (historical data queries)
 * 
 * UPDATED: March 7, 2026 - New EMQX Server Configuration
 */

// ================== MQTT WEBSOCKET CONFIGURATION ==================
/**
 * MQTT over WebSocket connection settings
 * 
 * PROTOCOL SELECTION:
 * - HTTPS sites: Use WSS (WebSocket Secure) on port 8084
 * - HTTP sites: Use WS (WebSocket) on port 8083
 * 
 * EMQX CLOUD BROKER:
 * - Provides TLS termination and WebSocket support
 * - Handles message routing between dashboard and ESP32
 * - Supports both subscription (receive data) and publishing (send commands)
 * 
 * UPDATED: March 7, 2026 - New EMQX Server
 */
window.MQTT_WS_URL = window.location.protocol === 'https:' 
    ? "wss://ga7a11da.ala.us-east-1.emqxsl.com:8084/mqtt"  // Secure WebSocket for HTTPS - UPDATED
    : "ws://ga7a11da.ala.us-east-1.emqxsl.com:8083/mqtt";   // Plain WebSocket for HTTP - UPDATED

// MQTT Authentication Credentials
window.MQTT_WS_USER = "hatim";  // Username with publish/subscribe permissions
window.MQTT_WS_PASS = "zoro";   // Password - CHANGE IN PRODUCTION!

// ================== DEVICE IDENTIFICATION ==================
/**
 * Hierarchical device identification system
 * 
 * STRUCTURE: site/[SITE_ID]/device/[DEVICE_ID]/[message_type]
 * - Allows multiple sites and devices in single MQTT namespace
 * - Enables selective subscription and message routing
 * - Facilitates scaling to multiple ESP32 devices
 */
window.SITE_ID   = "mysite01";        // Site identifier (can have multiple sites)
window.DEVICE_ID = "esp32_38pin_01";  // Unique ESP32 device identifier

// ================== MQTT TOPIC STRUCTURE ==================
/**
 * Standardized MQTT topic hierarchy for organized communication
 * 
 * TOPIC PURPOSES:
 * - TELEMETRY: ESP32 → Dashboard (sensor data, every 20 seconds)
 * - COMMANDS: Dashboard → ESP32 (control commands: LCD, restart, etc.)
 * - ACKNOWLEDGMENTS: ESP32 → Dashboard (command execution confirmation)
 * - STATUS: ESP32 → Dashboard (connection status, firmware info)
 * 
 * MESSAGE FLOW:
 * Dashboard subscribes to: TELEMETRY, ACKNOWLEDGMENTS, STATUS
 * Dashboard publishes to: COMMANDS
 * ESP32 subscribes to: COMMANDS
 * ESP32 publishes to: TELEMETRY, ACKNOWLEDGMENTS, STATUS
 */
window.TOPIC_TEL  = `site/${window.SITE_ID}/device/${window.DEVICE_ID}/telemetry`; // Sensor data stream
window.TOPIC_CMD  = `site/${window.SITE_ID}/device/${window.DEVICE_ID}/cmd`;       // Device commands
window.TOPIC_ACK  = `site/${window.SITE_ID}/device/${window.DEVICE_ID}/cmd_ack`;  // Command confirmations
window.TOPIC_STAT = `site/${window.SITE_ID}/device/${window.DEVICE_ID}/status`;   // Device status updates

// ================== DATABASE API ENDPOINTS ==================
/**
 * REST API endpoints for historical sensor data queries
 * 
 * DATA FLOW:
 * Dashboard → GET request → API → Database → JSON response → Charts
 * 
 * ENDPOINTS:
 * - get_range.php: Retrieves sensor data for specified time ranges (1h, 6h, 24h)
 * - Supports filtering by date range, device ID, and sensor type
 * - Returns JSON formatted for Chart.js visualization
 */
window.API_BASE  = "/api";                              // API base path (relative to domain)
window.API_RANGE = `${window.API_BASE}/get_range.php`;   // Historical data endpoint