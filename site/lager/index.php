<?php
/*
 * Lagersystem – Teater Scenario (/lager)
 * Erstatter det gamle WordPress-plugin. Kræver kun PHP – ingen database.
 */
if (version_compare(PHP_VERSION, '7.4.0', '<')) {
    http_response_code(500);
    exit('Lageret kræver PHP 7.4 eller nyere. Serveren kører PHP ' . PHP_VERSION . '.');
}
require dirname(__DIR__) . '/inc/lager.php';

header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex');
header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; font-src 'self'; script-src 'self'; connect-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");

sc_session_start('scenario_lager');

/* =========================================================
   Hjælpere
========================================================= */

function lg_flash(string $type, string $text): void
{
    $_SESSION['lg_flash'][] = [$type, $text];
}

function lg_go(array $q = []): void
{
    header('Location: ./' . ($q ? '?' . http_build_query($q) : ''));
    exit;
}

function lg_url(array $q = []): string
{
    return './' . ($q ? '?' . http_build_query($q) : '');
}

function lg_csrf(): string
{
    return '<input type="hidden" name="csrf" value="' . e($_SESSION['csrf']) . '">';
}

/** Det, man skrev i en formular, der fejlede (så man ikke skal skrive det igen). */
function lg_old(string $key, string $default = ''): string
{
    global $LG_OLD;
    return isset($LG_OLD[$key]) && is_string($LG_OLD[$key]) ? $LG_OLD[$key] : $default;
}

function lg_ids($v): array
{
    $ids = is_array($v) ? $v : explode(',', (string)$v);
    return array_values(array_unique(array_filter(array_map('intval', $ids), function ($i) { return $i > 0; })));
}

function lg_status(?array $res): array
{
    if (!$res) return ['ledig', 'Tilgængelig', 'ok'];
    return lg_overdue($res) ? ['overskredet', 'Overskredet', 'danger'] : ['reserveret', 'Reserveret', 'warn'];
}

/* =========================================================
   Handlinger (POST)
========================================================= */

