<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/database.php';

class SvwsDataService
{
    public static function getStudents(
        string $search = '',
        int $limit = 300,
        int $offset = 0,
        string $sort = 'nachname',
        string $direction = 'asc'
    ): array
    {
        $db = getDB();
        $sortMap = [
            'svws_id' => 's.svws_id',
            'nachname' => 's.nachname',
            'vorname' => 's.vorname',
            'klasse' => 's.klasse',
            'status' => 's.status',
        ];
        $orderBy = $sortMap[$sort] ?? 's.nachname';
        $orderDir = strtolower($direction) === 'desc' ? 'DESC' : 'ASC';

        $sql = '
            SELECT
                s.id,
                s.svws_id,
                s.nachname,
                s.vorname,
                s.klasse,
                s.status,
                s.email,
                b.id AS borrower_id
            FROM svws_students s
            LEFT JOIN borrowers b ON b.kind = "student" AND b.svws_id = s.svws_id
            WHERE (:search = "" OR s.nachname LIKE :searchLike OR s.vorname LIKE :searchLike OR s.klasse LIKE :searchLike)
            ORDER BY ' . $orderBy . ' ' . $orderDir . ', s.nachname, s.vorname
            LIMIT :limit OFFSET :offset
        ';

        $stmt = $db->prepare($sql);
        $stmt->bindValue(':search', $search, PDO::PARAM_STR);
        $stmt->bindValue(':searchLike', '%' . $search . '%', PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public static function getStudentsCount(string $search = ''): int
    {
        $db = getDB();
        $stmt = $db->prepare(
            'SELECT COUNT(*) AS c
             FROM svws_students s
             WHERE (:search = "" OR s.nachname LIKE :searchLike OR s.vorname LIKE :searchLike OR s.klasse LIKE :searchLike)'
        );
        $stmt->bindValue(':search', $search, PDO::PARAM_STR);
        $stmt->bindValue(':searchLike', '%' . $search . '%', PDO::PARAM_STR);
        $stmt->execute();

        return (int) ($stmt->fetch()['c'] ?? 0);
    }

    public static function getTeachers(
        string $search = '',
        int $limit = 300,
        int $offset = 0,
        string $sort = 'nachname',
        string $direction = 'asc'
    ): array
    {
        $db = getDB();
        $sortMap = [
            'svws_id' => 't.svws_id',
            'kuerzel' => 't.kuerzel',
            'nachname' => 't.nachname',
            'vorname' => 't.vorname',
            'email' => 't.email',
        ];
        $orderBy = $sortMap[$sort] ?? 't.nachname';
        $orderDir = strtolower($direction) === 'desc' ? 'DESC' : 'ASC';

        $sql = '
            SELECT
                t.id,
                t.svws_id,
                t.kuerzel,
                t.nachname,
                t.vorname,
                t.email,
                b.id AS borrower_id
            FROM svws_teachers t
            LEFT JOIN borrowers b ON b.kind = "teacher" AND b.svws_id = t.svws_id
            WHERE (:search = "" OR t.kuerzel LIKE :searchLike OR t.nachname LIKE :searchLike OR t.vorname LIKE :searchLike)
            ORDER BY ' . $orderBy . ' ' . $orderDir . ', t.nachname, t.vorname
            LIMIT :limit OFFSET :offset
        ';

        $stmt = $db->prepare($sql);
        $stmt->bindValue(':search', $search, PDO::PARAM_STR);
        $stmt->bindValue(':searchLike', '%' . $search . '%', PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public static function getTeachersCount(string $search = ''): int
    {
        $db = getDB();
        $stmt = $db->prepare(
            'SELECT COUNT(*) AS c
             FROM svws_teachers t
             WHERE (:search = "" OR t.kuerzel LIKE :searchLike OR t.nachname LIKE :searchLike OR t.vorname LIKE :searchLike)'
        );
        $stmt->bindValue(':search', $search, PDO::PARAM_STR);
        $stmt->bindValue(':searchLike', '%' . $search . '%', PDO::PARAM_STR);
        $stmt->execute();

        return (int) ($stmt->fetch()['c'] ?? 0);
    }

    public static function getClasses(
        string $search = '',
        int $limit = 300,
        int $offset = 0,
        string $sort = 'kuerzel',
        string $direction = 'asc'
    ): array
    {
        $db = getDB();
        $sortMap = [
            'svws_id' => 'c.svws_id',
            'kuerzel' => 'c.kuerzel',
            'name' => 'c.name',
            'jahrgang' => 'c.jahrgang',
        ];
        $orderBy = $sortMap[$sort] ?? 'c.kuerzel';
        $orderDir = strtolower($direction) === 'desc' ? 'DESC' : 'ASC';

        $sql = '
            SELECT
                c.id,
                c.svws_id,
                c.kuerzel,
                c.name,
                c.jahrgang
            FROM svws_classes c
            WHERE (:search = "" OR c.kuerzel LIKE :searchLike OR c.name LIKE :searchLike OR c.jahrgang LIKE :searchLike)
            ORDER BY ' . $orderBy . ' ' . $orderDir . ', c.kuerzel, c.name
            LIMIT :limit OFFSET :offset
        ';

        $stmt = $db->prepare($sql);
        $stmt->bindValue(':search', $search, PDO::PARAM_STR);
        $stmt->bindValue(':searchLike', '%' . $search . '%', PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public static function getClassesCount(string $search = ''): int
    {
        $db = getDB();
        $stmt = $db->prepare(
            'SELECT COUNT(*) AS c
             FROM svws_classes c
             WHERE (:search = "" OR c.kuerzel LIKE :searchLike OR c.name LIKE :searchLike OR c.jahrgang LIKE :searchLike)'
        );
        $stmt->bindValue(':search', $search, PDO::PARAM_STR);
        $stmt->bindValue(':searchLike', '%' . $search . '%', PDO::PARAM_STR);
        $stmt->execute();

        return (int) ($stmt->fetch()['c'] ?? 0);
    }

    public static function getGroups(
        string $search = '',
        int $limit = 300,
        int $offset = 0,
        string $sort = 'kuerzel',
        string $direction = 'asc'
    ): array
    {
        $db = getDB();
        $sortMap = [
            'svws_id' => 'g.svws_id',
            'kuerzel' => 'g.kuerzel',
            'name' => 'g.name',
            'jahrgang' => 'g.jahrgang',
        ];
        $orderBy = $sortMap[$sort] ?? 'g.kuerzel';
        $orderDir = strtolower($direction) === 'desc' ? 'DESC' : 'ASC';

        $sql = '
            SELECT
                g.id,
                g.svws_id,
                g.kuerzel,
                g.name,
                g.jahrgang,
                g.raw_json
            FROM svws_groups g
            WHERE (:search = "" OR g.kuerzel LIKE :searchLike OR g.name LIKE :searchLike OR g.jahrgang LIKE :searchLike)
            ORDER BY ' . $orderBy . ' ' . $orderDir . ', g.kuerzel, g.name
            LIMIT :limit OFFSET :offset
        ';

        $stmt = $db->prepare($sql);
        $stmt->bindValue(':search', $search, PDO::PARAM_STR);
        $stmt->bindValue(':searchLike', '%' . $search . '%', PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll();
        foreach ($rows as &$row) {
            $row['unterrichtstyp'] = self::detectGroupType((string) ($row['raw_json'] ?? ''));
            unset($row['raw_json']);
        }
        unset($row);

        return $rows;
    }

    public static function getGroupsCount(string $search = ''): int
    {
        $db = getDB();
        $stmt = $db->prepare(
            'SELECT COUNT(*) AS c
             FROM svws_groups g
             WHERE (:search = "" OR g.kuerzel LIKE :searchLike OR g.name LIKE :searchLike OR g.jahrgang LIKE :searchLike)'
        );
        $stmt->bindValue(':search', $search, PDO::PARAM_STR);
        $stmt->bindValue(':searchLike', '%' . $search . '%', PDO::PARAM_STR);
        $stmt->execute();

        return (int) ($stmt->fetch()['c'] ?? 0);
    }

    public static function getOverviewCounts(): array
    {
        $db = getDB();

        return [
            'students' => (int) $db->query('SELECT COUNT(*) AS c FROM svws_students')->fetch()['c'],
            'classes' => (int) $db->query('SELECT COUNT(*) AS c FROM svws_classes')->fetch()['c'],
            'teachers' => (int) $db->query('SELECT COUNT(*) AS c FROM svws_teachers')->fetch()['c'],
            'groups' => (int) $db->query('SELECT COUNT(*) AS c FROM svws_groups')->fetch()['c'],
        ];
    }

    private static function detectGroupType(string $rawJson): string
    {
        $decoded = json_decode($rawJson, true);
        if (!is_array($decoded)) {
            return 'Lerngruppe';
        }

        if (self::isTruthy($decoded['istKlassenunterricht'] ?? null)
            || self::isTruthy($decoded['klassenunterricht'] ?? null)
            || self::isTruthy($decoded['isKlassenunterricht'] ?? null)
            || isset($decoded['idKlasse'])
            || isset($decoded['klasseId'])
            || isset($decoded['idKlassenunterricht'])) {
            return 'Klassenunterricht';
        }

        if (self::isTruthy($decoded['istKursunterricht'] ?? null)
            || self::isTruthy($decoded['kursunterricht'] ?? null)
            || self::isTruthy($decoded['isKursunterricht'] ?? null)
            || isset($decoded['idKurs'])
            || isset($decoded['idKursart'])
            || isset($decoded['kursart'])) {
            return 'Kursunterricht';
        }

        foreach (['typ', 'art'] as $key) {
            if (!isset($decoded[$key]) || !is_scalar($decoded[$key])) {
                continue;
            }
            $value = mb_strtolower(trim((string) $decoded[$key]));
            if (str_contains($value, 'klasse')) {
                return 'Klassenunterricht';
            }
            if (str_contains($value, 'kurs')) {
                return 'Kursunterricht';
            }
        }

        return 'Lerngruppe';
    }

    private static function isTruthy(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value)) {
            return $value !== 0;
        }
        if (is_string($value)) {
            $normalized = mb_strtolower(trim($value));
            return in_array($normalized, ['1', 'true', 'ja', 'yes'], true);
        }

        return false;
    }
}
