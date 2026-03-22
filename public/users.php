<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/config/config.php';
require_once __DIR__ . '/../src/auth/user.php';
require_once __DIR__ . '/../templates/layout.php';

requireLogin();
requireAdmin();

$currentUser = getCurrentUser();
$currentUserId = (int) ($currentUser['id'] ?? 0);
$forcePasswordChange = isset($_GET['force_pw_change']) && (string) $_GET['force_pw_change'] === '1';

$errorMessage = '';
$successMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrfToken();

    $action = (string) ($_POST['action'] ?? 'create');

    if ($action === 'create') {
        $username = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $role = (string) ($_POST['role'] ?? 'viewer');

        if (createUser($username, $password, $role)) {
            $successMessage = 'Benutzer wurde angelegt.';
        } else {
            $errorMessage = 'Benutzer konnte nicht angelegt werden (Name evtl. vorhanden oder Eingabe ungueltig). ' . getPasswordPolicyMessage();
        }
    } elseif ($action === 'change_password') {
        $oldPassword = (string) ($_POST['old_password'] ?? '');
        $newPassword = (string) ($_POST['new_password'] ?? '');

        if (changeOwnPassword($currentUserId, $oldPassword, $newPassword)) {
            $successMessage = 'Passwort wurde geaendert.';
            $forcePasswordChange = false;
        } else {
            $errorMessage = 'Passwort konnte nicht geaendert werden (aktuelles Passwort oder Eingabe ungueltig). ' . getPasswordPolicyMessage();
        }
    } elseif ($action === 'toggle_active') {
        $targetUserId = (int) ($_POST['target_user_id'] ?? 0);
        $newActive = (int) ($_POST['new_active'] ?? 0) === 1;

        if (updateUserActiveStatus($targetUserId, $newActive, $currentUserId)) {
            $successMessage = $newActive ? 'Benutzer wurde aktiviert.' : 'Benutzer wurde deaktiviert.';
        } else {
            $errorMessage = 'Status konnte nicht geaendert werden.';
        }
    } elseif ($action === 'delete_user') {
        $targetUserId = (int) ($_POST['target_user_id'] ?? 0);

        if (deleteUser($targetUserId, $currentUserId)) {
            $successMessage = 'Benutzer wurde geloescht.';
        } else {
            $errorMessage = 'Benutzer konnte nicht geloescht werden.';
        }
    }
}

$users = getAllUsers();

ob_start();
?>
<div class="svws-content-header">
    <div class="svws-avatar">U</div>
    <div>
        <p class="svws-title-main">Benutzerverwaltung</p>
        <div class="svws-title-sub">Lokale Konten fuer SVWS-Media</div>
    </div>
</div>

<section class="svws-panel" style="margin-bottom:8px;">
    <div class="svws-panel-header">
        <h3>Neuen Benutzer anlegen</h3>
    </div>
    <div class="svws-panel-body">
        <?php if ($forcePasswordChange): ?>
            <p style="margin-top:0;color:#a40000;"><strong>Sicherheits-Hinweis:</strong> Bitte zuerst das Standardpasswort aendern.</p>
        <?php endif; ?>
        <p class="svws-muted" style="margin-top:0;"><?= htmlspecialchars(getPasswordPolicyMessage()) ?></p>

        <form method="post" style="display:grid;grid-template-columns:repeat(3,minmax(180px,1fr));gap:8px;">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="create">
            <label>
                <span class="svws-muted">Benutzername</span><br>
                <input class="svws-search" type="text" name="username" required>
            </label>
            <label>
                <span class="svws-muted">Passwort</span><br>
                <input class="svws-search" type="password" name="password" required>
            </label>
            <label>
                <span class="svws-muted">Rolle</span><br>
                <select class="svws-filter" name="role">
                    <option value="viewer">viewer</option>
                    <option value="admin">admin</option>
                </select>
            </label>
            <div style="grid-column:1 / -1;">
                <button class="svws-help-btn" type="submit">Benutzer speichern</button>
            </div>
        </form>

        <?php if ($successMessage !== ''): ?>
            <p style="margin-top:8px;color:#0c5c0c;"><?= htmlspecialchars($successMessage) ?></p>
        <?php endif; ?>
        <?php if ($errorMessage !== ''): ?>
            <p style="margin-top:8px;color:#a40000;"><?= htmlspecialchars($errorMessage) ?></p>
        <?php endif; ?>
    </div>
</section>

<section class="svws-panel" style="margin-bottom:8px;">
    <div class="svws-panel-header">
        <h3>Eigenes Passwort aendern</h3>
    </div>
    <div class="svws-panel-body">
        <form method="post" style="display:grid;grid-template-columns:repeat(2,minmax(180px,1fr));gap:8px;max-width:640px;">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="change_password">
            <label>
                <span class="svws-muted">Aktuelles Passwort</span><br>
                <input class="svws-search" type="password" name="old_password" required>
            </label>
            <label>
                <span class="svws-muted">Neues Passwort</span><br>
                <input class="svws-search" type="password" name="new_password" required>
            </label>
            <div style="grid-column:1 / -1;">
                <button class="svws-help-btn" type="submit">Passwort speichern</button>
            </div>
        </form>
    </div>
</section>

<section class="svws-panel">
    <div class="svws-panel-header">
        <h3>Bestehende Benutzer</h3>
    </div>
    <div class="svws-panel-body">
        <table class="svws-tight">
            <thead>
            <tr>
                <th>ID</th>
                <th>Benutzername</th>
                <th>Rolle</th>
                <th>Status</th>
                <th>Aktionen</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($users as $user): ?>
                <tr>
                    <td><?= htmlspecialchars((string) $user['id']) ?></td>
                    <td><?= htmlspecialchars((string) $user['username']) ?></td>
                    <td><?= htmlspecialchars((string) $user['role']) ?></td>
                    <td><?= (int) ($user['is_active'] ?? 0) === 1 ? 'aktiv' : 'inaktiv' ?></td>
                    <td>
                        <?php if ((int) $user['id'] !== $currentUserId): ?>
                            <form method="post" style="display:inline-block; margin-right:4px;">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="toggle_active">
                                <input type="hidden" name="target_user_id" value="<?= htmlspecialchars((string) $user['id']) ?>">
                                <input type="hidden" name="new_active" value="<?= (int) ($user['is_active'] ?? 0) === 1 ? '0' : '1' ?>">
                                <button class="svws-help-btn" type="submit"><?= (int) ($user['is_active'] ?? 0) === 1 ? 'Deaktivieren' : 'Aktivieren' ?></button>
                            </form>
                            <form method="post" style="display:inline-block;">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="delete_user">
                                <input type="hidden" name="target_user_id" value="<?= htmlspecialchars((string) $user['id']) ?>">
                                <button class="svws-help-btn" type="submit">Loeschen</button>
                            </form>
                        <?php else: ?>
                            <span class="svws-muted">Eigenes Konto</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php
$content = ob_get_clean();

renderLayout('Benutzer', $content, 'admin');
