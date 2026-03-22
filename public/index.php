<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/auth/user.php';

header('Location: ' . (getCurrentUser() !== null ? '/dashboard.php' : '/login.php'));
exit;
