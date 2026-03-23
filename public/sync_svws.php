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

$db = getDB();

// Load persisted config, fall back to constants if nothing saved yet.
$savedConfig = $db->query('SELECT * FROM svws_sync_config WHERE id = 1')->fetch();
$baseUrl = is_array($savedConfig) && $savedConfig['base_url'] !== null ? (string) $savedConfig['base_url'] : SVWS_BASE_URL;
$schema = is_array($savedConfig) && $savedConfig['schema'] !== null ? (string) $savedConfig['schema'] : SVWS_SCHEMA;
$idLernplattform = is_array($savedConfig) && $savedConfig['id_lernplattform'] !== null ? (int) $savedConfig['id_lernplattform'] : SVWS_ID_LERNPLATTFORM;
$idSchuljahresabschnitt = is_array($savedConfig) && $savedConfig['id_schuljahresabschnitt'] !== null ? (int) $savedConfig['id_schuljahresabschnitt'] : SVWS_ID_SCHULJAHRESABSCHNITT;
$verifyTls = is_array($savedConfig) && $savedConfig['verify_tls'] !== null ? (bool) $savedConfig['verify_tls'] : SVWS_VERIFY_TLS;
$username = is_array($savedConfig) && $savedConfig['username'] !== null ? (string) $savedConfig['username'] : SVWS_USERNAME;

