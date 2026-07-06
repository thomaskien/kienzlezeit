# Auslagen & Kilometer 1.1.3

Die Auslagenverwaltung ist eine optionale, lokal betriebene Ergänzung zu `kienzlezeit`. Sie verwendet
die bestehende kienzlezeit-Anmeldung, bleibt technisch aber in einer eigenen PHP-Datei und einer eigenen
SQLite-Datenbank gekapselt.

## Funktionen

- nur für aktive, angemeldete kienzlezeit-Mitarbeitende und -Admins erreichbar
- global durch einen Admin in den kienzlezeit-Einstellungen aktivierbar oder deaktivierbar
- Belege und Kilometer in einem gemeinsamen Erstattungsvorgang
- ausschließlich administrativer Ja/Nein-Status „Beleg vorhanden“ für jeden Belegposten
- Adminübersicht mit buchhalterischem Gesamtstatus „vollständig“, „fehlt“ oder „offen“ je Vorgang
- Antrag, Mitarbeiterdetail und Mitarbeiterdruck enthalten keinen internen Belegstatus
- eindeutige Belegnummern im Format `2026-AUSL-0001`
- historisierte Erstattungskonten je Mitarbeiter
- unveränderlicher Konto- und Namensschnappschuss je eingereichtem Vorgang
- Mitarbeitende sehen und drucken ausschließlich eigene Vorgänge
- Adminstatus: Eingereicht, Genehmigt, Bezahlt, Abgelehnt oder Storniert
- nachvollziehbares Status- und Änderungsprotokoll ohne dauerhafte Beleglöschung
- lokale EPC-/GiroCode-Erzeugung mit `qrencode`; Webanzeige ausschließlich für Admins
- A4-Druckansicht mit Tabelle, Erstellungsdatum und Unterschriftsfeldern
- bestehende Version-1.0-Daten bleiben als nicht automatisch zugeordneter Altbestand erhalten

## Installation

Die Auslagenverwaltung wird ausschließlich über den gemeinsamen `installer.sh` im Projektstamm
installiert. Dieser installiert `qrencode`, legt die Webdatei als `/var/www/html/auslagen.php` ab und
führt die Datenbank unter `/var/lib/kienzlezeit/auslagen.sqlite`.

Eine im Quellordner vorhandene `auslagen.sqlite` wird nur nach ausdrücklicher Bestätigung als Altbestand
übernommen. Vorhandene Zieldatenbanken werden vor jedem Update gesichert und additiv migriert.

Anschließend aktiviert ein Admin die Funktion unter **Administration → Einstellungen**. Ein separates
Auslagen-Adminpasswort existiert ab Version 1.1 nicht mehr.

## Datenschutz und Revision

- Die Datenbank liegt nicht im Apache-Webverzeichnis.
- IBAN und BIC anderer Mitarbeitender werden nie an Mitarbeiterbrowser ausgeliefert.
- Kontoänderungen überschreiben den bisherigen Datensatz nicht.
- Abgelehnte, bezahlte oder stornierte Vorgänge bleiben erhalten.
- Altdaten werden wegen möglicher Namensgleichheit ausschließlich manuell durch einen Admin zugeordnet.

Regelmäßige, konsistente Backups sind dringend empfohlen:

```bash
sudo sqlite3 /var/lib/kienzlezeit/auslagen.sqlite \
  ".backup '/SICHERER-PFAD/auslagen-YYYY-MM-DD.sqlite'"
```

Vor einer Veröffentlichung auf GitHub müssen insbesondere SQLite-, WAL-/SHM- und Sicherungsdateien
ausgeschlossen werden.

**Version:** 1.1.3<br>
**Autor:** Dr. Thomas Kienzle
