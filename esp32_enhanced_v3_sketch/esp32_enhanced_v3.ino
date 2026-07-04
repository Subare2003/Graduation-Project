/*
  ESP32 Enhanced IoT Environmental Monitor v3.0
  ==============================================

  SYSTEM OVERVIEW:
  This firmware creates a comprehensive IoT environmental monitoring system that:
  1. Reads environmental sensors using Bosch BSEC2 algorithm for meaningful output
  2. Displays real-time data on LCD with custom message support
  3. Sends telemetry data via dual channels: MQTT + HTTP POST
  4. Receives and processes remote commands via MQTT
  5. Maintains persistent connection with automatic reconnection

  COMMUNICATION ARCHITECTURE:
  - MQTT over TLS (port 8883) to EMQX Cloud for real-time bidirectional communication
  - HTTP(S) POST to web server ingest.php for data storage with API key authentication
  - I2C communication with BME680 sensor and 20x4 LCD display
  - Analog reading from MQ135 gas sensor via ADC

  SENSOR HARDWARE:
  - BME680: Driven by Bosch BSEC2 algorithm (I2C: 0x76/0x77)
      Outputs: IAQ index, CO2 equivalent (ppm), breath VOC (ppm),
               compensated temperature (°C), compensated humidity (%RH), pressure (hPa)
  - MQ135: Air quality gas sensor (Analog: GPIO32/ADC1_CH4) - relative level only
  - LCD: 20x4 I2C LCD display for real-time status and sensor readings

  BSEC2 IAQ SCALE:
  0-50   EXCELLENT  |  50-100 GOOD  |  100-150 MODERATE
  150-200 POOR      |  200-300 BAD  |  300+    HAZARDOUS
  Accuracy: 0=calibrating, 1=low, 2=medium, 3=high (takes 24-48h to reach 3)
  BSEC2 state is saved to NVS flash every 25 minutes once accuracy=3,
  so calibration persists across reboots.

  COMMAND SYSTEM:
  Supports remote control via MQTT JSON commands:
  - lcd_msg: Display custom text message on LCD
  - lcd_backlight: Control LCD backlight (on/off)
  - restart: Remote device restart
  - read_now: Trigger immediate sensor reading

  DATA FLOW:
  ESP32 → Sensors → BSEC2 Processing → LCD Display + MQTT Publish + HTTP POST
  Web Dashboard ← MQTT Broker ← ESP32 ← Command Processing ← MQTT Subscribe

  TIMING:
  - BSEC2 sensor readings: ~3.3 seconds (LP mode, driven by library)
  - MQTT/HTTP publish: Every 20 seconds
  - LCD updates: Every 1 second
  - Network reconnection: Automatic on failure
  - Custom LCD messages: 10 second display duration
*/

// ================== LIBRARY INCLUDES ==================
#include <WiFi.h>
#include <WiFiClientSecure.h>
#include <HTTPClient.h>
#include <PubSubClient.h>
#include <Wire.h>
#include <LiquidCrystal_I2C.h>
#include <bsec2.h>        // Bosch BSEC2 algorithm: IAQ, CO2eq, VOCeq, compensated T/H
#include <Preferences.h>  // NVS (flash) storage for BSEC2 state persistence
#include "secrets.h"

// ================== HARDWARE OBJECTS ==================
LiquidCrystal_I2C lcd(LCD_I2C_ADDR, 20, 4);
Bsec2 envSensor;      // BSEC2-driven BME680
Preferences prefs;    // NVS storage for BSEC2 calibration state

// ================== LCD DISPLAY MANAGEMENT ==================
bool lcdBacklightOn = true;
String customLcdMessage = "";
bool showCustomMessage = false;
unsigned long customMessageStartTime = 0;
const unsigned long CUSTOM_MESSAGE_DURATION = 10000;

// ================== BSEC2 OUTPUT VARIABLES ==================
// Updated by the BSEC2 callback every ~3.3 seconds
float     bsecIaq       = 0.0f;
uint8_t   bsecIaqAcc    = 0;      // 0=calibrating, 1=low, 2=med, 3=high
float     bsecCo2Eq     = 0.0f;  // CO2 equivalent in ppm
float     bsecVocEq     = 0.0f;  // Breath VOC equivalent in ppm
float     bsecTemp      = NAN;   // Compensated temperature (°C)
float     bsecHumidity  = NAN;   // Compensated humidity (%RH)
float     bsecPressure  = NAN;   // Pressure in hPa
bool      bsecHaveData  = false;
unsigned long bsecLastSaveMs = 0;
const unsigned long BSEC_STATE_SAVE_INTERVAL_MS = 1500000UL; // save every ~25 min