$me = lg_current_user();
$LG_OLD = $_SESSION['lg_old'] ?? [];
unset($_SESSION['lg_old']);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $a = (string)($_POST['handling'] ?? '');
    $back = ['side' => (string)($_POST['side'] ?? 'inventar')];
    if (!hash_equals($_SESSION['csrf'] ?? '', (string)($_POST['csrf'] ?? ''))) {
        lg_flash('fejl', 'Siden var for gammel. Prøv igen.');
        lg_go();
    }
    try {
        // --- Uden login ---
        if ($a === 'opsaetning') {
            sc_locked(function () {
                if (lg_users()) throw new ScApiError('Der er allerede oprettet brugere. Log ind i stedet.');
                $navn = sc_validate_name($_POST['navn'] ?? '');
                $bn = sc_validate_username($_POST['brugernavn'] ?? '');
                $pw = sc_validate_password($_POST['kodeord'] ?? '');
                if ($pw !== (string)($_POST['gentag'] ?? '')) throw new ScApiError('De to kodeord er ikke ens.');
                lg_users_save([[
                    'brugernavn' => $bn, 'navn' => $navn, 'rolle' => 'admin',
                    'hash' => password_hash($pw, PASSWORD_DEFAULT), 'oprettet' => date('c'), 'sidstLoggetInd' => date('c'),
                ]]);
                session_regenerate_id(true);
                $_SESSION['lg_bruger'] = $bn;
                $_SESSION['csrf'] = bin2hex(random_bytes(32));
            });
            lg_flash('ok', 'Velkommen! Du er administrator. Tilføj flere personer under “Brugere”.');
            lg_go();
        }
        if ($a === 'login') {
            $min = sc_login_blocked();
            if ($min > 0) throw new ScApiError("For mange forkerte forsøg. Vent $min minutter og prøv igen.");
            $users = lg_users();
            $i = sc_user_find($users, trim((string)($_POST['brugernavn'] ?? '')));
            $pw = (string)($_POST['kodeord'] ?? '');
            if ($i === null || !password_verify($pw, $users[$i]['hash'])) {
                sc_login_register(false);
                usleep(400000);
                throw new ScApiError('Forkert brugernavn eller kodeord.');
            }
            sc_login_register(true);
            $bn = $users[$i]['brugernavn'];
            sc_locked(function () use ($bn, $pw) {
                $users = lg_users();
                $i = sc_user_find($users, $bn);
                if ($i === null) return;
                if (password_needs_rehash($users[$i]['hash'], PASSWORD_DEFAULT)) $users[$i]['hash'] = password_hash($pw, PASSWORD_DEFAULT);
                $users[$i]['sidstLoggetInd'] = date('c');
                lg_users_save($users);
            });
            session_regenerate_id(true);
            $_SESSION['lg_bruger'] = $bn;
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
            lg_go();
        }
        if ($a === 'logout') {
            $_SESSION = [];
            session_regenerate_id(true);
            lg_go();
        }

        // --- Kræver login ---
        if (!$me) {
            lg_flash('fejl', 'Du er blevet logget ud. Log ind igen.');
            lg_go();
        }
        $isAdmin = $me['rolle'] === 'admin';
        $now = date('c');

        switch ($a) {
            case 'reserver': {
                $ids = lg_ids($_POST['ids'] ?? []);
                $back = ['side' => 'reserver', 'ids' => implode(',', $ids)];
                $fra = lg_clean_dt($_POST['fra'] ?? '');
                $til = lg_clean_dt($_POST['til'] ?? '');
                $note = sc_text($_POST['note'] ?? '', 2000);
                if (!$ids) throw new ScApiError('Vælg mindst én enhed.');
                if ($fra === '' || $til === '') throw new ScApiError('Udfyld både fra og til.');
                if (strtotime($til) <= strtotime($fra)) throw new ScApiError('“Til” skal ligge efter “Fra”.');
                if (count($ids) > 1 && $note === '') throw new ScApiError('Skriv kort, hvad enhederne skal bruges til.');
                $done = lg_change(function (array &$d) use ($ids, $fra, $til, $note, $me, $now) {
                    $active = lg_active($d);
                    $n = 0;
                    foreach ($ids as $id) {
                        if (lg_item_index($d, $id) === null || isset($active[$id])) continue;
                        $d['reservationer'][] = [
                            'id' => $d['naesteResId']++, 'enhed' => $id, 'bruger' => $me['brugernavn'], 'navn' => $me['navn'],
                            'fra' => $fra, 'til' => $til, 'note' => $note, 'status' => 'aktiv', 'oprettet' => $now, 'returneret' => '',
                        ];
                        $n++;
                    }
                    return $n;
                });
                if (!$done) throw new ScApiError('Enhederne er allerede reserveret af en anden.');
                lg_flash('ok', $done === 1 ? 'Enheden er reserveret.' : "$done enheder er reserveret.");
                lg_go(count($ids) === 1 ? ['side' => 'info', 'id' => $ids[0]] : []);
            }

            case 'returner': {
                $ids = lg_ids($_POST['ids'] ?? []);
                $done = lg_change(function (array &$d) use ($ids, $now) {
                    $n = 0;
                    foreach ($d['reservationer'] as &$r) {
                        if ($r['status'] === 'aktiv' && in_array((int)$r['enhed'], $ids, true)) {
                            $r['status'] = 'returneret';
                            $r['returneret'] = $now;
                            $n++;
                        }
                    }
                    unset($r);
                    return $n;
                });
                lg_flash('ok', $done === 1 ? 'Enheden er returneret.' : ($done ? "$done enheder er returneret." : 'Der var ikke noget at returnere.'));
                lg_go(($_POST['tilbage'] ?? '') === 'info' && count($ids) === 1 ? ['side' => 'info', 'id' => $ids[0]] : []);
            }

            case 'ny-enhed': {
                $back = ['side' => 'ny'];
                $navn = sc_line($_POST['navn'] ?? '', 200);
                $nr = sc_line($_POST['nummer'] ?? '', 40);
                $sek = strtoupper(sc_line($_POST['sektion'] ?? '', 30));
                if ($nr === '') throw new ScApiError('Skriv et ID-nummer.');
                if ($navn === '') throw new ScApiError('Skriv et navn.');
                $sku = lg_change(function (array &$d) use ($navn, $nr, $sek, $now) {
                    if ($sek !== '' && !in_array($sek, $d['sektioner'], true)) throw new ScApiError('Sektionen findes ikke.');
                    $sku = $sek !== '' ? $sek . '-' . $nr : $nr;
                    if (lg_sku_taken($d, $sku)) throw new ScApiError("ID'et $sku findes allerede. Vælg et andet nummer.");
                    $d['enheder'][] = [
                        'id' => $d['naesteId']++, 'sku' => $sku, 'navn' => $navn,
                        'placering' => sc_line($_POST['placering'] ?? '', 200),
                        'beskrivelse' => sc_text($_POST['beskrivelse'] ?? '', 4000),
                        'oprettet' => $now, 'opdateret' => $now,
                    ];
                    return $sku;
                });
                lg_flash('ok', "Enhed tilføjet: $sku – $navn");
                lg_go();
            }

            case 'rediger': {
                $id = (int)($_POST['id'] ?? 0);
                $back = ['side' => 'rediger', 'id' => $id];
                $sku = sc_line($_POST['sku'] ?? '', 60);
                $navn = sc_line($_POST['navn'] ?? '', 200);
                if ($sku === '' || $navn === '') throw new ScApiError('ID og navn skal udfyldes.');
                lg_change(function (array &$d) use ($id, $sku, $navn, $now) {
                    $i = lg_item_index($d, $id);
                    if ($i === null) throw new ScApiError('Enheden findes ikke længere.');
                    if (lg_sku_taken($d, $sku, $id)) throw new ScApiError("ID'et $sku bruges allerede af en anden enhed.");
                    $d['enheder'][$i] = array_merge($d['enheder'][$i], [
                        'sku' => $sku, 'navn' => $navn,
                        'placering' => sc_line($_POST['placering'] ?? '', 200),
                        'beskrivelse' => sc_text($_POST['beskrivelse'] ?? '', 4000),
                        'opdateret' => $now,
                    ]);
                });
                lg_flash('ok', 'Ændringerne er gemt.');
                lg_go(['side' => 'info', 'id' => $id]);
            }

            case 'slet': {
                $id = (int)($_POST['id'] ?? 0);
                $navn = lg_change(function (array &$d) use ($id) {
                    $i = lg_item_index($d, $id);
                    if ($i === null) throw new ScApiError('Enheden findes ikke længere.');
                    $navn = $d['enheder'][$i]['navn'];
                    array_splice($d['enheder'], $i, 1);
                    $d['reservationer'] = array_values(array_filter($d['reservationer'], function ($r) use ($id) { return (int)$r['enhed'] !== $id; }));
                    return $navn;
                });
                lg_flash('ok', "“{$navn}” er slettet.");
                lg_go();
            }

            case 'sektion-ny': {
                $back = ['side' => 'indstillinger'];
                $raw = sc_line($_POST['navn'] ?? '', 40);
                $navn = function_exists('mb_strtoupper') ? mb_strtoupper($raw) : strtoupper($raw);
                if (!preg_match('~^[\p{L}\p{N} ]{1,30}$~u', $navn)) throw new ScApiError('Sektionens navn må kun indeholde bogstaver, tal og mellemrum (højst 30 tegn).');
                lg_change(function (array &$d) use ($navn) {
                    if (in_array($navn, $d['sektioner'], true)) throw new ScApiError("Sektionen $navn findes allerede.");
                    $d['sektioner'][] = $navn;
                });
                lg_flash('ok', "Sektionen $navn er tilføjet.");
                lg_go($back);
            }

            case 'sektion-slet': {
                $navn = (string)($_POST['navn'] ?? '');
                lg_change(function (array &$d) use ($navn) {
                    $d['sektioner'] = array_values(array_filter($d['sektioner'], function ($s) use ($navn) { return $s !== $navn; }));
                });
                lg_flash('ok', "Sektionen $navn er fjernet. Enhederne er ikke slettet.");
                lg_go(['side' => 'indstillinger']);
            }

            case 'import': {
                $back = ['side' => 'indstillinger'];
                if (empty($_FILES['fil'])) throw new ScApiError('Vælg en fil først.');
                $rows = lg_read_rows($_FILES['fil']);
                [$added, $skipped] = lg_change(function (array &$d) use ($rows) { return lg_import_rows($d, $rows); });
                lg_flash($added ? 'ok' : 'fejl', "Importerede $added enheder." . ($skipped ? " $skipped rækker blev sprunget over (manglende eller allerede brugt ID)." : ''));
                lg_go($added ? [] : $back);
            }

            case 'eksport':
                lg_export_csv(lg_data());
                exit;

            case 'min-konto': {
                $back = ['side' => 'indstillinger'];
                sc_locked(function () use ($me) {
                    $users = lg_users();
                    $i = sc_user_find($users, $me['brugernavn']);
                    if ($i === null) throw new ScApiError('Brugeren findes ikke.');
                    if (isset($_POST['navn'])) $users[$i]['navn'] = sc_validate_name($_POST['navn']);
                    if (!empty($_POST['nytKodeord'])) {
                        if (!password_verify((string)($_POST['kodeord'] ?? ''), $users[$i]['hash'])) throw new ScApiError('Dit nuværende kodeord er forkert.');
                        if ($_POST['nytKodeord'] !== ($_POST['gentag'] ?? '')) throw new ScApiError('De to nye kodeord er ikke ens.');
                        $users[$i]['hash'] = password_hash(sc_validate_password($_POST['nytKodeord']), PASSWORD_DEFAULT);
                    }
                    lg_users_save($users);
                });
                lg_flash('ok', !empty($_POST['nytKodeord']) ? 'Dit kodeord er skiftet.' : 'Dit navn er gemt.');
                lg_go($back);
            }
        }

        // --- Kun administratorer ---
        if (!$isAdmin) throw new ScApiError('Kun administratorer kan gøre det her.');
        $back = ['side' => 'brugere'];
        switch ($a) {
            case 'bruger-ny': {
                $new = sc_locked(function () {
                    $users = lg_users();
                    $bn = sc_validate_username($_POST['brugernavn'] ?? '');
                    if (sc_user_find($users, $bn) !== null) throw new ScApiError('Brugernavnet er allerede i brug.');
                    $u = [
                        'brugernavn' => $bn, 'navn' => sc_validate_name($_POST['navn'] ?? ''),
                        'rolle' => ($_POST['rolle'] ?? '') === 'admin' ? 'admin' : 'bruger',
                        'hash' => password_hash(sc_validate_password($_POST['kodeord'] ?? ''), PASSWORD_DEFAULT),
                        'oprettet' => date('c'), 'sidstLoggetInd' => '',
                    ];
                    $users[] = $u;
                    lg_users_save($users);
                    return $u;
                });
                $_SESSION['lg_creds'] = ['navn' => $new['navn'], 'brugernavn' => $new['brugernavn'], 'kodeord' => (string)$_POST['kodeord']];
                lg_go($back);
            }
            case 'bruger-kodeord': {
                $pw = lg_make_password();
                $u = sc_locked(function () use ($pw) {
                    $users = lg_users();
                    $i = sc_user_find($users, (string)($_POST['brugernavn'] ?? ''));
                    if ($i === null) throw new ScApiError('Brugeren findes ikke.');
                    $users[$i]['hash'] = password_hash($pw, PASSWORD_DEFAULT);
                    lg_users_save($users);
                    return $users[$i];
                });
                $_SESSION['lg_creds'] = ['navn' => $u['navn'], 'brugernavn' => $u['brugernavn'], 'kodeord' => $pw];
                lg_go($back);
            }
            case 'bruger-rolle': {
                $u = sc_locked(function () use ($me) {
                    $users = lg_users();
                    $i = sc_user_find($users, (string)($_POST['brugernavn'] ?? ''));
                    if ($i === null) throw new ScApiError('Brugeren findes ikke.');
                    if (strcasecmp($users[$i]['brugernavn'], $me['brugernavn']) === 0) throw new ScApiError('Du kan ikke ændre din egen rolle.');
                    $users[$i]['rolle'] = ($_POST['rolle'] ?? '') === 'admin' ? 'admin' : 'bruger';
                    lg_users_save($users);
                    return $users[$i];
                });
                lg_flash('ok', $u['navn'] . ' er nu ' . ($u['rolle'] === 'admin' ? 'administrator.' : 'bruger.'));
                lg_go($back);
            }
            case 'bruger-slet': {
                $navn = sc_locked(function () use ($me) {
                    $users = lg_users();
                    $i = sc_user_find($users, (string)($_POST['brugernavn'] ?? ''));
                    if ($i === null) throw new ScApiError('Brugeren findes ikke.');
                    if (strcasecmp($users[$i]['brugernavn'], $me['brugernavn']) === 0) throw new ScApiError('Du kan ikke slette dig selv.');
                    $navn = $users[$i]['navn'];
                    array_splice($users, $i, 1);
                    lg_users_save($users);
                    return $navn;
                });
                lg_flash('ok', "$navn er slettet og kan ikke længere logge ind.");
                lg_go($back);
            }
        }
        throw new ScApiError('Ukendt handling.');
    } catch (ScApiError $ex) {
        lg_flash('fejl', $ex->getMessage());
        $_SESSION['lg_old'] = array_diff_key($_POST, ['csrf' => 1, 'kodeord' => 1, 'gentag' => 1, 'nytKodeord' => 1]);
        lg_go($back);
    } catch (Throwable $ex) {
        error_log('[Scenario lager] ' . $ex->getMessage() . ' i ' . $ex->getFile() . ':' . $ex->getLine());
        lg_flash('fejl', $ex instanceof RuntimeException ? $ex->getMessage() : 'Der skete en uventet fejl. Prøv igen.');
        lg_go($back);
    }
}

