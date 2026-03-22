<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/config/config.php';
require_once __DIR__ . '/../src/config/database.php';
require_once __DIR__ . '/../src/auth/user.php';
require_once __DIR__ . '/../src/modules/sync/svws_sync_service.php';
require_once __DIR__ . '/../templates/layout.php';

requireLogin();

$errorMessage = '';
$result = null;

$baseUrl = SVWS_BASE_URL;
$schema = SVWS_SCHEMA;
$idLernplattform = SVWS_ID_LERNPLATTFORM;
$idSchuljahresabschnitt = SVWS_ID_SCHULJAHRESABSCHNITT;
$verifyTls = SVWS_VERIFY_TLS;
$username = SVWS_USERNAME;
$password = SVWS_PASSWORD;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrfToken();

    $baseUrl = trim((string) ($_POST['baseUrl'] ?? $baseUrl));
    $schema = trim((string) ($_POST['schema'] ?? $schema));
    $idLernplattform = (int) ($_POST['idLernplattform'] ?? $idLernplattform);
    $idSchuljahresabschnitt = (int) ($_POST['idSchuljahresabschnitt'] ?? $idSchuljahresabschnitt);
    $verifyTls = isset($_POST['verifyTls']) && (string) $_POST['verifyTls'] === '1';
    $username = (string) ($_POST['username'] ?? $username);
    $password = (string) ($_POST['password'] ?? $password);

    try {
        $result = SvwsSyncService::synchronize([
            'baseUrl' => $baseUrl,
            'schema' => $schema,
            'idLernplattform' => $idLernplattform,
            'idSchuljahresabschnitt' => $idSchuljahresabschnitt,
            'verifyTls' => $verifyTls,
            'username' => $username,
            'password' => $password,
        ]);
    } catch (Throwable $e) {
        $errorMessage = $e->getMessage();
    }
}

$latestRun = SvwsSyncService::getLatestRun();
$latestStats = [];
if (is_array($latestRun) && is_string($latestRun['stats_json'] ?? null)) {
    $decoded = json_decode((string) $latestRun['stats_json'], true);
    if (is_array($decoded)) {
        $latestStats = $decoded;
    }
}

$db = getDB();
$counts = [
    'Schueler' => (int) $db->query('SELECT COUNT(*) AS c FROM svws_students')->fetch()['c'],
    'Lehrkraefte' => (int) $db->query('SELECT COUNT(*) AS c FROM svws_teachers')->fetch()['c'],
    'Lerngruppen' => (int) $db->query('SELECT COUNT(*) AS c FROM svws_groups')->fetch()['c'],
    'Schueler-Lerngruppen' => (int) $db->query('SELECT COUNT(*) AS c FROM svws_student_groups')->fetch()['c'],
    'Lehrer-Lerngruppen' => (int) $db->query('SELECT COUNT(*) AS c FROM svws_teacher_groups')->fetch()['c'],
];

ob_start();
?>
<div class="svws-content-header">
    <div class="svws-avatar">S</div>
    <div>
        <p class="svws-title-main">SVWS Synchronisation</p>
        <div class="svws-title-sub">Lernplattform GZIP nach SVWS-Media</div>
    </div>
</div>

<div class="svws-tabs">
    <span class="svws-tab active">Import</span>
    <span class="svws-tab">Status</span>
</div>

