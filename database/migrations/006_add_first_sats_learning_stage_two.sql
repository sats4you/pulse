ALTER TABLE first_sats_invitations
    ADD COLUMN stage_two_completed_at DATETIME(6) NULL AFTER fraction_survey_state;