// BSEC2 virtual sensors to subscribe to (LP = ~3.3 sec sample rate)
static const bsecSensor BSEC_SENSOR_LIST[] = {
  BSEC_OUTPUT_IAQ,
  BSEC_OUTPUT_CO2_EQUIVALENT,
  BSEC_OUTPUT_BREATH_VOC_EQUIVALENT,
  BSEC_OUTPUT_SENSOR_HEAT_COMPENSATED_TEMPERATURE,
  BSEC_OUTPUT_SENSOR_HEAT_COMPENSATED_HUMIDITY,
  BSEC_OUTPUT_RAW_PRESSURE,
  BSEC_OUTPUT_STABILIZATION_STATUS,
  BSEC_OUTPUT_RUN_IN_STATUS
};

// ================== BSEC2 IAQ LEVEL ==================
static const char* iaqLevel(float iaq) {
  if (iaq <  50) return "EXCEL";
  if (iaq < 100) return "GOOD";
  if (iaq < 150) return "MOD";
  if (iaq < 200) return "POOR";
  if (iaq < 300) return "BAD";
  return "HAZARD";
}

// ================== BSEC2 STATE PERSISTENCE (NVS) ==================
static void loadBsecState() {
  uint8_t state[BSEC_MAX_STATE_BLOB_SIZE];
  prefs.begin("bsec2", true);
  size_t len = prefs.getBytes("state", state, BSEC_MAX_STATE_BLOB_SIZE);
  prefs.end();
  if (len == BSEC_MAX_STATE_BLOB_SIZE) {
    envSensor.setState(state);
    Serial.println(F("BSEC2: calibration state loaded from NVS"));
  } else {
    Serial.println(F("BSEC2: no saved state — starting fresh calibration"));
  }
}

static void saveBsecState() {
  uint8_t state[BSEC_MAX_STATE_BLOB_SIZE];
  if (envSensor.getState(state)) {
    prefs.begin("bsec2", false);
    prefs.putBytes("state", state, BSEC_MAX_STATE_BLOB_SIZE);
    prefs.end();
    Serial.println(F("BSEC2: calibration state saved to NVS"));
  }
}

// ================== BSEC2 DATA CALLBACK ==================
/**
 * Called automatically by envSensor.run() when new BSEC2 data is ready (~3.3s).
 * Stores latest outputs into global variables for use by publish/LCD logic.
 */
static void onBsecData(const bme68xData data, const bsecOutputs outputs, Bsec2 bsec) {
  if (!outputs.nOutputs) return;

  for (uint8_t i = 0; i < outputs.nOutputs; i++) {
    const bsecData o = outputs.output[i];
    switch (o.sensor_id) {
      case BSEC_OUTPUT_IAQ:
        bsecIaq    = o.signal;
        bsecIaqAcc = o.accuracy;
        break;
      case BSEC_OUTPUT_CO2_EQUIVALENT:
        bsecCo2Eq = o.signal;
        break;
      case BSEC_OUTPUT_BREATH_VOC_EQUIVALENT:
        bsecVocEq = o.signal;
        break;
      case BSEC_OUTPUT_SENSOR_HEAT_COMPENSATED_TEMPERATURE:
        bsecTemp = o.signal;
        break;
      case BSEC_OUTPUT_SENSOR_HEAT_COMPENSATED_HUMIDITY:
        bsecHumidity = o.signal;
        break;
      case BSEC_OUTPUT_RAW_PRESSURE:
        bsecPressure = o.signal; // BSEC2 already returns pressure in hPa
        break;
    }
  }
  bsecHaveData = true;

  // Persist calibration state once accuracy is high, every 25 min
  if (bsecIaqAcc == 3 && (millis() - bsecLastSaveMs > BSEC_STATE_SAVE_INTERVAL_MS)) {
    saveBsecState();
    bsecLastSaveMs = millis();
  }
}

