<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/database.php';

class MediaService
{
    public static function getAll(string $search = ''): array
    {
        $db = getDB();
        $sql =
            'SELECT
                t.id,
                t.name AS title,
                t.type,
                t.location,
                MIN(c.inventory_number) AS inventory_number,
                MIN(c.condition) AS condition,
                COUNT(c.id) AS copy_count,
                SUM(CASE WHEN l.id IS NOT NULL THEN 1 ELSE 0 END) AS borrowed_count
            FROM media_titles t
            LEFT JOIN media_copies c ON c.title_id = t.id AND c.is_active = 1
            LEFT JOIN lending l ON l.copy_id = c.id AND l.returned_at IS NULL
            WHERE (:search = "" OR LOWER(t.name) LIKE :searchLike)
            GROUP BY t.id, t.name, t.type, t.location
            ORDER BY t.name';

        $stmt = $db->prepare($sql);
        $stmt->execute([
            'search' => $search,
            'searchLike' => '%' . mb_strtolower($search) . '%',
        ]);

        return $stmt->fetchAll();
    }

    public static function getById(int $id): ?array
    {
        $db = getDB();
        $stmt = $db->prepare(
            'SELECT
                t.id,
                t.name AS title,
                t.type,
                t.location,
                t.beleg_filter,
                t.created_at,
                t.updated_at,
                COUNT(c.id) AS copy_count,
                SUM(CASE WHEN l.id IS NOT NULL THEN 1 ELSE 0 END) AS borrowed_count
            FROM media_titles t
            LEFT JOIN media_copies c ON c.title_id = t.id AND c.is_active = 1
            LEFT JOIN lending l ON l.copy_id = c.id AND l.returned_at IS NULL
            WHERE t.id = :id
            GROUP BY t.id, t.name, t.type, t.location, t.beleg_filter, t.created_at, t.updated_at'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    public static function getCopiesByTitleId(int $titleId): array
    {
        $db = getDB();
        $stmt = $db->prepare(
            'SELECT
                c.id,
                c.title_id,
                c.barcode,
                c.inventory_number,
                c.condition,
                c.memo,
                c.is_active,
                c.created_at,
                c.updated_at,
                l.id AS open_lending_id,
                l.borrowed_at AS open_borrowed_at,
                b.display_name AS open_borrower_name
            FROM media_copies c
            LEFT JOIN lending l ON l.copy_id = c.id AND l.returned_at IS NULL
            LEFT JOIN borrowers b ON b.id = l.borrower_id
            WHERE c.title_id = :title_id
            ORDER BY c.barcode ASC, c.id ASC'
        );
        $stmt->execute(['title_id' => $titleId]);

        return $stmt->fetchAll();
    }

    public static function createTitle(string $title, ?string $type, ?string $location): int
    {
        $title = trim($title);
        if ($title === '') {
            throw new InvalidArgumentException('Titel darf nicht leer sein.');
        }

        $db = getDB();
        $stmt = $db->prepare(
            'INSERT INTO media_titles (name, type, location, created_at, updated_at)
             VALUES (:name, :type, :location, :created_at, :updated_at)'
        );
        $now = gmdate('c');
        $stmt->execute([
            'name' => $title,
            'type' => self::normalizeNullable($type),
            'location' => self::normalizeNullable($location),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return (int) $db->lastInsertId();
    }

    public static function updateTitle(int $titleId, string $title, ?string $type, ?string $location): void
    {
        $title = trim($title);
        if ($title === '') {
            throw new InvalidArgumentException('Titel darf nicht leer sein.');
        }

        $db = getDB();
        $stmt = $db->prepare(
            'UPDATE media_titles
             SET name = :name,
                 type = :type,
                 location = :location,
                 updated_at = :updated_at
             WHERE id = :id'
        );
        $stmt->execute([
            'id' => $titleId,
            'name' => $title,
            'type' => self::normalizeNullable($type),
            'location' => self::normalizeNullable($location),
            'updated_at' => gmdate('c'),
        ]);

        if ($stmt->rowCount() < 1) {
            throw new RuntimeException('Titel wurde nicht gefunden.');
        }
    }

    public static function deleteTitle(int $titleId): void
    {
        $db = getDB();

        $openCheck = $db->prepare(
            'SELECT COUNT(*) AS c
             FROM lending l
             JOIN media_copies c ON c.id = l.copy_id
             WHERE c.title_id = :title_id AND l.returned_at IS NULL'
        );
        $openCheck->execute(['title_id' => $titleId]);
        if ((int) $openCheck->fetch()['c'] > 0) {
            throw new RuntimeException('Titel hat noch offene Ausleihen und kann nicht geloescht werden.');
        }

        $historyCheck = $db->prepare(
            'SELECT COUNT(*) AS c
             FROM lending l
             JOIN media_copies c ON c.id = l.copy_id
             WHERE c.title_id = :title_id'
        );
        $historyCheck->execute(['title_id' => $titleId]);
        if ((int) $historyCheck->fetch()['c'] > 0) {
            throw new RuntimeException('Titel hat Ausleihhistorie und kann nicht geloescht werden.');
        }

        $stmt = $db->prepare('DELETE FROM media_titles WHERE id = :id');
        $stmt->execute(['id' => $titleId]);

        if ($stmt->rowCount() < 1) {
            throw new RuntimeException('Titel wurde nicht gefunden.');
        }
    }

    public static function createCopy(int $titleId, ?string $barcode, ?string $inventoryNumber, ?string $condition, ?string $memo): int
    {
        $db = getDB();
        $stmt = $db->prepare(
            'INSERT INTO media_copies (title_id, barcode, inventory_number, condition, memo, created_at, updated_at)
             VALUES (:title_id, :barcode, :inventory_number, :condition, :memo, :created_at, :updated_at)'
        );
        $now = gmdate('c');
        $stmt->execute([
            'title_id' => $titleId,
            'barcode' => self::normalizeNullable($barcode),
            'inventory_number' => self::normalizeNullable($inventoryNumber),
            'condition' => self::normalizeNullable($condition),
            'memo' => self::normalizeNullable($memo),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        self::touchTitle($db, $titleId);

        return (int) $db->lastInsertId();
    }

    public static function updateCopy(int $copyId, ?string $barcode, ?string $inventoryNumber, ?string $condition, ?string $memo, bool $isActive): void
    {
        $db = getDB();
        $titleQuery = $db->prepare('SELECT title_id FROM media_copies WHERE id = :id');
        $titleQuery->execute(['id' => $copyId]);
        $row = $titleQuery->fetch();
        if ($row === false) {
            throw new RuntimeException('Exemplar wurde nicht gefunden.');
        }

        $openCheck = $db->prepare('SELECT COUNT(*) AS c FROM lending WHERE copy_id = :copy_id AND returned_at IS NULL');
        $openCheck->execute(['copy_id' => $copyId]);
        if (!$isActive && (int) $openCheck->fetch()['c'] > 0) {
            throw new RuntimeException('Exemplar mit offener Ausleihe kann nicht deaktiviert werden.');
        }

        $stmt = $db->prepare(
            'UPDATE media_copies
             SET barcode = :barcode,
                 inventory_number = :inventory_number,
                 condition = :condition,
                 memo = :memo,
                 is_active = :is_active,
                 updated_at = :updated_at
             WHERE id = :id'
        );
        $stmt->execute([
            'id' => $copyId,
            'barcode' => self::normalizeNullable($barcode),
            'inventory_number' => self::normalizeNullable($inventoryNumber),
            'condition' => self::normalizeNullable($condition),
            'memo' => self::normalizeNullable($memo),
            'is_active' => $isActive ? 1 : 0,
            'updated_at' => gmdate('c'),
        ]);

        self::touchTitle($db, (int) $row['title_id']);
    }

    public static function deleteCopy(int $copyId): void
    {
        $db = getDB();

        $rowStmt = $db->prepare('SELECT title_id FROM media_copies WHERE id = :id');
        $rowStmt->execute(['id' => $copyId]);
        $row = $rowStmt->fetch();
        if ($row === false) {
            throw new RuntimeException('Exemplar wurde nicht gefunden.');
        }

        $openCheck = $db->prepare('SELECT COUNT(*) AS c FROM lending WHERE copy_id = :copy_id AND returned_at IS NULL');
        $openCheck->execute(['copy_id' => $copyId]);
        if ((int) $openCheck->fetch()['c'] > 0) {
            throw new RuntimeException('Exemplar hat offene Ausleihe und kann nicht geloescht werden.');
        }

        $historyCheck = $db->prepare('SELECT COUNT(*) AS c FROM lending WHERE copy_id = :copy_id');
        $historyCheck->execute(['copy_id' => $copyId]);
        if ((int) $historyCheck->fetch()['c'] > 0) {
            throw new RuntimeException('Exemplar hat Ausleihhistorie und kann nicht geloescht werden.');
        }

        $deleteStmt = $db->prepare('DELETE FROM media_copies WHERE id = :id');
        $deleteStmt->execute(['id' => $copyId]);

        if ($deleteStmt->rowCount() < 1) {
            throw new RuntimeException('Exemplar wurde nicht gefunden.');
        }

        self::touchTitle($db, (int) $row['title_id']);
    }

    public static function getInventoryReportRows(): array
    {
        $db = getDB();
        $stmt = $db->query(
            'SELECT
                t.id AS title_id,
                t.name AS title,
                t.type,
                t.location,
                COUNT(c.id) AS copies_total,
                SUM(CASE WHEN c.is_active = 1 THEN 1 ELSE 0 END) AS copies_active,
                SUM(CASE WHEN l.id IS NOT NULL THEN 1 ELSE 0 END) AS copies_lent,
                SUM(CASE WHEN c.is_active = 1 AND l.id IS NULL THEN 1 ELSE 0 END) AS copies_available
             FROM media_titles t
             LEFT JOIN media_copies c ON c.title_id = t.id
             LEFT JOIN lending l ON l.copy_id = c.id AND l.returned_at IS NULL
             GROUP BY t.id, t.name, t.type, t.location
             ORDER BY t.name'
        );

        return $stmt->fetchAll();
    }

    private static function touchTitle(PDO $db, int $titleId): void
    {
        $stmt = $db->prepare('UPDATE media_titles SET updated_at = :updated_at WHERE id = :id');
        $stmt->execute([
            'updated_at' => gmdate('c'),
            'id' => $titleId,
        ]);
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
