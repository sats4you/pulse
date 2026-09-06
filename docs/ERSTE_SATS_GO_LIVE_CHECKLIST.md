# Erste Sats – Go-Live-Readiness

Stand: 6. September 2026
Status: öffentliche Beta auf `https://sats4you.ch` technisch freigegeben und erfolgreich im Livebetrieb geprüft

## 1. Lokal bestanden

- [x] Der mobile Kernablauf unterstützt Einzeleinladungen mit `0` bis `100'000` Sats ohne Benutzerkonto.
- [x] Vier feste Lernetappen, Wallet-Wahl, BOLT11-Prüfung, Übergabe und Abschluss sind automatisiert getestet.
- [x] Geheimnisse werden hochentropisch erzeugt; serverseitig liegen HMAC-Prüfwerte.
- [x] Einladungs-, Verwaltungs-, Wiederherstellungs- und Fortsetzungszugänge sind rollenbezogen getrennt.
- [x] Keine Seed Phrase, privaten Schlüssel, Wallet-PINs, Passwörter, Geburtsdaten oder Ausweisdokumente werden verlangt.
- [x] Teilnahmebedingungen und Datenschutzerklärung sind vollständig, viersprachig und versioniert.
- [x] Betreiber- und Mailanbieterangaben sind mit Andreas Kuoni und lima-city ergänzt.
- [x] DE und FR sind durch den Betreiber für den aktuellen Stand freigegeben.
- [x] Öffentliche Seiten sind indexierbar; Einladung, Wiederherstellung, Verwaltung und Betreiberbereich bleiben `noindex`.
- [x] Lokale Runtime-Daten, SQLite-Dateien, `.env`, `config/runtime.php` und Schlüsseldateien sind vom Repository ausgeschlossen.
- [x] Packagist meldet für den gesperrten Abhängigkeitsstand keine bekannten Sicherheitslücken.
- [x] Wartungsmodus liefert ohne Datenbankzugriff eine neutrale `503`-Seite.
- [x] Der Löschlauf schreibt nur einen nicht personenbezogenen Erfolgszeitpunkt; der Deployment-Check lehnt fehlende oder über 26 Stunden alte Läufe ab.
- [x] Der MVP-Restore-Weg löscht im Wartungsmodus alle wiederhergestellten einladungsbezogenen Erste-Sats-Daten und erhält nur anonyme Summenzähler.
- [x] Wiederherstellungsbereinigung verweigert den Start ohne Wartungsmodus und ausdrückliches Bestätigungsargument.
- [x] Vollständige PHPUnit-Suite: 167 Tests und 2'776 Assertions erfolgreich.

## 2. Vor Upload

- [x] Erste öffentliche Beta verbindlich in Deutsch, Französisch, Italienisch und Rumantsch Grischun beschlossen.
- [x] IT/RM bleiben sichtbar als Community-Review-Fassungen gekennzeichnet; ihre weitergehende Prüfung ist kein Go-Live-Blocker.
- [ ] Konkreten lima-city-Produktionsvertrag und die anwendbare Auftragsbearbeitungsvereinbarung als internen Nachweis ablegen.
- [x] Release-Stand in Git sauber festhalten; keine lokale Vorschau-Datenbank, Laufzeitkonfiguration oder Geheimnisse aufnehmen.
- [x] Produktionsabhängigkeiten mit `composer install --no-dev --classmap-authoritative` im Release Candidate erzeugt.
- [x] Release Candidate `sats4you-beta-2026-08-30-rc1.zip` ohne `.git`, `.runtime`, `tests`, lokale Daten, Logs, Geheimnisse und Entwicklungsdateien kontrolliert.

## 3. Einrichtung auf lima-city

- [x] Anwendung zunächst im getrennten Ordner `sats4you-rc1` vorbereiten und erst nach erfolgreicher Prüfung umschalten.
- [x] PHP 8.3 oder neuer sowie `json`, `intl`, `mbstring`, `pdo` und `pdo_mysql` aktivieren.
- [x] Gemeinsame sats4you-Datenbank mit fachlich getrennten Pulse- und `first_sats_*`-Tabellen anbinden.
- [x] Migrationen `001` bis `015` in aufsteigender Reihenfolge anwenden.
- [x] `config/runtime.php` ausserhalb von `public` erstellen.
- [ ] Dateiberechtigungen von `config/runtime.php` auf dem Server nochmals ausdrücklich kontrollieren und dokumentieren.
- [x] `APP_ENV=production`, `APP_BASE_URL=https://sats4you.ch` und für die Freigabe `APP_MAINTENANCE=0` setzen.
- [x] `FIRST_SATS_PUBLIC_LOCALES=de,fr,it,rm` setzen.
- [x] Einen neuen zufälligen `APP_HMAC_KEY` mit mindestens 32 Zeichen erzeugen und offline sichern.
- [x] Betreiberzugang einmalig erzeugen, Geheimnis offline sichern und nur dessen HMAC-Prüfwert konfigurieren.
- [x] Mailkonfiguration auf die autorisierten lima-city-Adressen setzen.
- [x] Täglichen Shell-Cronjob für `php bin/pulse-retention.php` mit `flock` einrichten.
- [x] Benachrichtigungs-Cronjob für `pulse` getrennt einrichten.
- [x] Löschlauf einmal manuell erfolgreich ausführen, damit der erste Heartbeat vorhanden ist.

## 4. Technische Freigabe vor Umschaltung

