/*
  kienzlezeit v1.4
  von Dr. Thomas Kienzle

  Board:
    Werkzeuge -> Board -> M5Stack -> M5Dial

  Bibliotheken:
    - M5Dial by M5Stack
    - ArduinoJson by Benoit Blanchon

  Tonfolge:
    Karte erkannt:        C7
    Erfolgreiche Antwort: E7 -> G7 -> E7
    Fehler:               G7 -> G7 -> G7 -> G7

  RFID-Ablauf:
    Boot: RFID aus
    WLAN/IP vorhanden: IP 10 Sekunden zeigen
    Bereitschaft: RFID an
    Karte erkannt: RFID sofort aus
    150 ms später: HTTP(S)-Request
    Ergebnisanzeige vorbei: RFID wieder an

  Changelog:
    v1.4
      - Ergebnisanzeige fuer das runde Display neu gestaltet.
      - Erfolg: gruener Haken oben, Fehler: rotes X mit Rahmen.
      - Servertext beginnt weiter oben und wird bei fester
        Schriftgroesse automatisch innerhalb der Rundung umgebrochen.
      - Eine freie Textzeile trennt Symbol und Servertext.
    v1.3
      - Transportverschluesselung per HTTPS mit WiFiClientSecure.
      - HTTP oder HTTPS werden anhand der API_URL gezielt ausgewaehlt.
        Es gibt keinen automatischen Rueckfall zwischen den Protokollen.
      - Alle Serverzertifikate werden bewusst akzeptiert (setInsecure).
        Damit besteht Schutz gegen passives Mitlesen, aber keine
        Authentifizierung des Servers gegen aktive MITM-Angriffe.
    v1.2
      - Fehleranzeige: rote, fette erste Zeile "FEHLER".
      - Fehlerton: viermal G7, je 200 ms.
      - Startmeldung: "kienzlezeit v1.2 von Dr. Thomas Kienzle"
        auf Display und serieller Konsole.
    v1.1
      - Erfolgsanzeige auf 3,2 Sekunden, Fehleranzeige auf
        4,8 Sekunden verlängert.
    v1.0
      - Tonfolge C7 -> E7 -> G7 -> E7 eingeführt.
*/

#include <WiFi.h>
#include <HTTPClient.h>
#include <WiFiClientSecure.h>
#include <ArduinoJson.h>
#include <esp_system.h>
#include "M5Dial.h"

// ------------------------------------------------------------
// Produktkennung
// ------------------------------------------------------------

static const char *APP_NAME    = "kienzlezeit";
static const char *APP_VERSION = "v1.4";
static const char *APP_AUTHOR  = "Dr. Thomas Kienzle";

// ------------------------------------------------------------
// Konfiguration
// ------------------------------------------------------------

static const char *WIFI_SSID     = "WLAN-NAME";
static const char *WIFI_PASSWORD = "WLAN-PASSWORT";

static const char *DEVICE_HOSTNAME = "zeiterfassung-eingang";
static const char *TERMINAL_ID     = "eingang-1";
static const char *TERMINAL_KEY    = "KEY HIER HER";

static const char *API_URL =
  "https://10.0.xxxx.xxx/rfid-scan.php";

// ------------------------------------------------------------
// Zeitwerte
// ------------------------------------------------------------

static const uint32_t IP_SCREEN_MS               = 10000;

// v1.1: gegenüber v1.0 verdoppelt.
static const uint32_t RESULT_SCREEN_OK_MS        = 3200;
static const uint32_t RESULT_SCREEN_ERROR_MS     = 4800;

static const uint32_t RFID_SETTLE_MS             = 60;
static const uint32_t RFID_TO_HTTP_DELAY_MS      = 150;
static const uint32_t SAME_CARD_COOLDOWN_MS      = 1500;
static const uint32_t WIFI_RECONNECT_INTERVAL_MS = 10000;
static const uint32_t HTTP_CONNECT_TIMEOUT_MS    = 1500;
static const uint32_t HTTPS_CONNECT_TIMEOUT_MS   = 4000;
static const uint16_t HTTP_RESPONSE_TIMEOUT_MS   = 2200;
static const uint32_t HTTP_RETRY_DELAY_MS        = 250;

