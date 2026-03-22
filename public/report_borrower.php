<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/config/config.php';
require_once __DIR__ . '/../src/auth/user.php';
require_once __DIR__ . '/../src/modules/lending/lending_service.php';

requireLogin();

$borrowerId = max(0, (int) ($_GET['borrower_id'] ?? 0));
$mode = trim((string) ($_GET['mode'] ?? 'open'));
$includeReturned = $mode === 'all';
$format = trim((string) ($_GET['format'] ?? 'html'));

$borrower = LendingService::getBorrowerById($borrowerId);
$rows = LendingService::getBorrowerStatementRows($borrowerId, $includeReturned);

if ($format === 'csv') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="kontoauszug_' . $borrowerId . '.csv"');

    $out = fopen('php://output', 'wb');
    if ($out === false) {
        exit;
    }

    fputcsv($out, ['Ausleihe-ID', 'Titel', 'Barcode', 'Inventar-Nr.', 'Ausgeliehen am', 'Zurueckgegeben am', 'Schuljahr', 'Klasse', 'Kurs', 'Kurs-Lehrkraft', 'Status'], ';');
    foreach ($rows as $row) {
        fputcsv($out, [
            (string) $row['id'],
            (string) $row['title'],
            (string) ($row['barcode'] ?? ''),
            (string) ($row['inventory_number'] ?? ''),
            (string) $row['borrowed_at'],
            (string) ($row['returned_at'] ?? ''),
            (string) ($row['school_year'] ?? ''),
            (string) ($row['klasse_snapshot'] ?? ''),
            (string) ($row['kurs_snapshot'] ?? ''),
            (string) ($row['kurs_lehrer_snapshot'] ?? ''),
            (string) ($row['status'] ?? ''),
        ], ';');
    }

    fclose($out);
    exit;
}

$borrowerName = $borrower !== null ? LendingService::formatBorrowerName($borrower) : 'Unbekannt';
?>
<!doctype html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kontoauszug</title>
    <style>
        body { font-family: "Segoe UI", Arial, sans-serif; margin: 20px; color: #111; }
        h1 { margin: 0 0 4px; }
        .meta { color: #555; margin-bottom: 14px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #bbb; padding: 6px 8px; text-align: left; font-size: 12px; }
        th { background: #f0f0f0; }
        @media print { .no-print { display: none; } body { margin: 0; } }
    </style>
</head>
<body>
    <h1>Kontoauszug</h1>
    <div class="meta">
        <?= htmlspecialchars($borrowerName) ?>
        <?php if ($borrower !== null && trim((string) ($borrower['klasse'] ?? '')) !== ''): ?>
            | Klasse: <?= htmlspecialchars((string) $borrower['klasse']) ?>
        <?php endif; ?>
        | Stand: <?= htmlspecialchars(date('d.m.Y H:i')) ?> Uhr
    </div>
    <div class="no-print" style="margin-bottom:10px;">
        <button onclick="window.print()">Drucken</button>
    </div>
    <table>
        <thead>
        <tr>
            <th>ID</th>
            <th>Titel</th>
            <th>Barcode</th>
            <th>Inventar-Nr.</th>
            <th>Ausgeliehen</th>
            <th>Rueckgabe</th>
            <th>Schuljahr</th>
            <th>Klasse/Kurs</th>
            <th>Status</th>
        </tr>
        </thead>
        <tbody>
        <?php if ($rows === []): ?>
            <tr><td colspan="9">Keine passenden Ausleihen.</td></tr>
        <?php else: ?>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td><?= htmlspecialchars((string) $row['id']) ?></td>
                    <td><?= htmlspecialchars((string) $row['title']) ?></td>
                    <td><?= htmlspecialchars((string) ($row['barcode'] ?? '')) ?></td>
                    <td><?= htmlspecialchars((string) ($row['inventory_number'] ?? '')) ?></td>
                    <td><?= htmlspecialchars((string) $row['borrowed_at']) ?></td>
                    <td><?= htmlspecialchars((string) ($row['returned_at'] ?? '')) ?></td>
                    <td><?= htmlspecialchars((string) ($row['school_year'] ?? '')) ?></td>
                    <td>
                        <?= htmlspecialchars((string) ($row['klasse_snapshot'] ?? '')) ?>
                        <?= htmlspecialchars((string) ($row['kurs_snapshot'] ?? '')) ?>
                    </td>
                    <td><?= htmlspecialchars((string) ($row['status'] ?? '')) ?></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</body>
</html>
