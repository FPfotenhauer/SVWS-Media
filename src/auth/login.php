<?php

declare(strict_types=1);

session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $_SESSION['username'] = trim((string) ($_POST['username'] ?? ''));
    $_SESSION['role'] = 'admin';
    header('Location: /dashboard.php');
    exit;
}
