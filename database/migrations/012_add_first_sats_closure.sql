ALTER TABLE first_sats_invitations
    MODIFY status ENUM('open', 'accepted', 'revoked', 'expired', 'participant_ended') NOT NULL DEFAULT 'open';

ALTER TABLE first_sats_handover_states
    ADD COLUMN closed_without_confirmation_at DATETIME(6) NULL AFTER not_received_at,
    ADD KEY ix_first_sats_handover_closed (closed_without_confirmation_at);