static const uint8_t DISPLAY_TEXT_SIZE = 2;
static const int DISPLAY_TEXT_MAX_WIDTH = 222;

// ------------------------------------------------------------
// Töne: Oktave 7
// ------------------------------------------------------------

static const uint16_t TONE_C7 = 2093;
static const uint16_t TONE_E7 = 2637;
static const uint16_t TONE_G7 = 3136;

static const uint16_t TONE_DURATION_MS = 200;
static const uint16_t TONE_PAUSE_MS    = 40;

// ------------------------------------------------------------
// Status
// ------------------------------------------------------------

enum class DeviceState {
  CONNECTING,
  SHOW_IP,
  READY,
  WAIT_BEFORE_REQUEST,
  RESULT
};

enum class ToneSequence {
  NONE,
  CARD_RECOGNIZED,
  SERVER_ANSWER,
  SERVER_ERROR
};

DeviceState deviceState = DeviceState::CONNECTING;
ToneSequence toneSequence = ToneSequence::NONE;

volatile bool wifiGotIpEvent = false;
volatile bool wifiDisconnectedEvent = false;
volatile uint8_t wifiDisconnectReason = 0;

bool wifiReady = false;
bool rfidAntennaEnabled = false;

uint32_t screenUntil = 0;
uint32_t requestDueAt = 0;
uint32_t rfidReadyAt = 0;
uint32_t lastWifiConnectAttempt = 0;

String lastUid = "";
uint32_t lastUidMillis = 0;

String heldCardUid = "";
String pendingUid = "";
String pendingEventId = "";

uint32_t bootNonce = 0;
uint32_t eventSequence = 0;

uint8_t toneStep = 0;
uint32_t toneNextAt = 0;

struct ServerReply {
  bool received = false;
  bool ok = false;

  int httpCode = 0;
  int transportError = 0;

  String title;
  String line1;
  String line2;
};

// ------------------------------------------------------------
// Hilfsfunktionen
// ------------------------------------------------------------

bool timeReached(uint32_t timestamp) {
  return static_cast<int32_t>(millis() - timestamp) >= 0;
}

String shortenForDisplay(const String &text) {
  M5Dial.Display.setTextFont(1);
  M5Dial.Display.setTextSize(DISPLAY_TEXT_SIZE);

  if (M5Dial.Display.textWidth(text.c_str()) <= DISPLAY_TEXT_MAX_WIDTH) {
    return text;
  }

  const String suffix = "...";
  String result = text;

  while (
    result.length() > 0 &&
    M5Dial.Display.textWidth((result + suffix).c_str()) > DISPLAY_TEXT_MAX_WIDTH
  ) {
    result.remove(result.length() - 1);
  }

  return result + suffix;
}

// ------------------------------------------------------------
// Display
// ------------------------------------------------------------

void drawUpArrow(int centerX, int topY) {
  M5Dial.Display.fillTriangle(
    centerX, topY,
    centerX - 8, topY + 10,
    centerX + 8, topY + 10,
    WHITE
  );

  M5Dial.Display.fillRect(
    centerX - 3,
    topY + 9,
    7,
    15,
    WHITE
  );
}

void drawCardArrows() {
  drawUpArrow(88, 18);
  drawUpArrow(120, 18);
  drawUpArrow(152, 18);
}

