# Pulse operations

Status: local implementation guide, 15 August 2026. This is not a production approval.

## Runtime

- PHP 8.2 or newer with PDO MySQL, JSON, Intl and Mbstring;
- MySQL 8;
- Apache with the document root set to `public/`, or an equivalent web server that forwards non-file routes to `public/index.php`;
- Composer for dependency installation outside the public document root.

Install dependencies with `composer install --no-dev --classmap-authoritative` and apply all numbered SQL files in `database/migrations/` in order to a dedicated database and least-privilege database user. The lima-city-specific sequence and target directories are documented in [DEPLOYMENT_LIMA_CITY.md](DEPLOYMENT_LIMA_CITY.md).

## Environment

The required values are documented in `.env.example` and `config/runtime.example.php`. On lima-city, the real values are stored in `config/runtime.php` outside the `public/` web root; operating-system environment variables override the file when available. `APP_HMAC_KEY` must contain at least 32 unpredictable characters and must not be stored in the repository, database, public web root or support messages. Changing it invalidates stored access and RSVP digests.

lima-city's encrypted webspace backups can include the non-public runtime file for up to 90 days. This accepted pilot boundary is stated in the privacy explanation. A restore therefore always requires maintenance mode followed by rotation of participant, administration and recovery credentials before reopening.

The web server must use HTTPS in production. The application then marks access and RSVP cookies as `Secure`, `HttpOnly` and `SameSite=Strict`.

`NOTIFICATION_RECIPIENT`, `NOTIFICATION_FROM` and `NOTIFICATION_LOCALE` configure the operator notification. They must only exist in the non-public runtime configuration. The sender mailbox must be authorised for the hosting mail function; the recipient may be hosted by another provider. The visible privacy explanation must name the actual mail-provider data flow.

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

On lima-city this is configured as a Shell-Cronjob with a dedicated `flock` lock file, not as a public URL endpoint.

The command is idempotent. It outputs only success state and aggregate deletion counts. It must not be extended to output event identifiers, access links, RSVP secrets or other sensitive values.

The job deletes due active RSVP records and due events from the primary database. Foreign-key cascading removes any remaining RSVP records belonging to a deleted event.

## Bundled administrator notification

Run the following Shell-Cronjob every five minutes:

```text
php bin/pulse-notifications.php
```

Each actual join or withdrawal creates an anonymous event-specific queue row. The dispatcher waits until an event has had no further change for five minutes, then sends one email containing only the event, bundled join/withdraw counts and current aggregate count. It deletes sent rows immediately. Failed rows are retried and expire after seven days. Command output contains only aggregate delivery counters; never extend it with addresses, event data, access links or RSVP secrets.

## Hosting gate

Do not operate the closed pilot with real group data until all of the following have been verified and documented:

- daily encrypted lima-city backups and provider access/security data are described visibly with their published maximum retention of 90 days;
- backups are used only for technical recovery, never for analytics or normal application access;
- access and security log fields and retention periods match the privacy explanation;
- HTTPS, security headers and cookie attributes are correct in the real environment;
- the deletion command runs reliably and failures are noticed without logging sensitive data;
- the legal controller and contact details have been added to the final privacy notice.

The 90-day lima-city retention is an accepted and documented pilot boundary. On 17 August 2026, lima-city support confirmed that Shared Webhosting backups cannot be disabled or shortened and are no longer recoverable from day 91. Access logs and their retention cannot be disabled or shortened either. Webhosting and MySQL operate in Frankfurt within the EU/EEA. lima-city does not retain the output of Shell cronjobs. The data-processing agreement for the hosting account has been completed.

## Restore procedure

Never restore a database backup while the public application is available. Put pulse into maintenance mode, restore the selected backup, then rotate the participant, administration and recovery credentials before reopening access. This prevents participant or administration links revoked after the backup date from remaining valid again. Document the restore, credential rotation and reopening without recording any raw secret.
