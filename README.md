# 🌬️ Smart Air Quality & Attention Assistant

![Project Status](https://img.shields.io/badge/Status-Completed-success)
![Version](https://img.shields.io/badge/Version-3.0-blue)
![Platform](https://img.shields.io/badge/Platform-ESP32%20%7C%20Web%20%7C%20Python-lightgrey)
![License](https://img.shields.io/badge/License-MIT-green)

**A local-LLM-assisted IoT system for real-time indoor environmental monitoring.**

---

## 📖 Table of Contents
1. [Project Overview](#-project-overview)
2. [Academic Context & Team](#-academic-context--team)
3. [System Architecture](#-system-architecture)
4. [Hardware Requirements](#-hardware-requirements)
5. [Software Stack](#-software-stack)
6. [Core Features](#-core-features)
7. [Database Schema](#-database-schema)
8. [API Documentation](#-api-documentation)
9. [Setup & Installation](#-setup--installation)
   - [1. Web Dashboard & API Setup](#1-web-dashboard--api-setup)
   - [2. ESP32 Firmware Setup](#2-esp32-firmware-setup)
   - [3. Python AI Engine Setup](#3-python-ai-engine-setup)
10. [Security Features](#-security-features)

---

## 🌍 Project Overview

The **Smart Air Quality & Attention Assistant** is designed to combat the silent accumulation of poor indoor environmental conditions (CO₂, VOCs, unbalanced temperature/humidity) that erode comfort, focus, and cognitive performance. 

By leveraging an ESP32 microcontroller equipped with high-precision sensors (BME680 with Bosch BSEC2 algorithm, MQ135), the system continuously monitors the environment. The data is transmitted via a dual-channel architecture (MQTT & HTTP) to a central server. A Python-based AI engine, powered by a local **Mistral LLM (via Ollama)**, analyzes this data against dynamic thresholds (like ASHRAE 55 and WHO guidelines) to generate real-time, actionable insights.

---

## 🎓 Academic Context & Team

This project was developed as the **CSC 497 Software Project** at the **College of Computer and Information Sciences (CCIS), King Saud University (KSU)**.

- **Semester:** Spring 2026 (1447H)
- **Supervisor:** Prof. Hatim A. Aboalsamh
- **Funding:** Supported by the Saudi Computer Society (SCS) and King Saud University.

**Development Team:**
- Nawaf A. Alfurhud 
- Ayman A. Hakami 
- Khaled A. Alshehri 
- Haider A. Alhassan 

---

## 🏗️ System Architecture

The architecture is divided into three main components:

1. **Edge/Hardware Layer (ESP32):** Reads sensor data, processes it via BSEC2, displays it on a local LCD, and pushes telemetry payloads via MQTT (EMQX) and HTTPS POST to the Web API.
2. **Cloud/Web Layer (PHP/MySQL):** Acts as the central data repository. Handles authentication, provides a clean monochromatic real-time dashboard (Chart.js & Canvas-Gauges), and exposes APIs for data ingestion and retrieval.
3. **AI/Processing Layer (Python):** Connects to the MQTT broker to receive live data, computes dynamic ASHRAE 55 thermal comfort thresholds, and uses a local Ollama model to generate contextual environmental advice.

---

## 🛠️ Hardware Requirements

- **ESP32 Development Board** (e.g., 38-pin version)
- **BME680 Environmental Sensor** (Temperature, Humidity, Pressure, Gas/VOCs via BSEC2 I2C: 0x76/0x77)
- **MQ135 Gas Sensor** (Analog interface for relative air quality)
- **20x4 I2C LCD Display** (For real-time local status)
- Connecting jumper wires & breadboard/custom PCB.

---

## 💻 Software Stack

- **Firmware:** C++ (Arduino IDE/PlatformIO), WiFiClientSecure, PubSubClient, Bosch BSEC2 Library.
- **Web Dashboard:** PHP 8+, HTML5, CSS3 (Monochromatic/Clean UI), JavaScript, Chart.js, Canvas-Gauges, Paho-MQTT WS.
- **Database:** MySQL / MariaDB (Optimized InnoDB schemas).
- **AI Engine:** Python 3.10+, Paho-MQTT, Requests, Ollama (Mistral model).
- **Broker:** EMQX Cloud (MQTT over TLS, Port 8883).

---

## ✨ Core Features

- **High-Precision Telemetry:** Utilizes the Bosch BSEC2 algorithm to output a highly accurate IAQ (Indoor Air Quality) index, CO2 equivalent, and breath VOC equivalent.
- **Dual-Channel Comm:** Fallback communication using both MQTT (real-time) and HTTP POST (historical persistence).
- **Local AI Insights:** No cloud-LLM dependency. Uses a local Mistral instance to read 30-day weather forecasts and indoor telemetry to give precise ventilation/cooling advice.
- **Secure Web Dashboard:** Session-based authentication with automatic timeout and secure PDO database connections.
- **Dynamic Charting:** Real-time visual representation of sensor data over the last 24-168 hours.

---

## 🗄️ Database Schema

The project uses two separate databases (or logical schemas) for separation of concerns:

1. **`scsorla4_multi_sensors_esp32_v3` (Telemetry)**
   - `telemetry` table: Stores historical sensor readings (`device_id`, `ts`, `mq_v`, `bme_t`, `bme_h`, `bme_iaq`, `bme_co2_eq`, `rssi`, etc.)
2. **`scsorla4_env_adv` (AI Advice)**
   - `advice` table: Stores timestamped LLM recommendations (`id`, `timestamp`, `advise`).

---

## 🔌 API Documentation

The Web Layer exposes several internal APIs for the hardware and AI layers:

### 1. Ingest Telemetry (`POST /ingest.php`)
- **Description:** Receives data from the ESP32.
- **Headers:** `Content-Type: application/json`, `X-API-Key: <secret_key>`
- **Payload:** JSON containing BME680 data, MQ135 data, RSSI, etc.

### 2. Fetch Range (`GET /get_range.php`)
- **Description:** Used by the dashboard to plot historical data.
- **Parameters:** `device_id` (string), `hours` (int, default: 24)
- **Returns:** JSON array of historical telemetry.

### 3. Insert Advice (`POST /insert_advice.php`)
- **Description:** Called by the Python AI script to save new LLM recommendations.
- **Payload:** `{"api_key": "...", "advise": "..."}`

### 4. Get Advice (`GET /get_advice.php`)
- **Description:** Called by the dashboard (`llm.php`) to display the latest advice.
- **Returns:** JSON object containing the latest timestamp and recommendation text.

---

## 🚀 Setup & Installation

### 1. Web Dashboard & API Setup
1. Clone the web directory to your PHP server (e.g., Apache/Nginx).
2. Import the two SQL schemas (`schema_scsorla4_multi_sensors_esp32_v3.sql`, `schema_scsorla4_env_adv.sql`) into your MySQL server.
3. Update `db.php`, `ingest.php`, `get_advice.php`, and `insert_advice.php` with your specific database credentials and API Keys.
4. Access `login.php` (Default password hint provided on the page).

### 2. ESP32 Firmware Setup
1. Open `esp32_enhanced_v3.txt` (rename to `.ino` or `.cpp`).
2. Install necessary libraries in Arduino IDE: *Bosch BSEC2, PubSubClient, LiquidCrystal_I2C, ArduinoJson*.
3. Update WiFi credentials, EMQX Broker details, and the HTTP API endpoint/key in the firmware code.
4. Compile and upload to the ESP32.

### 3. Python AI Engine Setup
1. Ensure [Ollama](https://ollama.com/) is installed and running locally.
2. Pull the Mistral model: `ollama run mistral`
3. Install Python dependencies: `pip install paho-mqtt requests ollama`
4. Update `MistralNewDB.py` with your EMQX credentials and the web server API URL.
5. Run the engine: `python MistralNewDB.py`

---

## 🛡️ Security Features

- **API Authentication:** Hardware and AI APIs are protected via static API Keys (`hash_equals` validation).
- **Dashboard Guard:** All UI endpoints are protected by `auth_check.php` utilizing strict PHP session management with configurable inactivity timeouts.
- **Database Security:** Strict use of PDO prepared statements throughout to eliminate SQL injection vectors.
- **Network Encryption:** MQTT payload is transmitted over TLS (Port 8883).

---
*Developed with dedication by the KSU CCIS Spring 2026 Graduating Team.*
