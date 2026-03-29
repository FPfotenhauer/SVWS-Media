<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/config/config.php';
require_once __DIR__ . '/../src/auth/user.php';
require_once __DIR__ . '/../src/modules/media/media_service.php';
require_once __DIR__ . '/../templates/layout.php';

requireLogin();

$search = trim((string) ($_GET['q'] ?? ''));
$typeFilter = trim((string) ($_GET['type_filter'] ?? ''));
$emptyTypeFilterToken = '__EMPTY__';
$selectedTitleId = max(0, (int) ($_GET['title_id'] ?? 0));
$sortBy = trim((string) ($_GET['sort'] ?? 'title'));
$sortDir = mb_strtolower(trim((string) ($_GET['dir'] ?? 'asc')));
$flashType = trim((string) ($_GET['flash_type'] ?? ''));
$flashMessage = trim((string) ($_GET['flash_message'] ?? ''));
$flashAction = trim((string) ($_GET['flash_action'] ?? ''));

$allowedSortFields = ['title', 'type', 'copy_count'];
if (!in_array($sortBy, $allowedSortFields, true)) {
    $sortBy = 'title';
}
if ($sortDir !== 'asc' && $sortDir !== 'desc') {
    $sortDir = 'asc';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrfToken();

    $action = (string) ($_POST['action'] ?? '');
    $flashAction = $action;
    $selectedTitleId = max(0, (int) ($_POST['title_id'] ?? $selectedTitleId));

    try {
        if ($action === 'create_title') {
            $selectedTitleId = MediaService::createTitle(
                (string) ($_POST['title'] ?? ''),
                (string) ($_POST['type'] ?? ''),
                (string) ($_POST['location'] ?? '')
            );
            $flashType = 'success';
            $flashMessage = 'Titel wurde angelegt.';
        } elseif ($action === 'create_title_from_scan') {
            $selectedTitleId = MediaService::createTitle(
                (string) ($_POST['scan_title'] ?? ''),
                (string) ($_POST['scan_type'] ?? ''),
                (string) ($_POST['scan_location'] ?? '')
            );
            $flashType = 'success';
            $flashMessage = 'Titel wurde per Scan angelegt.';
        } elseif ($action === 'update_title') {
            MediaService::updateTitle(
                $selectedTitleId,
                (string) ($_POST['title'] ?? ''),
                (string) ($_POST['type'] ?? ''),
                (string) ($_POST['location'] ?? '')
            );
            $flashType = 'success';
            $flashMessage = 'Titel wurde aktualisiert.';
        } elseif ($action === 'delete_title') {
            MediaService::deleteTitle($selectedTitleId);
            $selectedTitleId = 0;
            $flashType = 'success';
            $flashMessage = 'Titel wurde geloescht.';
        } elseif ($action === 'create_copy') {
            MediaService::createCopy(
                $selectedTitleId,
                (string) ($_POST['barcode'] ?? ''),
                (string) ($_POST['inventory_number'] ?? ''),
                (string) ($_POST['condition'] ?? ''),
                (string) ($_POST['memo'] ?? '')
            );
            $flashType = 'success';
            $flashMessage = 'Exemplar wurde angelegt.';
        } elseif ($action === 'update_copy') {
            MediaService::updateCopy(
                (int) ($_POST['copy_id'] ?? 0),
                (string) ($_POST['barcode'] ?? ''),
                (string) ($_POST['inventory_number'] ?? ''),
                (string) ($_POST['condition'] ?? ''),
                (string) ($_POST['memo'] ?? ''),
                isset($_POST['is_active']) && (string) $_POST['is_active'] === '1'
            );
            $flashType = 'success';
            $flashMessage = 'Exemplar wurde aktualisiert.';
        } elseif ($action === 'delete_copy') {
            MediaService::deleteCopy((int) ($_POST['copy_id'] ?? 0));
            $flashType = 'success';
            $flashMessage = 'Exemplar wurde geloescht.';
        }
    } catch (Throwable $e) {
        $flashType = 'error';
        $flashMessage = $e->getMessage();
    }

    $redirectQuery = http_build_query([
        'q' => $search,
        'type_filter' => $typeFilter,
        'title_id' => $selectedTitleId,
        'sort' => $sortBy,
        'dir' => $sortDir,
        'flash_type' => $flashType,
        'flash_message' => $flashMessage,
        'flash_action' => $flashAction,
    ]);
    header('Location: /media_list.php?' . $redirectQuery);
    exit;
}

$mediaListAll = MediaService::getAll($search);
$typeOptionsMap = [];
foreach ($mediaListAll as $mediaItem) {
    $typeValue = trim((string) ($mediaItem['type'] ?? ''));
    if ($typeValue !== '') {
        $typeOptionsMap[$typeValue] = true;
    }
}
$typeOptions = array_keys($typeOptionsMap);
natcasesort($typeOptions);
$typeOptions = array_values($typeOptions);

