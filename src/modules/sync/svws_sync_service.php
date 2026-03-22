<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

class SvwsSyncService
{
    public static function synchronize(array $options = []): array
    {
        $baseUrl = (string) ($options['baseUrl'] ?? SVWS_BASE_URL);
        $schema = (string) ($options['schema'] ?? SVWS_SCHEMA);
        $idLernplattform = (int) ($options['idLernplattform'] ?? SVWS_ID_LERNPLATTFORM);
        $idSchuljahresabschnitt = (int) ($options['idSchuljahresabschnitt'] ?? SVWS_ID_SCHULJAHRESABSCHNITT);
        $verifyTls = (bool) ($options['verifyTls'] ?? SVWS_VERIFY_TLS);
        $username = (string) ($options['username'] ?? SVWS_USERNAME);
        $password = (string) ($options['password'] ?? SVWS_PASSWORD);

        if ($schema === '') {
            throw new RuntimeException('Schema darf nicht leer sein.');
        }

        $endpoint = self::buildEndpoint($baseUrl, $schema, $idLernplattform, $idSchuljahresabschnitt);
        $db = getDB();
        $startedAt = gmdate('c');
        $runId = self::createRun($db, $startedAt, $endpoint);

        try {
            $gzip = self::downloadGzip($endpoint, $verifyTls, $username, $password);
            $payload = self::decodePayload($gzip);
            $normalized = self::normalizePayload($payload);
            $stats = self::persistData($db, $normalized);

            self::finishRun($db, $runId, 'success', 'Synchronisation abgeschlossen.', $stats);

            return [
                'status' => 'success',
                'endpoint' => $endpoint,
                'stats' => $stats,
            ];
        } catch (Throwable $e) {
            self::finishRun($db, $runId, 'error', $e->getMessage(), []);
            throw $e;
        }
    }

    public static function getLatestRun(): ?array
    {
        $db = getDB();
        $stmt = $db->query('SELECT * FROM svws_sync_runs ORDER BY id DESC LIMIT 1');
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    private static function buildEndpoint(string $baseUrl, string $schema, int $idLernplattform, int $idSchuljahresabschnitt): string
    {
        $base = rtrim($baseUrl, '/');
        $encodedSchema = rawurlencode($schema);

        return sprintf(
            '%s/api/external/%s/v1/lernplattformen/%d/%d/gzip',
            $base,
            $encodedSchema,
            $idLernplattform,
            $idSchuljahresabschnitt
        );
    }

    private static function downloadGzip(string $endpoint, bool $verifyTls, string $username, string $password): string
    {
        // Some SVWS setups require BasicAuth even when the password is intentionally empty.
        $useBasicAuth = trim($username) !== '';
        $authHeader = 'Authorization: Basic ' . base64_encode($username . ':' . $password);

        if (function_exists('curl_init')) {
            $ch = curl_init($endpoint);
            if ($ch === false) {
                throw new RuntimeException('Konnte cURL nicht initialisieren.');
            }

            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 45);
            $headers = ['Accept: application/gzip, application/octet-stream'];
            if ($useBasicAuth) {
                $headers[] = $authHeader;
            }
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $verifyTls);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $verifyTls ? 2 : 0);
            if ($useBasicAuth) {
                curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
                curl_setopt($ch, CURLOPT_USERPWD, $username . ':' . $password);
            }

            $body = curl_exec($ch);
            $statusCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($body === false) {
                throw new RuntimeException('Download fehlgeschlagen: ' . $error);
            }
            if ($statusCode >= 400) {
                $detail = trim((string) $body);
                $detail = preg_replace('/\s+/', ' ', $detail ?? '');
                $message = 'SVWS-Endpunkt liefert HTTP ' . $statusCode;
                if ($detail !== '') {
                    $message .= ' - ' . mb_substr($detail, 0, 220);
                }

                throw new RuntimeException($message);
            }

