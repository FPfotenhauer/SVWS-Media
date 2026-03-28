<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/config/config.php';
require_once __DIR__ . '/../src/auth/user.php';
require_once __DIR__ . '/../src/modules/sync/svws_data_service.php';
require_once __DIR__ . '/../templates/layout.php';

requireLogin();

$tab = (string) ($_GET['tab'] ?? 'students');
$search = trim((string) ($_GET['q'] ?? ''));
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = (int) ($_GET['per_page'] ?? 25);
$allowedPerPage = [25, 50, 100];
if (!in_array($perPage, $allowedPerPage, true)) {
    $perPage = 25;
}
$sort = (string) ($_GET['sort'] ?? 'nachname');
$dir = strtolower((string) ($_GET['dir'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';

$validTabs = ['students', 'teachers', 'classes', 'groups'];
if (!in_array($tab, $validTabs, true)) {
    $tab = 'students';
}

$allowedSortByTab = [
    'students' => ['svws_id', 'nachname', 'vorname', 'klasse', 'status'],
    'teachers' => ['svws_id', 'kuerzel', 'nachname', 'vorname', 'email'],
    'classes' => ['svws_id', 'kuerzel', 'name', 'jahrgang'],
    'groups' => ['svws_id', 'kuerzel', 'name', 'jahrgang'],
];

$defaultSortByTab = [
    'students' => 'nachname',
    'teachers' => 'nachname',
    'classes' => 'kuerzel',
    'groups' => 'kuerzel',
];

if (!in_array($sort, $allowedSortByTab[$tab], true)) {
    $sort = $defaultSortByTab[$tab];
}

$totalRows = match ($tab) {
    'students' => SvwsDataService::getStudentsCount($search),
    'teachers' => SvwsDataService::getTeachersCount($search),
    'classes' => SvwsDataService::getClassesCount($search),
    'groups' => SvwsDataService::getGroupsCount($search),
    default => SvwsDataService::getStudentsCount($search),
};
$totalPages = max(1, (int) ceil($totalRows / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $perPage;

$overview = SvwsDataService::getOverviewCounts();
$students = $tab === 'students'
    ? SvwsDataService::getStudents($search, $perPage, $offset, $sort, $dir)
    : [];
$teachers = $tab === 'teachers'
    ? SvwsDataService::getTeachers($search, $perPage, $offset, $sort, $dir)
    : [];
$classes = $tab === 'classes'
    ? SvwsDataService::getClasses($search, $perPage, $offset, $sort, $dir)
    : [];
$groups = $tab === 'groups'
    ? SvwsDataService::getGroups($search, $perPage, $offset, $sort, $dir)
    : [];

$buildUrl = static function (array $overrides = []) use ($tab, $search, $page, $perPage, $sort, $dir): string {
    $params = array_merge([
        'tab' => $tab,
        'q' => $search,
        'page' => $page,
        'per_page' => $perPage,
        'sort' => $sort,
        'dir' => $dir,
    ], $overrides);

    return '/sync_data.php?' . http_build_query($params);
};

$sortLink = static function (string $column) use ($sort, $dir, $buildUrl): string {
    $nextDir = ($sort === $column && $dir === 'asc') ? 'desc' : 'asc';
    return $buildUrl([
        'sort' => $column,
        'dir' => $nextDir,
        'page' => 1,
    ]);
};

$sortIndicator = static function (string $column) use ($sort, $dir): string {
    if ($sort !== $column) {
        return '↕';
    }
    return $dir === 'asc' ? '↑' : '↓';
};

ob_start();
?>
<style>
.svws-tabs.svws-tabs-svws {
    gap: 0;
    border-bottom: 1px solid #cfd5dc;
    padding: 0 0 0 6px;
    margin: 2px 0 10px;
    overflow-x: auto;
}

.svws-tabs.svws-tabs-svws .svws-tab {
    font-size: 21px;
    font-weight: 700;
    color: #111;
    text-decoration: none;
    padding: 8px 12px 7px;
    margin: 0 3px;
    border-radius: 5px 5px 0 0;
    border-bottom: 3px solid transparent;
    white-space: nowrap;
}

.svws-tabs.svws-tabs-svws .svws-tab:hover {
    background: #f0f4f8;
}

.svws-tabs.svws-tabs-svws .svws-tab.active {
    color: #1a5e98;
    background: #e7f0f9;
    border-bottom-color: #1a5e98;
}

@media (max-width: 780px) {
    .svws-tabs.svws-tabs-svws .svws-tab {
        font-size: 16px;
        padding: 7px 10px 6px;
    }
}

.svws-table-wrap {
    overflow: auto;
}

.svws-table-enhanced {
    width: 100%;
    table-layout: fixed;
}

.svws-table-enhanced th {
    position: relative;
}

.svws-sort-link {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    color: inherit;
    text-decoration: none;
}

.svws-sort-link:hover {
    text-decoration: underline;
}

.svws-sort-indicator {
    font-size: 11px;
    color: #5e7388;
}

.svws-col-resizer {
    position: absolute;
    top: 0;
    right: 0;
    width: 8px;
    height: 100%;
    cursor: col-resize;
    user-select: none;
}

.svws-col-resizer::after {
    content: '';
    position: absolute;
    right: 2px;
    top: 6px;
    bottom: 6px;
    width: 1px;
    background: #d3dce6;
}

.svws-pagination {
    margin-top: 10px;
    display: flex;
    gap: 6px;
    align-items: center;
    flex-wrap: wrap;
}

.svws-table-action {
    text-align: left;
    white-space: nowrap;
}

.svws-action-btn {
    text-decoration: none;
    display: inline-block;
    color: inherit;
}

.svws-action-btn:visited,
.svws-action-btn:hover,
.svws-action-btn:active {
    color: inherit;
    text-decoration: none;
}
</style>
<div class="svws-content-header">
    <div class="svws-avatar">D</div>
    <div>
        <p class="svws-title-main">SVWS Stammdaten</p>
        <div class="svws-title-sub">Schüler, Lehrkräfte, Klassen und Lerngruppen aus API-Sync</div>
    </div>
</div>

<div class="svws-tabs svws-tabs-svws">
    <a class="svws-tab <?= $tab === 'students' ? 'active' : '' ?>" href="/sync_data.php?tab=students">Schüler</a>
    <a class="svws-tab <?= $tab === 'teachers' ? 'active' : '' ?>" href="/sync_data.php?tab=teachers">Lehrkräfte</a>
    <a class="svws-tab <?= $tab === 'classes' ? 'active' : '' ?>" href="/sync_data.php?tab=classes">Klassen</a>
    <a class="svws-tab <?= $tab === 'groups' ? 'active' : '' ?>" href="/sync_data.php?tab=groups">Lerngruppen</a>
</div>

<section class="svws-panel" style="margin-bottom:8px;">
    <div class="svws-panel-header">
        <h3>Übersicht</h3>
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
            <tr><td>Schüler</td><td><?= htmlspecialchars((string) $overview['students']) ?></td></tr>
            <tr><td>Lehrkräfte</td><td><?= htmlspecialchars((string) $overview['teachers']) ?></td></tr>
            <tr><td>Klassen</td><td><?= htmlspecialchars((string) $overview['classes']) ?></td></tr>
            <tr><td>Lerngruppen</td><td><?= htmlspecialchars((string) $overview['groups']) ?></td></tr>
            </tbody>
        </table>
    </div>
</section>

<section class="svws-panel">
    <div class="svws-panel-header">
        <h3>Liste</h3>
        <span class="svws-muted">Vorbereitung für Nachfolgefunktionen statt CSV-Import</span>
    </div>
    <div class="svws-panel-body">
        <form method="get" style="display:flex; gap:6px; margin-bottom:8px; align-items:center; flex-wrap:wrap;">
            <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">
            <input type="hidden" name="sort" value="<?= htmlspecialchars($sort) ?>">
            <input type="hidden" name="dir" value="<?= htmlspecialchars($dir) ?>">
            <input type="hidden" name="page" value="1">
            <input class="svws-search" type="search" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Suche">
            <button class="svws-help-btn svws-btn-modern" type="submit">Filtern</button>
            <label class="svws-muted" style="display:inline-flex; align-items:center; gap:4px; margin-left:8px;">
                Pro Seite
                <select class="svws-search" name="per_page" style="width:auto;">
                    <?php foreach ($allowedPerPage as $opt): ?>
                        <option value="<?= $opt ?>" <?= $perPage === $opt ? 'selected' : '' ?>><?= $opt ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <a class="svws-help-btn svws-btn-modern" href="/sync_svws.php" style="text-decoration:none;display:inline-block;">Neu synchronisieren</a>
        </form>

        <?php if ($tab === 'students'): ?>
            <div class="svws-table-wrap">
            <table class="svws-tight svws-table-enhanced" data-resizable-table="students">
                <thead>
                <tr>
                    <th><a class="svws-sort-link" href="<?= htmlspecialchars($sortLink('svws_id')) ?>">SVWS-ID <span class="svws-sort-indicator"><?= $sortIndicator('svws_id') ?></span></a><span class="svws-col-resizer" aria-hidden="true"></span></th>
                    <th><a class="svws-sort-link" href="<?= htmlspecialchars($sortLink('nachname')) ?>">Nachname <span class="svws-sort-indicator"><?= $sortIndicator('nachname') ?></span></a><span class="svws-col-resizer" aria-hidden="true"></span></th>
                    <th><a class="svws-sort-link" href="<?= htmlspecialchars($sortLink('vorname')) ?>">Vorname <span class="svws-sort-indicator"><?= $sortIndicator('vorname') ?></span></a><span class="svws-col-resizer" aria-hidden="true"></span></th>
                    <th><a class="svws-sort-link" href="<?= htmlspecialchars($sortLink('klasse')) ?>">Klasse <span class="svws-sort-indicator"><?= $sortIndicator('klasse') ?></span></a><span class="svws-col-resizer" aria-hidden="true"></span></th>
                    <th><a class="svws-sort-link" href="<?= htmlspecialchars($sortLink('status')) ?>">Status <span class="svws-sort-indicator"><?= $sortIndicator('status') ?></span></a><span class="svws-col-resizer" aria-hidden="true"></span></th>
                    <th>Aktion</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($students as $row): ?>
                    <?php $borrowerUrl = !empty($row['borrower_id']) ? '/lending.php?' . http_build_query(['borrower_id' => (int) $row['borrower_id']]) : ''; ?>
                    <tr>
                        <td><?= htmlspecialchars((string) $row['svws_id']) ?></td>
                        <td><?= htmlspecialchars((string) $row['nachname']) ?></td>
                        <td><?= htmlspecialchars((string) $row['vorname']) ?></td>
                        <td><?= htmlspecialchars((string) ($row['klasse'] ?? '')) ?></td>
                        <td><?= htmlspecialchars((string) ($row['status'] ?? '')) ?></td>
                        <td class="svws-table-action">
                            <?php if ($borrowerUrl !== ''): ?>
                                <a class="svws-help-btn svws-btn-modern svws-action-btn" href="<?= htmlspecialchars($borrowerUrl) ?>">Zur Ausleihe</a>
                            <?php else: ?>
                                <span class="svws-muted">-</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($students === []): ?>
                    <tr><td colspan="6" class="svws-muted">Keine Schülerdaten vorhanden.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
            </div>
        <?php endif; ?>

        <?php if ($tab === 'teachers'): ?>
            <div class="svws-table-wrap">
            <table class="svws-tight svws-table-enhanced" data-resizable-table="teachers">
                <thead>
                <tr>
                    <th><a class="svws-sort-link" href="<?= htmlspecialchars($sortLink('svws_id')) ?>">SVWS-ID <span class="svws-sort-indicator"><?= $sortIndicator('svws_id') ?></span></a><span class="svws-col-resizer" aria-hidden="true"></span></th>
                    <th><a class="svws-sort-link" href="<?= htmlspecialchars($sortLink('kuerzel')) ?>">Kürzel <span class="svws-sort-indicator"><?= $sortIndicator('kuerzel') ?></span></a><span class="svws-col-resizer" aria-hidden="true"></span></th>
                    <th><a class="svws-sort-link" href="<?= htmlspecialchars($sortLink('nachname')) ?>">Nachname <span class="svws-sort-indicator"><?= $sortIndicator('nachname') ?></span></a><span class="svws-col-resizer" aria-hidden="true"></span></th>
                    <th><a class="svws-sort-link" href="<?= htmlspecialchars($sortLink('vorname')) ?>">Vorname <span class="svws-sort-indicator"><?= $sortIndicator('vorname') ?></span></a><span class="svws-col-resizer" aria-hidden="true"></span></th>
                    <th><a class="svws-sort-link" href="<?= htmlspecialchars($sortLink('email')) ?>">E-Mail <span class="svws-sort-indicator"><?= $sortIndicator('email') ?></span></a><span class="svws-col-resizer" aria-hidden="true"></span></th>
                    <th>Aktion</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($teachers as $row): ?>
                    <?php $borrowerUrl = !empty($row['borrower_id']) ? '/lending.php?' . http_build_query(['borrower_id' => (int) $row['borrower_id']]) : ''; ?>
                    <tr>
                        <td><?= htmlspecialchars((string) $row['svws_id']) ?></td>
                        <td><?= htmlspecialchars((string) ($row['kuerzel'] ?? '')) ?></td>
                        <td><?= htmlspecialchars((string) $row['nachname']) ?></td>
                        <td><?= htmlspecialchars((string) $row['vorname']) ?></td>
                        <td><?= htmlspecialchars((string) ($row['email'] ?? '')) ?></td>
                        <td class="svws-table-action">
                            <?php if ($borrowerUrl !== ''): ?>
                                <a class="svws-help-btn svws-btn-modern svws-action-btn" href="<?= htmlspecialchars($borrowerUrl) ?>">Zur Ausleihe</a>
                            <?php else: ?>
                                <span class="svws-muted">-</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($teachers === []): ?>
                    <tr><td colspan="6" class="svws-muted">Keine Lehrerdaten vorhanden.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
            </div>
        <?php endif; ?>

        <?php if ($tab === 'classes'): ?>
            <div class="svws-table-wrap">
            <table class="svws-tight svws-table-enhanced" data-resizable-table="classes">
                <thead>
                <tr>
                    <th><a class="svws-sort-link" href="<?= htmlspecialchars($sortLink('svws_id')) ?>">SVWS-ID <span class="svws-sort-indicator"><?= $sortIndicator('svws_id') ?></span></a><span class="svws-col-resizer" aria-hidden="true"></span></th>
                    <th><a class="svws-sort-link" href="<?= htmlspecialchars($sortLink('kuerzel')) ?>">Kürzel <span class="svws-sort-indicator"><?= $sortIndicator('kuerzel') ?></span></a><span class="svws-col-resizer" aria-hidden="true"></span></th>
                    <th><a class="svws-sort-link" href="<?= htmlspecialchars($sortLink('name')) ?>">Bezeichnung <span class="svws-sort-indicator"><?= $sortIndicator('name') ?></span></a><span class="svws-col-resizer" aria-hidden="true"></span></th>
                    <th><a class="svws-sort-link" href="<?= htmlspecialchars($sortLink('jahrgang')) ?>">Jahrgang <span class="svws-sort-indicator"><?= $sortIndicator('jahrgang') ?></span></a><span class="svws-col-resizer" aria-hidden="true"></span></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($classes as $row): ?>
                    <tr>
                        <td><?= htmlspecialchars((string) $row['svws_id']) ?></td>
                        <td><?= htmlspecialchars((string) ($row['kuerzel'] ?? '')) ?></td>
                        <td><?= htmlspecialchars((string) ($row['name'] ?? '')) ?></td>
                        <td><?= htmlspecialchars((string) ($row['jahrgang'] ?? '')) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($classes === []): ?>
                    <tr><td colspan="4" class="svws-muted">Keine Klassendaten vorhanden.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
            </div>
        <?php endif; ?>

        <?php if ($tab === 'groups'): ?>
            <div class="svws-table-wrap">
            <table class="svws-tight svws-table-enhanced" data-resizable-table="groups">
                <thead>
                <tr>
                    <th><a class="svws-sort-link" href="<?= htmlspecialchars($sortLink('svws_id')) ?>">SVWS-ID <span class="svws-sort-indicator"><?= $sortIndicator('svws_id') ?></span></a><span class="svws-col-resizer" aria-hidden="true"></span></th>
                    <th><a class="svws-sort-link" href="<?= htmlspecialchars($sortLink('kuerzel')) ?>">Kürzel <span class="svws-sort-indicator"><?= $sortIndicator('kuerzel') ?></span></a><span class="svws-col-resizer" aria-hidden="true"></span></th>
                    <th><a class="svws-sort-link" href="<?= htmlspecialchars($sortLink('name')) ?>">Bezeichnung <span class="svws-sort-indicator"><?= $sortIndicator('name') ?></span></a><span class="svws-col-resizer" aria-hidden="true"></span></th>
                    <th>Unterricht</th>
                    <th><a class="svws-sort-link" href="<?= htmlspecialchars($sortLink('jahrgang')) ?>">Jahrgang <span class="svws-sort-indicator"><?= $sortIndicator('jahrgang') ?></span></a><span class="svws-col-resizer" aria-hidden="true"></span></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($groups as $row): ?>
                    <tr>
                        <td><?= htmlspecialchars((string) $row['svws_id']) ?></td>
                        <td><?= htmlspecialchars((string) ($row['kuerzel'] ?? '')) ?></td>
                        <td><?= htmlspecialchars((string) ($row['name'] ?? '')) ?></td>
                        <td><?= htmlspecialchars((string) ($row['unterrichtstyp'] ?? 'Lerngruppe')) ?></td>
                        <td><?= htmlspecialchars((string) ($row['jahrgang'] ?? '')) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($groups === []): ?>
                    <tr><td colspan="5" class="svws-muted">Keine Lerngruppen vorhanden.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
            </div>
        <?php endif; ?>

        <div class="svws-pagination">
            <span class="svws-muted">Seite <?= $page ?> von <?= $totalPages ?> (<?= $totalRows ?> Einträge)</span>
            <?php if ($page > 1): ?>
                <a class="svws-help-btn svws-btn-modern" style="text-decoration:none;display:inline-block;" href="<?= htmlspecialchars($buildUrl(['page' => 1])) ?>">« Erste</a>
                <a class="svws-help-btn svws-btn-modern" style="text-decoration:none;display:inline-block;" href="<?= htmlspecialchars($buildUrl(['page' => $page - 1])) ?>">‹ Zurück</a>
            <?php endif; ?>
            <?php if ($page < $totalPages): ?>
                <a class="svws-help-btn svws-btn-modern" style="text-decoration:none;display:inline-block;" href="<?= htmlspecialchars($buildUrl(['page' => $page + 1])) ?>">Weiter ›</a>
                <a class="svws-help-btn svws-btn-modern" style="text-decoration:none;display:inline-block;" href="<?= htmlspecialchars($buildUrl(['page' => $totalPages])) ?>">Letzte »</a>
            <?php endif; ?>
        </div>

    </div>
</section>
<script>
(function () {
    function initResizableTable(table) {
        var tableKey = table.getAttribute('data-resizable-table');
        var headers = table.querySelectorAll('thead th');

        headers.forEach(function (th, index) {
            var saved = window.localStorage.getItem('svws-colwidth-' + tableKey + '-' + index);
            if (saved) {
                th.style.width = saved + 'px';
            }

            var handle = th.querySelector('.svws-col-resizer');
            if (!handle) {
                return;
            }

            handle.addEventListener('mousedown', function (event) {
                event.preventDefault();
                var startX = event.pageX;
                var startWidth = th.offsetWidth;

                function onMove(moveEvent) {
                    var newWidth = Math.max(90, startWidth + (moveEvent.pageX - startX));
                    th.style.width = newWidth + 'px';
                }

                function onUp() {
                    var finalWidth = th.offsetWidth;
                    window.localStorage.setItem('svws-colwidth-' + tableKey + '-' + index, String(finalWidth));
                    document.removeEventListener('mousemove', onMove);
                    document.removeEventListener('mouseup', onUp);
                }

                document.addEventListener('mousemove', onMove);
                document.addEventListener('mouseup', onUp);
            });
        });
    }

    document.querySelectorAll('table[data-resizable-table]').forEach(initResizableTable);
}());
</script>
<?php
$content = ob_get_clean();

renderLayout('SVWS Daten', $content, 'data');
