<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/database.php';

class LendingService
{
    public static function getOpenLendings(): array
    {
        $db = getDB();
        $sql = '
            SELECT
                l.id,
                l.copy_id AS media_id,
                l.borrower_id AS user_id,
                l.borrowed_at,
                l.status,
                t.name AS title,
                c.barcode,
                c.id AS copy_id,
                b.kind AS borrower_kind,
                COALESCE(b.display_name, TRIM(COALESCE(b.nachname, "") || ", " || COALESCE(b.vorname, ""), " ,")) AS borrower_name,
                l.school_year,
                l.klasse_snapshot,
                l.kurs_snapshot
            FROM lending l
            JOIN media_copies c ON c.id = l.copy_id
            JOIN media_titles t ON t.id = c.title_id
            LEFT JOIN borrowers b ON b.id = l.borrower_id
            WHERE l.returned_at IS NULL
            ORDER BY l.borrowed_at DESC
        ';

        return $db->query($sql)->fetchAll();
    }

    public static function getBorrowers(string $search = '', int $limit = 50): array
    {
        $db = getDB();
        $limit = max(1, min(200, $limit));

        $stmt = $db->prepare(
            'SELECT
                b.id,
                b.kind,
                b.vorname,
                b.nachname,
                b.display_name,
                b.klasse,
                b.jahrgang,
                b.is_blocked,
                b.is_active
             FROM borrowers b
             WHERE b.is_active = 1
               AND (
                    :search = ""
                    OR LOWER(COALESCE(b.display_name, "")) LIKE :search_like
                    OR LOWER(COALESCE(b.nachname, "")) LIKE :search_like
                    OR LOWER(COALESCE(b.vorname, "")) LIKE :search_like
                    OR LOWER(COALESCE(b.klasse, "")) LIKE :search_like
               )
             ORDER BY b.kind, b.nachname, b.vorname
             LIMIT ' . $limit
        );

        $stmt->execute([
            'search' => $search,
            'search_like' => '%' . mb_strtolower($search) . '%',
        ]);

        return $stmt->fetchAll();
    }