// ================== BSEC2 / BME680 SETUP ==================
static void setupBSEC2() {
  if (!envSensor.begin(BME680_ADDR, Wire)) {
    Serial.print(F("BSEC2/BME680 init FAILED, status="));
    Serial.println(envSensor.status);
    return;
  }
  loadBsecState();
  envSensor.attachCallback(onBsecData);
  if (!envSensor.updateSubscription(
        const_cast<bsecSensor*>(BSEC_SENSOR_LIST),
        sizeof(BSEC_SENSOR_LIST) / sizeof(BSEC_SENSOR_LIST[0]),
        BSEC_SAMPLE_RATE_LP)) {
    Serial.print(F("BSEC2 subscription FAILED, status="));
    Serial.println(envSensor.status);
    return;
  }
  Serial.println(F("BSEC2 OK — IAQ/CO2eq/VOCeq active"));
}

// ================== MQ135 AIR QUALITY SENSOR ==================
/**
 * MQ135 Gas Sensor for Air Quality Monitoring
 * - Detects: CO2, NH3, NOx, alcohol, benzene, smoke
 * - Output: Analog voltage proportional to gas concentration
 * - Connection: GPIO32 (ADC1_CH4) for noise-free WiFi operation
 * - Calibration: Uses baseline measurement during clean air period
 * 
 * IMPORTANT: MQ135 requires 24-48 hours warm-up for stable readings
 * First 2 minutes used for baseline calibration in clean air
 */
const int MQ135_ADC_PIN = 32; // GPIO32 (ADC1_CH4) - avoids ADC2 WiFi interference

/**
 * Read MQ135 sensor with multiple samples and averaging for noise reduction
 * @param samples Number of readings to average (default: 9)
 * @param gapMs Delay between samples in milliseconds (default: 15ms)
 * @return Averaged raw ADC value (0-4095)
 */
static int readMQ135Raw(int samples=9, int gapMs=15){
  long s=0; 
  // Take multiple samples and average to reduce noise
  for(int i=0; i<samples; i++){ 
    s += analogRead(MQ135_ADC_PIN); 
    delay(gapMs); 
  }
  return (int)(s/samples);
}

/**
 * Convert raw ADC reading to voltage
 * ESP32 ADC: 12-bit (0-4095) with 3.3V reference
 * @param raw Raw ADC value (0-4095)
 * @return Voltage (0.0-3.3V)
 */
static float rawToVoltage(int raw){ 
  return 3.3f * raw / 4095.0f; 
}

/**
 * Determine air quality level based on relative change from baseline
 * Baseline is established during first 2 minutes in clean air
 * @param rel Relative ratio (current_voltage / baseline_voltage)
 * @return Air quality level string
 */
static const char* levelFromRel(float rel){
  if (rel < 0.95f) return "ND";      // No Detection (below baseline)
  if (rel < 1.10f) return "LOW";     // Low pollution (0-10% above baseline)
  if (rel < 1.30f) return "MED";     // Medium pollution (10-30% above baseline)
  if (rel < 1.60f) return "HIGH";    // High pollution (30-60% above baseline)
  return "DANGER";                   // Dangerous levels (>60% above baseline)
}

// MQ135 calibration variables
float mqBaseline = 1.0f;           // Baseline voltage in clean air (set after 2min warmup)
unsigned long warmStartMs = 0;     // Timestamp when system started (for warmup period)

// ---- MQTT over TLS ----
WiFiClientSecure tlsClient;
PubSubClient mqtt(tlsClient);
unsigned long lastPubMs=0, lastLCDMs=0;
const unsigned long PUB_INTERVAL_MS = 20000;

String TOPIC_TEL  = String("site/") + SITE_ID + "/device/" + DEVICE_ID + "/telemetry";
String TOPIC_STAT = String("site/") + SITE_ID + "/device/" + DEVICE_ID + "/status";
String TOPIC_CMD  = String("site/") + SITE_ID + "/device/" + DEVICE_ID + "/cmd";
String TOPIC_ACK  = String("site/") + SITE_ID + "/device/" + DEVICE_ID + "/cmd_ack";

