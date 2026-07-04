-- ============================================================
-- Database Schema: scsorla4_env_adv
-- Project: LLM-IoT Environmental Recommendation System
-- Description: Stores LLM-generated environmental advice
--              with timestamps derived from ESP32 sensor data
-- ============================================================

CREATE DATABASE IF NOT EXISTS scsorla4_env_adv
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE scsorla4_env_adv;

CREATE TABLE IF NOT EXISTS advice (
  id        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  timestamp DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  advise    TEXT         NOT NULL,
  PRIMARY KEY (id),
  KEY idx_timestamp (timestamp)
) ENGINE=InnoDB;

-- ============================================================
-- SAMPLE DATA (optional - remove before production)
-- ============================================================
-- INSERT INTO advice (timestamp, advise) VALUES
-- (NOW(), 'Air quality is currently GOOD. Temperature is within normal range at 24.5°C with 45% humidity. IAQ index is 75. No immediate action required. Consider ventilating the room if CO2 equivalent rises above 1000 ppm.');
