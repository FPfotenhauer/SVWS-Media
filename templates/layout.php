<?php

declare(strict_types=1);

function renderLayout(string $pageTitle, string $content, string $activeNav = 'dashboard'): void
{
    require __DIR__ . '/header.php';
    echo $content;
    require __DIR__ . '/footer.php';
}