void showThreeLines(const String &line1,
                    const String &line2,
                    const String &line3,
                    bool showArrows = false) {
  const int centerX = M5Dial.Display.width() / 2;

  const String safeLine1 = shortenForDisplay(line1);
  const String safeLine2 = shortenForDisplay(line2);
  const String safeLine3 = shortenForDisplay(line3);

  M5Dial.Display.fillScreen(BLACK);

  if (showArrows) {
    drawCardArrows();
  }

  M5Dial.Display.setTextDatum(middle_center);
  M5Dial.Display.setTextFont(1);
  M5Dial.Display.setTextSize(DISPLAY_TEXT_SIZE);
  M5Dial.Display.setTextColor(WHITE, BLACK);

  M5Dial.Display.drawString(safeLine1, centerX, 98);
  M5Dial.Display.drawString(safeLine2, centerX, 124);
  M5Dial.Display.drawString(safeLine3, centerX, 150);
}

int resultMaxWidthForLine(int lineIndex) {
  static const int widths[] = {208, 214, 208, 190, 158};

  if (lineIndex < 0 || lineIndex >= 5) {
    return 158;
  }

  return widths[lineIndex];
}

int findResultLineBreak(const String &text, int maxWidth) {
  if (M5Dial.Display.textWidth(text.c_str()) <= maxWidth) {
    return text.length();
  }

  int lastSpace = -1;

  for (int index = 1; index <= text.length(); ++index) {
    const char current = text.charAt(index - 1);

    if (current == ' ' || current == '\t') {
      lastSpace = index - 1;
    }

    const String candidate = text.substring(0, index);

    if (M5Dial.Display.textWidth(candidate.c_str()) > maxWidth) {
      return lastSpace > 0 ? lastSpace : max(1, index - 1);
    }
  }

  return text.length();
}

String buildResultText(const String &line1,
                       const String &line2,
                       const String &line3) {
  String result;
  const String inputLines[] = {line1, line2, line3};

  for (const String &inputLine : inputLines) {
    String cleaned = inputLine;
    cleaned.trim();

    if (cleaned.length() == 0) {
      continue;
    }

    if (result.length() > 0) {
      result += '\n';
    }

    result += cleaned;
  }

  return result;
}

int wrapResultText(const String &text,
                   String outputLines[],
                   bool &truncated) {
  const int maxLines = 5;
  int outputCount = 0;
  String remaining = text;

  truncated = false;

  M5Dial.Display.setTextFont(1);
  M5Dial.Display.setTextSize(DISPLAY_TEXT_SIZE);

  while (remaining.length() > 0) {
    const int newlinePosition = remaining.indexOf('\n');
    String paragraph;

    if (newlinePosition >= 0) {
      paragraph = remaining.substring(0, newlinePosition);
      remaining = remaining.substring(newlinePosition + 1);
    } else {
      paragraph = remaining;
      remaining = "";
    }

    paragraph.trim();

    while (paragraph.length() > 0) {
      if (outputCount >= maxLines) {
        truncated = true;
        break;
      }

      const int breakPosition = findResultLineBreak(
        paragraph,
        resultMaxWidthForLine(outputCount)
      );

      String line = paragraph.substring(0, breakPosition);
      line.trim();

      if (line.length() == 0) {
        line = paragraph.substring(0, 1);
      }

      outputLines[outputCount++] = line;

      int consumed = max(1, breakPosition);

      while (
        consumed < paragraph.length() &&
        (paragraph.charAt(consumed) == ' ' || paragraph.charAt(consumed) == '\t')
      ) {
        ++consumed;
      }

      paragraph = paragraph.substring(consumed);
      paragraph.trim();
    }

    if (truncated) {
      break;
    }
  }

  if (truncated && outputCount > 0) {
    const int lastIndex = outputCount - 1;
    const int maxWidth = resultMaxWidthForLine(lastIndex);
    String shortened = outputLines[lastIndex];

    while (
      shortened.length() > 0 &&
      M5Dial.Display.textWidth((shortened + "...").c_str()) > maxWidth
    ) {
      shortened.remove(shortened.length() - 1);
    }

    outputLines[lastIndex] = shortened + "...";
  }

  return outputCount;
}

