<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';

function initializeSchemaIfNeeded(PDO $db): void
{
    static $initialized = false;

    if ($initialized) {
        return;
    }

    $schemaFile = BASE_PATH . 'data/schema.sql';
    if (!is_file($schemaFile)) {
        throw new RuntimeException('Schema file not found: ' . $schemaFile);
    }

    $schemaSql = file_get_contents($schemaFile);
    if ($schemaSql === false) {
        throw new RuntimeException('Failed to read schema file: ' . $schemaFile);
    }

    $db->exec($schemaSql);
    ensureAuthSchemaAndDefaultUser($db);
    ensureLibraryDomainSchema($db);

    // Expensive borrower projection should not run on every page request.
    // The regular sync flow already refreshes borrowers explicitly.
    if (shouldBootstrapBorrowersFromSvwsData($db)) {
        refreshBorrowersFromSvwsData($db);
    }
    $initialized = true;
}

function ensureAuthSchemaAndDefaultUser(PDO $db): void
{
    $columns = [];
    $stmt = $db->query('PRAGMA table_info(users)');
    foreach ($stmt->fetchAll() as $row) {
        $columns[] = (string) $row['name'];
    }

    if (!in_array('password_hash', $columns, true)) {
        $db->exec("ALTER TABLE users ADD COLUMN password_hash TEXT NOT NULL DEFAULT ''");
    }
    if (!in_array('is_active', $columns, true)) {
        $db->exec('ALTER TABLE users ADD COLUMN is_active INTEGER NOT NULL DEFAULT 1');
    }
    if (!in_array('must_change_password', $columns, true)) {
        $db->exec('ALTER TABLE users ADD COLUMN must_change_password INTEGER NOT NULL DEFAULT 0');
    }

    $db->exec(
        'CREATE TABLE IF NOT EXISTS login_attempts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT NOT NULL,
            ip_address TEXT NOT NULL,
            attempted_at TEXT NOT NULL,
            success INTEGER NOT NULL DEFAULT 0
        )'
    );
    $db->exec('CREATE INDEX IF NOT EXISTS idx_login_attempts_username_ip_time ON login_attempts(username, ip_address, attempted_at)');
    $db->exec('CREATE INDEX IF NOT EXISTS idx_login_attempts_attempted_at ON login_attempts(attempted_at)');

    $db->exec('CREATE UNIQUE INDEX IF NOT EXISTS idx_users_username_unique ON users(username)');

    $adminStmt = $db->prepare('SELECT id, password_hash, is_active, must_change_password FROM users WHERE username = :username LIMIT 1');
    $adminStmt->execute(['username' => 'Admin']);
    $admin = $adminStmt->fetch();

    $defaultHash = password_hash('admin', PASSWORD_DEFAULT);
    if ($admin === false) {
        $insert = $db->prepare('INSERT INTO users (username, password_hash, is_active, must_change_password, role) VALUES (:username, :password_hash, :is_active, :must_change_password, :role)');
        $insert->execute([
            'username' => 'Admin',
            'password_hash' => $defaultHash,
            'is_active' => 1,
            'must_change_password' => 1,
            'role' => 'admin',
        ]);
        return;
    }

    if (trim((string) ($admin['password_hash'] ?? '')) === '' || (int) ($admin['is_active'] ?? 0) !== 1) {
        $update = $db->prepare('UPDATE users SET password_hash = :password_hash, is_active = :is_active, must_change_password = :must_change_password, role = :role WHERE id = :id');
        $update->execute([
            'password_hash' => $defaultHash,
            'is_active' => 1,
            'must_change_password' => 1,
            'role' => 'admin',
            'id' => (int) $admin['id'],
        ]);
        return;
    }

    // Force an immediate password change while default credentials are still active.
    if ((string) ($admin['password_hash'] ?? '') !== ''
        && password_verify('admin', (string) $admin['password_hash'])
        && (int) ($admin['must_change_password'] ?? 0) !== 1) {
        $forceChange = $db->prepare('UPDATE users SET must_change_password = 1 WHERE id = :id');
        $forceChange->execute(['id' => (int) $admin['id']]);
    }
}

