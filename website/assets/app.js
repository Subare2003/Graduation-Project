// Enhanced ESP32 Dashboard v2.0 - Main Application with Gauges
let client = null;
let chart = null;
let autoRefreshInterval = null;

// Gauge objects
let gauges = {};

// DOM Elements
const els = {
  status: document.getElementById('status'),
  mqttStatus: document.getElementById('mqtt-status'),
  lastUpdate: document.getElementById('last-update'),
  
  // Gauge value displays
  tempValue: document.getElementById('temp-value'),
  humidityValue: document.getElementById('humidity-value'),
  pressureValue: document.getElementById('pressure-value'),
  airValue: document.getElementById('air-value'),
  voltageValue: document.getElementById('voltage-value'),
  gasValue: document.getElementById('gas-value'),
  
  // Controls
  lcdMessage: document.getElementById('lcd-message'),
  sendLcdMsg: document.getElementById('send-lcd-msg'),
  lcdOn: document.getElementById('lcd-on'),
  lcdOff: document.getElementById('lcd-off'),
  readNow: document.getElementById('read-now'),
  restartDevice: document.getElementById('restart-device'),
  
  // Chart controls
  range: document.getElementById('range'),
  reload: document.getElementById('reload'),
  autoRefresh: document.getElementById('auto-refresh'),
  
  // Status
  commandStatus: document.getElementById('command-status'),
  canvas: document.getElementById('chart')
};

function setStatus(message, isError = false) {
  els.status.textContent = message;
  els.status.style.color = isError ? '#dc3545' : '#666';
}

function setMqttStatus(status, connected = false) {
  els.mqttStatus.textContent = status;
  els.mqttStatus.style.color = connected ? '#28a745' : '#dc3545';
}

function updateLastUpdate() {
  const now = new Date();
  els.lastUpdate.textContent = now.toLocaleTimeString();
}

function showCommandStatus(message, isError = false) {
  els.commandStatus.style.color = isError ? '#dc3545' : '#28a745';
  els.commandStatus.textContent = `${new Date().toLocaleTimeString()}: ${message}`;
  
  // Auto clear after 10 seconds
  setTimeout(() => {
    if (els.commandStatus.textContent.includes(message)) {
      els.commandStatus.textContent = '';
    }
  }, 10000);
}

