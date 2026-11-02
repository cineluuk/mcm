<?php
declare(strict_types=1);

function swiss_teams(): array
{
    $cache = __DIR__ . '/data/swiss-teams.json';
    $cached = read_swiss_cache($cache, true);
    if ($cached !== null && is_file($cache) && filemtime($cache) >= time() - 7 * 24 * 3600) {
        return $cached;
    }

    $teams = fetch_swiss_teams();
    if ($teams === []) {
        return $cached ?? [];
    }

    $dir = dirname($cache);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    file_put_contents($cache, json_encode($teams, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

    return $teams;
}

function swiss_team_logo(string $club): ?string
{
    $wanted = club_key($club);
    if ($wanted === '') {
        return null;
    }

    foreach (swiss_teams() as $team) {
        if (club_key((string) $team['name']) === $wanted) {
            return (string) $team['logo'];
        }
    }

    return null;
}

function read_swiss_cache(string $cache, bool $allowStale = false): ?array
{
    if (!is_file($cache) || (!$allowStale && filemtime($cache) < time() - 7 * 24 * 3600)) {
        return null;
    }

    $decoded = json_decode((string) file_get_contents($cache), true);
    if (!is_array($decoded) || $decoded === []) {
        return null;
    }

    return $decoded;
}

function fetch_swiss_teams(): array
{
    $cfg = function_exists('mcm_config') ? mcm_config() : (require __DIR__ . '/config.php');
    if (!is_array($cfg)) {
        return [];
    }
    $key = trim((string) ($cfg['football_key'] ?? ''));
    if ($key === '' || !function_exists('curl_init')) {
        return [];
    }

    $leagues = [
        207 => 'Super League',
        208 => 'Challenge League',
        510 => 'Promotion League',
    ];
    $teams = [];
    foreach ($leagues as $id => $league) {
        $payload = football_get(
            'https://v3.football.api-sports.io/teams?league=' . $id . '&season=2024',
            $key
        );
        foreach ($payload as $row) {
            $team = $row['team'] ?? null;
            if (!is_array($team) || !isset($team['id'], $team['name'], $team['logo'])) {
                continue;
            }
            $teamId = (int) $team['id'];
            if (isset($teams[$teamId])) {
                continue;
            }
            $teams[$teamId] = [
                'id' => $teamId,
                'name' => (string) $team['name'],
                'logo' => (string) $team['logo'],
                'league' => $league,
            ];
        }
    }

    $list = array_values($teams);
    usort($list, static function (array $a, array $b): int {
        $league = ['Super League' => 0, 'Challenge League' => 1, 'Promotion League' => 2];
        $byLeague = ($league[$a['league']] ?? 9) <=> ($league[$b['league']] ?? 9);
        if ($byLeague !== 0) {
            return $byLeague;
        }

        return strcasecmp((string) $a['name'], (string) $b['name']);
    });

    return $list;
}

function football_get(string $url, string $key): array
{
    $ch = curl_init($url);
    if ($ch === false) {
        return [];
    }

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTPHEADER => ['x-apisports-key: ' . $key],
    ]);
    $body = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if (!is_string($body) || $code !== 200) {
        return [];
    }

    $json = json_decode($body, true);
    $response = is_array($json) ? ($json['response'] ?? null) : null;

    return is_array($response) ? $response : [];
}
