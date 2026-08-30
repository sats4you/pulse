ALTER TABLE first_sats_invitations
    ADD COLUMN participant_adult_attested TINYINT(1) NULL AFTER inviter_terms_accepted_at,
    ADD COLUMN participant_terms_version VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NULL AFTER participant_adult_attested,
    ADD COLUMN participant_terms_accepted_at DATETIME(6) NULL AFTER participant_terms_version,
    ADD COLUMN accepted_at DATETIME(6) NULL AFTER participant_terms_accepted_at,
    ADD COLUMN complete_until DATETIME(6) NULL AFTER accepted_at;

ALTER TABLE first_sats_invitations
    ADD KEY ix_first_sats_complete_until (complete_until),
    ADD CONSTRAINT chk_first_sats_participant_adult CHECK (participant_adult_attested IS NULL OR participant_adult_attested = 1);
