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
                s.email,
                COUNT(sg.group_id) AS group_count
            FROM svws_students s
            LEFT JOIN svws_student_groups sg ON sg.student_id = s.id
            WHERE (:search = "" OR s.nachname LIKE :searchLike OR s.vorname LIKE :searchLike OR s.klasse LIKE :searchLike)
            GROUP BY s.id
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
                t.email,
                COUNT(tg.group_id) AS group_count
            FROM svws_teachers t
            LEFT JOIN svws_teacher_groups tg ON tg.teacher_id = t.id
            WHERE (:search = "" OR t.kuerzel LIKE :searchLike OR t.nachname LIKE :searchLike OR t.vorname LIKE :searchLike)
            GROUP BY t.id
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

    public static function getGroups(string $search = '', int $limit = 300): array
    {
        $db = getDB();
        $sql = '
            SELECT
                g.id,
                g.svws_id,
                g.kuerzel,
                g.name,
                g.jahrgang,
                COUNT(DISTINCT sg.student_id) AS student_count,
                COUNT(DISTINCT tg.teacher_id) AS teacher_count
            FROM svws_groups g
            LEFT JOIN svws_student_groups sg ON sg.group_id = g.id
            LEFT JOIN svws_teacher_groups tg ON tg.group_id = g.id
            WHERE (:search = "" OR g.kuerzel LIKE :searchLike OR g.name LIKE :searchLike OR g.jahrgang LIKE :searchLike)
            GROUP BY g.id
            ORDER BY g.name, g.kuerzel
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
            'groups' => (int) $db->query('SELECT COUNT(*) AS c FROM svws_groups')->fetch()['c'],
            'studentGroupLinks' => (int) $db->query('SELECT COUNT(*) AS c FROM svws_student_groups')->fetch()['c'],
            'teacherGroupLinks' => (int) $db->query('SELECT COUNT(*) AS c FROM svws_teacher_groups')->fetch()['c'],
        ];
    }
}
