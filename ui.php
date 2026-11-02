<?php
declare(strict_types=1);

function current_lang(): string
{
    mcm_session_start();
    $allowed = ['de', 'it', 'fr', 'en'];
    $pick = strtolower((string) ($_GET['lang'] ?? ''));
    if (in_array($pick, $allowed, true)) {
        $_SESSION['lang'] = $pick;
    }

    $lang = strtolower((string) ($_SESSION['lang'] ?? 'de'));

    return in_array($lang, $allowed, true) ? $lang : 'de';
}

function topbar(bool $showLogout): void
{
    unset($showLogout);

    echo '<header class="topbar">';
    echo '<button class="back-link" type="button" aria-label="Zurück" onclick="history.back()">';
    echo '<svg viewBox="0 0 24 24" width="28" height="28" aria-hidden="true"><path d="M15 5L8 12l7 7" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"></path></svg>';
    echo '</button>';
    echo '</header>';
}

function page_start(string $title): void
{
    $lang = current_lang();
    $htmlLang = ['de' => 'de-CH', 'it' => 'it-CH', 'fr' => 'fr-CH', 'en' => 'en'][$lang];
    $safe = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    echo <<<HTML
<!doctype html>
<html lang="{$htmlLang}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#111111">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">
    <title>{$safe}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/css/app.css?v=20">
</head>
<body>
<div class="app">
HTML;
    topbar(false);
}

function page_end(string $active): void
{
    unset($active);
    echo '</div></body></html>';
}

function club_initials(string $club): string
{
    $parts = preg_split('/\s+/u', trim($club)) ?: [];
    $letters = '';
    foreach ($parts as $part) {
        if ($part === '') {
            continue;
        }
        $letters .= mb_strtoupper(mb_substr($part, 0, 1, 'UTF-8'), 'UTF-8');
        if (mb_strlen($letters, 'UTF-8') >= 3) {
            break;
        }
    }

    return $letters !== '' ? $letters : '–';
}

function club_logo_file(string $club): ?string
{
    $key = club_key($club);
    if ($key === '') {
        return null;
    }

    $base = __DIR__ . '/uploads/clubs/' . hash('sha256', $key);
    foreach (['png', 'jpg', 'jpeg', 'webp'] as $ext) {
        $path = $base . '.' . $ext;
        if (is_file($path)) {
            return $path;
        }
    }

    return null;
}

function club_logo_url(string $club): ?string
{
    $file = club_logo_file($club);
    if ($file === null) {
        return null;
    }

    return '/uploads/clubs/' . rawurlencode(basename($file));
}

function move_club_logo(string $fromClub, string $toClub): void
{
    if (club_key($fromClub) === '' || club_key($fromClub) === club_key($toClub)) {
        return;
    }

    $file = club_logo_file($fromClub);
    if ($file === null) {
        return;
    }

    $dir = __DIR__ . '/uploads/clubs';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $target = $dir . '/' . hash('sha256', club_key($toClub)) . '.' . pathinfo($file, PATHINFO_EXTENSION);
    if ($file !== $target) {
        rename($file, $target);
    }
}

function store_club_logo(string $club, array $file): ?string
{
    if (!isset($file['error']) || (int) $file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    $error = (int) $file['error'];
    if ($error !== UPLOAD_ERR_OK) {
        return 'Das Logo hat nicht geklappt.';
    }

    $info = @getimagesize((string) $file['tmp_name']);
    $types = [
        IMAGETYPE_JPEG => 'jpg',
        IMAGETYPE_PNG => 'png',
        IMAGETYPE_WEBP => 'webp',
    ];
    if (!is_array($info) || !isset($types[$info[2]])) {
        return 'Das Logo braucht JPG, PNG oder WebP.';
    }

    $dir = __DIR__ . '/uploads/clubs';
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        return 'Das Logo hat nicht geklappt.';
    }

    $hash = hash('sha256', club_key($club));
    foreach (glob($dir . '/' . $hash . '.*') ?: [] as $old) {
        unlink($old);
    }

    $target = $dir . '/' . $hash . '.' . $types[$info[2]];
    if (!move_uploaded_file((string) $file['tmp_name'], $target)) {
        return 'Das Logo hat nicht geklappt.';
    }

    return null;
}

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function processed_photos(): array
{
    $dir = __DIR__ . '/uploads/processed/';
    $images = array_merge(
        glob($dir . '*.jpg') ?: [],
        glob($dir . '*.JPG') ?: [],
        glob($dir . '*.jpeg') ?: [],
        glob($dir . '*.JPEG') ?: []
    );
    usort($images, static function (string $a, string $b): int {
        return filemtime($b) <=> filemtime($a);
    });

    return $images;
}

