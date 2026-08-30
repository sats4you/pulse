# Erste Sats – Bauetappe 1

Stand: 23. August 2026

## Ergebnis

Diese abgegrenzte Bauetappe überführt den freigegebenen mobilen Prototyp erstmals in die gemeinsame serverseitige PHP-/Slim-/MySQL-Plattform. Sie ist noch keine veröffentlichungsfähige Gesamtanwendung.

Umgesetzt sind:

- neues, fachlich getrenntes Modul `FirstSats` im modularen Monolithen;
- MySQL-Migration für genau eine kontolose Einzeleinladung;
- Betragsgrenze `0` bis `100'000` Sats und zusätzlicher serverseitig erzwungener Hinweis ab `50'000` Sats;
- zwei frei wählbare persönliche Bezeichnungen mit serverseitiger Längen-, Zeilenumbruch- und Linkprüfung;
- kein zusätzlicher persönlicher Freitext auf der Erstellungsseite; neue Einladungen speichern ausschliesslich die beiden Bezeichnungen und erzeugen den editierbaren Versandtext erst lokal auf der Ergebnisseite;
- getrennte Altersbestätigung und Zustimmung zur versionierten Bedingungsfassung;
- getrennte hochentropische Einladungs-, Verwaltungs- und Wiederherstellungsgeheimnisse; in der Datenhaltung liegen nur HMAC-Digests;
- rollen- und versionsgebundene signierte Sitzungen für Einladungs- und Verwaltungszugang;
- Geheimnisse im URL-Fragment, damit sie nicht an den Server, in normale Zugriffslogs oder als Referrer übertragen werden;
- einmalige Ausgabe von Verwaltungslink und Wiederherstellungscode;
- genau eine Hauptaktion „Einladung teilen / versenden“, mit lokaler Teilen-Funktion beziehungsweise Kopierfallback und ohne Empfängererhebung;
- klar als **„Nachricht an … versenden“** bezeichneter, lokal editierbarer Versandtext in der Ich-Perspektive des Einladenden mit eigenständigem `0`-Sats-Wortlaut;
- vollständige sats4you.ch-Wortmarke und der bestätigte mobile Einlader-Ablauf als visuelle Umsetzungsreferenz;
- mobile Ansichten für Erstellung, Ergebnis, erhaltene Einladung, einfache Verwaltung, FAQ, Teilnahmebedingungen und Datenschutz;
- sichere Annahme durch den Teilnehmer mit eigener Volljährigkeits- und Bedingungsbestätigung, rotierter Zugangssitzung und bewusst erzeugbarem Fortsetzungslink;
- erste der vier verbindlichen Lernetappen **„Bitcoin gibt es auch in klein“** mit dynamischem Einladungsbetrag, stabil variierter Antwortposition, hilfreicher Rückmeldung und beliebig vielen Versuchen ohne Nachteil;
- zweite verbindliche Lernetappe **„Die Wallet ist dein Zugang“** mit klarer Abgrenzung zwischen Wallet und Bitcoin, neutraler Einordnung von Selbstverwahrung und Anbieterbeteiligung sowie einem zwingenden Sicherheitscheck zu Wiederherstellungsdaten;
- dritte verbindliche Lernetappe **„So kommen die Sats zu dir“** mit einer anfängergerechten Lightning-Einordnung, dem exakten dynamischen Einladungsbetrag und einem eigenen neutralen Wortlaut für 0-Sats-Lernreisen;
- vierte verbindliche Lernetappe **„Möglichkeiten haben auch Grenzen“** mit globaler Übertragbarkeit, möglicher Wertspeicher-Perspektive, Kursschwankungen, Eigenverantwortung und einer sachlichen Abgrenzung zu TWINT;
- gespeicherter Gesamtabschluss nach genau vier richtigen Wissenschecks; 0-Sats-Lernreisen enden ohne Wallet- oder Zahlungsweg;
- Wallet-Wahl nach positivem Sats-Versprechen mit **Wallet of Satoshi**, **Phoenix** und einer freien kompatiblen Lightning-Wallet; die Auswahl bleibt bis vor der Rechnung änderbar;
- anbieterbezogene Einrichtungshilfe mit offiziellen Anbieterlinks, dynamischem Sats-Betrag, korrekter Unterscheidung der Wallet-of-Satoshi-Verwahrungsmodi, Phoenix-Gebührenhinweis und klarer BOLT11-/LNURL-Abgrenzung;
- Teilnehmerformular für die BOLT11-Zahlungsanforderung mit Prüfung von Mainnet-Präfix, exaktem Sats-Betrag, Pflichtfeldern, Bech32-Prüfsumme und mindestens zehn Minuten Restlaufzeit;
- getrennte, ersetzbare Speicherung der Zahlungsanforderung und Übergabe auf der geheimen Verwaltungsseite; sats4you.ch führt weiterhin keine Zahlung aus;
- sichtbare Sicherheitsgrenze für persönliche Hilfe: eigenes Gerät selbst bedienen und Seed Phrase, Wiederherstellungswörter, private Schlüssel, PIN und Passwörter weder zeigen noch eingeben;
- diskreter Bereich **„Quellen & Einordnung“** mit Inhaltsversion und fachlichem Prüfdatum;
- freiwillige Erkenntnisfrage nach der ersten Etappe; Antworten werden serverseitig unmittelbar nur in irreversible Zähler nach Inhaltsversion, Sprache und Antwort überführt und nie einer Einladung zugeordnet;
- Internationalisierung in Deutsch, Französisch, Italienisch und Rumantsch Grischun; IT und RM sind sichtbar als Community-Review-Fassungen gekennzeichnet;
- bestehende `pulse`-Routen und -Tests bleiben unverändert funktionsfähig.

