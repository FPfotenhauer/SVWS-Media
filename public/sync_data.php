<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/config/config.php';
require_once __DIR__ . '/../src/auth/user.php';
require_once __DIR__ . '/../src/modules/sync/svws_data_service.php';
require_once __DIR__ . '/../templates/layout.php';

requireLogin();

$tab = (string) ($_GET['tab'] ?? 'students');
$search = trim((string) ($_GET['q'] ?? ''));

$validTabs = ['students', 'teachers'];
if (!in_array($tab, $validTabs, true)) {
    $tab = 'students';
}

$overview = SvwsDataService::getOverviewCounts();
$students = $tab === 'students' ? SvwsDataService::getStudents($search) : [];
$teachers = $tab === 'teachers' ? SvwsDataService::getTeachers($search) : [];

ob_start();
?>
<div class="svws-content-header">
    <div class="svws-avatar">D</div>
    <div>
        <p class="svws-title-main">SVWS Stammdaten</p>
        <div class="svws-title-sub">Schueler und Lehrkraefte aus API-Sync</div>
    </div>
</div>

<div class="svws-tabs">
    <a class="svws-tab <?= $tab === 'students' ? 'active' : '' ?>" href="/sync_data.php?tab=students">Schueler</a>
    <a class="svws-tab <?= $tab === 'teachers' ? 'active' : '' ?>" href="/sync_data.php?tab=teachers">Lehrkraefte</a>
</div>

<section class="svws-panel" style="margin-bottom:8px;">
    <div class="svws-panel-header">
        <h3>Uebersicht</h3>
    </div>
    <div class="svws-panel-body">
        <table class="svws-tight">
            <thead>
            <tr>
                <th>Objekt</th>
                <th>Anzahl</th>
            </tr>
            </thead>
            <tbody>
            <tr><td>Schueler</td><td><?= htmlspecialchars((string) $overview['students']) ?></td></tr>
            <tr><td>Lehrkraefte</td><td><?= htmlspecialchars((string) $overview['teachers']) ?></td></tr>
            </tbody>
        </table>
    </div>
</section>

<section class="svws-panel">
    <div class="svws-panel-header">
        <h3>Liste</h3>
        <span class="svws-muted">Vorbereitung fuer Nachfolgefunktionen statt CSV-Import</span>
    </div>
    <div class="svws-panel-body">
        <form method="get" style="display:flex; gap:6px; margin-bottom:8px; align-items:center;">
            <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">
            <input class="svws-search" type="search" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Suche">
            <button class="svws-help-btn" type="submit">Filtern</button>
            <a class="svws-help-btn" href="/sync_svws.php" style="text-decoration:none;display:inline-block;">Neu synchronisieren</a>
        </form>

        <?php if ($tab === 'students'): ?>
            <table class="svws-tight">
                <thead>
                <tr>
                    <th>SVWS-ID</th>
                    <th>Nachname</th>
                    <th>Vorname</th>
                    <th>Klasse</th>
                    <th>Status</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($students as $row): ?>
                    <tr>
                        <td><?= htmlspecialchars((string) $row['svws_id']) ?></td>
                        <td><?= htmlspecialchars((string) $row['nachname']) ?></td>
                        <td><?= htmlspecialchars((string) $row['vorname']) ?></td>
                        <td><?= htmlspecialchars((string) ($row['klasse'] ?? '')) ?></td>
                        <td><?= htmlspecialchars((string) ($row['status'] ?? '')) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($students === []): ?>
                    <tr><td colspan="5" class="svws-muted">Keine Schuelerdaten vorhanden.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <?php if ($tab === 'teachers'): ?>
            <table class="svws-tight">
                <thead>
                <tr>
                    <th>SVWS-ID</th>
                    <th>Kuerzel</th>
                    <th>Nachname</th>
                    <th>Vorname</th>
                    <th>E-Mail</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($teachers as $row): ?>
                    <tr>
                        <td><?= htmlspecialchars((string) $row['svws_id']) ?></td>
                        <td><?= htmlspecialchars((string) ($row['kuerzel'] ?? '')) ?></td>
                        <td><?= htmlspecialchars((string) $row['nachname']) ?></td>
                        <td><?= htmlspecialchars((string) $row['vorname']) ?></td>
                        <td><?= htmlspecialchars((string) ($row['email'] ?? '')) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($teachers === []): ?>
                    <tr><td colspan="5" class="svws-muted">Keine Lehrerdaten vorhanden.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        <?php endif; ?>

    </div>
</section>
<?php
$content = ob_get_clean();

renderLayout('SVWS Daten', $content, 'data');