/* =========================================================
   Sider (GET) – samme opbygning som det oprindelige plugin
========================================================= */

$side = (string)($_GET['side'] ?? 'inventar');

if (!lg_users()) {
    lg_page_login(true);
    exit;
}
if (!$me) {
    lg_page_login(false);
    exit;
}

$d = lg_data();
switch ($side) {
    case 'info': lg_page_info($d, (int)($_GET['id'] ?? 0)); break;
    case 'ny': lg_page_add($d); break;
    case 'rediger': lg_page_edit($d, (int)($_GET['id'] ?? 0)); break;
    case 'reserver': lg_page_reserve($d, lg_ids($_GET['ids'] ?? '')); break;
    case 'historik': lg_page_history($d); break;
    case 'indstillinger':
    case 'konto': lg_page_settings($d); break;
    case 'brugere':
        if ($me['rolle'] === 'admin') { lg_page_users(); break; }
        // ellers: vis inventaret
    default: lg_page_inventory($d);
}
exit;

/* =========================================================
   Fælles
========================================================= */

function lg_dmy(string $v): string
{
    $t = strtotime($v);
    return $v === '' || $t === false ? '-' : date('d/m/Y H:i', $t);
}

/** Skjulte felter, så søgning, filtre og sortering bliver husket mellem formularerne. */
function lg_hidden(array $q): string
{
    $h = '';
    foreach ($q as $k => $v) {
        foreach ((array)$v as $x) {
            $h .= '<input type="hidden" name="' . e(is_array($v) ? $k . '[]' : $k) . '" value="' . e($x) . '">';
        }
    }
    return $h;
}

function lg_html_start(string $title): void
{
    $v = function (string $f): string {
        $p = __DIR__ . '/' . $f;
        return $f . (is_file($p) ? '?v=' . filemtime($p) : '');
    };
    ?>
<!doctype html>
<html lang="da">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <title><?= e($title) ?> – Scenario Lager</title>
  <link rel="stylesheet" href="<?= e($v('lager.css')) ?>">
  <script src="<?= e($v('lager.js')) ?>" defer></script>
</head>
<body>
<?php
}

function lg_alerts(): void
{
    $msgs = $_SESSION['lg_flash'] ?? [];
    unset($_SESSION['lg_flash']);
    if (!$msgs) return;
    echo '<div class="sl-alerts">';
    foreach ($msgs as [$type, $text]) {
        echo '<div class="sl-alert sl-alert-' . ($type === 'ok' ? 'success' : 'danger') . '">' . e($text) . '</div>';
    }
    echo '</div>';
}

function lg_start(string $title, string $active): void
{
    global $me;
    lg_html_start($title);
    $nav = [['inventar', 'Inventar'], ['historik', 'Historik'], ['indstillinger', 'Indstillinger']];
    if ($me['rolle'] === 'admin') $nav[] = ['brugere', 'Brugere'];
    ?>
<div class="scenario-lager-frontend">
  <nav class="sl-navbar">
    <div class="sl-nav-container">
      <div class="sl-nav-brand">Scenari<span class="red-o">o</span> Lager</div>
      <ul class="sl-nav-menu">
        <?php foreach ($nav as [$id, $label]): ?>
        <li><a href="<?= e(lg_url($id === 'inventar' ? [] : ['side' => $id])) ?>" class="<?= $active === $id ? 'active' : '' ?>"><?= e($label) ?></a></li>
        <?php endforeach; ?>
        <li><form method="post"><?= lg_csrf() ?><input type="hidden" name="handling" value="logout"><button type="submit" class="sl-link logout">Log ud</button></form></li>
      </ul>
    </div>
  </nav>
  <?php lg_alerts(); ?>
  <div class="sl-container">
<?php
}

