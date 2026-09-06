# Deployment auf lima-city

Status: Öffentliche Beta seit 6. September 2026 auf `https://sats4you.ch`. Die kombinierte Installation für **Erste Sats** und **Pulse** wurde auf der echten Domain technisch geprüft.

## Zielstruktur

lima-city erlaubt, eine Website einem frei gewählten Ordner auf dem Webspace zuzuordnen. Die vollständige modulare Anwendung für sats4you.ch wird in einen eigenen Ordner hochgeladen und nur deren Unterordner `public` als Website-Pfad verwendet:

```text
/home/webpages/lima-city/<benutzername>/sats4you-rc1/
├── app/
├── bin/
├── config/
│   └── runtime.php       # geheim, nicht im Git-Repository
├── database/
├── public/               # einziger öffentlich zugeordneter Ordner
├── resources/
└── vendor/
```

In der lima-city-Verwaltung ist `sats4you.ch` unter **Websites & Domains → Inhalt ändern** dem Pfad `sats4you-rc1/public` zugeordnet. Die neutrale Plattformseite liegt auf `/`, **Erste Sats** auf `/erste-sats` und **Pulse** auf `/pulse`.

## 1. PHP und Dateien

Für die Website PHP 8.3 oder neuer verwenden. Pulse benötigt `json`, `intl`, `mbstring`, `pdo` und `pdo_mysql`.

Die Anwendung inklusive der mit `composer install --no-dev --classmap-authoritative` installierten Produktionsabhängigkeiten nach `sats4you-rc1/` hochladen. Nicht hochgeladen werden:

- `.git/`, `.runtime/`, `tests/`, `.phpunit.cache/` und lokale Daten;
- `.env`-Dateien;
- reale Zugangslinks oder Wiederherstellungscodes.

## 2. Datenbank

In der lima-city-Verwaltung eine MySQL-Datenbank und einen ausschliesslich für sats4you.ch verwendeten Datenbankbenutzer anlegen. **Erste Sats** und **Pulse** dürfen diese Datenbank innerhalb des modularen Monolithen gemeinsam verwenden; ihre Tabellen bleiben über die fachlich getrennten Namensbereiche klar unterscheidbar. Danach alle nummerierten Dateien in `database/migrations/` in aufsteigender Reihenfolge einmal über phpMyAdmin oder die MySQL-Konsole importieren. Bei einer bestehenden Installation wird nur die jeweils neue Migration angewendet.

Der Benutzer benötigt für den laufenden Pilot nur Rechte auf dieser Datenbank. Keine Rechte auf Datenbanken anderer Websites vergeben.

## 3. Geheime Laufzeitkonfiguration

`config/runtime.example.php` auf dem Server als `config/runtime.php` kopieren und alle Platzhalter ersetzen. Die Datei liegt ausserhalb von `public`, ist in `.gitignore` ausgeschlossen und darf nie in GitHub, Supporttickets oder Chatnachrichten eingefügt werden. `APP_MAINTENANCE` steht im Normalbetrieb ausdrücklich auf `0`. Vor einem Restore wird es auf `1` gesetzt; die öffentliche Anwendung antwortet dann ohne Datenbankzugriff mit einer neutralen `503`-Wartungsseite. Für die öffentliche Beta muss `FIRST_SATS_PUBLIC_LOCALES` exakt auf `de,fr,it,rm` stehen. Italienisch und Rumantsch Grischun erscheinen mit ihrer sichtbaren Community-Review-Kennzeichnung.

Für `APP_HMAC_KEY` mindestens 32 zufällige Zeichen verwenden. Dieser Schlüssel ist nicht Teilnehmer-, Verwaltungs- oder Wiederherstellungscode. Ein Wechsel macht sämtliche gespeicherten Zugangs- und Zusageprüfwerte ungültig.

Für die gebündelte Betreiberbenachrichtigung zusätzlich `NOTIFICATION_RECIPIENT`, `NOTIFICATION_FROM` und `NOTIFICATION_LOCALE` setzen. Die Werte bleiben in `config/runtime.php` und dürfen nicht in Repository oder Kommandoausgaben erscheinen.

