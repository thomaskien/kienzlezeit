# Backup und Wiederherstellung

**Programm:** `kienzlezeit-backup-und-restore.sh` · **Version:** 1.0 · **Stand:** 16.11.2026<br>
**Autor:** Dr. Thomas Kienzle

## Voraussetzungen und Installation

Vorgesehen für Debian 12/13 und Ubuntu ab 22.04 mit dem normalen kienzlezeit-Installer
(Apache mit mod_php, `/var/www/html`, Daten unter `/var/lib/kienzlezeit`). Mit `sudo`
oder als root ausführen. Für fehlende Pakete ist Zugriff auf die eingerichteten apt-Paketquellen nötig.
Kein Cloudkonto, kein pip und kein zusätzlicher Download von Bibliotheken erforderlich.

Das Programm installiert fehlende Pakete selbst:

- Python 3 mit Standardbibliothek und SQLite-Unterstützung, `sqlite3`
- PHP CLI, `php-zip`, `php-sqlite3`; OpenSSL und AES-256-Unterstützung werden geprüft
- `cron`, `tzdata`, `ca-certificates`
- je nach Ziel `openssh-client` oder `smbclient`

PHP/libzip erzeugt und liest ZIP-Archive, auch mit AES-256. 7-Zip oder ein anderes
AES-ZIP-fähiges Programm kann die verschlüsselten Archive ebenfalls öffnen. Einfaches
historisches ZIP-Passwortverfahren (ZipCrypto) wird nicht für neue Backups verwendet.

```bash
curl -fsSL \
  https://raw.githubusercontent.com/thomaskien/kienzlezeit/main/kienzlezeit-backup-und-restore.sh \
  -o kienzlezeit-backup-und-restore.sh
sudo bash kienzlezeit-backup-und-restore.sh
```

Ohne Argument erscheint das Menü. Installation/Konfiguration aktiviert **keinen** Cronjob
ohne ausdrückliche Bestätigung. Nicht parallel mit `installer.sh`, manuellen Datenbankänderungen
oder anderen Wartungsprogrammen ausführen.

## Ziel konfigurieren – Menüpunkt 1

Ein eigenes Zielverzeichnis auf einem anderen System/Datenträger verwenden. Die Freigabe
beziehungsweise das angegebene Stammverzeichnis muss vorhanden sein. Das Programm legt
darin `daily/` und `weekly/` an. Bestehende Archive werden beim Verbindungstest nicht verändert.
Der Test schreibt, liest und entfernt ausschließlich eine eigene, zufällig benannte Testdatei.

### SSH / SFTP

DNS-Name oder IPv4-Adresse, Port, Benutzer und absoluten Zielpfad angeben. Der Server muss
SFTP unterstützen; eine ausführbare Remote-Shell ist nicht erforderlich. Der Benutzer benötigt
Lese-, Schreib-, Umbenennungs- und Löschrechte im Backupverzeichnis.

Einen eigenen SSH-Schlüssel erzeugen oder einen vorhandenen passphrasenlosen Schlüssel
kopieren lassen. Den angezeigten **öffentlichen** Schlüssel auf dem Zielserver hinterlegen.
Den angezeigten Host-Fingerabdruck unabhängig am Zielserver vergleichen und bestätigen.
Weitere Verbindungen prüfen diesen Fingerabdruck strikt; ein geänderter Hostschlüssel
führt zum Fehler, nicht zum stillschweigenden Akzeptieren.

Den Backup-Benutzer auf das Zielverzeichnis beschränken. Private Schlüssel werden nur lokal
root-geschützt gespeichert und niemals ins Datenarchiv aufgenommen.

### SMB / Netzwerkfreigabe

Server, Freigabe, optionales vorhandenes Unterverzeichnis, Benutzer, Domäne/Arbeitsgruppe
und Passwort eingeben. Es wird `smbclient` genutzt, kein dauerhaftes Mounten und kein Eintrag
in `/etc/fstab`. SMB1 ist ausgeschlossen (mindestens SMB2). SMB-Transportverschlüsselung
hängt von der Serverkonfiguration ab; für vertrauliche Archive den ZIP-Passwortschutz einschalten.