// Initialize all sensor gauges with canvas-gauges library
function initGauges() {
  console.log('[GAUGES] Initializing sensor gauges...');
  
  if (typeof RadialGauge === 'undefined') {
    console.error('[GAUGES] canvas-gauges library not loaded!');
    return;
  }
  
  // Temperature Gauge (0-50°C)
  const tempCanvas = document.getElementById('temp-gauge');
  if (tempCanvas) {
    gauges.temperature = new RadialGauge({
      renderTo: tempCanvas,
      width: 200,
      height: 200,
      units: '°C',
      minValue: 0,
      maxValue: 50,
      majorTicks: ['0', '10', '20', '30', '40', '50'],
      minorTicks: 2,
      strokeTicks: true,
      highlights: [
        { from: 0, to: 20, color: '#3498db' },
        { from: 20, to: 30, color: '#2ecc71' },
        { from: 30, to: 40, color: '#f39c12' },
        { from: 40, to: 50, color: '#e74c3c' }
      ],
      colorPlate: '#fff',
      borderShadowWidth: 0,
      borders: false,
      needleType: 'arrow',
      needleWidth: 2,
      needleCircleSize: 7,
      needleCircleOuter: true,
      needleCircleInner: false,
      animationDuration: 1500,
      animationRule: 'linear'
    });
    gauges.temperature.draw();
  }
  
  // Humidity Gauge (0-100%)
  const humidityCanvas = document.getElementById('humidity-gauge');
  if (humidityCanvas) {
    gauges.humidity = new RadialGauge({
      renderTo: humidityCanvas,
      width: 200,
      height: 200,
      units: '%',
      minValue: 0,
      maxValue: 100,
      majorTicks: ['0', '20', '40', '60', '80', '100'],
      minorTicks: 2,
      strokeTicks: true,
      highlights: [
        { from: 0, to: 30, color: '#e74c3c' },
        { from: 30, to: 40, color: '#f39c12' },
        { from: 40, to: 70, color: '#2ecc71' },
        { from: 70, to: 100, color: '#3498db' }
      ],
      colorPlate: '#fff',
      borderShadowWidth: 0,
      borders: false,
      needleType: 'arrow',
      needleWidth: 2,
      needleCircleSize: 7,
      needleCircleOuter: true,
      needleCircleInner: false,
      animationDuration: 1500,
      animationRule: 'linear'
    });
    gauges.humidity.draw();
  }
  
  // Pressure Gauge (900-1100 hPa)
  const pressureCanvas = document.getElementById('pressure-gauge');
  if (pressureCanvas) {
    gauges.pressure = new RadialGauge({
      renderTo: pressureCanvas,
      width: 200,
      height: 200,
      units: 'hPa',
      minValue: 900,
      maxValue: 1100,
      majorTicks: ['900', '950', '1000', '1050', '1100'],
      minorTicks: 2,
      strokeTicks: true,
      highlights: [
        { from: 900, to: 980, color: '#3498db' },
        { from: 980, to: 1020, color: '#2ecc71' },
        { from: 1020, to: 1100, color: '#f39c12' }
      ],
      colorPlate: '#fff',
      borderShadowWidth: 0,
      borders: false,
      needleType: 'arrow',
      needleWidth: 2,
      needleCircleSize: 7,
      needleCircleOuter: true,
      needleCircleInner: false,
      animationDuration: 1500,
      animationRule: 'linear'
    });
    gauges.pressure.draw();
  }
  
  // Air Quality Gauge (0-4 for ND, LOW, MED, HIGH, DANGER)
  const airCanvas = document.getElementById('air-gauge');
  if (airCanvas) {
    gauges.air = new RadialGauge({
      renderTo: airCanvas,
      width: 200,
      height: 200,
      units: '',
      minValue: 0,
      maxValue: 4,
      majorTicks: ['ND', 'LOW', 'MED', 'HIGH', 'DANGER'],
      minorTicks: 0,
      strokeTicks: true,
      highlights: [
        { from: 0, to: 0.8, color: '#95a5a6' },
        { from: 0.8, to: 1.6, color: '#2ecc71' },
        { from: 1.6, to: 2.4, color: '#f39c12' },
        { from: 2.4, to: 3.2, color: '#e74c3c' },
        { from: 3.2, to: 4, color: '#8e44ad' }
      ],
      colorPlate: '#fff',
      borderShadowWidth: 0,
      borders: false,
      needleType: 'arrow',
      needleWidth: 2,
      needleCircleSize: 7,
      needleCircleOuter: true,
      needleCircleInner: false,
      animationDuration: 1500,
      animationRule: 'linear'
    });
    gauges.air.draw();
  }
  
  // Voltage Gauge (0-3.3V)
  const voltageCanvas = document.getElementById('voltage-gauge');
  if (voltageCanvas) {
    gauges.voltage = new RadialGauge({
      renderTo: voltageCanvas,
      width: 200,
      height: 200,
      units: 'V',
      minValue: 0,
      maxValue: 3.3,
      majorTicks: ['0', '0.8', '1.6', '2.4', '3.2'],
      minorTicks: 2,
      strokeTicks: true,
      highlights: [
        { from: 0, to: 1.1, color: '#3498db' },
        { from: 1.1, to: 2.2, color: '#2ecc71' },
        { from: 2.2, to: 3.3, color: '#f39c12' }
      ],
      colorPlate: '#fff',
      borderShadowWidth: 0,
      borders: false,
      needleType: 'arrow',
      needleWidth: 2,
      needleCircleSize: 7,
      needleCircleOuter: true,
      needleCircleInner: false,
      animationDuration: 1500,
      animationRule: 'linear'
    });
    gauges.voltage.draw();
  }
  
  // IAQ Index Gauge (0-500, BSEC2 scale)
  const gasCanvas = document.getElementById('gas-gauge');
  if (gasCanvas) {
    gauges.gas = new RadialGauge({
      renderTo: gasCanvas,
      width: 200,
      height: 200,
      units: 'IAQ',
      minValue: 0,
      maxValue: 500,
      majorTicks: ['0', '100', '200', '300', '400', '500'],
      minorTicks: 2,
      strokeTicks: true,
      highlights: [
        { from: 0,   to: 50,  color: '#27ae60' },  // EXCELLENT
        { from: 50,  to: 100, color: '#2ecc71' },  // GOOD
        { from: 100, to: 150, color: '#f1c40f' },  // MODERATE
        { from: 150, to: 200, color: '#e67e22' },  // POOR
        { from: 200, to: 300, color: '#e74c3c' },  // BAD
        { from: 300, to: 500, color: '#8e44ad' }   // HAZARDOUS
      ],
      colorPlate: '#fff',
      borderShadowWidth: 0,
      borders: false,
      needleType: 'arrow',
      needleWidth: 2,
      needleCircleSize: 7,
      needleCircleOuter: true,
      needleCircleInner: false,
      animationDuration: 1500,
      animationRule: 'linear'
    });
    gauges.gas.draw();
  }
  
  console.log('[GAUGES] All gauges initialized successfully with canvas-gauges library');
}

