CREATE TABLE first_sats_metric_contributions (
    invitation_id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    metric_key VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    content_version VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    contributed_at DATETIME(6) NOT NULL,
    PRIMARY KEY (invitation_id, metric_key, content_version),
    CONSTRAINT fk_first_sats_metric_invitation FOREIGN KEY (invitation_id)
        REFERENCES first_sats_invitations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
