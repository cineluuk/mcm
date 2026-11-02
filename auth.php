<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

function mcm_session_start(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
    $host = (string) preg_replace('/:\d+$/', '', $host);
    $local = $host === '127.0.0.1' || $host === 'localhost';
    $https = !$local
        || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function current_user(): ?array
{
    mcm_session_start();

    if (!isset($_SESSION['user_id'])) {
        return null;
    }

    $userId = (int) $_SESSION['user_id'];
    if ($userId < 1) {
        return null;
    }

    $stmt = db()->prepare(
        'SELECT id, username, role, is_active, club
         FROM mcm_users
         WHERE id = :id
         LIMIT 1'
    );
    $stmt->execute(['id' => $userId]);
    $user = $stmt->fetch();

    if ($user === false || (int) $user['is_active'] !== 1) {
        logout();
        return null;
    }

    $user['id'] = (int) $user['id'];
    $user['is_active'] = (int) $user['is_active'];
    $user['club'] = trim((string) ($user['club'] ?? ''));

    return $user;
}

function club_key(?string $value): string
{
    $value = trim((string) $value);
    if ($value === '') {
        return '';
    }

    return mb_strtolower($value, 'UTF-8');
}

function can_open_match(array $user, array $match): bool
{
    $userClub = club_key($user['club'] ?? '');
    $matchClub = club_key(isset($match['club']) ? (string) $match['club'] : '');
    if ($userClub !== '' && $matchClub !== '') {
        return $userClub === $matchClub;
    }

    return (int) ($match['created_by'] ?? 0) === (int) ($user['id'] ?? 0);
}

function require_match_access(array $user, array $match): void
{
    if (!can_open_match($user, $match)) {
        header('Location: /', true, 302);
        exit;
    }
}

function require_login(): array
{
    $user = current_user();
    if ($user === null) {
        header('Location: /login.php', true, 302);
        exit;
    }

    return $user;
}

function turnstile_ok(string $token): bool
{
    $token = trim($token);
    $secret = trim((string) (mcm_config()['turnstile_secret'] ?? ''));
    if ($token === '' || $secret === '' || !function_exists('curl_init')) {
        return false;
    }

    $ch = curl_init('https://challenges.cloudflare.com/turnstile/v0/siteverify');
    if ($ch === false) {
        return false;
    }

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_POSTFIELDS => http_build_query([
            'secret' => $secret,
            'response' => $token,
            'remoteip' => (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
        ]),
    ]);
    $body = curl_exec($ch);
    if (!is_string($body)) {
        return false;
    }

    $json = json_decode($body, true);

    return is_array($json) && ($json['success'] ?? false) === true;
}

function attempt_login(string $password, string $club): bool
{
    $password = trim($password);
    $club = trim($club);
    if ($password === '' || $club === '') {
        return false;
    }

    $wanted = club_key($club);
    $users = db()->query(
        'SELECT id, password_hash, is_active, club
         FROM mcm_users
         WHERE is_active = 1'
    )->fetchAll();

    $matched = null;
    foreach ($users as $user) {
        $stored = trim((string) ($user['club'] ?? ''));
        $storedKey = club_key($stored);
        if ($storedKey !== '' && $storedKey !== $wanted) {
            continue;
        }
        if (!password_verify($password, (string) $user['password_hash'])) {
            continue;
        }
        $matched = $user;
        if ($storedKey === $wanted) {
            break;
        }
    }

    if ($matched === null) {
        return false;
    }

    if (club_key((string) ($matched['club'] ?? '')) === '') {
        $update = db()->prepare('UPDATE mcm_users SET club = :club WHERE id = :id');
        $update->execute([
            'club' => $club,
            'id' => (int) $matched['id'],
        ]);
    }

    mcm_session_start();
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $matched['id'];

    return true;
}

function csrf_token(): string
{
    mcm_session_start();

    if (!isset($_SESSION['csrf']) || !is_string($_SESSION['csrf']) || $_SESSION['csrf'] === '') {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }

    return $_SESSION['csrf'];
}

function csrf_ok(): bool
{
    $sent = (string) ($_POST['csrf'] ?? '');
    $token = csrf_token();

    return $sent !== '' && hash_equals($token, $sent);
}

function logout(): void
{
    mcm_session_start();
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        $cookie = [
            'expires' => time() - 42000,
            'path' => $params['path'] !== '' ? $params['path'] : '/',
            'secure' => (bool) $params['secure'],
            'httponly' => (bool) $params['httponly'],
            'samesite' => 'Lax',
        ];
        if ($params['domain'] !== '') {
            $cookie['domain'] = $params['domain'];
        }
        setcookie(session_name(), '', $cookie);
    }

    session_destroy();
}
