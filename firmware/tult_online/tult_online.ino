#include <Arduino.h>
#include <SPI.h>
#include <MFRC522.h>
#include <ESP32Servo.h>
#include <WiFi.h>
#include <HTTPClient.h>
#include <ArduinoJson.h>
#include <Preferences.h>
#include "config.h"

// Same wiring as submission/code_hardware. Physical obstacle IR: active LOW.
constexpr uint8_t CS_ENTRY=5, CS_EXIT=17, RST=22, IR=32, SERVO=25, BUZZER=14, GREEN=26, RED=27;
constexpr bool IR_ACTIVE_HIGH=false;
constexpr unsigned long GATE_TIMEOUT=10000, SAFE_DELAY=700;
MFRC522 entry(CS_ENTRY,RST), exitReader(CS_EXIT,RST);
Servo gate;
Preferences store;
enum Mode { READY, WAIT_PASS, REPORT, RECONCILE };
Mode mode=READY;
String transactionId, outcome, lastUid;
unsigned long openedAt=0, clearedAt=0, lastRead=0, lastNetwork=0, lastReconnect=0, feedbackUntil=0;
bool vehicleSeen=false;

bool sensorActive() { return digitalRead(IR)==(IR_ACTIVE_HIGH?HIGH:LOW); }
void leds(bool green,bool red) { digitalWrite(GREEN,green); digitalWrite(RED,red); }
void reject(const String &message) {
  Serial.println("[DENIED] "+message); leds(false,true); tone(BUZZER,520,300); feedbackUntil=millis()+1200;
}
int post(const char *path,JsonDocument &body,JsonDocument &response) {
  if (WiFi.status()!=WL_CONNECTED) return -1;
  HTTPClient http;
  http.setConnectTimeout(1200); http.setTimeout(1500);
  http.begin(String(API_BASE)+path);
  http.addHeader("Content-Type","application/json");
  http.addHeader("Accept","application/json");
  http.addHeader("Authorization",String("Bearer ")+DEVICE_TOKEN);
  String payload; serializeJson(body,payload);
  int code=http.POST(payload);
  if (code>0) deserializeJson(response,http.getString());
  http.end(); return code;
}
void clearStored() { store.remove("tx"); store.remove("outcome"); transactionId=""; outcome=""; mode=READY; leds(false,false); }
void queueReport(const char *result) {
  // Persist before sending, so a reset cannot silently lose a completed pass.
  outcome=result; store.putString("outcome",outcome);
  mode=REPORT; lastNetwork=0;
}
void report() {
  JsonDocument body,response;
  body["transaction_id"]=transactionId; body["outcome"]=outcome;
  int code=post("/confirm",body,response);
  if (code==200 && response["ok"].as<bool>()) {
    Serial.println("[SYNC] Confirmed by server"); clearStored();
  } else if (code==422 || code==401 || code==403) {
    mode=RECONCILE; leds(false,true);
    Serial.printf("[RECONCILE] HTTP %d. Check admin /perangkat. Transaction: %s\n",code,transactionId.c_str());
  } else Serial.printf("[SYNC] Waiting for server, HTTP %d; gate remains unavailable\n",code);
}
String uidOf(MFRC522 &reader) {
  String uid;
  for(byte i=0;i<reader.uid.size;i++) { if(i) uid+=':'; if(reader.uid.uidByte[i]<16) uid+='0'; uid+=String(reader.uid.uidByte[i],HEX); }
  uid.toUpperCase(); return uid;
}
void readCard(MFRC522 &reader,bool isEntry) {
  if(mode!=READY || !reader.PICC_IsNewCardPresent() || !reader.PICC_ReadCardSerial()) return;
  String uid=uidOf(reader); reader.PICC_HaltA(); reader.PCD_StopCrypto1();
  if(uid==lastUid && millis()-lastRead<1500) return;
  lastUid=uid; lastRead=millis();
  if(sensorActive()) { reject("Sensor occupied; ask attendant"); return; }
  JsonDocument body,response; body["uid"]=uid; body["direction"]=isEntry?"entry":"exit";
  int code=post("/authorize",body,response);
  if(code!=200 || !response["allowed"].as<bool>()) { reject(response["message"] | "Server unavailable / access denied"); return; }
  transactionId=response["transaction_id"].as<String>();
  if(transactionId.length()!=36) { reject("Invalid server response"); return; }
  // A reboot at this stage is ambiguous: require a human check, never assume a pass.
  store.putString("tx",transactionId); store.putString("outcome","unknown");
  if(sensorActive()) { queueReport("cancelled"); return; }
  vehicleSeen=false; clearedAt=0; openedAt=millis(); mode=WAIT_PASS;
  gate.write(90); leds(true,false); tone(BUZZER,1900,100);
  Serial.println("[GATE] Open; waiting for IR");
}
void setup() {
  Serial.begin(115200);
  pinMode(IR,IR_ACTIVE_HIGH?INPUT_PULLDOWN:INPUT_PULLUP);
  pinMode(GREEN,OUTPUT); pinMode(RED,OUTPUT); pinMode(BUZZER,OUTPUT);
  gate.setPeriodHertz(50); gate.attach(SERVO,500,2400);
  // If booting with the beam occupied, avoid issuing a close command until clear.
  if(sensorActive()) { gate.write(90); mode=RECONCILE; leds(false,true); Serial.println("[BOOT] IR occupied: inspect gate before resuming"); }
  else gate.write(0);
  pinMode(CS_ENTRY,OUTPUT); pinMode(CS_EXIT,OUTPUT); digitalWrite(CS_ENTRY,HIGH); digitalWrite(CS_EXIT,HIGH);
  SPI.begin(18,19,23); entry.PCD_Init(); digitalWrite(CS_ENTRY,HIGH); exitReader.PCD_Init(); digitalWrite(CS_EXIT,HIGH);
  store.begin("tult-parking",false);
  transactionId=store.getString("tx",""); outcome=store.getString("outcome","");
  if(transactionId.length()) {
    mode=!sensorActive() && (outcome=="passed" || outcome=="cancelled")?REPORT:RECONCILE;
    Serial.println("[RECOVERY] Stored transaction: "+transactionId+" / "+outcome);
  }
  WiFi.mode(WIFI_STA); WiFi.setAutoReconnect(true); WiFi.begin(WIFI_SSID,WIFI_PASSWORD);
  Serial.println("[READY] TULT online firmware. No network: no automatic access.");
  Serial.println("[SERVICE] After admin reconciliation, send RESUME over serial with IR clear.");
}
void loop() {
  bool active=sensorActive();
  // No network requests while the vehicle is crossing the gate.
  if(mode==WAIT_PASS) {
    if(active) { vehicleSeen=true; clearedAt=0; }
    else if(vehicleSeen) {
      if(!clearedAt) clearedAt=millis();
      if(millis()-clearedAt>=SAFE_DELAY) { gate.write(0); leds(false,false); queueReport("passed"); }
    }
    if(!vehicleSeen && millis()-openedAt>=GATE_TIMEOUT) {
      // Re-read just before moving the gate.
      if(!sensorActive()) { gate.write(0); queueReport("cancelled"); }
    }
    delay(10); return;
  }
  if(WiFi.status()!=WL_CONNECTED && millis()-lastReconnect>5000) { lastReconnect=millis(); WiFi.reconnect(); }
  if(mode==REPORT && millis()-lastNetwork>=2000) { lastNetwork=millis(); report(); }
  if((mode==READY || mode==RECONCILE) && millis()-lastNetwork>=15000) {
    lastNetwork=millis(); JsonDocument body,response; post("/heartbeat",body,response);
  }
  if(mode==READY) { readCard(entry,true); readCard(exitReader,false); }
  if(mode==READY && feedbackUntil && (long)(millis()-feedbackUntil)>=0) { feedbackUntil=0; leds(false,false); }
  if(mode==RECONCILE && Serial.available()) {
    String command=Serial.readStringUntil('\n'); command.trim();
    if(command=="RESUME" && !sensorActive()) {
      Serial.println("[SERVICE] Operator acknowledged reconciliation; closing gate and clearing local record");
      gate.write(0); clearStored();
    }
  }
  delay(10);
}
