<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/config/config.php';
require_once __DIR__ . '/../src/auth/user.php';
require_once __DIR__ . '/../src/modules/lending/lending_service.php';

requireLogin();

$groupId = max(0, (int) ($_GET['group_id'] ?? 0));
$titleId = max(0, (int) ($_GET['title_id'] ?? 0));
$format = trim((string) ($_GET['format'] ?? 'html'));

$group = LendingService::getGroupById($groupId);
$title = null;
foreach (LendingService::getTitles('', 300) as $candidate) {
    if ((int) $candidate['id'] === $titleId) {
        $title = $candidate;
        break;
    }
}
$rows = LendingService::getGroupDistributionSheetRows($groupId, $titleId);

if ($format === 'csv') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="kursliste_' . $groupId . '_' . $titleId . '.csv"');

    $out = fopen('php://output', 'wb');
    if ($out === false) {
        exit;
    }

    fputcsv($out, ['Ausleiher-ID', 'Name', 'Typ', 'Klasse', 'Barcode', 'Inventar-Nr.', 'Ausgeliehen am', 'Status'], ';');
    foreach ($rows as $row) {
        fputcsv($out, [
            (string) $row['borrower_id'],
            LendingService::formatBorrowerName($row),
            (string) $row['kind'],
            (string) ($row['klasse'] ?? ''),
            (string) ($row['barcode'] ?? ''),
            (string) ($row['inventory_number'] ?? ''),
            (string) ($row['borrowed_at'] ?? ''),
            (string) ((int) ($row['lending_id'] ?? 0) > 0 ? 'zugeordnet' : 'offen'),
        ], ';');
    }

    fclose($out);
    exit;
}

$groupName = $group === null ? 'Unbekannte Gruppe' : (string) (($group['name'] ?: $group['kuerzel']) ?: ('ID ' . $group['svws_id']));
$titleName = $title === null ? 'Unbekannter Titel' : (string) $title['name'];
?>
<!doctype html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kursliste</title>
    <style>
        body { font-family: "Segoe UI", Arial, sans-serif; margin: 20px; color: #111; }
        h1 { margin: 0 0 4px; }
        .meta { color: #555; margin-bottom: 14px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #bbb; padding: 6px 8px; text-align: left; font-size: 12px; }
        th { background: #f0f0f0; }
        .empty { color: #666; }
        @media print { .no-print { display: none; } body { margin: 0; } }
    </style>
</head>
<body>
    <h1>Kursliste</h1>
    <div class="meta">
        Gruppe: <?= htmlspecialchars($groupName) ?> | Titel: <?= htmlspecialchars($titleName) ?> | Stand: <?= htmlspecialchars(date('d.m.Y H:i')) ?> Uhr
    </div>
    <div class="no-print" style="margin-bottom:10px;">
        <button onclick="window.print()">Drucken</button>
    </div>
    <table>
        <thead>
        <tr>
            <th>#</th>
            <th>Name</th>
            <th>Typ</th>
            <th>Klasse</th>
            <th>Barcode</th>
            <th>Inventar</th>
            <th>Status</th>
            <th>Unterschrift</th>
        </tr>
        </thead>
        <tbody>
        <?php if ($rows === []): ?>
            <tr><td colspan="8">Keine Daten vorhanden.</td></tr>
        <?php else: ?>
            <?php $n = 1; ?>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td><?= $n++ ?></td>
                    <td><?= htmlspecialchars(LendingService::formatBorrowerName($row)) ?></td>
                    <td><?= htmlspecialchars((string) $row['kind']) ?></td>
                    <td><?= htmlspecialchars((string) ($row['klasse'] ?? '')) ?></td>
                    <td><?= htmlspecialchars((string) ($row['barcode'] ?? '')) ?></td>
                    <td><?= htmlspecialchars((string) ($row['inventory_number'] ?? '')) ?></td>
                    <td class="<?= (int) ($row['lending_id'] ?? 0) > 0 ? '' : 'empty' ?>">
                        <?= (int) ($row['lending_id'] ?? 0) > 0 ? 'zugeordnet' : 'offen' ?>
                    </td>
                    <td>&nbsp;</td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</body>
</html>
