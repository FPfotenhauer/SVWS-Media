<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/config/config.php';
require_once __DIR__ . '/../src/auth/user.php';
require_once __DIR__ . '/../src/modules/lending/lending_service.php';
require_once __DIR__ . '/../templates/layout.php';

requireLogin();

$search = trim((string) ($_GET['q'] ?? ''));
$selectedBorrowerId = max(0, (int) ($_GET['borrower_id'] ?? 0));
$flashType = trim((string) ($_GET['flash_type'] ?? ''));
$flashMessage = trim((string) ($_GET['flash_message'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    $search = trim((string) ($_POST['q'] ?? $search));
    $selectedBorrowerId = max(0, (int) ($_POST['borrower_id'] ?? $selectedBorrowerId));

    try {
        if ($action === 'save_memo') {
            LendingService::updateBorrowerMemo($selectedBorrowerId, (string) ($_POST['memo'] ?? ''));
            $flashType = 'success';
            $flashMessage = 'Memo wurde gespeichert.';
        } elseif ($action === 'toggle_block') {
            LendingService::setBorrowerBlocked(
                $selectedBorrowerId,
                isset($_POST['block']) && (string) $_POST['block'] === '1'
            );
            $flashType = 'success';
            $flashMessage = 'Sperrstatus wurde aktualisiert.';
        } elseif ($action === 'migrate') {
            $moved = LendingService::migrateBorrowerLendings(
                $selectedBorrowerId,
                (int) ($_POST['target_borrower_id'] ?? 0)
            );
            $flashType = 'success';
            $flashMessage = 'Migration abgeschlossen. Uebertragene Ausleihen: ' . $moved;
        }
    } catch (Throwable $e) {
        $flashType = 'error';
        $flashMessage = $e->getMessage();
    }

    $redirectQuery = http_build_query([
        'q' => $search,
        'borrower_id' => $selectedBorrowerId,
        'flash_type' => $flashType,
        'flash_message' => $flashMessage,
    ]);
    header('Location: /borrowers.php?' . $redirectQuery);
    exit;
}

$directory = LendingService::getBorrowerDirectory($search, 200);
if ($selectedBorrowerId === 0 && $directory !== []) {
    $selectedBorrowerId = (int) $directory[0]['id'];
}
$selectedBorrower = LendingService::getBorrowerById($selectedBorrowerId);
$priorYearOpen = LendingService::getBorrowersWithPriorYearOpenLendings((int) gmdate('Y'));
$blockedBorrowers = LendingService::getBlockedBorrowers();

ob_start();
?>
<div class="svws-split">
    <section class="svws-panel">
        <div class="svws-panel-header">
            <h3>Ausleiherverwaltung</h3>
            <span class="svws-muted">Sperren / Memo / Migration</span>
        </div>
        <div class="svws-panel-body">
            <form method="get" style="display:flex; gap:6px; margin-bottom:8px; align-items:center;">
                <input class="svws-search" type="search" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Name/Klasse suchen">
                <button class="svws-help-btn" type="submit">Suchen</button>
            </form>

            <table class="svws-list">
                <thead>
                <tr>
                    <th style="width:50%;">Name</th>
                    <th style="width:18%;">Typ</th>
                    <th style="width:14%;">Klasse</th>
                    <th style="width:18%;">Offen</th>
                </tr>
                </thead>
                <tbody>
                <?php if ($directory === []): ?>
                    <tr><td colspan="4" class="svws-muted">Keine Ausleiher gefunden.</td></tr>
                <?php else: ?>
                    <?php foreach ($directory as $row): ?>
                        <tr class="<?= (int) $row['id'] === $selectedBorrowerId ? 'svws-row-active' : '' ?>">
                            <td>
                                <a href="/borrowers.php?<?= htmlspecialchars(http_build_query([
                                    'q' => $search,
                                    'borrower_id' => (int) $row['id'],
                                ])) ?>">
                                    <?= htmlspecialchars(LendingService::formatBorrowerName($row)) ?>
                                </a>
                            </td>
                            <td><?= htmlspecialchars((string) $row['kind']) ?></td>
                            <td><?= htmlspecialchars((string) ($row['klasse'] ?? '')) ?></td>
                            <td><?= htmlspecialchars((string) ($row['open_lending_count'] ?? '0')) ?></td>
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
                <div class="svws-avatar">B</div>
                <div>
                    <p class="svws-title-main">Ausleiher-Details</p>
                    <div class="svws-title-sub">Konten pflegen und Vorjahresfaelle pruefen</div>
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
                <div class="svws-grid-note" style="margin-bottom:8px;">
                    ID <?= htmlspecialchars((string) $selectedBorrower['id']) ?> |
                    <?= htmlspecialchars(LendingService::formatBorrowerName($selectedBorrower)) ?> |
                    Typ <?= htmlspecialchars((string) $selectedBorrower['kind']) ?>
                </div>

                <form method="post" style="display:grid; gap:8px; margin-bottom:8px;">
                    <input type="hidden" name="action" value="save_memo">
                    <input type="hidden" name="q" value="<?= htmlspecialchars($search) ?>">
                    <input type="hidden" name="borrower_id" value="<?= (int) $selectedBorrower['id'] ?>">
                    <label>
                        <span class="svws-muted">Memo</span><br>
                        <input class="svws-search" type="text" name="memo" value="<?= htmlspecialchars((string) ($selectedBorrower['memo'] ?? '')) ?>">
                    </label>
                    <div>
                        <button class="svws-help-btn" type="submit">Memo speichern</button>
                    </div>
                </form>

                <form method="post" style="display:inline-block; margin-right:8px;">
                    <input type="hidden" name="action" value="toggle_block">
                    <input type="hidden" name="q" value="<?= htmlspecialchars($search) ?>">
                    <input type="hidden" name="borrower_id" value="<?= (int) $selectedBorrower['id'] ?>">
                    <input type="hidden" name="block" value="<?= (int) ($selectedBorrower['is_blocked'] ?? 0) === 1 ? '0' : '1' ?>">
                    <button class="svws-help-btn" type="submit">
                        <?= (int) ($selectedBorrower['is_blocked'] ?? 0) === 1 ? 'Entsperren' : 'Sperren' ?>
                    </button>
                </form>

                <form method="post" style="display:inline-block;" onsubmit="return confirm('Ausleihkonto wirklich migrieren?');">
                    <input type="hidden" name="action" value="migrate">
                    <input type="hidden" name="q" value="<?= htmlspecialchars($search) ?>">
                    <input type="hidden" name="borrower_id" value="<?= (int) $selectedBorrower['id'] ?>">
                    <select class="svws-filter" name="target_borrower_id" required>
                        <option value="">Zielkonto waehlen</option>
                        <?php foreach ($directory as $target): ?>
                            <?php if ((int) $target['id'] === (int) $selectedBorrower['id']): ?>
                                <?php continue; ?>
                            <?php endif; ?>
                            <option value="<?= (int) $target['id'] ?>">
                                <?= htmlspecialchars(LendingService::formatBorrowerName($target)) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button class="svws-help-btn" type="submit">Konto migrieren</button>
                </form>
            <?php endif; ?>

            <h3 style="margin:12px 0 6px;">Vorjahres-Ausleihen (offen)</h3>
            <table class="svws-tight" style="margin-bottom:8px;">
                <thead>
                <tr>
                    <th style="width:10%;">ID</th>
                    <th style="width:40%;">Name</th>
                    <th style="width:20%;">Klasse</th>
                    <th style="width:15%;">Offen alt</th>
                    <th style="width:15%;">Aeltestes Jahr</th>
                </tr>
                </thead>
                <tbody>
                <?php if ($priorYearOpen === []): ?>
                    <tr><td colspan="5" class="svws-muted">Keine offenen Vorjahres-Ausleihen.</td></tr>
                <?php else: ?>
                    <?php foreach ($priorYearOpen as $row): ?>
                        <tr>
                            <td><?= htmlspecialchars((string) $row['id']) ?></td>
                            <td><?= htmlspecialchars(LendingService::formatBorrowerName($row)) ?></td>
                            <td><?= htmlspecialchars((string) ($row['klasse'] ?? '')) ?></td>
                            <td><?= htmlspecialchars((string) ($row['open_old_count'] ?? '0')) ?></td>
                            <td><?= htmlspecialchars((string) ($row['oldest_year'] ?? '')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>

            <h3 style="margin:12px 0 6px;">Gesperrte Ausleiher</h3>
            <table class="svws-tight">
                <thead>
                <tr>
                    <th style="width:12%;">ID</th>
                    <th style="width:40%;">Name</th>
                    <th style="width:18%;">Typ</th>
                    <th style="width:15%;">Klasse</th>
                    <th style="width:15%;">Memo</th>
                </tr>
                </thead>
                <tbody>
                <?php if ($blockedBorrowers === []): ?>
                    <tr><td colspan="5" class="svws-muted">Keine gesperrten Ausleiher.</td></tr>
                <?php else: ?>
                    <?php foreach ($blockedBorrowers as $row): ?>
                        <tr>
                            <td><?= htmlspecialchars((string) $row['id']) ?></td>
                            <td><?= htmlspecialchars(LendingService::formatBorrowerName($row)) ?></td>
                            <td><?= htmlspecialchars((string) $row['kind']) ?></td>
                            <td><?= htmlspecialchars((string) ($row['klasse'] ?? '')) ?></td>
                            <td><?= htmlspecialchars((string) ($row['memo'] ?? '')) ?></td>
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

renderLayout('Ausleiher', $content, 'classes');
