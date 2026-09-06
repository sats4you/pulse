# Erste Sats – technischer Go-Live-Nachweis

Datum: 6. September 2026

Domain: `https://sats4you.ch`

Installation: `sats4you-rc1/public`

Prüfende Person: Andreas Kuoni, begleitet durch Codex

Status: kontrollierte öffentliche Beta technisch freigegeben

## Freigegebener Umfang

- Neutrale sats4you-Plattformseite auf `/`;
- **Erste Sats** auf `/erste-sats`;
- **Pulse** auf `/pulse`;
- Deutsch, Französisch, Italienisch und Rumantsch Grischun;
- Italienisch und Rumantsch Grischun sichtbar als Community-Review-Fassungen;
- interne, geheimnisgeschützte Betreiberansicht ohne individuelle Nutzerdaten.

## Nachgewiesene technische Prüfungen

- Der Produktions-Deployment-Check meldete nach erfolgreichem Löschlauf: `OK: PHP, Erweiterungen, HTTPS-Konfiguration und Datenbankschema sind bereit.`
- Die Landingpage sowie die Einstiege von **Erste Sats** und **Pulse** antworteten auf der echten Domain erfolgreich.
- `www.sats4you.ch` leitet dauerhaft auf `sats4you.ch` um und erhält Pfad und Query.
- CSP, HSTS, `Referrer-Policy: no-referrer`, `X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY` und eine restriktive Permissions Policy wurden auf der echten Domain festgestellt.
- Das technisch notwendige Erste-Sats-Formularcookie wurde mit `Secure`, `HttpOnly` und `SameSite=Strict` ausgeliefert.
- Der tägliche gemeinsame Löschlauf und der fünfminütige Pulse-Benachrichtigungslauf wurden auf dem Produktionsserver erfolgreich manuell ausgeführt und als Cronjobs eingerichtet.
- Die vier Betreiberzugangstexte wurden nach dem Upload auf der echten Domain geprüft.

## Erfolgreiche fachliche Live-Abläufe

- Eine Einladung mit `0` Sats wurde vollständig als reine Lernreise abgeschlossen.
- Eine Einladung mit positivem Betrag wurde einschliesslich echter, betragsgleicher BOLT11-Rechnung, externer Lightning-Zahlung und freiwilliger Empfangsbestätigung vollständig abgeschlossen.
- Die vier Lernetappen, falsche und richtige Antworten, Sprachwechsel, Wallet-Anleitungen, Teilnehmer-, Verwaltungs-, Wiederherstellungs- und Fortsetzungszugänge wurden im Freigabeprozess geprüft.
- Der geheime Betreiberzugang funktionierte. Das Dashboard zeigte nur aggregierte beziehungsweise bei kleinen Gruppen unterdrückte Kennzahlen, keine Namen, Einladungslinks, Wallet-Rechnungen oder individuellen Lernverläufe.
- Der vorbereitete Akquisetext für mögliche Einladende war vollständig sichtbar und kopierbar.

## Datenbank und Betrieb

Die gemeinsame sats4you-Datenbank enthält fachlich getrennte Pulse- und `first_sats_*`-Tabellen. Die Migrationen bis einschliesslich `015` sind angewendet. Laufzeitkonfiguration und Betreibergeheimnis liegen nicht im Repository; dokumentiert wird nur der erfolgreiche Prüfstatus.

## Noch offene Nacharbeiten

- lima-city-Vertrags- und Auftragsbearbeitungsnachweis intern ablegen;
- Server-Dateiberechtigungen der Laufzeitkonfiguration und nicht öffentliche Pfade gezielt nachprüfen;
- erste automatische Cronläufe und Produktionsprotokolle innerhalb von 24 Stunden kontrollieren;
- reale Geräteprüfung auf iPhone und Android ergänzen;
- externe schweizerische Rechtsprüfung nachholen;
- Italienisch und Rumantsch Grischun anhand von Community-Rückmeldungen weiter prüfen.

Diese Punkte sind bewusst als Nacharbeiten dokumentiert und werden nicht als bereits erledigt dargestellt. Geheimnisse, vollständige Zugangslinks, Rechnungen und personenbezogene Testdaten sind in diesem Nachweis nicht enthalten.