// Enhanced MQTT Connection with Paho MQTT
function connectMqtt() {
  console.log('[MQTT] Starting connection process...');
  console.log('[MQTT] URL:', window.MQTT_WS_URL);
  console.log('[MQTT] User:', window.MQTT_WS_USER);
  
  if (!window.MQTT_WS_URL || !window.MQTT_WS_USER || !window.MQTT_WS_PASS) {
    console.error('[MQTT] Configuration missing!');
    setStatus("MQTT configuration missing", true);
    return;
  }

  if (!window.Paho || !window.Paho.MQTT || !window.Paho.MQTT.Client) {
    console.error('[MQTT] Library not loaded!');
    setStatus("MQTT library not loaded", true);
    return;
  }

  try {
    const clientId = `dashboard_${Math.random().toString(16).slice(2,10)}`;
    const wsUrl = new URL(window.MQTT_WS_URL);
    
    console.log(`[MQTT] Connecting to ${wsUrl.hostname}:${wsUrl.port}${wsUrl.pathname}`);
    setStatus("Connecting to MQTT...");
    setMqttStatus("Connecting...");

    client = new window.Paho.MQTT.Client(
      wsUrl.hostname,
      parseInt(wsUrl.port),
      wsUrl.pathname,
      clientId
    );

    // Connection lost handler
    client.onConnectionLost = function(responseObject) {
      console.warn("[MQTT] Connection lost:", responseObject.errorMessage);
      setStatus("MQTT connection lost", true);
      setMqttStatus("Disconnected");
      
      // Attempt reconnection after 5 seconds
      setTimeout(connectMqtt, 5000);
    };

    // Message arrived handler
    client.onMessageArrived = function(message) {
      console.log(`[MQTT] Message received on ${message.destinationName}:`, message.payloadString);
      
      if (message.destinationName === window.TOPIC_TEL) {
        processTelemetryMessage(message.payloadString);
      } else if (message.destinationName === window.TOPIC_ACK) {
        processCommandAck(message.payloadString);
      } else if (message.destinationName === window.TOPIC_STAT) {
        processStatusMessage(message.payloadString);
      }
    };

    // Connection options
    const connectOptions = {
      timeout: 10,
      useSSL: wsUrl.protocol === 'wss:',
      userName: window.MQTT_WS_USER,
      password: window.MQTT_WS_PASS,
      keepAliveInterval: 30,
      
      onSuccess: function() {
        console.log("[MQTT] Connected successfully");
        setStatus("MQTT connected - Loading data...");
        setMqttStatus("Connected", true);
        
        // Subscribe to topics
        client.subscribe(window.TOPIC_TEL, {qos: 0});
        client.subscribe(window.TOPIC_ACK, {qos: 0});
        client.subscribe(window.TOPIC_STAT, {qos: 0});
        
        console.log("[MQTT] Subscribed to telemetry, ack, and status topics");
        loadChartData(); // Load initial data
      },
      
      onFailure: function(error) {
        console.error("[MQTT] Connection failed:", error);
        console.error("[MQTT] Error code:", error.errorCode);
        console.error("[MQTT] Error message:", error.errorMessage);
        setStatus(`MQTT connection failed: ${error.errorMessage}`, true);
        setMqttStatus("Failed");
      }
    };

    client.connect(connectOptions);

  } catch (error) {
    console.error("[MQTT] Connection error:", error);
    setStatus(`MQTT error: ${error.message}`, true);
    setMqttStatus("Error");
  }
}