function lg_end(): void
{
    global $me;
    ?>
  </div>
  <div class="sl-footer"><p>Scenario Lager · logget ind som <?= e($me['navn']) ?></p></div>
</div>
</body>
</html>
<?php
}

function lg_badge(?array $res): string
{
    if (!$res) return '<span class="sl-badge sl-badge-success">Tilgængelig</span>';
    return lg_overdue($res) ? '<span class="sl-badge sl-badge-danger">Overskredet</span>' : '<span class="sl-badge sl-badge-warning">Reserveret</span>';
}

function lg_not_found(): void
{
    lg_start('Ikke fundet', 'inventar');
    echo '<div class="sl-alert sl-alert-danger">Enhed ikke fundet</div><p><a class="sl-btn" href="./">Tilbage til inventar</a></p>';
    lg_end();
}

/* =========================================================
   Login
========================================================= */

function lg_page_login(bool $setup): void
{
    lg_html_start($setup ? 'Opsætning' : 'Log ind');
    ?>
<div class="scenario-lager-frontend">
  <div class="sl-login-container">
    <div class="sl-login-box">
      <h1>Scenari<span class="red-o">o</span> Lager</h1>
      <?php lg_alerts(); ?>
      <?php if ($setup): ?><p class="sl-muted">Opret den første administrator. Bagefter kan du tilføje flere personer under “Brugere”.</p><?php endif; ?>
      <form method="post">
        <?= lg_csrf() ?>
        <input type="hidden" name="handling" value="<?= $setup ? 'opsaetning' : 'login' ?>">
        <?php if ($setup): ?>
        <div class="sl-form-group"><label for="f-navn">Dit navn</label><input type="text" id="f-navn" name="navn" required autocomplete="name" value="<?= e(lg_old('navn')) ?>"></div>
        <?php endif; ?>
        <div class="sl-form-group"><label for="f-bn">Brugernavn</label><input type="text" id="f-bn" name="brugernavn" required autocomplete="username" autocapitalize="none" value="<?= e(lg_old('brugernavn')) ?>"></div>
        <div class="sl-form-group"><label for="f-pw">Adgangskode</label><input type="password" id="f-pw" name="kodeord" required autocomplete="<?= $setup ? 'new-password' : 'current-password' ?>"></div>
        <?php if ($setup): ?>
        <div class="sl-form-group"><label for="f-pw2">Gentag adgangskode</label><input type="password" id="f-pw2" name="gentag" required autocomplete="new-password"></div>
        <?php endif; ?>
        <button type="submit" class="sl-btn sl-btn-primary sl-btn-full"><?= $setup ? 'Opret og log ind' : 'Log ind' ?></button>
      </form>
    </div>
  </div>
</div>
</body>
</html>
<?php
}

/* =========================================================
   Inventar
========================================================= */

function lg_page_inventory(array $d): void
{
    $search = sc_line($_GET['search'] ?? '', 200);
    $status = array_values(array_intersect((array)($_GET['status'] ?? []), ['available', 'reserved']));
    $secs = array_values(array_intersect((array)($_GET['sections'] ?? []), $d['sektioner']));
    $sort = in_array($_GET['sort'] ?? '', ['id', 'name', 'location'], true) ? $_GET['sort'] : 'id';
    $dir = strtolower((string)($_GET['dir'] ?? '')) === 'desc' ? 'desc' : 'asc';
    $active = lg_active($d);

    // Søg (flere ord adskilt af komma), filtrér og sortér
    $terms = array_filter(array_map('trim', explode(',', sc_lower($search))), 'strlen');
    $items = array_filter($d['enheder'], function ($e) use ($terms, $status, $secs, $active) {
        $reserved = isset($active[(int)$e['id']]);
        if (count($status) === 1 && ($status[0] === 'available') === $reserved) return false;
        if ($secs) {
            $ok = false;
            foreach ($secs as $s) if (stripos($e['sku'], $s . '-') === 0) $ok = true;
            if (!$ok) return false;
        }
        if ($terms) {
            $hay = sc_lower($e['navn'] . ' ' . $e['sku'] . ' ' . $e['placering']);
            $ok = false;
            foreach ($terms as $t) if (strpos($hay, $t) !== false) $ok = true;
            if (!$ok) return false;
        }
        return true;
    });
    $field = ['id' => 'sku', 'name' => 'navn', 'location' => 'placering'][$sort];
    usort($items, function ($a, $b) use ($field, $dir) {
        $c = strnatcasecmp($a[$field], $b[$field]) ?: strnatcasecmp($a['sku'], $b['sku']);
        return $dir === 'desc' ? -$c : $c;
    });

    $state = array_filter(['search' => $search, 'status' => $status, 'sections' => $secs, 'sort' => $sort !== 'id' ? $sort : '', 'dir' => $dir !== 'asc' ? $dir : '']);
    $th = function (string $label, string $f) use ($state, $sort, $dir) {
        $on = $sort === $f;
        $q = array_merge($state, ['sort' => $f, 'dir' => $on && $dir === 'asc' ? 'desc' : 'asc']);
        return '<th><a href="' . e(lg_url($q)) . '" class="sl-sort-link' . ($on ? ' active' : '') . '">' . e($label) . ($on ? ($dir === 'asc' ? ' ▲' : ' ▼') : '') . '</a></th>';
    };

    lg_start('Inventar', 'inventar');
    ?>
<div class="sl-page-header">
  <h1>Inventar</h1>
  <div>
    <button type="button" id="sl-select-all-btn" class="sl-btn" hidden>Vælg alle</button>
    <button type="button" id="sl-reserve-btn" class="sl-btn sl-btn-warning" hidden>Reserver valgte</button>
    <button type="button" id="sl-return-btn" class="sl-btn sl-btn-success" hidden>Returner valgte</button>
    <a href="<?= e(lg_url(['side' => 'ny'])) ?>" class="sl-btn sl-btn-primary">Tilføj Enhed</a>
  </div>
</div>

<div class="sl-search-box">
  <form method="get">
    <?= lg_hidden(array_diff_key($state, ['search' => 1])) ?>
    <input type="text" name="search" value="<?= e($search) ?>" placeholder="Søg efter navn, ID eller placering...">
    <button type="submit" class="sl-btn sl-btn-primary">Søg</button>
    <?php if ($search !== '' || $status || $secs): ?><a href="./" class="sl-btn">Ryd alle</a><?php endif; ?>
  </form>
</div>

<div class="sl-layout">
  <div class="sl-filter-sidebar">
    <h2 class="sl-filter-title">Filtre</h2>
    <form method="get" data-autosubmit>
      <?= lg_hidden(array_intersect_key($state, ['search' => 1, 'sort' => 1, 'dir' => 1])) ?>
      <div class="sl-filter-section">
        <h3>Status</h3>
        <label class="sl-checkbox-label"><input type="checkbox" name="status[]" value="available"<?= in_array('available', $status, true) ? ' checked' : '' ?>><span>Tilgængelig</span></label>
        <label class="sl-checkbox-label"><input type="checkbox" name="status[]" value="reserved"<?= in_array('reserved', $status, true) ? ' checked' : '' ?>><span>Reserveret</span></label>
      </div>
      <?php if ($d['sektioner']): ?>
      <div class="sl-filter-section">
        <h3>Sektioner</h3>
        <?php foreach ($d['sektioner'] as $s): ?>
        <label class="sl-checkbox-label"><input type="checkbox" name="sections[]" value="<?= e($s) ?>"<?= in_array($s, $secs, true) ? ' checked' : '' ?>><span><?= e($s) ?></span></label>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
      <noscript><button type="submit" class="sl-btn sl-btn-sm">Filtrér</button></noscript>
    </form>
  </div>

  <div class="sl-layout-main">
    <div class="sl-section">
      <div class="sl-table-wrap">
      <table class="sl-table">
        <thead>
          <tr>
            <th class="sl-col-check"></th>
            <?= $th('ID', 'id') ?><?= $th('Navn', 'name') ?><?= $th('Placering', 'location') ?>
            <th>Status</th>
            <th>Handlinger</th>
          </tr>
        </thead>
        <tbody>
        <?php if (!$items): ?>
          <tr><td colspan="6"><?= $d['enheder'] ? 'Ingen Enheder fundet.' : 'Lageret er tomt. Tryk “Tilføj Enhed”, eller importér fra Excel under Indstillinger.' ?></td></tr>
        <?php endif; ?>
        <?php foreach ($items as $e): $r = $active[(int)$e['id']] ?? null; ?>
          <tr class="sl-table-row<?= $r ? ' item-in-use' : '' ?>">
            <td><input type="checkbox" class="sl-item-checkbox" value="<?= (int)$e['id'] ?>" data-available="<?= $r ? '0' : '1' ?>" aria-label="Vælg <?= e($e['navn']) ?>"></td>
            <td><?= e($e['sku']) ?></td>
            <td><strong><a class="sl-name-link" href="<?= e(lg_url(['side' => 'info', 'id' => $e['id']])) ?>"><?= e($e['navn']) ?></a></strong></td>
            <td><?= $e['placering'] !== '' ? e($e['placering']) : '-' ?></td>
            <td><?= lg_badge($r) ?>
              <?php if ($r): ?><div class="sl-small sl-muted"><?= e($r['navn']) ?><br>Til: <?= e(lg_dmy($r['til'])) ?></div><?php endif; ?></td>
            <td>
              <a href="<?= e(lg_url(['side' => 'info', 'id' => $e['id']])) ?>" class="sl-btn sl-btn-sm">Info</a>
              <a href="<?= e(lg_url(['side' => 'rediger', 'id' => $e['id']])) ?>" class="sl-btn sl-btn-primary sl-btn-sm">Rediger</a>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      </div>
    </div>
  </div>
</div>
<form method="post" id="sl-return-form" hidden><?= lg_csrf() ?><input type="hidden" name="handling" value="returner"></form>
<?php
    lg_end();
}

