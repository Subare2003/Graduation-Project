<?php
/**
 * ESP32 Sensor Data Ingestion API
 * ===============================
 * 
 * PURPOSE:
 * Receives and stores sensor telemetry data from ESP32 devices via HTTP POST.
 * Provides secure endpoint for real-time environmental data collection.
 * 
 * DATA FLOW:
 * ESP32 → HTTP POST → API Key Validation → JSON Parsing → Database Storage
 * 
 * SECURITY:
 * - API key authentication (X-API-Key header)
 * - Input validation and sanitization
 * - Prepared statements for SQL injection prevention
 * - HTTPS recommended for production
 * 
 * EXPECTED DATA FORMAT (v3.0 - BSEC2):
 * {
 *   "device_id": "esp32_device_01",
 *   "ts": 1635789123,
 *   "mq135": {"v": 2.1, "rel": 1.05, "level": "LOW"},
 *   "bme680": {
 *     "iaq": 87.5, "iaq_accuracy": 2, "iaq_level": "GOOD",
 *     "co2_eq": 621.4, "voc_eq": 0.72,
 *     "t": 23.5, "h": 45.2, "p": 1013.2
 *   },
 *   "rssi": -65,
 *   "vcc": 3.30,
 *   "fw": "3.0.0"
 * }
 */

require_once __DIR__ . '/db.php';  // Database connection functions
header('Content-Type: application/json'); // Always return JSON responses

// ================== API SECURITY CONFIGURATION ==================
/**
 * API Key Authentication
 * CRITICAL: This key MUST match the API_KEY in ESP32 firmware secrets.h
 * Change this to a long, random secret for production deployment
 */
$SERVER_API_KEY = "change-me-TO-a-long-random-secret";

/**
 * Validate API key from ESP32 device
 * ESP32 sends key in X-API-Key HTTP header
 * Uses hash_equals() for timing attack protection
 */
$api_key = $_SERVER['HTTP_X_API_KEY'] ?? '';
if (!hash_equals($SERVER_API_KEY, $api_key)) {
  // Unauthorized - invalid or missing API key
  http_response_code(401);
  echo json_encode(['ok'=>false,'err'=>'unauthorized']);
  exit;
}

// ================== HTTP PAYLOAD PROCESSING ==================
/**
 * Read raw HTTP POST body from ESP32
 * ESP32 sends JSON payload in HTTP body, not as form data
 */
$raw = file_get_contents('php://input');
if (!$raw) { 
  http_response_code(400); 
  echo json_encode(['ok'=>false,'err'=>'no body']); 
  exit; 
}

/**
 * Parse JSON payload from ESP32 into PHP associative array
 * Validate JSON structure to prevent processing invalid data
 */
$data = json_decode($raw, true);
if (!$data) { 
  http_response_code(400); 
  echo json_encode(['ok'=>false,'err'=>'bad json']); 
  exit; 
}

/**
 * Validate required device_id field
 * Each ESP32 must have unique identifier for data tracking
 */
$device_id = $data['device_id'] ?? '';
if ($device_id === '') { 
  http_response_code(400); 
  echo json_encode(['ok'=>false,'err'=>'no device_id']); 
  exit; 
}

// ================== DATABASE STORAGE ==================
/**
 * Insert sensor telemetry data into database
 *
 * TABLE SCHEMA (telemetry) - v3.0 BSEC2:
 * - device_id: ESP32 device identifier
 * - ts: Server timestamp (UTC)
 * - mq_v, mq_rel, mq_level: MQ135 air quality sensor data
 * - bme_t, bme_h, bme_p: BME680 compensated temperature/humidity/pressure
 * - bme_iaq, bme_iaq_accuracy, bme_iaq_level: BSEC2 IAQ index and category
 * - bme_co2_eq, bme_voc_eq: CO2/VOC equivalents from BSEC2
 * - rssi: WiFi signal strength
 * - vcc: Device voltage
 * - fw: Firmware version
 */
$sql = "INSERT INTO telemetry
        (device_id, ts, mq_v, mq_rel, mq_level,
         bme_t, bme_h, bme_p,
         bme_iaq, bme_iaq_accuracy, bme_iaq_level, bme_co2_eq, bme_voc_eq,
         rssi, vcc, fw)
        VALUES (:dev, UTC_TIMESTAMP(), :mv, :mr, :ml,
                :bt, :bh, :bp,
                :biaq, :biacc, :blvl, :bco2, :bvoc,
                :rssi, :vcc, :fw)";

$stmt = db()->prepare($sql);
$stmt->execute([
  ':dev'   => $device_id,
  ':mv'    => $data['mq135']['v']             ?? null,  // MQ135 voltage
  ':mr'    => $data['mq135']['rel']           ?? null,  // MQ135 relative ratio
  ':ml'    => $data['mq135']['level']         ?? null,  // MQ135 level
  ':bt'    => $data['bme680']['t']            ?? null,  // Temperature (°C)
  ':bh'    => $data['bme680']['h']            ?? null,  // Humidity (%RH)
  ':bp'    => $data['bme680']['p']            ?? null,  // Pressure (hPa)
  ':biaq'  => $data['bme680']['iaq']          ?? null,  // IAQ index (0-500)
  ':biacc' => $data['bme680']['iaq_accuracy'] ?? null,  // Accuracy (0-3)
  ':blvl'  => $data['bme680']['iaq_level']   ?? null,  // EXCEL/GOOD/MOD/POOR/BAD/HAZARD
  ':bco2'  => $data['bme680']['co2_eq']       ?? null,  // CO2 equivalent (ppm)
  ':bvoc'  => $data['bme680']['voc_eq']       ?? null,  // VOC equivalent (ppm)
  ':rssi'  => $data['rssi']                   ?? null,  // WiFi RSSI (dBm)
  ':vcc'   => $data['vcc']                    ?? null,  // Voltage (V)
  ':fw'    => $data['fw']                     ?? null,  // Firmware version
]);

// ================== SUCCESS RESPONSE ==================
/**
 * Send success confirmation back to ESP32
 * ESP32 firmware checks this response to confirm data was stored
 */
echo json_encode(['ok'=>true]);
