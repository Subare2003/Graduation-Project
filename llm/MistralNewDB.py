import paho.mqtt.client as mqtt
import requests
import json
import ollama
import time
import concurrent.futures
from datetime import datetime

MQTT_HOST = "ga7a11da.ala.us-east-1.emqxsl.com".strip() 
MQTT_PORT = 8883                                
MQTT_USER = "nwaf".strip()                              
MQTT_PASS = "ksu1957@".strip()                          
DEVICE_ID = "esp32_38pin_01".strip()                    

TOPIC_TEL = f"site/mysite01/device/{DEVICE_ID}/telemetry"

#API for insert 
API_URL = "https://scs.org.sa/api/insert_advice.php"
API_KEY = "change-me-TO-a-long-random-secret"

#5 readings array
data_buffer = {
    't': [],
    'h': [],
    'iaq': [],
    'p': []
}

#AI CD settings
LAST_INSIGHT_TIME = 0
INSIGHT_COOLDOWN = 30  #wait at least 30 seconds between each insight, can change if too much.

def get_dynamic_thresholds():
    """Fetches Riyadh weather and calculates ASHRAE 55 Adaptive Thermal Comfort."""
    try:
        url = "https://api.open-meteo.com/v1/forecast?latitude=24.7136&longitude=46.6753&daily=temperature_2m_mean&past_days=30&forecast_days=0&timezone=auto"
        response = requests.get(url, timeout=10)
        data = response.json()
        
        daily_means = data['daily']['temperature_2m_mean']
        valid_means = [m for m in daily_means if m is not None]
        t_out_mean = sum(valid_means) / len(valid_means)
        
    except Exception as e:
        fallback_means = { 1: 15.0, 2: 17.2, 3: 21.7, 4: 27.2, 5: 33.3, 6: 36.1, 7: 37.2, 8: 36.7, 9: 33.9, 10: 28.3, 11: 21.7, 12: 16.1 }
        current_month = datetime.now().month
        t_out_mean = fallback_means.get(current_month, 30.0)
    
    t_c = (0.31 * t_out_mean) + 17.8
    min_t = max(round(t_c - 3.5, 1), 18.0) #80% acceptability rate, lower bound capped per WHO standards.
    max_t = round(t_c + 3.5, 1)
    global tcG
    tcG = t_c 
    return min_t, max_t, 30.0, 60.0, 70.0, 100.0, 200.0, t_out_mean

def call_ollama(system_prompt, user_prompt):
    return ollama.chat(
        model='mistral-small', #Make sure this matches model name
        messages=[{'role': 'system', 'content': system_prompt}, {'role': 'user', 'content': user_prompt}],
        options={'temperature': 0.2, 'num_predict': 1500} 
    )

def process_ai_request(): #no longer mqtt callback
    """Calculates the average of the 5 readings, gets thresholds, and asks Mistral."""
    global LAST_INSIGHT_TIME #AI CD 
    if len(data_buffer['t']) < 5:
        return

    current_time = time.time()
    if current_time - LAST_INSIGHT_TIME < INSIGHT_COOLDOWN:
        print("[STATUS] AI is on cooldown. Skipping this batch of 5 readings.")
        return

    #Calc avg
    avg_t = round(sum(data_buffer['t']) / 5, 1)
    avg_h = round(sum(data_buffer['h']) / 5, 1)
    avg_iaq = round(sum(data_buffer['iaq']) / 5, 1)
    
    print(f"\n[AI TRIGGERED] Processing average of last 5 readings -> T:{avg_t}C | H:{avg_h}% | IAQ:{avg_iaq}")

    min_t, max_t, min_h, max_h, warn_h, iaq_w, iaq_d, t_out = get_dynamic_thresholds()
    
    t_state = "OPTIMAL"
    if avg_t < 18.0: t_state = "DANGER_COLD"
    elif avg_t < min_t: t_state = "WARNING_COLD"
    elif avg_t > max_t: t_state = "WARNING_HOT"
    
    h_state = "OPTIMAL"
    if avg_h < min_h: h_state = "DANGER_DRY"
    elif avg_h > warn_h: h_state = "DANGER_HUMID"
    elif avg_h > max_h: h_state = "WARNING_HUMID"
    
    iaq_state = "OPTIMAL"
    if avg_iaq > iaq_d: iaq_state = "DANGER_POLLUTED"
    elif avg_iaq > iaq_w: iaq_state = "WARNING_POLLUTED"

    if t_state == "OPTIMAL" and h_state == "OPTIMAL" and iaq_state == "OPTIMAL":
        final_advice = f"Average readings are {avg_t}°C and {avg_h}%. All environmental metrics are at optimal levels based on Riyadh's adaptive climate model. The environment is healthy and comfortable. No action is needed."
        LAST_INSIGHT_TIME = current_time #reset timer.
    else:
        system_prompt = f"""You are a health and safety smart home AI in Riyadh.
You will be provided with the average warning states of the home's environment over the last 5 readings. !!!Only use those the upcoming instruction for [Standard Target]!!! Know that the standards for a healthy air quality are Temp: MIN: 18°, MAX is: {tcG} +- 3.5 for an 80% acceptability rate. Humdity is safe from 30% to 60%.

You MUST formulate your advice using exactly this grammatical structure (or a shorter one only IF more concise):
"Average readings are [Temperature]°C and [Humidity]%. [Metric] is at a [Severity] level, [Metric] at that level can cause [Brief Health Effects], please [Action] to reach [Standard Target]."

Rules:
1. Do not use markdown. Do not say "Here is your advice". Just output the sentence.
2. Use your medical knowledge to fill in the [Brief Health Effects].
3. If multiple metrics are bad, combine them into one fluid sentence.
"""
        user_prompt = f"Current Average States:\nTemperature State: {t_state} (Avg: {avg_t}C)\nHumidity State: {h_state} (Avg: {avg_h}%)\nAir Quality State: {iaq_state}"
        
        attempt = 1
        final_advice = "AI Generation Failed after 3 attempts."
        while attempt <= 3:
            try:
                with concurrent.futures.ThreadPoolExecutor() as executor:
                    res = executor.submit(call_ollama, system_prompt, user_prompt).result(timeout=300)
                final_advice = res['message'].get('content', '').strip()
                LAST_INSIGHT_TIME = current_time #Reset timer
                break
            except Exception as e:
                print(f"[LLM ERROR] Attempt {attempt} failed: {e}")
                attempt += 1

    print(f"[AI RESPONSE] {final_advice}\n")
    
    
    
    #Insert into database via API
    try:
        payload = {
            "api_key": API_KEY,
            "advise": final_advice
        }
        headers = {
            "User-Agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36",
            "Accept": "application/json",
            "Content-Type": "application/json"
        }

        response = requests.post(API_URL, json=payload, headers=headers, timeout=10)
        
        if response.status_code == 200:
            print("[DATABASE] Advice successfully sent to API and inserted into scsorla4_env_adv.")
        else:
            print(f"[DATABASE ERROR] API returned status {response.status_code}: {response.text}")
            
    except Exception as api_err:
        print(f"[DATABASE ERROR] Failed to contact API: {api_err}")