function ensureLibraryDomainSchema(PDO $db): void
{
    $db->exec(
        'CREATE TABLE IF NOT EXISTS media_titles (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            type TEXT,
            location TEXT,
            beleg_filter INTEGER NOT NULL DEFAULT 0,
            created_at TEXT,
            updated_at TEXT
        )'
    );

    $db->exec(
        'CREATE TABLE IF NOT EXISTS media_copies (
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
        )'
    );

    $db->exec(
        'CREATE TABLE IF NOT EXISTS borrowers (
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
        )'
    );

    $db->exec(
        'CREATE TABLE IF NOT EXISTS borrower_group_memberships (
            borrower_id INTEGER NOT NULL,
            group_id INTEGER NOT NULL,
            role_in_group TEXT,
            PRIMARY KEY (borrower_id, group_id),
            FOREIGN KEY (borrower_id) REFERENCES borrowers(id) ON DELETE CASCADE,
            FOREIGN KEY (group_id) REFERENCES svws_groups(id) ON DELETE CASCADE
        )'
    );

    ensureColumnExists($db, 'lending', 'copy_id', 'INTEGER');
    ensureColumnExists($db, 'lending', 'borrower_id', 'INTEGER');
    ensureColumnExists($db, 'lending', 'school_year', 'INTEGER');
    ensureColumnExists($db, 'lending', 'klasse_snapshot', 'TEXT');
    ensureColumnExists($db, 'lending', 'kurs_snapshot', 'TEXT');
    ensureColumnExists($db, 'lending', 'kurs_lehrer_snapshot', 'TEXT');
    ensureColumnExists($db, 'lending', 'created_by_user_id', 'INTEGER');

    $db->exec(
        'CREATE TABLE IF NOT EXISTS svws_school_meta (
            id INTEGER PRIMARY KEY CHECK (id = 1),
            schulname TEXT,
            schulnummer TEXT,
            ort TEXT,
            plz TEXT,
            updated_at TEXT
        )'
    );

    $db->exec(
        'CREATE TABLE IF NOT EXISTS svws_classes (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            svws_id INTEGER NOT NULL UNIQUE,
            kuerzel TEXT,
            name TEXT,
            jahrgang TEXT,
            raw_json TEXT,
            updated_at TEXT NOT NULL
        )'
    );

    $db->exec(
        'CREATE TABLE IF NOT EXISTS svws_sync_config (
            id INTEGER PRIMARY KEY CHECK (id = 1),
            base_url TEXT,
            schema TEXT,
            id_lernplattform INTEGER,
            id_schuljahresabschnitt INTEGER,
            verify_tls INTEGER NOT NULL DEFAULT 1,
            username TEXT,
            updated_at TEXT
        )'
    );

    ensureColumnExists($db, 'svws_sync_config', 'password_enc', 'TEXT');
    ensureColumnExists($db, 'svws_school_meta', 'mailadresse', 'TEXT');

    $db->exec('CREATE UNIQUE INDEX IF NOT EXISTS idx_media_copies_barcode_unique ON media_copies(barcode)');
    $db->exec('CREATE INDEX IF NOT EXISTS idx_media_copies_title_id ON media_copies(title_id)');
    $db->exec('CREATE UNIQUE INDEX IF NOT EXISTS idx_borrowers_kind_svws_id_unique ON borrowers(kind, svws_id)');
    $db->exec('CREATE UNIQUE INDEX IF NOT EXISTS idx_borrowers_kind_external_id ON borrowers(kind, external_id)');
    $db->exec('CREATE INDEX IF NOT EXISTS idx_lending_copy_id ON lending(copy_id)');
    $db->exec('CREATE INDEX IF NOT EXISTS idx_lending_borrower_id ON lending(borrower_id)');
    $db->exec('CREATE UNIQUE INDEX IF NOT EXISTS idx_lending_open_copy_unique ON lending(copy_id) WHERE returned_at IS NULL');

    backfillLegacyMediaToTitlesAndCopies($db);
    backfillBorrowersFromLegacyUsers($db);
    backfillLegacyLending($db);
}

/**
 * Encrypt a plaintext value with AES-256-CBC using APP_SECRET.
 * Returns a base64-encoded string of IV + ciphertext.
 */
