<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

function ensureSessionStarted(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        $isHttps =
            (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443);

        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        ini_set('session.use_strict_mode', '1');
        session_start();
    }
}

function getCsrfToken(): string
{
    ensureSessionStarted();

    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrfField(): string
{
    return '<input type="hidden" name="csrf_token" value="'
        . htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8')
        . '">';
}

function isValidCsrfTokenFromPost(): bool
{
    ensureSessionStarted();

    $token = (string) ($_POST['csrf_token'] ?? '');
    $stored = (string) ($_SESSION['csrf_token'] ?? '');

    return $token !== '' && $stored !== '' && hash_equals($stored, $token);
}

function requireValidCsrfToken(): void
{
    if (!isValidCsrfTokenFromPost()) {
        // Rotate token so the next form submit can recover without manual cleanup.
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        http_response_code(400);
        echo 'Ungueltiges CSRF-Token.';
        exit;
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
    $user = getCurrentUser();
    if ($user !== null) {
        if (mustChangePasswordNow($user)) {
            $path = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
            if ($path !== '/users.php' && $path !== '/logout.php') {
                header('Location: /users.php?force_pw_change=1');
                exit;
            }
        }

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
        setLastLoginError('Ungueltiger Benutzername oder Passwort.');
        return false;
    }

    $ipAddress = getClientIpAddress();

    $db = getDB();
    $stmt = $db->prepare('SELECT id, username, password_hash, role, is_active, must_change_password FROM users WHERE username = :username LIMIT 1');
    $stmt->execute(['username' => $username]);
    $row = $stmt->fetch();

    $isRateLimited = isLoginRateLimited($db, $username, $ipAddress);

    if ($row === false) {
        if ($isRateLimited) {
            setLastLoginError('Zu viele fehlgeschlagene Anmeldeversuche. Bitte in 15 Minuten erneut versuchen.');
            return false;
        }

        recordLoginAttempt($db, $username, $ipAddress, false);
        setLastLoginError('Ungueltiger Benutzername oder Passwort.');
        return false;
    }

    if ((int) ($row['is_active'] ?? 1) !== 1) {
        if ($isRateLimited) {
            setLastLoginError('Zu viele fehlgeschlagene Anmeldeversuche. Bitte in 15 Minuten erneut versuchen.');
            return false;
        }

        recordLoginAttempt($db, $username, $ipAddress, false);
        setLastLoginError('Benutzer ist deaktiviert.');
        return false;
    }

    $hash = (string) ($row['password_hash'] ?? '');
    $passwordMatches = $hash !== '' && password_verify($password, $hash);

    if (!$passwordMatches) {
        if ($isRateLimited) {
            setLastLoginError('Zu viele fehlgeschlagene Anmeldeversuche. Bitte in 15 Minuten erneut versuchen.');
            return false;
        }

        recordLoginAttempt($db, $username, $ipAddress, false);
        setLastLoginError('Ungueltiger Benutzername oder Passwort.');
        return false;
    }

    ensureSessionStarted();
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $row['id'];
    $_SESSION['username'] = (string) $row['username'];
    $_SESSION['role'] = (string) $row['role'];
    $_SESSION['must_change_password'] = (int) ($row['must_change_password'] ?? 0) === 1;
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    unset($_SESSION['last_login_error']);
    clearFailedLoginAttempts($db, $username, $ipAddress);
    recordLoginAttempt($db, $username, $ipAddress, true);

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
    if (!isStrongPassword($password)) {
        return false;
    }
    if (!in_array($role, ['admin', 'viewer'], true)) {
        return false;
    }

    $db = getDB();
    $stmt = $db->prepare('INSERT INTO users (username, password_hash, role, is_active, must_change_password) VALUES (:username, :password_hash, :role, :is_active, :must_change_password)');

    try {
        $stmt->execute([
            'username' => $username,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'role' => $role,
            'is_active' => 1,
            'must_change_password' => 0,
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
    if (!isStrongPassword($newPassword)) {
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

    $update = $db->prepare('UPDATE users SET password_hash = :password_hash, must_change_password = 0 WHERE id = :id');
    $update->execute([
        'password_hash' => password_hash($newPassword, PASSWORD_DEFAULT),
        'id' => $userId,
    ]);

    ensureSessionStarted();
    if ((int) ($_SESSION['user_id'] ?? 0) === $userId) {
        $_SESSION['must_change_password'] = false;
    }

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

function getPasswordPolicyMessage(): string
{
    return 'Passwort muss mindestens 10 Zeichen enthalten sowie mindestens einen Buchstaben und eine Ziffer.';
}

function isStrongPassword(string $password): bool
{
    if (strlen($password) < 10) {
        return false;
    }

    return preg_match('/[A-Za-z]/', $password) === 1
        && preg_match('/[0-9]/', $password) === 1;
}

function getClientIpAddress(): string
{
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');

    return $ip === '' ? 'unknown' : $ip;
}

function isLoginRateLimited(PDO $db, string $username, string $ipAddress): bool
{
    $windowStart = gmdate('c', time() - 15 * 60);

    $cleanup = $db->prepare('DELETE FROM login_attempts WHERE attempted_at < :threshold');
    $cleanup->execute(['threshold' => gmdate('c', time() - 24 * 3600)]);

    $stmt = $db->prepare(
        'SELECT COUNT(*) AS c
         FROM login_attempts
         WHERE username = :username
           AND ip_address = :ip
           AND success = 0
           AND attempted_at >= :window_start'
    );
    $stmt->execute([
        'username' => $username,
        'ip' => $ipAddress,
        'window_start' => $windowStart,
    ]);

    return (int) ($stmt->fetch()['c'] ?? 0) >= 5;
}

function recordLoginAttempt(PDO $db, string $username, string $ipAddress, bool $success): void
{
    $stmt = $db->prepare(
        'INSERT INTO login_attempts (username, ip_address, attempted_at, success)
         VALUES (:username, :ip_address, :attempted_at, :success)'
    );
    $stmt->execute([
        'username' => $username,
        'ip_address' => $ipAddress,
        'attempted_at' => gmdate('c'),
        'success' => $success ? 1 : 0,
    ]);
}

function clearFailedLoginAttempts(PDO $db, string $username, string $ipAddress): void
{
    $stmt = $db->prepare(
        'DELETE FROM login_attempts
         WHERE username = :username
           AND ip_address = :ip_address
           AND success = 0'
    );
    $stmt->execute([
        'username' => $username,
        'ip_address' => $ipAddress,
    ]);
}

function mustChangePasswordNow(?array $user = null): bool
{
    $user = $user ?? getCurrentUser();
    if ($user === null) {
        return false;
    }

    ensureSessionStarted();

    return (bool) ($_SESSION['must_change_password'] ?? false);
}

function setLastLoginError(string $message): void
{
    ensureSessionStarted();
    $_SESSION['last_login_error'] = $message;
}

function getAndClearLastLoginError(): string
{
    ensureSessionStarted();
    $message = (string) ($_SESSION['last_login_error'] ?? '');
    unset($_SESSION['last_login_error']);

    return $message;
}