void drawSuccessCheck() {
  const int centerX = M5Dial.Display.width() / 2;

  for (int thickness = -2; thickness <= 2; ++thickness) {
    M5Dial.Display.drawLine(
      centerX - 16,
      35 + thickness,
      centerX - 5,
      46 + thickness,
      GREEN
    );
    M5Dial.Display.drawLine(
      centerX - 5,
      46 + thickness,
      centerX + 18,
      23 + thickness,
      GREEN
    );
  }
}

void drawErrorCross() {
  const int centerX = M5Dial.Display.width() / 2;
  const int centerY = 35;

  for (int inset = 0; inset < 3; ++inset) {
    M5Dial.Display.drawCircle(centerX, centerY, 21 - inset, RED);
  }

  for (int thickness = -2; thickness <= 2; ++thickness) {
    M5Dial.Display.drawLine(
      centerX - 10 + thickness,
      centerY - 10,
      centerX + 10 + thickness,
      centerY + 10,
      RED
    );
    M5Dial.Display.drawLine(
      centerX + 10 + thickness,
      centerY - 10,
      centerX - 10 + thickness,
      centerY + 10,
      RED
    );
  }
}

void showResultLayout(const String &line1,
                      const String &line2,
                      const String &line3,
                      bool success) {
  const int centerX = M5Dial.Display.width() / 2;
  const int textStartY = 96;
  const int lineHeight = 24;
  String wrappedLines[5];
  bool truncated = false;
  const String resultText = buildResultText(line1, line2, line3);
  const int lineCount = wrapResultText(
    resultText,
    wrappedLines,
    truncated
  );

  M5Dial.Display.fillScreen(BLACK);

  if (success) {
    drawSuccessCheck();
  } else {
    drawErrorCross();
  }

  M5Dial.Display.setTextDatum(middle_center);
  M5Dial.Display.setTextFont(1);
  M5Dial.Display.setTextSize(DISPLAY_TEXT_SIZE);
  M5Dial.Display.setTextColor(WHITE, BLACK);

  for (int index = 0; index < lineCount; ++index) {
    M5Dial.Display.drawString(
      wrappedLines[index],
      centerX,
      textStartY + index * lineHeight
    );
  }
}

void showConnectingScreen() {
  showThreeLines(
    "Starte ...",
    "WLAN verbinden",
    "Bitte warten"
  );

  deviceState = DeviceState::CONNECTING;
}

void showIpScreen() {
  showThreeLines(
    "WLAN verbunden",
    WiFi.localIP().toString(),
    "Bitte warten"
  );

  deviceState = DeviceState::SHOW_IP;
  screenUntil = millis() + IP_SCREEN_MS;
}

void showReadyScreen() {
  showThreeLines(
    "Zeiterfassung",
    "Bitte Karte",
    "vorhalten",
    true
  );

  deviceState = DeviceState::READY;
}

void showResultScreen(const String &line1,
                      const String &line2,
                      const String &line3,
                      bool success) {
  showResultLayout(line1, line2, line3, success);

  deviceState = DeviceState::RESULT;
  screenUntil = millis() + (
    success ? RESULT_SCREEN_OK_MS : RESULT_SCREEN_ERROR_MS
  );
}

// ------------------------------------------------------------
// Tonfolgen
// ------------------------------------------------------------

void startTone(uint16_t frequency) {
  M5Dial.Speaker.tone(frequency, TONE_DURATION_MS);
}

void startCardRecognizedTone() {
  toneSequence = ToneSequence::CARD_RECOGNIZED;
  toneStep = 0;

  startTone(TONE_C7);
  toneNextAt = millis() + TONE_DURATION_MS + TONE_PAUSE_MS;
}

void startServerAnswerTone(bool success) {
  toneStep = 0;

  if (success) {
    toneSequence = ToneSequence::SERVER_ANSWER;
    startTone(TONE_E7);
  } else {
    toneSequence = ToneSequence::SERVER_ERROR;
    startTone(TONE_G7);
  }

  toneNextAt = millis() + TONE_DURATION_MS + TONE_PAUSE_MS;
}