$mediaList = $mediaListAll;
if ($typeFilter === $emptyTypeFilterToken) {
    $mediaList = array_values(array_filter(
        $mediaListAll,
        static fn (array $mediaItem): bool => trim((string) ($mediaItem['type'] ?? '')) === ''
    ));
} elseif ($typeFilter !== '') {
    $mediaList = array_values(array_filter(
        $mediaListAll,
        static fn (array $mediaItem): bool => strcasecmp(trim((string) ($mediaItem['type'] ?? '')), $typeFilter) === 0
    ));
}
usort($mediaList, static function (array $a, array $b) use ($sortBy, $sortDir): int {
    if ($sortBy === 'copy_count') {
        $left = (int) ($a['copy_count'] ?? 0);
        $right = (int) ($b['copy_count'] ?? 0);
        $primaryCmp = $left <=> $right;
    } else {
        $left = (string) ($a[$sortBy] ?? '');
        $right = (string) ($b[$sortBy] ?? '');
        $primaryCmp = strcasecmp($left, $right);
    }

    if ($primaryCmp !== 0) {
        return $sortDir === 'desc' ? -$primaryCmp : $primaryCmp;
    }

    // Keep titles in ascending order as stable secondary sort key.
    return strcasecmp((string) ($a['title'] ?? ''), (string) ($b['title'] ?? ''));
});

if ($selectedTitleId === 0 && $mediaList !== []) {
    $selectedTitleId = (int) $mediaList[0]['id'];
}
if ($selectedTitleId > 0 && $mediaList !== []) {
    $isSelectedInCurrentList = false;
    foreach ($mediaList as $mediaItem) {
        if ((int) $mediaItem['id'] === $selectedTitleId) {
            $isSelectedInCurrentList = true;
            break;
        }
    }
    if (!$isSelectedInCurrentList) {
        $selectedTitleId = (int) $mediaList[0]['id'];
    }
}

$nextSortDir = static function (string $field) use ($sortBy, $sortDir): string {
    if ($sortBy === $field) {
        return $sortDir === 'asc' ? 'desc' : 'asc';
    }
    return 'asc';
};

$sortIndicator = static function (string $field) use ($sortBy, $sortDir): string {
    if ($sortBy !== $field) {
        return '';
    }
    return $sortDir === 'asc' ? ' ▲' : ' ▼';
};

$selectedTitle = $selectedTitleId > 0 ? MediaService::getById($selectedTitleId) : null;
$copies = $selectedTitle !== null ? MediaService::getCopiesByTitleId((int) $selectedTitle['id']) : [];

ob_start();
?>
<div class="svws-content-header" style="margin-bottom:0">
    <div class="svws-avatar">M</div>
    <div class="svws-content-header-main">
        <div>
            <p class="svws-title-main">Medienbestand</p>
            <div class="svws-title-sub">Bibliothek und Ausleihe</div>
        </div>
        <?php if ($flashMessage !== ''): ?>
            <p class="svws-header-status svws-header-status--<?= $flashType === 'error' ? 'error' : 'success' ?>">
                <?= htmlspecialchars($flashMessage) ?>
            </p>
        <?php endif; ?>
    </div>
</div>
<?php
$topbarLeft = ob_get_clean();

