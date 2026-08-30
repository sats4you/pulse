# Erste Sats – Datenschutz- und Rechtstextgrundlage

Stand: 30. August 2026
Öffentliche Arbeitsfassung: `2026-08-30-v1`

## Status und Zweck

Dieses Dokument gleicht die sichtbaren Teilnahmebedingungen und die Datenschutzerklärung von **Erste Sats** mit der aktuellen Implementierung ab. Es ist die interne Grundlage für den Go-Live-Readiness-Check und keine externe Rechtsprüfung.

Auf den öffentlichen Rechtstexten steht deshalb sichtbar **„Schweizerische Rechtsprüfung ausstehend“**. Die externe schweizerische Rechtsprüfung ist nach dem Go-Live vorgesehen; der Hinweis bleibt bis zu ihrem Abschluss sichtbar. Noch offene technische und sprachliche Release-Punkte sind unten getrennt aufgeführt.

## Verantwortlicher

Der Betreiber hat am 30. August 2026 folgende öffentliche Bezeichnung bestätigt:

- Andreas Kuoni, Betreiber von sats4you.ch in der Schweiz;
- allgemeiner Kontakt und Datenschutzanfragen: `hallo@sats4you.ch`;
- vertrauliche Sicherheitsmeldungen: `security@sats4you.ch`.

Die Sicherheitsadresse ersetzt den allgemeinen Datenschutzkontakt nicht. Ob für die endgültige Veröffentlichung zusätzlich eine zustellfähige Anschrift sichtbar anzugeben ist, bleibt Teil der späteren externen Rechtsprüfung.

## Datenfluss des Lernreise-MVP

| Schritt | Daten | Zweck und technische Grenze |
| --- | --- | --- |
| Einladung erstellen | Sprache, Absenderbezeichnung, Anrede, ganzzahliger Sats-Betrag, CHF-Referenzkurs mit Zeitpunkt, Altersbestätigung, akzeptierte Bedingungsfassung und Zeitpunkt | Einzeleinladung erstellen und das private Versprechen verständlich darstellen. Keine Empfängeradresse und keine Versandhistorie. |
| Geheime Zugänge | HMAC-Prüfwerte und Zugriffsversionen; lesbare Geheimnisse nur bei der Ausgabe im Browser | Einladungs-, Verwaltungs-, Wiederherstellungs- und Fortsetzungszugänge ohne Benutzerkonto absichern. Geheimnisse gelangen als URL-Fragment nicht in normale Serveranfragen. |
| Technisch notwendige Sitzungen | Signierte, rollen- und einladungsgebundene Cookies | Formular, Wiederherstellung, Teilnehmer-, Verwaltungs- und Betreiberzugang schützen. Keine Werbung und kein Webtracking. |
| Teilnahme | eigene Altersbestätigung, Bedingungsfassung mit Zeitpunkt, Annahme- und Abschlussfrist | Teilnahme zulassen und rollenbezogene Zustimmung dokumentieren. Kein Geburtsdatum, kein Ausweis und keine Identitätsprüfung. |
| Lernreise | Abschlusszeitpunkte der vier Etappen, Inhaltsversion, Status der freiwilligen Erkenntnisfrage | Fortschritt fortsetzen und feste Abschlussregel durchsetzen. Keine Gesamtnote und kein persönliches Wissensprofil. |
| Anonyme Erkenntnisse | vorab definierte Summenzähler nach Ereignis, Inhaltsversion, Sprache und gegebenenfalls Antwort; ein temporärer Beitragsmarker in der Einladung | Doppelzählung verhindern und nur aggregierte Produkt- beziehungsweise Wissenskennzahlen auswerten. Keine Einzelantworten, IP-Adressen, User-Agents, Freitexte oder Wallet-Daten im Dashboard. |
| Wallet-Weg | Wallet of Satoshi, Phoenix oder andere kompatible Lightning-Wallet | Passende, neutrale Einrichtungshilfe anzeigen. Keine Wallet-Zugangsdaten. |
| Sats-Anforderung | vollständige BOLT11-Lightning-Rechnung, exakter Sats-Betrag, Ablauf- und Eingabezeit | Eine direkte externe Zahlung koordinieren. Die Plattform bezahlt, verwahrt, verifiziert oder garantiert nichts. |
| Übergabe | freiwillige Versand-, Empfangs- und Noch-nicht-erhalten-Zeitpunkte | Beiden Rollen einen neutralen Status zeigen. Keine Konfliktentscheidung und kein Zahlungsnachweis. |
| CHF-Orientierung | öffentlicher XBT/CHF-Kurs von Kraken im serverseitigen Cache | Einen unverbindlichen Gegenwert berechnen. An Kraken gehen keine Einladungs- oder Teilnehmerdaten. |
| Externe Links und E-Mail | erst nach bewusster Nutzeraktion | Wallet- und Quellenanbieter bearbeiten ihre eigenen Daten ausserhalb der Anwendung. Die sats4you.ch-Adressen werden bei lima-city betrieben; beim Versand bearbeiten lima-city und der Mailanbieter des Absenders den Inhalt. `Referrer-Policy: no-referrer` verhindert die Übermittlung des aufrufenden Seitenlinks an externe Ziele. |
| Hosting | Webanwendung, Datenbank, technisch notwendige Zugriffs-, Verbindungs- und Sicherheitsdaten | Betrieb bei lima-city beziehungsweise TrafficPlex GmbH in Frankfurt am Main. Die Anwendung führt daneben keine eigene IP-Analyse oder Werbeprofile. |

