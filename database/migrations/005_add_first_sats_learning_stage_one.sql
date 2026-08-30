ALTER TABLE first_sats_invitations
    ADD COLUMN learning_content_version VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT '2026-08-23-v1' AFTER complete_until,
    ADD COLUMN stage_one_completed_at DATETIME(6) NULL AFTER learning_content_version,
    ADD COLUMN fraction_survey_state ENUM('pending', 'answered', 'skipped') NOT NULL DEFAULT 'pending' AFTER stage_one_completed_at;

CREATE TABLE first_sats_anonymous_counters (
    metric_key VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    content_version VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    locale CHAR(2) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    answer_key VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    count_value BIGINT UNSIGNED NOT NULL DEFAULT 0,
    updated_at DATETIME(6) NOT NULL,
    PRIMARY KEY (metric_key, content_version, locale, answer_key),
    CONSTRAINT chk_first_sats_counter_locale CHECK (locale IN ('de', 'fr', 'it', 'rm'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
