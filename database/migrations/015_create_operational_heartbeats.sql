CREATE TABLE operational_heartbeats (
    operation_key VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    last_succeeded_at DATETIME(6) NOT NULL,
    PRIMARY KEY (operation_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