function valid_photo(string $name): ?string
{
    $name = basename($name);
    $path = __DIR__ . '/uploads/processed/' . $name;
    if (preg_match('/^[A-Za-z0-9._-]+\.jpe?g$/i', $name) !== 1 || !is_file($path)) {
        return null;
    }

    return $name;
}

function kickoff_moment(string $value): ?DateTimeImmutable
{
    $value = substr(trim($value), 0, 19);
    $tz = new DateTimeZone('Europe/Zurich');
    $dt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $value, $tz);

    return $dt === false ? null : $dt;
}

function kickoff_window(string $value): ?array
{
    $kickoff = kickoff_moment($value);
    if ($kickoff === null) {
        return null;
    }

    return [
        $kickoff->modify('-2 hours')->getTimestamp(),
        $kickoff->modify('+4 hours')->getTimestamp(),
    ];
}

function attach_photo_to_match(int $matchId, string $filename): void
{
    $filename = basename($filename);
    if (valid_photo($filename) === null) {
        return;
    }

    $stmt = db()->prepare(
        'SELECT id, match_id
         FROM mcm_photos
         WHERE filename = :filename
         LIMIT 1'
    );
    $stmt->execute(['filename' => $filename]);
    $row = $stmt->fetch();

    if ($row === false) {
        $insert = db()->prepare(
            'INSERT INTO mcm_photos (match_id, filename)
             VALUES (:match_id, :filename)'
        );
        $insert->execute([
            'match_id' => $matchId,
            'filename' => $filename,
        ]);
        return;
    }

    if ($row['match_id'] === null) {
        $update = db()->prepare(
            'UPDATE mcm_photos
             SET match_id = :match_id
             WHERE id = :id AND match_id IS NULL'
        );
        $update->execute([
            'match_id' => $matchId,
            'id' => $row['id'],
        ]);
    }
}

function claim_photos_for_match(int $matchId, string $kickoff): void
{
    $window = kickoff_window($kickoff);
    if ($window === null) {
        return;
    }

    [$from, $to] = $window;
    foreach (processed_photos() as $path) {
        $mtime = filemtime($path);
        if ($mtime === false || $mtime < $from || $mtime > $to) {
            continue;
        }
        attach_photo_to_match($matchId, basename($path));
    }
}

function photos_for_match(int $matchId, string $kickoff): array
{
    claim_photos_for_match($matchId, $kickoff);

    $stmt = db()->prepare(
        'SELECT filename
         FROM mcm_photos
         WHERE match_id = :id'
    );
    $stmt->execute(['id' => $matchId]);

    $paths = [];
    foreach ($stmt->fetchAll() as $row) {
        $name = valid_photo((string) $row['filename']);
        if ($name !== null) {
            $paths[] = __DIR__ . '/uploads/processed/' . $name;
        }
    }

    usort($paths, static function (string $a, string $b): int {
        return filemtime($b) <=> filemtime($a);
    });

    return $paths;
}

function active_match_id(array $user): ?int
{
    $rows = db()->query('SELECT id, kickoff_at, club, created_by FROM mcm_matches')->fetchAll();
    $now = time();
    $bestId = null;
    $bestDist = PHP_INT_MAX;

    foreach ($rows as $row) {
        if (!can_open_match($user, $row)) {
            continue;
        }
        $moment = kickoff_moment((string) $row['kickoff_at']);
        $window = kickoff_window((string) $row['kickoff_at']);
        if ($moment === null || $window === null || $now < $window[0] || $now > $window[1]) {
            continue;
        }
        $dist = abs($now - $moment->getTimestamp());
        if ($dist < $bestDist) {
            $bestDist = $dist;
            $bestId = (int) $row['id'];
        }
    }

    return $bestId;
}

