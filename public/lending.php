<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/config/config.php';
require_once __DIR__ . '/../src/auth/user.php';
require_once __DIR__ . '/../src/modules/lending/lending_service.php';
require_once __DIR__ . '/../templates/layout.php';

requireLogin();

$currentUser = getCurrentUser();
$actorUserId = (int) ($currentUser['id'] ?? 0);

$borrowerSearch = trim((string) ($_GET['borrower_q'] ?? ''));
$selectedBorrowerId = max(0, (int) ($_GET['borrower_id'] ?? 0));
$unknownBarcode = trim((string) ($_GET['unknown_barcode'] ?? ''));
$flashType = trim((string) ($_GET['flash_type'] ?? ''));
$flashMessage = trim((string) ($_GET['flash_message'] ?? ''));
$borrowerSort = (string) ($_GET['borrower_sort'] ?? 'name');
$borrowerDir = strtolower((string) ($_GET['borrower_dir'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';
$openSort = (string) ($_GET['open_sort'] ?? 'since');
$openDir = strtolower((string) ($_GET['open_dir'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';

$allowedBorrowerSort = ['name', 'klasse', 'typ'];
if (!in_array($borrowerSort, $allowedBorrowerSort, true)) {
    $borrowerSort = 'name';
}

$allowedOpenSort = ['id', 'title', 'barcode', 'borrower', 'class_course', 'since'];
if (!in_array($openSort, $allowedOpenSort, true)) {
    $openSort = 'since';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrfToken();

    $action = (string) ($_POST['action'] ?? '');
    $borrowerSearch = trim((string) ($_POST['borrower_q'] ?? $borrowerSearch));
    $selectedBorrowerId = max(0, (int) ($_POST['borrower_id'] ?? $selectedBorrowerId));
    $unknownBarcode = trim((string) ($_POST['unknown_barcode'] ?? ''));
    $borrowerSort = (string) ($_POST['borrower_sort'] ?? $borrowerSort);
    $borrowerDir = strtolower((string) ($_POST['borrower_dir'] ?? $borrowerDir)) === 'desc' ? 'desc' : 'asc';
    $openSort = (string) ($_POST['open_sort'] ?? $openSort);
    $openDir = strtolower((string) ($_POST['open_dir'] ?? $openDir)) === 'asc' ? 'asc' : 'desc';
    if (!in_array($borrowerSort, $allowedBorrowerSort, true)) {
        $borrowerSort = 'name';
    }
    if (!in_array($openSort, $allowedOpenSort, true)) {
        $openSort = 'since';
    }

    $schoolYear = (int) ($_POST['school_year'] ?? (int) gmdate('Y'));
    $klasse = trim((string) ($_POST['klasse'] ?? ''));
    $kurs = trim((string) ($_POST['kurs'] ?? ''));
    $kursLehrer = trim((string) ($_POST['kurs_lehrer'] ?? ''));

    try {
        if ($action === 'scan') {
            $result = LendingService::processScan(
                (string) ($_POST['barcode'] ?? ''),
                $selectedBorrowerId,
                $actorUserId,
                [
                    'school_year' => $schoolYear,
                    'klasse' => $klasse,
                    'kurs' => $kurs,
                    'kurs_lehrer' => $kursLehrer,
                ]
            );
            if (($result['status'] ?? '') === 'unknown_barcode') {
                $unknownBarcode = (string) ($result['barcode'] ?? '');
                $flashType = 'error';
            } else {
                $unknownBarcode = '';
                $flashType = 'success';
            }
            $flashMessage = (string) ($result['message'] ?? 'Vorgang abgeschlossen.');
        } elseif ($action === 'assign_unknown') {
            $result = LendingService::assignUnknownBarcode(
                (string) ($_POST['unknown_barcode'] ?? ''),
                (int) ($_POST['title_id'] ?? 0),
                (string) ($_POST['inventory_number'] ?? ''),
                (string) ($_POST['condition'] ?? ''),
                (string) ($_POST['memo'] ?? ''),
                $selectedBorrowerId,
                $actorUserId,
                [
                    'school_year' => $schoolYear,
                    'klasse' => $klasse,
                    'kurs' => $kurs,
                    'kurs_lehrer' => $kursLehrer,
                ]
            );
            $unknownBarcode = '';
            $flashType = 'success';
            $flashMessage = (string) ($result['message'] ?? 'Barcode wurde zugeordnet.');
        } elseif ($action === 'return') {
            $result = LendingService::returnByBarcode((string) ($_POST['barcode'] ?? ''), $actorUserId);
            $unknownBarcode = '';
            $flashType = 'success';
            $flashMessage = 'Rückgabe verbucht (ID ' . (int) ($result['lending_id'] ?? 0) . ').';
        } elseif ($action === 'return_lending') {
            $result = LendingService::returnByLendingId((int) ($_POST['lending_id'] ?? 0), $actorUserId);
            $unknownBarcode = '';
            $flashType = 'success';
            $flashMessage = 'Rückgabe verbucht (ID ' . (int) ($result['lending_id'] ?? 0) . ').';
        }
    } catch (Throwable $e) {
        $flashType = 'error';
        $flashMessage = $e->getMessage();
    }

    $redirectQuery = http_build_query([
        'borrower_q' => $borrowerSearch,
        'borrower_id' => $selectedBorrowerId,
        'unknown_barcode' => $unknownBarcode,
        'borrower_sort' => $borrowerSort,
        'borrower_dir' => $borrowerDir,
        'open_sort' => $openSort,
        'open_dir' => $openDir,
        'flash_type' => $flashType,
        'flash_message' => $flashMessage,
    ]);
    header('Location: /lending.php?' . $redirectQuery);
    exit;
}

$borrowers = LendingService::getBorrowers($borrowerSearch, 60);

$buildBorrowerSortUrl = static function (array $overrides = []) use ($borrowerSearch, $selectedBorrowerId, $unknownBarcode, $borrowerSort, $borrowerDir, $openSort, $openDir): string {
    $params = array_merge([
        'borrower_q' => $borrowerSearch,
        'borrower_id' => $selectedBorrowerId,
        'unknown_barcode' => $unknownBarcode,
        'borrower_sort' => $borrowerSort,
        'borrower_dir' => $borrowerDir,
        'open_sort' => $openSort,
        'open_dir' => $openDir,
    ], $overrides);

    return '/lending.php?' . http_build_query($params);
};

$borrowerSortLink = static function (string $column) use ($borrowerSort, $borrowerDir, $buildBorrowerSortUrl): string {
    $nextDir = ($borrowerSort === $column && $borrowerDir === 'asc') ? 'desc' : 'asc';
    return $buildBorrowerSortUrl([
        'borrower_sort' => $column,
        'borrower_dir' => $nextDir,
    ]);
};

$borrowerSortIndicator = static function (string $column) use ($borrowerSort, $borrowerDir): string {
    if ($borrowerSort !== $column) {
        return '↕';
    }

    return $borrowerDir === 'asc' ? '↑' : '↓';
};

usort($borrowers, static function (array $a, array $b) use ($borrowerSort, $borrowerDir): int {
    $nameA = trim((string) ($a['display_name'] ?? ''));
    if ($nameA === '') {
        $nameA = trim((string) ($a['nachname'] ?? '') . ', ' . (string) ($a['vorname'] ?? ''), ' ,');
    }

    $nameB = trim((string) ($b['display_name'] ?? ''));
    if ($nameB === '') {
        $nameB = trim((string) ($b['nachname'] ?? '') . ', ' . (string) ($b['vorname'] ?? ''), ' ,');
    }

    $valueA = match ($borrowerSort) {
        'klasse' => mb_strtolower((string) ($a['klasse'] ?? '')),
        'typ' => mb_strtolower((string) ($a['kind'] ?? '')),
        default => mb_strtolower($nameA),
    };

    $valueB = match ($borrowerSort) {
        'klasse' => mb_strtolower((string) ($b['klasse'] ?? '')),
        'typ' => mb_strtolower((string) ($b['kind'] ?? '')),
        default => mb_strtolower($nameB),
    };

    $comparison = $valueA <=> $valueB;
    return $borrowerDir === 'asc' ? $comparison : -$comparison;
});

if ($selectedBorrowerId === 0 && $borrowers !== []) {
    $selectedBorrowerId = (int) $borrowers[0]['id'];
}
$selectedBorrower = LendingService::getBorrowerById($selectedBorrowerId);
$titles = LendingService::getTitles('', 200);
$openLendings = LendingService::getOpenLendings();

$buildOpenSortUrl = static function (array $overrides = []) use ($borrowerSearch, $selectedBorrowerId, $unknownBarcode, $openSort, $openDir): string {
    $params = array_merge([
        'borrower_q' => $borrowerSearch,
        'borrower_id' => $selectedBorrowerId,
        'unknown_barcode' => $unknownBarcode,
        'open_sort' => $openSort,
        'open_dir' => $openDir,
    ], $overrides);

    return '/lending.php?' . http_build_query($params);
};

$openSortLink = static function (string $column) use ($openSort, $openDir, $buildOpenSortUrl): string {
    $nextDir = ($openSort === $column && $openDir === 'asc') ? 'desc' : 'asc';
    return $buildOpenSortUrl([
        'open_sort' => $column,
        'open_dir' => $nextDir,
    ]);
};

$openSortIndicator = static function (string $column) use ($openSort, $openDir): string {
    if ($openSort !== $column) {
        return '↕';
    }

    return $openDir === 'asc' ? '↑' : '↓';
};

usort($openLendings, static function (array $a, array $b) use ($openSort, $openDir): int {
    $classCourseA = trim((string) ($a['klasse_snapshot'] ?? '') . ' ' . (string) ($a['kurs_snapshot'] ?? ''));
    $classCourseB = trim((string) ($b['klasse_snapshot'] ?? '') . ' ' . (string) ($b['kurs_snapshot'] ?? ''));

    $valueA = match ($openSort) {
        'id' => (int) ($a['id'] ?? 0),
        'title' => mb_strtolower((string) ($a['title'] ?? '')),
        'barcode' => mb_strtolower((string) ($a['barcode'] ?? '')),
        'borrower' => mb_strtolower((string) ($a['borrower_name'] ?? '')),
        'class_course' => mb_strtolower($classCourseA),
        'since' => (string) ($a['borrowed_at'] ?? ''),
        default => (string) ($a['borrowed_at'] ?? ''),
    };

    $valueB = match ($openSort) {
        'id' => (int) ($b['id'] ?? 0),
        'title' => mb_strtolower((string) ($b['title'] ?? '')),
        'barcode' => mb_strtolower((string) ($b['barcode'] ?? '')),
        'borrower' => mb_strtolower((string) ($b['borrower_name'] ?? '')),
        'class_course' => mb_strtolower($classCourseB),
        'since' => (string) ($b['borrowed_at'] ?? ''),
        default => (string) ($b['borrowed_at'] ?? ''),
    };

    $comparison = $valueA <=> $valueB;
    return $openDir === 'asc' ? $comparison : -$comparison;
});

ob_start();
?>
<style>
.svws-table-wrap {
    overflow: auto;
}

.svws-open-table {
    width: 100%;
    table-layout: fixed;
}

.svws-open-table th {
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

.svws-open-action {
    white-space: nowrap;
}

.svws-detail-stack {
    display: grid;
    gap: 5px;
    margin-top: 12px;
}

.svws-content-header-main {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    flex-wrap: wrap;
    width: 100%;
}

.svws-header-status {
    margin: 0;
    padding: 7px 12px;
    border-radius: 8px;
    font-size: 16px;
    font-weight: 700;
    line-height: 1.25;
    border: 2px solid transparent;
    background: #e8f4e8;
    color: #0f5a1d;
    box-shadow: 0 3px 10px rgba(9, 70, 18, 0.15);
}

.svws-header-status--success {
    background: #e8f4e8;
    color: #0f5a1d;
    border-color: #8ebf94;
}

.svws-header-status--error {
    background: #fde9e9;
    color: #8f0e0e;
    border-color: #d79a9a;
    box-shadow: 0 3px 10px rgba(120, 20, 20, 0.16);
}

.svws-lending-list-panel .svws-panel-body {
    background: #f6f8fc;
}

.svws-lending-list-panel .svws-list {
    background: #fcfdff;
}

.svws-lending-list-panel .svws-list th {
    background: #edf3fb;
}

.svws-lending-list-panel .svws-list a {
    color: #173f74;
    text-decoration: none;
}

.svws-lending-list-panel .svws-list a:hover {
    color: #0f2e54;
    text-decoration: none;
}

.svws-section-bar {
    margin: 2px 0 0;
    padding: 7px 10px;
    color: #fff;
    font-weight: 700;
    border-radius: 6px;
}

.svws-section-card {
    border: 3px solid;
    border-radius: 10px;
    padding: 12px;
    margin: 0;
    background: #fff;
}

.svws-section-card--blue {
    border-color: #2f7dd1;
}

.svws-section-card--green {
    border-color: #2f9d66;
}

.svws-section-card--orange {
    border-color: #d67e1f;
}

.svws-section-card--purple {
    border-color: #7b5ec8;
}

.svws-section-row {
    display: flex;
    gap: 6px;
    align-items: center;
}

@media (max-width: 900px) {
    .svws-header-status {
        width: 100%;
    }

    .svws-section-row {
        flex-wrap: wrap;
    }
}
</style>
<div class="svws-split">
    <section class="svws-panel svws-lending-list-panel">
        <div class="svws-panel-header">
            <h3>Ausleiher</h3>
            <span class="svws-muted">SVWS + Sonstige</span>
        </div>
        <div class="svws-panel-body svws-detail-stack">
            <form method="get" style="display:flex; gap:6px; margin-bottom:8px; align-items:center;">
                <input class="svws-search" type="search" name="borrower_q" value="<?= htmlspecialchars($borrowerSearch) ?>" placeholder="Name/Klasse suchen">
                <button class="svws-help-btn svws-btn-modern" type="submit">Suchen</button>
            </form>

            <table class="svws-list">
                <thead>
                <tr>
                    <th style="width:56%;"><a class="svws-sort-link" href="<?= htmlspecialchars($borrowerSortLink('name')) ?>">Name <span class="svws-sort-indicator"><?= $borrowerSortIndicator('name') ?></span></a></th>
                    <th style="width:22%;"><a class="svws-sort-link" href="<?= htmlspecialchars($borrowerSortLink('klasse')) ?>">Klasse <span class="svws-sort-indicator"><?= $borrowerSortIndicator('klasse') ?></span></a></th>
                    <th style="width:22%;"><a class="svws-sort-link" href="<?= htmlspecialchars($borrowerSortLink('typ')) ?>">Typ <span class="svws-sort-indicator"><?= $borrowerSortIndicator('typ') ?></span></a></th>
                </tr>
                </thead>
                <tbody>
                <?php if ($borrowers === []): ?>
                    <tr><td colspan="3" class="svws-muted">Keine Ausleiher gefunden.</td></tr>
                <?php else: ?>
                    <?php foreach ($borrowers as $b): ?>
                        <?php
                        $name = trim((string) ($b['display_name'] ?? ''));
                        if ($name === '') {
                            $name = trim((string) ($b['nachname'] ?? '') . ', ' . (string) ($b['vorname'] ?? ''), ' ,');
                        }
                        ?>
                        <tr class="<?= (int) $b['id'] === $selectedBorrowerId ? 'svws-row-active' : '' ?>">
                            <td>
                                <a href="/lending.php?<?= htmlspecialchars(http_build_query([
                                    'borrower_q' => $borrowerSearch,
                                    'borrower_id' => (int) $b['id'],
                                    'borrower_sort' => $borrowerSort,
                                    'borrower_dir' => $borrowerDir,
                                    'open_sort' => $openSort,
                                    'open_dir' => $openDir,
                                ])) ?>">
                                    <?= htmlspecialchars($name) ?>
                                </a>
                            </td>
                            <td><?= htmlspecialchars((string) ($b['klasse'] ?? '')) ?></td>
                            <td><?= htmlspecialchars((string) $b['kind']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>

            <div class="svws-grid-note"><?= count($borrowers) ?> Ausleiher</div>
        </div>
    </section>

    <section class="svws-panel">
        <div class="svws-panel-body svws-detail-stack">
            <div class="svws-content-header">
                <div class="svws-avatar">L</div>
                <div class="svws-content-header-main">
                    <div>
                        <p class="svws-title-main">Ausleihe & Rückgabe</p>
                        <div class="svws-title-sub">Barcode-Scan mit Rückgabe oder Ausgabe</div>
                    </div>
                    <?php if ($flashMessage !== ''): ?>
                        <p class="svws-header-status svws-header-status--<?= $flashType === 'error' ? 'error' : 'success' ?>">
                            <?= htmlspecialchars($flashMessage) ?>
                        </p>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($selectedBorrower === null): ?>
                <div class="svws-section-bar" style="background:#2f7dd1;">Ausleihe</div>
                <fieldset class="svws-section-card svws-section-card--blue">
                    <p class="svws-muted" style="margin:0;">Bitte links einen Ausleiher auswählen.</p>
                </fieldset>
            <?php else: ?>
                <?php
                $selectedBorrowerName = trim((string) ($selectedBorrower['display_name'] ?? ''));
                if ($selectedBorrowerName === '') {
                    $selectedBorrowerName = trim((string) ($selectedBorrower['nachname'] ?? '') . ', ' . (string) ($selectedBorrower['vorname'] ?? ''), ' ,');
                }
                ?>
                <div class="svws-grid-note" style="margin-bottom: 8px;">
                    Ausleiher: <?= htmlspecialchars($selectedBorrowerName) ?> |
                    Typ: <?= htmlspecialchars((string) $selectedBorrower['kind']) ?> |
                    Klasse: <?= htmlspecialchars((string) ($selectedBorrower['klasse'] ?? '')) ?>
                </div>

                <div class="svws-section-bar" style="background:#2f7dd1;">Ausleihe per Scan</div>
                <fieldset class="svws-section-card svws-section-card--blue">
                <form method="post" style="display:grid; grid-template-columns:repeat(2,minmax(220px,1fr)); gap:8px; margin-bottom:8px;">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="scan">
                    <input type="hidden" name="borrower_q" value="<?= htmlspecialchars($borrowerSearch) ?>">
                    <input type="hidden" name="borrower_id" value="<?= (int) $selectedBorrowerId ?>">
                    <input type="hidden" name="unknown_barcode" value="<?= htmlspecialchars($unknownBarcode) ?>">

                    <label style="grid-column:1 / -1;">
                        <span class="svws-muted">Barcode scannen</span><br>
                        <input class="svws-search" type="text" name="barcode" placeholder="Scan oder Eingabe" autofocus required>
                    </label>
                    <label>
                        <span class="svws-muted">Schuljahr</span><br>
                        <input class="svws-search" type="number" name="school_year" value="<?= htmlspecialchars((string) gmdate('Y')) ?>">
                    </label>
                    <label>
                        <span class="svws-muted">Klasse (Snapshot)</span><br>
                        <input class="svws-search" type="text" name="klasse" value="<?= htmlspecialchars((string) ($selectedBorrower['klasse'] ?? '')) ?>">
                    </label>
                    <label>
                        <span class="svws-muted">Kurs (optional)</span><br>
                        <input class="svws-search" type="text" name="kurs">
                    </label>
                    <label>
                        <span class="svws-muted">Kurs-Lehrkraft (optional)</span><br>
                        <input class="svws-search" type="text" name="kurs_lehrer">
                    </label>
                    <div>
                        <button class="svws-help-btn svws-btn-modern" type="submit">Scan verarbeiten</button>
                    </div>
                </form>
                </fieldset>

                <div class="svws-section-bar" style="background:#2f9d66;">Direkte Rückgabe</div>
                <fieldset class="svws-section-card svws-section-card--green">
                <form method="post" class="svws-section-row" style="margin-bottom:0;">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="return">
                    <input type="hidden" name="borrower_q" value="<?= htmlspecialchars($borrowerSearch) ?>">
                    <input type="hidden" name="borrower_id" value="<?= (int) $selectedBorrowerId ?>">
                    <input class="svws-search" type="text" name="barcode" placeholder="Barcode für direkte Rückgabe" required>
                    <button class="svws-help-btn svws-btn-modern" type="submit">Direkte Rückgabe</button>
                </form>
                </fieldset>

                <?php if ($unknownBarcode !== ''): ?>
                    <div class="svws-section-bar" style="background:#d67e1f;">Unbekannter Barcode: <?= htmlspecialchars($unknownBarcode) ?></div>
                    <fieldset class="svws-section-card svws-section-card--orange">
                            <form method="post" style="display:grid; grid-template-columns:repeat(2,minmax(220px,1fr)); gap:8px;">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="assign_unknown">
                                <input type="hidden" name="borrower_q" value="<?= htmlspecialchars($borrowerSearch) ?>">
                                <input type="hidden" name="borrower_id" value="<?= (int) $selectedBorrowerId ?>">
                                <input type="hidden" name="unknown_barcode" value="<?= htmlspecialchars($unknownBarcode) ?>">
                                <input type="hidden" name="school_year" value="<?= htmlspecialchars((string) gmdate('Y')) ?>">
                                <input type="hidden" name="klasse" value="<?= htmlspecialchars((string) ($selectedBorrower['klasse'] ?? '')) ?>">
                                <input type="hidden" name="kurs" value="">
                                <input type="hidden" name="kurs_lehrer" value="">

                                <label>
                                    <span class="svws-muted">Titel</span><br>
                                    <select class="svws-filter" name="title_id" required>
                                        <option value="">Bitte wählen</option>
                                        <?php foreach ($titles as $title): ?>
                                            <option value="<?= (int) $title['id'] ?>">
                                                <?= htmlspecialchars((string) $title['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>
                                <label>
                                    <span class="svws-muted">Inventar-Nr.</span><br>
                                    <input class="svws-search" type="text" name="inventory_number">
                                </label>
                                <label>
                                    <span class="svws-muted">Zustand</span><br>
                                    <input class="svws-search" type="text" name="condition">
                                </label>
                                <label>
                                    <span class="svws-muted">Memo</span><br>
                                    <input class="svws-search" type="text" name="memo">
                                </label>
                                <div>
                                    <button class="svws-help-btn svws-btn-modern" type="submit">Zuordnen und ausleihen</button>
                                </div>
                            </form>
                    </fieldset>
                <?php endif; ?>
            <?php endif; ?>

            <div class="svws-section-bar" style="background:#7b5ec8;">Offene Ausleihen</div>
            <fieldset class="svws-section-card svws-section-card--purple">
            <div class="svws-table-wrap">
            <table class="svws-tight svws-open-table" data-resizable-table="open-lendings">
                <thead>
                <tr>
                    <th style="width:8%;"><a class="svws-sort-link" href="<?= htmlspecialchars($openSortLink('id')) ?>">ID <span class="svws-sort-indicator"><?= $openSortIndicator('id') ?></span></a><span class="svws-col-resizer" aria-hidden="true"></span></th>
                    <th style="width:24%;"><a class="svws-sort-link" href="<?= htmlspecialchars($openSortLink('title')) ?>">Titel <span class="svws-sort-indicator"><?= $openSortIndicator('title') ?></span></a><span class="svws-col-resizer" aria-hidden="true"></span></th>
                    <th style="width:14%;"><a class="svws-sort-link" href="<?= htmlspecialchars($openSortLink('barcode')) ?>">Barcode <span class="svws-sort-indicator"><?= $openSortIndicator('barcode') ?></span></a><span class="svws-col-resizer" aria-hidden="true"></span></th>
                    <th style="width:20%;"><a class="svws-sort-link" href="<?= htmlspecialchars($openSortLink('borrower')) ?>">Ausleiher <span class="svws-sort-indicator"><?= $openSortIndicator('borrower') ?></span></a><span class="svws-col-resizer" aria-hidden="true"></span></th>
                    <th style="width:14%;"><a class="svws-sort-link" href="<?= htmlspecialchars($openSortLink('class_course')) ?>">Klasse/Kurs <span class="svws-sort-indicator"><?= $openSortIndicator('class_course') ?></span></a><span class="svws-col-resizer" aria-hidden="true"></span></th>
                    <th style="width:12%;"><a class="svws-sort-link" href="<?= htmlspecialchars($openSortLink('since')) ?>">Seit <span class="svws-sort-indicator"><?= $openSortIndicator('since') ?></span></a><span class="svws-col-resizer" aria-hidden="true"></span></th>
                    <th style="width:8%;">Aktion</th>
                </tr>
                </thead>
                <tbody>
                <?php if ($openLendings === []): ?>
                    <tr><td colspan="7" class="svws-muted">Keine offenen Ausleihen.</td></tr>
                <?php else: ?>
                    <?php foreach ($openLendings as $item): ?>
                        <tr>
                            <td><?= htmlspecialchars((string) $item['id']) ?></td>
                            <td><?= htmlspecialchars((string) $item['title']) ?></td>
                            <td><?= htmlspecialchars((string) ($item['barcode'] ?? '')) ?></td>
                            <td><?= htmlspecialchars((string) ($item['borrower_name'] ?? '')) ?></td>
                            <td>
                                <?= htmlspecialchars((string) ($item['klasse_snapshot'] ?? '')) ?>
                                <?= htmlspecialchars((string) ($item['kurs_snapshot'] ?? '')) ?>
                            </td>
                            <td><?= htmlspecialchars((string) $item['borrowed_at']) ?></td>
                            <td class="svws-open-action">
                                <form method="post" style="display:inline-block; margin:0;">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="return_lending">
                                    <input type="hidden" name="borrower_q" value="<?= htmlspecialchars($borrowerSearch) ?>">
                                    <input type="hidden" name="borrower_id" value="<?= (int) $selectedBorrowerId ?>">
                                    <input type="hidden" name="unknown_barcode" value="<?= htmlspecialchars($unknownBarcode) ?>">
                                    <input type="hidden" name="open_sort" value="<?= htmlspecialchars($openSort) ?>">
                                    <input type="hidden" name="open_dir" value="<?= htmlspecialchars($openDir) ?>">
                                    <input type="hidden" name="lending_id" value="<?= (int) $item['id'] ?>">
                                    <button class="svws-help-btn svws-btn-modern" type="submit">Rückgabe</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
            </div>
            </fieldset>
        </div>
    </section>
</div>
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

renderLayout('Ausleihe', $content, 'lending');
