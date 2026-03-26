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
</style>
<div class="svws-split">
    <section class="svws-panel">
        <div class="svws-panel-header">
            <h3>Medien</h3>
            <span class="svws-muted">Titel</span>
        </div>
        <div class="svws-panel-body">
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

            <div style="margin:10px 0 8px; padding-top:8px; border-top:2px solid #c5d8ec;">
                <p class="svws-muted" style="margin:0 0 6px; font-weight:600;">Titel suchen</p>
                <form method="get" style="display:flex; gap:6px; align-items:center;">
                    <input class="svws-search" type="search" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Titel suchen">
                    <button class="svws-help-btn svws-btn-modern" type="submit">Suchen</button>
                </form>
            </div>

            <table class="svws-list">
                <thead>
                <tr>
                    <th style="width: 56%;">Titel</th>
                    <th style="width: 22%;">Typ</th>
                    <th style="width: 22%; text-align: right;">Bestand</th>
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
                        <button class="svws-help-btn svws-btn-modern" type="submit">Exemplar anlegen</button>
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
</script>
<?php
$content = ob_get_clean();

renderLayout('Medienliste', $content, 'media');
