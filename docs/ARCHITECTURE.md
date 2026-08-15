# Pulse architecture

Status: tested vertical pilot slice, 15 August 2026.

Pulse is a server-rendered modular PHP monolith. The closed pilot contains one preconfigured coordination round, multiple events and anonymous event-specific RSVPs. It has no participant accounts, names, attendee list, analytics or calendar integration.

## Module boundaries

- `app/Platform`: neutral infrastructure such as secret handling, database access and translation.
- `app/Pulse/Event`: publication, visibility and event-time rules.
- `app/Pulse/Attendance`: idempotent RSVP and withdrawal operations.
- `app/Pulse/Administration`: event administration and credential rotation.
- `app/Pulse/Privacy`: user-facing data-flow and privacy information.
- `app/Pulse/Retention`: deletion deadlines and idempotent deletion jobs.

The implemented HTTP slice contains a public pulse entry, fragment-based participant and administration access, role-scoped signed sessions, the public event list, anonymous event-specific RSVPs, administration forms, a public technical privacy explanation and the primary-data deletion command. Recovery-code rotation and participant-link rotation remain implementation work.

The learning journey is not part of this repository. Pulse data, secrets, sessions and identifiers must never be reused to recognise people in another sats4you.ch module.

## Security model

Participant, administration, recovery and event-specific RSVP secrets are separate random values with at least 256 bits of entropy. Raw secrets are exchanged from URL fragments and are never stored in the database. The database contains binary HMAC-SHA-256 digests. The HMAC key remains outside the database and repository.

Authorisation, event visibility, RSVP deadlines, withdrawal deadlines and deletion deadlines are server-side rules. UI visibility is not an authorisation mechanism.

Administration sessions expire after at most twelve hours. State-changing administration requests require a valid administration session, an exact same-origin signal and a CSRF token derived for that session and round. Participant and administration cookies use separate names and path scopes.

## Hosting gate

Implementation may proceed independently of the hosting provider. Persistent pilot operation is not approved until the provider’s backup retention and logging behaviour match the published privacy and deletion rules. In particular, the current 90-day lima-city backup retention conflicts with the approved maximum of 30 days after primary deletion.