static void wifiConnect(){
  WiFi.mode(WIFI_STA);
  WiFi.begin(WIFI_SSID, WIFI_PASS);
  Serial.print(F("WiFi: connecting"));
  lcd.clear(); lcd.setCursor(0,0); lcd.print("WiFi: connecting...");
  while (WiFi.status()!=WL_CONNECTED){ delay(500); Serial.print("."); }
  Serial.print(F("\nWiFi OK. IP=")); Serial.println(WiFi.localIP());
  lcd.setCursor(0,0); lcd.print("WiFi: OK          ");
  String ipDisplay = String("IP: ") + WiFi.localIP().toString() + "   ";
  lcd.setCursor(0,1); lcd.print(ipDisplay);
}

// Simple command processing - no JSON library needed
static void processCommandSimple(const String& cmd, const String& text, const String& state) {
  String result = "ok";
  
  Serial.println("Processing command: " + cmd);
  
  if (cmd == "lcd_msg") {
    if (text.length() > 0) {
      customLcdMessage = text;
      showCustomMessage = true;
      customMessageStartTime = millis();
      Serial.println("LCD custom message set: " + customLcdMessage);
    } else {
      result = "error: missing text parameter";
      Serial.println("Error: LCD message missing text");
    }
  }
  else if (cmd == "restart") {
    Serial.println("Restart command received - restarting in 2 seconds...");
    String restartAck = "{\"cmd\":\"restart\",\"result\":\"restarting\",\"ts\":" + String(millis()/1000) + "}";
    mqtt.publish(TOPIC_ACK.c_str(), restartAck.c_str(), false);
    delay(2000);
    ESP.restart();
  }
  else if (cmd == "lcd_backlight") {
    if (state.length() > 0) {
      if (state == "on") {
        lcdBacklightOn = true;
        lcd.backlight();
        Serial.println("LCD backlight ON");
      } else if (state == "off") {
        lcdBacklightOn = false;
        lcd.noBacklight();
        Serial.println("LCD backlight OFF");
      } else {
        result = "error: state must be 'on' or 'off'";
        Serial.println("Error: invalid backlight state: " + state);
      }
    } else {
      result = "error: missing state parameter";
      Serial.println("Error: backlight command missing state");
    }
  }
  else if (cmd == "read_now") {
    Serial.println("Immediate reading requested");
    lastPubMs = 0; // Force immediate reading
    result = "reading_triggered";
  }
  else {
    result = "error: unknown command";
    Serial.println("Error: Unknown command: " + cmd);
  }
  
  // Send acknowledgment
  String ack = "{\"device_id\":\"" + String(DEVICE_ID) + "\",\"cmd\":\"" + cmd + "\",\"result\":\"" + result + "\",\"ts\":" + String(millis()/1000) + "}";
  mqtt.publish(TOPIC_ACK.c_str(), ack.c_str(), false);
  Serial.println("Sent acknowledgment: " + ack);
}

// Simple JSON parser - no library needed
String extractJsonValue(const String& json, const String& key) {
  String searchKey = "\"" + key + "\":\"";
  int startPos = json.indexOf(searchKey);
  if (startPos == -1) return "";
  
  startPos += searchKey.length();
  int endPos = json.indexOf('"', startPos);
  if (endPos == -1) return "";
  
  return json.substring(startPos, endPos);
}

static void onMqttMessage(char* topic, byte* payload, unsigned int len){
  String cmdPayload; cmdPayload.reserve(len+1);
  for (unsigned i=0;i<len;i++) cmdPayload+=(char)payload[i];
  
  Serial.printf("MQTT CMD %s: %s\n", topic, cmdPayload.c_str());
  
  // Simple string-based JSON parsing
  String cmd = extractJsonValue(cmdPayload, "cmd");
  String text = extractJsonValue(cmdPayload, "text");
  String state = extractJsonValue(cmdPayload, "state");
  
  if (cmd.length() == 0) {
    Serial.println("No cmd found in message");
    String ack = "{\"device_id\":\"" + String(DEVICE_ID) + "\",\"result\":\"error: missing cmd parameter\",\"ts\":" + String(millis()/1000) + "}";
    mqtt.publish(TOPIC_ACK.c_str(), ack.c_str(), false);
    return;
  }
  
  Serial.println("Parsed cmd: " + cmd);
  if (text.length() > 0) Serial.println("Parsed text: " + text);
  if (state.length() > 0) Serial.println("Parsed state: " + state);
  
  // Process the command
  processCommandSimple(cmd, text, state);
}

