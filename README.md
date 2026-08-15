# pulse

**Privacy-friendly group coordination without accounts or participant lists.**

`pulse` is a planned module of [sats4you.ch](https://sats4you.ch/). It helps a group answer a simple question: **Who is joining the next event?** Participants use a shared access link, RSVP without an account and see only the aggregated number of active commitments.

The first closed pilot is intended for **Bern Monthly Bitcoin Meetup**.

## Current status

This repository is being prepared privately. It currently contains project governance and security documentation, but **no production-ready application**.

- closed pilot in preparation;
- not independently audited;
- not yet deployed;
- not yet open for other groups;
- no real access links, secrets or participant data belong in this repository.

The repository will only be made public after the implementation, privacy information, self-hosting guide and release checks match the version actually operated on sats4you.ch.

## Product principles

- no participant accounts;
- no names, email addresses, phone numbers or public attendee lists;
- only the exact aggregated number of active RSVPs is visible to people holding the participant link;
- separate participant, administration and recovery secrets;
- event-specific RSVP secrets that do not create a cross-event participant profile;
- clear limits instead of absolute anonymity or security promises;
- minimal data, documented deletion and no advertising analytics;
- server-generated calendar files without calendar-account access.

## Documentation

The reviewed product and technical documents are still maintained in the private preparation workspace. Selected, implementation-matched versions will be added under `docs/` before publication.

Repository policies:

- [Security policy](SECURITY.md)
- [Trademark policy](TRADEMARKS.md)
- [Contributing](CONTRIBUTING.md)
- [Copyright and licensing notice](NOTICE)
- [GNU AGPL version 3](LICENSE)

## Licence

The original pulse application code, technical scripts, migrations and tests will be licensed under the **GNU Affero General Public License version 3 only (`AGPL-3.0-only`)**.

The names **sats4you.ch** and **pulse**, their logos and designated brand assets are not licensed under the software licence. See [TRADEMARKS.md](TRADEMARKS.md).

Copyright © 2026 Andreas Kuoni.
