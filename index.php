<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/ui.php';
require_once __DIR__ . '/clubs.php';

date_default_timezone_set('Europe/Zurich');

$user = require_login();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim((string) ($_POST['title'] ?? ''));
    $kickoffRaw = trim((string) ($_POST['kickoff'] ?? ''));
    $venue = trim((string) ($_POST['venue'] ?? ''));

    $tz = new DateTimeZone('Europe/Zurich');
    $kickoff = DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $kickoffRaw, $tz)
        ?: DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s', $kickoffRaw, $tz);

    if (!csrf_ok()) {
        $error = 'Bitte das Formular erneut senden.';
    } elseif (club_key($user['club']) === '') {
        $error = 'Zuerst im Profil den Verein eintragen.';
    } elseif ($title === '' || $kickoff === false) {
        $error = 'Titel und Anpfiff brauchen wir.';
    } else {
        $stmt = db()->prepare(
            'INSERT INTO mcm_matches (title, kickoff_at, venue, club, created_by)
             VALUES (:title, :kickoff, :venue, :club, :created_by)'
        );
        $stmt->execute([
            'title' => $title,
            'kickoff' => $kickoff->format('Y-m-d H:i:s'),
            'venue' => $venue === '' ? null : $venue,
            'club' => $user['club'],
            'created_by' => $user['id'],
        ]);

        header('Location: /match.php?id=' . db()->lastInsertId(), true, 302);
        exit;
    }
}

$matches = db()->query(
    'SELECT id, title, kickoff_at, venue, club, created_by
     FROM mcm_matches
     ORDER BY kickoff_at DESC'
)->fetchAll();

$defaultKickoff = (new DateTimeImmutable('now', new DateTimeZone('Europe/Zurich')))
    ->modify('+1 day')
    ->setTime(15, 0)
    ->format('Y-m-d\TH:i');

$calendar = [];
foreach ($matches as $match) {
    $moment = kickoff_moment((string) $match['kickoff_at']);
    $calendar[] = [
        'id' => (int) $match['id'],
        'title' => (string) $match['title'],
        'time' => $moment instanceof DateTimeImmutable ? $moment->format('H:i') : '',
        'day' => $moment instanceof DateTimeImmutable ? $moment->format('Y-m-d') : '',
        'venue' => (string) ($match['venue'] ?? ''),
        'club' => (string) ($match['club'] ?? ''),
        'open' => can_open_match($user, $match),
    ];
}
$clubLogo = club_logo_url((string) $user['club']) ?? swiss_team_logo((string) $user['club']);

page_start('Spielkalender');
?>
<div class="home">
    <div class="brand-lockup">
        <img class="logo" src="/assets/mcm-logo.png" alt="Matchday Content Manager">
        <span class="brand-rule" aria-hidden="true"></span>
        <a class="club-mark" href="/profile.php" aria-label="Profil">
            <?php if ($clubLogo !== null): ?>
                <img src="<?= h($clubLogo) ?>" alt="<?= h((string) $user['club']) ?>">
            <?php else: ?>
                <span><?= h(club_initials((string) $user['club'])) ?></span>
            <?php endif; ?>
        </a>
    </div>
    <h1>Spielkalender</h1>
    <div class="calendar" id="calendar"></div>

<?php if ($error !== ''): ?>
    <p class="form-error"><?= h($error) ?></p>
<?php endif; ?>
<form class="form" id="match-form" method="post" action="/"<?= $error === '' ? ' hidden' : '' ?>>
    <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
    <label for="title">Begegnung</label>
    <input id="title" name="title" type="text" required placeholder="FC Beispiel – Heim" maxlength="190" value="<?= h((string) ($_POST['title'] ?? '')) ?>">

    <label for="kickoff">Anpfiff</label>
    <input id="kickoff" name="kickoff" type="datetime-local" required value="<?= h((string) ($_POST['kickoff'] ?? $defaultKickoff)) ?>">

    <label for="venue">Ort</label>
    <input id="venue" name="venue" type="text" maxlength="190" placeholder="optional" value="<?= h((string) ($_POST['venue'] ?? '')) ?>">

    <button class="btn" type="submit">Spiel speichern</button>
</form>
<button class="add-match<?= $error !== '' ? ' is-open' : '' ?>" id="show-form" type="button" aria-label="Spiel eintragen" aria-expanded="<?= $error !== '' ? 'true' : 'false' ?>">
    <svg viewBox="0 0 24 24" width="32" height="32" aria-hidden="true"><path d="M12 5v14M5 12h14" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"></path></svg>
</button>
</div>
<script>
window.MCM_CALENDAR = <?= json_encode($calendar, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
</script>
<script src="/js/home.js?v=3"></script>
<script>
document.getElementById('show-form').addEventListener('click', function () {
    var form = document.getElementById('match-form');
    var open = form.hidden;
    form.hidden = !open;
    this.classList.toggle('is-open', open);
    this.setAttribute('aria-expanded', open ? 'true' : 'false');
    if (open) {
        document.getElementById('title').focus();
    }
});
</script>
<?php
page_end('calendar');
