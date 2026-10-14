#include <WiFi.h>
#include <HTTPClient.h>
#include <Wire.h>
#include <Adafruit_MPU6050.h>
#include <Adafruit_Sensor.h>
#include <OneWire.h>
#include <DallasTemperature.h>
#include <ArduinoJson.h>
#include "time.h"

// ============================================================
// BIBLIOTECA FFT (Instale "arduinoFFT" pelo Library Manager)
// ============================================================
#include <arduinoFFT.h>
const uint16_t FFT_SAMPLES = 64; 
const double FFT_SAMPLING_FREQ = 20.0; // 20 Hz
double vReal[FFT_SAMPLES];
double vImag[FFT_SAMPLES];
ArduinoFFT<double> FFT = ArduinoFFT<double>(vReal, vImag, FFT_SAMPLES, FFT_SAMPLING_FREQ);

// ============================================================
// REDE E CREDENCIAIS
// ============================================================
const char* ssid = "RedeLBD2";
const char* password = "senha123";

const char* ntpServer = "pool.ntp.org";
const long  gmtOffset_sec = -10800; // Brasil UTC-3
const int   daylightOffset_sec = 0;

const char* serverURL = "http://192.168.100.101:8000/api/sensors/data";
const char* serverURL_RT = "http://192.168.100.101:8000/api/sensors/realtime";
const char* deviceUuid = "fbd9bb9c-e526-4f9b-a1d7-3dfe21400990";
const char* apiToken = "H3rsrkcZD8gzh0yWrXKYe3SBt8RwfsynuAWC2vi5";

// ============================================================
// SENSORES
// ============================================================
Adafruit_MPU6050 mpu;
#define ONE_WIRE_BUS 4
OneWire oneWire(ONE_WIRE_BUS);
DallasTemperature tempSensor(&oneWire);

// ============================================================
// CONFIGURA��O DE AQUISI��O
// ============================================================
const unsigned long INTERVALO_AMOSTRA_MS = 50; // 20 Hz
const int TAMANHO_BUFFER = 100; // 5 segundos
const unsigned long INTERVALO_ENVIO_MS = 5000; 
const unsigned long INTERVALO_REALTIME_MS = 300; 

// ============================================================
// ESTRUTURA DE UMA AMOSTRA
// ============================================================
struct SensorSample {
  float ax, ay, az;
  float gx, gy, gz;
  float pitch, roll;
};

SensorSample buffer[TAMANHO_BUFFER];
int bufferIndex = 0;

unsigned long ultimaAmostra = 0;
unsigned long ultimoEnvio = 0;
unsigned long ultimoEnvioRealtime = 0;
unsigned long totalPassos = 0;
bool emMovimento = false;
unsigned long eventosRuminacao = 0;
unsigned long ultimoMomentoRuminacao = 0;

void conectarWiFi() {
  if (WiFi.status() == WL_CONNECTED) return;
  Serial.print("Conectando ao Wi-Fi...");
  WiFi.begin(ssid, password);
  unsigned long inicio = millis();
  while (WiFi.status() != WL_CONNECTED && millis() - inicio < 10000) {
    delay(500);
    Serial.print(".");
  }
  Serial.println();
  if (WiFi.status() == WL_CONNECTED) {
    Serial.println("Wi-Fi conectado!");
    configTime(gmtOffset_sec, daylightOffset_sec, ntpServer);
  }
}

unsigned long getUnixTimestamp() {
  time_t now;
  struct tm timeinfo;
  if (!getLocalTime(&timeinfo)) return millis() / 1000;
  time(&now);
  return now;
}

void setup() {
  Serial.begin(115200);
  delay(1000);
  Wire.begin(21, 22);

  if (!mpu.begin()) {
    Serial.println("ERRO: MPU n encontrado!");
    while (true) delay(1000);
  }
  mpu.setAccelerometerRange(MPU6050_RANGE_4_G);
  mpu.setGyroRange(MPU6050_RANGE_500_DEG);
  mpu.setFilterBandwidth(MPU6050_BAND_21_HZ);

  tempSensor.begin();
  conectarWiFi();

  ultimaAmostra = millis();
  ultimoEnvio = millis();
}

void coletarAmostra() {
  sensors_event_t a, g, temp_mpu;
  mpu.getEvent(&a, &g, &temp_mpu);

  float ax = a.acceleration.x;
  float ay = a.acceleration.y;
  float az = a.acceleration.z;

  float pitch = atan2(ax, sqrt(ay * ay + az * az)) * 180.0 / PI;
  float roll  = atan2(ay, sqrt(ax * ax + az * az)) * 180.0 / PI;

  if (bufferIndex < TAMANHO_BUFFER) {
    buffer[bufferIndex].ax = ax;
    buffer[bufferIndex].ay = ay;
    buffer[bufferIndex].az = az;
    buffer[bufferIndex].gx = g.gyro.x;
    buffer[bufferIndex].gy = g.gyro.y;
    buffer[bufferIndex].gz = g.gyro.z;
    buffer[bufferIndex].pitch = pitch;
    buffer[bufferIndex].roll = roll;
  }

  float magAceleracao = sqrt(ax*ax + ay*ay + az*az);
  if (magAceleracao > 12.5 && !emMovimento) {
    totalPassos++;
    emMovimento = true;
  } else if (magAceleracao < 10.5) {
    emMovimento = false;
  }

  if (bufferIndex < TAMANHO_BUFFER) bufferIndex++;
}