### Interner Betreiberzugang für Erste Sats

Sobald `APP_HMAC_KEY` endgültig gesetzt ist und Andreas das einmal angezeigte Geheimnis unmittelbar sicher speichern kann, einmal ausführen:

```text
php bin/provision-first-sats-operator.php
```

Das Kommando zeigt das Betreibergeheimnis und den dazugehörigen HMAC-Prüfwert genau einmal. Das Geheimnis offline aufbewahren; nur den 64-stelligen Prüfwert als `FIRST_SATS_OPERATOR_SECRET_HASH` in `config/runtime.php` eintragen. Der persönliche Zugang wird lokal als `https://sats4you.ch/erste-sats/operator?lang=de#<Betreibergeheimnis>` zusammengesetzt. Weder den vollständigen Link noch das Geheimnis in Tickets, Chatnachrichten, Browser-Lesezeichen oder Serverprotokolle übernehmen.

lima-city sichert Webspace und Datenbanken gemäss veröffentlichter Hostingbeschreibung. Dadurch kann auch die nicht öffentliche Laufzeitkonfiguration bis zu 90 Tage in verschlüsselten Betreiberbackups verbleiben. Das ist eine akzeptierte und in der Datenschutzerklärung offengelegte Pilotgrenze. Nach jeder Wiederherstellung bleibt die in `docs/OPERATIONS.md` beschriebene Rotation aller drei Gruppenzugänge zwingend.

## 4. Vorab-Check ohne echte Gruppenzugänge

Nach dem Einrichten des täglichen Shell-Cronjobs aus Abschnitt 5 den Löschlauf einmal manuell ausführen. Erst wenn er erfolgreich war und seinen nicht personenbezogenen Kontrollzeitpunkt geschrieben hat, über SSH im Anwendungsverzeichnis ausführen:

```text
php bin/deployment-check.php
```

Der Check prüft PHP-Version, Erweiterungen, sichere PHP-Fehlerausgabe, Produktions- und Wartungsmodus, HTTPS-Basis-URL, die öffentliche Sprachliste `de,fr,it,rm`, Mindestlänge des HMAC-Schlüssels, Datenbankverbindung, sämtliche Migrationen von `pulse` und **Erste Sats**, die Form des gehashten Betreiberzugangs sowie einen höchstens 26 Stunden alten erfolgreichen Löschlauf. Er gibt keine Konfigurationswerte oder Geheimnisse aus. Erst bei der Ausgabe `OK` folgen Gruppeneinrichtung beziehungsweise Freigabe von **Erste Sats**.

## 5. Tägliche Löschung

In der lima-city-Verwaltung einen täglichen **Shell-Cronjob** anlegen. `<absoluter-pfad>` wird durch den in der Verwaltung beziehungsweise per SSH sichtbaren pulse-Pfad ersetzt:

```text
cd <absoluter-pfad>/sats4you-rc1 && flock -n <absoluter-pfad>/.sats4you-retention.lock php bin/pulse-retention.php
```

Der Job ist idempotent und gibt nur aggregierte Löschzahlen aus. Nach einem vollständig erfolgreichen Lauf aktualisiert er in `operational_heartbeats` ausschliesslich den Zeitpunkt für `retention`. Bei einem Fehler beendet er sich mit Fehlercode und einer generischen Meldung ohne Datenbank- oder Einladungsdetails. Für diesen Pilot wird kein öffentlich erreichbarer URL-Cronjob verwendet.

## 5a. Gebündelte Betreiberbenachrichtigung

Einen zweiten **Shell-Cronjob** alle fünf Minuten anlegen:

```text
cd <absoluter-pfad>/sats4you-rc1 && php bin/pulse-notifications.php
```

Der Dispatcher verwendet selbst eine MySQL-Sperre gegen parallele Läufe. Er wartet pro Termin fünf Minuten seit der letzten Änderung, bündelt An- und Abmeldungen und löscht erfolgreich versendete Warteschlangenzeilen sofort. Nicht zustellbare Zeilen werden erneut versucht und nach spätestens sieben Tagen gelöscht. Die Ausgabe enthält nur aggregierte Zähler.

