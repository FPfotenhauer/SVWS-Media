<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/database.php';

class SvwsDataService
{
    public static function getStudents(string $search = '', int $limit = 300): array
    {
        $db = getDB();
        $sql = '
            SELECT
                s.id,
                s.svws_id,
                s.nachname,
                s.vorname,
                s.klasse,
                s.status,
                s.email
            FROM svws_students s
            WHERE (:search = "" OR s.nachname LIKE :searchLike OR s.vorname LIKE :searchLike OR s.klasse LIKE :searchLike)
            ORDER BY s.nachname, s.vorname
            LIMIT :limit
        ';

        $stmt = $db->prepare($sql);
        $stmt->bindValue(':search', $search, PDO::PARAM_STR);
        $stmt->bindValue(':searchLike', '%' . $search . '%', PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public static function getTeachers(string $search = '', int $limit = 300): array
    {
        $db = getDB();
        $sql = '
            SELECT
                t.id,
                t.svws_id,
                t.kuerzel,
                t.nachname,
                t.vorname,
                t.email
            FROM svws_teachers t
            WHERE (:search = "" OR t.kuerzel LIKE :searchLike OR t.nachname LIKE :searchLike OR t.vorname LIKE :searchLike)
            ORDER BY t.nachname, t.vorname
            LIMIT :limit
        ';

        $stmt = $db->prepare($sql);
        $stmt->bindValue(':search', $search, PDO::PARAM_STR);
        $stmt->bindValue(':searchLike', '%' . $search . '%', PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
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