void calcularRumunacaoFFT() {
  if (bufferIndex < FFT_SAMPLES) return;
  int startIdx = bufferIndex - FFT_SAMPLES;
  for (int i = 0; i < FFT_SAMPLES; i++) {
    vReal[i] = buffer[startIdx + i].gz;
    vImag[i] = 0.0;
  }
  FFT.windowing(FFTWindow::Hamming, FFTDirection::Forward);
  FFT.compute(FFTDirection::Forward);
  FFT.complexToMagnitude();

  double maxMagnitude = 0;
  int melhorBin = 0;
  for (int i = 3; i <= 5; i++) {
    if (vReal[i] > maxMagnitude) {
      maxMagnitude = vReal[i];
      melhorBin = i;
    }
  }

  if (maxMagnitude > 3.5) { 
    eventosRuminacao += 1; 
    ultimoMomentoRuminacao = millis(); // Marca o momento da ruminação!
    Serial.println("FFT: Ruminação!");
  }
}

void enviarDadosRealtime() {
  if (WiFi.status() != WL_CONNECTED) return;
  sensors_event_t a, g, temp_mpu;
  mpu.getEvent(&a, &g, &temp_mpu);

  float p = atan2(a.acceleration.x, sqrt(a.acceleration.y * a.acceleration.y + a.acceleration.z * a.acceleration.z)) * 180.0 / PI;
  float r  = atan2(a.acceleration.y, sqrt(a.acceleration.x * a.acceleration.x + a.acceleration.z * a.acceleration.z)) * 180.0 / PI;

  JsonDocument doc;
  doc["device_uuid"] = deviceUuid;
  doc["pitch"] = serialized(String(p, 1));
  doc["roll"] = serialized(String(r, 1));
  doc["is_moving"] = emMovimento; 
  
  // Ruminando se a última FFT positiva ocorreu há menos de 6 segundos
  doc["is_ruminating"] = (millis() - ultimoMomentoRuminacao < 6000); 
  
  String payload;
  serializeJson(doc, payload);

  HTTPClient http;
  http.begin(serverURL_RT);
  http.setTimeout(500); 
  http.addHeader("Content-Type", "application/json");
  http.addHeader("Authorization", "Bearer " + String(apiToken));
  http.POST(payload);
  http.end();
}

void enviarDadosLaravel() {
  if (bufferIndex == 0) return;
  calcularRumunacaoFFT();

  tempSensor.requestTemperatures();
  float temp = tempSensor.getTempCByIndex(0);
  if (temp == -127.00 || temp < 30.0 || temp > 45.0) temp = 38.5;

  if (WiFi.status() != WL_CONNECTED) conectarWiFi();
  if (WiFi.status() != WL_CONNECTED) return; 

  JsonDocument doc; 
  doc["device_uuid"] = deviceUuid;
  doc["timestamp_unix"] = getUnixTimestamp();
  doc["sample_count"] = bufferIndex;
  doc["window_seconds"] = 5;
  doc["steps"] = totalPassos;
  doc["rumination_events"] = eventosRuminacao;
  doc["temperatura_animal"] = serialized(String(temp, 2));

  JsonArray samples = doc["samples"].to<JsonArray>();
  for (int i = 0; i < bufferIndex; i += 2) { 
    JsonArray arr = samples.add<JsonArray>();
    arr.add(serialized(String(buffer[i].ax, 2)));
    arr.add(serialized(String(buffer[i].ay, 2)));
    arr.add(serialized(String(buffer[i].az, 2)));
    arr.add(serialized(String(buffer[i].gx, 2)));
    arr.add(serialized(String(buffer[i].gy, 2)));
    arr.add(serialized(String(buffer[i].gz, 2)));
    arr.add(serialized(String(buffer[i].pitch, 1)));
    arr.add(serialized(String(buffer[i].roll, 1)));
  }

  String jsonData;
  serializeJson(doc, jsonData);
  
  HTTPClient http;
  http.begin(serverURL);
  http.addHeader("Content-Type", "application/json");
  http.addHeader("Authorization", "Bearer " + String(apiToken));
  http.POST(jsonData);
  http.end();

  bufferIndex = 0;
  totalPassos = 0;
  eventosRuminacao = 0;
}

void loop() {
  unsigned long agora = millis();
  if (agora - ultimaAmostra >= INTERVALO_AMOSTRA_MS) {
    ultimaAmostra = agora;
    coletarAmostra();
  }
  if (agora - ultimoEnvioRealtime >= INTERVALO_REALTIME_MS) {
    ultimoEnvioRealtime = agora;
    enviarDadosRealtime();
  }
  if (agora - ultimoEnvio >= INTERVALO_ENVIO_MS) {
    ultimoEnvio = agora;
    if (bufferIndex >= TAMANHO_BUFFER) enviarDadosLaravel();
  }
  delay(1);
}
