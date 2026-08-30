# Erste Sats – Go-Live-Readiness

Stand: 30. August 2026
Status: lokale Release-Prüfung weitgehend bestanden; vier Sprachen für Beta beschlossen; Produktionsprüfung noch offen

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
- [ ] Release-Stand in Git sauber festhalten; keine lokale Vorschau-Datenbank, Laufzeitkonfiguration oder Geheimnisse aufnehmen.
- [x] Produktionsabhängigkeiten mit `composer install --no-dev --classmap-authoritative` im Release Candidate erzeugt.
- [x] Release Candidate `sats4you-beta-2026-08-30-rc1.zip` ohne `.git`, `.runtime`, `tests`, lokale Daten, Logs, Geheimnisse und Entwicklungsdateien kontrolliert.

## 3. Einrichtung auf lima-city

- [ ] Website zunächst noch nicht auf den neuen Ordner umschalten.
- [ ] PHP 8.3 oder neuer sowie `json`, `intl`, `mbstring`, `pdo` und `pdo_mysql` aktivieren.
- [ ] Eigene MySQL-Datenbank und Benutzer mit Rechten nur für diese Datenbank anlegen.
- [ ] Migrationen `001` bis `015` in aufsteigender Reihenfolge anwenden.
- [ ] `config/runtime.php` ausserhalb von `public` erstellen und Berechtigungen auf den notwendigen Zugriff begrenzen.
- [ ] `APP_ENV=production`, `APP_MAINTENANCE=1` und `APP_BASE_URL=https://sats4you.ch` setzen.
- [ ] `FIRST_SATS_PUBLIC_LOCALES=de,fr,it,rm` setzen; der Deployment-Check akzeptiert für die öffentliche Beta keine andere Liste.
- [ ] Einen neuen zufälligen `APP_HMAC_KEY` mit mindestens 32 Zeichen erzeugen und offline sichern.
- [ ] Betreiberzugang einmalig erzeugen, Geheimnis offline sichern und nur dessen HMAC-Prüfwert konfigurieren.
- [ ] Mailkonfiguration auf die autorisierten lima-city-Adressen setzen.
- [ ] Täglichen Shell-Cronjob für `php bin/pulse-retention.php` mit `flock` einrichten.
- [ ] Benachrichtigungs-Cronjob für `pulse` getrennt einrichten, sofern der Gruppenpilot auf derselben Installation aktiv ist.
- [ ] Löschlauf einmal manuell erfolgreich ausführen, damit der erste Heartbeat vorhanden ist.

## 4. Technische Freigabe vor Umschaltung

- [ ] `display_errors=Off`, `log_errors=On` und `expose_php=Off` kontrollieren.
- [ ] `APP_MAINTENANCE=0` setzen und sofort `php bin/deployment-check.php` ausführen.
- [ ] Nur bei Ausgabe `OK` weiterfahren; sonst Wartungsmodus wieder aktivieren.
- [ ] Document Root exakt auf `pulse/public` setzen und Verzeichnisauflistung kontrollieren.
- [ ] Sicherstellen, dass `config/runtime.php`, Datenbankdateien, Logs und Anwendungscode nicht per HTTP abrufbar sind.
- [ ] TLS-Zertifikat und automatische HTTPS-Weiterleitung für `sats4you.ch` und die tatsächlich genutzte `www`-Variante kontrollieren.
- [ ] Echte Sicherheitsheader auf Startseite, Einladung und Verwaltung kontrollieren.
- [ ] Prüfen, dass Cookies auf HTTPS `Secure`, `HttpOnly` und `SameSite=Strict` tragen.

## 5. Smoke-Test auf der echten Domain

- [ ] Öffentliche Startseite, FAQ, Teilnahmebedingungen, Datenschutz und CHF-Methodik in allen vier Sprachen öffnen.
- [ ] IT und RM erscheinen mit ihrer jeweiligen sichtbaren Community-Review-Kennzeichnung in der Sprachwahl.
- [ ] Testeinladung mit `0` Sats vollständig abschliessen.
- [ ] Testeinladung mit kleinem positivem Sats-Betrag vollständig bis zur externen Zahlung und freiwilligen Empfangsbestätigung durchlaufen.
- [ ] Falsche und richtige Antworten in allen vier Etappen prüfen.
- [ ] Wallet of Satoshi, Phoenix und freie Wallet-Wahl mindestens bis zur jeweiligen Anleitung prüfen.
- [ ] Eine echte BOLT11-Rechnung mit exakt passendem Betrag und ausreichender Laufzeit prüfen.
- [ ] Einladungs-, Verwaltungs-, Wiederherstellungs- und Fortsetzungslink getrennt testen.
- [ ] Sprachwechsel darf keinen Fortschritt verlieren.
- [ ] Betreiber-Dashboard darf nur aggregierte Werte und keine individuellen Lernverläufe zeigen.
- [ ] Keine Geheimnisse, URL-Fragmente oder BOLT11-Rechnungen in Server-, Fehler- oder Cronprotokollen feststellen.
- [ ] Öffentliche Seiten liefern `index, follow`; geheime und interne Seiten `noindex, nofollow, noarchive`.
- [ ] Mobile Darstellung auf einem realen iPhone- und Android-Browser prüfen.

## 6. Inhaltliche und rechtliche Freigabe

- [x] Fachinhalt der vier Etappen durch den Betreiber redaktionell freigegeben.
- [x] Berechtigte Kritik, Risiken, Eigenverantwortung, Gebühren und TWINT-Abgrenzung sind sichtbar enthalten.
- [x] Rechtstexte nennen sichtbar, dass die schweizerische Rechtsprüfung aussteht.
- [x] IT/RM als vollständige, sichtbar gekennzeichnete Community-Review-Fassungen für die erste Beta freigeben.
- [ ] Interne Rechtstext- und Datenschutzprüfung nach dem echten Domain-Test nochmals bestätigen.
- [ ] Externe Schweizer Rechtsprüfung nach dem Go-Live priorisiert durchführen und Korrekturen versionieren.

## 7. Freigabe und Rückfallplan

- [ ] Datum, Uhrzeit, Release-Stand, Migrationen und prüfende Person dokumentieren – ohne Geheimnisse.
- [ ] Erst nach allen zwingenden Punkten öffentlich auf den neuen `public`-Ordner umschalten.
- [ ] Bei einem kritischen Fehler sofort `APP_MAINTENANCE=1` setzen oder den Website-Pfad auf die bisherige Seite zurückstellen.
- [ ] Keine Datenbank im öffentlich erreichbaren Zustand wiederherstellen.
- [ ] Nach einem Restore das dokumentierte Purge-, Löschlauf- und Zugangsrotationsverfahren vollständig durchführen.
- [ ] In den ersten 24 Stunden Fehlerprotokolle und den ersten automatischen Löschlauf kontrollieren, ohne Nutzerdaten in ein externes Monitoring zu übertragen.

## Aktuelle Go-Live-Blocker

1. Produktionseinrichtung und echter lima-city-Deployment-Check.
2. Echter Domain-, HTTPS-, Cookie-, Log- und End-to-End-Test.
3. Ablage des konkreten lima-city-Vertragsnachweises.

Die externe schweizerische Rechtsprüfung ist gemäss Betreiberentscheid nach dem Go-Live vorgesehen und deshalb kein technischer Vorab-Blocker. Der sichtbare Hinweis darf bis zu ihrem Abschluss nicht entfernt werden.