/* =========================================================
   Enhed: info, tilføj, rediger
========================================================= */

function lg_page_info(array $d, int $id): void
{
    $i = lg_item_index($d, $id);
    if ($i === null) { lg_not_found(); return; }
    $e = $d['enheder'][$i];
    $cur = lg_active($d)[$id] ?? null;
    $hist = array_values(array_filter($d['reservationer'], function ($r) use ($id) { return (int)$r['enhed'] === $id; }));
    usort($hist, function ($a, $b) { return strcmp($b['oprettet'], $a['oprettet']); });

    lg_start($e['navn'], 'inventar');
    ?>
<div class="sl-page-header">
  <h1>Enhed information</h1>
  <div>
    <a href="./" class="sl-btn">Tilbage til inventar</a>
    <?php if (!$cur): ?><a href="<?= e(lg_url(['side' => 'reserver', 'ids' => $id])) ?>" class="sl-btn sl-btn-warning">Reserver Enhed</a><?php endif; ?>
    <a href="<?= e(lg_url(['side' => 'rediger', 'id' => $id])) ?>" class="sl-btn sl-btn-primary">Rediger Enhed</a>
    <form method="post" data-confirm="Er du sikker på at du vil slette denne Enhed? Dette vil også slette al historik."><?= lg_csrf() ?><input type="hidden" name="handling" value="slet"><input type="hidden" name="id" value="<?= $id ?>"><button type="submit" class="sl-btn sl-btn-danger">Slet Enhed</button></form>
  </div>
</div>

<div class="sl-info-card">
  <h2><?= e($e['navn']) ?></h2>
  <div class="sl-info-grid">
    <div class="sl-info-item"><strong>ID</strong><span><?= e($e['sku']) ?></span></div>
    <div class="sl-info-item"><strong>Placering</strong><span><?= $e['placering'] !== '' ? e($e['placering']) : '-' ?></span></div>
    <div class="sl-info-item"><strong>Status</strong><span><?= lg_badge($cur) ?></span></div>
  </div>
  <?php if ($e['beskrivelse'] !== ''): ?>
  <div class="sl-info-item sl-desc">
    <strong>Beskrivelse</strong><span><?= nl2br(e($e['beskrivelse'])) ?></span>
  </div>
  <?php endif; ?>
</div>

<?php if ($cur): ?>
<div class="sl-active-checkout">
  <h3>Nuværende reservation</h3>
  <div class="sl-checkout-details">
    <p><strong>Brugers navn:</strong> <?= e($cur['navn']) ?></p>
    <p><strong>Fra:</strong> <?= e(lg_dmy($cur['fra'])) ?></p>
    <p><strong>Til:</strong> <?= e(lg_dmy($cur['til'])) ?><?= lg_overdue($cur) ? ' <span class="sl-badge sl-badge-danger">Overskredet</span>' : '' ?></p>
    <p><strong>Reserveret:</strong> <?= e(lg_dmy($cur['oprettet'])) ?></p>
    <?php if ($cur['note'] !== ''): ?><p><strong>Beskrivelse:</strong> <?= nl2br(e($cur['note'])) ?></p><?php endif; ?>
  </div>
  <form method="post" data-confirm="Marker denne Enhed som returneret?"><?= lg_csrf() ?><input type="hidden" name="handling" value="returner"><input type="hidden" name="tilbage" value="info"><input type="hidden" name="ids[]" value="<?= $id ?>">
    <button type="submit" class="sl-btn sl-btn-primary">Returner Enhed</button></form>
</div>
<?php endif; ?>

<?php if ($hist): ?>
<div class="sl-section">
  <h3>Historik</h3>
  <div class="sl-table-wrap">
  <table class="sl-table">
    <thead><tr><th>Brugers navn</th><th>Fra</th><th>Til</th><th>Reserveret</th><th>Status</th></tr></thead>
    <tbody>
    <?php foreach ($hist as $r): ?>
      <tr>
        <td><?= e($r['navn']) ?></td>
        <td><?= e(lg_dmy($r['fra'])) ?></td>
        <td><?= e(lg_dmy($r['til'])) ?></td>
        <td><?= e(lg_dmy($r['oprettet'])) ?></td>
        <td><?= $r['status'] === 'aktiv' ? (lg_overdue($r) ? '<span class="sl-badge sl-badge-danger">Overskredet</span>' : '<span class="sl-badge sl-badge-warning">Aktiv</span>') : '<span class="sl-badge sl-badge-success">Returneret</span>' ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</div>
<?php endif; ?>
<?php
    lg_end();
}