<section class="svws-panel" style="margin-bottom: 8px;">
    <div class="svws-panel-header">
        <h3>Konfiguration</h3>
        <span class="svws-muted">Endpunkt</span>
    </div>
    <div class="svws-panel-body">
        <form method="post" style="display:grid;grid-template-columns:repeat(2,minmax(220px,1fr));gap:8px;">
            <?= csrfField() ?>
            <label>
                <span class="svws-muted">SVWS Base URL</span><br>
                <input class="svws-search" type="text" name="baseUrl" value="<?= htmlspecialchars($baseUrl) ?>">
            </label>
            <label>
                <span class="svws-muted">Schema</span><br>
                <input class="svws-search" type="text" name="schema" value="<?= htmlspecialchars($schema) ?>">
            </label>
            <label>
                <span class="svws-muted">idLernplattform</span><br>
                <input class="svws-search" type="number" name="idLernplattform" value="<?= htmlspecialchars((string) $idLernplattform) ?>">
            </label>
            <label>
                <span class="svws-muted">idSchuljahresabschnitt</span><br>
                <input class="svws-search" type="number" name="idSchuljahresabschnitt" value="<?= htmlspecialchars((string) $idSchuljahresabschnitt) ?>">
            </label>
            <label>
                <span class="svws-muted">BasicAuth Benutzername</span><br>
                <input class="svws-search" type="text" name="username" value="<?= htmlspecialchars($username) ?>">
            </label>
            <label>
                <span class="svws-muted">BasicAuth Passwort</span><br>
                <input class="svws-search" type="password" name="password" value="" autocomplete="off" placeholder="Nur fuer diesen Sync-Lauf eingeben">
            </label>
            <label style="grid-column:1 / -1;">
                <input type="checkbox" name="verifyTls" value="1" <?= $verifyTls ? 'checked' : '' ?>> TLS-Zertifikat pruefen
            </label>
            <div style="grid-column:1 / -1;">
                <button class="svws-help-btn" type="submit">Synchronisation starten</button>
            </div>
        </form>

        <?php if ($errorMessage !== ''): ?>
            <p style="margin-top:8px;color:#a40000;"><strong>Fehler:</strong> <?= htmlspecialchars($errorMessage) ?></p>
        <?php endif; ?>

        <?php if (is_array($result)): ?>
            <p style="margin-top:8px;color:#0c5c0c;"><strong>Erfolg:</strong> Synchronisation wurde ausgefuehrt.</p>
            <p class="svws-muted" style="margin-top:4px;">Endpunkt: <?= htmlspecialchars((string) $result['endpoint']) ?></p>
        <?php endif; ?>
    </div>
</section>

<div class="svws-split" style="grid-template-columns:1fr 1fr;">
    <section class="svws-panel">
        <div class="svws-panel-header">
            <h3>Aktueller Datenstand</h3>
        </div>
        <div class="svws-panel-body">
            <table class="svws-tight">
                <thead>
                <tr><th>Objekt</th><th>Anzahl</th></tr>
                </thead>
                <tbody>
                <?php foreach ($counts as $label => $value): ?>
                    <tr>
                        <td><?= htmlspecialchars($label) ?></td>
                        <td><?= htmlspecialchars((string) $value) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="svws-panel">
        <div class="svws-panel-header">
            <h3>Letzter Lauf</h3>
        </div>
        <div class="svws-panel-body">
            <?php if (!is_array($latestRun)): ?>
                <p class="svws-muted">Noch kein Sync-Lauf vorhanden.</p>
            <?php else: ?>
                <table class="svws-tight" style="margin-bottom:6px;">
                    <tbody>
                    <tr><td>Status</td><td><?= htmlspecialchars((string) $latestRun['status']) ?></td></tr>
                    <tr><td>Gestartet</td><td><?= htmlspecialchars((string) $latestRun['started_at']) ?></td></tr>
                    <tr><td>Beendet</td><td><?= htmlspecialchars((string) ($latestRun['finished_at'] ?? '')) ?></td></tr>
                    <tr><td>Meldung</td><td><?= htmlspecialchars((string) ($latestRun['message'] ?? '')) ?></td></tr>
                    </tbody>
                </table>
                <?php if ($latestStats !== []): ?>
                    <table class="svws-tight">
                        <thead>
                        <tr><th>Sync-Statistik</th><th>Wert</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($latestStats as $label => $value): ?>
                            <tr>
                                <td><?= htmlspecialchars((string) $label) ?></td>
                                <td><?= htmlspecialchars((string) $value) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </section>
</div>
<?php
$content = ob_get_clean();

renderLayout('SVWS Sync', $content, 'sync');
