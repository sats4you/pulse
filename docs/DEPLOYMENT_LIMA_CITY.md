# Deployment auf lima-city

Status: Vorbereitete Pilotinstallation, 16. August 2026. Die echten Zugänge werden erst nach erfolgreichem Vorab-Check erzeugt.

## Zielstruktur

lima-city erlaubt, eine Website einem frei gewählten Ordner auf dem Webspace zuzuordnen. Für pulse wird die vollständige Anwendung in einen eigenen Ordner hochgeladen und nur dessen Unterordner `public` als Website-Pfad verwendet:

```text
/home/webpages/lima-city/<benutzername>/pulse/
├── app/
├── bin/
├── config/
│   └── runtime.php       # geheim, nicht im Git-Repository
├── database/
├── public/               # einziger öffentlich zugeordneter Ordner
├── resources/
└── vendor/
```

In der lima-city-Verwaltung wird `sats4you.ch` unter **Websites & Domains → Inhalt ändern** dem Pfad `pulse/public` zugeordnet. Vor der Umschaltung bleibt die bestehende Weiterleitung erreichbar. Die Umschaltung erfolgt erst, wenn Anwendung und Datenbank vollständig vorbereitet sind.

## 1. PHP und Dateien

Für die Website PHP 8.3 oder neuer verwenden. Pulse benötigt `json`, `intl`, `mbstring`, `pdo` und `pdo_mysql`.

Die Anwendung inklusive der mit `composer install --no-dev --classmap-authoritative` installierten Produktionsabhängigkeiten nach `pulse/` hochladen. Nicht hochgeladen werden:

- `.git/`, `.runtime/`, `tests/`, `.phpunit.cache/` und lokale Daten;
- `.env`-Dateien;
- reale Zugangslinks oder Wiederherstellungscodes.

## 2. Datenbank

In der lima-city-Verwaltung eine eigene MySQL-Datenbank und einen ausschliesslich dafür verwendeten Datenbankbenutzer anlegen. Danach `database/migrations/001_create_pulse_tables.sql` einmal über phpMyAdmin oder die MySQL-Konsole importieren.

Der Benutzer benötigt für den laufenden Pilot nur Rechte auf dieser Datenbank. Keine Rechte auf Datenbanken anderer Websites vergeben.

## 3. Geheime Laufzeitkonfiguration

`config/runtime.example.php` auf dem Server als `config/runtime.php` kopieren und alle Platzhalter ersetzen. Die Datei liegt ausserhalb von `public`, ist in `.gitignore` ausgeschlossen und darf nie in GitHub, Supporttickets oder Chatnachrichten eingefügt werden.

Für `APP_HMAC_KEY` mindestens 32 zufällige Zeichen verwenden. Dieser Schlüssel ist nicht Teilnehmer-, Verwaltungs- oder Wiederherstellungscode. Ein Wechsel macht sämtliche gespeicherten Zugangs- und Zusageprüfwerte ungültig.

lima-city sichert Webspace und Datenbanken gemäss veröffentlichter Hostingbeschreibung. Dadurch kann auch die nicht öffentliche Laufzeitkonfiguration bis zu 90 Tage in verschlüsselten Betreiberbackups verbleiben. Das ist eine akzeptierte und in der Datenschutzerklärung offengelegte Pilotgrenze. Nach jeder Wiederherstellung bleibt die in `docs/OPERATIONS.md` beschriebene Rotation aller drei Gruppenzugänge zwingend.

## 4. Vorab-Check ohne echte Gruppenzugänge

Über SSH im Anwendungsverzeichnis ausführen:

```text
php bin/deployment-check.php
```

Der Check prüft PHP-Version, Erweiterungen, HTTPS-Basis-URL, Mindestlänge des HMAC-Schlüssels, Datenbankverbindung und Migration. Er gibt keine Konfigurationswerte oder Geheimnisse aus. Erst bei der Ausgabe `OK` folgt die Gruppeneinrichtung.

## 5. Tägliche Löschung

In der lima-city-Verwaltung einen täglichen **Shell-Cronjob** anlegen. `<absoluter-pfad>` wird durch den in der Verwaltung beziehungsweise per SSH sichtbaren pulse-Pfad ersetzt:

```text
cd <absoluter-pfad>/pulse && flock -n ~/.pulse-retention.lock php bin/pulse-retention.php
```

Der Job ist idempotent und gibt nur aggregierte Löschzahlen aus. Für diesen Pilot wird kein öffentlich erreichbarer URL-Cronjob verwendet.

## 6. Einmalige Gruppeneinrichtung

Dieser Schritt wird erst ausgeführt, wenn Andreas die einmalige Ausgabe unmittelbar sicher speichern kann:

```text
php bin/provision-round.php bern-bitcoin "Bern Monthly Bitcoin Meetup"
```

Das Terminal zeigt genau einmal Teilnehmerlink, Verwaltungslink und Wiederherstellungscode. Der Wiederherstellungscode wird offline und getrennt vom Verwaltungslink aufbewahrt. Die Ausgabe wird nicht fotografiert, in ein Ticket kopiert oder in einer Shell-History als Argument verwendet.

## 7. Umschaltung und reale Prüfung

Nach der Einrichtung:

1. Website-Pfad von `sats4you.ch` auf `pulse/public` umstellen.
2. HTTPS-Aufruf und Sprachwechsel in Deutsch, Französisch, Italienisch und Rumantsch Grischun prüfen.
3. Sicherheitsheader und Cookie-Attribute im echten Browser prüfen.
4. Einen Testtermin erstellen, veröffentlichen, zusagen und zurückziehen.
5. Sicherstellen, dass weder URL-Fragmente noch Geheimnisse in lima-city-Protokollen erscheinen.
6. Den Löschjob einmal manuell ausführen und nur die aggregierte Erfolgsausgabe kontrollieren.
7. Erst danach den Teilnehmerlink an die Pilotgruppe weitergeben.

## Noch ausstehende Freigabepunkte

- Verantwortlicher und Kontaktangaben in der final sichtbaren Datenschutzerklärung bestätigen;
- Auftragsverarbeitungsvertrag mit lima-city im Kundenkonto prüfen beziehungsweise abschliessen;
- Repository samt Selbsthosting-Anleitung öffentlich schalten, bevor pulse öffentlich als Open Source bezeichnet wird;
- finalen Browser- und Logtest auf der echten Domain dokumentieren.
