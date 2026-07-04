<?php
// Include authentication check
require_once 'auth_check.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ESP32 Dashboard v3.0 - BSEC2</title>
    <link rel="stylesheet" href="assets/css/styles.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/canvas-gauges@2.1.7/gauge.min.js"></script>
</head>
<body>
    <!-- Header -->
    <header>
        <div class="header-content">
            <h1>ESP32 Enhanced Dashboard v3.0</h1>
            <div class="header-status">
                <div id="status" class="status-text">Initializing...</div>
                <div class="device-info">Device: esp32_38pin_01</div>
                <div class="user-controls">
                    <span class="user-info">✅ Authenticated</span>
                    <a href="logout.php" class="logout-btn" onclick="return confirm('Are you sure you want to logout?')">🚪 Logout</a>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="main-container">
        
        <!-- Sensor Gauges -->
        <section class="gauge-section">
            <h2>Live Sensor Readings</h2>
            <div class="gauge-grid">
                <div class="gauge-card">
                    <h3>Temperature</h3>
                    <div class="gauge-container">
                        <canvas id="temp-gauge" width="200" height="150"></canvas>
                        <div class="gauge-value" id="temp-value">-- °C</div>
                    </div>
                </div>
                
                <div class="gauge-card">
                    <h3>Humidity</h3>
                    <div class="gauge-container">
                        <canvas id="humidity-gauge" width="200" height="150"></canvas>
                        <div class="gauge-value" id="humidity-value">-- %</div>
                    </div>
                </div>
                
                <div class="gauge-card">
                    <h3>Pressure</h3>
                    <div class="gauge-container">
                        <canvas id="pressure-gauge" width="200" height="150"></canvas>
                        <div class="gauge-value" id="pressure-value">-- hPa</div>
                    </div>
                </div>
                
                <div class="gauge-card">
                    <h3>Air Quality</h3>
                    <div class="gauge-container">
                        <canvas id="air-gauge" width="200" height="150"></canvas>
                        <div class="gauge-value" id="air-value">--</div>
                    </div>
                </div>
                
                <div class="gauge-card">
                    <h3>MQ135 Voltage</h3>
                    <div class="gauge-container">
                        <canvas id="voltage-gauge" width="200" height="150"></canvas>
                        <div class="gauge-value" id="voltage-value">-- V</div>
                    </div>
                </div>
                
                <div class="gauge-card">
                    <h3>IAQ Index</h3>
                    <div class="gauge-container">
                        <canvas id="gas-gauge" width="200" height="150"></canvas>
                        <div class="gauge-value" id="gas-value">Calibrating...</div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Control Panel -->
        <section class="control-section">
            <h2>Device Control Panel</h2>
            
            <!-- LCD Message Control -->
            <div class="control-group">
                <label for="lcd-message">Send Custom LCD Message:</label>
                <div class="input-row">
                    <input type="text" id="lcd-message" placeholder="Enter message (max 20 characters)" maxlength="20">
                    <button id="send-lcd-msg" class="btn btn-primary">Send to LCD</button>
                </div>
            </div>

            <!-- LCD Backlight Control -->
            <div class="control-group">
                <label>LCD Backlight Control:</label>
                <div class="button-row">
                    <button id="lcd-on" class="btn btn-success">Backlight ON</button>
                    <button id="lcd-off" class="btn btn-warning">Backlight OFF</button>
                </div>
            </div>

            <!-- Device Actions -->
            <div class="control-group">
                <label>Device Actions:</label>
                <div class="button-row">
                    <button id="read-now" class="btn btn-info">Read Sensors Now</button>
                    <button id="restart-device" class="btn btn-danger">Restart Device</button>
                </div>
            </div>

            <!-- Command Status Display -->
            <div class="status-display">
                <strong>Command Status:</strong>
                <div id="command-status" class="command-feedback"></div>
            </div>
        </section>

        <!-- Chart Section -->
        <section class="chart-section">
            <div class="chart-header">
                <h2>Historical Data</h2>
                <div class="chart-controls">
                    <label>
                        Time Range:
                        <select id="range">
                            <option value="1h">Last 1 Hour</option>
                            <option value="6h">Last 6 Hours</option>
                            <option value="24h" selected>Last 24 Hours</option>
                        </select>
                    </label>
                    <button id="reload" class="btn btn-secondary">Reload Chart</button>
                    <label class="checkbox-label">
                        <input type="checkbox" id="auto-refresh" checked>
                        Auto-refresh (30s)
                    </label>
                </div>
            </div>
            <div class="chart-container">
                <canvas id="chart"></canvas>
            </div>
        </section>

    </main>

    <!-- LLM Recommendation Section -->
    <section class="llm-section">
        <label class="llm-label">Generate LLM Recommendation</label>
        <a href="llm.php" target="_blank" rel="noopener noreferrer">
            <button class="btn btn-primary llm-btn">LLM Recommendation</button>
        </a>
    </section>

    <!-- Footer -->
    <footer>
        <div class="footer-content">
            <div class="footer-item">
                <span>Last Update: <span id="last-update">Never</span></span>
            </div>
            <div class="footer-item">
                <span>MQTT Status: <span id="mqtt-status">Disconnected</span></span>
            </div>
        </div>
    </footer>

    <!-- Scripts -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/paho-mqtt/1.0.1/mqttws31.min.js"></script>
    <script src="assets/js/config.js"></script>
    <script src="assets/js/app.js"></script>
</body>
</html>