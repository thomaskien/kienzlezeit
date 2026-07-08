# kienzlezeit

![kienzlezeit](kienzlezeit.png)

Lokale RFID-Zeiterfassung für kleine Teams mit M5Stack M5Dial, PHP, Apache und SQLite. Das System läuft ohne Cloud-Zwang im eigenen Netzwerk.

**Aktueller Stand:** 8. Juli 2026<br>
**Autor:** Dr. Thomas Kienzle

## Versionsstand

| Komponente | Version | Aufgabe |
|---|---:|---|
| `kienzlezeit.php` | 1.6.6 | Mitarbeiter- und Adminoberfläche, Datenmodell und Auswertungen |
| `rfid-scan.php` | 1.2 | produktiver, idempotenter RFID-API-Endpunkt |
| `auslagen/auslagen.php` | 1.1.3 | integrierte Auslagen- und Kilometerverwaltung mit eigener SQLite-Datenbank |
| `installer.sh` | 1.6.7 | GitHub-Bootstrap, Installation und additive Aktualisierung unter Debian/Ubuntu mit Apache |
| `kienzlezeit_v1.4.ino` | 1.4 | aktuelle M5Dial-Firmware mit wählbarem HTTP/HTTPS und optimierter Anzeige |

## Funktionsumfang

- Kommen/Gehen per RFID; der Server bestimmt den nächsten Buchungstyp.
- Dauerhafte Idempotenz über `event_id`: Wiederholungen liefern dieselbe Antwort und erzeugen keine Doppelbuchung.
- Mitarbeiter-, Karten- und Terminalverwaltung; mehrere Karten je Mitarbeiter und löschbare Testkarten.
- Passwortloser Mitarbeiterlogin durch kurzes Auflegen der Karte am ausgewählten Terminal.
- Historisierte Sollzeiten mit getrennten Vorgaben für Vormittag und Nachmittag.
- Urlaubs- und Abwesenheitsverwaltung einschließlich Fortbildung und frei definierbarer Arten.
- Öffentliche Teamplanung für aktuelle und zukünftige Wochen; Samstag und Sonntag sind global ein- oder ausblendbar.
- Leere Sollabschnitte entfallen in der Wochenplanung; aktuell Anwesende erscheinen als übersichtliche Kacheln.
- Mitarbeiter-Korrekturanträge, zusätzliche Vertretungsanwesenheiten und administrative Buchungsprüfung.
  Prüfhinweise können mit einer vorbelegten oder frei eingetragenen Tagesarbeitszeit abgeschlossen werden.
  Ein so abgeschlossener alter Tag blockiert anschließend keine neue Buchung am RFID-Terminal.
- Gesamtsalden mit nachvollziehbarem Startsaldo, Urlaub und Krankheit in der Mitarbeiterübersicht.
- Persönliche Übersicht mit Arbeitszeit/Ist-Soll, tagesaktuellem Monatssaldo, Resturlaub und Gesamt-Stunden-Saldo.
- Historisierte Öffnungszeiten als übernehmbare Vorlage für persönliche Sollzeiten.
- Feiertage aller 16 Bundesländer mit dokumentierten Ergänzungen, Änderungen und Deaktivierungen.
- Unveränderliche Rohbuchungen, separate Korrekturen, Auditprotokoll und Monatsabschluss.
- Monatsauswertungen sowie tabellarische PDF- und CSV-Exporte mit Erstellungsdatum, Summen, Urlaub und Gesamtsaldo.
- Stichtagsbezogene Monatsübersicht aller aktiven Mitarbeitenden mit Salden, Urlaub sowie Jahreswerten für Krankheit, Fortbildung und sonstige Abwesenheiten.
- Mehrere gleichberechtigte Admin-Konten mit verpflichtendem Passwortwechsel und revisionssicherer Deaktivierung.
- Verschlüsselte Terminal-Secrets; Terminals können revisionssicher archiviert werden.
- Optional aktivierbare Auslagenverwaltung mit gemeinsamer Anmeldung, historischen Erstattungskonten,
  eigenen Mitarbeiterbelegen und administrativem Genehmigungs-/Bezahlstatus.
