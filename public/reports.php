<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/config/config.php';
require_once __DIR__ . '/../src/auth/user.php';
require_once __DIR__ . '/../src/modules/lending/lending_service.php';
require_once __DIR__ . '/../src/modules/media/media_service.php';
require_once __DIR__ . '/../templates/layout.php';

requireLogin();

$borrowers = LendingService::getBorrowers('', 200);
$titles = LendingService::getTitles('', 200);
$inventoryRows = MediaService::getInventoryReportRows();

ob_start();
?>
<div class="svws-content-header">
    <div class="svws-avatar">R</div>
    <div>
        <p class="svws-title-main">Druck & Export</p>
        <div class="svws-title-sub">Kontoauszug und Medienbestand</div>
    </div>
</div>

<section class="svws-panel" style="margin-bottom:8px;">
    <div class="svws-panel-header">
        <h3>Medienbestand</h3>
    </div>
    <div class="svws-panel-body" style="display:flex; gap:8px;">
        <a class="svws-help-btn" href="/report_media.php" target="_blank" rel="noopener">Druckansicht</a>
        <a class="svws-help-btn" href="/report_media.php?format=csv">CSV exportieren</a>
    </div>
</section>

<section class="svws-panel" style="margin-bottom:8px;">
    <div class="svws-panel-header">
        <h3>Ausleiher-Kontoauszug</h3>
    </div>
    <div class="svws-panel-body">
        <form method="get" action="/report_borrower.php" style="display:grid; grid-template-columns:repeat(2,minmax(220px,1fr)); gap:8px;">
            <label>
                <span class="svws-muted">Ausleiher</span><br>
                <select class="svws-filter" name="borrower_id" required>
                    <option value="">Bitte waehlen</option>
                    <?php foreach ($borrowers as $borrower): ?>
                        <option value="<?= (int) $borrower['id'] ?>">
                            <?= htmlspecialchars(LendingService::formatBorrowerName($borrower)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                <span class="svws-muted">Inhalt</span><br>
                <select class="svws-filter" name="mode">
                    <option value="open">Nur offene Ausleihen</option>
                    <option value="all">Komplette Historie</option>
                </select>
            </label>
            <div style="display:flex; gap:8px; align-items:flex-end;">
                <button class="svws-help-btn" type="submit">Druckansicht</button>
                <button class="svws-help-btn" type="submit" name="format" value="csv">CSV exportieren</button>
            </div>
        </form>
    </div>
</section>

<section class="svws-panel">
    <div class="svws-panel-header">
        <h3>Schnelluebersicht Bestand</h3>
    </div>
    <div class="svws-panel-body">
        <table class="svws-tight">
            <thead>
            <tr>
                <th>Titel</th>
                <th>Gesamt</th>
                <th>Aktiv</th>
                <th>Verliehen</th>
                <th>Verfuegbar</th>
            </tr>
            </thead>
            <tbody>
            <?php if ($inventoryRows === []): ?>
                <tr><td colspan="5" class="svws-muted">Keine Mediendaten vorhanden.</td></tr>
            <?php else: ?>
                <?php foreach ($inventoryRows as $row): ?>
                    <tr>
                        <td><?= htmlspecialchars((string) $row['title']) ?></td>
                        <td><?= htmlspecialchars((string) $row['copies_total']) ?></td>
                        <td><?= htmlspecialchars((string) $row['copies_active']) ?></td>
                        <td><?= htmlspecialchars((string) $row['copies_lent']) ?></td>
                        <td><?= htmlspecialchars((string) $row['copies_available']) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php
$content = ob_get_clean();

renderLayout('Druck & Export', $content, 'reports');