$storedPassword = '';
if (is_array($savedConfig) && !empty($savedConfig['password_enc'])) {
    $storedPassword = decryptAppValue((string) $savedConfig['password_enc']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (!isset($_POST['action']) || (string) $_POST['action'] === 'sync_run')) {
    requireValidCsrfToken();

    $baseUrl = trim((string) ($_POST['baseUrl'] ?? $baseUrl));
    $schema = trim((string) ($_POST['schema'] ?? $schema));
    $idLernplattform = (int) ($_POST['idLernplattform'] ?? $idLernplattform);
    $idSchuljahresabschnitt = (int) ($_POST['idSchuljahresabschnitt'] ?? $idSchuljahresabschnitt);
    $verifyTls = isset($_POST['verifyTls']) && (string) $_POST['verifyTls'] === '1';
    $username = trim((string) ($_POST['username'] ?? $username));
    $newPasswordInput = (string) ($_POST['syncPassword'] ?? '');

    // Persist exactly what the user submits: empty stays empty.
    $password = $newPasswordInput;
    $passwordEnc = $newPasswordInput !== '' ? encryptAppValue($newPasswordInput) : null;
    $storedPassword = $newPasswordInput;

    // Persist config including encrypted password.
    $upsert = $db->prepare(
        'INSERT INTO svws_sync_config (id, base_url, schema, id_lernplattform, id_schuljahresabschnitt, verify_tls, username, password_enc, updated_at)
         VALUES (1, :base_url, :schema, :id_lernplattform, :id_schuljahresabschnitt, :verify_tls, :username, :password_enc, :updated_at)
         ON CONFLICT(id) DO UPDATE SET
             base_url = excluded.base_url,
             schema = excluded.schema,
             id_lernplattform = excluded.id_lernplattform,
             id_schuljahresabschnitt = excluded.id_schuljahresabschnitt,
             verify_tls = excluded.verify_tls,
             username = excluded.username,
             password_enc = excluded.password_enc,
             updated_at = excluded.updated_at'
    );
    $upsert->execute([
        'base_url' => $baseUrl,
        'schema' => $schema,
        'id_lernplattform' => $idLernplattform,
        'id_schuljahresabschnitt' => $idSchuljahresabschnitt,
        'verify_tls' => (int) $verifyTls,
        'username' => $username,
        'password_enc' => $passwordEnc,
        'updated_at' => gmdate('c'),
    ]);

    // Reload stored password for placeholder after save.
    // ($storedPassword is already updated above when passwordChanged)

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

$schoolMeta = $db->query('SELECT * FROM svws_school_meta WHERE id = 1')->fetch() ?: [];
$schoolMetaSaved = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && (string) $_POST['action'] === 'school_meta') {
    requireValidCsrfToken();
    $metaUpsert = $db->prepare(
        'INSERT INTO svws_school_meta (id, schulname, schulnummer, ort, plz, mailadresse, updated_at)
         VALUES (1, :schulname, :schulnummer, :ort, :plz, :mailadresse, :updated_at)
         ON CONFLICT(id) DO UPDATE SET
             schulname   = excluded.schulname,
             schulnummer = excluded.schulnummer,
             ort         = excluded.ort,
             plz         = excluded.plz,
             mailadresse = excluded.mailadresse,
             updated_at  = excluded.updated_at'
    );
    $metaUpsert->execute([
        'schulname'   => trim((string) ($_POST['schulname'] ?? '')),
        'schulnummer' => trim((string) ($_POST['schulnummer'] ?? '')),
        'ort'         => trim((string) ($_POST['ort'] ?? '')),
        'plz'         => trim((string) ($_POST['plz'] ?? '')),
        'mailadresse' => trim((string) ($_POST['mailadresse'] ?? '')),
        'updated_at'  => gmdate('c'),
    ]);
    $schoolMeta = $db->query('SELECT * FROM svws_school_meta WHERE id = 1')->fetch() ?: [];
    $schoolMetaSaved = true;
}

$counts = [
    'Schueler' => (int) $db->query('SELECT COUNT(*) AS c FROM svws_students')->fetch()['c'],
    'Lehrkraefte' => (int) $db->query('SELECT COUNT(*) AS c FROM svws_teachers')->fetch()['c'],
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
            <input type="hidden" name="action" value="sync_run">
            <input type="text" name="fake_username" autocomplete="username" style="display:none" tabindex="-1" aria-hidden="true">
            <input type="password" name="fake_password" autocomplete="current-password" style="display:none" tabindex="-1" aria-hidden="true">
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
                <input class="svws-search" type="password" id="sync-password" name="syncPassword" value="" autocomplete="new-password"
                    placeholder="<?= $storedPassword !== '' ? '(Gespeichertes Passwort – leer lassen zum Beibehalten)' : 'Passwort eingeben' ?>">
            </label>
            <label style="grid-column:1 / -1;">
                <input type="checkbox" name="verifyTls" value="1" <?= $verifyTls ? 'checked' : '' ?>> TLS-Zertifikat pruefen
            </label>
            <div style="grid-column:1 / -1;">
                <button class="svws-help-btn svws-btn-modern" type="submit">Synchronisation starten</button>
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

<section class="svws-panel" style="margin-top:8px;">
    <div class="svws-panel-header">
        <h3>Schuldaten</h3>
        <span class="svws-muted">Werden auf der Startseite angezeigt</span>
    </div>
    <div class="svws-panel-body">
        <?php if ($schoolMetaSaved): ?>
            <p style="margin-bottom:8px;color:#0c5c0c;"><strong>Gespeichert.</strong></p>
        <?php endif; ?>
        <form method="post" style="display:grid;grid-template-columns:repeat(2,minmax(200px,1fr));gap:8px;">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="school_meta">
            <label>
                <span class="svws-muted">Schulname</span><br>
                <input class="svws-search" type="text" name="schulname" value="<?= htmlspecialchars((string) ($schoolMeta['schulname'] ?? '')) ?>">
            </label>
            <label>
                <span class="svws-muted">Schulnummer</span><br>
                <input class="svws-search" type="text" name="schulnummer" value="<?= htmlspecialchars((string) ($schoolMeta['schulnummer'] ?? '')) ?>">
            </label>
            <label>
                <span class="svws-muted">PLZ</span><br>
                <input class="svws-search" type="text" name="plz" value="<?= htmlspecialchars((string) ($schoolMeta['plz'] ?? '')) ?>">
            </label>
            <label>
                <span class="svws-muted">Ort</span><br>
                <input class="svws-search" type="text" name="ort" value="<?= htmlspecialchars((string) ($schoolMeta['ort'] ?? '')) ?>">
            </label>
            <label style="grid-column:1 / -1;">
                <span class="svws-muted">E-Mail-Adresse</span><br>
                <input class="svws-search" type="email" name="mailadresse" value="<?= htmlspecialchars((string) ($schoolMeta['mailadresse'] ?? '')) ?>">
            </label>
            <div style="grid-column:1 / -1;">
                <button class="svws-help-btn svws-btn-modern" type="submit">Schuldaten speichern</button>
            </div>
        </form>
    </div>
</section>
<?php
$content = ob_get_clean();

renderLayout('SVWS Sync', $content, 'sync');