- Administrativer Buchhaltungsstatus „Beleg vorhanden“ je Belegposten; die Adminübersicht kennzeichnet
  Vorgänge als vollständig, fehlend oder offen. Antrag und Mitarbeiteransicht enthalten diesen Status nicht.
- Mitarbeiter-Detailansicht ohne QR-Code und mit verbreiterter Belegvorschau; QR-Code und Belegstatus im Web nur für Admins.
- Vollständige Namen von Admin-Konten sind im Adminbereich editierbar und Änderungen werden protokolliert.

## Hardware

Erforderlich sind:

- ein **M5Stack M5Dial** mit integriertem RFID-Leser,
- mindestens eine mit dem M5Dial kompatible RFID-Karte oder ein RFID-Tag,
- ein USB-C-Datenkabel zum Programmieren und eine zuverlässige Stromversorgung,
- ein erreichbares 2,4-GHz-WLAN,
- ein lokaler Debian-/Ubuntu-Server oder Kleinrechner mit Apache.

Im Normalbetrieb genügt ein Terminal. Bei mehreren Terminals wird das gewünschte Gerät beim Kartenlogin ausgewählt.

## Server installieren

### Voraussetzungen

- Debian oder Ubuntu
- auch für den Betrieb auf einem Raspberry Pi geeignet
- Root-Zugriff beziehungsweise `sudo`
- Netzwerkzugriff des M5Dial auf den Webserver
- ein über HTTP oder HTTPS erreichbarer Apache-Endpunkt

Der Installer prüft beziehungsweise installiert nach Bestätigung:

- Apache 2 und `libapache2-mod-php`
- PHP CLI mit PDO/SQLite, `mbstring`, XML, GD und OpenSSL
- SQLite 3
- `qrencode` für lokale EPC-/GiroCode-QR-Codes der Auslagenverwaltung

### Installation

Den Bootstrap-Installer direkt aus GitHub laden und ausführen:

```bash
curl -fsSL \
  https://raw.githubusercontent.com/thomaskien/kienzlezeit/main/installer.sh \
  -o installer.sh
chmod +x installer.sh
sudo ./installer.sh
```

Der Installer lädt `kienzlezeit.php`, `rfid-scan.php`, `kienzlezeit.png` und
`auslagen/auslagen.php` selbst aus dem Repository. Für einen festgelegten Release- oder Teststand
kann der Git-Ref beim Aufruf über `KIENZLEZEIT_REF` vorgegeben werden.

Der Installer:

1. installiert auf Wunsch fehlende Pakete,
2. kopiert Webanwendung, API, Auslagenverwaltung und Logo nach `/var/www/html`,
3. legt Daten und Sicherungen unter `/var/lib/kienzlezeit` ab,
4. erzeugt SQLite-Datenbank und Secret-Schlüsseldatei,
5. führt vorhandene Zeit- und Auslagen-Datenbanken additiv auf das aktuelle Schema über,
6. gibt das erste Admin-Übergangspasswort und den ersten Terminal-Key aus.

Anschließend öffnen:

```text
http://SERVER-IP/kienzlezeit.php
```

Beim ersten Adminlogin muss das Übergangspasswort geändert werden. Den ausgegebenen Terminal-Key sicher aufbewahren; er wird in der Firmware benötigt und kann später im Adminbereich angezeigt oder ersetzt werden.

Der Installer 1.6.7 richtet noch kein Apache-Zertifikat und keinen HTTPS-VirtualHost ein. Soll Firmware 1.4 HTTPS verwenden, muss der Server bereits unter einer `https://`-Adresse erreichbar sein; selbstsignierte Zertifikate sind zulässig. HTTP kann alternativ weiterhin gezielt konfiguriert werden.

### Auslagenverwaltung aktivieren