## 6. Einmalige Gruppeneinrichtung

Dieser Schritt wird erst ausgeführt, wenn Andreas die einmalige Ausgabe unmittelbar sicher speichern kann:

```text
php bin/provision-round.php bern-bitcoin "Bern Monthly Bitcoin Meetup"
```

Das Terminal zeigt genau einmal Teilnehmerlink, Verwaltungslink und Wiederherstellungscode. Der Wiederherstellungscode wird offline und getrennt vom Verwaltungslink aufbewahrt. Die Ausgabe wird nicht fotografiert, in ein Ticket kopiert oder in einer Shell-History als Argument verwendet.

## 7. Umschaltung und reale Prüfung

Nach der Einrichtung:

1. Website-Pfad von `sats4you.ch` auf `pulse/public` umstellen.
2. HTTPS-Aufruf und Sprachwechsel in Deutsch, Französisch, Italienisch und Rumantsch Grischun prüfen; die Community-Review-Kennzeichnungen von IT und RM müssen sichtbar sein.
3. Sicherheitsheader und Cookie-Attribute im echten Browser prüfen.
4. Einen Testtermin erstellen, veröffentlichen, zusagen und zurückziehen.
5. Sicherstellen, dass weder URL-Fragmente noch Geheimnisse in lima-city-Protokollen erscheinen.
6. Den Löschjob einmal manuell ausführen und nur die aggregierte Erfolgsausgabe kontrollieren.
7. Den Benachrichtigungsjob bei leerer Warteschlange einmal manuell ausführen; es darf keine E-Mail versendet werden und die Ausgabe muss null versendete Meldungen zeigen.
8. Erst danach den Teilnehmerlink an die Pilotgruppe weitergeben.

## 8. Restore-Grenze von Erste Sats

Vor jedem Restore `APP_MAINTENANCE` auf `1` setzen. Ein Datenbankbackup kann frühere, zwischenzeitlich widerrufene Einladungs-, Verwaltungs- oder Wiederherstellungsprüfwerte von **Erste Sats** enthalten. Anders als bei einer einzelnen Pilotgruppe können diese Zugänge nicht zentral neu ausgegeben werden, ohne laufende Einladungen zu zerstören.

Für den MVP ist deshalb der einfache, sichere Weg verbindlich: Alle aus dem Backup wiederhergestellten, einladungsbezogenen Erste-Sats-Daten werden vor dem Öffnen gelöscht. Anonyme Summenzähler bleiben erhalten. Laufende Einladungen gehen bei einem solchen seltenen Katastrophen-Restore verloren.

Nach dem Restore und weiterhin mit `APP_MAINTENANCE=1` im Anwendungsverzeichnis ausführen:

```text
php bin/first-sats-post-restore-purge.php --confirm-delete-restored-first-sats-invitations
php bin/pulse-retention.php
```

Das erste Kommando verweigert den Start ohne Wartungsmodus und ohne die ausgeschriebene Bestätigung. Es löscht über die Fremdschlüssel auch Zahlungsanforderungen, Übergabestatus und einladungsbezogene Zählmarker, jedoch keine anonymen Summenzähler. Die aggregierte Ausgabe enthält keine IDs oder Geheimnisse.

Danach die drei Zugänge der separat verwalteten `pulse`-Pilotgruppe wie in `OPERATIONS.md` beschrieben rotieren. Erst wenn diese Schritte erfolgreich dokumentiert sind, `APP_MAINTENANCE` auf `0` setzen und sofort `php bin/deployment-check.php` ausführen. Bei einem Fehler unverzüglich wieder Wartungsmodus aktivieren; erst die Ausgabe `OK` erlaubt die Wiederöffnung.

## Noch ausstehende Freigabepunkte

- Testtermine und Testzusagen vor der Produktionsumschaltung bereinigen und den Teilnehmerlink ersetzen;
- finalen Browser- und Logtest auf der echten Domain dokumentieren.
