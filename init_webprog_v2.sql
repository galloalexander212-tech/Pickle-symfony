SET FOREIGN_KEY_CHECKS=0;

DROP TABLE IF EXISTS EVENT_JOINS;
DROP TABLE IF EXISTS BOOKINGS;
DROP TABLE IF EXISTS EVENTS;
DROP TABLE IF EXISTS COURTS;
DROP TABLE IF EXISTS USERS;

CREATE TABLE USERS (
    id INT NOT NULL AUTO_INCREMENT,
    username VARCHAR(20) NOT NULL,
    email VARCHAR(255) NOT NULL,
    password VARCHAR(255) NOT NULL,
    role VARCHAR(10) NOT NULL DEFAULT 'user',
    failed_attempts INT NOT NULL DEFAULT 0,
    locked_until DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY UNIQ_E3D76759F85E0677 (username),
    UNIQUE KEY UNIQ_E3D76759E7927C74 (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE COURTS (
    id INT NOT NULL AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    location VARCHAR(150) NOT NULL,
    rating DECIMAL(2,1) NOT NULL DEFAULT 4.5,
    image VARCHAR(255) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE EVENTS (
    id INT NOT NULL AUTO_INCREMENT,
    type VARCHAR(20) NOT NULL DEFAULT 'open_play',
    title VARCHAR(150) NOT NULL,
    description LONGTEXT DEFAULT NULL,
    location VARCHAR(150) NOT NULL,
    event_date DATE NOT NULL,
    event_time TIME NOT NULL,
    max_players INT NOT NULL DEFAULT 8,
    prize VARCHAR(255) DEFAULT NULL,
    image VARCHAR(255) DEFAULT NULL,
    created_by INT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY IDX_A37F90CCDE12AB56 (created_by),
    CONSTRAINT fk_events_creator FOREIGN KEY (created_by) REFERENCES USERS (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE BOOKINGS (
    id INT NOT NULL AUTO_INCREMENT,
    user_id INT NOT NULL,
    court VARCHAR(100) NOT NULL,
    court_id INT DEFAULT NULL,
    booking_date DATE NOT NULL,
    booking_time TIME NOT NULL,
    players INT NOT NULL DEFAULT 2,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY IDX_BCB6A079A76ED395 (user_id),
    KEY IDX_BCB6A079E3184009 (court_id),
    CONSTRAINT fk_bookings_user FOREIGN KEY (user_id) REFERENCES USERS (id) ON DELETE CASCADE,
    CONSTRAINT fk_bookings_court FOREIGN KEY (court_id) REFERENCES COURTS (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE EVENT_JOINS (
    id INT NOT NULL AUTO_INCREMENT,
    event_id INT NOT NULL,
    user_id INT NOT NULL,
    joined_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_event_user (event_id, user_id),
    KEY IDX_C825CF56A76ED395 (user_id),
    CONSTRAINT fk_event_joins_event FOREIGN KEY (event_id) REFERENCES EVENTS (id) ON DELETE CASCADE,
    CONSTRAINT fk_event_joins_user FOREIGN KEY (user_id) REFERENCES USERS (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    CREATE TABLE IF NOT EXISTS messenger_messages (
        id BIGINT NOT NULL AUTO_INCREMENT,
        body LONGTEXT NOT NULL,
        headers LONGTEXT NOT NULL,
        queue_name VARCHAR(190) NOT NULL,
        created_at DATETIME NOT NULL,
        available_at DATETIME NOT NULL,
        delivered_at DATETIME DEFAULT NULL,
        INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 (queue_name, available_at, delivered_at, id),
        PRIMARY KEY (id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO USERS (id, username, email, password, role, failed_attempts, locked_until, created_at) VALUES
(1, 'admin', 'admin@pickle.ph', '$2y$10$jTCLwI8dtu39ox0LxOOnBOBqkpBLknDMzkXQCYaGZL/zv/yXhfPAq', 'admin', 0, NULL, '2026-11-01 09:00:00'),
(2, 'juan', 'juan@pickle.ph', '$2y$10$jTCLwI8dtu39ox0LxOOnBOBqkpBLknDMzkXQCYaGZL/zv/yXhfPAq', 'user', 0, NULL, '2026-11-02 14:30:00'),
(3, 'maria', 'maria@pickle.ph', '$2y$10$jTCLwI8dtu39ox0LxOOnBOBqkpBLknDMzkXQCYaGZL/zv/yXhfPAq', 'user', 0, NULL, '2026-11-03 10:15:00');

INSERT INTO COURTS (id, name, location, rating, image, created_at) VALUES
(1, 'Riverside Pickleball Club', 'Dumaguete City', 4.8, 'uploads/courts/courts_6aa2898ed41b5.jpg', '2026-11-01 08:00:00'),
(2, 'Skyline Sports Center', 'Dumaguete City', 4.7, 'uploads/courts/courts_6aa2899cdb517.jpg', '2026-11-01 08:15:00'),
(3, 'Bayview Courts', 'Sibulan', 4.5, 'uploads/courts/courts_6aa289a5c797f.jpg', '2026-11-01 08:30:00'),
(4, 'Northside Pickleball Park', 'Valencia', 4.9, 'uploads/courts/courts_6aa289af53f2d.jpg', '2026-11-01 08:45:00'),
(5, 'Campus Recreation Courts', 'Dumaguete City', 4.6, 'uploads/courts/courts_6aa81a279bba1.jpg', '2026-11-01 09:00:00');

INSERT INTO EVENTS (id, type, title, description, location, event_date, event_time, max_players, prize, image, created_by, created_at) VALUES
(1, 'open_play', 'Saturday Social Rally', 'All levels welcome. Bring a paddle or borrow one at the court.', 'Riverside Pickleball Club, Dumaguete City', '2026-11-14', '08:00:00', 12, NULL, 'uploads/events/events_6aa28a881c0cf.jpg', 1, '2026-11-01 10:00:00'),
(2, 'open_play', 'Sunset Doubles Mixer', 'A friendly rotating doubles session for local players.', 'Bayview Courts, Sibulan', '2026-11-15', '16:30:00', 16, NULL, 'uploads/events/events_6aa28b162e623.jpg', 1, '2026-11-01 10:15:00'),
(3, 'tournament', 'Negros Weekend Cup', 'Round-robin play followed by a knockout final.', 'Skyline Sports Center, Dumaguete City', '2026-11-21', '09:00:00', 16, 'Trophy and PHP 5,000', 'uploads/events/events_6aa28b21391b0.jpg', 1, '2026-11-01 10:30:00'),
(4, 'tournament', 'Valencia Fall Classic', 'Mixed doubles tournament. Registration is open to all skill levels.', 'Northside Pickleball Park, Valencia', '2026-11-28', '10:00:00', 24, 'Trophy and sponsor prizes', 'uploads/events/events_6aa28b2ee1fcb.jpg', 1, '2026-11-01 10:45:00');

INSERT INTO BOOKINGS (id, user_id, court, court_id, booking_date, booking_time, players, status, created_at) VALUES
(1, 2, 'Riverside Pickleball Club', 1, '2026-11-10', '08:00:00', 4, 'confirmed', '2026-11-04 09:00:00'),
(2, 3, 'Skyline Sports Center', 2, '2026-11-11', '10:00:00', 2, 'pending', '2026-11-04 09:15:00'),
(3, 1, 'Bayview Courts', 3, '2026-11-12', '17:00:00', 6, 'confirmed', '2026-11-04 09:30:00'),
(4, 2, 'Northside Pickleball Park', 4, '2026-11-13', '19:00:00', 4, 'pending', '2026-11-04 09:45:00');

INSERT INTO EVENT_JOINS (id, event_id, user_id, joined_at) VALUES
(1, 1, 2, '2026-11-04 10:00:00'),
(2, 1, 3, '2026-11-04 10:05:00'),
(3, 2, 3, '2026-11-04 10:10:00'),
(4, 3, 2, '2026-11-04 10:15:00');

SET FOREIGN_KEY_CHECKS=1;