    public static function getBorrowerById(int $borrowerId): ?array
    {
        if ($borrowerId <= 0) {
            return null;
        }

        $db = getDB();
        $stmt = $db->prepare(
            'SELECT id, kind, vorname, nachname, display_name, klasse, jahrgang, memo, is_blocked, is_active
             FROM borrowers
             WHERE id = :id
             LIMIT 1'
        );
        $stmt->execute(['id' => $borrowerId]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    public static function getTitles(string $search = '', int $limit = 100): array
    {
        $db = getDB();
        $limit = max(1, min(200, $limit));

        $stmt = $db->prepare(
            'SELECT
                t.id,
                t.name,
                t.type,
                COUNT(c.id) AS copy_count
             FROM media_titles t
             LEFT JOIN media_copies c ON c.title_id = t.id AND c.is_active = 1
             WHERE :search = "" OR LOWER(t.name) LIKE :search_like
             GROUP BY t.id, t.name, t.type
             ORDER BY t.name
             LIMIT ' . $limit
        );

        $stmt->execute([
            'search' => $search,
            'search_like' => '%' . mb_strtolower($search) . '%',
        ]);

        return $stmt->fetchAll();
    }

    public static function processScan(string $barcode, int $borrowerId, int $actorUserId, array $snapshots = []): array
    {
        $barcode = trim($barcode);
        if ($barcode === '') {
            throw new InvalidArgumentException('Barcode darf nicht leer sein.');
        }

        $copy = self::findCopyByBarcode($barcode);
        if ($copy === null) {
            return [
                'status' => 'unknown_barcode',
                'barcode' => $barcode,
                'message' => 'Barcode ist unbekannt und muss zuerst einem Titel zugeordnet werden.',
            ];
        }

        $open = self::getOpenLendingByCopyId((int) $copy['id']);
        if ($open !== null) {
            $returned = self::returnByBarcode($barcode, $actorUserId);

            return [
                'status' => 'returned',
                'barcode' => $barcode,
                'title' => (string) $copy['title_name'],
                'message' => 'Rueckgabe verbucht.',
                'returned_lending_id' => $returned['lending_id'] ?? null,
            ];
        }

        $lending = self::lendByBarcode($barcode, $borrowerId, $actorUserId, $snapshots);

        return [
            'status' => 'lent',
            'barcode' => $barcode,
            'title' => (string) $copy['title_name'],
            'message' => 'Ausleihe verbucht.',
            'lending_id' => $lending['lending_id'] ?? null,
        ];
    }

    public static function assignUnknownBarcode(
        string $barcode,
        int $titleId,
        ?string $inventoryNumber,
        ?string $condition,
        ?string $memo,
        int $borrowerId,
        int $actorUserId,
        array $snapshots = []
    ): array {
        $barcode = trim($barcode);
        if ($barcode === '') {
            throw new InvalidArgumentException('Barcode darf nicht leer sein.');
        }
        if ($titleId <= 0) {
            throw new InvalidArgumentException('Titel ist ungueltig.');
        }

        $db = getDB();
        $existing = self::findCopyByBarcode($barcode);
        if ($existing === null) {
            $insert = $db->prepare(
                'INSERT INTO media_copies (title_id, barcode, inventory_number, condition, memo, is_active, created_at, updated_at)
                 VALUES (:title_id, :barcode, :inventory_number, :condition, :memo, 1, :created_at, :updated_at)'
            );
            $now = gmdate('c');
            $insert->execute([
                'title_id' => $titleId,
                'barcode' => $barcode,
                'inventory_number' => self::normalizeNullable($inventoryNumber),
                'condition' => self::normalizeNullable($condition),
                'memo' => self::normalizeNullable($memo),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $lending = self::lendByBarcode($barcode, $borrowerId, $actorUserId, $snapshots);

        return [
            'status' => 'assigned_and_lent',
            'barcode' => $barcode,
            'message' => 'Barcode wurde zugeordnet und ausgeliehen.',
            'lending_id' => $lending['lending_id'] ?? null,
        ];
    }

    public static function lendByBarcode(string $barcode, int $borrowerId, int $actorUserId, array $snapshots = []): array
    {
        if ($borrowerId <= 0) {
            throw new InvalidArgumentException('Bitte einen Ausleiher auswaehlen.');
        }

        $borrower = self::getBorrowerById($borrowerId);
        if ($borrower === null || (int) $borrower['is_active'] !== 1) {
            throw new RuntimeException('Ausleiher nicht gefunden oder inaktiv.');
        }
        if ((int) $borrower['is_blocked'] === 1) {
            throw new RuntimeException('Ausleiher ist gesperrt.');
        }

        $copy = self::findCopyByBarcode($barcode);
        if ($copy === null) {
            throw new RuntimeException('Barcode ist unbekannt.');
        }
        if ((int) $copy['is_active'] !== 1) {
            throw new RuntimeException('Exemplar ist inaktiv.');
        }

        $open = self::getOpenLendingByCopyId((int) $copy['id']);
        if ($open !== null) {
            throw new RuntimeException('Exemplar ist bereits verliehen.');
        }

        $db = getDB();
        $stmt = $db->prepare(
            'INSERT INTO lending (
                media_id,
                user_id,
                copy_id,
                borrower_id,
                borrowed_at,
                returned_at,
                status,
                school_year,
                klasse_snapshot,
                kurs_snapshot,
                kurs_lehrer_snapshot,
                created_by_user_id
            ) VALUES (
                :media_id,
                :user_id,
                :copy_id,
                :borrower_id,
                :borrowed_at,
                NULL,
                :status,
                :school_year,
                :klasse_snapshot,
                :kurs_snapshot,
                :kurs_lehrer_snapshot,
                :created_by_user_id
            )'
        );
        $now = gmdate('c');
        $schoolYear = isset($snapshots['school_year']) && is_numeric($snapshots['school_year'])
            ? (int) $snapshots['school_year']
            : (int) gmdate('Y');
        $klasse = self::normalizeNullable((string) ($snapshots['klasse'] ?? ($borrower['klasse'] ?? '')));

        $stmt->execute([
            'media_id' => (int) $copy['id'],
            'user_id' => $actorUserId,
            'copy_id' => (int) $copy['id'],
            'borrower_id' => $borrowerId,
            'borrowed_at' => $now,
            'status' => 'borrowed',
            'school_year' => $schoolYear,
            'klasse_snapshot' => $klasse,
            'kurs_snapshot' => self::normalizeNullable((string) ($snapshots['kurs'] ?? '')),
            'kurs_lehrer_snapshot' => self::normalizeNullable((string) ($snapshots['kurs_lehrer'] ?? '')),
            'created_by_user_id' => $actorUserId,
        ]);

        return [
            'lending_id' => (int) $db->lastInsertId(),
            'borrowed_at' => $now,
        ];
    }

    public static function returnByBarcode(string $barcode, int $actorUserId): array
    {
        $copy = self::findCopyByBarcode($barcode);
        if ($copy === null) {
            throw new RuntimeException('Barcode ist unbekannt.');
        }

        $open = self::getOpenLendingByCopyId((int) $copy['id']);
        if ($open === null) {
            throw new RuntimeException('Exemplar ist derzeit nicht ausgeliehen.');
        }

        $db = getDB();
        $stmt = $db->prepare(
            'UPDATE lending
             SET returned_at = :returned_at,
                 status = :status,
                 user_id = :user_id
             WHERE id = :id'
        );
        $now = gmdate('c');
        $stmt->execute([
            'returned_at' => $now,
            'status' => 'returned',
            'user_id' => $actorUserId,
            'id' => (int) $open['id'],
        ]);

        return [
            'lending_id' => (int) $open['id'],
            'returned_at' => $now,
        ];
    }

    public static function findCopyByBarcode(string $barcode): ?array
    {
        $barcode = trim($barcode);
        if ($barcode === '') {
            return null;
        }

        $db = getDB();
        $stmt = $db->prepare(
            'SELECT
                c.id,
                c.title_id,
                c.barcode,
                c.is_active,
                t.name AS title_name
             FROM media_copies c
             JOIN media_titles t ON t.id = c.title_id
             WHERE c.barcode = :barcode
             LIMIT 1'
        );
        $stmt->execute(['barcode' => $barcode]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    public static function getOpenLendingByCopyId(int $copyId): ?array
    {
        $db = getDB();
        $stmt = $db->prepare(
            'SELECT
                l.id,
                l.copy_id,
                l.borrower_id,
                l.borrowed_at,
                b.display_name,
                b.nachname,
                b.vorname
             FROM lending l
             LEFT JOIN borrowers b ON b.id = l.borrower_id
             WHERE l.copy_id = :copy_id AND l.returned_at IS NULL
             LIMIT 1'
        );
        $stmt->execute(['copy_id' => $copyId]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    public static function getGroups(string $search = '', int $limit = 80): array
    {
        $db = getDB();
        $limit = max(1, min(200, $limit));

        $stmt = $db->prepare(
            'SELECT
                g.id,
                g.svws_id,
                g.kuerzel,
                g.name,
                g.jahrgang,
                COUNT(bgm.borrower_id) AS member_count
             FROM svws_groups g
             LEFT JOIN borrower_group_memberships bgm ON bgm.group_id = g.id
             LEFT JOIN borrowers b ON b.id = bgm.borrower_id AND b.is_active = 1
             WHERE :search = ""
                OR LOWER(COALESCE(g.name, "")) LIKE :search_like
                OR LOWER(COALESCE(g.kuerzel, "")) LIKE :search_like
                OR LOWER(COALESCE(g.jahrgang, "")) LIKE :search_like
             GROUP BY g.id, g.svws_id, g.kuerzel, g.name, g.jahrgang
             ORDER BY g.jahrgang, g.name, g.kuerzel
             LIMIT ' . $limit
        );
        $stmt->execute([
            'search' => $search,
            'search_like' => '%' . mb_strtolower($search) . '%',
        ]);

        return $stmt->fetchAll();
    }

    public static function getGroupById(int $groupId): ?array
    {
        if ($groupId <= 0) {
            return null;
        }

        $db = getDB();
        $stmt = $db->prepare('SELECT id, svws_id, kuerzel, name, jahrgang FROM svws_groups WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $groupId]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    public static function getGroupMembers(int $groupId): array
    {
        if ($groupId <= 0) {
            return [];
        }

        $db = getDB();
        $stmt = $db->prepare(
            'SELECT
                b.id,
                b.kind,
                b.vorname,
                b.nachname,
                b.display_name,
                b.klasse,
                b.jahrgang,
                b.is_blocked,
                b.is_active,
                bgm.role_in_group
             FROM borrower_group_memberships bgm
             JOIN borrowers b ON b.id = bgm.borrower_id
             WHERE bgm.group_id = :group_id
               AND b.is_active = 1
             ORDER BY b.nachname, b.vorname, b.id'
        );
        $stmt->execute(['group_id' => $groupId]);

        return $stmt->fetchAll();
    }

    public static function processGroupScan(string $barcode, int $groupId, int $titleId, int $actorUserId, array $snapshots = []): array
    {
        $barcode = trim($barcode);
        if ($barcode === '') {
            throw new InvalidArgumentException('Barcode darf nicht leer sein.');
        }
        if ($groupId <= 0) {
            throw new InvalidArgumentException('Bitte eine Gruppe auswaehlen.');
        }
        if ($titleId <= 0) {
            throw new InvalidArgumentException('Bitte einen Titel auswaehlen.');
        }

        $group = self::getGroupById($groupId);
        if ($group === null) {
            throw new RuntimeException('Gruppe wurde nicht gefunden.');
        }

        $copy = self::findCopyByBarcode($barcode);
        if ($copy === null) {
            return [
                'status' => 'unknown_barcode',
                'barcode' => $barcode,
                'message' => 'Barcode ist unbekannt.',
            ];
        }

        if ((int) $copy['title_id'] !== $titleId) {
            throw new RuntimeException('Falscher Titel zum Kurslauf. Erwartet wird der ausgewaehlte Titel.');
        }

        $open = self::getOpenLendingByCopyId((int) $copy['id']);
        if ($open !== null) {
            self::returnByBarcode($barcode, $actorUserId);

            return [
                'status' => 'returned',
                'message' => 'Rueckgabe im Gruppenmodus verbucht.',
                'barcode' => $barcode,
            ];
        }

        $schoolYear = isset($snapshots['school_year']) && is_numeric($snapshots['school_year'])
            ? (int) $snapshots['school_year']
            : (int) gmdate('Y');
        $nextBorrower = self::getNextGroupBorrowerForTitle($groupId, $titleId, $schoolYear);
        if ($nextBorrower === null) {
            throw new RuntimeException('Die Gruppe ist fuer diesen Titel bereits vollstaendig versorgt.');
        }

        $result = self::lendByBarcode(
            $barcode,
            (int) $nextBorrower['id'],
            $actorUserId,
            [
                'school_year' => $schoolYear,
                'klasse' => (string) ($nextBorrower['klasse'] ?? ''),
                'kurs' => (string) ($group['name'] ?: $group['kuerzel']),
                'kurs_lehrer' => (string) ($snapshots['kurs_lehrer'] ?? ''),
            ]
        );

        return [
            'status' => 'lent',
            'barcode' => $barcode,
            'borrower_id' => (int) $nextBorrower['id'],
            'borrower_name' => self::formatBorrowerName($nextBorrower),
            'lending_id' => $result['lending_id'] ?? null,
            'message' => 'Ausleihe im Gruppenmodus verbucht.',
        ];
    }

    public static function assignUnknownBarcodeToGroup(
        string $barcode,
        int $groupId,
        int $titleId,
        ?string $inventoryNumber,
        ?string $condition,
        ?string $memo,
        int $actorUserId,
        array $snapshots = []
    ): array {
        if ($groupId <= 0 || $titleId <= 0) {
            throw new InvalidArgumentException('Gruppe und Titel sind erforderlich.');
        }

        $group = self::getGroupById($groupId);
        if ($group === null) {
            throw new RuntimeException('Gruppe wurde nicht gefunden.');
        }

        $schoolYear = isset($snapshots['school_year']) && is_numeric($snapshots['school_year'])
            ? (int) $snapshots['school_year']
            : (int) gmdate('Y');
        $nextBorrower = self::getNextGroupBorrowerForTitle($groupId, $titleId, $schoolYear);
        if ($nextBorrower === null) {
            throw new RuntimeException('Die Gruppe ist fuer diesen Titel bereits vollstaendig versorgt.');
        }

        return self::assignUnknownBarcode(
            $barcode,
            $titleId,
            $inventoryNumber,
            $condition,
            $memo,
            (int) $nextBorrower['id'],
            $actorUserId,
            [
                'school_year' => $schoolYear,
                'klasse' => (string) ($nextBorrower['klasse'] ?? ''),
                'kurs' => (string) (($group['name'] ?: $group['kuerzel']) ?? ''),
                'kurs_lehrer' => (string) ($snapshots['kurs_lehrer'] ?? ''),
            ]
        );
    }

    public static function getNextGroupBorrowerForTitle(int $groupId, int $titleId, int $schoolYear): ?array
    {
        $db = getDB();
        $stmt = $db->prepare(
            'SELECT
                b.id,
                b.kind,
                b.vorname,
                b.nachname,
                b.display_name,
                b.klasse,
                b.is_blocked,
                b.is_active,
                EXISTS(
                    SELECT 1
                    FROM lending l
                    JOIN media_copies c ON c.id = l.copy_id
                    WHERE l.borrower_id = b.id
                      AND c.title_id = :title_id
                      AND l.returned_at IS NULL
                ) AS has_open_title
             FROM borrower_group_memberships bgm
             JOIN borrowers b ON b.id = bgm.borrower_id
             WHERE bgm.group_id = :group_id
               AND b.is_active = 1
               AND b.is_blocked = 0
             ORDER BY b.nachname, b.vorname, b.id'
        );
        $stmt->execute([
            'group_id' => $groupId,
            'title_id' => $titleId,
        ]);

        foreach ($stmt->fetchAll() as $row) {
            if ((int) ($row['has_open_title'] ?? 0) === 1) {
                continue;
            }

            return $row;
        }

        return null;
    }

    public static function getBorrowerDirectory(string $search = '', int $limit = 120): array
    {
        $db = getDB();
        $limit = max(1, min(300, $limit));

        $stmt = $db->prepare(
            'SELECT
                b.id,
                b.kind,
                b.vorname,
                b.nachname,
                b.display_name,
                b.klasse,
                b.jahrgang,
                b.memo,
                b.is_blocked,
                b.is_active,
                SUM(CASE WHEN l.id IS NOT NULL AND l.returned_at IS NULL THEN 1 ELSE 0 END) AS open_lending_count
             FROM borrowers b
             LEFT JOIN lending l ON l.borrower_id = b.id
             WHERE :search = ""
                OR LOWER(COALESCE(b.display_name, "")) LIKE :search_like
                OR LOWER(COALESCE(b.nachname, "")) LIKE :search_like
                OR LOWER(COALESCE(b.vorname, "")) LIKE :search_like
                OR LOWER(COALESCE(b.klasse, "")) LIKE :search_like
             GROUP BY b.id, b.kind, b.vorname, b.nachname, b.display_name, b.klasse, b.jahrgang, b.memo, b.is_blocked, b.is_active
             ORDER BY b.kind, b.nachname, b.vorname
             LIMIT ' . $limit
        );
        $stmt->execute([
            'search' => $search,
            'search_like' => '%' . mb_strtolower($search) . '%',
        ]);

        return $stmt->fetchAll();
    }

    public static function updateBorrowerMemo(int $borrowerId, ?string $memo): void
    {
        if ($borrowerId <= 0) {
            throw new InvalidArgumentException('Ungueltiger Ausleiher.');
        }

        $db = getDB();
        $stmt = $db->prepare('UPDATE borrowers SET memo = :memo, updated_at = :updated_at WHERE id = :id');
        $stmt->execute([
            'memo' => self::normalizeNullable($memo),
            'updated_at' => gmdate('c'),
            'id' => $borrowerId,
        ]);
    }

    public static function setBorrowerBlocked(int $borrowerId, bool $blocked): void
    {
        if ($borrowerId <= 0) {
            throw new InvalidArgumentException('Ungueltiger Ausleiher.');
        }

        $db = getDB();
        $stmt = $db->prepare('UPDATE borrowers SET is_blocked = :blocked, updated_at = :updated_at WHERE id = :id');
        $stmt->execute([
            'blocked' => $blocked ? 1 : 0,
            'updated_at' => gmdate('c'),
            'id' => $borrowerId,
        ]);
    }

    public static function migrateBorrowerLendings(int $sourceBorrowerId, int $targetBorrowerId): int
    {
        if ($sourceBorrowerId <= 0 || $targetBorrowerId <= 0 || $sourceBorrowerId === $targetBorrowerId) {
            throw new InvalidArgumentException('Ungueltige Migration.');
        }

        $source = self::getBorrowerById($sourceBorrowerId);
        $target = self::getBorrowerById($targetBorrowerId);
        if ($source === null || $target === null) {
            throw new RuntimeException('Ausleiher nicht gefunden.');
        }

        $db = getDB();
        $db->beginTransaction();
        try {
            $checkStmt = $db->prepare(
                'SELECT COUNT(*) AS c
                 FROM lending ls
                 JOIN lending lt ON lt.copy_id = ls.copy_id AND lt.returned_at IS NULL
                 WHERE ls.borrower_id = :source_id
                   AND ls.returned_at IS NULL
                   AND lt.borrower_id = :target_id'
            );
            $checkStmt->execute([
                'source_id' => $sourceBorrowerId,
                'target_id' => $targetBorrowerId,
            ]);
            if ((int) $checkStmt->fetch()['c'] > 0) {
                throw new RuntimeException('Migration nicht moeglich: Ziel hat bereits offene Ausleihe auf mindestens ein Exemplar.');
            }

            $update = $db->prepare('UPDATE lending SET borrower_id = :target_id WHERE borrower_id = :source_id');
            $update->execute([
                'target_id' => $targetBorrowerId,
                'source_id' => $sourceBorrowerId,
            ]);

            $markSource = $db->prepare('UPDATE borrowers SET is_active = 0, updated_at = :updated_at WHERE id = :id');
            $markSource->execute([
                'id' => $sourceBorrowerId,
                'updated_at' => gmdate('c'),
            ]);

            $db->commit();

            return (int) $update->rowCount();
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    public static function getBorrowersWithPriorYearOpenLendings(int $currentYear): array
    {
        $db = getDB();
        $stmt = $db->prepare(
            'SELECT
                b.id,
                b.kind,
                b.display_name,
                b.nachname,
                b.vorname,
                b.klasse,
                COUNT(l.id) AS open_old_count,
                MIN(l.school_year) AS oldest_year
             FROM lending l
             JOIN borrowers b ON b.id = l.borrower_id
             WHERE l.returned_at IS NULL
               AND COALESCE(l.school_year, 0) < :current_year
               AND b.is_active = 1
             GROUP BY b.id, b.kind, b.display_name, b.nachname, b.vorname, b.klasse
             ORDER BY open_old_count DESC, b.nachname, b.vorname'
        );
        $stmt->execute(['current_year' => $currentYear]);

        return $stmt->fetchAll();
    }

    public static function getBlockedBorrowers(): array
    {
        $db = getDB();
        $stmt = $db->query(
            'SELECT
                b.id,
                b.kind,
                b.display_name,
                b.nachname,
                b.vorname,
                b.klasse,
                b.memo
             FROM borrowers b
             WHERE b.is_active = 1
               AND b.is_blocked = 1
             ORDER BY b.nachname, b.vorname'
        );

        return $stmt->fetchAll();
    }

    public static function formatBorrowerName(array $borrower): string
    {
        $display = trim((string) ($borrower['display_name'] ?? ''));
        if ($display !== '') {
            return $display;
        }

        return trim((string) ($borrower['nachname'] ?? '') . ', ' . (string) ($borrower['vorname'] ?? ''), ' ,');
    }

    public static function getBorrowerStatementRows(int $borrowerId, bool $includeReturned = false): array
    {
        if ($borrowerId <= 0) {
            return [];
        }

        $db = getDB();
        $sql =
            'SELECT
                l.id,
                t.name AS title,
                c.barcode,
                c.inventory_number,
                l.borrowed_at,
                l.returned_at,
                l.school_year,
                l.klasse_snapshot,
                l.kurs_snapshot,
                l.kurs_lehrer_snapshot,
                l.status
             FROM lending l
             JOIN media_copies c ON c.id = l.copy_id
             JOIN media_titles t ON t.id = c.title_id
             WHERE l.borrower_id = :borrower_id';

        if (!$includeReturned) {
            $sql .= ' AND l.returned_at IS NULL';
        }

        $sql .= ' ORDER BY l.borrowed_at DESC, l.id DESC';

        $stmt = $db->prepare($sql);
        $stmt->execute(['borrower_id' => $borrowerId]);

        return $stmt->fetchAll();
    }

    public static function getGroupDistributionSheetRows(int $groupId, int $titleId): array
    {
        if ($groupId <= 0 || $titleId <= 0) {
            return [];
        }

        $db = getDB();
        $stmt = $db->prepare(
            'SELECT
                b.id AS borrower_id,
                b.kind,
                b.vorname,
                b.nachname,
                b.display_name,
                b.klasse,
                b.is_blocked,
                l.id AS lending_id,
                l.borrowed_at,
                c.barcode,
                c.inventory_number,
                c.id AS copy_id,
                t.name AS title
             FROM borrower_group_memberships bgm
             JOIN borrowers b ON b.id = bgm.borrower_id
             JOIN media_titles t ON t.id = :title_id
             LEFT JOIN lending l ON l.borrower_id = b.id AND l.returned_at IS NULL
                AND l.copy_id IN (SELECT id FROM media_copies WHERE title_id = :title_id)
             LEFT JOIN media_copies c ON c.id = l.copy_id
             WHERE bgm.group_id = :group_id
               AND b.is_active = 1
             ORDER BY b.nachname, b.vorname, b.id'
        );
        $stmt->execute([
            'group_id' => $groupId,
            'title_id' => $titleId,
        ]);

        return $stmt->fetchAll();
    }

    private static function normalizeNullable(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
