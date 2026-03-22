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

    public static function getOverviewCounts(): array
    {
        $db = getDB();

        return [
            'students' => (int) $db->query('SELECT COUNT(*) AS c FROM svws_students')->fetch()['c'],
            'teachers' => (int) $db->query('SELECT COUNT(*) AS c FROM svws_teachers')->fetch()['c'],
        ];
    }
}