void handleToneSequence() {
  if (
    toneSequence == ToneSequence::NONE ||
    !timeReached(toneNextAt)
  ) {
    return;
  }

  if (toneSequence == ToneSequence::CARD_RECOGNIZED) {
    toneSequence = ToneSequence::NONE;
    return;
  }

  if (toneSequence == ToneSequence::SERVER_ANSWER) {
    if (toneStep == 0) {
      toneStep = 1;
      startTone(TONE_G7);
      toneNextAt = millis() + TONE_DURATION_MS + TONE_PAUSE_MS;
      return;
    }

    if (toneStep == 1) {
      toneStep = 2;
      startTone(TONE_E7);
      toneNextAt = millis() + TONE_DURATION_MS + TONE_PAUSE_MS;
      return;
    }

    toneSequence = ToneSequence::NONE;
    return;
  }

  if (toneSequence == ToneSequence::SERVER_ERROR) {
    // Der erste Ton wurde bereits beim Start gespielt.
    // Danach noch dreimal G7: insgesamt viermal G7.
    if (toneStep < 3) {
      ++toneStep;
      startTone(TONE_G7);
      toneNextAt = millis() + TONE_DURATION_MS + TONE_PAUSE_MS;
      return;
    }

    toneSequence = ToneSequence::NONE;
  }
}

// ------------------------------------------------------------
// RFID-Antenne
// ------------------------------------------------------------

void disableRfidAntenna(const char *reason) {
  if (!rfidAntennaEnabled) {
    return;
  }

  M5Dial.Rfid.PCD_AntennaOff();
  rfidAntennaEnabled = false;

  Serial.printf("[rfid] Antenne AUS: %s\n", reason);
}

void enableRfidAntenna() {
  if (!wifiReady || rfidAntennaEnabled) {
    return;
  }

  M5Dial.Rfid.PCD_AntennaOn();
  rfidAntennaEnabled = true;
  rfidReadyAt = millis() + RFID_SETTLE_MS;

  Serial.println("[rfid] Antenne AN: bereit");
}

void returnToReadyState() {
  if (!wifiReady) {
    disableRfidAntenna("kein WLAN");
    showConnectingScreen();
    return;
  }

  enableRfidAntenna();
  showReadyScreen();
}

// ------------------------------------------------------------
// WLAN
// ------------------------------------------------------------

void onWiFiEvent(WiFiEvent_t event, WiFiEventInfo_t info) {
  if (event == ARDUINO_EVENT_WIFI_STA_GOT_IP) {
    wifiGotIpEvent = true;
  }

  if (event == ARDUINO_EVENT_WIFI_STA_DISCONNECTED) {
    wifiDisconnectedEvent = true;
    wifiDisconnectReason = info.wifi_sta_disconnected.reason;
  }
}

void startWifiConnection() {
  lastWifiConnectAttempt = millis();

  Serial.printf(
    "[wifi] Verbinde mit SSID '%s' ...\n",
    WIFI_SSID
  );

  WiFi.begin(WIFI_SSID, WIFI_PASSWORD);
}

void setupWifi() {
  WiFi.persistent(false);
  WiFi.mode(WIFI_STA);
  WiFi.setHostname(DEVICE_HOSTNAME);
  WiFi.setAutoReconnect(true);
  WiFi.setSleep(false);

  WiFi.onEvent(onWiFiEvent);

  startWifiConnection();
}

void handleWifi() {
  if (
    wifiReady &&
    WiFi.status() != WL_CONNECTED
  ) {
    wifiDisconnectedEvent = true;
    wifiDisconnectReason = 0;
  }

  if (wifiGotIpEvent) {
    wifiGotIpEvent = false;
    wifiReady = true;

    disableRfidAntenna("neue IP erhalten");

    Serial.printf(
      "[wifi] Verbunden. IP=%s RSSI=%d dBm Kanal=%d\n",
      WiFi.localIP().toString().c_str(),
      WiFi.RSSI(),
      WiFi.channel()
    );

    showIpScreen();
  }

  if (wifiDisconnectedEvent) {
    wifiDisconnectedEvent = false;
    wifiReady = false;

    disableRfidAntenna("WLAN getrennt");

    Serial.printf(
      "[wifi] Getrennt. Grund=%u Status=%d\n",
      wifiDisconnectReason,
      WiFi.status()
    );

    showConnectingScreen();
  }

  if (
    !wifiReady &&
    millis() - lastWifiConnectAttempt >= WIFI_RECONNECT_INTERVAL_MS
  ) {
    Serial.println("[wifi] Neuer Verbindungsversuch.");
    startWifiConnection();
  }
}

