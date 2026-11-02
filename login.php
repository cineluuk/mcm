<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/ui.php';
require_once __DIR__ . '/clubs.php';

mcm_session_start();

if (current_user() !== null) {
    header('Location: /', true, 302);
    exit;
}

$error = false;
$teams = swiss_teams();
$groups = [];
foreach ($teams as $team) {
    $groups[(string) $team['league']][] = $team;
}
$selectedClub = (string) ($_POST['club'] ?? '');
$selectedLogo = '';
foreach ($teams as $team) {
    if ((string) $team['name'] === $selectedClub) {
        $selectedLogo = (string) $team['logo'];
        break;
    }
}

$turnstileSite = (string) (mcm_config()['turnstile_site'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = (string) ($_POST['password'] ?? '');
    $token = (string) ($_POST['cf-turnstile-response'] ?? '');

    if (turnstile_ok($token) && attempt_login($password, $selectedClub)) {
        header('Location: /', true, 302);
        exit;
    }

    $error = true;
}
?>
<!doctype html>
<html lang="<?= h(['de' => 'de-CH', 'it' => 'it-CH', 'fr' => 'fr-CH', 'en' => 'en'][current_lang()]) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login · Matchday Content Manager</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&display=swap" rel="stylesheet">
    <meta name="theme-color" content="#111111">
    <link rel="stylesheet" href="/css/app.css?v=16">
    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
</head>
<body>
<div class="login-wrap">
    <?php topbar(false); ?>
    <div class="login-main">
    <div class="login-brand<?= $selectedClub !== '' ? ' is-paired' : '' ?>" id="login-brand">
        <img class="logo logo-hero" src="/assets/mcm-logo.png" alt="Matchday Content Manager">
        <span class="brand-rule" aria-hidden="true"></span>
        <img class="club-pick" id="club-logo" alt=""<?= $selectedClub !== '' ? '' : ' hidden' ?>>
    </div>
    <?php if ($error): ?>
        <p class="form-error">Verein, Passwort oder Sicherheitsprüfung stimmt nicht.</p>
    <?php endif; ?>

    <form class="login-form" method="post" action="/login.php" autocomplete="on">
        <label id="club-label" for="club-trigger">Verein</label>
        <div class="club-picker" id="club-picker">
            <input type="hidden" name="club" id="club" value="<?= h($selectedClub) ?>">
            <button class="club-trigger" id="club-trigger" type="button" aria-haspopup="listbox" aria-expanded="false" aria-labelledby="club-label">
                <img class="club-mini" id="club-trigger-logo" alt=""<?= $selectedLogo === '' ? ' hidden' : ' src="' . h($selectedLogo) . '"' ?>>
                <span id="club-trigger-label"><?= $selectedClub !== '' ? h($selectedClub) : 'Verein wählen' ?></span>
                <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path d="M6 9l6 6 6-6" fill="none" stroke="currentColor" stroke-width="2"></path></svg>
            </button>
            <div class="club-menu" id="club-menu" role="listbox" hidden>
                <?php foreach ($groups as $league => $leagueTeams): ?>
                    <div class="club-league"><?= h($league) ?></div>
                    <?php foreach ($leagueTeams as $team): ?>
                        <button class="club-option<?= $selectedClub === (string) $team['name'] ? ' is-selected' : '' ?>" type="button" role="option" data-name="<?= h((string) $team['name']) ?>" data-logo="<?= h((string) $team['logo']) ?>"<?= $selectedClub === (string) $team['name'] ? ' aria-selected="true"' : '' ?>>
                            <img src="<?= h((string) $team['logo']) ?>" alt="" width="22" height="22">
                            <span><?= h((string) $team['name']) ?></span>
                        </button>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </div>
        </div>

        <label for="password">Passwort</label>
        <input id="password" name="password" type="password" autocomplete="current-password" required>

        <div class="turnstile">
            <div class="cf-turnstile" data-sitekey="<?= h($turnstileSite) ?>" data-theme="dark"></div>
        </div>

        <button class="btn" type="submit">Anmelden</button>
    </form>
    </div>
</div>
<script>
(function () {
    var picker = document.getElementById('club-picker');
    var field = document.getElementById('club');
    var trigger = document.getElementById('club-trigger');
    var triggerLogo = document.getElementById('club-trigger-logo');
    var triggerLabel = document.getElementById('club-trigger-label');
    var menu = document.getElementById('club-menu');
    var brand = document.getElementById('login-brand');
    var logo = document.getElementById('club-logo');
    var form = document.querySelector('.login-form');
    if (!picker || !field || !trigger || !menu || !brand || !logo) {
        return;
    }

    function setOpen(open) {
        menu.hidden = !open;
        trigger.setAttribute('aria-expanded', open ? 'true' : 'false');
    }

    function choose(name, src) {
        field.value = name;
        triggerLabel.textContent = name;
        triggerLogo.hidden = false;
        triggerLogo.src = src;
        triggerLogo.alt = '';
        menu.querySelectorAll('.club-option').forEach(function (option) {
            var selected = option.getAttribute('data-name') === name;
            option.classList.toggle('is-selected', selected);
            if (selected) {
                option.setAttribute('aria-selected', 'true');
            } else {
                option.removeAttribute('aria-selected');
            }
        });
        logo.alt = name;
        logo.hidden = false;
        if (logo.getAttribute('src') !== src) {
            logo.src = src;
        }
        brand.classList.add('is-paired');
        setOpen(false);
    }

    trigger.addEventListener('click', function () {
        setOpen(menu.hidden);
    });

    menu.addEventListener('click', function (event) {
        var option = event.target.closest('.club-option');
        if (!option) {
            return;
        }
        choose(option.getAttribute('data-name'), option.getAttribute('data-logo'));
    });

    document.addEventListener('click', function (event) {
        if (!picker.contains(event.target)) {
            setOpen(false);
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            setOpen(false);
        }
    });

    if (form) {
        form.addEventListener('submit', function (event) {
            if (field.value !== '') {
                return;
            }
            event.preventDefault();
            setOpen(true);
        });
    }

    if (field.value && triggerLogo.getAttribute('src')) {
        logo.hidden = false;
        logo.alt = field.value;
        logo.src = triggerLogo.getAttribute('src');
        brand.classList.add('is-paired');
    }
})();
</script>
</body>
</html>
