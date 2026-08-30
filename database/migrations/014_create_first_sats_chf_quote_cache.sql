CREATE TABLE first_sats_chf_quote_cache (
    cache_key VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    bid_units BIGINT UNSIGNED NOT NULL,
    ask_units BIGINT UNSIGNED NOT NULL,
    fetched_at DATETIME(6) NOT NULL,
    PRIMARY KEY (cache_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