Zielpfade dürfen Buchstaben, Zahlen, Leerzeichen, `/`, `.`, `_` und `-` enthalten; keine `..`,
Shellsonderzeichen oder Wildcards. Für Servernamen DNS oder IPv4 verwenden. Diese konservativen
Einschränkungen vermeiden mehrdeutige Befehle der Übertragungsprogramme.

Ein erfolgreich gespeicherter Zielwechsel deaktiviert den bisherigen Cronjob. Lokale ausstehende
Archive des alten Ziels bleiben erhalten und werden nicht versehentlich zum neuen Ziel gesendet.

## Automatische Sicherung – Menüpunkt 2

1. Uhrzeit angeben, Standard **02:00 Uhr Europe/Berlin**.
2. ZIP-Passwortschutz wählen, bei Aktivierung Passwort zweimal verdeckt eingeben.
3. Testsicherung durchführen (empfohlen).
4. Cronjob ausdrücklich aktivieren.

Cron prüft einmal pro Minute, ob die gewählte Uhrzeit erreicht ist. Die Sicherung läuft einmal
pro lokalem Tag; Sommer-/Winterzeit wird berücksichtigt. Eine wegen ausgeschaltetem Server
verpasste Uhrzeit wird nach dem Start am selben Tag nachgeholt. Vergangene Tagesstände können
nicht nachträglich rekonstruiert werden. Bei Fehlern wird frühestens nach einer Stunde erneut
versucht. Eine lange offene Menüsitzung blockiert parallele Backup-/Restore-Läufe.

Der Cronlauf installiert selbst keine Pakete. Fehlende Voraussetzungen werden mit einem Fehler
gemeldet; das Programm dann interaktiv erneut starten. Ein deaktivierter Cronjob lässt alle
vorhandenen lokalen und entfernten Sicherungen unangetastet.

### Aufbewahrung

- Sicherung jeden Tag; alle Tagesstände der letzten **30 Kalendertage einschließlich heute** bleiben erhalten.
- Pro abgeschlossener ISO-Kalenderwoche (Montag–Sonntag) wird der letzte verfügbare, erfolgreich
  übertragene Stand nach `weekly/` verschoben. Er zählt innerhalb der ersten 30 Tage weiterhin
  als vorhandener Tagesstand und bleibt danach unbegrenzt erhalten.
- Übrige Tagesarchive werden frühestens am 31. Kalendertag gelöscht, und nur wenn die dazugehörige
  Wochensicherung am Ziel vorhanden und erneut anhand ihrer Prüfsumme geprüft wurde.
- **Dateien in `weekly/` werden niemals automatisch gelöscht oder ersetzt.** Auch fremde
  Installationskennungen und unvollständige Archive werden nicht automatisch bereinigt.
- Bei Speicherproblemen wird ein Fehler gemeldet. Es gibt keine automatische Verkürzung der
  Aufbewahrung. Ausreichend Platz am Server und am Ziel überwachen.

Jede Installation besitzt eine Kennung, damit mehrere Installationen im Zielverzeichnis nicht
gegenseitig aufräumen. Beim Restore wird die Kennung des gesicherten Systems übernommen.
Nicht zwei produktive Server mit derselben wiederhergestellten Identität betreiben.

### Inhalt und Dateinamen

Beispiel (Zeitstempel im Dateinamen ist **UTC**, `Z`; Menüauswahl zeigt Europe/Berlin):

```text
kienzlezeit_kienzlebox_20261116T010000Z_v1.6.7_a1b2c3d4e5f60718_012345abcdef.zip
```

Jedes vollständige Archiv besitzt zusätzlich eine Datei `.zip.sha256` mit der externen Prüfsumme.
Das ZIP enthält ausschließlich:

- `kienzlezeit.sqlite`
- `auslagen.sqlite`, sofern vorhanden – auch wenn die Auslagenfunktion deaktiviert ist
- `terminal-secret.key`
- `manifest.json`: Sicherungsformat/-version, Anwendungsversion, Datum, Installationskennung,
  Schemaversionen, Dateigrößen und SHA-256-Prüfsummen

SQLite-Snapshots werden über die Backup-API erstellt. Kurze gleichzeitige Schreibsperren auf
beiden Datenbanken halten die Snapshots zusammenpassend; Leser können weiterarbeiten.
Schreibzugriffe können kurz warten oder bei Überschreiten ihres Timeouts fehlschlagen – deshalb
eine ruhige Sicherungszeit wählen. Apache wird für das Backup nicht gestoppt.

