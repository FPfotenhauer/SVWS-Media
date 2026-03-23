<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/config/config.php';
require_once __DIR__ . '/../src/auth/user.php';

if (getCurrentUser() !== null) {
    header('Location: /dashboard.php');
    exit;
}

$errorMessage = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isValidCsrfTokenFromPost()) {
        $errorMessage = 'Sitzung abgelaufen. Bitte Formular erneut senden.';
    } else {
        $username = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        if (authenticateUser($username, $password)) {
            header('Location: /dashboard.php');
            exit;
        }

        $errorMessage = getAndClearLastLoginError();
        if ($errorMessage === '') {
            $errorMessage = 'Ungueltiger Benutzername oder Passwort.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | <?= htmlspecialchars(APP_NAME) ?></title>
    <style>
        body { margin: 0; font-family: "Segoe UI", Arial, sans-serif; background: #ececec; color: #111; }
        .login-wrap { min-height: 100vh; display: grid; place-items: center; }
        .login-card { width: min(420px, 92vw); background: #f2f2f2; border: 1px solid #d1d1d1; border-radius: 12px; padding: 18px; }
        h1 { margin: 0 0 12px; font-size: 22px; }
        label { display: block; margin-bottom: 8px; font-size: 12px; }
        input { width: 100%; box-sizing: border-box; border: 1px solid #afafaf; border-radius: 4px; height: 34px; padding: 6px 8px; margin-top: 4px; }
        button { margin-top: 10px; border: 1px solid #9f9f9f; border-radius: 16px; background: #fff; padding: 6px 14px; cursor: pointer; }
        .error { margin-top: 10px; color: #980000; font-size: 12px; }
        .hint { margin-top: 12px; color: #666; font-size: 12px; }
    </style>
</head>
<body>
<div class="login-wrap">
    <div class="login-card">
        <h1>SVWS-Media Anmeldung</h1>
        <form method="post">
            <?= csrfField() ?>
            <label>
                Benutzername
                <input type="text" name="username" value="<?= htmlspecialchars($username) ?>" required>
            </label>
            <label>
                Passwort
                <input type="password" name="password" required>
            </label>
            <button type="submit">Anmelden</button>
        </form>
        <?php if ($errorMessage !== ''): ?>
            <div class="error"><?= htmlspecialchars($errorMessage) ?></div>
        <?php endif; ?>
        <div class="hint">Default: Admin / admin</div>
    </div>
</div>
</body>
</html>
