<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

function ensureSessionStarted(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
}

function getCurrentUser(): ?array
{
    ensureSessionStarted();

    if (empty($_SESSION['username']) || !isset($_SESSION['user_id'])) {
        return null;
    }

    return [
        'id' => (int) $_SESSION['user_id'],
        'username' => (string) $_SESSION['username'],
        'role' => (string) ($_SESSION['role'] ?? 'viewer'),
    ];
}

function requireLogin(): void
{
    if (getCurrentUser() !== null) {
        return;
    }

    header('Location: /login.php');
    exit;
}

function requireAdmin(): void
{
    $user = getCurrentUser();
    if ($user !== null && $user['role'] === 'admin') {
        return;
    }

    http_response_code(403);
    echo 'Zugriff verweigert.';
    exit;
}

function authenticateUser(string $username, string $password): bool
{
    $username = trim($username);
    if ($username === '' || $password === '') {
        return false;
    }

    $db = getDB();
    $stmt = $db->prepare('SELECT id, username, password_hash, role, is_active FROM users WHERE username = :username LIMIT 1');
    $stmt->execute(['username' => $username]);
    $row = $stmt->fetch();

    if ($row === false) {
        return false;
    }

    if ((int) ($row['is_active'] ?? 1) !== 1) {
        return false;
    }

    $hash = (string) ($row['password_hash'] ?? '');
    if ($hash === '' || !password_verify($password, $hash)) {
        return false;
    }

    ensureSessionStarted();
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $row['id'];
    $_SESSION['username'] = (string) $row['username'];
    $_SESSION['role'] = (string) $row['role'];

    return true;
}

function logoutUser(): void
{
    ensureSessionStarted();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool) $params['secure'], (bool) $params['httponly']);
    }
    session_destroy();
}

function getAllUsers(): array
{
    $db = getDB();
    $stmt = $db->query('SELECT id, username, role, is_active FROM users ORDER BY username');

    return $stmt->fetchAll();
}

function createUser(string $username, string $password, string $role = 'viewer'): bool
{
    $username = trim($username);
    $role = trim($role);

    if ($username === '' || $password === '') {
        return false;
    }
    if (!in_array($role, ['admin', 'viewer'], true)) {
        return false;
    }

    $db = getDB();
    $stmt = $db->prepare('INSERT INTO users (username, password_hash, role, is_active) VALUES (:username, :password_hash, :role, :is_active)');

    try {
        $stmt->execute([
            'username' => $username,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'role' => $role,
            'is_active' => 1,
        ]);
    } catch (PDOException $e) {
        return false;
    }

    return true;
}

function changeOwnPassword(int $userId, string $currentPassword, string $newPassword): bool
{
    $currentPassword = trim($currentPassword);
    $newPassword = trim($newPassword);
    if ($userId <= 0 || $currentPassword === '' || $newPassword === '') {
        return false;
    }

    $db = getDB();
    $stmt = $db->prepare('SELECT password_hash FROM users WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $userId]);
    $row = $stmt->fetch();

    if ($row === false) {
        return false;
    }

    $currentHash = (string) ($row['password_hash'] ?? '');
    if ($currentHash === '' || !password_verify($currentPassword, $currentHash)) {
        return false;
    }

    $update = $db->prepare('UPDATE users SET password_hash = :password_hash WHERE id = :id');
    $update->execute([
        'password_hash' => password_hash($newPassword, PASSWORD_DEFAULT),
        'id' => $userId,
    ]);

    return true;
}

function updateUserActiveStatus(int $targetUserId, bool $isActive, int $actorUserId): bool
{
    if ($targetUserId <= 0 || $actorUserId <= 0 || $targetUserId === $actorUserId) {
        return false;
    }

    $db = getDB();
    $stmt = $db->prepare('SELECT id, role, is_active FROM users WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $targetUserId]);
    $target = $stmt->fetch();
    if ($target === false) {
        return false;
    }

    if ((string) $target['role'] === 'admin' && !$isActive && countActiveAdmins() <= 1) {
        return false;
    }

    $update = $db->prepare('UPDATE users SET is_active = :is_active WHERE id = :id');
    $update->execute([
        'is_active' => $isActive ? 1 : 0,
        'id' => $targetUserId,
    ]);

    return true;
}

function deleteUser(int $targetUserId, int $actorUserId): bool
{
    if ($targetUserId <= 0 || $actorUserId <= 0 || $targetUserId === $actorUserId) {
        return false;
    }

    $db = getDB();
    $stmt = $db->prepare('SELECT id, role FROM users WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $targetUserId]);
    $target = $stmt->fetch();
    if ($target === false) {
        return false;
    }

    if ((string) $target['role'] === 'admin' && countActiveAdmins() <= 1) {
        return false;
    }

    $delete = $db->prepare('DELETE FROM users WHERE id = :id');
    $delete->execute(['id' => $targetUserId]);

    return true;
}

function countActiveAdmins(): int
{
    $db = getDB();
    $stmt = $db->query("SELECT COUNT(*) AS c FROM users WHERE role = 'admin' AND is_active = 1");
    $row = $stmt->fetch();

    return (int) ($row['c'] ?? 0);
}
