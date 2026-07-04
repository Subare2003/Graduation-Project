CREATE DATABASE IF NOT EXISTS scsorla4_multi_sensors_esp32_v3
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE scsorla4_multi_sensors_esp32_v3;

CREATE TABLE IF NOT EXISTS telemetry (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  device_id    VARCHAR(64)     NOT NULL,
  ts           DATETIME        NOT NULL,
  mq_v         DECIMAL(6,3)    NULL,
  mq_rel       DECIMAL(8,3)    NULL,
  mq_level     ENUM('ND','LOW','MED','HIGH','DANGER') NULL,
  bme_t            DECIMAL(5,2)   NULL,
  bme_h            DECIMAL(5,2)   NULL,
  bme_p            DECIMAL(7,2)   NULL,
  bme_iaq          DECIMAL(6,1)   NULL,            -- BSEC2 IAQ index (0-500)
  bme_iaq_accuracy TINYINT        NULL,            -- 0=calibrating, 1=low, 2=med, 3=high
  bme_iaq_level    VARCHAR(10)    NULL,            -- EXCEL/GOOD/MOD/POOR/BAD/HAZARD
  bme_co2_eq       DECIMAL(8,1)   NULL,            -- CO2 equivalent (ppm)
  bme_voc_eq       DECIMAL(7,3)   NULL,            -- Breath VOC equivalent (ppm)
  rssi             SMALLINT       NULL,
  vcc          DECIMAL(4,2)    NULL,
  fw           VARCHAR(32)     NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_device_ts (device_id, ts),
  KEY idx_ts (ts)
) ENGINE=InnoDB;

-- ============================================================
-- MIGRATION: Run this if upgrading an existing database
-- (skip if creating fresh from this schema)
-- ============================================================
-- ALTER TABLE telemetry
--   DROP COLUMN  bme_gas_kohm,
--   ADD COLUMN   bme_iaq          DECIMAL(6,1)  NULL AFTER bme_p,
--   ADD COLUMN   bme_iaq_accuracy TINYINT       NULL AFTER bme_iaq,
--   ADD COLUMN   bme_iaq_level    VARCHAR(10)   NULL AFTER bme_iaq_accuracy,
--   ADD COLUMN   bme_co2_eq       DECIMAL(8,1)  NULL AFTER bme_iaq_level,
--   ADD COLUMN   bme_voc_eq       DECIMAL(7,3)  NULL AFTER bme_co2_eq;

CREATE TABLE IF NOT EXISTS status (
  device_id   VARCHAR(64)  NOT NULL,
  boot_ts     DATETIME     NULL,
  fw          VARCHAR(32)  NULL,
  ip          VARCHAR(64)  NULL,
  broker      ENUM('connected','disconnected') NULL,
  last_ok_pub DATETIME     NULL,
  PRIMARY KEY (device_id)
) ENGINE=InnoDB;