function lg_page_add(array $d): void
{
    lg_start('Tilføj Enhed', 'inventar');
    ?>
<div class="sl-page-header">
  <h1>Tilføj ny Enhed</h1>
  <a href="./" class="sl-btn">Tilbage til inventar</a>
</div>
<div class="sl-section">
  <form method="post" class="sl-form" id="add-item-form">
    <?= lg_csrf() ?><input type="hidden" name="handling" value="ny-enhed">
    <div class="sl-add-grid">
      <label for="item-type-select">Sektion *</label>
      <select name="sektion" id="item-type-select">
        <option value="">Ingen</option>
        <?php foreach ($d['sektioner'] as $s): ?><option value="<?= e($s) ?>"<?= lg_old('sektion') === $s ? ' selected' : '' ?>><?= e($s) ?></option><?php endforeach; ?>
      </select>
      <label for="sku-input">ID *</label>
      <input type="text" name="nummer" id="sku-input" required maxlength="20" placeholder="123" value="<?= e(lg_old('nummer')) ?>">
    </div>
    <div class="sl-add-grid">
      <label for="item-name">Navn *</label>
      <input type="text" name="navn" id="item-name" required value="<?= e(lg_old('navn')) ?>">
      <label for="item-location">Placering</label>
      <input type="text" name="placering" id="item-location" value="<?= e(lg_old('placering')) ?>">
    </div>
    <div class="sl-form-group">
      <label for="item-description">Beskrivelse</label>
      <textarea name="beskrivelse" id="item-description" rows="3" class="sl-wide"><?= e(lg_old('beskrivelse')) ?></textarea>
    </div>
    <div class="sl-form-actions">
      <button type="submit" class="sl-btn sl-btn-primary">Tilføj Enhed</button>
      <a href="./" class="sl-btn">Annuller</a>
    </div>
  </form>
</div>
<div class="sl-section">
  <h3>Live Preview</h3>
  <div class="sl-preview">
    <table class="sl-table">
      <thead><tr><th class="sl-col-check"></th><th>ID</th><th>Navn</th><th>Placering</th><th>Status</th></tr></thead>
      <tbody><tr>
        <td><input type="checkbox" disabled aria-label="Eksempel"></td>
        <td><strong id="preview-sku" class="sl-muted">-</strong></td>
        <td id="preview-name" class="sl-muted">-</td>
        <td id="preview-location" class="sl-muted">-</td>
        <td><span class="sl-badge sl-badge-success">Tilgængelig</span></td>
      </tr></tbody>
    </table>
  </div>
</div>
<?php
    lg_end();
}

function lg_page_edit(array $d, int $id): void
{
    $i = lg_item_index($d, $id);
    if ($i === null) { lg_not_found(); return; }
    $e = $d['enheder'][$i];
    $val = function (string $k) use ($e) { return lg_old($k, (string)$e[$k]); };
    lg_start('Rediger ' . $e['navn'], 'inventar');
    ?>
<div class="sl-page-header">
  <h1>Rediger Enhed</h1>
  <a href="<?= e(lg_url(['side' => 'info', 'id' => $id])) ?>" class="sl-btn">Annuller</a>
</div>
<div class="sl-section">
  <form method="post" class="sl-form">
    <?= lg_csrf() ?><input type="hidden" name="handling" value="rediger"><input type="hidden" name="id" value="<?= $id ?>">
    <div class="sl-form-group"><label for="f-sku">ID (SKU) *</label><input type="text" id="f-sku" name="sku" required value="<?= e($val('sku')) ?>"></div>
    <div class="sl-form-group"><label for="f-navn">Navn *</label><input type="text" id="f-navn" name="navn" required value="<?= e($val('navn')) ?>"></div>
    <div class="sl-form-group"><label for="f-plac">Placering</label><input type="text" id="f-plac" name="placering" value="<?= e($val('placering')) ?>"></div>
    <div class="sl-form-group"><label for="f-besk">Beskrivelse</label><textarea id="f-besk" name="beskrivelse" rows="5"><?= e($val('beskrivelse')) ?></textarea></div>
    <div class="sl-form-actions">
      <button type="submit" class="sl-btn sl-btn-primary">Opdater Enhed</button>
      <a href="<?= e(lg_url(['side' => 'info', 'id' => $id])) ?>" class="sl-btn">Annuller</a>
    </div>
  </form>
</div>
<?php
    lg_end();
}

/* =========================================================
   Reserver
========================================================= */

function lg_page_reserve(array $d, array $ids): void
{
    global $me;
    $active = lg_active($d);
    $free = [];
    foreach ($ids as $id) {
        $i = lg_item_index($d, $id);
        if ($i !== null && !isset($active[$id])) $free[] = $d['enheder'][$i];
    }
    $start = (int)ceil(time() / 900) * 900;
    $fra = lg_old('fra', date('Y-m-d\TH:i', $start));
    $til = lg_old('til', date('Y-m-d\TH:i', $start + 86400));
    $many = count($free) > 1;

    lg_start('Reserver', 'inventar');
    if (!$free) {
        echo '<div class="sl-alert sl-alert-danger">Ingen tilgængelige Enheder fundet</div><p><a class="sl-btn" href="./">Tilbage til inventar</a></p>';
        lg_end();
        return;
    }
    ?>
<div class="sl-page-header">
  <h1><?= $many ? 'Reserver flere Enheder' : 'Reserver Enhed' ?></h1>
  <a href="./" class="sl-btn">Tilbage til inventar</a>
</div>
<div class="sl-section">
  <?php if ($many): ?>
  <h3>Valgte Enheder (<?= count($free) ?>)</h3>
  <ul>
    <?php foreach ($free as $e): ?><li><strong><?= e($e['navn']) ?></strong> (<?= e($e['sku']) ?>)</li><?php endforeach; ?>
  </ul>
  <?php else: ?>
  <h2><?= e($free[0]['navn']) ?></h2>
  <p><strong>ID:</strong> <?= e($free[0]['sku']) ?></p>
  <p><strong>Placering:</strong> <?= $free[0]['placering'] !== '' ? e($free[0]['placering']) : '-' ?></p>
  <?php endif; ?>
  <form method="post" class="sl-form">
    <?= lg_csrf() ?><input type="hidden" name="handling" value="reserver">
    <?php foreach ($free as $e): ?><input type="hidden" name="ids[]" value="<?= (int)$e['id'] ?>"><?php endforeach; ?>
    <div class="sl-form-group"><label for="f-who">Brugers navn *</label><input type="text" id="f-who" value="<?= e($me['navn']) ?>" readonly></div>
    <div class="sl-form-group"><label for="f-note"><?= $many ? 'Beskrivelse (hvad skal de bruges til?) *' : 'Beskrivelse (hvad skal det reserveres til?)' ?></label>
      <textarea id="f-note" name="note" rows="3"<?= $many ? ' required' : '' ?>><?= e(lg_old('note')) ?></textarea></div>
    <div class="sl-form-row">
      <div class="sl-form-group"><label for="f-fra">Fra *</label><input type="datetime-local" id="f-fra" name="fra" required value="<?= e($fra) ?>"></div>
      <div class="sl-form-group"><label for="f-til">Til *</label><input type="datetime-local" id="f-til" name="til" required value="<?= e($til) ?>"></div>
    </div>
    <div class="sl-form-actions">
      <button type="submit" class="sl-btn sl-btn-warning"><?= $many ? 'Reserver alle Enheder' : 'Reserver Enhed' ?></button>
      <a href="./" class="sl-btn">Annuller</a>
    </div>
  </form>
</div>
<?php
    lg_end();
}