// Process incoming telemetry data and update gauges
function processTelemetryMessage(payloadString) {
  try {
    const data = JSON.parse(payloadString);
    console.log("[DATA] Telemetry received:", data);
    
    // Update MQ135 data (Air Quality and Voltage)
    if (data.mq135) {
      const level = data.mq135.level || 'ND';
      const voltage = data.mq135.v != null ? Number(data.mq135.v) : 0;
      
      // Update Air Quality gauge with level mapping
      if (gauges.air) {
        let airValue = 0;
        switch(level) {
          case 'ND': airValue = 0; break;
          case 'LOW': airValue = 1; break;
          case 'MED': airValue = 2; break;
          case 'HIGH': airValue = 3; break;
          case 'DANGER': airValue = 4; break;
        }
        gauges.air.value = airValue;
      }
      
      // Update Voltage gauge
      if (gauges.voltage) {
        gauges.voltage.value = voltage;
      }
      
      // Update display values
      if (els.airValue) els.airValue.textContent = level;
      if (els.voltageValue) els.voltageValue.textContent = voltage.toFixed(3) + ' V';
    }
    
    // Update BME680 data via BSEC2 (Temperature, Humidity, Pressure, IAQ)
    if (data.bme680) {
      const temp     = data.bme680.t   != null ? Number(data.bme680.t)   : 0;
      const humidity = data.bme680.h   != null ? Number(data.bme680.h)   : 0;
      const pressure = data.bme680.p   != null ? Number(data.bme680.p)   : 1013;
      const iaq      = data.bme680.iaq != null ? Number(data.bme680.iaq) : 0;
      const iaqLevel = data.bme680.iaq_level || '--';
      const iaqAcc   = data.bme680.iaq_accuracy != null ? Number(data.bme680.iaq_accuracy) : 0;
      const co2eq    = data.bme680.co2_eq  != null ? Number(data.bme680.co2_eq)  : 0;
      const vocEq    = data.bme680.voc_eq  != null ? Number(data.bme680.voc_eq)  : 0;

      // Update Temperature gauge
      if (gauges.temperature) {
        gauges.temperature.value = Math.max(0, Math.min(50, temp));
      }

      // Update Humidity gauge
      if (gauges.humidity) {
        gauges.humidity.value = Math.max(0, Math.min(100, humidity));
      }

      // Update Pressure gauge
      if (gauges.pressure) {
        gauges.pressure.value = Math.max(900, Math.min(1100, pressure));
      }

      // Update IAQ gauge
      if (gauges.gas) {
        gauges.gas.value = Math.max(0, Math.min(500, iaq));
      }

      // Update display values
      if (els.tempValue)     els.tempValue.textContent     = temp.toFixed(1) + ' °C';
      if (els.humidityValue) els.humidityValue.textContent = humidity.toFixed(0) + ' %';
      if (els.pressureValue) els.pressureValue.textContent = pressure.toFixed(1) + ' hPa';
      if (els.gasValue) {
        if (iaqAcc === 0) {
          els.gasValue.textContent = 'Calibrating...';
        } else {
          els.gasValue.textContent = iaq.toFixed(0) + ' · ' + iaqLevel + ' (A:' + iaqAcc + ')';
        }
      }

      console.log(`[BME680] IAQ=${iaq.toFixed(1)} (${iaqLevel}, acc=${iaqAcc}) CO2eq=${co2eq.toFixed(1)}ppm VOC=${vocEq.toFixed(2)}ppm`);
    }
    
    updateLastUpdate();
    setStatus("Live data updating - Gauges animated");
    console.log('[GAUGES] Updated all gauges with live data');
    
  } catch (error) {
    console.warn("[DATA] Invalid telemetry JSON:", error);
  }
}

// Process command acknowledgments
function processCommandAck(payloadString) {
  try {
    const ack = JSON.parse(payloadString);
    console.log("[CMD] Acknowledgment:", ack);
    
    if (ack.result && ack.cmd) {
      if (ack.result === "ok" || ack.result === "reading_triggered") {
        showCommandStatus(`Command '${ack.cmd}' executed successfully`);
      } else {
        showCommandStatus(`Command '${ack.cmd}' failed: ${ack.result}`, true);
      }
    }
  } catch (error) {
    console.warn("[CMD] Invalid ack JSON:", error);
  }
}

