ALTER TABLE first_sats_invitations
    ADD COLUMN wallet_path VARCHAR(16) NULL AFTER stage_four_completed_at;
