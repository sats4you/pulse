CREATE TABLE first_sats_handover_states (
 invitation_id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 sent_at DATETIME(6) NULL, received_at DATETIME(6) NULL, not_received_at DATETIME(6) NULL,
 PRIMARY KEY (invitation_id),
 CONSTRAINT fk_first_sats_handover_invitation FOREIGN KEY (invitation_id) REFERENCES first_sats_invitations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