// Process status messages
function processStatusMessage(payloadString) {
  try {
    const status = JSON.parse(payloadString);
    console.log("[STATUS] Device status:", status);
    
    if (status.broker === "connected") {
      showCommandStatus(`Device ${status.device_id} connected (FW: ${status.fw})`);
    } else if (status.broker === "disconnected") {
      showCommandStatus(`Device ${status.device_id} disconnected`, true);
    }
  } catch (error) {
    console.warn("[STATUS] Invalid status JSON:", error);
  }
}

// Send MQTT command
function sendCommand(cmd, params = {}) {
  if (!client || !client.isConnected()) {
    showCommandStatus("MQTT not connected - cannot send command", true);
    return;
  }

  const command = {
    cmd: cmd,
    ts: Math.floor(Date.now() / 1000),
    ...params
  };

  try {
    const message = new window.Paho.MQTT.Message(JSON.stringify(command));
    message.destinationName = window.TOPIC_CMD;
    message.qos = 0;
    message.retained = false;

    client.send(message);
    console.log(`[CMD] Sent command:`, command);
    showCommandStatus(`Command '${cmd}' sent to device`);
    
  } catch (error) {
    console.error("[CMD] Send error:", error);
    showCommandStatus(`Failed to send command: ${error.message}`, true);
  }
}

// Chart initialization
function initChart() {
  const ctx = els.canvas.getContext('2d');
  chart = new Chart(ctx, {
    type: 'line',
    data: {
      labels: [],
      datasets: [
        {
          label: 'IAQ Index',
          data: [],
          yAxisID: 'y1',
          borderColor: '#667eea',
          backgroundColor: 'rgba(102, 126, 234, 0.1)',
          tension: 0.4
        },
        {
          label: 'Temp °C',
          data: [],
          yAxisID: 'y2',
          borderColor: '#ff4b2b',
          backgroundColor: 'rgba(255, 75, 43, 0.1)',
          tension: 0.4
        },
        {
          label: 'Hum %',
          data: [],
          yAxisID: 'y2',
          borderColor: '#56ab2f',
          backgroundColor: 'rgba(86, 171, 47, 0.1)',
          tension: 0.4
        }
      ]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      interaction: {
        mode: 'index',
        intersect: false
      },
      scales: {
        y1: {
          type: 'linear',
          position: 'left',
        title: {
          display: true,
          text: 'IAQ Index'
        }
        },
        y2: {
          type: 'linear',
          position: 'right',
          title: {
            display: true,
            text: 'Temperature / Humidity'
          },
          grid: {
            drawOnChartArea: false
          }
        }
      },
      plugins: {
        title: {
          display: true,
          text: 'Sensor Data History'
        },
        legend: {
          position: 'top'
        }
      }
    }
  });
}

