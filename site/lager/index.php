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
                lg_go();
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
   Sider (GET)
   Fast opbygning på alle sider: titel (+ knap) → filterbjælke → tabel/formular.
========================================================= */

$side = (string)($_GET['side'] ?? 'inventar');

if (!lg_users()) {
    lg_page_login(true);
    exit;
}
if (!$me) {
    if (!empty($_GET['popup'])) { http_response_code(401); exit; }
    lg_page_login(false);
    exit;
}

$d = lg_data();
switch ($side) {
    case 'info': lg_page_info($d, (int)($_GET['id'] ?? 0)); break;
    case 'ny': lg_page_item_form($d, null); break;
    case 'rediger': lg_page_item_form($d, (int)($_GET['id'] ?? 0)); break;
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

function lg_hidden(array $q): string
{
    $h = '';
    foreach ($q as $k => $v) $h .= '<input type="hidden" name="' . e($k) . '" value="' . e($v) . '">';
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
    foreach ($_SESSION['lg_flash'] ?? [] as [$type, $text]) {
        echo '<div class="sl-alert sl-alert-' . ($type === 'ok' ? 'success' : 'danger') . '">' . e($text) . '</div>';
    }
    unset($_SESSION['lg_flash']);
}

function lg_start(string $title, string $active): void
{
    global $me;
    lg_html_start($title);
    $nav = [['inventar', 'Inventar'], ['historik', 'Historik'], ['indstillinger', 'Indstillinger']];
    if ($me['rolle'] === 'admin') $nav[] = ['brugere', 'Brugere'];
    ?>
<div class="sl-top">
  <div class="sl-wrap sl-top-inner">
    <a class="sl-brand" href="./">Scenari<span class="red-o">o</span> Lager</a>
    <ul class="sl-nav">
      <?php foreach ($nav as [$id, $label]): ?>
      <li><a href="<?= e(lg_url($id === 'inventar' ? [] : ['side' => $id])) ?>"<?= $active === $id ? ' class="active"' : '' ?>><?= e($label) ?></a></li>
      <?php endforeach; ?>
    </ul>
    <div class="sl-user"><?= e($me['navn']) ?> ·
      <form method="post"><?= lg_csrf() ?><input type="hidden" name="handling" value="logout"><button type="submit" class="sl-link">Log ud</button></form>
    </div>
  </div>
</div>
<div class="sl-wrap sl-main">
<?php
    lg_alerts();
}

function lg_end(): void
{
    echo "</div>\n<dialog class=\"sl-popup\" id=\"sl-popup\" aria-label=\"Enhed\"></dialog>\n</body>\n</html>\n";
}

/** Titel med knapper til højre og en lille forklaring under. */
function lg_title(string $title, string $actions = '', string $sub = ''): void
{
    echo '<div class="sl-titlebar"><h1>' . e($title) . '</h1>' . ($actions !== '' ? '<div class="sl-actions">' . $actions . '</div>' : '') . '</div>';
    if ($sub !== '') echo '<p class="sl-sub">' . $sub . '</p>';
}

function lg_back(string $href, string $label): void
{
    echo '<p class="sl-back"><a href="' . e($href) . '">« ' . e($label) . '</a></p>';
}

function lg_badge(?array $res): string
{
    if (!$res) return '<span class="sl-st sl-st-ok">Tilgængelig</span>';
    return lg_overdue($res) ? '<span class="sl-st sl-st-bad">Overskredet</span>' : '<span class="sl-st sl-st-warn">Reserveret</span>';
}

function lg_res_status(array $r): string
{
    if ($r['status'] !== 'aktiv') return '<span class="sl-st sl-st-ok">Returneret</span>';
    return lg_overdue($r) ? '<span class="sl-st sl-st-bad">Overskredet</span>' : '<span class="sl-st sl-st-warn">Aktiv</span>';
}

function lg_not_found(): void
{
    lg_start('Ikke fundet', 'inventar');
    lg_back('./', 'Tilbage til inventar');
    echo '<h1>Enheden findes ikke</h1><p>Den er måske blevet slettet.</p>';
    lg_end();
}

/* =========================================================
   Login
========================================================= */

function lg_page_login(bool $setup): void
{
    lg_html_start($setup ? 'Opsætning' : 'Log ind');
    ?>
<div class="sl-login">
  <h1>Scenari<span class="red-o">o</span> Lager</h1>
  <?php lg_alerts(); ?>
  <?php if ($setup): ?><p>Opret den første administrator. Bagefter kan du tilføje flere personer under “Brugere”.</p><?php endif; ?>
  <form method="post">
    <?= lg_csrf() ?>
    <input type="hidden" name="handling" value="<?= $setup ? 'opsaetning' : 'login' ?>">
    <table class="sl-form">
      <?php if ($setup): ?>
      <tr><th><label for="f-navn">Dit navn</label></th><td><input type="text" id="f-navn" name="navn" required autocomplete="name" value="<?= e(lg_old('navn')) ?>"></td></tr>
      <?php endif; ?>
      <tr><th><label for="f-bn">Brugernavn</label></th><td><input type="text" id="f-bn" name="brugernavn" required autocomplete="username" autocapitalize="none" value="<?= e(lg_old('brugernavn')) ?>"></td></tr>
      <tr><th><label for="f-pw">Adgangskode</label></th><td><input type="password" id="f-pw" name="kodeord" required autocomplete="<?= $setup ? 'new-password' : 'current-password' ?>"></td></tr>
      <?php if ($setup): ?>
      <tr><th><label for="f-pw2">Gentag</label></th><td><input type="password" id="f-pw2" name="gentag" required autocomplete="new-password"></td></tr>
      <?php endif; ?>
      <tr><th></th><td><button type="submit" class="sl-btn sl-btn-main"><?= $setup ? 'Opret og log ind' : 'Log ind' ?></button></td></tr>
    </table>
  </form>
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
    $status = in_array($_GET['status'] ?? '', ['available', 'reserved', 'overdue'], true) ? $_GET['status'] : '';
    $sek = in_array($_GET['sektion'] ?? '', $d['sektioner'], true) ? $_GET['sektion'] : '';
    $sort = in_array($_GET['sort'] ?? '', ['id', 'name', 'location'], true) ? $_GET['sort'] : 'id';
    $dir = strtolower((string)($_GET['dir'] ?? '')) === 'desc' ? 'desc' : 'asc';
    $active = lg_active($d);

    $nRes = count($active);
    $nOver = count(array_filter($active, 'lg_overdue'));

    $terms = array_filter(array_map('trim', explode(',', sc_lower($search))), 'strlen');
    $items = array_filter($d['enheder'], function ($e) use ($terms, $status, $sek, $active) {
        $r = $active[(int)$e['id']] ?? null;
        if ($status === 'available' && $r) return false;
        if ($status === 'reserved' && !$r) return false;
        if ($status === 'overdue' && !($r && lg_overdue($r))) return false;
        if ($sek !== '' && stripos($e['sku'], $sek . '-') !== 0) return false;
        if ($terms) {
            $hay = sc_lower($e['navn'] . ' ' . $e['sku'] . ' ' . $e['placering']);
            foreach ($terms as $t) if (strpos($hay, $t) !== false) return true;
            return false;
        }
        return true;
    });
    $field = ['id' => 'sku', 'name' => 'navn', 'location' => 'placering'][$sort];
    usort($items, function ($a, $b) use ($field, $dir) {
        $c = strnatcasecmp($a[$field], $b[$field]) ?: strnatcasecmp($a['sku'], $b['sku']);
        return $dir === 'desc' ? -$c : $c;
    });

    $state = array_filter(['search' => $search, 'status' => $status, 'sektion' => $sek, 'sort' => $sort !== 'id' ? $sort : '', 'dir' => $dir !== 'asc' ? $dir : '']);
    $th = function (string $label, string $f) use ($state, $sort, $dir) {
        $on = $sort === $f;
        $q = array_merge($state, ['sort' => $f, 'dir' => $on && $dir === 'asc' ? 'desc' : 'asc']);
        return '<th><a href="' . e(lg_url($q)) . '">' . e($label) . ($on ? ($dir === 'asc' ? ' ▲' : ' ▼') : '') . '</a></th>';
    };
    $filtered = $search !== '' || $status !== '' || $sek !== '';

    lg_start('Inventar', 'inventar');
    $sub = count($d['enheder']) . ' enheder i alt · ' . $nRes . ' reserveret' . ($nOver ? ' · <span class="sl-st-bad">' . $nOver . ' overskredet</span>' : '');
    lg_title('Inventar', '<a href="' . e(lg_url(['side' => 'ny'])) . '" class="sl-btn sl-btn-main">Tilføj enhed</a>', $sub);
    ?>
<form method="get" class="sl-toolbar" data-autosubmit>
  <?= lg_hidden(array_intersect_key($state, ['sort' => 1, 'dir' => 1])) ?>
  <label for="t-search" class="sl-hide">Søg</label>
  <input type="text" id="t-search" name="search" value="<?= e($search) ?>" placeholder="Søg efter navn, ID eller placering">
  <label for="t-status">Status</label>
  <select id="t-status" name="status">
    <option value="">Alle</option>
    <option value="available"<?= $status === 'available' ? ' selected' : '' ?>>Tilgængelig</option>
    <option value="reserved"<?= $status === 'reserved' ? ' selected' : '' ?>>Reserveret</option>
    <option value="overdue"<?= $status === 'overdue' ? ' selected' : '' ?>>Overskredet</option>
  </select>
  <label for="t-sek">Sektion</label>
  <select id="t-sek" name="sektion">
    <option value="">Alle</option>
    <?php foreach ($d['sektioner'] as $s): ?><option value="<?= e($s) ?>"<?= $sek === $s ? ' selected' : '' ?>><?= e($s) ?></option><?php endforeach; ?>
  </select>
  <button type="submit" class="sl-btn">Søg</button>
  <?php if ($filtered): ?><a href="./" class="sl-btn">Ryd</a><?php endif; ?>
</form>

<div class="sl-table-wrap">
<table class="sl-table" id="sl-inventar">
  <thead>
    <tr>
      <th class="sl-col-check"><input type="checkbox" id="sl-all" title="Vælg alle" aria-label="Vælg alle"></th>
      <?= $th('ID', 'id') ?><?= $th('Navn', 'name') ?><?= $th('Placering', 'location') ?>
      <th>Status</th>
      <th class="sl-col-act"></th>
    </tr>
  </thead>
  <tbody>
  <?php if (!$items): ?>
    <tr><td colspan="6" class="sl-empty"><?= $d['enheder'] ? 'Ingen enheder passer til søgningen.' : 'Lageret er tomt. Tryk “Tilføj enhed”, eller importér fra Excel under Indstillinger.' ?></td></tr>
  <?php endif; ?>
  <?php foreach ($items as $e): $r = $active[(int)$e['id']] ?? null; $info = lg_url(['side' => 'info', 'id' => $e['id']]); ?>
    <tr<?= $r ? ' class="sl-reserved"' : '' ?> data-href="<?= e($info) ?>">
      <td class="sl-col-check"><input type="checkbox" class="sl-pick" value="<?= (int)$e['id'] ?>" data-free="<?= $r ? '0' : '1' ?>" aria-label="Vælg <?= e($e['navn']) ?>"></td>
      <td class="sl-nowrap"><?= e($e['sku']) ?></td>
      <td><a href="<?= e($info) ?>" data-popup><?= e($e['navn']) ?></a></td>
      <td><?= $e['placering'] !== '' ? e($e['placering']) : '-' ?></td>
      <td><?= lg_badge($r) ?><?php if ($r): ?><br><span class="sl-small"><?= e($r['navn']) ?>, til <?= e(lg_dmy($r['til'])) ?></span><?php endif; ?></td>
      <td class="sl-col-act"><a href="<?= e(lg_url(['side' => 'rediger', 'id' => $e['id']])) ?>">Rediger</a></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>

<div class="sl-bulk">
  <span>Med valgte (<span id="sl-count">0</span>):</span>
  <button type="button" class="sl-btn" id="sl-reserve" disabled>Reserver</button>
  <button type="button" class="sl-btn" id="sl-return" disabled>Returner</button>
  <span class="sl-small sl-right"><?= $filtered ? 'Viser ' . count($items) . ' af ' . count($d['enheder']) . ' enheder' : count($items) . ' enheder' ?></span>
</div>
<form method="post" id="sl-return-form" hidden><?= lg_csrf() ?><input type="hidden" name="handling" value="returner"></form>
<?php
    lg_end();
}

/* =========================================================
   Enhed
========================================================= */

function lg_page_info(array $d, int $id): void
{
    $popup = !empty($_GET['popup']);
    $i = lg_item_index($d, $id);
    if ($i === null) {
        if ($popup) { echo '<div class="sl-popup-head"><h2>Ikke fundet</h2><button type="button" class="sl-popup-close" data-close aria-label="Luk">×</button></div><div class="sl-popup-content"><p>Enheden findes ikke længere.</p></div>'; return; }
        lg_not_found();
        return;
    }
    $e = $d['enheder'][$i];
    $cur = lg_active($d)[$id] ?? null;
    $hist = array_values(array_filter($d['reservationer'], function ($r) use ($id) { return (int)$r['enhed'] === $id; }));
    usort($hist, function ($a, $b) { return strcmp($b['oprettet'], $a['oprettet']); });

    if ($popup) {
        header('Cache-Control: no-store');
        echo '<div class="sl-popup-head"><h2>' . e($e['navn']) . '</h2><button type="button" class="sl-popup-close" data-close aria-label="Luk">×</button></div><div class="sl-popup-content">';
        lg_info_body($d, $e, $cur, $hist, true);
        echo '</div>';
        return;
    }
    lg_start($e['navn'], 'inventar');
    lg_back('./', 'Tilbage til inventar');
    lg_title($e['navn']);
    lg_info_body($d, $e, $cur, $hist, false);
    lg_end();
}

function lg_info_body(array $d, array $e, ?array $cur, array $hist, bool $popup): void
{
    $id = (int)$e['id'];
    $sek = lg_section_of($e['sku'], $d['sektioner']);
    $shown = $popup ? array_slice($hist, 0, 5) : $hist;
    ?>
<table class="sl-details">
  <tr><th>ID</th><td><?= e($e['sku']) ?></td></tr>
  <tr><th>Placering</th><td><?= $e['placering'] !== '' ? e($e['placering']) : '-' ?></td></tr>
  <tr><th>Sektion</th><td><?= $sek !== '' ? e($sek) : '-' ?></td></tr>
  <tr><th>Status</th><td><?= lg_badge($cur) ?></td></tr>
  <?php if ($e['beskrivelse'] !== ''): ?><tr><th>Beskrivelse</th><td><?= nl2br(e($e['beskrivelse'])) ?></td></tr><?php endif; ?>
</table>

<?php if ($cur): ?>
<h3>Nuværende reservation</h3>
<table class="sl-details sl-details-warn">
  <tr><th>Reserveret af</th><td><?= e($cur['navn']) ?></td></tr>
  <tr><th>Fra</th><td><?= e(lg_dmy($cur['fra'])) ?></td></tr>
  <tr><th>Til</th><td><?= e(lg_dmy($cur['til'])) ?><?= lg_overdue($cur) ? ' <span class="sl-st sl-st-bad">(overskredet)</span>' : '' ?></td></tr>
  <?php if ($cur['note'] !== ''): ?><tr><th>Formål</th><td><?= nl2br(e($cur['note'])) ?></td></tr><?php endif; ?>
  <tr><th>Reserveret den</th><td><?= e(lg_dmy($cur['oprettet'])) ?></td></tr>
</table>
<?php endif; ?>

<div class="sl-buttons">
  <?php if ($cur): ?>
  <form method="post" data-confirm="Marker “<?= e($e['navn']) ?>” som returneret?"><?= lg_csrf() ?><input type="hidden" name="handling" value="returner"><?php if (!$popup): ?><input type="hidden" name="tilbage" value="info"><?php endif; ?><input type="hidden" name="ids[]" value="<?= $id ?>">
    <button type="submit" class="sl-btn sl-btn-main">Returner</button></form>
  <?php else: ?>
  <a href="<?= e(lg_url(['side' => 'reserver', 'ids' => $id])) ?>" class="sl-btn sl-btn-main">Reserver</a>
  <?php endif; ?>
  <a href="<?= e(lg_url(['side' => 'rediger', 'id' => $id])) ?>" class="sl-btn">Rediger</a>
  <form method="post" data-confirm="Slet “<?= e($e['navn']) ?>”? Al historik for enheden bliver også slettet."><?= lg_csrf() ?><input type="hidden" name="handling" value="slet"><input type="hidden" name="id" value="<?= $id ?>"><button type="submit" class="sl-btn sl-btn-danger">Slet</button></form>
</div>

<h3>Historik</h3>
<?php if (!$hist): ?>
<p class="sl-small">Enheden har ikke været reserveret endnu.</p>
<?php else: ?>
<div class="sl-table-wrap">
<table class="sl-table">
  <thead><tr><th>Reserveret af</th><th>Fra</th><th>Til</th><th>Formål</th><th>Status</th></tr></thead>
  <tbody>
  <?php foreach ($shown as $r): ?>
    <tr><td><?= e($r['navn']) ?></td><td class="sl-nowrap"><?= e(lg_dmy($r['fra'])) ?></td><td class="sl-nowrap"><?= e(lg_dmy($r['til'])) ?></td><td><?= e($r['note']) ?></td><td><?= lg_res_status($r) ?></td></tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
<?php if (count($hist) > count($shown)): ?><p class="sl-small"><a href="<?= e(lg_url(['side' => 'info', 'id' => $id])) ?>">Se hele historikken (<?= count($hist) ?>)</a></p><?php endif; ?>
<?php endif; ?>
<?php
}

function lg_page_item_form(array $d, ?int $id): void
{
    $edit = $id !== null;
    $e = null;
    if ($edit) {
        $i = lg_item_index($d, $id);
        if ($i === null) { lg_not_found(); return; }
        $e = $d['enheder'][$i];
    }
    $val = function (string $k) use ($e) { return lg_old($k, $e ? (string)$e[$k] : ''); };
    $back = $edit ? lg_url(['side' => 'info', 'id' => $id]) : './';

    lg_start($edit ? 'Rediger ' . $e['navn'] : 'Tilføj enhed', 'inventar');
    lg_back($back, $edit ? 'Tilbage til ' . $e['navn'] : 'Tilbage til inventar');
    lg_title($edit ? 'Rediger enhed' : 'Tilføj enhed');
    ?>
<form method="post">
  <?= lg_csrf() ?>
  <input type="hidden" name="handling" value="<?= $edit ? 'rediger' : 'ny-enhed' ?>">
  <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int)$id ?>"><?php endif; ?>
  <table class="sl-form">
    <?php if ($edit): ?>
    <tr><th><label for="f-sku">ID *</label></th><td><input type="text" id="f-sku" name="sku" required maxlength="60" value="<?= e($val('sku')) ?>"></td></tr>
    <?php else: ?>
    <tr><th><label for="f-sek">Sektion</label></th><td>
      <select id="f-sek" name="sektion">
        <option value="">Ingen</option>
        <?php foreach ($d['sektioner'] as $s): ?><option value="<?= e($s) ?>"<?= lg_old('sektion') === $s ? ' selected' : '' ?>><?= e($s) ?></option><?php endforeach; ?>
      </select></td></tr>
    <tr><th><label for="f-nr">ID-nummer *</label></th><td><input type="text" id="f-nr" name="nummer" required maxlength="20" placeholder="fx 012" value="<?= e(lg_old('nummer')) ?>" class="sl-short">
      <span class="sl-small">Fuldt ID: <strong id="f-full">-</strong></span></td></tr>
    <?php endif; ?>
    <tr><th><label for="f-navn">Navn *</label></th><td><input type="text" id="f-navn" name="navn" required maxlength="200" value="<?= e($val('navn')) ?>"></td></tr>
    <tr><th><label for="f-plac">Placering</label></th><td><input type="text" id="f-plac" name="placering" maxlength="200" value="<?= e($val('placering')) ?>" placeholder="fx Reol 2, hylde 3"></td></tr>
    <tr><th><label for="f-besk">Beskrivelse</label></th><td><textarea id="f-besk" name="beskrivelse" rows="4"><?= e($val('beskrivelse')) ?></textarea></td></tr>
    <tr><th></th><td><button type="submit" class="sl-btn sl-btn-main"><?= $edit ? 'Gem ændringer' : 'Tilføj enhed' ?></button> <a href="<?= e($back) ?>" class="sl-btn">Annuller</a></td></tr>
  </table>
</form>
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
    $many = count($free) > 1;

    lg_start('Reserver', 'inventar');
    lg_back('./', 'Tilbage til inventar');
    if (!$free) {
        lg_title('Reserver');
        echo '<p>De valgte enheder er allerede reserveret.</p>';
        lg_end();
        return;
    }
    lg_title($many ? 'Reserver ' . count($free) . ' enheder' : 'Reserver ' . $free[0]['navn']);
    ?>
<div class="sl-table-wrap">
<table class="sl-table sl-table-small">
  <thead><tr><th>ID</th><th>Navn</th><th>Placering</th></tr></thead>
  <tbody>
  <?php foreach ($free as $e): ?><tr><td class="sl-nowrap"><?= e($e['sku']) ?></td><td><?= e($e['navn']) ?></td><td><?= $e['placering'] !== '' ? e($e['placering']) : '-' ?></td></tr><?php endforeach; ?>
  </tbody>
</table>
</div>

<form method="post">
  <?= lg_csrf() ?><input type="hidden" name="handling" value="reserver">
  <?php foreach ($free as $e): ?><input type="hidden" name="ids[]" value="<?= (int)$e['id'] ?>"><?php endforeach; ?>
  <table class="sl-form">
    <tr><th>Reserveres af</th><td><?= e($me['navn']) ?></td></tr>
    <tr><th><label for="f-fra">Fra *</label></th><td><input type="datetime-local" id="f-fra" name="fra" required value="<?= e(lg_old('fra', date('Y-m-d\TH:i', $start))) ?>"></td></tr>
    <tr><th><label for="f-til">Til *</label></th><td><input type="datetime-local" id="f-til" name="til" required value="<?= e(lg_old('til', date('Y-m-d\TH:i', $start + 86400))) ?>"></td></tr>
    <tr><th><label for="f-note">Formål<?= $many ? ' *' : '' ?></label></th><td><textarea id="f-note" name="note" rows="3"<?= $many ? ' required' : '' ?> placeholder="Hvad skal det bruges til?"><?= e(lg_old('note')) ?></textarea></td></tr>
    <tr><th></th><td><button type="submit" class="sl-btn sl-btn-main">Reserver</button> <a href="./" class="sl-btn">Annuller</a></td></tr>
  </table>
</form>
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
        return strpos(sc_lower($e['navn'] . ' ' . $e['sku'] . ' ' . $r['navn'] . ' ' . $r['note']), $t) !== false;
    });
    usort($hist, function ($a, $b) { return strcmp($b['oprettet'], $a['oprettet']); });

    lg_start('Historik', 'historik');
    lg_title('Historik', '', 'Alle reservationer, de nyeste først.');
    ?>