Die Auslagenverwaltung ist nach einer Neuinstallation zunächst deaktiviert. Ein Admin aktiviert sie unter
**Administration → Einstellungen → Auslagenverwaltung aktivieren**. Erst dann erscheinen die Menüpunkte
für Admins und Mitarbeitende; direkte Aufrufe sind bei deaktivierter Funktion ebenfalls gesperrt.

Mitarbeitende hinterlegen ihr eigenes Erstattungskonto, reichen Belege oder Kilometer ein und können nur
ihre eigenen Vorgänge ansehen und drucken. Kontoänderungen werden historisiert; jeder Vorgang behält den
beim Einreichen gültigen Namen, die IBAN und BIC als unveränderlichen Schnappschuss. Admins bearbeiten den
Status `Eingereicht → Genehmigt → Bezahlt` beziehungsweise lehnen oder stornieren mit Dokumentation.
Vorhandene Auslagen-Altdaten werden nicht automatisch anhand von Namen zugeordnet.

## M5Dial mit der Arduino IDE einrichten

### 1. Arduino IDE und Boardpaket

1. Arduino IDE 2.x installieren.
2. Unter **Datei/Arduino IDE → Einstellungen → Zusätzliche Boardverwalter-URLs** eintragen:

   ```text
   https://static-cdn.m5stack.com/resource/arduino/package_m5stack_index.json
   ```

3. In der Boardverwaltung nach **M5Stack** suchen und das aktuelle stabile Paket installieren.
4. Unter **Werkzeuge → Board → M5Stack → M5Dial** das Board auswählen.

