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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    $borrowerSearch = trim((string) ($_POST['borrower_q'] ?? $borrowerSearch));
    $selectedBorrowerId = max(0, (int) ($_POST['borrower_id'] ?? $selectedBorrowerId));
    $unknownBarcode = trim((string) ($_POST['unknown_barcode'] ?? ''));

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
            $flashMessage = 'Rueckgabe verbucht (ID ' . (int) ($result['lending_id'] ?? 0) . ').';
        }
    } catch (Throwable $e) {
        $flashType = 'error';
        $flashMessage = $e->getMessage();
    }

    $redirectQuery = http_build_query([
        'borrower_q' => $borrowerSearch,
        'borrower_id' => $selectedBorrowerId,
        'unknown_barcode' => $unknownBarcode,
        'flash_type' => $flashType,
        'flash_message' => $flashMessage,
    ]);
    header('Location: /lending.php?' . $redirectQuery);
    exit;
}

$borrowers = LendingService::getBorrowers($borrowerSearch, 60);
if ($selectedBorrowerId === 0 && $borrowers !== []) {
    $selectedBorrowerId = (int) $borrowers[0]['id'];
}
$selectedBorrower = LendingService::getBorrowerById($selectedBorrowerId);
$titles = LendingService::getTitles('', 200);
$openLendings = LendingService::getOpenLendings();

ob_start();
?>
<div class="svws-split">
    <section class="svws-panel">
        <div class="svws-panel-header">
            <h3>Ausleiher</h3>
            <span class="svws-muted">SVWS + Sonstige</span>
        </div>
        <div class="svws-panel-body">
            <form method="get" style="display:flex; gap:6px; margin-bottom:8px; align-items:center;">
                <input class="svws-search" type="search" name="borrower_q" value="<?= htmlspecialchars($borrowerSearch) ?>" placeholder="Name/Klasse suchen">
                <button class="svws-help-btn" type="submit">Suchen</button>
            </form>

            <table class="svws-list">
                <thead>
                <tr>
                    <th style="width:56%;">Name</th>
                    <th style="width:22%;">Typ</th>
                    <th style="width:22%;">Klasse</th>
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
                                ])) ?>">
                                    <?= htmlspecialchars($name) ?>
                                </a>
                            </td>
                            <td><?= htmlspecialchars((string) $b['kind']) ?></td>
                            <td><?= htmlspecialchars((string) ($b['klasse'] ?? '')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="svws-panel">
        <div class="svws-panel-body">
            <div class="svws-content-header">
                <div class="svws-avatar">L</div>
                <div>
                    <p class="svws-title-main">Ausleihe & Rueckgabe</p>
                    <div class="svws-title-sub">Barcode-Scan mit Rueckgabe oder Ausgabe</div>
                </div>
            </div>

            <?php if ($flashMessage !== ''): ?>
                <p style="margin:0 0 8px; color: <?= $flashType === 'error' ? '#a40000' : '#0c5c0c' ?>;">
                    <?= htmlspecialchars($flashMessage) ?>
                </p>
            <?php endif; ?>

            <?php if ($selectedBorrower === null): ?>
                <p class="svws-muted">Bitte links einen Ausleiher auswaehlen.</p>
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

                <form method="post" style="display:grid; grid-template-columns:repeat(2,minmax(220px,1fr)); gap:8px; margin-bottom:8px;">
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
                        <button class="svws-help-btn" type="submit">Scan verarbeiten</button>
                    </div>
                </form>

                <form method="post" style="display:flex; gap:6px; align-items:center; margin-bottom:10px;">
                    <input type="hidden" name="action" value="return">
                    <input type="hidden" name="borrower_q" value="<?= htmlspecialchars($borrowerSearch) ?>">
                    <input type="hidden" name="borrower_id" value="<?= (int) $selectedBorrowerId ?>">
                    <input class="svws-search" type="text" name="barcode" placeholder="Barcode fuer direkte Rueckgabe" required>
                    <button class="svws-help-btn" type="submit">Direkte Rueckgabe</button>
                </form>

                <?php if ($unknownBarcode !== ''): ?>
                    <section class="svws-panel" style="margin-bottom:8px;">
                        <div class="svws-panel-header">
                            <h3>Unbekannter Barcode</h3>
                            <span class="svws-muted"><?= htmlspecialchars($unknownBarcode) ?></span>
                        </div>
                        <div class="svws-panel-body">
                            <form method="post" style="display:grid; grid-template-columns:repeat(2,minmax(220px,1fr)); gap:8px;">
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
                                        <option value="">Bitte waehlen</option>
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
                                    <button class="svws-help-btn" type="submit">Zuordnen und ausleihen</button>
                                </div>
                            </form>
                        </div>
                    </section>
                <?php endif; ?>
            <?php endif; ?>

            <h3 style="margin-bottom:6px;">Offene Ausleihen</h3>
            <table class="svws-tight">
                <thead>
                <tr>
                    <th style="width:10%;">ID</th>
                    <th style="width:26%;">Titel</th>
                    <th style="width:14%;">Barcode</th>
                    <th style="width:20%;">Ausleiher</th>
                    <th style="width:15%;">Klasse/Kurs</th>
                    <th style="width:15%;">Seit</th>
                </tr>
                </thead>
                <tbody>
                <?php if ($openLendings === []): ?>
                    <tr><td colspan="6" class="svws-muted">Keine offenen Ausleihen.</td></tr>
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
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
<?php
$content = ob_get_clean();

renderLayout('Ausleihe', $content, 'lending');
