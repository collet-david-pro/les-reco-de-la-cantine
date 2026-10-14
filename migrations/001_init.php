<?php
/**
 * Migration 001 : création du schéma initial.
 * Ne jamais modifier une migration publiée : ajouter un nouveau fichier 002_..., 003_...
 * Les {noms} sont remplacés par les noms de tables préfixés.
 */
declare(strict_types=1);

defined('CANTINE') || exit;

$mysqlTable = ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

return [
    'mysql' => [
        "CREATE TABLE IF NOT EXISTS {settings} (
            name VARCHAR(64) NOT NULL PRIMARY KEY,
            value MEDIUMTEXT NULL
        )$mysqlTable",

        "CREATE TABLE IF NOT EXISTS {users} (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(30) NOT NULL,
            display_name VARCHAR(50) NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            role VARCHAR(10) NOT NULL DEFAULT 'member',
            avatar_color VARCHAR(7) NOT NULL DEFAULT '#C2410C',
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            must_change_password TINYINT(1) NOT NULL DEFAULT 0,
            session_version INT UNSIGNED NOT NULL DEFAULT 1,
            last_seen_at DATETIME NULL,
            previous_seen_at DATETIME NULL,
            is_demo TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            UNIQUE KEY {users}_username (username)
        )$mysqlTable",

        "CREATE TABLE IF NOT EXISTS {remember_tokens} (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            selector CHAR(24) NOT NULL,
            validator_hash CHAR(64) NOT NULL,
            user_id INT UNSIGNED NOT NULL,
            expires_at DATETIME NOT NULL,
            created_at DATETIME NOT NULL,
            UNIQUE KEY {remember_tokens}_selector (selector),
            KEY {remember_tokens}_user (user_id),
            CONSTRAINT {remember_tokens}_user_fk FOREIGN KEY (user_id) REFERENCES {users} (id) ON DELETE CASCADE
        )$mysqlTable",

        "CREATE TABLE IF NOT EXISTS {media} (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            type VARCHAR(20) NOT NULL,
            source VARCHAR(20) NOT NULL,
            external_id VARCHAR(100) NULL,
            title VARCHAR(255) NOT NULL,
            normalized_title VARCHAR(190) NOT NULL,
            year SMALLINT NULL,
            description TEXT NULL,
            image_url VARCHAR(500) NULL,
            external_url VARCHAR(500) NULL,
            trailer_url VARCHAR(500) NULL,
            isbn VARCHAR(20) NULL,
            extra TEXT NULL,
            added_by INT UNSIGNED NULL,
            is_demo TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            UNIQUE KEY {media}_external (type, source, external_id),
            KEY {media}_type_created (type, created_at),
            KEY {media}_created (created_at),
            KEY {media}_normalized_title (normalized_title),
            KEY {media}_added_by (added_by),
            CONSTRAINT {media}_added_by_fk FOREIGN KEY (added_by) REFERENCES {users} (id) ON DELETE SET NULL
        )$mysqlTable",

        "CREATE TABLE IF NOT EXISTS {genres} (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            UNIQUE KEY {genres}_name (name)
        )$mysqlTable",

        "CREATE TABLE IF NOT EXISTS {media_genres} (
            media_id INT UNSIGNED NOT NULL,
            genre_id INT UNSIGNED NOT NULL,
            PRIMARY KEY (media_id, genre_id),
            KEY {media_genres}_genre (genre_id),
            CONSTRAINT {media_genres}_media_fk FOREIGN KEY (media_id) REFERENCES {media} (id) ON DELETE CASCADE,
            CONSTRAINT {media_genres}_genre_fk FOREIGN KEY (genre_id) REFERENCES {genres} (id) ON DELETE CASCADE
        )$mysqlTable",

        "CREATE TABLE IF NOT EXISTS {user_media} (
            user_id INT UNSIGNED NOT NULL,
            media_id INT UNSIGNED NOT NULL,
            status VARCHAR(20) NOT NULL,
            rating TINYINT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (user_id, media_id),
            KEY {user_media}_user_status (user_id, status),
            KEY {user_media}_media_status (media_id, status),
            KEY {user_media}_updated (updated_at),
            CONSTRAINT {user_media}_user_fk FOREIGN KEY (user_id) REFERENCES {users} (id) ON DELETE CASCADE,
            CONSTRAINT {user_media}_media_fk FOREIGN KEY (media_id) REFERENCES {media} (id) ON DELETE CASCADE
        )$mysqlTable",

        "CREATE TABLE IF NOT EXISTS {comments} (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            media_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NOT NULL,
            body TEXT NOT NULL,
            is_spoiler TINYINT(1) NOT NULL DEFAULT 0,
            is_demo TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NULL,
            KEY {comments}_media (media_id, created_at),
            KEY {comments}_created (created_at),
            KEY {comments}_user (user_id),
            CONSTRAINT {comments}_media_fk FOREIGN KEY (media_id) REFERENCES {media} (id) ON DELETE CASCADE,
            CONSTRAINT {comments}_user_fk FOREIGN KEY (user_id) REFERENCES {users} (id) ON DELETE CASCADE
        )$mysqlTable",

        "CREATE TABLE IF NOT EXISTS {watch_providers} (
            media_id INT UNSIGNED NOT NULL PRIMARY KEY,
            region VARCHAR(2) NOT NULL,
            data MEDIUMTEXT NOT NULL,
            updated_at DATETIME NOT NULL,
            CONSTRAINT {watch_providers}_media_fk FOREIGN KEY (media_id) REFERENCES {media} (id) ON DELETE CASCADE
        )$mysqlTable",

        "CREATE TABLE IF NOT EXISTS {login_attempts} (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            identifier VARCHAR(100) NOT NULL,
            ip VARCHAR(45) NOT NULL,
            attempted_at DATETIME NOT NULL,
            KEY {login_attempts}_identifier (identifier, attempted_at),
            KEY {login_attempts}_ip (ip, attempted_at)
        )$mysqlTable",

        "CREATE TABLE IF NOT EXISTS {api_cache} (
            cache_key CHAR(64) NOT NULL PRIMARY KEY,
            body MEDIUMTEXT NOT NULL,
            expires_at DATETIME NOT NULL,
            KEY {api_cache}_expires (expires_at)
        )$mysqlTable",
    ],

    'sqlite' => [
        'CREATE TABLE IF NOT EXISTS {settings} (
            name TEXT NOT NULL PRIMARY KEY,
            value TEXT NULL
        )',

        "CREATE TABLE IF NOT EXISTS {users} (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT NOT NULL UNIQUE,
            display_name TEXT NOT NULL,
            password_hash TEXT NOT NULL,
            role TEXT NOT NULL DEFAULT 'member',
            avatar_color TEXT NOT NULL DEFAULT '#C2410C',
            is_active INTEGER NOT NULL DEFAULT 1,
            must_change_password INTEGER NOT NULL DEFAULT 0,
            session_version INTEGER NOT NULL DEFAULT 1,
            last_seen_at TEXT NULL,
            previous_seen_at TEXT NULL,
            is_demo INTEGER NOT NULL DEFAULT 0,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
        )",

        'CREATE TABLE IF NOT EXISTS {remember_tokens} (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            selector TEXT NOT NULL UNIQUE,
            validator_hash TEXT NOT NULL,
            user_id INTEGER NOT NULL REFERENCES {users} (id) ON DELETE CASCADE,
            expires_at TEXT NOT NULL,
            created_at TEXT NOT NULL
        )',
        'CREATE INDEX IF NOT EXISTS {remember_tokens}_user ON {remember_tokens} (user_id)',

        'CREATE TABLE IF NOT EXISTS {media} (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            type TEXT NOT NULL,
            source TEXT NOT NULL,
            external_id TEXT NULL,
            title TEXT NOT NULL,
            normalized_title TEXT NOT NULL,
            year INTEGER NULL,
            description TEXT NULL,
            image_url TEXT NULL,
            external_url TEXT NULL,
            trailer_url TEXT NULL,
            isbn TEXT NULL,
            extra TEXT NULL,
            added_by INTEGER NULL REFERENCES {users} (id) ON DELETE SET NULL,
            is_demo INTEGER NOT NULL DEFAULT 0,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL,
            UNIQUE (type, source, external_id)
        )',
        'CREATE INDEX IF NOT EXISTS {media}_type_created ON {media} (type, created_at)',
        'CREATE INDEX IF NOT EXISTS {media}_created ON {media} (created_at)',
        'CREATE INDEX IF NOT EXISTS {media}_normalized_title ON {media} (normalized_title)',
        'CREATE INDEX IF NOT EXISTS {media}_added_by ON {media} (added_by)',

        'CREATE TABLE IF NOT EXISTS {genres} (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL UNIQUE
        )',

        'CREATE TABLE IF NOT EXISTS {media_genres} (
            media_id INTEGER NOT NULL REFERENCES {media} (id) ON DELETE CASCADE,
            genre_id INTEGER NOT NULL REFERENCES {genres} (id) ON DELETE CASCADE,
            PRIMARY KEY (media_id, genre_id)
        )',
        'CREATE INDEX IF NOT EXISTS {media_genres}_genre ON {media_genres} (genre_id)',

        'CREATE TABLE IF NOT EXISTS {user_media} (
            user_id INTEGER NOT NULL REFERENCES {users} (id) ON DELETE CASCADE,
            media_id INTEGER NOT NULL REFERENCES {media} (id) ON DELETE CASCADE,
            status TEXT NOT NULL,
            rating INTEGER NULL,
            updated_at TEXT NOT NULL,
            PRIMARY KEY (user_id, media_id)
        )',
        'CREATE INDEX IF NOT EXISTS {user_media}_user_status ON {user_media} (user_id, status)',
        'CREATE INDEX IF NOT EXISTS {user_media}_media_status ON {user_media} (media_id, status)',
        'CREATE INDEX IF NOT EXISTS {user_media}_updated ON {user_media} (updated_at)',

        'CREATE TABLE IF NOT EXISTS {comments} (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            media_id INTEGER NOT NULL REFERENCES {media} (id) ON DELETE CASCADE,
            user_id INTEGER NOT NULL REFERENCES {users} (id) ON DELETE CASCADE,
            body TEXT NOT NULL,
            is_spoiler INTEGER NOT NULL DEFAULT 0,
            is_demo INTEGER NOT NULL DEFAULT 0,
            created_at TEXT NOT NULL,
            updated_at TEXT NULL
        )',
        'CREATE INDEX IF NOT EXISTS {comments}_media ON {comments} (media_id, created_at)',
        'CREATE INDEX IF NOT EXISTS {comments}_created ON {comments} (created_at)',
        'CREATE INDEX IF NOT EXISTS {comments}_user ON {comments} (user_id)',

        'CREATE TABLE IF NOT EXISTS {watch_providers} (
            media_id INTEGER NOT NULL PRIMARY KEY REFERENCES {media} (id) ON DELETE CASCADE,
            region TEXT NOT NULL,
            data TEXT NOT NULL,
            updated_at TEXT NOT NULL
        )',

        'CREATE TABLE IF NOT EXISTS {login_attempts} (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            identifier TEXT NOT NULL,
            ip TEXT NOT NULL,
            attempted_at TEXT NOT NULL
        )',
        'CREATE INDEX IF NOT EXISTS {login_attempts}_identifier ON {login_attempts} (identifier, attempted_at)',
        'CREATE INDEX IF NOT EXISTS {login_attempts}_ip ON {login_attempts} (ip, attempted_at)',

        'CREATE TABLE IF NOT EXISTS {api_cache} (
            cache_key TEXT NOT NULL PRIMARY KEY,
            body TEXT NOT NULL,
            expires_at TEXT NOT NULL
        )',
        'CREATE INDEX IF NOT EXISTS {api_cache}_expires ON {api_cache} (expires_at)',
    ],
];