Offizielle Anleitung: [M5Stack Boardverwaltung](https://docs.m5stack.com/en/arduino/arduino_board)

### 2. Bibliotheken installieren

Über **Werkzeuge → Bibliotheken verwalten** installieren:

- **M5Dial** von M5Stack; angebotene Abhängigkeiten wie M5Unified/M5GFX mitinstallieren,
- **ArduinoJson** von Benoit Blanchon.

`WiFi`, `HTTPClient`, `WiFiClientSecure` und `esp_system` sind Bestandteil des installierten ESP32-/M5Stack-Boardpakets. Eine separate Installation ist nicht erforderlich.

Offizielle M5Dial-Anleitung: [M5Dial kompilieren und hochladen](https://docs.m5stack.com/en/arduino/m5dial/program)

### 3. Firmware konfigurieren

`kienzlezeit_v1.4.ino` im Projektstamm öffnen und im Konfigurationsabschnitt anpassen:

```cpp
static const char *WIFI_SSID     = "MEIN-WLAN";
static const char *WIFI_PASSWORD = "MEIN-WLAN-PASSWORT";

static const char *DEVICE_HOSTNAME = "zeiterfassung-eingang";
static const char *TERMINAL_ID     = "eingang-1";
static const char *TERMINAL_KEY    = "KEY_AUS_INSTALLER_ODER_ADMINBEREICH";

static const char *API_URL =
  "https://SERVER-IP/rfid-scan.php";
```

Wichtig:

- `TERMINAL_ID` und `TERMINAL_KEY` müssen exakt mit dem Terminal im Adminbereich übereinstimmen.
- Bei einer Installation in einem Unterverzeichnis muss `API_URL` entsprechend ergänzt werden.
- Firmware 1.4 wählt das Protokoll anhand der `API_URL`: `https://` verwendet TLS, `http://` bleibt wahlweise möglich. Es gibt keinen automatischen Rückfall zwischen beiden Protokollen.
- Alle Serverzertifikate werden bewusst akzeptiert. Das schützt vor passivem Mitlesen, authentifiziert aber den Server nicht gegen aktive Man-in-the-Middle-Angriffe. Diese Einschränkung ist für das vorgesehene kontrollierte lokale Netz ausdrücklich akzeptiert.

### 4. Kompilieren und hochladen

1. M5Dial per USB-C-Datenkabel verbinden.
2. Unter **Werkzeuge → Port** den passenden Anschluss wählen.
3. Sketch prüfen und hochladen.
4. Falls das Gerät nicht erkannt wird: G0-Taste auf der Rückseite gedrückt halten, USB-C verbinden und G0 wieder loslassen, um den Downloadmodus zu aktivieren.
5. Optional den seriellen Monitor zur Kontrolle der WLAN- und Servermeldungen öffnen.

Nach erfolgreicher WLAN-Verbindung zeigt das M5Dial kurz seine IP-Adresse und wechselt anschließend in die RFID-Bereitschaft.

## Erster Funktionstest

1. Im Adminbereich prüfen, ob `eingang-1` aktiv ist.
2. Terminal-ID und Secret in der Firmware eintragen und Firmware hochladen.
3. Eine der mitgelieferten Testkarten verwenden:

   - `E9-4A-2C-83`
   - `15-38-E4-3D`
   - `04-3F-32-6A-EC-6B-80`

4. Die erwartete Textausgabe kann unter **Karten** im Adminbereich angepasst werden.
5. Danach Mitarbeiter anlegen, Sollzeiten hinterlegen und eine echte Karte registrieren.

## Daten und Sicherheit

- Datenbank: `/var/lib/kienzlezeit/kienzlezeit.sqlite`
- Auslagen-Datenbank: `/var/lib/kienzlezeit/auslagen.sqlite`
- Terminal-Secret-Schlüssel: `/var/lib/kienzlezeit/terminal-secret.key`
- Installer-Sicherungen: `/var/lib/kienzlezeit/backups/`
- Die Anwendung ist für den lokalen Betrieb konzipiert.
- Firmware 1.4 kann die Terminalkommunikation per HTTPS verschlüsseln, ohne das Serverzertifikat zu prüfen; HTTP bleibt bewusst konfigurierbar.
- Rohbuchungen werden nicht gelöscht oder überschrieben; Änderungen erfolgen über dokumentierte Korrekturen.

### Datensicherung

**Bei Nutzung als Zeiterfassung sind regelmäßige Backups dringend empfohlen.** Die Sicherung, die der Installer vor einer Aktualisierung erzeugt, ersetzt kein laufendes Backupkonzept.

SQLite sollte konsistent über den eingebauten Backup-Befehl gesichert werden, beispielsweise:

```bash
sudo sqlite3 /var/lib/kienzlezeit/kienzlezeit.sqlite \
  ".backup '/SICHERER-PFAD/kienzlezeit-YYYY-MM-DD.sqlite'"
sudo sqlite3 /var/lib/kienzlezeit/auslagen.sqlite \
  ".backup '/SICHERER-PFAD/auslagen-YYYY-MM-DD.sqlite'"
```

Empfohlen sind automatisierte tägliche Sicherungen, eine definierte Aufbewahrungsfrist und mindestens eine Kopie auf einem anderen Datenträger oder System. Beide Datenbanken **und** `/var/lib/kienzlezeit/terminal-secret.key` müssen gemeinsam gesichert und vor unbefugtem Zugriff geschützt werden. Die Wiederherstellung sollte vor dem Produktivbetrieb einmal getestet werden.

## Vorbereitung für GitHub

Vor der Veröffentlichung müssen insbesondere folgende Punkte geprüft werden:

- keine realen WLAN-Zugangsdaten, IP-Adressen oder Terminal-Secrets einchecken,
- SQLite-Dateien, WAL-/SHM-Dateien, Sicherungen und `terminal-secret.key` ausschließen,
- Test- und Produktivkonfiguration klar trennen,
- Lizenz und Beitragsregeln festlegen,
- Apache-HTTPS-Konfiguration bereitstellen und den HTTPS-Modus vor der Veröffentlichung Ende-zu-Ende testen; HTTP bleibt als optionale Betriebsart erhalten.

Die produktive Datenbank und alle Schlüsseldateien gehören niemals in das Repository.
