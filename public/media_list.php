<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/config/config.php';
require_once __DIR__ . '/../src/auth/user.php';
require_once __DIR__ . '/../src/modules/media/media_service.php';
require_once __DIR__ . '/../templates/layout.php';

requireLogin();

$search = trim((string) ($_GET['q'] ?? ''));
$selectedTitleId = max(0, (int) ($_GET['title_id'] ?? 0));
$flashType = trim((string) ($_GET['flash_type'] ?? ''));
$flashMessage = trim((string) ($_GET['flash_message'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrfToken();

    $action = (string) ($_POST['action'] ?? '');
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
        'title_id' => $selectedTitleId,
        'flash_type' => $flashType,
        'flash_message' => $flashMessage,
    ]);
    header('Location: /media_list.php?' . $redirectQuery);
    exit;
}

$mediaList = MediaService::getAll($search);
if ($selectedTitleId === 0 && $mediaList !== []) {
    $selectedTitleId = (int) $mediaList[0]['id'];
}

$selectedTitle = $selectedTitleId > 0 ? MediaService::getById($selectedTitleId) : null;
$copies = $selectedTitle !== null ? MediaService::getCopiesByTitleId((int) $selectedTitle['id']) : [];

ob_start();
?>
<div class="svws-split">
    <section class="svws-panel">
        <div class="svws-panel-header">
            <h3>Medien</h3>
            <span class="svws-muted">Titel</span>
        </div>
        <div class="svws-panel-body">
            <form method="get" style="display:flex; gap:6px; margin-bottom:8px; align-items:center;">
                <input class="svws-search" type="search" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Titel suchen">
                <button class="svws-help-btn" type="submit">Suchen</button>
            </form>

            <form method="post" style="display:grid; gap:6px; margin-bottom:8px;">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="create_title">
                <input class="svws-search" type="text" name="title" placeholder="Neuer Titel" required>
                <input class="svws-search" type="text" name="type" placeholder="Typ (optional)">
                <input class="svws-search" type="text" name="location" placeholder="Standort (optional)">
                <button class="svws-help-btn" type="submit">Titel anlegen</button>
            </form>

            <table class="svws-list">
                <thead>
                <tr>
                    <th style="width: 56%;">Titel</th>
                    <th style="width: 22%;">Typ</th>
                    <th style="width: 22%;">Bestand</th>
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
                        <tr class="<?= $isActiveRow ? 'svws-row-active' : '' ?>">
                            <td>
                                <a href="/media_list.php?<?= htmlspecialchars(http_build_query(['q' => $search, 'title_id' => (int) $media['id']])) ?>">
                                    <?= htmlspecialchars((string) $media['title']) ?>
                                </a>
                            </td>
                            <td><?= htmlspecialchars((string) ($media['type'] ?? '')) ?></td>
                            <td><?= htmlspecialchars((string) $media['copy_count']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>

            <div class="svws-grid-note"><?= count($mediaList) ?> Titel</div>
        </div>
    </section>

    <section class="svws-panel">
        <div class="svws-panel-body">
            <div class="svws-content-header">
                <div class="svws-avatar">M</div>
                <div>
                    <p class="svws-title-main">Medienbestand</p>
                    <div class="svws-title-sub">Bibliothek und Ausleihe</div>
                </div>
            </div>

            <?php if ($flashMessage !== ''): ?>
                <p style="margin:0 0 8px; color: <?= $flashType === 'error' ? '#a40000' : '#0c5c0c' ?>;">
                    <?= htmlspecialchars($flashMessage) ?>
                </p>
            <?php endif; ?>

            <?php if ($selectedTitle === null): ?>
                <p class="svws-muted">Waehle links einen Titel aus oder lege einen neuen Titel an.</p>
            <?php else: ?>
                <h3 style="margin-bottom: 6px;">Titel bearbeiten</h3>
                <form method="post" style="display:grid; grid-template-columns:repeat(2,minmax(220px,1fr)); gap:8px; margin-bottom:8px;">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="update_title">
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
                        <button class="svws-help-btn" type="submit">Titel speichern</button>
                    </div>
                </form>

                <form method="post" onsubmit="return confirm('Titel wirklich loeschen?');" style="margin-bottom:10px;">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="delete_title">
                    <input type="hidden" name="title_id" value="<?= (int) $selectedTitle['id'] ?>">
                    <button class="svws-help-btn" type="submit">Titel loeschen</button>
                </form>

                <h3 style="margin-bottom: 6px;">Exemplar anlegen</h3>
                <form method="post" style="display:grid; grid-template-columns:repeat(2,minmax(220px,1fr)); gap:8px; margin-bottom:8px;">
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
                        <button class="svws-help-btn" type="submit">Exemplar anlegen</button>
                    </div>
                </form>

                <h3 style="margin-bottom: 6px;">Exemplare</h3>
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
                                    <button class="svws-help-btn" type="submit">Speichern</button>
                                    </form>

                                    <form method="post" style="display:inline-block; margin-top:4px;">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="action" value="update_copy">
                                        <input type="hidden" name="title_id" value="<?= (int) $selectedTitle['id'] ?>">
                                        <input type="hidden" name="copy_id" value="<?= (int) $copy['id'] ?>">
                                        <input type="hidden" name="barcode" value="<?= htmlspecialchars((string) ($copy['barcode'] ?? '')) ?>">
                                        <input type="hidden" name="inventory_number" value="<?= htmlspecialchars((string) ($copy['inventory_number'] ?? '')) ?>">
                                        <input type="hidden" name="condition" value="<?= htmlspecialchars((string) ($copy['condition'] ?? '')) ?>">
                                        <input type="hidden" name="memo" value="<?= htmlspecialchars((string) ($copy['memo'] ?? '')) ?>">
                                        <input type="hidden" name="is_active" value="<?= (int) $copy['is_active'] === 1 ? '0' : '1' ?>">
                                        <button class="svws-help-btn" type="submit"><?= (int) $copy['is_active'] === 1 ? 'Deaktivieren' : 'Aktivieren' ?></button>
                                    </form>

                                    <form method="post" style="display:inline-block; margin-top:4px;" onsubmit="return confirm('Exemplar wirklich loeschen?');">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="action" value="delete_copy">
                                        <input type="hidden" name="title_id" value="<?= (int) $selectedTitle['id'] ?>">
                                        <input type="hidden" name="copy_id" value="<?= (int) $copy['id'] ?>">
                                        <button class="svws-help-btn" type="submit">Loeschen</button>
                                    </form>
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
            <?php endif; ?>
        </div>
    </section>
</div>
<?php
$content = ob_get_clean();

renderLayout('Medienliste', $content, 'media');