// ------------------------------------------------------------
// RFID
// ------------------------------------------------------------

String uidToString() {
  String uid;
  uid.reserve(M5Dial.Rfid.uid.size * 3);

  for (byte i = 0; i < M5Dial.Rfid.uid.size; ++i) {
    if (i > 0) {
      uid += '-';
    }

    char part[3];

    snprintf(
      part,
      sizeof(part),
      "%02X",
      M5Dial.Rfid.uid.uidByte[i]
    );

    uid += part;
  }

  return uid;
}

bool isDuplicateCard(const String &uid) {
  const uint32_t now = millis();

  if (
    uid == lastUid &&
    now - lastUidMillis < SAME_CARD_COOLDOWN_MS
  ) {
    return true;
  }

  lastUid = uid;
  lastUidMillis = now;

  return false;
}

String createEventId() {
  ++eventSequence;

  char buffer[80];

  snprintf(
    buffer,
    sizeof(buffer),
    "%s-%08lX-%lu",
    TERMINAL_ID,
    static_cast<unsigned long>(bootNonce),
    static_cast<unsigned long>(eventSequence)
  );

  return String(buffer);
}

void startCardProcessing(const String &uid) {
  heldCardUid = uid;

  disableRfidAntenna("Karte erkannt");
  startCardRecognizedTone();

  pendingUid = uid;
  pendingEventId = createEventId();
  requestDueAt = millis() + RFID_TO_HTTP_DELAY_MS;

  showThreeLines(
    "Karte erkannt",
    uid,
    "Bitte warten"
  );

  deviceState = DeviceState::WAIT_BEFORE_REQUEST;

  Serial.printf(
    "[rfid] UID=%s event_id=%s RSSI=%d dBm\n",
    pendingUid.c_str(),
    pendingEventId.c_str(),
    WiFi.RSSI()
  );
}

void handleRfid() {
  if (
    deviceState != DeviceState::READY ||
    !wifiReady ||
    !rfidAntennaEnabled ||
    !timeReached(rfidReadyAt)
  ) {
    return;
  }

  if (!M5Dial.Rfid.PICC_IsNewCardPresent()) {
    if (heldCardUid.length() > 0) {
      heldCardUid = "";
    }

    return;
  }

  if (!M5Dial.Rfid.PICC_ReadCardSerial()) {
    return;
  }

  const String uid = uidToString();

  M5Dial.Rfid.PICC_HaltA();
  M5Dial.Rfid.PCD_StopCrypto1();

  if (uid == heldCardUid) {
    return;
  }

  if (isDuplicateCard(uid)) {
    return;
  }

  startCardProcessing(uid);
}

// ------------------------------------------------------------
// HTTP / HTTPS
// ------------------------------------------------------------

String transportErrorText(int errorCode) {
  switch (errorCode) {
    case HTTPC_ERROR_CONNECTION_REFUSED:
      return "Keine Verbindung";

    case HTTPC_ERROR_READ_TIMEOUT:
      return "Antwort Timeout";

    case HTTPC_ERROR_CONNECTION_LOST:
      return "Verbindung verloren";

    case HTTPC_ERROR_NOT_CONNECTED:
      return "WLAN nicht bereit";

    default:
      return HTTPClient::errorToString(errorCode);
  }
}