<form method="get" class="sl-toolbar">
  <input type="hidden" name="side" value="historik">
  <label for="h-search" class="sl-hide">Søg</label>
  <input type="text" id="h-search" name="search" value="<?= e($search) ?>" placeholder="Søg efter enhed, ID, navn eller formål">
  <button type="submit" class="sl-btn">Søg</button>
  <?php if ($search !== ''): ?><a href="<?= e(lg_url(['side' => 'historik'])) ?>" class="sl-btn">Ryd</a><?php endif; ?>
</form>
<div class="sl-table-wrap">
<table class="sl-table">
  <thead><tr><th>ID</th><th>Enhed</th><th>Reserveret af</th><th>Fra</th><th>Til</th><th>Formål</th><th>Status</th></tr></thead>
  <tbody>
  <?php if (!$hist): ?><tr><td colspan="7" class="sl-empty">Ingen reservationer fundet.</td></tr><?php endif; ?>
  <?php foreach ($hist as $r): $e = $items[(int)$r['enhed']] ?? null; ?>
    <tr>
      <td class="sl-nowrap"><?= $e ? e($e['sku']) : '-' ?></td>
      <td><?= $e ? '<a href="' . e(lg_url(['side' => 'info', 'id' => $e['id']])) . '" data-popup>' . e($e['navn']) . '</a>' : '(slettet)' ?></td>
      <td><?= e($r['navn']) ?></td>
      <td class="sl-nowrap"><?= e(lg_dmy($r['fra'])) ?></td>
      <td class="sl-nowrap"><?= e(lg_dmy($r['til'])) ?></td>
      <td><?= e($r['note']) ?></td>
      <td><?= lg_res_status($r) ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
