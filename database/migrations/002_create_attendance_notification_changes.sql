CREATE TABLE attendance_notification_changes (
    id BINARY(16) NOT NULL,
    event_id BINARY(16) NOT NULL,
    change_type ENUM('join', 'withdraw') NOT NULL,
    occurred_at DATETIME(6) NOT NULL,
    delete_at DATETIME(6) NOT NULL,
    PRIMARY KEY (id),
    KEY ix_attendance_notification_event_time (event_id, occurred_at),
    KEY ix_attendance_notification_delete_at (delete_at),
    CONSTRAINT fk_attendance_notification_event
        FOREIGN KEY (event_id) REFERENCES coordination_events (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
