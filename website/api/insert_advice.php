<?php
/**
 * LLM-IoT Environmental Recommendation System
 * =============================================
 * API Endpoint: insert_advice.php
 *
 * Receives LLM-generated advice from an external program
 * and inserts it into the scsorla4_env_adv database.
 *
 * METHOD: HTTP POST
 * CONTENT-TYPE: application/json
 *
 * REQUEST FORMAT:
 * {
 *   "api_key": "change-me-TO-a-long-random-secret",
 *   "advise": "Your LLM-generated recommendation text here."
 * }
 *
 * RESPONSE FORMAT (success):
 * { "ok": true, "message": "Advice inserted successfully." }
 *
 * RESPONSE FORMAT (error):
 * { "ok": false, "err": "Error description." }
 *
 * PYTHON EXAMPLE:
 * import requests
 * requests.post(
 *     "https://scs.org.sa/api/insert_advice.php",
 *     json={
 *         "api_key": "change-me-TO-a-long-random-secret",
 *         "advise": "Air quality is GOOD. Temperature 24.5C, Humidity 45%..."
 *     }
 * )
 */

header('Content-Type: application/json');

// ================== API KEY ==================
// Same key used in secrets.h and ingest.php
define('API_KEY', 'change-me-TO-a-long-random-secret');

// ================== DATABASE CONFIGURATION ==================
$DB_HOST = 'localhost';
$DB_NAME = 'scsorla4_env_adv';
$DB_USER = 'scsorla4_nwaf';
$DB_PASS = 'ksu1957@';

// ================== REQUEST VALIDATION ==================
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'err' => 'Method not allowed. Use POST.']);
    exit;
}

$raw = file_get_contents('php://input');
if (!$raw) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'err' => 'Empty request body.']);
    exit;
}

$data = json_decode($raw, true);
if (!$data) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'err' => 'Invalid JSON.']);
    exit;
}

// Validate API key
$receivedKey = $data['api_key'] ?? '';
if (!hash_equals(API_KEY, $receivedKey)) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'err' => 'Unauthorized: invalid API key.']);
    exit;
}

// Validate advise field
$advise = trim($data['advise'] ?? '');
if ($advise === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'err' => 'Missing or empty advise field.']);
    exit;
}

// Truncate to 500 characters if needed
if (mb_strlen($advise) > 500) {
    $advise = mb_substr($advise, 0, 500);
}

// ================== DATABASE INSERT ==================
try {
    $pdo = new PDO(
        "mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4",
        $DB_USER,
        $DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );

    $stmt = $pdo->prepare(
        "INSERT INTO advice (timestamp, advise) VALUES (UTC_TIMESTAMP(), :advise)"
    );
    $stmt->execute([':advise' => $advise]);

    echo json_encode([
        'ok'      => true,
        'message' => 'Advice inserted successfully.',
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'ok'  => false,
        'err' => 'Database error. Please try again later.',
    ]);
}