static void mqttConnect(){
  tlsClient.setCACert(MEQX_CA_CERT_PEM);
  mqtt.setServer(MQTT_HOST, MQTT_PORT);
  mqtt.setCallback(onMqttMessage);
  mqtt.setBufferSize(512); // Default 256 bytes is too small for BSEC2 payload
  String lwt = String("{\"device_id\":\"")+DEVICE_ID+"\",\"broker\":\"disconnected\"}";
  Serial.print(F("MQTT: connecting... "));
  if (mqtt.connect(DEVICE_ID, MQTT_USER, MQTT_PASS, TOPIC_STAT.c_str(), 1, true, lwt.c_str())){
    Serial.println(F("OK"));
    String status = String("{\"device_id\":\"")+DEVICE_ID+"\",\"broker\":\"connected\",\"fw\":\"" FW_VER "\",\"ip\":\""+WiFi.localIP().toString()+"\"}";
    mqtt.publish(TOPIC_STAT.c_str(), status.c_str(), true);
    mqtt.subscribe(TOPIC_CMD.c_str());
  } else {
    Serial.print(F("FAIL rc=")); Serial.println(mqtt.state());
  }
}

// ---- HTTPS/HTTP POST to ingest.php with X-API-Key ----
static bool postToIngest(const String& jsonPayload){
  if (WiFi.status()!=WL_CONNECTED) return false;
  HTTPClient http; bool ok=false;
  if (String(INGEST_URL).startsWith("https://")){
    WiFiClientSecure https; https.setInsecure();
    if (http.begin(https, INGEST_URL)){
      http.addHeader("Content-Type","application/json");
      http.addHeader("X-API-Key", API_KEY);
      int code=http.POST(jsonPayload);
      Serial.printf("HTTP POST ingest: %d\n", code);
      ok=(code>=200 && code<300);
      http.end();
    }
  } else {
    WiFiClient plain;
    if (http.begin(plain, INGEST_URL)){
      http.addHeader("Content-Type","application/json");
      http.addHeader("X-API-Key", API_KEY);
      int code=http.POST(jsonPayload);
      Serial.printf("HTTP POST ingest: %d\n", code);
      ok=(code>=200 && code<300);
      http.end();
    }
  }
  return ok;
}

// ================== LCD UPDATE ==================
/**
 * Row 0: WiFi status + firmware version
 * Row 1: IAQ index + level label + accuracy (or "calibrating" if acc=0)
 * Row 2: Compensated temperature + humidity  (from BSEC2)
 * Row 3: MQTT + HTTP connection status
 * Custom message overrides all rows for 10 seconds when active.
 */
static void updateLCD(float mqV, const char* mqLvl, bool mqok, bool httpok) {
  if (showCustomMessage) {
    if (millis() - customMessageStartTime > CUSTOM_MESSAGE_DURATION) {
      showCustomMessage = false;
      customLcdMessage = "";
    } else {
      lcd.setCursor(0,0); lcd.print("CUSTOM MESSAGE:     ");
      lcd.setCursor(0,1); lcd.print(customLcdMessage.substring(0,20)); lcd.print("                    ");
      lcd.setCursor(0,2); lcd.print("                    ");
      unsigned long rem = (CUSTOM_MESSAGE_DURATION - (millis() - customMessageStartTime)) / 1000;
      lcd.setCursor(0,3); lcd.print("Auto-clear in "); lcd.print(rem); lcd.print("s  ");
      return;
    }
  }

  // Row 0: WiFi + FW version
  lcd.setCursor(0,0); lcd.print("WiFi OK  FW "); lcd.print(FW_VER); lcd.print("   ");

  // Row 1: IAQ (BSEC2)
  lcd.setCursor(0,1);
  if (bsecIaqAcc == 0) {
    lcd.print("IAQ: calibrating... ");
  } else {
    String row1 = "IAQ:" + String((int)bsecIaq) + " " + String(iaqLevel(bsecIaq)) + " A:" + String(bsecIaqAcc);
    while (row1.length() < 20) row1 += ' ';
    lcd.print(row1);
  }

  // Row 2: Compensated temperature + humidity
  lcd.setCursor(0,2);
  if (!isnan(bsecTemp)) {
    String row2 = "T:" + String(bsecTemp,1) + " H:" + String(bsecHumidity,0) + "%";
    while (row2.length() < 20) row2 += ' ';
    lcd.print(row2);
  } else {
    lcd.print("BME680: no data     ");
  }

  // Row 3: MQTT + HTTP status
  lcd.setCursor(0,3);
  lcd.print("MQTT:"); lcd.print(mqok  ? "OK " : "NO ");
  lcd.print("HTTP:"); lcd.print(httpok ? "OK  " : "NO  ");
}

