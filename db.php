<?php
declare(strict_types=1);

function mcm_config(): array
{
    static $cfg = null;
    if (is_array($cfg)) {
        return $cfg;
    }

    $loaded = require __DIR__ . '/config.php';
    $cfg = is_array($loaded) ? $loaded : [];
    $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
    $host = (string) preg_replace('/:\d+$/', '', $host);
    $localFile = __DIR__ . '/config.local.php';
    if (($host === '127.0.0.1' || $host === 'localhost') && is_file($localFile)) {
        $local = require $localFile;
        if (is_array($local)) {
            $cfg = array_merge($cfg, $local);
        }
    }

    return $cfg;
}

function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $cfg = mcm_config();

    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ];

    if (($cfg['driver'] ?? 'mysql') === 'sqlite') {
        $pdo = new PDO('sqlite:' . $cfg['path'], null, null, $options);
        $pdo->exec('PRAGMA foreign_keys = ON');
        mcm_ensure_club_columns($pdo);

        return $pdo;
    }

    $pdo = new PDO(
        sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            $cfg['host'],
            $cfg['name'],
            $cfg['charset']
        ),
        $cfg['user'],
        $cfg['pass'],
        $options
    );
    mcm_ensure_club_columns($pdo);

    return $pdo;
}

function mcm_ensure_club_columns(PDO $pdo): void
{
    $driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver === 'sqlite') {
        $userCols = array_column($pdo->query('PRAGMA table_info(mcm_users)')->fetchAll(), 'name');
        if (!in_array('club', $userCols, true)) {
            $pdo->exec('ALTER TABLE mcm_users ADD COLUMN club TEXT');
        }
        $matchCols = array_column($pdo->query('PRAGMA table_info(mcm_matches)')->fetchAll(), 'name');
        if (!in_array('club', $matchCols, true)) {
            $pdo->exec('ALTER TABLE mcm_matches ADD COLUMN club TEXT');
        }
        return;
    }

    if ($pdo->query("SHOW COLUMNS FROM mcm_users LIKE 'club'")->fetch() === false) {
        $pdo->exec('ALTER TABLE mcm_users ADD COLUMN club VARCHAR(190) NULL');
    }
    if ($pdo->query("SHOW COLUMNS FROM mcm_matches LIKE 'club'")->fetch() === false) {
        $pdo->exec('ALTER TABLE mcm_matches ADD COLUMN club VARCHAR(190) NULL');
    }
}
