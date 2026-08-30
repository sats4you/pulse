CREATE TABLE first_sats_payment_requests (
    invitation_id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    bolt11_invoice TEXT CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    sats_amount INT UNSIGNED NOT NULL,
    expires_at DATETIME(6) NOT NULL,
    submitted_at DATETIME(6) NOT NULL,
    PRIMARY KEY (invitation_id),
    CONSTRAINT fk_first_sats_payment_invitation FOREIGN KEY (invitation_id) REFERENCES first_sats_invitations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