def on_message(client, userdata, msg):
    try:
        #o/w
        if msg.topic == TOPIC_TEL:
            payload = json.loads(msg.payload.decode())
            bme = payload.get('bme680', {})
            
            t = float(bme.get('t', 0))
            h = float(bme.get('h', 0))
            p = float(bme.get('p', 0))
            iaq = float(bme.get('iaq', bme.get('gas_kohm', 0))) 
            
            #Store data and cap at 5 readings
            data_buffer['t'].append(t)
            data_buffer['h'].append(h)
            data_buffer['iaq'].append(iaq)
            data_buffer['p'].append(p)
            
            # trigger the AI once 5 readings are buffered
            if len(data_buffer['t']) == 5:
                print(f"[Live] T:{t:0.1f}C | H:{h:0.1f}% | IAQ:{iaq:0.1f} (Buffer: 5/5) -> Triggering AI")
                process_ai_request()
                
                # Clear the buffer after processing
                data_buffer['t'].clear()
                data_buffer['h'].clear()
                data_buffer['iaq'].clear()
                data_buffer['p'].clear()
            else:
                print(f"[Live] T:{t:0.1f}C | H:{h:0.1f}% | IAQ:{iaq:0.1f} (Buffer: {len(data_buffer['t'])}/5)")

    except Exception as e:
        print(f"[MQTT ERROR] {e}")

if __name__ == "__main__":
    print("Starting Autonomous Mistral 24B Edge Node...")
    
    client = mqtt.Client(mqtt.CallbackAPIVersion.VERSION2)
    client.username_pw_set(MQTT_USER, MQTT_PASS)
    client.tls_set() 
    client.on_message = on_message
    
    print("Fetching last 30 days of weather data for Riyadh...")
    m1, m2, h1, h2, wh, i1, i2, t_out = get_dynamic_thresholds()
    print(f"\n Riyadh ASHRAE/WHO Live Calibration ")
    print(f"30-Day Mean Outdoor Temp: {t_out:.1f}°C")
    print(f"Adaptive Indoor Temp Target (80% Acceptable): {m1}°C to {m2}°C (Min 18C)")
    print(f"Optimal Humidity: {h1}% to {h2}% (Warning >{h2}%, Danger >{wh}%)")
    print(f"Bosch IAQ Limits: <{i1} (Safe), >{i2} (Danger)")
    print("------------------------------------------\n")
    
    # Auto-Retry Connection 
    connected = False
    while not connected:
        try:
            print(f"Connecting to EMQX Broker at {MQTT_HOST}...")
            client.connect(MQTT_HOST, MQTT_PORT, keepalive=60)
            connected = True
        except TimeoutError:
            print("\n[NETWORK ERROR] Connection timed out! Port 8883 is being blocked.")
            print("-> Are you on a restrictive Wi-Fi network (like a university or corporate office)? They often block MQTT ports.")
            print("-> Retrying in 5 seconds...\n")
            time.sleep(5)
        except Exception as e:
            print(f"\n[MQTT ERROR] {e}")
            time.sleep(5)
    
    client.subscribe(TOPIC_TEL)
    
    print("Listening for live telemetry...")
    client.loop_forever()