template <typename ClientType>
bool sendScanWithClient(ClientType &client,
                        const char *protocolLabel,
                        uint32_t connectTimeoutMs,
                        const String &payload,
                        const String &eventId,
                        ServerReply &reply) {
  HTTPClient http;

  http.setConnectTimeout(connectTimeoutMs);
  http.setTimeout(HTTP_RESPONSE_TIMEOUT_MS);
  http.setReuse(false);
  http.useHTTP10(true);

  if (!http.begin(client, API_URL)) {
    reply.transportError = HTTPC_ERROR_CONNECTION_REFUSED;
    reply.title = "Netzwerkfehler";
    reply.line1 = String(protocolLabel) + " Start fehlte";
    reply.line2 = "Bitte erneut";
    return false;
  }

  http.addHeader("Content-Type", "application/json");
  http.addHeader("Connection", "close");
  http.addHeader("X-Terminal-Key", TERMINAL_KEY);

  const int httpCode = http.POST(payload);
  reply.httpCode = httpCode;

  if (httpCode <= 0) {
    reply.transportError = httpCode;
    reply.title = "Netzwerkfehler";
    reply.line1 = transportErrorText(httpCode);
    reply.line2 = "Bitte erneut";

    Serial.printf(
      "[%s] Fehler %d: %s; event_id=%s\n",
      protocolLabel,
      httpCode,
      HTTPClient::errorToString(httpCode).c_str(),
      eventId.c_str()
    );

    http.end();
    client.stop();
    return false;
  }

  const String body = http.getString();

  http.end();
  client.stop();

  if (httpCode != HTTP_CODE_OK) {
    reply.title = "Serverfehler";
    reply.line1 = String("HTTP ") + String(httpCode);
    reply.line2 = "Keine Buchung";

    Serial.printf(
      "[%s] HTTP %d: %s\n",
      protocolLabel,
      httpCode,
      body.c_str()
    );

    return false;
  }

  JsonDocument responseDoc;

  const DeserializationError jsonError =
    deserializeJson(responseDoc, body);

  if (jsonError) {
    reply.title = "Serverfehler";
    reply.line1 = "Antwort ungueltig";
    reply.line2 = "Bitte erneut";

    Serial.printf(
      "[%s] JSON Fehler: %s; Antwort=%s\n",
      protocolLabel,
      jsonError.c_str(),
      body.c_str()
    );

    return false;
  }

  reply.received = true;
  reply.ok = responseDoc["ok"] | false;

  reply.title =
    responseDoc["title"] |
    (reply.ok ? "Gebucht" : "Nicht gebucht");

  reply.line1 = responseDoc["line1"] | "";
  reply.line2 = responseDoc["line2"] | "";

  Serial.printf(
    "[%s] HTTP 200; event_id=%s; ok=%s\n",
    protocolLabel,
    eventId.c_str(),
    reply.ok ? "true" : "false"
  );

  return true;
}

bool sendScanOnce(const String &uid,
                  const String &eventId,
                  ServerReply &reply) {
  reply = ServerReply{};

  if (WiFi.status() != WL_CONNECTED) {
    reply.transportError = HTTPC_ERROR_NOT_CONNECTED;
    reply.title = "Kein WLAN";
    reply.line1 = "Keine Buchung";
    reply.line2 = "Bitte erneut";
    return false;
  }

  JsonDocument requestDoc;
  requestDoc["terminal"] = TERMINAL_ID;
  requestDoc["uid"] = uid;
  requestDoc["event_id"] = eventId;

  String payload;
  serializeJson(requestDoc, payload);

  const String apiUrl = String(API_URL);

  if (apiUrl.startsWith("https://")) {
    WiFiClientSecure client;

    // Bewusste Projektentscheidung fuer das kontrollierte lokale Netz:
    // TLS verschluesselt den Transport, das Serverzertifikat wird jedoch
    // nicht authentifiziert.
    client.setInsecure();

    return sendScanWithClient(
      client,
      "HTTPS",
      HTTPS_CONNECT_TIMEOUT_MS,
      payload,
      eventId,
      reply
    );
  }

  if (apiUrl.startsWith("http://")) {
    WiFiClient client;

    return sendScanWithClient(
      client,
      "HTTP",
      HTTP_CONNECT_TIMEOUT_MS,
      payload,
      eventId,
      reply
    );
  }

  reply.transportError = HTTPC_ERROR_CONNECTION_REFUSED;
  reply.title = "Konfigurationsfehler";
  reply.line1 = "API_URL ungueltig";
  reply.line2 = "HTTP(S) pruefen";

  Serial.printf(
    "[netzwerk] API_URL muss mit http:// oder https:// beginnen: %s\n",
    API_URL
  );

  return false;
}

