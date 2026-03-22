<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/config/config.php';
require_once __DIR__ . '/../src/auth/user.php';
require_once __DIR__ . '/../src/modules/lending/lending_service.php';
require_once __DIR__ . '/../templates/layout.php';

requireLogin();

$currentUser = getCurrentUser();
$actorUserId = (int) ($currentUser['id'] ?? 0);

$groupSearch = trim((string) ($_GET['group_q'] ?? ''));
$selectedGroupId = max(0, (int) ($_GET['group_id'] ?? 0));
$selectedTitleId = max(0, (int) ($_GET['title_id'] ?? 0));
$unknownBarcode = trim((string) ($_GET['unknown_barcode'] ?? ''));
$flashType = trim((string) ($_GET['flash_type'] ?? ''));
$flashMessage = trim((string) ($_GET['flash_message'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrfToken();

    $action = (string) ($_POST['action'] ?? '');
    $groupSearch = trim((string) ($_POST['group_q'] ?? $groupSearch));
    $selectedGroupId = max(0, (int) ($_POST['group_id'] ?? $selectedGroupId));
    $selectedTitleId = max(0, (int) ($_POST['title_id'] ?? $selectedTitleId));
    $unknownBarcode = trim((string) ($_POST['unknown_barcode'] ?? $unknownBarcode));

    try {
        if ($action === 'scan_group') {
            $result = LendingService::processGroupScan(
                (string) ($_POST['barcode'] ?? ''),
                $selectedGroupId,
                $selectedTitleId,
                $actorUserId,
                [
                    'school_year' => (int) ($_POST['school_year'] ?? (int) gmdate('Y')),
                    'kurs_lehrer' => (string) ($_POST['kurs_lehrer'] ?? ''),
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
            if (($result['status'] ?? '') === 'lent') {
                $flashMessage .= ' -> ' . (string) ($result['borrower_name'] ?? '');
            }
        } elseif ($action === 'assign_unknown_group') {
            $result = LendingService::assignUnknownBarcodeToGroup(
                (string) ($_POST['unknown_barcode'] ?? ''),
                $selectedGroupId,
                $selectedTitleId,
                (string) ($_POST['inventory_number'] ?? ''),
                (string) ($_POST['condition'] ?? ''),
                (string) ($_POST['memo'] ?? ''),
                $actorUserId,
                [
                    'school_year' => (int) ($_POST['school_year'] ?? (int) gmdate('Y')),
                    'kurs_lehrer' => (string) ($_POST['kurs_lehrer'] ?? ''),
                ]
            );
            $unknownBarcode = '';
            $flashType = 'success';
            $flashMessage = (string) ($result['message'] ?? 'Barcode wurde zugeordnet und verliehen.');
        }
    } catch (Throwable $e) {
        $flashType = 'error';
        $flashMessage = $e->getMessage();
    }

    $redirectQuery = http_build_query([
        'group_q' => $groupSearch,
        'group_id' => $selectedGroupId,
        'title_id' => $selectedTitleId,
        'unknown_barcode' => $unknownBarcode,
        'flash_type' => $flashType,
        'flash_message' => $flashMessage,
    ]);
    header('Location: /group_lending.php?' . $redirectQuery);
    exit;
}

$groups = LendingService::getGroups($groupSearch, 120);
if ($selectedGroupId === 0 && $groups !== []) {
    $selectedGroupId = (int) $groups[0]['id'];
}
$selectedGroup = LendingService::getGroupById($selectedGroupId);
$groupMembers = $selectedGroup !== null ? LendingService::getGroupMembers($selectedGroupId) : [];
$titles = LendingService::getTitles('', 200);
if ($selectedTitleId === 0 && $titles !== []) {
    $selectedTitleId = (int) $titles[0]['id'];
}

$nextBorrower = ($selectedGroup !== null && $selectedTitleId > 0)
    ? LendingService::getNextGroupBorrowerForTitle($selectedGroupId, $selectedTitleId, (int) gmdate('Y'))
    : null;

ob_start();
?>
<div class="svws-split">
    <section class="svws-panel">
        <div class="svws-panel-header">
            <h3>Gruppen / Kurse</h3>
            <span class="svws-muted">SVWS Lerngruppen</span>
        </div>
        <div class="svws-panel-body">
            <form method="get" style="display:flex; gap:6px; margin-bottom:8px; align-items:center;">
                <input class="svws-search" type="search" name="group_q" value="<?= htmlspecialchars($groupSearch) ?>" placeholder="Gruppe/Kurs suchen">
                <button class="svws-help-btn" type="submit">Suchen</button>
            </form>

            <table class="svws-list">
                <thead>
                <tr>
                    <th style="width:55%;">Gruppe</th>
                    <th style="width:20%;">Jahrgang</th>
                    <th style="width:25%;">Mitglieder</th>
                </tr>
                </thead>
                <tbody>
                <?php if ($groups === []): ?>
                    <tr><td colspan="3" class="svws-muted">Keine Gruppen gefunden.</td></tr>
                <?php else: ?>
                    <?php foreach ($groups as $group): ?>
                        <tr class="<?= (int) $group['id'] === $selectedGroupId ? 'svws-row-active' : '' ?>">
                            <td>
                                <a href="/group_lending.php?<?= htmlspecialchars(http_build_query([
                                    'group_q' => $groupSearch,
                                    'group_id' => (int) $group['id'],
                                    'title_id' => $selectedTitleId,
                                    'unknown_barcode' => $unknownBarcode,
                                ])) ?>">
                                    <?= htmlspecialchars((string) (($group['name'] ?: $group['kuerzel']) ?: ('ID ' . $group['svws_id']))) ?>
                                </a>
                            </td>
                            <td><?= htmlspecialchars((string) ($group['jahrgang'] ?? '')) ?></td>
                            <td><?= htmlspecialchars((string) $group['member_count']) ?></td>
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
                <div class="svws-avatar">K</div>
                <div>
                    <p class="svws-title-main">Gruppenausleihe</p>
                    <div class="svws-title-sub">Ein Titel, viele Exemplare, naechstes Mitglied automatisch</div>
                </div>
            </div>

            <?php if ($flashMessage !== ''): ?>
                <p style="margin:0 0 8px; color: <?= $flashType === 'error' ? '#a40000' : '#0c5c0c' ?>;">
                    <?= htmlspecialchars($flashMessage) ?>
                </p>
            <?php endif; ?>

            <?php if ($selectedGroup === null): ?>
                <p class="svws-muted">Bitte links eine Gruppe auswaehlen.</p>
            <?php else: ?>
                <form method="get" style="display:grid; grid-template-columns:repeat(2,minmax(220px,1fr)); gap:8px; margin-bottom:8px;">
                    <input type="hidden" name="group_q" value="<?= htmlspecialchars($groupSearch) ?>">
                    <input type="hidden" name="group_id" value="<?= (int) $selectedGroupId ?>">
                    <input type="hidden" name="unknown_barcode" value="<?= htmlspecialchars($unknownBarcode) ?>">

                    <label>
                        <span class="svws-muted">Titel fuer diesen Lauf</span><br>
                        <select class="svws-filter" name="title_id" required>
                            <?php foreach ($titles as $title): ?>
                                <option value="<?= (int) $title['id'] ?>" <?= (int) $title['id'] === $selectedTitleId ? 'selected' : '' ?>>
                                    <?= htmlspecialchars((string) $title['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <div style="display:flex; align-items:flex-end;">
                        <button class="svws-help-btn" type="submit">Titel setzen</button>
                    </div>
                </form>

                <div class="svws-grid-note" style="margin-bottom:8px;">
                    Gruppe: <?= htmlspecialchars((string) (($selectedGroup['name'] ?: $selectedGroup['kuerzel']) ?: ('ID ' . $selectedGroup['svws_id']))) ?> |
                    Mitglieder: <?= count($groupMembers) ?>
                </div>

                <?php if ($nextBorrower !== null): ?>
                    <div class="svws-grid-note" style="margin-bottom:8px; color:#0b4f7a;">
                        Naechstes Mitglied: <?= htmlspecialchars(LendingService::formatBorrowerName($nextBorrower)) ?>
                    </div>
                <?php else: ?>
                    <div class="svws-grid-note" style="margin-bottom:8px; color:#0b4f7a;">
                        Kein naechstes Mitglied offen. Gruppe scheint versorgt zu sein.
                    </div>
                <?php endif; ?>

                <form method="post" style="display:grid; grid-template-columns:repeat(2,minmax(220px,1fr)); gap:8px; margin-bottom:10px;">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="scan_group">
                    <input type="hidden" name="group_q" value="<?= htmlspecialchars($groupSearch) ?>">
                    <input type="hidden" name="group_id" value="<?= (int) $selectedGroupId ?>">
                    <input type="hidden" name="title_id" value="<?= (int) $selectedTitleId ?>">
                    <input type="hidden" name="unknown_barcode" value="<?= htmlspecialchars($unknownBarcode) ?>">

                    <label style="grid-column:1 / -1;">
                        <span class="svws-muted">Barcode scannen (Gruppenlauf)</span><br>
                        <input class="svws-search" type="text" name="barcode" placeholder="Scan oder Eingabe" autofocus required>
                    </label>
                    <label>
                        <span class="svws-muted">Schuljahr</span><br>
                        <input class="svws-search" type="number" name="school_year" value="<?= htmlspecialchars((string) gmdate('Y')) ?>">
                    </label>
                    <label>
                        <span class="svws-muted">Kurs-Lehrkraft (optional)</span><br>
                        <input class="svws-search" type="text" name="kurs_lehrer">
                    </label>
                    <div>
                        <button class="svws-help-btn" type="submit">Gruppenscan verarbeiten</button>
                    </div>
                </form>

                <?php if ($unknownBarcode !== ''): ?>
                    <section class="svws-panel" style="margin-bottom:8px;">
                        <div class="svws-panel-header">
                            <h3>Unbekannter Barcode im Gruppenlauf</h3>
                            <span class="svws-muted"><?= htmlspecialchars($unknownBarcode) ?></span>
                        </div>
                        <div class="svws-panel-body">
                            <form method="post" style="display:grid; grid-template-columns:repeat(2,minmax(220px,1fr)); gap:8px;">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="assign_unknown_group">
                                <input type="hidden" name="group_q" value="<?= htmlspecialchars($groupSearch) ?>">
                                <input type="hidden" name="group_id" value="<?= (int) $selectedGroupId ?>">
                                <input type="hidden" name="title_id" value="<?= (int) $selectedTitleId ?>">
                                <input type="hidden" name="unknown_barcode" value="<?= htmlspecialchars($unknownBarcode) ?>">
                                <input type="hidden" name="school_year" value="<?= htmlspecialchars((string) gmdate('Y')) ?>">

                                <label>
                                    <span class="svws-muted">Inventar-Nr.</span><br>
                                    <input class="svws-search" type="text" name="inventory_number">
                                </label>
                                <label>
                                    <span class="svws-muted">Zustand</span><br>
                                    <input class="svws-search" type="text" name="condition">
                                </label>
                                <label style="grid-column:1 / -1;">
                                    <span class="svws-muted">Memo</span><br>
                                    <input class="svws-search" type="text" name="memo">
                                </label>
                                <label>
                                    <span class="svws-muted">Kurs-Lehrkraft (optional)</span><br>
                                    <input class="svws-search" type="text" name="kurs_lehrer">
                                </label>
                                <div style="display:flex; align-items:flex-end;">
                                    <button class="svws-help-btn" type="submit">Zuordnen und naechstem Mitglied geben</button>
                                </div>
                            </form>
                        </div>
                    </section>
                <?php endif; ?>

                <h3 style="margin-bottom:6px;">Gruppenmitglieder</h3>
                <table class="svws-tight">
                    <thead>
                    <tr>
                        <th style="width:9%;">ID</th>
                        <th style="width:40%;">Name</th>
                        <th style="width:18%;">Typ</th>
                        <th style="width:18%;">Klasse</th>
                        <th style="width:15%;">Status</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if ($groupMembers === []): ?>
                        <tr><td colspan="5" class="svws-muted">Keine Mitglieder in dieser Gruppe.</td></tr>
                    <?php else: ?>
                        <?php foreach ($groupMembers as $member): ?>
                            <tr>
                                <td><?= htmlspecialchars((string) $member['id']) ?></td>
                                <td><?= htmlspecialchars(LendingService::formatBorrowerName($member)) ?></td>
                                <td><?= htmlspecialchars((string) $member['kind']) ?></td>
                                <td><?= htmlspecialchars((string) ($member['klasse'] ?? '')) ?></td>
                                <td><?= (int) ($member['is_blocked'] ?? 0) === 1 ? 'gesperrt' : 'aktiv' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </section>
</div>
<?php
$content = ob_get_clean();

renderLayout('Gruppenausleihe', $content, 'courses');