            return (string) $body;
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => "Accept: application/gzip, application/octet-stream\r\n"
                    . ($useBasicAuth ? ($authHeader . "\r\n") : ''),
                'timeout' => 45,
            ],
            'ssl' => [
                'verify_peer' => $verifyTls,
                'verify_peer_name' => $verifyTls,
            ],
        ]);

        $body = file_get_contents($endpoint, false, $context);
        if ($body === false) {
            throw new RuntimeException('Download fehlgeschlagen (file_get_contents).');
        }

        return $body;
    }

    private static function decodePayload(string $gzip): array
    {
        $candidates = [$gzip];

        for ($i = 0; $i < 4; $i++) {
            $current = $candidates[count($candidates) - 1];
            $json = json_decode($current, true);
            if (is_array($json)) {
                return $json;
            }

            $decoded = gzdecode($current);
            if ($decoded === false) {
                break;
            }

            $candidates[] = $decoded;
        }

        $last = $candidates[count($candidates) - 1];
        $asJson = json_decode($last, true);
        if (!is_array($asJson)) {
            throw new RuntimeException('Entpackte Datei enthaelt kein valides JSON.');
        }

        return $asJson;
    }

    private static function normalizePayload(array $payload): array
    {
        $students = self::extractList($payload, ['schueler', 'schuelerListe', 'students']);
        $classes = self::extractList($payload, ['Klassen', 'klassen', 'classes']);
        $teachers = self::extractList($payload, ['lehrer', 'lehrkraefte', 'lehrkraefteListe', 'teachers']);
        $groups = self::extractList($payload, ['lerngruppen', 'kurse', 'gruppen', 'groups']);

        $studentGroupMap = self::extractMap($payload, ['schuelerLerngruppen', 'studentGroupMap']);
        $teacherGroupMap = self::extractMap($payload, ['lehrerLerngruppen', 'teacherGroupMap']);

        if (!self::mapHasLinks($studentGroupMap)) {
            $studentGroupMap = self::extractMembershipMapFromItems($students, ['lerngruppen', 'gruppen', 'lerngruppeIds', 'gruppenIds', 'idsLerngruppen']);
        }
        if (!self::mapHasLinks($teacherGroupMap)) {
            $teacherGroupMap = self::extractMembershipMapFromItems($teachers, ['lerngruppen', 'gruppen', 'lerngruppeIds', 'gruppenIds', 'idsLerngruppen']);
        }
        if (!self::mapHasLinks($teacherGroupMap)) {
            $teacherGroupMap = self::extractTeacherGroupMapFromGroups($groups);
        }

        $schoolMeta = self::extractSchoolMeta($payload);

        return [
            'students' => $students,
            'classes' => $classes,
            'teachers' => $teachers,
            'groups' => $groups,
            'studentGroupMap' => $studentGroupMap,
            'teacherGroupMap' => $teacherGroupMap,
            'schoolMeta' => $schoolMeta,
        ];
    }

    private static function extractSchoolMeta(array $payload): array
    {
        // SVWS may nest school data under a 'schule' key or provide it at root level.
        $root = isset($payload['schule']) && is_array($payload['schule']) ? $payload['schule'] : $payload;

        $schulname = self::firstValue($root, ['schulbezeichnung', 'schulname', 'bezeichnungSchule', 'nameSchule', 'name', 'schulbezeichnung1']);
        $schulnummer = self::firstValue($root, ['schulnummer', 'snr', 'schulNummer', 'schuldatennummer']);
        $ort = self::firstValue($root, ['ort', 'ortsname', 'ort1', 'city']);
        $plz = self::firstValue($root, ['plz', 'postleitzahl', 'zip']);
        $mailadresse = self::firstValue($root, ['mailadresse', 'email', 'emailSchule', 'mail']);

        return [
            'schulname'   => is_string($schulname) ? $schulname : null,
            'schulnummer' => is_string($schulnummer) || is_int($schulnummer) ? (string) $schulnummer : null,
            'ort'         => is_string($ort) ? $ort : null,
            'plz'         => is_string($plz) ? $plz : null,
            'mailadresse' => is_string($mailadresse) ? $mailadresse : null,
        ];
    }

    private static function firstValue(array $data, array $keys): mixed
    {
        foreach ($keys as $key) {
            if (isset($data[$key]) && $data[$key] !== '' && $data[$key] !== null) {
                return $data[$key];
            }
        }

        return null;
    }

    private static function persistData(PDO $db, array $data): array
    {
        $students = $data['students'];
        $classLookup = self::buildClassLookup($data['classes'] ?? []);
        $teachers = $data['teachers'];
        $groups = $data['groups'];

        $now = gmdate('c');

        $db->beginTransaction();
        try {
            self::syncStudents($db, $students, $classLookup, $now);
            self::syncTeachers($db, $teachers, $now);
            self::syncGroups($db, $groups, $now);
            self::syncRelations($db, $data['studentGroupMap'], $data['teacherGroupMap']);
            self::syncSchoolMeta($db, $data['schoolMeta'], $now);
            refreshBorrowersFromSvwsData($db);
            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }

        return [
            'students' => count($students),
            'teachers' => count($teachers),
            'groups' => count($groups),
            'studentGroupLinks' => self::countTable($db, 'svws_student_groups'),
            'teacherGroupLinks' => self::countTable($db, 'svws_teacher_groups'),
        ];
    }

    private static function syncSchoolMeta(PDO $db, array $meta, string $now): void
    {
        // Only non-null fields overwrite existing values so a partial payload doesn't blank out known data.
        $existing = $db->query('SELECT * FROM svws_school_meta WHERE id = 1')->fetch();

        if ($existing === false) {
            $stmt = $db->prepare(
                'INSERT INTO svws_school_meta (id, schulname, schulnummer, ort, plz, mailadresse, updated_at)
                 VALUES (1, :schulname, :schulnummer, :ort, :plz, :mailadresse, :updated_at)'
            );
            $stmt->execute([
                'schulname'   => $meta['schulname'],
                'schulnummer' => $meta['schulnummer'],
                'ort'         => $meta['ort'],
                'plz'         => $meta['plz'],
                'mailadresse' => $meta['mailadresse'],
                'updated_at'  => $now,
            ]);

            return;
        }

        $stmt = $db->prepare(
            'UPDATE svws_school_meta SET
                schulname   = COALESCE(:schulname,   schulname),
                schulnummer = COALESCE(:schulnummer, schulnummer),
                ort         = COALESCE(:ort,         ort),
                plz         = COALESCE(:plz,         plz),
                mailadresse = COALESCE(:mailadresse, mailadresse),
                updated_at  = :updated_at
             WHERE id = 1'
        );
        $stmt->execute([
            'schulname'   => $meta['schulname'],
            'schulnummer' => $meta['schulnummer'],
            'ort'         => $meta['ort'],
            'plz'         => $meta['plz'],
            'mailadresse' => $meta['mailadresse'],
            'updated_at'  => $now,
        ]);
    }

    private static function syncStudents(PDO $db, array $items, array $classLookup, string $now): void
    {
        $ids = [];
        $stmt = $db->prepare(
            'INSERT INTO svws_students (svws_id, nachname, vorname, anzeige_name, klasse, status, email, raw_json, updated_at)
             VALUES (:svws_id, :nachname, :vorname, :anzeige_name, :klasse, :status, :email, :raw_json, :updated_at)
             ON CONFLICT(svws_id) DO UPDATE SET
                nachname = excluded.nachname,
                vorname = excluded.vorname,
                anzeige_name = excluded.anzeige_name,
                klasse = excluded.klasse,
                status = excluded.status,
                email = excluded.email,
                raw_json = excluded.raw_json,
                updated_at = excluded.updated_at'
        );

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $svwsId = self::extractId($item, ['id', 'idSchueler', 'schuelerId']);
            if ($svwsId === null) {
                continue;
            }
            $ids[] = $svwsId;

            $vorname = self::pickString($item, ['vorname', 'rufname']);
            $nachname = self::pickString($item, ['nachname']);
            $classFromLookup = '';
            $classId = self::extractId($item, ['idKlasse', 'klasseId', 'id_klasse']);
            if ($classId !== null) {
                $classFromLookup = $classLookup[$classId] ?? '';
            }
            $klasse = $classFromLookup !== '' ? $classFromLookup : self::extractStudentClass($item);

            $stmt->execute([
                'svws_id' => $svwsId,
                'nachname' => $nachname,
                'vorname' => $vorname,
                'anzeige_name' => trim($nachname . ', ' . $vorname, ' ,'),
                'klasse' => $klasse,
                'status' => self::pickString($item, ['status']),
                'email' => self::pickString($item, ['email', 'eMailAdresse']),
                'raw_json' => json_encode($item, JSON_UNESCAPED_UNICODE),
                'updated_at' => $now,
            ]);
        }

        self::deleteMissing($db, 'svws_students', $ids);
    }

    private static function extractStudentClass(array $item): string
    {
        $direct = self::pickString($item, [
            'klasse',
            'klassenbezeichnung',
            'klasseKuerzel',
            'bezeichnungKlasse',
            'lerngruppe',
        ]);
        if ($direct !== '') {
            return $direct;
        }

        // Some payload variants nest class information as an object.
        if (isset($item['klasse']) && is_array($item['klasse'])) {
            $nested = self::pickString($item['klasse'], ['kuerzel', 'bezeichnung', 'name', 'anzeige']);
            if ($nested !== '') {
                return $nested;
            }
        }

        // Some payloads expose class references as list-like structures.
        foreach (['klassen', 'lerngruppen'] as $listKey) {
            if (!isset($item[$listKey]) || !is_array($item[$listKey])) {
                continue;
            }

            foreach ($item[$listKey] as $classRef) {
                if (is_array($classRef)) {
                    $fromRef = self::pickString($classRef, ['kuerzel', 'bezeichnung', 'name', 'anzeige']);
                    if ($fromRef !== '') {
                        return $fromRef;
                    }
                } elseif (is_scalar($classRef)) {
                    $fromScalar = trim((string) $classRef);
                    if ($fromScalar !== '') {
                        return $fromScalar;
                    }
                }
            }
        }

        return '';
    }

    private static function buildClassLookup(array $classes): array
    {
        $lookup = [];

        foreach ($classes as $classItem) {
            if (!is_array($classItem)) {
                continue;
            }

            $id = self::extractId($classItem, ['id', 'idKlasse', 'klasseId', 'id_klasse']);
            if ($id === null) {
                continue;
            }

            $label = self::pickString($classItem, [
                'kuerzel',
                'bezeichnung',
                'anzeigename',
                'name',
                'klassenbezeichnung',
            ]);
            if ($label === '') {
                continue;
            }

            $lookup[$id] = $label;
        }

        return $lookup;
    }

    private static function syncTeachers(PDO $db, array $items, string $now): void
    {
        $ids = [];
        $stmt = $db->prepare(
            'INSERT INTO svws_teachers (svws_id, kuerzel, nachname, vorname, anzeige_name, email, raw_json, updated_at)
             VALUES (:svws_id, :kuerzel, :nachname, :vorname, :anzeige_name, :email, :raw_json, :updated_at)
             ON CONFLICT(svws_id) DO UPDATE SET
                kuerzel = excluded.kuerzel,
                nachname = excluded.nachname,
                vorname = excluded.vorname,
                anzeige_name = excluded.anzeige_name,
                email = excluded.email,
                raw_json = excluded.raw_json,
                updated_at = excluded.updated_at'
        );

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $svwsId = self::extractId($item, ['id', 'idLehrer', 'lehrerId']);
            if ($svwsId === null) {
                continue;
            }
            $ids[] = $svwsId;

            $vorname = self::pickString($item, ['vorname', 'rufname']);
            $nachname = self::pickString($item, ['nachname']);

            $stmt->execute([
                'svws_id' => $svwsId,
                'kuerzel' => self::pickString($item, ['kuerzel', 'kuerzelAnzeige']),
                'nachname' => $nachname,
                'vorname' => $vorname,
                'anzeige_name' => trim($nachname . ', ' . $vorname, ' ,'),
                'email' => self::pickString($item, ['email', 'eMailAdresse']),
                'raw_json' => json_encode($item, JSON_UNESCAPED_UNICODE),
                'updated_at' => $now,
            ]);
        }

        self::deleteMissing($db, 'svws_teachers', $ids);
    }

    private static function syncGroups(PDO $db, array $items, string $now): void
    {
        $ids = [];
        $stmt = $db->prepare(
            'INSERT INTO svws_groups (svws_id, kuerzel, name, jahrgang, raw_json, updated_at)
             VALUES (:svws_id, :kuerzel, :name, :jahrgang, :raw_json, :updated_at)
             ON CONFLICT(svws_id) DO UPDATE SET
                kuerzel = excluded.kuerzel,
                name = excluded.name,
                jahrgang = excluded.jahrgang,
                raw_json = excluded.raw_json,
                updated_at = excluded.updated_at'
        );

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $svwsId = self::extractId($item, ['id', 'idLerngruppe', 'idKurs']);
            if ($svwsId === null) {
                continue;
            }
            $ids[] = $svwsId;

            $stmt->execute([
                'svws_id' => $svwsId,
                'kuerzel' => self::pickString($item, ['kuerzel', 'fachkuerzel']),
                'name' => self::pickString($item, ['bezeichnung', 'name', 'anzeige']),
                'jahrgang' => self::pickString($item, ['jahrgang', 'stufe']),
                'raw_json' => json_encode($item, JSON_UNESCAPED_UNICODE),
                'updated_at' => $now,
            ]);
        }

        self::deleteMissing($db, 'svws_groups', $ids);
    }

    private static function syncRelations(PDO $db, array $studentGroupMap, array $teacherGroupMap): void
    {
        $db->exec('DELETE FROM svws_student_groups');
        $db->exec('DELETE FROM svws_teacher_groups');

        $studentLookup = self::buildIdLookup($db, 'svws_students');
        $teacherLookup = self::buildIdLookup($db, 'svws_teachers');
        $groupLookup = self::buildIdLookup($db, 'svws_groups');

        $studentInsert = $db->prepare('INSERT OR IGNORE INTO svws_student_groups (student_id, group_id) VALUES (:entity_id, :group_id)');
        $teacherInsert = $db->prepare('INSERT OR IGNORE INTO svws_teacher_groups (teacher_id, group_id) VALUES (:entity_id, :group_id)');

        self::insertRelationMap($studentInsert, $studentGroupMap, $studentLookup, $groupLookup);
        self::insertRelationMap($teacherInsert, $teacherGroupMap, $teacherLookup, $groupLookup);
    }

    private static function insertRelationMap(PDOStatement $stmt, array $map, array $entityLookup, array $groupLookup): void
    {
        foreach ($map as $entitySvwsId => $groupSvwsIds) {
            $entityId = $entityLookup[(int) $entitySvwsId] ?? null;
            if ($entityId === null || !is_array($groupSvwsIds)) {
                continue;
            }

            foreach ($groupSvwsIds as $groupSvwsId) {
                $groupId = $groupLookup[(int) $groupSvwsId] ?? null;
                if ($groupId === null) {
                    continue;
                }

                $stmt->execute([
                    'entity_id' => $entityId,
                    'group_id' => $groupId,
                ]);
            }
        }
    }

    private static function buildIdLookup(PDO $db, string $table): array
    {
        $stmt = $db->query('SELECT id, svws_id FROM ' . $table);
        $lookup = [];

        foreach ($stmt->fetchAll() as $row) {
            $lookup[(int) $row['svws_id']] = (int) $row['id'];
        }

        return $lookup;
    }

    private static function deleteMissing(PDO $db, string $table, array $svwsIds): void
    {
        $svwsIds = array_values(array_unique(array_map('intval', $svwsIds)));

        if ($svwsIds === []) {
            $db->exec('DELETE FROM ' . $table);
            return;
        }

        $placeholders = implode(',', array_fill(0, count($svwsIds), '?'));
        $stmt = $db->prepare('DELETE FROM ' . $table . ' WHERE svws_id NOT IN (' . $placeholders . ')');
        $stmt->execute($svwsIds);
    }

    private static function createRun(PDO $db, string $startedAt, string $endpoint): int
    {
        $stmt = $db->prepare('INSERT INTO svws_sync_runs (started_at, status, endpoint, message, stats_json) VALUES (:started_at, :status, :endpoint, :message, :stats_json)');
        $stmt->execute([
            'started_at' => $startedAt,
            'status' => 'running',
            'endpoint' => $endpoint,
            'message' => 'Synchronisation gestartet.',
            'stats_json' => json_encode([], JSON_UNESCAPED_UNICODE),
        ]);

        return (int) $db->lastInsertId();
    }

    private static function finishRun(PDO $db, int $runId, string $status, string $message, array $stats): void
    {
        $stmt = $db->prepare('UPDATE svws_sync_runs SET finished_at = :finished_at, status = :status, message = :message, stats_json = :stats_json WHERE id = :id');
        $stmt->execute([
            'finished_at' => gmdate('c'),
            'status' => $status,
            'message' => $message,
            'stats_json' => json_encode($stats, JSON_UNESCAPED_UNICODE),
            'id' => $runId,
        ]);
    }

    private static function countTable(PDO $db, string $table): int
    {
        $stmt = $db->query('SELECT COUNT(*) AS cnt FROM ' . $table);
        $row = $stmt->fetch();

        return (int) ($row['cnt'] ?? 0);
    }

    private static function extractList(array $payload, array $keys): array
    {
        foreach ($keys as $key) {
            if (isset($payload[$key]) && is_array($payload[$key])) {
                return array_values($payload[$key]);
            }
        }

        return [];
    }

    private static function extractMap(array $payload, array $keys): array
    {
        foreach ($keys as $key) {
            if (!isset($payload[$key]) || !is_array($payload[$key])) {
                continue;
            }

            $map = [];
            foreach ($payload[$key] as $entityId => $groupIds) {
                if (!is_array($groupIds)) {
                    continue;
                }

                $map[(int) $entityId] = array_values(array_map('intval', $groupIds));
            }

            return $map;
        }

        return [];
    }

    private static function extractMembershipMapFromItems(array $items, array $possibleListKeys): array
    {
        $map = [];

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $entityId = self::extractId($item, ['id', 'idSchueler', 'idLehrer', 'schuelerId', 'lehrerId']);
            if ($entityId === null) {
                continue;
            }

            $groupIds = [];
            foreach ($possibleListKeys as $listKey) {
                if (!isset($item[$listKey]) || !is_array($item[$listKey])) {
                    continue;
                }

                foreach ($item[$listKey] as $groupRef) {
                    if (is_array($groupRef)) {
                        $groupId = self::extractId($groupRef, ['id', 'idLerngruppe', 'idKurs']);
                    } else {
                        $groupId = is_numeric($groupRef) ? (int) $groupRef : null;
                    }

                    if ($groupId !== null) {
                        $groupIds[] = $groupId;
                    }
                }
            }

            $map[$entityId] = array_values(array_unique($groupIds));
        }

        return $map;
    }

    private static function extractTeacherGroupMapFromGroups(array $groups): array
    {
        $map = [];

        foreach ($groups as $group) {
            if (!is_array($group)) {
                continue;
            }

            $groupId = self::extractId($group, ['id', 'idLerngruppe', 'idKurs']);
            if ($groupId === null) {
                continue;
            }

            $teacherIds = [];
            foreach (['idsLehrer', 'lehrerIds', 'idsLehrkraefte'] as $key) {
                if (!isset($group[$key]) || !is_array($group[$key])) {
                    continue;
                }

                foreach ($group[$key] as $teacherRef) {
                    if (is_array($teacherRef)) {
                        $teacherId = self::extractId($teacherRef, ['id', 'idLehrer', 'lehrerId']);
                    } else {
                        $teacherId = is_numeric($teacherRef) ? (int) $teacherRef : null;
                    }

                    if ($teacherId !== null) {
                        $teacherIds[] = $teacherId;
                    }
                }
            }

            foreach (array_unique($teacherIds) as $teacherId) {
                if (!isset($map[$teacherId])) {
                    $map[$teacherId] = [];
                }
                $map[$teacherId][] = $groupId;
            }
        }

        foreach ($map as $teacherId => $groupIds) {
            $map[$teacherId] = array_values(array_unique(array_map('intval', $groupIds)));
        }

        return $map;
    }

    private static function mapHasLinks(array $map): bool
    {
        foreach ($map as $groupIds) {
            if (is_array($groupIds) && $groupIds !== []) {
                return true;
            }
        }

        return false;
    }

    private static function extractId(array $item, array $keys): ?int
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $item)) {
                continue;
            }

            $value = $item[$key];
            if (is_int($value)) {
                return $value;
            }
            if (is_string($value) && ctype_digit($value)) {
                return (int) $value;
            }
        }

        return null;
    }

    private static function pickString(array $item, array $keys): string
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $item)) {
                continue;
            }

            $value = $item[$key];
            if (is_scalar($value)) {
                return trim((string) $value);
            }
        }

        return '';
    }
}
