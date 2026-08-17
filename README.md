# pulse

**Privacy-friendly group coordination without accounts or participant lists.**

`pulse` is a module of [sats4you.ch](https://sats4you.ch/). It helps a group answer a simple question: **Who is joining the next event?** Participants use a shared access link, RSVP without an account and see only the aggregated number of active commitments.

The first closed pilot is intended for **Bern Monthly Bitcoin Meetup**.

## Current status

This repository contains the source operated for the first pilot at [sats4you.ch/pulse](https://sats4you.ch/pulse). The production installation passed the documented release checks on 17 August 2026.

- closed pilot implementation started on 15 August 2026;
- participant access, upcoming-event view, anonymous RSVP and withdrawal are implemented;
- secret administration access and event creation, editing, scheduling, publication, cancellation, duplication and RSVP closure are implemented;
- recovery-code rotation, participant-link rotation and one-time round provisioning are implemented;
- the four-language technical privacy explanation, primary-data retention job and anonymous bundled administrator notifications are implemented;
- automated checks currently cover the core HTTP, authorisation, event, RSVP, notification, CSRF, translation and retention rules;
- not independently audited;
- deployed and tested on the production domain;
- not yet open for other groups;
- no real access links, secrets or participant data belong in this repository;
- the closed pilot is designed for lima-city's published 90-day backup and infrastructure-log limits;
- the production-domain, log and clean-data checks are complete; the data-processing agreement with lima-city is complete.

The public source must match the version operated on sats4you.ch. Real access links, secrets, production configuration and participant data are never part of the repository.

## Product principles

- no participant accounts;
- no names, email addresses, phone numbers or public attendee lists;
- only the exact aggregated number of active RSVPs is visible to people holding the participant link;
- separate participant, administration and recovery secrets;
- event-specific RSVP secrets that do not create a cross-event participant profile;
- clear limits instead of absolute anonymity or security promises;
- minimal data, documented deletion and no advertising analytics;
- administrator email notifications contain only bundled RSVP changes and the current aggregate count, never participant data or access links;
- no calendar integration in the pilot; it may be reconsidered if users request it;
- complete user interface in Deutsch, Français, Italiano and Rumantsch Grischun, with full language names in the selector.

## Local verification

```text
composer install
composer test
```

After applying the database migration, the first round is provisioned once from the command line. The command prints the participant link, administration link and recovery code only once:

```text
php bin/provision-round.php bern-bitcoin "Bern Monthly Bitcoin Meetup"
```

The production web root must point to `public/`. Database configuration and cryptographic keys are supplied through environment variables or a Git-ignored runtime file outside that public directory; no real secret belongs in the repository. See [Operations](docs/OPERATIONS.md) and the [lima-city deployment guide](docs/DEPLOYMENT_LIMA_CITY.md).

## Documentation

The reviewed product and technical documents are still maintained in the private preparation workspace. Selected, implementation-matched versions will be added under `docs/` before publication.

Repository policies:

- [Security policy](SECURITY.md)
- [Trademark policy](TRADEMARKS.md)
- [Contributing](CONTRIBUTING.md)
- [Copyright and licensing notice](NOTICE)
- [GNU AGPL version 3](LICENSE)
- [Architecture](docs/ARCHITECTURE.md)
- [Operations](docs/OPERATIONS.md)
- [Self-hosting](docs/SELF_HOSTING.md)

## Licence

The original pulse application code, technical scripts, migrations and tests will be licensed under the **GNU Affero General Public License version 3 only (`AGPL-3.0-only`)**.

The names **sats4you.ch** and **pulse**, their logos and designated brand assets are not licensed under the software licence. See [TRADEMARKS.md](TRADEMARKS.md).

Copyright © 2026 Andreas Kuoni.