ob_start();
?>
<style>
    .svws-title-create-actions {
        display: flex;
        gap: 6px;
    }

    .svws-btn-small {
        padding: 4px 8px;
        font-size: 10px;
    }

    .svws-btn-scan {
        border-color: #79a175;
        color: #194a1d;
        background: linear-gradient(180deg, #fbfffb 0%, #e5f3e3 100%);
    }

    .svws-btn-scan:hover {
        border-color: #5c8558;
        background: linear-gradient(180deg, #f8fdf8 0%, #d8ecd5 100%);
        color: #173d1a;
    }

    .svws-modal-backdrop {
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.45);
        display: none;
        align-items: center;
        justify-content: center;
        z-index: 1200;
        padding: 14px;
    }

    .svws-modal-backdrop.is-open {
        display: flex;
    }

    .svws-modal {
        width: min(680px, 100%);
        max-height: calc(100vh - 28px);
        overflow: auto;
        background: #f6f6f6;
        border: 1px solid #b9c6d2;
        border-radius: 10px;
        padding: 12px;
        box-shadow: 0 18px 40px rgba(0, 0, 0, 0.24);
    }

    .svws-modal-title {
        margin: 0 0 2px;
        font-size: 16px;
        color: #103a5d;
    }

    .svws-modal-actions {
        display: flex;
        gap: 6px;
        flex-wrap: wrap;
        margin-top: 8px;
    }

    .svws-modal-note {
        margin-top: 6px;
        color: #1f5b2c;
        min-height: 14px;
        font-size: 10px;
        font-weight: 600;
    }

    .svws-scan-description {
        width: 100%;
        min-height: 92px;
        border: 1px solid #b2b2b2;
        border-radius: 4px;
        padding: 6px 8px;
        font-size: 11px;
        resize: vertical;
        background: #fff;
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

    @media (max-width: 900px) {
        .svws-header-status {
            width: 100%;
        }
    }

    .svws-detail-card {
        background: #ffffff;
        border: 2px solid #9eb8d1;
        border-left-width: 8px;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 10px 20px rgba(16, 41, 66, 0.12);
        outline: 2px solid rgba(255, 255, 255, 0.9);
    }

    html.dark-mode .svws-detail-card {
        background: #1b1e22;
        border-color: #4d6175;
        box-shadow: 0 10px 20px rgba(0, 0, 0, 0.35);
        outline-color: rgba(0, 0, 0, 0.45);
    }

    .svws-detail-card--title {
        border-left-color: #2f7dd1;
    }

    .svws-detail-card--copy-create {
        border-left-color: #2f9d66;
    }

    .svws-detail-card--copies {
        border-left-color: #7b5ec8;
    }

    .svws-detail-card-header {
        margin: 0;
        padding: 11px 14px;
        font-size: 14px;
        font-weight: 700;
        color: var(--text);
        border-bottom: 1px solid #c7d6e5;
        background: linear-gradient(180deg, #f6fbff 0%, #eaf2fa 100%);
    }

    html.dark-mode .svws-detail-card-header {
        border-bottom-color: #3e4f61;
        background: linear-gradient(180deg, #242b33 0%, #1f252c 100%);
    }

    .svws-detail-card-body {
        padding: 14px;
    }

    .svws-detail-card + .svws-detail-card {
        margin-top: 2px;
    }

    .svws-detail-card form {
        margin: 0;
    }

    .svws-panel-media-list .svws-panel-body {
        background: #f6f8fc;
    }

    .svws-panel-media-list .svws-list {
        background: #fcfdff;
    }

    .svws-panel-media-list .svws-list th {
        background: #edf3fb;
    }

    .svws-panel-media-list .svws-list a {
        color: #173f74;
        text-decoration: none;
    }

    .svws-panel-media-list .svws-list a:hover {
        color: #0f2e54;
        text-decoration: none;
    }

    .svws-sort-link {
        color: #0f2f56;
        text-decoration: none;
        font-weight: 700;
        display: inline-block;
    }

    .svws-sort-link:hover {
        text-decoration: none;
    }

    .svws-media-row-clickable {
        cursor: pointer;
    }

    .svws-media-list-search {
        margin-bottom: 8px;
        padding: 8px;
        border: 2px solid #b8cee4;
        border-radius: 8px;
        background: #eef4fb;
    }

    .svws-media-list-search-title {
        margin: 0 0 6px;
        font-weight: 700;
        color: #214b76;
    }

    .svws-media-list-search-form {
        display: grid;
        gap: 6px;
    }

    .svws-media-list-search-row {
        display: flex;
        gap: 6px;
        align-items: center;
    }

    .svws-collapsible-toggle {
        cursor: pointer;
        user-select: none;
        position: relative;
        padding-right: 28px !important;
    }

    .svws-collapsible-toggle::after {
        content: '▾';
        position: absolute;
        right: 10px;
        top: 50%;
        transform: translateY(-50%);
        font-size: 12px;
    }

    .svws-collapsible-toggle.is-collapsed::after {
        content: '▸';
    }

    .svws-collapsible-content.is-collapsed {
        display: none;
    }
</style>
<div class="svws-split">
    <section class="svws-panel svws-panel-media-list">
        <div class="svws-panel-header">
            <h3>Medienliste</h3>
            <span class="svws-muted">Bestand</span>
        </div>
        <div class="svws-panel-body svws-detail-stack">
            <div class="svws-media-list-search">
                <p class="svws-media-list-search-title">Titel suchen</p>
                <form method="get" class="svws-media-list-search-form">
                    <div class="svws-media-list-search-row">
                        <input class="svws-search" type="search" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Titel suchen">
                        <button class="svws-help-btn svws-btn-modern" type="submit">Suchen</button>
                    </div>
                    <div class="svws-media-list-search-row">
                        <select class="svws-search" name="type_filter">
                            <option value="">Typ: Alle</option>
                            <option value="<?= htmlspecialchars($emptyTypeFilterToken) ?>" <?= $typeFilter === $emptyTypeFilterToken ? 'selected' : '' ?>>Typ: leer</option>
                            <?php foreach ($typeOptions as $typeOption): ?>
                                <option value="<?= htmlspecialchars((string) $typeOption) ?>" <?= strcasecmp((string) $typeOption, $typeFilter) === 0 ? 'selected' : '' ?>>
                                    <?= htmlspecialchars((string) $typeOption) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <input type="hidden" name="sort" value="<?= htmlspecialchars($sortBy) ?>">
                    <input type="hidden" name="dir" value="<?= htmlspecialchars($sortDir) ?>">
                    <input type="hidden" name="title_id" value="<?= (int) $selectedTitleId ?>">
                </form>
            </div>

            <table class="svws-list">
                <thead>
                <tr>
                    <th style="width: 56%;">
                        <a class="svws-sort-link" href="/media_list.php?<?= htmlspecialchars(http_build_query(['q' => $search, 'type_filter' => $typeFilter, 'title_id' => $selectedTitleId, 'sort' => 'title', 'dir' => $nextSortDir('title')])) ?>">Titel<?= $sortIndicator('title') ?></a>
                    </th>
                    <th style="width: 22%;">
                        <a class="svws-sort-link" href="/media_list.php?<?= htmlspecialchars(http_build_query(['q' => $search, 'type_filter' => $typeFilter, 'title_id' => $selectedTitleId, 'sort' => 'type', 'dir' => $nextSortDir('type')])) ?>">Typ<?= $sortIndicator('type') ?></a>
                    </th>
                    <th style="width: 22%; text-align: right;">
                        <a class="svws-sort-link" href="/media_list.php?<?= htmlspecialchars(http_build_query(['q' => $search, 'type_filter' => $typeFilter, 'title_id' => $selectedTitleId, 'sort' => 'copy_count', 'dir' => $nextSortDir('copy_count')])) ?>">Bestand<?= $sortIndicator('copy_count') ?></a>
                    </th>
                </tr>
                </thead>
                <tbody>
                <?php if (count($mediaList) === 0): ?>
                    <tr>
                        <td colspan="3" class="svws-muted">Noch keine Medien angelegt.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($mediaList as $media): ?>
                        <?php $isActiveRow = (int) $media['id'] === $selectedTitleId; ?>
                        <tr
                            class="svws-media-row-clickable <?= $isActiveRow ? 'svws-row-active' : '' ?>"
                            data-href="/media_list.php?<?= htmlspecialchars(http_build_query(['q' => $search, 'type_filter' => $typeFilter, 'title_id' => (int) $media['id'], 'sort' => $sortBy, 'dir' => $sortDir])) ?>"
                            role="link"
                            tabindex="0"
                        >
                            <td>
                                <a href="/media_list.php?<?= htmlspecialchars(http_build_query(['q' => $search, 'type_filter' => $typeFilter, 'title_id' => (int) $media['id'], 'sort' => $sortBy, 'dir' => $sortDir])) ?>">
                                    <?= htmlspecialchars((string) $media['title']) ?>
                                </a>
                            </td>
                            <td><?= htmlspecialchars((string) ($media['type'] ?? '')) ?></td>
                            <td style="text-align: right;"><?= htmlspecialchars((string) $media['copy_count']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>

            <div class="svws-grid-note"><?= count($mediaList) ?> Titel</div>
        </div>
    </section>

    <section class="svws-panel">
        <div class="svws-panel-body svws-detail-stack">
            <div class="svws-collapsible-toggle" style="margin:2px 0 0; padding:7px 10px; background:#1f5c98; color:#fff; font-weight:700; border-radius:6px;">Medien</div>
            <fieldset class="svws-collapsible-content" style="border:3px solid #1f5c98; border-radius:10px; padding:12px; margin:0; background:#fff;">
                <form method="post" style="display:grid; gap:6px; margin-bottom:8px;">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="create_title">
                    <input class="svws-search" type="text" name="title" placeholder="Neuer Titel" required>
                    <input class="svws-search" type="text" name="type" placeholder="Typ (optional)">
                    <input class="svws-search" type="text" name="location" placeholder="Standort (optional)">
                    <div class="svws-title-create-actions">
                        <button class="svws-help-btn svws-btn-modern svws-btn-small" type="submit">Titel anlegen</button>
                        <button
                            class="svws-help-btn svws-btn-modern svws-btn-small svws-btn-scan"
                            type="button"
                            id="open-title-scan-modal"
                        >Titel Scan</button>
                    </div>
                </form>
            </fieldset>

            <?php if ($selectedTitle === null): ?>
                <div class="svws-collapsible-toggle" style="margin:2px 0 0; padding:7px 10px; background:#2f7dd1; color:#fff; font-weight:700; border-radius:6px;">Titel bearbeiten</div>
                <fieldset class="svws-collapsible-content" style="border:3px solid #2f7dd1; border-radius:10px; padding:12px; margin:0; background:#fff;">
                    <p class="svws-muted" style="margin:0;">Waehle links einen Titel aus, um ihn zu bearbeiten.</p>
                </fieldset>

                <div class="svws-collapsible-toggle" style="margin:2px 0 0; padding:7px 10px; background:#2f9d66; color:#fff; font-weight:700; border-radius:6px;">Exemplar anlegen</div>
                <fieldset class="svws-collapsible-content" style="border:3px solid #2f9d66; border-radius:10px; padding:12px; margin:0; background:#fff;">
                    <p class="svws-muted" style="margin:0;">Waehle links einen Titel aus, um ein Exemplar anzulegen.</p>
                </fieldset>

                <div class="svws-collapsible-toggle" style="margin:2px 0 0; padding:7px 10px; background:#7b5ec8; color:#fff; font-weight:700; border-radius:6px;">Exemplare</div>
                <fieldset class="svws-collapsible-content" style="border:3px solid #7b5ec8; border-radius:10px; padding:12px; margin:0; background:#fff;">
                    <p class="svws-muted" style="margin:0;">Noch keine Exemplare sichtbar. Waehle links einen Titel aus.</p>
                </fieldset>
            <?php else: ?>
                <div class="svws-collapsible-toggle" style="margin:2px 0 0; padding:7px 10px; background:#2f7dd1; color:#fff; font-weight:700; border-radius:6px;">Titel bearbeiten</div>
                <fieldset class="svws-collapsible-content" style="border:3px solid #2f7dd1; border-radius:10px; padding:12px; margin:0; background:#fff;">
                    <form method="post" style="display:grid; grid-template-columns:repeat(2,minmax(220px,1fr)); gap:8px;">
                        <?= csrfField() ?>
                        <input type="hidden" name="title_id" value="<?= (int) $selectedTitle['id'] ?>">
                        <label>
                            <span class="svws-muted">Titel</span><br>
                            <input class="svws-search" type="text" name="title" value="<?= htmlspecialchars((string) $selectedTitle['title']) ?>" required>
                        </label>
                        <label>
                            <span class="svws-muted">Typ</span><br>
                            <input class="svws-search" type="text" name="type" value="<?= htmlspecialchars((string) ($selectedTitle['type'] ?? '')) ?>">
                        </label>
                        <label>
                            <span class="svws-muted">Standort</span><br>
                            <input class="svws-search" type="text" name="location" value="<?= htmlspecialchars((string) ($selectedTitle['location'] ?? '')) ?>">
                        </label>
                        <div style="display:flex; align-items:flex-end; gap:6px;">
                            <button class="svws-help-btn svws-btn-modern" type="submit" name="action" value="update_title">Titel speichern</button>
                            <button
                                class="svws-help-btn svws-btn-modern"
                                type="submit"
                                name="action"
                                value="delete_title"
                                onclick="return confirm('Titel wirklich löschen?');"
                            >Titel löschen</button>
                        </div>
                    </form>
                </fieldset>

                <div class="svws-collapsible-toggle" style="margin:2px 0 0; padding:7px 10px; background:#2f9d66; color:#fff; font-weight:700; border-radius:6px;">Exemplar anlegen</div>
                <fieldset class="svws-collapsible-content" style="border:3px solid #2f9d66; border-radius:10px; padding:12px; margin:0; background:#fff;">
                    <form method="post" style="display:grid; grid-template-columns:repeat(2,minmax(220px,1fr)); gap:8px;">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="create_copy">
                        <input type="hidden" name="title_id" value="<?= (int) $selectedTitle['id'] ?>">
                        <label>
                            <span class="svws-muted">Barcode</span><br>
                            <input class="svws-search" type="text" name="barcode" placeholder="z. B. B101">
                        </label>
                        <label>
                            <span class="svws-muted">Inventar-Nr.</span><br>
                            <input class="svws-search" type="text" name="inventory_number">
                        </label>
                        <label>
                            <span class="svws-muted">Zustand</span><br>
                            <input class="svws-search" type="text" name="condition" placeholder="gut, neu, gebraucht...">
                        </label>
                        <label>
                            <span class="svws-muted">Memo</span><br>
                            <input class="svws-search" type="text" name="memo">
                        </label>
                        <div>
                            <button class="svws-help-btn svws-btn-modern" type="submit">Exemplar anlegen</button>
                        </div>
                    </form>
                </fieldset>

                <div class="svws-collapsible-toggle" style="margin:2px 0 0; padding:7px 10px; background:#7b5ec8; color:#fff; font-weight:700; border-radius:6px;">Exemplare</div>
                <fieldset class="svws-collapsible-content" style="border:3px solid #7b5ec8; border-radius:10px; padding:12px; margin:0; background:#fff;">
                <table class="svws-tight">
                    <thead>
                    <tr>
                        <th style="width: 8%;">ID</th>
                        <th style="width: 16%;">Barcode</th>
                        <th style="width: 16%;">Inventar-Nr.</th>
                        <th style="width: 14%;">Zustand</th>
                        <th style="width: 22%;">Memo</th>
                        <th style="width: 10%;">Status</th>
                        <th style="width: 14%;">Aktionen</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if ($copies === []): ?>
                        <tr>
                            <td colspan="7" class="svws-muted">Noch keine Exemplare vorhanden.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($copies as $copy): ?>
                            <tr>
                                <td><?= htmlspecialchars((string) $copy['id']) ?></td>
                                <td>
                                    <form method="post" style="display:flex; gap:4px; align-items:center;">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="action" value="update_copy">
                                        <input type="hidden" name="title_id" value="<?= (int) $selectedTitle['id'] ?>">
                                        <input type="hidden" name="copy_id" value="<?= (int) $copy['id'] ?>">
                                        <input type="hidden" name="is_active" value="<?= (int) $copy['is_active'] === 1 ? '1' : '0' ?>">
                                        <input class="svws-search" style="height:26px;" type="text" name="barcode" value="<?= htmlspecialchars((string) ($copy['barcode'] ?? '')) ?>">
                                </td>
                                <td><input class="svws-search" style="height:26px;" type="text" name="inventory_number" value="<?= htmlspecialchars((string) ($copy['inventory_number'] ?? '')) ?>"></td>
                                <td><input class="svws-search" style="height:26px;" type="text" name="condition" value="<?= htmlspecialchars((string) ($copy['condition'] ?? '')) ?>"></td>
                                <td><input class="svws-search" style="height:26px;" type="text" name="memo" value="<?= htmlspecialchars((string) ($copy['memo'] ?? '')) ?>"></td>
                                <td>
                                    <?php if ((int) ($copy['open_lending_id'] ?? 0) > 0): ?>
                                        verliehen
                                    <?php elseif ((int) $copy['is_active'] === 1): ?>
                                        aktiv
                                    <?php else: ?>
                                        inaktiv
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="svws-action-buttons">
                                        <button class="svws-help-btn svws-icon-btn" type="submit" title="Speichern" aria-label="Speichern">
                                            <i class="ri-save-line" aria-hidden="true"></i>
                                        </button>
                                    </form>

                                    <form method="post" style="display:inline-block; margin:0;">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="action" value="update_copy">
                                        <input type="hidden" name="title_id" value="<?= (int) $selectedTitle['id'] ?>">
                                        <input type="hidden" name="copy_id" value="<?= (int) $copy['id'] ?>">
                                        <input type="hidden" name="barcode" value="<?= htmlspecialchars((string) ($copy['barcode'] ?? '')) ?>">
                                        <input type="hidden" name="inventory_number" value="<?= htmlspecialchars((string) ($copy['inventory_number'] ?? '')) ?>">
                                        <input type="hidden" name="condition" value="<?= htmlspecialchars((string) ($copy['condition'] ?? '')) ?>">
                                        <input type="hidden" name="memo" value="<?= htmlspecialchars((string) ($copy['memo'] ?? '')) ?>">
                                        <input type="hidden" name="is_active" value="<?= (int) $copy['is_active'] === 1 ? '0' : '1' ?>">
                                        <button
                                            class="svws-help-btn svws-icon-btn svws-icon-btn-warning"
                                            type="submit"
                                            title="<?= (int) $copy['is_active'] === 1 ? 'Deaktivieren' : 'Aktivieren' ?>"
                                            aria-label="<?= (int) $copy['is_active'] === 1 ? 'Deaktivieren' : 'Aktivieren' ?>"
                                        >
                                            <i class="<?= (int) $copy['is_active'] === 1 ? 'ri-toggle-line' : 'ri-toggle-fill' ?>" aria-hidden="true"></i>
                                        </button>
                                    </form>

                                    <form method="post" style="display:inline-block; margin:0;" onsubmit="return confirm('Exemplar wirklich löschen?');">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="action" value="delete_copy">
                                        <input type="hidden" name="title_id" value="<?= (int) $selectedTitle['id'] ?>">
                                        <input type="hidden" name="copy_id" value="<?= (int) $copy['id'] ?>">
                                        <button class="svws-help-btn svws-icon-btn svws-icon-btn-danger" type="submit" title="Löschen" aria-label="Löschen">
                                            <i class="ri-delete-bin-line" aria-hidden="true"></i>
                                        </button>
                                    </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>

                    <div class="svws-grid-note">
                        Titel-ID <?= htmlspecialchars((string) $selectedTitle['id']) ?> |
                        Exemplare <?= htmlspecialchars((string) $selectedTitle['copy_count']) ?> |
                        offen verliehen <?= htmlspecialchars((string) $selectedTitle['borrowed_count']) ?>
                    </div>
                    </fieldset>
            <?php endif; ?>
        </div>
    </section>
</div>

<div class="svws-modal-backdrop" id="title-scan-modal" aria-hidden="true">
    <div class="svws-modal" role="dialog" aria-modal="true" aria-labelledby="title-scan-modal-title">
        <h3 class="svws-modal-title" id="title-scan-modal-title">Titel per ISBN-Scan anlegen</h3>
        <p class="svws-muted" style="margin:0 0 10px;">Barcode scannen oder ISBN eingeben, Buchdaten aus dem Internet laden und als neuen Titel speichern.</p>

        <form method="post" id="title-scan-form" style="display:grid; grid-template-columns:repeat(2,minmax(220px,1fr)); gap:8px;">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="create_title_from_scan">

            <label style="grid-column:1 / -1;">
                <span class="svws-muted">ISBN / Barcode</span><br>
                <input class="svws-search" type="text" id="scan-isbn" name="scan_isbn" placeholder="978..." autocomplete="off" required>
            </label>

            <label style="grid-column:1 / -1;">
                <span class="svws-muted">Titel</span><br>
                <input class="svws-search" type="text" id="scan-title" name="scan_title" required>
            </label>

            <label>
                <span class="svws-muted">Typ</span><br>
                <input class="svws-search" type="text" id="scan-type" name="scan_type" placeholder="Buch">
            </label>

            <label>
                <span class="svws-muted">Standort</span><br>
                <input class="svws-search" type="text" id="scan-location" name="scan_location" placeholder="z. B. Bibliothek A">
            </label>

            <label style="grid-column:1 / -1;">
                <span class="svws-muted">Beschreibung (aus Internet)</span><br>
                <textarea class="svws-scan-description" id="scan-description" readonly></textarea>
            </label>

            <div class="svws-modal-actions" style="grid-column:1 / -1;">
                <button class="svws-help-btn svws-btn-modern" type="button" id="scan-fetch-btn">Buchdaten laden</button>
                <button class="svws-help-btn svws-btn-modern svws-btn-scan" type="submit" id="scan-create-btn" disabled>Titel anlegen</button>
                <button class="svws-help-btn svws-btn-modern" type="button" id="close-title-scan-modal">Schließen</button>
            </div>
            <div class="svws-modal-note" style="grid-column:1 / -1;" id="scan-status"></div>
        </form>
    </div>
</div>

<script>
    (function () {
        var openBtn = document.getElementById('open-title-scan-modal');
        var closeBtn = document.getElementById('close-title-scan-modal');
        var modal = document.getElementById('title-scan-modal');
        var fetchBtn = document.getElementById('scan-fetch-btn');
        var createBtn = document.getElementById('scan-create-btn');

        var isbnInput = document.getElementById('scan-isbn');
        var titleInput = document.getElementById('scan-title');
        var typeInput = document.getElementById('scan-type');
        var locationInput = document.getElementById('scan-location');
        var descriptionInput = document.getElementById('scan-description');
        var statusBox = document.getElementById('scan-status');

        if (!openBtn || !closeBtn || !modal || !fetchBtn || !createBtn) {
            return;
        }

        function setStatus(text, isError) {
            statusBox.textContent = text;
            statusBox.style.color = isError ? '#9c1e1e' : '#1f5b2c';
        }

        function openModal() {
            modal.classList.add('is-open');
            modal.setAttribute('aria-hidden', 'false');
            setTimeout(function () {
                isbnInput.focus();
            }, 20);
        }

        function closeModal() {
            modal.classList.remove('is-open');
            modal.setAttribute('aria-hidden', 'true');
        }

        function normalizeIsbn(raw) {
            return (raw || '').replace(/[^0-9Xx]/g, '').toUpperCase();
        }

        function renderDescription(book) {
            var lines = [];
            if (book.title) {
                lines.push('Titel: ' + book.title);
            }
            if (book.subtitle) {
                lines.push('Untertitel: ' + book.subtitle);
            }
            if (book.authors) {
                lines.push('Autor(en): ' + book.authors);
            }
            if (book.publish_date) {
                lines.push('Erscheinungsjahr: ' + book.publish_date);
            }
            if (book.publishers) {
                lines.push('Verlag: ' + book.publishers);
            }
            if (book.number_of_pages) {
                lines.push('Seiten: ' + String(book.number_of_pages));
            }

            return lines.join('\n');
        }

        async function loadBookData() {
            var isbn = normalizeIsbn(isbnInput.value);
            if (!isbn) {
                setStatus('Bitte zuerst eine gueltige ISBN eingeben.', true);
                createBtn.disabled = true;
                return;
            }

            setStatus('Buchdaten werden geladen ...', false);
            createBtn.disabled = true;

            var endpoint = 'https://openlibrary.org/api/books?jscmd=data&format=json&bibkeys=ISBN:' + encodeURIComponent(isbn);

            try {
                var response = await fetch(endpoint, { method: 'GET' });
                if (!response.ok) {
                    throw new Error('HTTP ' + response.status);
                }

                var data = await response.json();
                var key = 'ISBN:' + isbn;
                var book = data[key];

                if (!book || !book.title) {
                    throw new Error('Keine Treffer fuer diese ISBN gefunden.');
                }

                var authors = (book.authors || []).map(function (item) {
                    return item.name;
                }).filter(Boolean).join(', ');

                var publishers = (book.publishers || []).map(function (item) {
                    return item.name;
                }).filter(Boolean).join(', ');

                titleInput.value = book.title || '';
                if (!typeInput.value.trim()) {
                    typeInput.value = 'Buch';
                }
                if (!locationInput.value.trim()) {
                    locationInput.value = 'ISBN: ' + isbn;
                }

                descriptionInput.value = renderDescription({
                    title: book.title || '',
                    subtitle: book.subtitle || '',
                    authors: authors,
                    publish_date: book.publish_date || '',
                    publishers: publishers,
                    number_of_pages: book.number_of_pages || ''
                });

                setStatus('Buchdaten geladen. Du kannst Felder noch anpassen und dann speichern.', false);
                createBtn.disabled = titleInput.value.trim() === '';
            } catch (error) {
                descriptionInput.value = '';
                setStatus('Buchdaten konnten nicht geladen werden: ' + (error && error.message ? error.message : 'Unbekannter Fehler'), true);
                createBtn.disabled = true;
            }
        }

        openBtn.addEventListener('click', openModal);
        closeBtn.addEventListener('click', closeModal);
        fetchBtn.addEventListener('click', loadBookData);

        modal.addEventListener('click', function (event) {
            if (event.target === modal) {
                closeModal();
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && modal.classList.contains('is-open')) {
                closeModal();
            }
        });

        titleInput.addEventListener('input', function () {
            createBtn.disabled = titleInput.value.trim() === '';
        });
    })();

    (function () {
        var rows = document.querySelectorAll('.svws-media-row-clickable');
        if (!rows.length) {
            return;
        }

        function isInteractiveTarget(target) {
            return !!target.closest('a, button, input, select, textarea, label, form');
        }

        rows.forEach(function (row) {
            var href = row.getAttribute('data-href');
            if (!href) {
                return;
            }

            row.addEventListener('click', function (event) {
                if (isInteractiveTarget(event.target)) {
                    return;
                }
                window.location.href = href;
            });

            row.addEventListener('keydown', function (event) {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    window.location.href = href;
                }
            });
        });
    })();

    (function () {
        var toggles = document.querySelectorAll('.svws-collapsible-toggle');
        if (!toggles.length) {
            return;
        }
        var flashAction = <?= json_encode($flashAction) ?>;
        var openMedienOnLoad = flashAction === 'create_title' || flashAction === 'create_title_from_scan';
        var openExemplarOnLoad = flashAction === 'create_copy';

        function setCollapsed(toggle, content, collapsed) {
            content.classList.toggle('is-collapsed', collapsed);
            toggle.classList.toggle('is-collapsed', collapsed);
            toggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
        }

        toggles.forEach(function (toggle) {
            var content = toggle.nextElementSibling;
            if (!content || !content.classList.contains('svws-collapsible-content')) {
                return;
            }

            toggle.setAttribute('role', 'button');
            toggle.setAttribute('tabindex', '0');

            var title = (toggle.textContent || '').trim().toLowerCase();
            var shouldStartCollapsed = title === 'medien' || title === 'exemplar anlegen';
            if (title === 'medien' && openMedienOnLoad) {
                shouldStartCollapsed = false;
            }
            if (title === 'exemplar anlegen' && openExemplarOnLoad) {
                shouldStartCollapsed = false;
            }
            setCollapsed(toggle, content, shouldStartCollapsed);

            toggle.addEventListener('click', function () {
                setCollapsed(toggle, content, !content.classList.contains('is-collapsed'));
            });

            toggle.addEventListener('keydown', function (event) {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    setCollapsed(toggle, content, !content.classList.contains('is-collapsed'));
                }
            });
        });
    })();
</script>
<?php
$content = ob_get_clean();

renderLayout('Medienliste', $content, 'media', $topbarLeft);
