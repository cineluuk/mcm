<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/ui.php';
require_once __DIR__ . '/clubs.php';

$user = require_login();
$error = '';
$clubError = '';
$accountError = '';
$saved = isset($_GET['saved']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'delete') {
        if (!csrf_ok()) {
            $accountError = 'Bitte das Formular erneut senden.';
        } else {
            $id = (int) $user['id'];
            $clear = db()->prepare('UPDATE mcm_matches SET created_by = NULL WHERE created_by = :id');
            $clear->execute(['id' => $id]);
            $drop = db()->prepare('DELETE FROM mcm_users WHERE id = :id');
            $drop->execute(['id' => $id]);
            logout();
            header('Location: /login.php', true, 302);
            exit;
        }
    } elseif ($action === 'password') {
        $password = (string) ($_POST['password'] ?? '');
        $again = (string) ($_POST['password_again'] ?? '');
        if (!csrf_ok()) {
            $error = 'Bitte das Formular erneut senden.';
        } elseif ($password === '' || $again === '') {
            $error = 'Bitte das neue Passwort zweimal eingeben.';
        } elseif (strlen($password) < 8) {
            $error = 'Das neue Passwort braucht mindestens 8 Zeichen.';
        } elseif ($password !== $again) {
            $error = 'Die beiden Passwörter stimmen nicht überein.';
        } else {
            $stmt = db()->prepare('UPDATE mcm_users SET password_hash = :hash WHERE id = :id');
            $stmt->execute([
                'hash' => password_hash($password, PASSWORD_DEFAULT),
                'id' => $user['id'],
            ]);
            header('Location: /profile.php?saved=1', true, 302);
            exit;
        }
    } elseif ($action === 'club' && (string) $user['role'] === 'admin') {
        $club = trim((string) ($_POST['club'] ?? ''));
        if (!csrf_ok()) {
            $clubError = 'Bitte das Formular erneut senden.';
        } elseif ($club === '' || mb_strlen($club) > 190) {
            $clubError = 'Der Vereinsname fehlt.';
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
                $clubError = $logoError;
                $user['club'] = $club;
            } else {
                header('Location: /profile.php', true, 302);
                exit;
            }
        }
    }
}

$clubLogo = club_logo_url((string) $user['club']) ?? swiss_team_logo((string) $user['club']);
$lang = current_lang();

page_start('Profil');
?>
<div class="brand-lockup profile-logos">
    <img class="logo" src="/assets/mcm-logo.png" alt="Matchday Content Manager">
    <span class="brand-rule" aria-hidden="true"></span>
    <span class="club-mark">
        <?php if ($clubLogo !== null): ?>
            <img src="<?= h($clubLogo) ?>" alt="<?= h((string) $user['club']) ?>">
        <?php else: ?>
            <span><?= h(club_initials((string) $user['club'])) ?></span>
        <?php endif; ?>
    </span>
</div>

<h2>Passwort ändern</h2>
<?php if ($saved): ?>
    <p class="lead">Passwort gespeichert.</p>
<?php endif; ?>
<?php if ($error !== ''): ?>
    <p class="form-error"><?= h($error) ?></p>
<?php endif; ?>
<form class="form" method="post" action="/profile.php">
    <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
    <input type="hidden" name="action" value="password">
    <label for="password">Neues Passwort</label>
    <input id="password" name="password" type="password" autocomplete="new-password" required minlength="8">
    <label for="password_again">Neues Passwort wiederholen</label>
    <input id="password_again" name="password_again" type="password" autocomplete="new-password" required minlength="8">
    <button class="btn" type="submit">Speichern</button>
</form>

<h2>Sprache</h2>
<select class="lang-select" id="lang" name="lang" aria-label="Sprache" onchange="location.href='?lang=' + this.value">
    <?php foreach (['en' => 'EN', 'de' => 'DE', 'fr' => 'FR', 'it' => 'IT'] as $code => $label): ?>
        <option value="<?= h($code) ?>"<?= $lang === $code ? ' selected' : '' ?>><?= h($label) ?></option>
    <?php endforeach; ?>
</select>

<?php if ((string) $user['role'] === 'admin'): ?>
    <h2>Verein</h2>
    <?php if ($clubError !== ''): ?>
        <p class="form-error"><?= h($clubError) ?></p>
    <?php endif; ?>
    <form class="form" method="post" action="/profile.php" enctype="multipart/form-data">
        <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="action" value="club">
        <label for="club">Verein</label>
        <input id="club" name="club" type="text" maxlength="190" required value="<?= h((string) ($clubError !== '' ? ($_POST['club'] ?? '') : $user['club'])) ?>" placeholder="FC St. Gallen">
        <label for="logo">Vereinslogo</label>
        <input id="logo" name="logo" type="file" accept="image/jpeg,image/png,image/webp">
        <button class="btn" type="submit">Verein speichern</button>
    </form>
<?php endif; ?>

<?php if ($accountError !== ''): ?>
    <p class="form-error"><?= h($accountError) ?></p>
<?php endif; ?>
<a class="btn btn-danger" href="/logout.php">Logout</a>
<form method="post" action="/profile.php" onsubmit="return confirm('Account wirklich löschen?');">
    <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
    <input type="hidden" name="action" value="delete">
    <button class="btn btn-danger" type="submit">Account löschen</button>
</form>
<p class="help-line">Bei Problemen oder Fragen an <a href="mailto:contact@cineluuk.ch">contact@cineluuk.ch</a> schreiben.</p>
<?php
page_end('profile');
