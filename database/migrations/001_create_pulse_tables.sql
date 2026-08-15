CREATE TABLE coordination_rounds (
    id BINARY(16) NOT NULL,
    public_slug VARCHAR(100) NOT NULL,
    name VARCHAR(160) NOT NULL,
    timezone VARCHAR(64) NOT NULL DEFAULT 'Europe/Zurich',
    participant_access_digest BINARY(32) NOT NULL,
    participant_access_version INT UNSIGNED NOT NULL DEFAULT 1,
    admin_access_digest BINARY(32) NOT NULL,
    admin_access_version INT UNSIGNED NOT NULL DEFAULT 1,
    admin_recovery_digest BINARY(32) NOT NULL,
    admin_recovery_version INT UNSIGNED NOT NULL DEFAULT 1,
    admin_credentials_rotated_at DATETIME(6) NULL,
    created_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NOT NULL,
    delete_at DATETIME(6) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_coordination_rounds_public_slug (public_slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE coordination_events (
    id BINARY(16) NOT NULL,
    public_id BINARY(16) NOT NULL,
    round_id BINARY(16) NOT NULL,
    title VARCHAR(180) NOT NULL,
    starts_at DATETIME(6) NOT NULL,
    ends_at DATETIME(6) NULL,
    location VARCHAR(240) NULL,
    note VARCHAR(1000) NULL,
    publication_state ENUM('draft', 'scheduled', 'published', 'cancelled') NOT NULL,
    publish_at DATETIME(6) NULL,
    rsvp_closed_at DATETIME(6) NULL,
    material_changed_at DATETIME(6) NULL,
    created_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NOT NULL,
    delete_at DATETIME(6) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_coordination_events_public_id (public_id),
    KEY ix_coordination_events_round_start (round_id, starts_at),
    KEY ix_coordination_events_delete_at (delete_at),
    CONSTRAINT fk_coordination_events_round
        FOREIGN KEY (round_id) REFERENCES coordination_rounds (id) ON DELETE CASCADE,
    CONSTRAINT chk_coordination_events_end
        CHECK (ends_at IS NULL OR ends_at > starts_at),
    CONSTRAINT chk_coordination_events_publish_at
        CHECK (publication_state <> 'scheduled' OR publish_at IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE attendance_commitments (
    id BINARY(16) NOT NULL,
    event_id BINARY(16) NOT NULL,
    participant_secret_digest BINARY(32) NOT NULL,
    created_at DATETIME(6) NOT NULL,
    delete_at DATETIME(6) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_attendance_event_secret (event_id, participant_secret_digest),
    KEY ix_attendance_delete_at (delete_at),
    CONSTRAINT fk_attendance_event
        FOREIGN KEY (event_id) REFERENCES coordination_events (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