// Load chart data from API with enhanced debugging
async function loadChartData() {
  try {
    const hours = els.range.value.replace('h', '');
    console.log(`[CHART] === Chart Loading Debug ===`);
    console.log(`[CHART] Selected range: "${els.range.value}", Parsed hours: "${hours}"`);
    console.log(`[CHART] Device ID: "${window.DEVICE_ID}"`);
    console.log(`[CHART] API Base: "${window.API_RANGE}"`);
    
    setStatus(`Loading last ${hours}h data...`);
    
    const apiUrl = `${window.API_RANGE}?device_id=${encodeURIComponent(window.DEVICE_ID)}&hours=${encodeURIComponent(hours)}`;
    console.log(`[CHART] Full API URL: ${apiUrl}`);
    
    console.log(`[CHART] Sending API request...`);
    const response = await fetch(apiUrl);
    console.log(`[CHART] Response status: ${response.status} ${response.statusText}`);
    console.log(`[CHART] Response headers:`, Object.fromEntries(response.headers.entries()));
    
    if (!response.ok) {
      const errorText = await response.text();
      console.error(`[CHART] Error response body:`, errorText);
      throw new Error(`HTTP ${response.status}: ${response.statusText}`);
    }
    
    const responseText = await response.text();
    console.log(`[CHART] Raw response text:`, responseText.substring(0, 500) + (responseText.length > 500 ? '...' : ''));
    
    let rows;
    try {
      rows = JSON.parse(responseText);
    } catch (parseError) {
      console.error(`[CHART] JSON parse error:`, parseError);
      console.error(`[CHART] Response was not valid JSON:`, responseText);
      throw new Error(`Invalid JSON response: ${parseError.message}`);
    }
    
    console.log(`[CHART] Parsed data:`, rows);
    console.log(`[CHART] Data type:`, typeof rows, `Is Array:`, Array.isArray(rows));
    console.log(`[CHART] Loaded ${rows.length} data points for ${hours}h range`);
    
    if (rows.length === 0) {
      console.warn(`[CHART] ⚠️ No data returned for ${hours}h range`);
      setStatus(`No data available for ${hours}h range`, true);
      // Clear the chart
      chart.data.labels = [];
      chart.data.datasets[0].data = [];
      chart.data.datasets[1].data = [];
      chart.data.datasets[2].data = [];
      chart.update('none');
      return;
    }
    
    // Log first few data points
    console.log(`[CHART] First 3 data points:`, rows.slice(0, 3));
    
    const labels = rows.map(r => r.ts);
    const iaq  = rows.map(r => r.bme_iaq ? Number(r.bme_iaq) : null);
    const temp = rows.map(r => r.bme_t   ? Number(r.bme_t)   : null);
    const hum  = rows.map(r => r.bme_h   ? Number(r.bme_h)   : null);

    console.log(`[CHART] Processed data:`);
    console.log(`[CHART] - Labels: ${labels.length} items`);
    console.log(`[CHART] - IAQ:  ${iaq.filter(v => v !== null).length}/${iaq.length} valid points`);
    console.log(`[CHART] - Temp: ${temp.filter(v => v !== null).length}/${temp.length} valid points`);
    console.log(`[CHART] - Humidity: ${hum.filter(v => v !== null).length}/${hum.length} valid points`);

    chart.data.labels = labels;
    chart.data.datasets[0].data = iaq;
    chart.data.datasets[1].data = temp;
    chart.data.datasets[2].data = hum;
    chart.update('none'); // No animation for faster update
    
    console.log(`[CHART] ✅ Chart updated successfully`);
    setStatus(`Chart loaded (${rows.length} points for ${hours}h)`);
    
  } catch (error) {
    console.error(`[CHART] ❌ Load error:`, error);
    console.error(`[CHART] Error stack:`, error.stack);
    setStatus(`Chart load failed: ${error.message}`, true);
  }
}

// Auto refresh functionality
function startAutoRefresh() {
  if (autoRefreshInterval) {
    clearInterval(autoRefreshInterval);
  }
  
  if (els.autoRefresh.checked) {
    autoRefreshInterval = setInterval(loadChartData, 30000); // 30 seconds
    console.log("[REFRESH] Auto-refresh enabled (30s)");
  }
}

// Event Listeners
document.addEventListener('DOMContentLoaded', function() {
  console.log("[APP] Dashboard v2.0 with Gauges initializing...");
  
  // Initialize gauges first
  initGauges();
  
  // Initialize chart
  initChart();
  
  // Connect to MQTT
  connectMqtt();
  
  // Control event listeners
  els.sendLcdMsg.addEventListener('click', function() {
    const message = els.lcdMessage.value.trim();
    if (message) {
      sendCommand('lcd_msg', { text: message });
      els.lcdMessage.value = '';
    } else {
      showCommandStatus("Please enter a message", true);
    }
  });

  els.lcdMessage.addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
      els.sendLcdMsg.click();
    }
  });

  els.lcdOn.addEventListener('click', function() {
    sendCommand('lcd_backlight', { state: 'on' });
  });

  els.lcdOff.addEventListener('click', function() {
    sendCommand('lcd_backlight', { state: 'off' });
  });

  els.readNow.addEventListener('click', function() {
    sendCommand('read_now');
  });

  els.restartDevice.addEventListener('click', function() {
    if (confirm('Are you sure you want to restart the ESP32 device?')) {
      sendCommand('restart');
    }
  });

  // Chart controls with debug logging
  els.reload.addEventListener('click', function() {
    console.log('[CHART] Reload button clicked');
    loadChartData();
  });
  
  els.range.addEventListener('change', function() {
    console.log('[CHART] Range changed to:', els.range.value);
    loadChartData();
  });
  
  els.autoRefresh.addEventListener('change', startAutoRefresh);
  
  // Start auto-refresh
  startAutoRefresh();
  
  console.log("[APP] Dashboard initialized successfully");
});