bool sendScanWithOneSafeRetry(const String &uid,
                              const String &eventId,
                              ServerReply &reply) {
  if (sendScanOnce(uid, eventId, reply)) {
    return true;
  }

  if (
    reply.httpCode <= 0 &&
    WiFi.status() == WL_CONNECTED
  ) {
    Serial.printf(
      "[http] Wiederhole event_id=%s einmal.\n",
      eventId.c_str()
    );

    delay(HTTP_RETRY_DELAY_MS);

    return sendScanOnce(uid, eventId, reply);
  }

  return false;
}

// ------------------------------------------------------------
// Server-Anfrage
// ------------------------------------------------------------

void executePendingRequest() {
  if (pendingUid.length() == 0 || pendingEventId.length() == 0) {
    showResultScreen(
      "Interner Fehler",
      "Kein RFID Ereignis",
      "Bitte erneut",
      false
    );

    startServerAnswerTone(false);
    return;
  }

  showThreeLines(
    "Karte erkannt",
    "Server wird gefragt",
    "Bitte warten"
  );

  ServerReply reply;

  const bool communicationSucceeded =
    sendScanWithOneSafeRetry(
      pendingUid,
      pendingEventId,
      reply
    );

  pendingUid = "";
  pendingEventId = "";

  if (!communicationSucceeded) {
    showResultScreen(
      reply.title,
      reply.line1,
      reply.line2,
      false
    );

    startServerAnswerTone(false);
    return;
  }

  showResultScreen(
    reply.title,
    reply.line1,
    reply.line2,
    reply.ok
  );

  startServerAnswerTone(reply.ok);
}

// ------------------------------------------------------------
// Timer / Zustandswechsel
// ------------------------------------------------------------

void handleTimers() {
  if (
    deviceState == DeviceState::SHOW_IP &&
    timeReached(screenUntil)
  ) {
    returnToReadyState();
    return;
  }

  if (
    deviceState == DeviceState::WAIT_BEFORE_REQUEST &&
    timeReached(requestDueAt)
  ) {
    executePendingRequest();
    return;
  }

  if (
    deviceState == DeviceState::RESULT &&
    timeReached(screenUntil)
  ) {
    returnToReadyState();
  }
}

// ------------------------------------------------------------
// Arduino
// ------------------------------------------------------------

void setup() {
  Serial.begin(115200);
  delay(150);

  auto cfg = M5.config();

  M5Dial.begin(cfg, false, true);

  // RFID beim Booten bewusst aus.
  M5Dial.Rfid.PCD_AntennaOff();
  rfidAntennaEnabled = false;

  bootNonce = esp_random();

  Serial.printf(
    "%s %s von %s\n",
    APP_NAME,
    APP_VERSION,
    APP_AUTHOR
  );

  Serial.printf(
    "[boot] Terminal=%s Boot-Nonce=%08lX Reset-Grund=%d\n",
    TERMINAL_ID,
    static_cast<unsigned long>(bootNonce),
    static_cast<int>(esp_reset_reason())
  );

  showThreeLines(
    String(APP_NAME) + " " + APP_VERSION,
    APP_AUTHOR,
    "Starte ..."
  );

  delay(5000);

  showConnectingScreen();
  setupWifi();
}

void loop() {
  M5Dial.update();

  handleWifi();
  handleTimers();
  handleRfid();
  handleToneSequence();

  delay(5);
}
