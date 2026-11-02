<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/ui.php';

date_default_timezone_set('Europe/Zurich');
$user = require_login();

$id = (int) ($_GET['id'] ?? 0);
$stmt = db()->prepare(
    'SELECT id, title, kickoff_at, venue, club, created_by
     FROM mcm_matches
     WHERE id = :id
     LIMIT 1'
);
$stmt->execute(['id' => $id]);
$match = $stmt->fetch();

if ($match === false) {
    header('Location: /', true, 302);
    exit;
}

require_match_access($user, $match);

page_start((string) $match['title']);
?>
<p class="lead"><a href="/">Zurück zum Kalender</a></p>
<h1><?= h((string) $match['title']) ?></h1>
<p class="lead"><?= h(format_kickoff((string) $match['kickoff_at'])) ?></p>
<?php if (!empty($match['venue'])): ?>
    <p class="lead"><?= h((string) $match['venue']) ?></p>
<?php endif; ?>

<div class="stack">
    <a class="btn" href="/photos.php?match=<?= (int) $match['id'] ?>">Fotos</a>
    <a class="btn" href="/goal.php?id=<?= (int) $match['id'] ?>">Tor</a>
    <a class="btn" href="/lineup.php?id=<?= (int) $match['id'] ?>">Aufstellung</a>
    <a class="btn" href="/score.php?id=<?= (int) $match['id'] ?>&amp;kind=pause">Pause</a>
    <a class="btn" href="/score.php?id=<?= (int) $match['id'] ?>&amp;kind=final">Endstand</a>
</div>
<?php
page_end('calendar');