## Nicht verlangte oder nicht gespeicherte Daten

Erste Sats verlangt und speichert insbesondere keine:

- Seed Phrases, Wiederherstellungswörter, privaten oder erweiterten privaten Schlüssel, Wallet-PINs oder Passwörter;
- Geburtsdaten, Ausweiskopien oder verifizierten Identitäten;
- E-Mail-Adressen der eingeladenen Personen, Empfängerlisten oder Versandhistorien;
- IP-Adressen, User-Agents oder Gerätekennungen für Analytics;
- Werbeprofile, öffentlichen Teilnehmerprofile oder produktübergreifenden Kennungen.

Amtliche Namen sind nicht erforderlich. Absenderbezeichnung und Anrede dürfen auch Spitznamen oder vertraute Rollenbezeichnungen sein und werden nicht als verifiziert dargestellt.

## Aufbewahrung und Löschung

| Datengruppe | Aktive Datenbank | Technische Sicherungskopien beziehungsweise Infrastruktur |
| --- | --- | --- |
| Widerrufene Zugangsdaten | sofort unbrauchbar | Prüfwerte können bis zur fachlichen Gesamtlöschung beziehungsweise in Backups verbleiben, erlauben aber keinen normalen Zugang mehr. |
| BOLT11-Lightning-Rechnung | sofort nach bestätigtem Empfang, sonst am Ende der 14-tägigen Übergabephase löschen | Kann nach Primärlöschung noch bis zu 90 weitere Tage in einer verschlüsselten Sicherungskopie enthalten sein. |
| Einladung, Bezeichnungen, Zustimmung und Fortschritt | grundsätzlich 30 Tage nach Ablauf, Teilnehmerabbruch oder Schliessung löschen | Kann nach Primärlöschung noch bis zu 90 weitere Tage in einer verschlüsselten Sicherungskopie enthalten sein. |
| Irreversible Summenzähler | keine einladungsbezogene Löschfrist | Bleiben ohne Einladungs-, Teilnehmer- oder Wallet-Bezug bestehen. |
| Technisch notwendige Hostingprotokolle | durch den Hoster nach dessen verbindlicher Konfiguration | Gemäss aktuell dokumentierter Anbietergrenze bis zu 90 Tage; vor Release nochmals anhand der Produktion prüfen. |
| lima-city-Sicherungen | nicht Teil der aktiven Anwendung | Tägliche verschlüsselte Sicherungen von Webspace und Datenbank, aktuell bis zu 90 Tage; nur für technische Wiederherstellung verwenden. |

Der produktive Löschlauf muss regelmässig und überwacht ausgeführt werden. Ein erfolgreicher Datenbank-Restore darf abgelaufene Fachdaten nicht dauerhaft wieder aktivieren; unmittelbar danach ist der Löschlauf erneut auszuführen und sind geheime Zugänge gemäss Wiederherstellungsverfahren zu prüfen.

## Empfänger und Auslandbekanntgaben

- **lima-city / TrafficPlex GmbH, Deutschland:** Hosting und technisch notwendige Infrastruktur. Deutschland ist nach der massgeblichen Schweizer Staatenliste als Staat mit angemessenem Datenschutz einzuordnen. Der konkrete Produktionsvertrag und die anwendbare Auftragsbearbeitungsvereinbarung sind vor Veröffentlichung zu bestätigen.
- **Kraken:** Der Server ruft nur einen öffentlichen XBT/CHF-Kurs ab. Es werden keine Einladungs-, Teilnehmer- oder Wallet-Daten übermittelt.
- **Verlinkte Wallet- und Informationsanbieter:** Daten fliessen erst, wenn die Person einen externen Link öffnet. Danach gelten die Erklärungen und Bedingungen des jeweiligen Dritten.
- **E-Mail-Anbieter:** Die Adressen `hallo@sats4you.ch` und `security@sats4you.ch` werden bei lima-city betrieben. Beim freiwilligen Kontakt per lokalem Mailprogramm bearbeiten lima-city und der Anbieter des Absenders den Nachrichteninhalt. Die Anwendung erhebt weder Empfängeradresse noch Versandhistorie.