function visible_photos(array $user): array
{
    $matches = db()->query('SELECT id, club, created_by FROM mcm_matches')->fetchAll();
    $open = [];
    foreach ($matches as $match) {
        if (can_open_match($user, $match)) {
            $open[(int) $match['id']] = true;
        }
    }
    if ($open === []) {
        return [];
    }

    $paths = [];
    foreach (db()->query('SELECT match_id, filename FROM mcm_photos')->fetchAll() as $row) {
        if (!isset($open[(int) $row['match_id']])) {
            continue;
        }
        $name = valid_photo((string) $row['filename']);
        if ($name !== null) {
            $paths[] = __DIR__ . '/uploads/processed/' . $name;
        }
    }

    usort($paths, static function (string $a, string $b): int {
        return filemtime($b) <=> filemtime($a);
    });

    return $paths;
}

function photo_in_list(array $images, ?string $photo): bool
{
    if ($photo === null) {
        return false;
    }

    foreach ($images as $img) {
        if (basename($img) === $photo) {
            return true;
        }
    }

    return false;
}

function receive_phone_photo(): array
{
    if (!isset($_FILES['photo'])) {
        return [];
    }

    $file = $_FILES['photo'];
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['error' => 'Bitte ein Foto wählen.'];
    }
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return ['error' => 'Upload hat nicht geklappt.'];
    }

    $info = @getimagesize((string) $file['tmp_name']);
    $name = 'PHONE_' . date('Ymd_His') . '.jpg';
    $target = __DIR__ . '/uploads/processed/' . $name;
    $saved = false;

    if (is_array($info) && $info[2] === IMAGETYPE_JPEG) {
        $saved = move_uploaded_file((string) $file['tmp_name'], $target);
    } elseif (is_array($info) && $info[2] === IMAGETYPE_PNG && function_exists('imagecreatefrompng')) {
        $image = imagecreatefrompng((string) $file['tmp_name']);
        if ($image !== false) {
            $saved = imagejpeg($image, $target, 90);
            imagedestroy($image);
        }
    }

    if (!$saved) {
        return ['error' => 'Nur JPG oder PNG.'];
    }

    return ['filename' => $name];
}

function render_match_gallery(array $images, string $pageUrl, string $uploadError): void
{
    echo '<h2>Foto wählen</h2>';
    echo '<p class="lead">Nur Bilder von diesem Spiel. Aufnahmen von 2 Stunden vor dem Anpfiff bis 4 Stunden danach erscheinen hier von selbst.</p>';

    if ($images === []) {
        echo '<p class="empty">Noch keine Bilder von diesem Spiel.</p>';
    } else {
        echo '<div class="grid">';
        foreach ($images as $img) {
            $filename = basename($img);
            $href = $pageUrl . '&photo=' . rawurlencode($filename);
            echo '<a class="item" href="' . h($href) . '">';
            echo '<img src="/uploads/processed/' . h(rawurlencode($filename)) . '" alt="" loading="lazy">';
            echo '</a>';
        }
        echo '</div>';
    }

    if ($uploadError !== '') {
        echo '<p class="form-error">' . h($uploadError) . '</p>';
    }

    echo '<form class="form" method="post" action="' . h($pageUrl) . '" enctype="multipart/form-data">';
    echo '<input type="hidden" name="csrf" value="' . h(csrf_token()) . '">';
    echo '<label for="photo">Oder mit dem Handy aufnehmen</label>';
    echo '<input id="photo" name="photo" type="file" accept="image/jpeg,image/png" capture="environment">';
    echo '<button class="btn" type="submit">Hochladen</button>';
    echo '</form>';
    echo <<<'HTML'
<script>
setTimeout(function () {
    var input = document.getElementById('photo');
    if (document.visibilityState === 'visible' && (!input || !input.value)) {
        window.location.reload();
    }
}, 15000);
</script>
HTML;
}

function format_kickoff(string $value): string
{
    $dt = kickoff_moment($value);
    if ($dt === null) {
        return $value;
    }

    $days = ['So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa'];
    $months = [1 => 'Jan', 'Feb', 'Mär', 'Apr', 'Mai', 'Jun', 'Jul', 'Aug', 'Sep', 'Okt', 'Nov', 'Dez'];

    return $days[(int) $dt->format('w')] . ' ' . $dt->format('j') . '. ' . $months[(int) $dt->format('n')] . ' · ' . $dt->format('H:i');
}