function encryptAppValue(string $plaintext): string
{
    $key = hash('sha256', APP_SECRET, true);
    $iv = random_bytes(16);
    $ciphertext = openssl_encrypt($plaintext, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
    if ($ciphertext === false) {
        throw new RuntimeException('Verschl\u00fcsselung fehlgeschlagen.');
    }

    return base64_encode($iv . $ciphertext);
}

/**
 * Decrypt a value previously encrypted with encryptAppValue().
 * Returns empty string on any failure.
 */
function decryptAppValue(string $encoded): string
{
    $key = hash('sha256', APP_SECRET, true);
    $data = base64_decode($encoded, true);
    if ($data === false || strlen($data) < 17) {
        return '';
    }

    $iv = substr($data, 0, 16);
    $ciphertext = substr($data, 16);
    $plaintext = openssl_decrypt($ciphertext, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);

    return $plaintext === false ? '' : $plaintext;
}

function ensureColumnExists(PDO $db, string $table, string $column, string $definition): void
{
    $columns = [];
    $stmt = $db->query('PRAGMA table_info(' . $table . ')');
    foreach ($stmt->fetchAll() as $row) {
        $columns[] = (string) $row['name'];
    }

    if (!in_array($column, $columns, true)) {
        $db->exec('ALTER TABLE ' . $table . ' ADD COLUMN ' . $column . ' ' . $definition);
    }
}

function backfillLegacyMediaToTitlesAndCopies(PDO $db): void
{
    $hasLegacyMedia = (int) $db->query('SELECT COUNT(*) AS c FROM media')->fetch()['c'] > 0;
    if (!$hasLegacyMedia) {
        return;
    }

    $insertTitle = $db->prepare(
        'INSERT OR IGNORE INTO media_titles (id, name, type, location, created_at, updated_at)
         VALUES (:id, :name, :type, :location, :created_at, :updated_at)'
    );
    $insertCopy = $db->prepare(
        'INSERT OR IGNORE INTO media_copies (id, title_id, barcode, inventory_number, condition, created_at, updated_at)
         VALUES (:id, :title_id, :barcode, :inventory_number, :condition, :created_at, :updated_at)'
    );

    $rows = $db->query('SELECT * FROM media')->fetchAll();
    $now = gmdate('c');
    foreach ($rows as $row) {
        $id = (int) $row['id'];
        $name = trim((string) ($row['title'] ?? ''));
        if ($name === '') {
            $name = 'Medium #' . $id;
        }

        $insertTitle->execute([
            'id' => $id,
            'name' => $name,
            'type' => $row['type'] ?? null,
            'location' => $row['location'] ?? null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $barcode = trim((string) ($row['inventory_number'] ?? ''));
        $insertCopy->execute([
            'id' => $id,
            'title_id' => $id,
            'barcode' => $barcode === '' ? null : $barcode,
            'inventory_number' => $row['inventory_number'] ?? null,
            'condition' => $row['condition'] ?? null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}

function backfillBorrowersFromLegacyUsers(PDO $db): void
{
    $insert = $db->prepare(
        'INSERT OR IGNORE INTO borrowers (kind, external_id, vorname, nachname, display_name, is_active, updated_at)
         VALUES (:kind, :external_id, :vorname, :nachname, :display_name, :is_active, :updated_at)'
    );

    $rows = $db->query('SELECT id, username, is_active FROM users')->fetchAll();
    $now = gmdate('c');
    foreach ($rows as $row) {
        $username = trim((string) ($row['username'] ?? ''));
        $insert->execute([
            'kind' => 'legacy_user',
            'external_id' => 'user:' . (int) $row['id'],
            'vorname' => '',
            'nachname' => $username,
            'display_name' => $username,
            'is_active' => (int) ($row['is_active'] ?? 1),
            'updated_at' => $now,
        ]);
    }
}

function backfillLegacyLending(PDO $db): void
{
    $db->exec('UPDATE lending SET copy_id = media_id WHERE copy_id IS NULL AND media_id IS NOT NULL');
    $db->exec('UPDATE lending SET created_by_user_id = user_id WHERE created_by_user_id IS NULL AND user_id IS NOT NULL');
    $db->exec('UPDATE lending SET school_year = CAST(strftime("%Y", borrowed_at) AS INTEGER) WHERE school_year IS NULL AND borrowed_at IS NOT NULL');

    $db->exec(
        'UPDATE lending
         SET borrower_id = (
            SELECT b.id
            FROM borrowers b
            WHERE b.kind = "legacy_user"
              AND b.external_id = "user:" || lending.user_id
            LIMIT 1
         )
         WHERE borrower_id IS NULL AND user_id IS NOT NULL'
    );
}

function refreshBorrowersFromSvwsData(PDO $db): void
{
    $now = gmdate('c');

    $studentUpsert = $db->prepare(
        'INSERT INTO borrowers (kind, svws_id, vorname, nachname, display_name, klasse, raw_json, is_active, updated_at)
         VALUES (:kind, :svws_id, :vorname, :nachname, :display_name, :klasse, :raw_json, 1, :updated_at)
         ON CONFLICT(kind, svws_id) DO UPDATE SET
            vorname = excluded.vorname,
            nachname = excluded.nachname,
            display_name = excluded.display_name,
            klasse = excluded.klasse,
            raw_json = excluded.raw_json,
            is_active = 1,
            updated_at = excluded.updated_at'
    );

    $teacherUpsert = $db->prepare(
        'INSERT INTO borrowers (kind, svws_id, vorname, nachname, display_name, raw_json, is_active, updated_at)
         VALUES (:kind, :svws_id, :vorname, :nachname, :display_name, :raw_json, 1, :updated_at)
         ON CONFLICT(kind, svws_id) DO UPDATE SET
            vorname = excluded.vorname,
            nachname = excluded.nachname,
            display_name = excluded.display_name,
            raw_json = excluded.raw_json,
            is_active = 1,
            updated_at = excluded.updated_at'
    );

    foreach ($db->query('SELECT * FROM svws_students')->fetchAll() as $row) {
        $nachname = trim((string) ($row['nachname'] ?? ''));
        $vorname = trim((string) ($row['vorname'] ?? ''));
        $studentUpsert->execute([
            'kind' => 'student',
            'svws_id' => (int) $row['svws_id'],
            'vorname' => $vorname,
            'nachname' => $nachname,
            'display_name' => trim($nachname . ', ' . $vorname, ' ,'),
            'klasse' => $row['klasse'] ?? null,
            'raw_json' => $row['raw_json'] ?? null,
            'updated_at' => $now,
        ]);
    }

    foreach ($db->query('SELECT * FROM svws_teachers')->fetchAll() as $row) {
        $nachname = trim((string) ($row['nachname'] ?? ''));
        $vorname = trim((string) ($row['vorname'] ?? ''));
        $teacherUpsert->execute([
            'kind' => 'teacher',
            'svws_id' => (int) $row['svws_id'],
            'vorname' => $vorname,
            'nachname' => $nachname,
            'display_name' => trim($nachname . ', ' . $vorname, ' ,'),
            'raw_json' => $row['raw_json'] ?? null,
            'updated_at' => $now,
        ]);
    }

    $db->exec('DELETE FROM borrower_group_memberships WHERE borrower_id IN (SELECT id FROM borrowers WHERE kind IN ("student", "teacher"))');

    $studentMembershipInsert = $db->prepare(
        'INSERT OR IGNORE INTO borrower_group_memberships (borrower_id, group_id, role_in_group)
         VALUES (
            (SELECT id FROM borrowers WHERE kind = "student" AND svws_id = :svws_id LIMIT 1),
            :group_id,
            "student"
         )'
    );

    $teacherMembershipInsert = $db->prepare(
        'INSERT OR IGNORE INTO borrower_group_memberships (borrower_id, group_id, role_in_group)
         VALUES (
            (SELECT id FROM borrowers WHERE kind = "teacher" AND svws_id = :svws_id LIMIT 1),
            :group_id,
            "teacher"
         )'
    );

    $studentLinks = $db->query(
        'SELECT s.svws_id AS student_svws_id, sg.group_id AS group_id
         FROM svws_student_groups sg
         JOIN svws_students s ON s.id = sg.student_id'
    )->fetchAll();

    foreach ($studentLinks as $row) {
        $studentMembershipInsert->execute([
            'svws_id' => (int) $row['student_svws_id'],
            'group_id' => (int) $row['group_id'],
        ]);
    }

    $teacherLinks = $db->query(
        'SELECT t.svws_id AS teacher_svws_id, tg.group_id AS group_id
         FROM svws_teacher_groups tg
         JOIN svws_teachers t ON t.id = tg.teacher_id'
    )->fetchAll();

    foreach ($teacherLinks as $row) {
        $teacherMembershipInsert->execute([
            'svws_id' => (int) $row['teacher_svws_id'],
            'group_id' => (int) $row['group_id'],
        ]);
    }
}

function shouldBootstrapBorrowersFromSvwsData(PDO $db): bool
{
    $studentCount = (int) $db->query('SELECT COUNT(*) AS c FROM svws_students')->fetch()['c'];
    $teacherCount = (int) $db->query('SELECT COUNT(*) AS c FROM svws_teachers')->fetch()['c'];
    if ($studentCount === 0 && $teacherCount === 0) {
        return false;
    }

    $borrowerCount = (int) $db->query(
        'SELECT COUNT(*) AS c FROM borrowers WHERE kind IN ("student", "teacher")'
    )->fetch()['c'];

    return $borrowerCount === 0;
}

function getDB(): PDO
{
    if (!is_dir(DATA_PATH)) {
        mkdir(DATA_PATH, 0775, true);
    }

    $db = new PDO('sqlite:' . DB_PATH);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    initializeSchemaIfNeeded($db);

    return $db;
}