Archiv und Datenbanken werden lokal geprüft, einschließlich Entschlüsselbarkeit gespeicherter
Terminal-Secrets. Die Übertragung verwendet zunächst `.part`; vor Veröffentlichung wird das
Archiv vollständig zurückgelesen und per SHA-256 verglichen. Erst nach erfolgreicher Übertragung
wird die lokale Kopie aus der Outbox entfernt. Die entfernte Bereinigung läuft nur bei aktiviertem
Zeitplan nach einer erfolgreichen Sicherung/Übertragung, niemals allein beim Konfigurieren oder Restore.
Manuelle Tests bei deaktiviertem Zeitplan löschen keine alten Zielarchive.

### Passwort und Sicherheit

Das Backup-Passwort ist für unbeaufsichtigte Sicherungen lokal verfügbar und steht in der nur
für root lesbaren Konfiguration, nicht in Cronzeilen, Prozessargumenten, Ausgaben oder Archiven.
Diese Absicherung schützt nicht vor einer bereits kompromittierten root-Umgebung.

Passwort zusätzlich separat aufbewahren. **Ohne das damalige Passwort kein Restore.** Ein neues
Passwort gilt nur für neue Archive. ZIP-Dateinamen und die Namen der enthaltenen Dateien bleiben
auch bei AES-ZIP sichtbar. Ohne Passwortschutz sind auch Bankdaten und die Terminal-Schlüsseldatei
im ZIP lesbar. Prüfsummen erkennen Beschädigungen, ersetzen aber keine digitale Signatur.

## Wiederherstellung – Menüpunkt 4

Der vorgesehene Ablauf auf einem neuen Server:

1. **`installer.sh` ausführen** und die normale kienzlezeit-Installation fertigstellen.
2. **`kienzlezeit-backup-und-restore.sh` starten.**
3. Unter **1** das vorhandene Backupziel konfigurieren. Noch keinen Cronjob aktivieren.
4. Unter **4** das gewünschte entfernte Archiv auswählen oder eine lokale ZIP-Datei angeben.
5. Bei Verschlüsselung das Passwort **dieser Sicherung** eingeben.
6. Prüfungen abwarten, Datum/Version kontrollieren und `WIEDERHERSTELLEN` eingeben.
7. Nach erfolgreichem Restore unter **2** automatische Backups einrichten/aktivieren.

**Dies ist ein vollständiger Ersatz, kein Datenimport zum Zusammenführen.** Änderungen nach dem
Sicherungszeitpunkt fehlen anschließend. Benutzer, Admin-Zugänge, Buchungen, Konfiguration in
den Datenbanken und Terminal-Secrets stammen danach wieder aus der Sicherung. Backupziel und
dessen Zugangsdaten bleiben erhalten. Browser müssen sich neu anmelden.

Vor dem Ersetzen werden Archivstruktur, Passwort, Prüfsummen, SQLite-Integrität, Schlüsselzuordnung
und Versionsverträglichkeit geprüft. Version 1.0 akzeptiert keine neuere Anwendungsversion und
keine abweichenden Datenbankschemata; in diesem Fall zuerst den passenden Anwendungsinstaller nutzen.
Es werden keine ausführbaren Dateien aus dem Archiv übernommen. Größenlimit: 8 GiB je Archiv
und insgesamt für den entpackten Inhalt.

Während des Einspielens wird **Apache kurz gestoppt**, also auch andere dort gehostete Seiten.
Der vorherige Zustand wird unter `/var/lib/kienzlezeit-backup/recovery/` gesichert; diese geschützten
lokalen Rückfallsicherungen werden nicht automatisch gelöscht und sind nicht ZIP-verschlüsselt.
Bei Fehlern vor der Freigabe wird zurückgesetzt. Ein persistenter Apache-Zugriffsschutz und ein
Restore-Journal verhindern nach einem Abbruch die Freigabe eines teilweise eingespielten Bestands.
Bei Stromausfall/Abbruch das Backup-Programm erneut interaktiv starten und die angebotene
Wiederaufnahme beziehungsweise Rücksetzung ausführen. Den Schutz nicht manuell löschen.