/* =========================================================
   Historik
========================================================= */

function lg_page_history(array $d): void
{
    $search = sc_line($_GET['search'] ?? '', 200);
    $items = [];
    foreach ($d['enheder'] as $e) $items[(int)$e['id']] = $e;
    $t = sc_lower($search);
    $hist = array_filter($d['reservationer'], function ($r) use ($items, $t) {
        if ($t === '') return true;
        $e = $items[(int)$r['enhed']] ?? ['navn' => '', 'sku' => ''];
        return strpos(sc_lower($e['navn'] . ' ' . $e['sku'] . ' ' . $r['navn']), $t) !== false;
    });
    usort($hist, function ($a, $b) { return strcmp($b['oprettet'], $a['oprettet']); });

    lg_start('Historik', 'historik');
    ?>
<div class="sl-page-header">
  <h1>Historik</h1>
  <a href="./" class="sl-btn">Tilbage til inventar</a>
</div>
<div class="sl-search-box">
  <form method="get">
    <input type="hidden" name="side" value="historik">
    <input type="text" name="search" value="<?= e($search) ?>" placeholder="Søg efter Enhedsnavn, ID, eller brugers navn...">
    <button type="submit" class="sl-btn sl-btn-primary">Søg</button>
    <?php if ($search !== ''): ?><a href="<?= e(lg_url(['side' => 'historik'])) ?>" class="sl-btn">Ryd</a><?php endif; ?>
  </form>
</div>
<div class="sl-section">
  <div class="sl-table-wrap">
  <table class="sl-table">
    <thead><tr><th>Enhed ID</th><th>Enhedsnavn</th><th>Brugers navn</th><th>Fra</th><th>Til</th><th>Reserveret</th><th>Status</th></tr></thead>
    <tbody>
    <?php if (!$hist): ?><tr><td colspan="7">Ingen historik fundet.</td></tr><?php endif; ?>
    <?php foreach ($hist as $r): $e = $items[(int)$r['enhed']] ?? null; ?>
      <tr>
        <td><?= $e ? e($e['sku']) : '-' ?></td>
        <td><strong><?= $e ? '<a href="' . e(lg_url(['side' => 'info', 'id' => $e['id']])) . '">' . e($e['navn']) . '</a>' : '(slettet)' ?></strong></td>
        <td><?= e($r['navn']) ?></td>
        <td><?= e(lg_dmy($r['fra'])) ?></td>
        <td><?= e(lg_dmy($r['til'])) ?></td>
        <td><?= e(lg_dmy($r['oprettet'])) ?></td>
        <td><?= $r['status'] === 'aktiv' ? (lg_overdue($r) ? '<span class="sl-badge sl-badge-danger">Overskredet</span>' : '<span class="sl-badge sl-badge-warning">Aktiv</span>') : '<span class="sl-badge sl-badge-success">Returneret</span>' ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</div>
<?php
    lg_end();
}

/* =========================================================
   Indstillinger (sektioner, import/eksport, min konto)
========================================================= */

function lg_page_settings(array $d): void
{
    global $me;
    lg_start('Indstillinger', 'indstillinger');
    ?>
<div class="sl-page-header">
  <h1>Indstillinger</h1>
</div>
<div class="sl-section">
  <h2>Administrer sektioner</h2>
  <div class="sl-card">
    <h3>Tilføj ny sektion</h3>
    <form method="post" class="sl-inline-form">
      <?= lg_csrf() ?><input type="hidden" name="handling" value="sektion-ny">
      <div><label for="f-sek">Sektion navn</label>
        <input type="text" id="f-sek" name="navn" placeholder="f.eks. LAGER, SCENE, VIDEO" maxlength="30" required value="<?= e(lg_old('navn')) ?>"></div>
      <button type="submit" class="sl-btn sl-btn-primary">Tilføj sektion</button>
    </form>
  </div>
  <div class="sl-card">
    <h3>Nuværende sektioner</h3>
    <?php if (!$d['sektioner']): ?><p class="sl-muted">Ingen sektioner fundet.</p><?php endif; ?>
    <div class="sl-list">
      <?php foreach ($d['sektioner'] as $s): ?>
      <div class="sl-list-row">
        <span><?= e($s) ?></span>
        <form method="post" data-confirm="Er du sikker på at du vil slette sektionen '<?= e($s) ?>'? Enhederne bliver ikke slettet."><?= lg_csrf() ?><input type="hidden" name="handling" value="sektion-slet"><input type="hidden" name="navn" value="<?= e($s) ?>">
          <button type="submit" class="sl-btn sl-btn-danger sl-btn-sm">Slet</button></form>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="sl-card">
    <h2>Import/Export Data</h2>
    <div class="sl-sub">
      <h3>Importer Enheder</h3>
      <p class="sl-muted">Upload en Excel-fil (.xlsx) eller CSV-fil med kolonnerne: ID, Navn, Placering (og evt. Beskrivelse). ID'er, der allerede findes, springes over.</p>
      <form method="post" enctype="multipart/form-data" class="sl-inline-form">
        <?= lg_csrf() ?><input type="hidden" name="handling" value="import">
        <div><input type="file" name="fil" accept=".xlsx,.csv" required aria-label="Vælg fil"></div>
        <button type="submit" class="sl-btn sl-btn-primary">Importer</button>
      </form>
    </div>
    <div class="sl-sub">
      <h3>Eksporter Enheder</h3>
      <p class="sl-muted">Download alle enheder som Excel-fil</p>
      <form method="post"><?= lg_csrf() ?><input type="hidden" name="handling" value="eksport">
        <button type="submit" class="sl-btn sl-btn-success">Eksporter til Excel</button></form>
    </div>
  </div>

  <div class="sl-card">
    <h2>Min konto</h2>
    <p class="sl-muted">Du er logget ind som <strong><?= e($me['brugernavn']) ?></strong> (<?= $me['rolle'] === 'admin' ? 'administrator' : 'bruger' ?>).</p>
    <form method="post" class="sl-form">
      <?= lg_csrf() ?><input type="hidden" name="handling" value="min-konto">
      <div class="sl-form-group"><label for="k-navn">Navn (vises når du reserverer)</label><input type="text" id="k-navn" name="navn" required value="<?= e($me['navn']) ?>"></div>
      <div class="sl-form-group"><label for="k-pw">Nuværende adgangskode (kun hvis du vil skifte den)</label><input type="password" id="k-pw" name="kodeord" autocomplete="current-password"></div>
      <div class="sl-form-row">
        <div class="sl-form-group"><label for="k-pw1">Ny adgangskode</label><input type="password" id="k-pw1" name="nytKodeord" autocomplete="new-password"></div>
        <div class="sl-form-group"><label for="k-pw2">Gentag ny adgangskode</label><input type="password" id="k-pw2" name="gentag" autocomplete="new-password"></div>
      </div>
      <button type="submit" class="sl-btn sl-btn-primary">Gem</button>
    </form>
  </div>
</div>
<?php
    lg_end();
}

