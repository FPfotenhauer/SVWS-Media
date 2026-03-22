CREATE TABLE IF NOT EXISTS media (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title TEXT NOT NULL,
    type TEXT,
    isbn TEXT,
    inventory_number TEXT,
    condition TEXT,
    location TEXT
);

CREATE TABLE IF NOT EXISTS media_titles (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    type TEXT,
    location TEXT,
    beleg_filter INTEGER NOT NULL DEFAULT 0,
    created_at TEXT,
    updated_at TEXT
);

CREATE TABLE IF NOT EXISTS media_copies (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title_id INTEGER NOT NULL,
    barcode TEXT UNIQUE,
    inventory_number TEXT,
    condition TEXT,
    memo TEXT,
    is_active INTEGER NOT NULL DEFAULT 1,
    created_at TEXT,
    updated_at TEXT,
    FOREIGN KEY (title_id) REFERENCES media_titles(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS borrowers (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    kind TEXT NOT NULL,
    svws_id INTEGER,
    external_id TEXT,
    vorname TEXT,
    nachname TEXT,
    display_name TEXT,
    klasse TEXT,
    jahrgang TEXT,
    memo TEXT,
    is_blocked INTEGER NOT NULL DEFAULT 0,
    is_active INTEGER NOT NULL DEFAULT 1,
    raw_json TEXT,
    updated_at TEXT
);

CREATE TABLE IF NOT EXISTS borrower_group_memberships (
    borrower_id INTEGER NOT NULL,
    group_id INTEGER NOT NULL,
    role_in_group TEXT,
    PRIMARY KEY (borrower_id, group_id),
    FOREIGN KEY (borrower_id) REFERENCES borrowers(id) ON DELETE CASCADE,
    FOREIGN KEY (group_id) REFERENCES svws_groups(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT NOT NULL UNIQUE,
    password_hash TEXT NOT NULL,
    is_active INTEGER NOT NULL DEFAULT 1,
    role TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS lending (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    media_id INTEGER NOT NULL,
    user_id INTEGER NOT NULL,
    copy_id INTEGER,
    borrower_id INTEGER,
    borrowed_at TEXT NOT NULL,
    returned_at TEXT,
    status TEXT NOT NULL,
    school_year INTEGER,
    klasse_snapshot TEXT,
    kurs_snapshot TEXT,
    kurs_lehrer_snapshot TEXT,
    created_by_user_id INTEGER,
    FOREIGN KEY (media_id) REFERENCES media(id),
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (copy_id) REFERENCES media_copies(id),
    FOREIGN KEY (borrower_id) REFERENCES borrowers(id),
    FOREIGN KEY (created_by_user_id) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS svws_students (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    svws_id INTEGER NOT NULL UNIQUE,
    nachname TEXT,
    vorname TEXT,
    anzeige_name TEXT,
    klasse TEXT,
    status TEXT,
    email TEXT,
    raw_json TEXT,
    updated_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS svws_teachers (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    svws_id INTEGER NOT NULL UNIQUE,
    kuerzel TEXT,
    nachname TEXT,
    vorname TEXT,
    anzeige_name TEXT,
    email TEXT,
    raw_json TEXT,
    updated_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS svws_groups (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    svws_id INTEGER NOT NULL UNIQUE,
    kuerzel TEXT,
    name TEXT,
    jahrgang TEXT,
    raw_json TEXT,
    updated_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS svws_student_groups (
    student_id INTEGER NOT NULL,
    group_id INTEGER NOT NULL,
    PRIMARY KEY (student_id, group_id),
    FOREIGN KEY (student_id) REFERENCES svws_students(id) ON DELETE CASCADE,
    FOREIGN KEY (group_id) REFERENCES svws_groups(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS svws_teacher_groups (
    teacher_id INTEGER NOT NULL,
    group_id INTEGER NOT NULL,
    PRIMARY KEY (teacher_id, group_id),
    FOREIGN KEY (teacher_id) REFERENCES svws_teachers(id) ON DELETE CASCADE,
    FOREIGN KEY (group_id) REFERENCES svws_groups(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS svws_sync_runs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    started_at TEXT NOT NULL,
    finished_at TEXT,
    status TEXT NOT NULL,
    endpoint TEXT,
    message TEXT,
    stats_json TEXT
);

CREATE UNIQUE INDEX IF NOT EXISTS idx_media_copies_barcode_unique ON media_copies(barcode);
CREATE INDEX IF NOT EXISTS idx_media_copies_title_id ON media_copies(title_id);
CREATE UNIQUE INDEX IF NOT EXISTS idx_borrowers_kind_svws_id_unique ON borrowers(kind, svws_id);
CREATE UNIQUE INDEX IF NOT EXISTS idx_borrowers_kind_external_id ON borrowers(kind, external_id);
