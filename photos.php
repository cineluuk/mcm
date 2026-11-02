<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/ui.php';

$user = require_login();

$matchId = (int) ($_GET['match'] ?? $_POST['match'] ?? 0);
$match = null;
if ($matchId > 0) {
    $stmt = db()->prepare(
        'SELECT id, title, kickoff_at, club, created_by
         FROM mcm_matches
         WHERE id = :id
         LIMIT 1'
    );
    $stmt->execute(['id' => $matchId]);
    $match = $stmt->fetch();
    if ($match === false) {
        header('Location: /photos.php', true, 302);
        exit;
    }
    require_match_access($user, $match);
}

$error = '';
$redirect = $match === null ? '/photos.php' : '/photos.php?match=' . (int) $match['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['photo'])) {
    if (!csrf_ok()) {
        $error = 'Bitte das Foto erneut senden.';
    } else {
        $received = receive_phone_photo();
        if (isset($received['filename'])) {
            $targetMatch = $match === null ? active_match_id($user) : (int) $match['id'];
            if ($targetMatch !== null) {
                attach_photo_to_match($targetMatch, $received['filename']);
            }
            header('Location: ' . $redirect, true, 302);
            exit;
        }
        $error = (string) ($received['error'] ?? 'Upload hat nicht geklappt.');
    }
}

if ($match === null) {
    $activeId = active_match_id($user);
    if ($activeId !== null) {
        $active = db()->prepare('SELECT kickoff_at FROM mcm_matches WHERE id = :id LIMIT 1');
        $active->execute(['id' => $activeId]);
        $activeRow = $active->fetch();
        if ($activeRow !== false) {
            claim_photos_for_match($activeId, (string) $activeRow['kickoff_at']);
        }
    }
    $images = visible_photos($user);
} else {
    $images = photos_for_match((int) $match['id'], (string) $match['kickoff_at']);
}

page_start('Fotos');
?>
<?php if ($match !== null): ?>
    <p class="lead"><a href="/match.php?id=<?= (int) $match['id'] ?>">Zurück zum Spiel</a></p>
    <h1>Fotos</h1>
    <p class="lead"><?= h((string) $match['title']) ?></p>
<?php else: ?>
    <h1>Fotos</h1>
    <p class="lead">Neue Kamera-Bilder erscheinen hier automatisch. Oder direkt mit dem Handy aufnehmen.</p>
<?php endif; ?>

<?php if ($error !== ''): ?>
    <p class="form-error"><?= h($error) ?></p>
<?php endif; ?>

<form class="form" method="post" action="<?= h($redirect) ?>" enctype="multipart/form-data">
    <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
    <?php if ($match !== null): ?>
        <input type="hidden" name="match" value="<?= (int) $match['id'] ?>">
    <?php endif; ?>
    <label for="photo">Foto aufnehmen</label>
    <input id="photo" name="photo" type="file" accept="image/jpeg,image/png" capture="environment" required>
    <button class="btn" type="submit">Hochladen</button>
</form>

<?php if ($images === []): ?>
    <p class="empty"><?= $match === null ? 'Noch keine Bilder vorhanden.' : 'Noch keine Bilder von diesem Spiel.' ?></p>
<?php else: ?>
    <div class="grid" style="margin-top:18px">
        <?php foreach ($images as $img): ?>
            <?php $filename = basename($img); ?>
            <a class="item" href="/uploads/processed/<?= h(rawurlencode($filename)) ?>">
                <img src="/uploads/processed/<?= h(rawurlencode($filename)) ?>" alt="" loading="lazy">
            </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
<script>
setTimeout(function () {
    var input = document.getElementById('photo');
    if (document.visibilityState === 'visible' && (!input || !input.value)) {
        window.location.reload();
    }
}, 15000);
</script>
<?php
page_end('photos');