Die CHF-Schätzung ist in dieser Bauetappe bewusst noch nicht mit Kraken verbunden. Wo ein Sats-Betrag sichtbar ist, wird der CHF-Wert deshalb korrekt als vorübergehend nicht verfügbar bezeichnet und niemals erfunden.

## Verifikation

- vollständige PHPUnit-Suite: `124` Tests, `2'507` Assertions, alle erfolgreich;
- zusätzliche negative Tests für falsche Beträge, unzulässige Bezeichnungen, fehlende Alters-/Bedingungsbestätigung, hohen Betrag ohne Zusatzbestätigung, Cross-Site-Erstellung und vertauschte Rollen-Geheimnisse;
- alle vier neuen Übersetzungskataloge sind valides JSON, vollständig und schlüsselgleich;
- PHP-Syntaxprüfung für neue Dateien und geänderte Einstiegspunkte erfolgreich;
- mobiler Browser-End-to-End-Test bei 390 × 844 Pixeln: Formular, Erstellen, personalisierte Ausgabe, Fragment-Austausch, sichere Sitzung, falsche und richtige Antwort, Etappenabschluss sowie anonyme Erkenntnisfrage erfolgreich;
- keine neuen Browserfehler nach der Korrektur des lokalen Test-Routers beobachtet.

## Bewusst nicht Teil dieser Bauetappe

- kryptografische BOLT11-Signaturprüfung, freiwillige Versand-/Empfangsbestätigungen und der optionale Abschluss „Und jetzt?“;
- Teilnahme-Reset;
- nutzbarer Wiederherstellungsablauf und Rotation von Verwaltungslink/Wiederherstellungscode;
- Fristverlängerung, Reaktivierung, Widerruf, Abbruch und Löschlauf;
- Live-Kursabruf und CHF-Cache;
- automatische Prüfung der Erreichbarkeit oder Bezahlbarkeit einer Lightning-Rechnung; dies kann erst die sendende Wallet feststellen;
- Betreiber-Dashboard und Darstellung der bereits erhobenen anonymen Aggregatzähler;
- E-Mail-Versand, Benutzerkonten, Gruppen, Affiliate- oder Trinkgeldfunktionen;
- Deployment oder öffentliche Veröffentlichung.

## Vor der nächsten Bauetappe zu entscheiden

1. Die Wiederherstellung sollte als nächster sicherheitskritischer Teil fertiggestellt werden, bevor reale Einladungen erzeugt werden.
2. Die Repository- und Lizenzgrenze für `Erste Sats` muss vor einer Veröffentlichung ausdrücklich festgelegt werden. Die AGPL-Entscheidung ist bisher nur für `pulse` verbindlich; aus der gemeinsamen technischen Ablage darf keine stillschweigende Produktentscheidung abgeleitet werden.
3. Dieser Punkt wurde am 30. August 2026 technisch weiterbearbeitet: Die sichtbaren Rechtstexte sind nun versionierte, viersprachige Arbeitsfassungen und mit Datenflüssen, Fristen und Betroffenenrechten abgeglichen. Der aktuelle Stand und die noch erforderlichen Release-Bestätigungen stehen in [`ERSTE_SATS_DATENSCHUTZ.md`](ERSTE_SATS_DATENSCHUTZ.md). Die externe schweizerische Rechtsprüfung bleibt sichtbar ausstehend.
4. Die Kraken-Anbindung und das definierte Ausfallverhalten bilden eine eigene kleine Bauetappe, damit kein ungesicherter oder erfundener CHF-Wert in Einladungen gespeichert wird.
