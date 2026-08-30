ALTER TABLE first_sats_invitations
    ADD COLUMN stage_three_completed_at DATETIME(6) NULL AFTER stage_two_completed_at;
