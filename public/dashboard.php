<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/config/config.php';
require_once __DIR__ . '/../src/config/database.php';
require_once __DIR__ . '/../src/auth/user.php';
require_once __DIR__ . '/../templates/layout.php';

requireLogin();

$db = getDB();

$mediaTotal = (int) $db->query('SELECT COUNT(*) AS c FROM media_copies WHERE is_active = 1')->fetch()['c'];
$openLendingTotal = (int) $db->query('SELECT COUNT(*) AS c FROM lending WHERE returned_at IS NULL')->fetch()['c'];

$currentSchoolYear = (int) gmdate('Y');
$overdueStmt = $db->prepare(
    'SELECT COUNT(*) AS c
     FROM lending
     WHERE returned_at IS NULL
       AND school_year IS NOT NULL
       AND school_year < :current_school_year'
);
$overdueStmt->execute(['current_school_year' => $currentSchoolYear]);
$overdueLendingTotal = (int) $overdueStmt->fetch()['c'];

$schoolMeta = $db->query('SELECT * FROM svws_school_meta WHERE id = 1')->fetch() ?: [];
$schulname = htmlspecialchars((string) ($schoolMeta['schulname'] ?? ''));
$schulnummer = htmlspecialchars((string) ($schoolMeta['schulnummer'] ?? ''));
$ort = htmlspecialchars((string) ($schoolMeta['ort'] ?? ''));
$plz = htmlspecialchars((string) ($schoolMeta['plz'] ?? ''));

ob_start();
?>
<h2>Übersicht</h2>
<?php if ($schulname !== ''): ?>
<p class="svws-school-meta-title"><?= $schulname ?><?php
    $details = array_filter([$schulnummer !== '' ? 'Schulnummer: ' . $schulnummer : '', trim($plz . ' ' . $ort)]);
    if ($details !== []): ?><span class="svws-school-meta-details"> · <?= implode(' · ', $details) ?></span><?php endif; ?>
</p>
<?php else: ?>
<p class="svws-muted">Startbereich für die Medienverwaltung in einem SVWS-nahen Layoutstil.</p>
<?php endif; ?>

<div class="svws-kpis">
    <article class="svws-kpi">
        <div class="svws-kpi-value"><?= htmlspecialchars((string) $mediaTotal) ?></div>
        <div class="svws-kpi-label">Medien gesamt</div>
    </article>
    <article class="svws-kpi">
        <div class="svws-kpi-value"><?= htmlspecialchars((string) $openLendingTotal) ?></div>
        <div class="svws-kpi-label">Offene Ausleihen</div>
    </article>
    <article class="svws-kpi">
        <div class="svws-kpi-value"><?= htmlspecialchars((string) $overdueLendingTotal) ?></div>
        <div class="svws-kpi-label">Überfällige Rückgaben</div>
    </article>
</div>

<div class="svws-panel" style="margin-top: 12px;">
    <div class="svws-panel-body">
        <div class="svws-card-grid">
            <a class="svws-nav-card" href="/media_list.php">
                <p class="svws-nav-card-title">Medienbestand</p>
                <p class="svws-nav-card-text">Verwaltung der vorhandenen Medien und Stammdaten.</p>
            </a>

            <a class="svws-nav-card" href="/lending.php">
                <p class="svws-nav-card-title">Ausleihe</p>
                <p class="svws-nav-card-text">Ausgabe und Rückgabe per Barcode inkl. Zuordnung unbekannter Barcodes.</p>
            </a>

            <a class="svws-nav-card" href="/sync_svws.php">
                <p class="svws-nav-card-title">SVWS Sync</p>
                <p class="svws-nav-card-text">Import von Schüler- und Lehrerdaten aus dem SVWS-Server.</p>
            </a>

            <a class="svws-nav-card" href="/sync_data.php">
                <p class="svws-nav-card-title">SVWS Daten</p>
                <p class="svws-nav-card-text">Tabellenansichten für synchronisierte Schüler- und Lehrerdaten.</p>
            </a>

            <a class="svws-nav-card" href="/borrowers.php">
                <p class="svws-nav-card-title">Ausleiher</p>
                <p class="svws-nav-card-text">Sperren, Memo, Kontomigration und Vorjahres-Ausleihen.</p>
            </a>

            <a class="svws-nav-card" href="/reports.php">
                <p class="svws-nav-card-title">Druck & Export</p>
                <p class="svws-nav-card-text">Druckansichten und CSV-Exporte für Medienbestand und Kontoauszüge.</p>
            </a>
        </div>
    </div>
</div>
<?php
$content = ob_get_clean();

renderLayout('Dashboard', $content, 'dashboard');
