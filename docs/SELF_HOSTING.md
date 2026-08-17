# Self-hosting pulse

This guide describes a minimal self-hosted installation of `pulse`. A deployment operated by a third party must use its own product name and logo as described in [TRADEMARKS.md](../TRADEMARKS.md).

## Requirements

- PHP 8.2 or newer with `json`, `intl`, `mbstring`, `pdo` and `pdo_mysql`;
- MySQL 8;
- Composer 2 for installation;
- a web server whose document root points only to `public/`;
- HTTPS for every real participant or administration access.

Node.js is not required in production.

## Installation

1. Clone the repository and install locked production dependencies:

   ```text
   composer install --no-dev --classmap-authoritative
   ```

2. Create a dedicated database and a least-privilege database user. Apply:

   ```text
   database/migrations/001_create_pulse_tables.sql
   ```

3. Copy `config/runtime.example.php` to `config/runtime.php`. Keep the real file outside the public document root and outside Git. Set the database values, the HTTPS `APP_BASE_URL` and an unpredictable `APP_HMAC_KEY` of at least 32 characters.

4. Point the web server document root to `public/`. Requests for non-existing files must be forwarded to `public/index.php`. Do not expose the repository root, `config/`, `vendor/`, `database/` or `bin/` directly.

5. Verify the installation without printing secrets:

   ```text
   php bin/deployment-check.php
   ```

6. Provision one group and immediately store the one-time output securely:

   ```text
   php bin/provision-round.php group-slug "Group name"
   ```

   Participant link, administration link and recovery code are independent secrets. Never place them in logs, tickets or the repository.

7. Run the primary-data deletion at least daily:

   ```text
   php bin/pulse-retention.php
   ```

   Use a scheduler that prevents overlapping runs. The command is idempotent and reports only aggregate deletion counts.

## Operational responsibilities

Before inviting participants, the operator must adapt the visible privacy information to the actual controller, host, logs, backup periods and contact channel. The operator is also responsible for HTTPS, security headers, dependency updates, database backups and applicable data-protection agreements.

Never restore a backup while the application is publicly available. After a restore, rotate participant, administration and recovery credentials before reopening the service so that previously revoked access cannot become valid again. See [OPERATIONS.md](OPERATIONS.md) for the full operating model.

Run the automated test suite before every release:

```text
composer test
```