- [x] `display_errors=Off`, `log_errors=On` und `expose_php=Off` kontrollieren.
- [x] `APP_MAINTENANCE=0` setzen und sofort `php bin/deployment-check.php` ausführen.
- [x] Deployment-Check mit `OK: PHP, Erweiterungen, HTTPS-Konfiguration und Datenbankschema sind bereit.` abschliessen.
- [x] Document Root exakt auf `sats4you-rc1/public` setzen und Verzeichnisauflistung deaktivieren.
- [ ] `config/runtime.php`, Datenbankdateien, Logs und Anwendungscode zusätzlich mit gezielten HTTP-Abrufversuchen kontrollieren.
- [x] TLS und HTTPS für `sats4you.ch` kontrollieren; `www.sats4you.ch` leitet mit HTTP 308 auf die Hauptdomain weiter und erhält Pfad sowie Query.
- [x] Sicherheitsheader auf der Live-Domain kontrollieren: CSP, HSTS, `no-referrer`, `nosniff`, `DENY` und restriktive Permissions Policy sind aktiv.
- [x] Technisch notwendige Erste-Sats-Cookies tragen auf HTTPS `Secure`, `HttpOnly` und `SameSite=Strict`.

## 5. Smoke-Test auf der echten Domain

- [x] Öffentliche Startseite, FAQ, Teilnahmebedingungen, Datenschutz und CHF-Methodik in allen vier Sprachen öffnen.
- [x] IT und RM erscheinen mit ihrer jeweiligen sichtbaren Community-Review-Kennzeichnung in der Sprachwahl.
- [x] Testeinladung mit `0` Sats vollständig abschliessen.
- [x] Testeinladung mit kleinem positivem Sats-Betrag vollständig bis zur externen Zahlung und freiwilligen Empfangsbestätigung durchlaufen.
- [x] Falsche und richtige Antworten in allen vier Etappen prüfen.
- [x] Wallet of Satoshi, Phoenix und freie Wallet-Wahl mindestens bis zur jeweiligen Anleitung prüfen.
- [x] Eine echte BOLT11-Rechnung mit exakt passendem Betrag und ausreichender Laufzeit prüfen.
- [x] Einladungs-, Verwaltungs-, Wiederherstellungs- und Fortsetzungslink getrennt testen.
- [x] Sprachwechsel darf keinen Fortschritt verlieren.
- [x] Betreiber-Dashboard zeigt nur aggregierte beziehungsweise unterdrückte Werte und keine individuellen Lernverläufe.
- [ ] Keine Geheimnisse, URL-Fragmente oder BOLT11-Rechnungen in Server-, Fehler- oder Cronprotokollen feststellen.
- [x] Öffentliche Seiten liefern `index, follow`; geheime und interne Seiten `noindex, nofollow, noarchive`.
- [ ] Mobile Darstellung auf einem realen iPhone- und Android-Browser prüfen.

## 6. Inhaltliche und rechtliche Freigabe

- [x] Fachinhalt der vier Etappen durch den Betreiber redaktionell freigegeben.
- [x] Berechtigte Kritik, Risiken, Eigenverantwortung, Gebühren und TWINT-Abgrenzung sind sichtbar enthalten.
- [x] Rechtstexte nennen sichtbar, dass die schweizerische Rechtsprüfung aussteht.
- [x] IT/RM als vollständige, sichtbar gekennzeichnete Community-Review-Fassungen für die erste Beta freigeben.
- [x] Interne Rechtstext- und Datenschutzprüfung nach dem echten Domain-Test nochmals bestätigt.
- [ ] Externe Schweizer Rechtsprüfung nach dem Go-Live priorisiert durchführen und Korrekturen versionieren.

## 7. Freigabe und Rückfallplan

- [x] Datum, Release-Stand, Migrationen und prüfende Person dokumentieren – ohne Geheimnisse.
- [x] Nach bestandenem Deployment-Check öffentlich auf `sats4you-rc1/public` umschalten.
- [ ] Bei einem kritischen Fehler sofort `APP_MAINTENANCE=1` setzen oder den Website-Pfad auf die bisherige Seite zurückstellen.
- [ ] Keine Datenbank im öffentlich erreichbaren Zustand wiederherstellen.
- [ ] Nach einem Restore das dokumentierte Purge-, Löschlauf- und Zugangsrotationsverfahren vollständig durchführen.
- [ ] In den ersten 24 Stunden Fehlerprotokolle und den ersten automatischen Löschlauf kontrollieren, ohne Nutzerdaten in ein externes Monitoring zu übertragen.

## Offene Nacharbeiten nach dem technischen Go-Live

1. Konkreten lima-city-Vertrags- und Auftragsbearbeitungsnachweis intern ablegen.
2. Dateiberechtigungen der geheimen Laufzeitkonfiguration und nicht öffentliche Pfade nochmals gezielt prüfen.
3. Fehler- und Cronprotokolle nach den ersten realen Abläufen auf technische Fehler und versehentliche Geheimnisse kontrollieren.
4. Darstellung zusätzlich auf einem realen iPhone und Android-Gerät prüfen.
5. Den ersten automatischen Löschlauf innerhalb der ersten 24 Stunden kontrollieren.
6. Externe schweizerische Rechtsprüfung priorisiert nachholen und allfällige Korrekturen versionieren.

Diese Nacharbeiten bleiben wichtig, blockieren gemäss Betreiberentscheid jedoch nicht die bereits gestartete, kontrollierte öffentliche Beta. Der sichtbare Hinweis zur ausstehenden schweizerischen Rechtsprüfung darf bis zu deren Abschluss nicht entfernt werden.
