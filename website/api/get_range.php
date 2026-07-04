<?php
require_once __DIR__ . '/db.php';

$device_id = $_GET['device_id'] ?? '';
$hours     = intval($_GET['hours'] ?? 24);
if ($hours <= 0 || $hours > 168) $hours = 24;

$sql = "SELECT DATE_FORMAT(ts, '%Y-%m-%d %H:%i:%s') as ts,
               mq_v, mq_rel, mq_level,
               bme_t, bme_h, bme_p,
               bme_iaq, bme_iaq_accuracy, bme_iaq_level,
               bme_co2_eq, bme_voc_eq,
               rssi, vcc, fw
        FROM telemetry
        WHERE device_id = :dev
          AND ts >= (UTC_TIMESTAMP() - INTERVAL :h HOUR)
        ORDER BY ts ASC";
$stmt = db()->prepare($sql);
$stmt->bindValue(':dev', $device_id, PDO::PARAM_STR);
$stmt->bindValue(':h', $hours, PDO::PARAM_INT);
$stmt->execute();

header('Content-Type: application/json');
echo json_encode($stmt->fetchAll());
