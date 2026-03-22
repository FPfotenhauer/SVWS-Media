<?php

declare(strict_types=1);

require_once __DIR__ . '/lending_service.php';

$openLendings = LendingService::getOpenLendings();
