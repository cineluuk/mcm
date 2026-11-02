<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/ui.php';

$kinds = [
    'pause' => ['title' => 'Pause', 'badge' => 'HALBZEIT', 'file' => 'halbzeit.jpg'],
    'final' => ['title' => 'Endstand', 'badge' => 'ENDSTAND', 'file' => 'endstand.jpg'],
];
$kind = (string) ($_GET['kind'] ?? '');
if (!isset($kinds[$kind])) {
    header('Location: /', true, 302);
    exit;
}
$graphic = $kinds[$kind];

$user = require_login();
$id = (int) ($_GET['id'] ?? 0);
$stmt = db()->prepare('SELECT id, title, kickoff_at, club, created_by FROM mcm_matches WHERE id = :id LIMIT 1');
$stmt->execute(['id' => $id]);
$match = $stmt->fetch();

if ($match === false) {
    header('Location: /', true, 302);
    exit;
}

require_match_access($user, $match);

$uploadError = '';
$pageUrl = '/score.php?id=' . $id . '&kind=' . rawurlencode($kind);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_ok()) {
        $uploadError = 'Bitte das Foto erneut senden.';
    } else {
        $received = receive_phone_photo();
        if (isset($received['filename'])) {
            attach_photo_to_match($id, $received['filename']);
            header('Location: ' . $pageUrl, true, 302);
            exit;
        }
        $uploadError = (string) ($received['error'] ?? 'Upload hat nicht geklappt.');
    }
}

$images = photos_for_match($id, (string) $match['kickoff_at']);
$photo = valid_photo((string) ($_GET['photo'] ?? ''));
if (!photo_in_list($images, $photo)) {
    $photo = null;
}

page_start($graphic['title']);
?>
<p class="lead"><a href="/match.php?id=<?= (int) $match['id'] ?>">Zurück zum Spiel</a></p>
<h1><?= h($graphic['title']) ?></h1>
<p class="lead"><?= h((string) $match['title']) ?></p>

<?php if ($photo === null): ?>
    <?php render_match_gallery($images, $pageUrl, $uploadError); ?>
<?php else: ?>
    <canvas id="preview" class="preview" width="1080" height="1350"></canvas>
    <p class="lead">Bild verschieben. Mit dem Regler zoomen.</p>
    <label for="zoom">Zoom</label>
    <input id="zoom" type="range" min="100" max="250" value="100">

    <div class="score-row">
        <div>
            <label for="home">Heim</label>
            <input id="home" type="text" inputmode="numeric" maxlength="2" value="0">
        </div>
        <span>:</span>
        <div>
            <label for="away">Gast</label>
            <input id="away" type="text" inputmode="numeric" maxlength="2" value="0">
        </div>
    </div>

    <button class="btn" id="save" type="button">In Fotos sichern</button>
    <p class="lead">Auf dem Handy öffnet sich Teilen. Auf dem iPhone „In Fotos sichern“, auf Android die Galerie.</p>
    <script>
        window.MCM_SCORE = {
            photo: <?= json_encode('/uploads/processed/' . rawurlencode($photo), JSON_UNESCAPED_SLASHES) ?>,
            match: <?= json_encode((string) $match['title'], JSON_UNESCAPED_UNICODE) ?>,
            badge: <?= json_encode($graphic['badge'], JSON_UNESCAPED_UNICODE) ?>,
            file: <?= json_encode($graphic['file'], JSON_UNESCAPED_UNICODE) ?>
        };
    </script>
    <script src="/js/save-graphic.js?v=2"></script>
    <script src="/js/score.js?v=1"></script>
<?php endif; ?>
<?php
page_end('calendar');
