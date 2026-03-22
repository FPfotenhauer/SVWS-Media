<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/config/config.php';
require_once __DIR__ . '/../src/auth/user.php';
require_once __DIR__ . '/../src/modules/media/media_service.php';

requireLogin();

$rows = MediaService::getInventoryReportRows();
$format = trim((string) ($_GET['format'] ?? 'html'));

if ($format === 'csv') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="medienbestand.csv"');

    $out = fopen('php://output', 'wb');
    if ($out === false) {
        exit;
    }

    fputcsv($out, ['Titel', 'Typ', 'Standort', 'Gesamt', 'Aktiv', 'Verliehen', 'Verfuegbar'], ';');
    foreach ($rows as $row) {
        fputcsv($out, [
            (string) $row['title'],
            (string) ($row['type'] ?? ''),
            (string) ($row['location'] ?? ''),
            (string) $row['copies_total'],
            (string) $row['copies_active'],
            (string) $row['copies_lent'],
            (string) $row['copies_available'],
        ], ';');
    }

    fclose($out);
    exit;
}
?>
<!doctype html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medienbestand</title>
    <style>
        body { font-family: "Segoe UI", Arial, sans-serif; margin: 20px; color: #111; }
        h1 { margin: 0 0 4px; }
        .meta { color: #555; margin-bottom: 16px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #bbb; padding: 6px 8px; text-align: left; font-size: 12px; }
        th { background: #f0f0f0; }
        @media print { .no-print { display: none; } body { margin: 0; } }
    </style>
</head>
<body>
    <h1>Medienbestand</h1>
    <div class="meta">Stand: <?= htmlspecialchars(date('d.m.Y H:i')) ?> Uhr</div>
    <div class="no-print" style="margin-bottom:10px;">
        <button onclick="window.print()">Drucken</button>
    </div>
    <table>
        <thead>
        <tr>
            <th>Titel</th>
            <th>Typ</th>
            <th>Standort</th>
            <th>Gesamt</th>
            <th>Aktiv</th>
            <th>Verliehen</th>
            <th>Verfuegbar</th>
        </tr>
        </thead>
        <tbody>
        <?php if ($rows === []): ?>
            <tr><td colspan="7">Keine Daten vorhanden.</td></tr>
        <?php else: ?>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td><?= htmlspecialchars((string) $row['title']) ?></td>
                    <td><?= htmlspecialchars((string) ($row['type'] ?? '')) ?></td>
                    <td><?= htmlspecialchars((string) ($row['location'] ?? '')) ?></td>
                    <td><?= htmlspecialchars((string) $row['copies_total']) ?></td>
                    <td><?= htmlspecialchars((string) $row['copies_active']) ?></td>
                    <td><?= htmlspecialchars((string) $row['copies_lent']) ?></td>
                    <td><?= htmlspecialchars((string) $row['copies_available']) ?></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</body>
</html>
