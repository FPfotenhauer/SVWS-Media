<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/config/config.php';
require_once __DIR__ . '/../src/auth/user.php';
require_once __DIR__ . '/../templates/layout.php';

requireLogin();

ob_start();
?>
<h2>Uebersicht</h2>
<p class="svws-muted">Startbereich fuer die Medienverwaltung in einem SVWS-nahen Layoutstil.</p>

<div class="svws-kpis">
    <article class="svws-kpi">
        <div class="svws-kpi-value">0</div>
        <div class="svws-kpi-label">Medien gesamt</div>
    </article>
    <article class="svws-kpi">
        <div class="svws-kpi-value">0</div>
        <div class="svws-kpi-label">Offene Ausleihen</div>
    </article>
    <article class="svws-kpi">
        <div class="svws-kpi-value">0</div>
        <div class="svws-kpi-label">Ueberfaellige Rueckgaben</div>
    </article>
</div>

<div class="svws-panel" style="margin-top: 12px;">
    <div class="svws-panel-header">
        <h3>Navigationsbereiche</h3>
    </div>
    <div class="svws-panel-body">
        <table>
            <thead>
            <tr>
                <th>Bereich</th>
                <th>Beschreibung</th>
                <th>Aktion</th>
            </tr>
            </thead>
            <tbody>
            <tr>
                <td>Medienbestand</td>
                <td>Verwaltung der vorhandenen Medien und Stammdaten.</td>
                <td><a href="/media_list.php">Oeffnen</a></td>
            </tr>
            <tr>
                <td>Ausleihe</td>
                <td>Ausgabe und Rueckgabe per Barcode inkl. Zuordnung unbekannter Barcodes.</td>
                <td><a href="/lending.php">Oeffnen</a></td>
            </tr>
            <tr>
                <td>SVWS Sync</td>
                <td>Import von Schueler- und Lehrerdaten aus dem SVWS-Server.</td>
                <td><a href="/sync_svws.php">Oeffnen</a></td>
            </tr>
            <tr>
                <td>SVWS Daten</td>
                <td>Tabellenansichten fuer synchronisierte Schueler- und Lehrerdaten.</td>
                <td><a href="/sync_data.php">Oeffnen</a></td>
            </tr>
            <tr>
                <td>Ausleiher</td>
                <td>Sperren, Memo, Kontomigration und Vorjahres-Ausleihen.</td>
                <td><a href="/borrowers.php">Oeffnen</a></td>
            </tr>
            <tr>
                <td>Druck & Export</td>
                <td>Druckansichten und CSV-Exporte fuer Medienbestand und Kontoauszuege.</td>
                <td><a href="/reports.php">Oeffnen</a></td>
            </tr>
            </tbody>
        </table>
    </div>
</div>
<?php
$content = ob_get_clean();

renderLayout('Dashboard', $content, 'dashboard');