Es gibt im MVP kein Profiling und keine automatisierte Entscheidung mit rechtlicher oder ähnlich erheblicher Wirkung.

## Betroffenenrechte und praktische Bearbeitung

Anfragen auf Auskunft, Berichtigung, Löschung oder Widerspruch gehen an `hallo@sats4you.ch`. Da bewusst keine Identitätskonten und keine verifizierten Namen geführt werden, darf der Betreiber Daten nicht allein aufgrund einer behaupteten Identität offenlegen. Vor einer Auskunft oder Änderung ist die Berechtigung zum betroffenen geheimen Zugang angemessen zu prüfen. Wallet-Geheimnisse dürfen dafür niemals verlangt werden.

Kann eine Person Einladung oder Zugang nicht ausreichend bestimmen, muss dies transparent erklärt werden. Gesetzliche Rechte werden dadurch nicht ausgeschlossen.

## Versionierung und Zustimmung

- Einladende akzeptieren ab dieser Fassung `inviter-2026-08-30-v1`.
- Teilnehmer akzeptieren ab dieser Fassung `participant-2026-08-30-v1`.
- Die sichtbare Datenschutz- und Rechtstextfassung lautet `2026-08-30-v1`, gültig ab 30. August 2026.
- Gespeichert werden Rolle, Fassung und Zeitpunkt innerhalb der betreffenden Einladung beziehungsweise Teilnahme.
- Wesentliche neue Bedingungen gelten nicht rückwirkend. Wenn eine neue Zustimmung erforderlich ist, muss sie getrennt eingeholt werden.

## Bestätigte Entscheidungen vom 30. August 2026

- Öffentliche Betreiberbezeichnung: **Andreas Kuoni, Betreiber von sats4you.ch in der Schweiz**.
- Mailanbieter für `hallo@sats4you.ch` und `security@sats4you.ch`: **lima-city**.
- Deutsch und Französisch: durch den Betreiber inhaltlich und sprachlich für den aktuellen Stand freigegeben.
- Italienisch und Rumantsch Grischun: werden in der ersten öffentlichen Beta sichtbar als Community-Review-Fassungen angeboten; die Suche nach einer rumantschen Prüfperson läuft und geeignete Nutzerkorrekturen werden redaktionell geprüft.
- Externe Prüfung durch eine schweizerische Rechtsfachperson: nach dem Go-Live vorgesehen. Der sichtbare Hinweis bleibt bis zur abgeschlossenen Prüfung bestehen.

## Noch offene Release-Punkte

1. Konkreten lima-city-Produktionsvertrag, die dazugehörige Fassung einer allfälligen Auftragsbearbeitungsvereinbarung, Serverstandort, Protokolldaten und effektive Aufbewahrungsfristen als Deployment-Nachweis ablegen. Der Betreiber hat lima-city als Produktions- und Mailanbieter bestätigt; eine konkrete Vertragsfassung wurde im Repository noch nicht dokumentiert.
2. Produktiven Löschlauf und Überwachung testen. Der Code schreibt nun einen nicht personenbezogenen Erfolgszeitpunkt und der Deployment-Check lehnt einen fehlenden oder mehr als 26 Stunden alten Lauf ab; der reale lima-city-Cronjob ist noch einzurichten und auf der Produktionsumgebung zu prüfen.
3. Die weitergehende Verifikation von Italienisch und Rumantsch Grischun nachholen. Sie blockiert die erste öffentliche Beta nicht; die transparente Community-Review-Kennzeichnung bleibt bis zu einer tatsächlich weitergehenden Prüfung bestehen.
4. Interne Datenschutz- und Rechtstextprüfung im abschliessenden Go-Live-Readiness-Check bestätigen. Die externe schweizerische Rechtsprüfung wird anschliessend priorisiert nachgeholt.

Die Restore-Strategie ist entschieden und technisch vorbereitet: Im Wartungsmodus werden nach einem Datenbank-Restore alle wiederhergestellten einladungsbezogenen Erste-Sats-Daten gelöscht; irreversible anonyme Summenzähler bleiben erhalten. Damit können widerrufene Geheimlinks aus einem Backup nicht wieder aktiv werden. Der konkrete Ablauf steht in `DEPLOYMENT_LIMA_CITY.md` und `OPERATIONS.md`.