<?php
    lg_end();
}

/* =========================================================
   Indstillinger
========================================================= */

function lg_page_settings(array $d): void
{
    global $me;
    lg_start('Indstillinger', 'indstillinger');
    lg_title('Indstillinger');
    ?>
<h2>Sektioner</h2>
<p class="sl-small">Sektionen er første del af en enheds ID, fx <strong>LYD</strong>-012.</p>
<table class="sl-table sl-table-small">
  <thead><tr><th>Sektion</th><th class="sl-col-act"></th></tr></thead>
  <tbody>
  <?php if (!$d['sektioner']): ?><tr><td colspan="2" class="sl-empty">Ingen sektioner.</td></tr><?php endif; ?>
  <?php foreach ($d['sektioner'] as $s): ?>
    <tr><td><?= e($s) ?></td><td class="sl-col-act">
      <form method="post" data-confirm="Slet sektionen <?= e($s) ?>? Enhederne bliver ikke slettet."><?= lg_csrf() ?><input type="hidden" name="handling" value="sektion-slet"><input type="hidden" name="navn" value="<?= e($s) ?>"><button type="submit" class="sl-link sl-danger">Slet</button></form>
    </td></tr>
  <?php endforeach; ?>
  </tbody>
</table>
<form method="post" class="sl-toolbar">
  <?= lg_csrf() ?><input type="hidden" name="handling" value="sektion-ny">
  <label for="f-sek">Ny sektion</label>
  <input type="text" id="f-sek" name="navn" maxlength="30" required placeholder="fx VIDEO" value="<?= e(lg_old('navn')) ?>" class="sl-short">
  <button type="submit" class="sl-btn">Tilføj</button>
</form>

<h2>Importér fra Excel</h2>
<p class="sl-small">Excel-fil (.xlsx) eller CSV-fil med kolonnerne <strong>ID, Navn, Placering</strong> og evt. <strong>Beskrivelse</strong>. ID'er, der allerede findes, springes over.</p>
<form method="post" enctype="multipart/form-data" class="sl-toolbar">
  <?= lg_csrf() ?><input type="hidden" name="handling" value="import">
  <label for="f-fil" class="sl-hide">Fil</label>
  <input type="file" id="f-fil" name="fil" accept=".xlsx,.csv" required>
  <button type="submit" class="sl-btn">Importér</button>
</form>

<h2>Eksportér</h2>
<p class="sl-small">Hent alle <?= count($d['enheder']) ?> enheder som en fil, der kan åbnes i Excel.</p>
<form method="post" class="sl-buttons"><?= lg_csrf() ?><input type="hidden" name="handling" value="eksport">
  <button type="submit" class="sl-btn">Eksportér til Excel</button></form>

<h2>Min konto</h2>
<form method="post">
  <?= lg_csrf() ?><input type="hidden" name="handling" value="min-konto">
  <table class="sl-form">
    <tr><th>Brugernavn</th><td><?= e($me['brugernavn']) ?> (<?= $me['rolle'] === 'admin' ? 'administrator' : 'bruger' ?>)</td></tr>
    <tr><th><label for="k-navn">Navn</label></th><td><input type="text" id="k-navn" name="navn" required value="<?= e($me['navn']) ?>"></td></tr>
    <tr><th><label for="k-pw">Nuværende adgangskode</label></th><td><input type="password" id="k-pw" name="kodeord" autocomplete="current-password"><br><span class="sl-small">Kun hvis du vil skifte adgangskode.</span></td></tr>
    <tr><th><label for="k-pw1">Ny adgangskode</label></th><td><input type="password" id="k-pw1" name="nytKodeord" autocomplete="new-password"></td></tr>
    <tr><th><label for="k-pw2">Gentag ny</label></th><td><input type="password" id="k-pw2" name="gentag" autocomplete="new-password"></td></tr>
    <tr><th></th><td><button type="submit" class="sl-btn sl-btn-main">Gem</button></td></tr>
  </table>
</form>
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
    lg_title('Brugere', '', 'Alle, der kan logge ind på lageret.');
    if ($creds):
        $host = preg_replace('~[^A-Za-z0-9.\-:\[\]]~', '', $_SERVER['HTTP_HOST'] ?? 'localhost');
        $url = (sc_is_https() ? 'https' : 'http') . '://' . $host . rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/lager/index.php')), '/') . '/';
        $text = 'Hej ' . explode(' ', $creds['navn'])[0] . "!\n\nDu kan nu bruge Teater Scenarios lager her:\n$url\n\nBrugernavn: {$creds['brugernavn']}\nAdgangskode: {$creds['kodeord']}\n\nSkift gerne adgangskoden under Indstillinger, når du er logget ind.";
    ?>
<div class="sl-alert sl-alert-success">
  <strong>Login er klar.</strong> Send denne besked til <?= e($creds['navn']) ?>. Adgangskoden bliver ikke vist igen.
  <pre class="sl-creds" id="sl-creds-text"><?= e($text) ?></pre>
  <button type="button" class="sl-btn" id="sl-copy">Kopiér beskeden</button>
</div>
    <?php endif; ?>

<div class="sl-table-wrap">
<table class="sl-table">
  <thead><tr><th>Navn</th><th>Brugernavn</th><th>Rolle</th><th>Sidst logget ind</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($users as $u): $self = strcasecmp($u['brugernavn'], $me['brugernavn']) === 0; ?>
    <tr>
      <td><?= e($u['navn']) ?><?= $self ? ' <span class="sl-small">(dig)</span>' : '' ?></td>
      <td><?= e($u['brugernavn']) ?></td>
      <td><?= $u['rolle'] === 'admin' ? 'Administrator' : 'Bruger' ?></td>
      <td class="sl-nowrap"><?= $u['sidstLoggetInd'] ? e(lg_dmy($u['sidstLoggetInd'])) : 'Aldrig' ?></td>
      <td class="sl-nowrap">
        <?php if (!$self): ?>
        <form method="post" data-confirm="<?= e($u['navn']) ?> får en ny adgangskode, og den gamle holder op med at virke."><?= lg_csrf() ?><input type="hidden" name="handling" value="bruger-kodeord"><input type="hidden" name="brugernavn" value="<?= e($u['brugernavn']) ?>"><button type="submit" class="sl-link">Ny adgangskode</button></form> ·
        <form method="post"><?= lg_csrf() ?><input type="hidden" name="handling" value="bruger-rolle"><input type="hidden" name="brugernavn" value="<?= e($u['brugernavn']) ?>"><input type="hidden" name="rolle" value="<?= $u['rolle'] === 'admin' ? 'bruger' : 'admin' ?>"><button type="submit" class="sl-link"><?= $u['rolle'] === 'admin' ? 'Gør til bruger' : 'Gør til administrator' ?></button></form> ·
        <form method="post" data-confirm="Slet brugeren <?= e($u['brugernavn']) ?>? Det kan ikke fortrydes."><?= lg_csrf() ?><input type="hidden" name="handling" value="bruger-slet"><input type="hidden" name="brugernavn" value="<?= e($u['brugernavn']) ?>"><button type="submit" class="sl-link sl-danger">Slet</button></form>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>

<h2>Tilføj bruger</h2>
<form method="post" id="sl-new-user">
  <?= lg_csrf() ?><input type="hidden" name="handling" value="bruger-ny">
  <table class="sl-form">
    <tr><th><label for="u-navn">Navn *</label></th><td><input type="text" id="u-navn" name="navn" required placeholder="fx Anna Hansen" value="<?= e(lg_old('navn')) ?>" autocomplete="off"></td></tr>
    <tr><th><label for="u-bn">Brugernavn *</label></th><td><input type="text" id="u-bn" name="brugernavn" required placeholder="fx anna" value="<?= e(lg_old('brugernavn')) ?>" autocomplete="off" autocapitalize="none"><br><span class="sl-small">Små bogstaver, uden mellemrum.</span></td></tr>
    <tr><th><label for="u-pw">Adgangskode *</label></th><td><input type="text" id="u-pw" name="kodeord" required value="<?= e(lg_make_password()) ?>" autocomplete="off"> <button type="button" class="sl-link" id="u-pw-new">Lav en ny</button><br><span class="sl-small">Midlertidig – personen kan selv skifte den.</span></td></tr>
    <tr><th><label for="u-rolle">Rolle</label></th><td>
      <select id="u-rolle" name="rolle">
        <option value="bruger">Bruger</option>
        <option value="admin"<?= lg_old('rolle') === 'admin' ? ' selected' : '' ?>>Administrator (kan også styre brugere)</option>
      </select></td></tr>
    <tr><th></th><td><button type="submit" class="sl-btn sl-btn-main">Tilføj bruger</button></td></tr>
  </table>
</form>
<?php
    lg_end();
}
