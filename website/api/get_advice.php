<?php
/**
 * LLM-IoT Environmental Recommendation System
 * =============================================
 * API Endpoint: get_advice.php
 *
 * Fetches the most recent LLM-generated advice record
 * from the scsorla4_env_adv database and returns it as JSON.
 *
 * Response format:
 * { "ok": true, "timestamp": "2026-06-07 14:00:00", "advise": "..." }
 */

header('Content-Type: application/json');

// ================== DATABASE CONFIGURATION ==================
$DB_HOST = 'localhost';
$DB_NAME = 'scsorla4_env_adv';
$DB_USER = 'scsorla4_nwaf';
$DB_PASS = 'ksu1957@';

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

    // Fetch the most recent record
    $stmt = $pdo->query(
        "SELECT timestamp, advise FROM advice ORDER BY timestamp DESC LIMIT 1"
    );
    $row = $stmt->fetch();

    if ($row) {
        echo json_encode([
            'ok'        => true,
            'timestamp' => $row['timestamp'],
            'advise'    => $row['advise'],
        ]);
    } else {
        echo json_encode([
            'ok'  => false,
            'err' => 'No recommendations found in the database.',
        ]);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'ok'  => false,
        'err' => 'Database connection error. Please try again later.',
    ]);
}