// ================== SETUP ==================
void setup() {
  Serial.begin(115200);
  Wire.begin();
  lcd.init();
  if (lcdBacklightOn) lcd.backlight();
  lcd.setCursor(0,0); lcd.print("Booting FW "); lcd.print(FW_VER);
  setupBSEC2();
  pinMode(MQ135_ADC_PIN, INPUT);
  warmStartMs = millis();
  wifiConnect();
  mqttConnect();
}

// ================== LOOP ==================
void loop() {
  if (WiFi.status() != WL_CONNECTED) wifiConnect();
  if (!mqtt.connected()) mqttConnect();
  mqtt.loop();

  // Drive BSEC2 — fires onBsecData() callback when new data is ready (~3.3s)
  if (!envSensor.run()) {
    if (envSensor.status < BSEC_OK) {
      Serial.printf("BSEC2 error: %d\n", envSensor.status);
    }
  }

  // MQ135 relative reading
  int raw = readMQ135Raw();
  float v = rawToVoltage(raw);
  if (mqBaseline == 1.0f && millis() - warmStartMs > 120000UL) {
    mqBaseline = v > 0.1f ? v : 1.0f;
  }
  float rel = (mqBaseline > 0.1f) ? (v / mqBaseline) : 1.0f;
  const char* mqLvl = levelFromRel(rel);

  // LCD refresh every second
  if (millis() - lastLCDMs > 1000) {
    lastLCDMs = millis();
    updateLCD(v, mqLvl, mqtt.connected(), true);
  }

  // Publish telemetry every 20 seconds
  if (millis() - lastPubMs > PUB_INTERVAL_MS) {
    lastPubMs = millis();

    String payload = "{";
    payload += "\"device_id\":\"" DEVICE_ID "\",";
    payload += "\"ts\":" + String(millis() / 1000) + ",";
    payload += "\"mq135\":{\"v\":" + String(v, 3) + ",\"rel\":" + String(rel, 2) + ",\"level\":\"" + String(mqLvl) + "\"}";

    if (bsecHaveData) {
      payload += ",\"bme680\":{";
      payload += "\"iaq\":"          + String(bsecIaq,      1) + ",";
      payload += "\"iaq_accuracy\":" + String(bsecIaqAcc)      + ",";
      payload += "\"iaq_level\":\"" + String(iaqLevel(bsecIaq)) + "\",";
      payload += "\"co2_eq\":"       + String(bsecCo2Eq,   1) + ",";
      payload += "\"voc_eq\":"       + String(bsecVocEq,   2) + ",";
      payload += "\"t\":"            + String(bsecTemp,    1) + ",";
      payload += "\"h\":"            + String(bsecHumidity,1) + ",";
      payload += "\"p\":"            + String(bsecPressure,1) + "}";
    }

    payload += ",\"rssi\":" + String(WiFi.RSSI());
    payload += ",\"vcc\":3.30";
    payload += ",\"fw\":\"" FW_VER "\"}";

    bool mqok   = mqtt.publish(TOPIC_TEL.c_str(), payload.c_str(), false);
    bool httpok = postToIngest(payload);
    Serial.printf("Published. MQTT=%d HTTP=%d\n", mqok, httpok);
    Serial.printf("IAQ=%.1f(acc=%d) CO2eq=%.1fppm VOC=%.2fppm T=%.1fC H=%.1f%%\n",
                  bsecIaq, bsecIaqAcc, bsecCo2Eq, bsecVocEq, bsecTemp, bsecHumidity);
  }
}