/* =========================================================
   Brugere (kun administratorer) – tilføj bruger som i editoren
========================================================= */

function lg_page_users(): void
{
    global $me;
    $users = lg_users();
    usort($users, function ($a, $b) { return strcasecmp($a['navn'], $b['navn']); });
    $creds = $_SESSION['lg_creds'] ?? null;
    unset($_SESSION['lg_creds']);

    lg_start('Brugere', 'brugere');
    ?>
<div class="sl-page-header">
  <h1>Brugerstyring</h1>
</div>
<div class="sl-section">
  <?php if ($creds):
      $host = preg_replace('~[^A-Za-z0-9.\-:\[\]]~', '', $_SERVER['HTTP_HOST'] ?? 'localhost');
      $url = (sc_is_https() ? 'https' : 'http') . '://' . $host . rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/lager/index.php')), '/') . '/';
      $text = 'Hej ' . explode(' ', $creds['navn'])[0] . "!\n\nDu kan nu bruge Teater Scenarios lager her:\n$url\n\nBrugernavn: {$creds['brugernavn']}\nAdgangskode: {$creds['kodeord']}\n\nSkift gerne adgangskoden under Indstillinger, når du er logget ind.";
  ?>
  <div class="sl-alert sl-alert-success">
    <strong>Login er klar.</strong> Send denne besked til <?= e($creds['navn']) ?>, fx på sms eller mail. Adgangskoden bliver ikke vist igen.
    <pre class="sl-creds" id="sl-creds-text"><?= e($text) ?></pre>
    <button type="button" class="sl-btn sl-btn-success sl-btn-sm" id="sl-copy">Kopiér beskeden</button>
  </div>
  <?php endif; ?>

  <div class="sl-card">
    <h3>Tilføj ny bruger</h3>
    <form method="post" class="sl-form" id="sl-new-user">
      <?= lg_csrf() ?><input type="hidden" name="handling" value="bruger-ny">
      <div class="sl-form-group"><label for="u-navn">Navn *</label><input type="text" id="u-navn" name="navn" required placeholder="fx Anna Hansen" value="<?= e(lg_old('navn')) ?>" autocomplete="off"></div>
      <div class="sl-form-group"><label for="u-bn">Brugernavn *</label><input type="text" id="u-bn" name="brugernavn" required placeholder="fx anna" value="<?= e(lg_old('brugernavn')) ?>" autocomplete="off" autocapitalize="none">
        <p class="sl-help">Små bogstaver, uden mellemrum.</p></div>
      <div class="sl-form-group"><label for="u-pw">Midlertidig adgangskode *</label><input type="text" id="u-pw" name="kodeord" required value="<?= e(lg_make_password()) ?>" autocomplete="off">
        <p class="sl-help">Personen kan selv skifte den under Indstillinger. <button type="button" class="sl-link" id="u-pw-new">Lav en ny</button></p></div>
      <div class="sl-form-group"><label for="u-rolle">Rolle</label>
        <select id="u-rolle" name="rolle">
          <option value="bruger">Bruger – kan se, reservere og rette udstyr</option>
          <option value="admin"<?= lg_old('rolle') === 'admin' ? ' selected' : '' ?>>Administrator – kan også styre brugere</option>
        </select></div>
      <button type="submit" class="sl-btn sl-btn-primary">Tilføj bruger</button>
    </form>
  </div>

  <div class="sl-card">
    <h3>Nuværende brugere</h3>
    <div class="sl-table-wrap">
    <table class="sl-table">
      <thead><tr><th>Brugernavn</th><th>Navn</th><th>Rolle</th><th>Sidst logget ind</th><th>Handlinger</th></tr></thead>
      <tbody>
      <?php foreach ($users as $u): $self = strcasecmp($u['brugernavn'], $me['brugernavn']) === 0; ?>
        <tr>
          <td><strong><?= e($u['brugernavn']) ?></strong></td>
          <td><?= e($u['navn']) ?><?= $self ? ' <span class="sl-muted">(dig)</span>' : '' ?></td>
          <td><?= $u['rolle'] === 'admin' ? 'Administrator' : 'Bruger' ?></td>
          <td><?= $u['sidstLoggetInd'] ? e(lg_dmy($u['sidstLoggetInd'])) : '<span class="sl-muted">Aldrig</span>' ?></td>
          <td>
            <?php if (!$self): ?>
            <form method="post" data-confirm="<?= e($u['navn']) ?> får en ny adgangskode, og den gamle holder op med at virke."><?= lg_csrf() ?><input type="hidden" name="handling" value="bruger-kodeord"><input type="hidden" name="brugernavn" value="<?= e($u['brugernavn']) ?>">
              <button type="submit" class="sl-btn sl-btn-sm">Ny adgangskode</button></form>
            <form method="post"><?= lg_csrf() ?><input type="hidden" name="handling" value="bruger-rolle"><input type="hidden" name="brugernavn" value="<?= e($u['brugernavn']) ?>"><input type="hidden" name="rolle" value="<?= $u['rolle'] === 'admin' ? 'bruger' : 'admin' ?>">
              <button type="submit" class="sl-btn sl-btn-sm"><?= $u['rolle'] === 'admin' ? 'Gør til bruger' : 'Gør til administrator' ?></button></form>
            <form method="post" data-confirm="Er du sikker på at du vil slette brugeren &quot;<?= e($u['brugernavn']) ?>&quot;? Denne handling kan ikke fortrydes."><?= lg_csrf() ?><input type="hidden" name="handling" value="bruger-slet"><input type="hidden" name="brugernavn" value="<?= e($u['brugernavn']) ?>">
              <button type="submit" class="sl-btn sl-btn-danger sl-btn-sm">Slet</button></form>
            <?php else: ?><span class="sl-muted">-</span><?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    </div>
  </div>
</div>
<?php
    lg_end();
}
