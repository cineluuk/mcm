<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/ui.php';

$user = require_login();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) $user['role'] === 'admin') {
    $club = trim((string) ($_POST['club'] ?? ''));
    if (!csrf_ok()) {
        $error = 'Bitte das Formular erneut senden.';
    } elseif ($club === '' || mb_strlen($club) > 190) {
        $error = 'Der Vereinsname fehlt.';
    } else {
        $old = (string) $user['club'];
        $stmt = db()->prepare('UPDATE mcm_users SET club = :club WHERE id = :id');
        $stmt->execute([
            'club' => $club,
            'id' => $user['id'],
        ]);
        $matches = db()->prepare(
            'UPDATE mcm_matches
             SET club = :club
             WHERE created_by = :id
               AND (club IS NULL OR club = \'\' OR club = :old)'
        );
        $matches->execute([
            'club' => $club,
            'id' => $user['id'],
            'old' => $old,
        ]);
        move_club_logo($old, $club);
        $logoError = store_club_logo($club, $_FILES['logo'] ?? []);
        if ($logoError !== null) {
            $error = $logoError;
        } else {
            header('Location: /profile.php', true, 302);
            exit;
        }
    }
}

page_start('Profil');
?>
<div class="brand-row">
    <img class="logo" src="/assets/mcm-logo.png" alt="">
    <div>
        <h1>Profil</h1>
        <p class="lead"><?= h((string) $user['username']) ?> · <?= h((string) $user['role']) ?></p>
    </div>
</div>

<?php if ((string) $user['role'] === 'admin'): ?>
    <?php if ($error !== ''): ?>
        <p class="form-error"><?= h($error) ?></p>
    <?php endif; ?>
    <form class="form" method="post" action="/profile.php" enctype="multipart/form-data">
        <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
        <label for="club">Verein</label>
        <input id="club" name="club" type="text" maxlength="190" required value="<?= h((string) ($error !== '' ? ($_POST['club'] ?? '') : $user['club'])) ?>" placeholder="FC St. Gallen">
        <label for="logo">Vereinslogo</label>
        <input id="logo" name="logo" type="file" accept="image/jpeg,image/png,image/webp">
        <button class="btn" type="submit">Verein speichern</button>
    </form>
<?php else: ?>
    <p class="lead"><?= $user['club'] !== '' ? h($user['club']) : 'Noch keinem Verein zugeordnet.' ?></p>
<?php endif; ?>

<a class="btn" href="/logout.php">Logout</a>
<?php
page_end('profile');
