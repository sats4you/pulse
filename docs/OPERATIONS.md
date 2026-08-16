# Pulse operations

Status: local implementation guide, 15 August 2026. This is not a production approval.

## Runtime

- PHP 8.2 or newer with PDO MySQL, JSON, Intl and Mbstring;
- MySQL 8;
- Apache with the document root set to `public/`, or an equivalent web server that forwards non-file routes to `public/index.php`;
- Composer for dependency installation outside the public document root.

Install dependencies with `composer install --no-dev --classmap-authoritative` and apply `database/migrations/001_create_pulse_tables.sql` to a dedicated database and least-privilege database user.

## Environment

The required variables are documented in `.env.example`. `APP_HMAC_KEY` must contain at least 32 unpredictable bytes and must not be stored in the repository, database, web root, support messages or backups together with the database. Changing it invalidates stored access and RSVP digests.

The web server must use HTTPS in production. The application then marks access and RSVP cookies as `Secure`, `HttpOnly` and `SameSite=Strict`.

## Einmalige Einrichtung der Pilotgruppe

Nach der Migration wird die Runde genau einmal über die Kommandozeile angelegt:

```text
php bin/provision-round.php bern-bitcoin "Bern Monthly Bitcoin Meetup"
```

Das Kommando erzeugt unabhängig voneinander Teilnehmer-, Verwaltungs- und Wiederherstellungsgeheimnis. Es speichert nur deren HMAC-Prüfwerte und zeigt die lesbaren Links beziehungsweise den Wiederherstellungscode genau einmal im Terminal an. Teilnehmerlink und Verwaltungslink sind getrennt zu speichern; der Wiederherstellungscode ist zusätzlich offline und getrennt vom Verwaltungslink aufzubewahren. Die Ausgabe darf weder in ein Ticket noch in ein Log, eine Bildschirmaufzeichnung oder das Repository kopiert werden.

Existiert der Slug bereits, verhindert der eindeutige Datenbankindex eine zweite Einrichtung. Ein verlorener Teilnehmerlink kann später aus der Verwaltung ersetzt werden. Nur der aktuelle Wiederherstellungscode kann einen verlorenen Verwaltungslink ersetzen. Bei erfolgreicher Wiederherstellung werden Verwaltungslink und Wiederherstellungscode atomar ersetzt und alle früheren Verwaltungssitzungen ungültig. Sind Verwaltungslink und Wiederherstellungscode verloren, gibt es im Pilot keine Support-Wiederherstellung.

## Daily primary-data deletion

Run the following command at least once per day with the same database environment as the web application:

```text
php bin/pulse-retention.php
```

The command is idempotent. It outputs only success state and aggregate deletion counts. It must not be extended to output event identifiers, access links, RSVP secrets or other sensitive values.

The job deletes due active RSVP records and due events from the primary database. Foreign-key cascading removes any remaining RSVP records belonging to a deleted event.

## Hosting gate

Do not operate the closed pilot with real group data until all of the following have been verified and documented:

- backup copies are removed no later than 30 days after primary deletion;
- access and security log fields and retention periods match the privacy explanation;
- HTTPS, security headers and cookie attributes are correct in the real environment;
- the deletion command runs reliably and failures are noticed without logging sensitive data;
- the legal controller and contact details have been added to the final privacy notice.

The currently known 90-day standard backup retention at lima-city conflicts with the approved maximum. The application must not be declared production-ready while that conflict remains unresolved.
