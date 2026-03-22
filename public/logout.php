<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/auth/user.php';

logoutUser();
header('Location: /login.php');
exit;