Nach erfolgreichem Restore verwendet kienzlezeit ein neues, eigenes PHP-Sitzungsverzeichnis.
Sitzungen anderer Webanwendungen werden nicht gelöscht. Apache mit mod_php gemäß normalem
Installer ist dafür Voraussetzung; alternative PHP-FPM-Konfigurationen sind nicht unterstützt.

## Dateien, Status und manuelle Aufrufe

| Pfad | Zweck |
|---|---|
| `/etc/kienzlezeit-backup/config.json` | Ziel, Zugangsdaten, Passwort und Zeitplan; root, `0600` |
| `/etc/kienzlezeit-backup/` | private SSH-Schlüssel und bestätigte Hostschlüssel; root, `0700` |
| `/usr/local/sbin/kienzlezeit-backup-und-restore.sh` | bei Cronaktivierung installierte Programmkopie |
| `/etc/cron.d/kienzlezeit-backup` | genau ein verwalteter Cronjob |
| `/var/lib/kienzlezeit-backup/status.json` | letzter Versuch, Erfolg und Fehlerstatus |
| `/var/lib/kienzlezeit-backup/outbox/` | noch nicht erfolgreich übertragene Archive, getrennt je Ziel |
| `/var/lib/kienzlezeit-backup/recovery/` | lokale Rückfallsicherungen vor Restores |
| `/var/lib/kienzlezeit-backup/restore-pending.json` | Journal während einer Wiederherstellung |
| `/etc/apache2/conf-enabled/kienzlezeit-restore.conf` | temporärer, persistenter Restore-Zugriffsschutz |
| `/etc/apache2/conf-enabled/kienzlezeit-restored-sessions.conf` | isolierter Sitzungsspeicher nach Restore |

```bash
sudo bash kienzlezeit-backup-und-restore.sh --backup
sudo bash kienzlezeit-backup-und-restore.sh --status
bash kienzlezeit-backup-und-restore.sh --version
```

`--backup` setzt ein konfiguriertes Ziel und eine bewusste Auswahl des Passwortschutzes unter **2**
voraus, aktiviert aber keinen Zeitplan. **5** zeigt den Status und prüft zusätzlich die Verbindung.
Bei mehr als 36 Stunden seit dem letzten Erfolg erscheint eine Warnung. Es ist kein Maildienst
enthalten; Status regelmäßig prüfen oder Rückgabecode/Ausgabe extern überwachen.

Für ein Update die neue Datei erneut herunterladen, starten und unter **2** den bestehenden
Zeitplan bestätigen; dabei wird die installierte Programmkopie aktualisiert. Keine Sicherung,
kein Passwort und kein Cronjob wird allein durch den Download geändert.

Eine zusätzliche getrennte/offline oder unveränderliche Kopie schützt gegen versehentliches
Löschen und kompromittierte Systeme. Ein Backup ist keine vollständige Systemsicherung:
Anwendungsdateien, Betriebssystem, Apache-/TLS-Konfiguration und Zielzugänge müssen separat
installiert beziehungsweise wieder eingerichtet werden.

## Tests und Versionshistorie

```bash
bash -n kienzlezeit-backup-und-restore.sh
python3 -m unittest discover -s tests -p 'test_backup_restore.py' -v
```

Die Tests nutzen ausschließlich temporäre Daten, echte PHP-ZIP-Verschlüsselung und echte SQLite-
Datenbanken. Serviceverwaltung und SMB-Transport werden simuliert. Wenn lokal verfügbar, wird
zusätzlich das echte OpenSSH-SFTP-Dateiprotokoll über einen lokalen `sftp-server` getestet
(ohne Netzwerkziel oder Änderung an Benutzerkonten). Ein
Test gegen das eigene SSH-/SMB-Ziel und ein Restore auf einem Testserver bleiben vor dem
Produktivbetrieb erforderlich.

- **1.0 – 16.11.2026:** Erste eigenständige Version mit automatischer Installation der Abhängigkeiten,
  SSH/SFTP und SMB, optional AES-256-ZIP, Cron, 30-Tage-/dauerhafter Wochenaufbewahrung sowie
  geprüftem Restore mit lokaler Rückfallsicherung und Wiederaufnahme nach Abbruch.
