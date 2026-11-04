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
header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; script-src 'self'; connect-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");

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

function lg_icon(string $name, string $cls = 'ico'): string
{
    static $I = [
        'box' => '<path d="M21 8 12 3 3 8v8l9 5 9-5z"/><path d="m3 8 9 5 9-5M12 13v8"/>',
        'history' => '<path d="M3 12a9 9 0 1 0 3-6.7L3 8"/><path d="M3 3v5h5"/><path d="M12 7v5l3 2"/>',
        'settings' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z"/>',
        'users' => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0"/><path d="M16 4.6a3.5 3.5 0 0 1 0 6.8M18 14.3a6.5 6.5 0 0 1 3.5 5.7"/>',
        'logout' => '<path d="M9 4H6a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h3"/><path d="m16 17 5-5-5-5M21 12H9"/>',
        'plus' => '<path d="M12 5v14M5 12h14"/>',
        'search' => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
        'edit' => '<path d="M4 20h4L19 9l-4-4L4 16z"/><path d="m13 7 4 4"/>',
        'trash' => '<path d="M4 7h16M10 11v6M14 11v6M6 7l1 13h10l1-13M9 7V4h6v3"/>',
        'left' => '<path d="m15 6-6 6 6 6"/>',
        'right' => '<path d="m9 6 6 6-6 6"/>',
        'calendar' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/>',
        'return' => '<path d="M9 14 4 9l5-5"/><path d="M4 9h11a5 5 0 0 1 0 10h-3"/>',
        'upload' => '<path d="M12 16V4M7 9l5-5 5 5"/><path d="M4 16v3a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-3"/>',
        'download' => '<path d="M12 4v11M7 10l5 5 5-5"/><path d="M4 20h16"/>',
        'key' => '<circle cx="8" cy="15" r="4"/><path d="m11 12 9-9M17 6l3 3M14 9l2 2"/>',
        'spark' => '<path d="M12 3v4M12 17v4M3 12h4M17 12h4M6 6l2.5 2.5M15.5 15.5 18 18M6 18l2.5-2.5M15.5 8.5 18 6"/>',
        'copy' => '<rect x="9" y="9" width="11" height="11" rx="2"/><path d="M5 15V5a1 1 0 0 1 1-1h10"/>',
        'check' => '<path d="m5 12 5 5 9-10"/>',
        'x' => '<path d="M6 6l12 12M18 6 6 18"/>',
        'alert' => '<path d="M12 3 2 20h20z"/><path d="M12 10v4M12 17v.01"/>',
        'eye' => '<path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
        'user' => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
    ];
    return '<span class="' . e($cls) . '" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">' . ($I[$name] ?? '') . '</svg></span>';
}

function lg_initials(string $name): string
{
    $parts = preg_split('/\s+/u', trim($name)) ?: [];
    $out = '';
    foreach (array_slice($parts, 0, 2) as $p) $out .= sc_substr($p, 0, 1);
    return function_exists('mb_strtoupper') ? mb_strtoupper($out) : strtoupper($out);
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
                lg_flash('ok', "$sku – $navn er tilføjet.");
                if (!empty($_POST['igen'])) {
                    $_SESSION['lg_old'] = ['sektion' => $sek, 'placering' => sc_line($_POST['placering'] ?? '', 200)];
                    lg_go(['side' => 'ny']);
                }
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
                $back = ['side' => 'konto'];
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
    case 'ny': lg_page_form($d, null); break;
    case 'rediger': lg_page_form($d, (int)($_GET['id'] ?? 0)); break;
    case 'reserver': lg_page_reserve($d, lg_ids($_GET['ids'] ?? '')); break;
    case 'historik': lg_page_history($d); break;
    case 'indstillinger': lg_page_settings($d); break;
    case 'brugere': $me['rolle'] === 'admin' ? lg_page_users() : lg_page_inventory($d); break;
    case 'konto': lg_page_account(); break;
    default: lg_page_inventory($d);
}
exit;

/* =========================================================
   Fælles layout
========================================================= */

function lg_head(string $title): void
{
    $asset = function (string $f): string {
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
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Work+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= e($asset('../admin/admin.css')) ?>">
  <link rel="stylesheet" href="<?= e($asset('lager.css')) ?>">
  <script src="<?= e($asset('lager.js')) ?>" defer></script>
</head>
<?php
}

function lg_toasts(): void
{
    $msgs = $_SESSION['lg_flash'] ?? [];
    unset($_SESSION['lg_flash']);
    echo '<div class="toasts" role="status" aria-live="polite">';
    foreach ($msgs as [$type, $text]) {
        echo '<div class="toast toast-' . ($type === 'ok' ? 'ok' : 'error') . '">' . lg_icon($type === 'ok' ? 'check' : 'alert') . '<span>' . e($text) . '</span></div>';
    }
    echo '</div>';
}

function lg_start(string $title, string $active): void
{
    global $me;
    lg_head($title);
    $nav = [['inventar', 'Inventar', 'box'], ['historik', 'Historik', 'history'], ['indstillinger', 'Indstillinger', 'settings']];
    if ($me['rolle'] === 'admin') $nav[] = ['brugere', 'Brugere', 'users'];
    ?>
<body class="lg">
<a class="lg-skip" href="#lg-main">Spring til indhold</a>
<header class="lg-top">
  <div class="lg-top-inner">
    <a class="lg-brand" href="./"><img src="../billeder/logo.png" alt="Scenario" width="838" height="216"><span>Lager</span></a>
    <nav class="lg-nav" aria-label="Lagermenu">
      <?php foreach ($nav as [$id, $label, $ic]): ?>
      <a href="<?= e(lg_url($id === 'inventar' ? [] : ['side' => $id])) ?>"<?= $active === $id ? ' aria-current="page"' : '' ?>><?= lg_icon($ic) ?><span><?= e($label) ?></span></a>
      <?php endforeach; ?>
    </nav>
    <div class="lg-user">
      <a class="lg-me" href="<?= e(lg_url(['side' => 'konto'])) ?>" title="Min konto"<?= $active === 'konto' ? ' aria-current="page"' : '' ?>>
        <span class="avatar avatar-sm"><?= e(lg_initials($me['navn'])) ?></span><span class="lg-me-name"><?= e($me['navn']) ?></span>
      </a>
      <form method="post" class="lg-logout"><?= lg_csrf() ?><input type="hidden" name="handling" value="logout">
        <button type="submit" class="icon-btn" aria-label="Log ud" title="Log ud"><?= lg_icon('logout') ?></button>
      </form>
    </div>
  </div>
</header>
<main class="lg-main" id="lg-main">
<?php
    lg_toasts();
}

function lg_end(): void
{
    echo "</main>\n";
    lg_confirm_dialog();
    echo "</body>\n</html>\n";
}

/** Dialog til "Er du sikker?" – bruges af formularer med data-confirm. */
function lg_confirm_dialog(): void
{
    ?>
<dialog class="modal" id="lg-confirm" aria-labelledby="lg-confirm-title">
  <div class="modal-head"><h2 id="lg-confirm-title">Er du sikker?</h2></div>
  <div class="modal-body"><p id="lg-confirm-text"></p></div>
  <div class="modal-actions">
    <button type="button" class="btn btn-secondary" value="nej">Annuller</button>
    <button type="button" class="btn btn-primary" value="ja" id="lg-confirm-ok">OK</button>
  </div>
</dialog>
<?php
}

function lg_pagehead(string $title, string $intro = '', string $actions = '', string $eyebrow = ''): void
{
    echo '<header class="page-head lg-head"><div>';
    if ($eyebrow !== '') echo '<p class="lg-eyebrow">' . $eyebrow . '</p>';
    echo '<h1>' . e($title) . '</h1>';
    if ($intro !== '') echo '<p class="intro">' . $intro . '</p>';
    echo '</div>';
    if ($actions !== '') echo '<div class="page-actions">' . $actions . '</div>';
    echo '</header>';
}

/* =========================================================
   Login og første opsætning
========================================================= */

function lg_page_login(bool $setup): void
{
    lg_head($setup ? 'Opsætning' : 'Log ind');
    ?>
<body>
<?php lg_toasts(); ?>
<div class="login lg-login">
  <div class="login-art">
    <img src="../billeder/logo.png" alt="Scenario" width="838" height="216">
    <p>Lager</p>
  </div>
  <div class="login-panel">
    <form method="post" class="login-form">
      <?= lg_csrf() ?>
      <input type="hidden" name="handling" value="<?= $setup ? 'opsaetning' : 'login' ?>">
      <h1><?= $setup ? 'Velkommen!' : 'Log ind' ?></h1>
      <p class="muted"><?= $setup ? 'Opret den første administrator af lageret. Bagefter kan du tilføje flere personer under “Brugere”.' : 'Log ind for at se og reservere udstyr.' ?></p>
      <?php if ($setup): ?>
      <div class="field"><label class="label" for="f-navn">Dit navn</label><input class="input input-lg" id="f-navn" name="navn" autocomplete="name" required value="<?= e(lg_old('navn')) ?>" placeholder="fx Simon Hansen"></div>
      <?php endif; ?>
      <div class="field"><label class="label" for="f-bn">Brugernavn</label><input class="input input-lg" id="f-bn" name="brugernavn" autocomplete="username" autocapitalize="none" spellcheck="false" required value="<?= e(lg_old('brugernavn')) ?>">
        <?php if ($setup): ?><p class="help">Små bogstaver, uden mellemrum – fx simon.</p><?php endif; ?></div>
      <div class="field pw-field"><label class="label" for="f-pw">Kodeord</label><input class="input input-lg" id="f-pw" name="kodeord" type="password" autocomplete="<?= $setup ? 'new-password' : 'current-password' ?>" required data-pw>
        <button type="button" class="pw-toggle" aria-label="Vis kodeord" data-pw-toggle><?= lg_icon('eye') ?></button>
        <?php if ($setup): ?><p class="help">Mindst 8 tegn.</p><?php endif; ?></div>
      <?php if ($setup): ?>
      <div class="field"><label class="label" for="f-pw2">Gentag kodeord</label><input class="input input-lg" id="f-pw2" name="gentag" type="password" autocomplete="new-password" required data-pw></div>
      <?php endif; ?>
      <button type="submit" class="btn btn-primary btn-lg"><?= $setup ? 'Opret og log ind' : 'Log ind' ?></button>
      <a class="back-site" href="../"><?= lg_icon('left') ?>Til hjemmesiden</a>
    </form>
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
    $active = lg_active($d);
    $items = $d['enheder'];
    usort($items, function ($a, $b) { return strnatcasecmp($a['sku'], $b['sku']); });
    $counts = ['ledig' => 0, 'reserveret' => 0, 'overskredet' => 0];
    $noSection = false;
    foreach ($items as $e) {
        $counts[lg_status($active[(int)$e['id']] ?? null)[0]]++;
        if (lg_section_of($e['sku'], $d['sektioner']) === '') $noSection = true;
    }
    $reservedTotal = $counts['reserveret'] + $counts['overskredet'];

    lg_start('Inventar', 'inventar');
    $summary = count($items) . ' enheder' . ($reservedTotal ? " · $reservedTotal reserveret" : '') . ($counts['overskredet'] ? ' · <strong class="lg-danger-text">' . $counts['overskredet'] . ' overskredet</strong>' : '');
    lg_pagehead('Inventar', $summary, '<a class="btn btn-primary" href="' . e(lg_url(['side' => 'ny'])) . '">' . lg_icon('plus') . 'Tilføj enhed</a>');

    if (!$items) {
        echo '<div class="empty">' . lg_icon('box', 'empty-ico') . '<h2>Lageret er tomt</h2><p>Tilføj den første enhed – eller importér hele listen fra Excel under Indstillinger.</p>'
            . '<div class="lg-row-actions"><a class="btn btn-primary" href="' . e(lg_url(['side' => 'ny'])) . '">' . lg_icon('plus') . 'Tilføj enhed</a>'
            . '<a class="btn btn-secondary" href="' . e(lg_url(['side' => 'indstillinger'])) . '">' . lg_icon('upload') . 'Importér fra Excel</a></div></div>';
        lg_end();
        return;
    }
    ?>
<section class="card lg-toolbar" aria-label="Søg og filtrér">
  <label class="lg-search"><?= lg_icon('search') ?><span class="sr">Søg</span>
    <input type="search" id="lg-q" placeholder="Søg på navn, ID eller placering – brug komma for flere" autocomplete="off"></label>
  <div class="lg-filters">
    <div class="chips" data-filter="status" role="group" aria-label="Status">
      <button type="button" class="chip on" data-value="">Alle</button>
      <button type="button" class="chip" data-value="ledig">Tilgængelig (<?= $counts['ledig'] ?>)</button>
      <button type="button" class="chip" data-value="reserveret">Reserveret (<?= $reservedTotal ?>)</button>
      <?php if ($counts['overskredet']): ?><button type="button" class="chip chip-danger" data-value="overskredet">Overskredet (<?= $counts['overskredet'] ?>)</button><?php endif; ?>
    </div>
    <?php if ($d['sektioner']): ?>
    <div class="chips" data-filter="sektion" role="group" aria-label="Sektion">
      <button type="button" class="chip on" data-value="">Alle sektioner</button>
      <?php foreach ($d['sektioner'] as $s): ?><button type="button" class="chip" data-value="<?= e($s) ?>"><?= e($s) ?></button><?php endforeach; ?>
      <?php if ($noSection): ?><button type="button" class="chip" data-value="-">Uden sektion</button><?php endif; ?>
    </div>
    <?php endif; ?>
  </div>
</section>

<div class="card lg-table-card">
  <table class="lg-table" id="lg-inventar">
    <thead>
      <tr>
        <th class="lg-check"><input type="checkbox" id="lg-all" aria-label="Vælg alle viste"></th>
        <th><button type="button" class="lg-sort" data-sort="sku" aria-sort="ascending">ID</button></th>
        <th><button type="button" class="lg-sort" data-sort="navn">Navn</button></th>
        <th><button type="button" class="lg-sort" data-sort="placering">Placering</button></th>
        <th>Status</th>
        <th class="lg-act"><span class="sr">Handlinger</span></th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($items as $e):
        $r = $active[(int)$e['id']] ?? null;
        [$st, $label, $kind] = lg_status($r);
        $sek = lg_section_of($e['sku'], $d['sektioner']);
        $info = lg_url(['side' => 'info', 'id' => $e['id']]);
    ?>
      <tr data-id="<?= (int)$e['id'] ?>" data-sku="<?= e($e['sku']) ?>" data-navn="<?= e($e['navn']) ?>" data-placering="<?= e($e['placering']) ?>" data-sektion="<?= e($sek !== '' ? $sek : '-') ?>" data-status="<?= $st ?>" data-href="<?= e($info) ?>">
        <td class="lg-check"><input type="checkbox" class="lg-select" value="<?= (int)$e['id'] ?>" aria-label="Vælg <?= e($e['navn']) ?>"></td>
        <td class="lg-sku"><?= e($e['sku']) ?></td>
        <td class="lg-name"><a href="<?= e($info) ?>"><?= e($e['navn']) ?></a></td>
        <td class="lg-loc"><?= $e['placering'] !== '' ? e($e['placering']) : '<span class="muted">–</span>' ?></td>
        <td class="lg-status"><span class="badge badge-<?= $kind ?>"><?= $label ?></span>
          <?php if ($r): ?><span class="lg-who"><?= e($r['navn']) ?> · til <?= e(lg_dato($r['til'])) ?></span><?php endif; ?></td>
        <td class="lg-act"><a class="icon-btn" href="<?= e(lg_url(['side' => 'rediger', 'id' => $e['id']])) ?>" aria-label="Redigér <?= e($e['navn']) ?>" title="Redigér"><?= lg_icon('edit') ?></a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <p class="lg-none" id="lg-none" hidden>Ingen enheder passer til søgningen.</p>
</div>
<p class="muted small lg-count" id="lg-count" aria-live="polite"></p>

<div class="savebar lg-bulkbar" id="lg-bulk" role="region" aria-label="Valgte enheder">
  <span class="savebar-text"><span id="lg-bulk-count">0 valgt</span></span>
  <div class="savebar-actions">
    <button type="button" class="btn btn-ghost-dark" id="lg-bulk-clear">Ryd</button>
    <button type="button" class="btn btn-secondary lg-btn-dark" id="lg-bulk-return"><?= lg_icon('return') ?><span>Returner</span></button>
    <button type="button" class="btn btn-primary" id="lg-bulk-reserve"><?= lg_icon('calendar') ?><span>Reserver</span></button>
  </div>
</div>
<form method="post" id="lg-return-form" hidden data-confirm="Markér de valgte enheder som returneret?"><?= lg_csrf() ?><input type="hidden" name="handling" value="returner"></form>
<?php
    lg_end();
}

/* =========================================================
   Enhed: info, ny, rediger
========================================================= */

function lg_not_found(): void
{
    lg_start('Ikke fundet', 'inventar');
    echo '<div class="empty">' . lg_icon('alert', 'empty-ico') . '<h2>Enheden findes ikke</h2><p>Den er måske blevet slettet.</p><a class="btn btn-primary" href="./">Til inventaret</a></div>';
    lg_end();
}

function lg_page_info(array $d, int $id): void
{
    $i = lg_item_index($d, $id);
    if ($i === null) { lg_not_found(); return; }
    $e = $d['enheder'][$i];
    $active = lg_active($d)[$id] ?? null;
    [, $label, $kind] = lg_status($active);
    $hist = array_values(array_filter($d['reservationer'], function ($r) use ($id) { return (int)$r['enhed'] === $id; }));
    usort($hist, function ($a, $b) { return strcmp($b['oprettet'], $a['oprettet']); });
    $sek = lg_section_of($e['sku'], $d['sektioner']);

    lg_start($e['navn'], 'inventar');
    echo '<a class="back" href="./">' . lg_icon('left') . 'Inventar</a>';
    $actions = $active
        ? '<form method="post" data-confirm="Markér “' . e($e['navn']) . '” som returneret?">' . lg_csrf() . '<input type="hidden" name="handling" value="returner"><input type="hidden" name="tilbage" value="info"><input type="hidden" name="ids[]" value="' . $id . '"><button class="btn btn-primary" type="submit">' . lg_icon('return') . 'Returner</button></form>'
        : '<a class="btn btn-primary" href="' . e(lg_url(['side' => 'reserver', 'ids' => $id])) . '">' . lg_icon('calendar') . 'Reserver</a>';
    $actions .= '<a class="btn btn-secondary" href="' . e(lg_url(['side' => 'rediger', 'id' => $id])) . '">' . lg_icon('edit') . 'Redigér</a>';
    $actions .= '<form method="post" data-confirm="Slet “' . e($e['navn']) . '”? Al historik for enheden bliver også slettet." data-danger>' . lg_csrf() . '<input type="hidden" name="handling" value="slet"><input type="hidden" name="id" value="' . $id . '"><button class="btn btn-ghost danger" type="submit">' . lg_icon('trash') . 'Slet</button></form>';
    lg_pagehead($e['navn'], '', $actions, e($e['sku']) . ' <span class="badge badge-' . $kind . '">' . $label . '</span>');
    ?>
<div class="lg-info-grid">
  <section class="card">
    <h2 class="card-title">Detaljer</h2>
    <dl class="lg-dl">
      <div><dt>ID</dt><dd><?= e($e['sku']) ?></dd></div>
      <div><dt>Placering</dt><dd><?= $e['placering'] !== '' ? e($e['placering']) : '–' ?></dd></div>
      <div><dt>Sektion</dt><dd><?= $sek !== '' ? e($sek) : '–' ?></dd></div>
      <div><dt>Tilføjet</dt><dd><?= e(lg_dato($e['oprettet'], false)) ?></dd></div>
    </dl>
    <?php if ($e['beskrivelse'] !== ''): ?><div class="lg-desc"><?= sc_paragraphs($e['beskrivelse']) ?></div><?php endif; ?>
  </section>

  <?php if ($active): ?>
  <section class="card lg-current<?= lg_overdue($active) ? ' is-overdue' : '' ?>">
    <h2 class="card-title">Reserveret lige nu</h2>
    <div class="lg-person"><span class="avatar"><?= e(lg_initials($active['navn'])) ?></span><div><strong><?= e($active['navn']) ?></strong><span class="muted small">reserverede <?= e(lg_dato($active['oprettet'])) ?></span></div></div>
    <dl class="lg-dl">
      <div><dt>Fra</dt><dd><?= e(lg_dato($active['fra'])) ?></dd></div>
      <div><dt>Til</dt><dd><?= e(lg_dato($active['til'])) ?><?= lg_overdue($active) ? ' <span class="badge badge-danger">Overskredet</span>' : '' ?></dd></div>
    </dl>
    <?php if ($active['note'] !== ''): ?><div class="lg-note"><?= sc_paragraphs($active['note']) ?></div><?php endif; ?>
  </section>
  <?php else: ?>
  <section class="card lg-free">
    <h2 class="card-title">Tilgængelig</h2>
    <p class="muted">Enheden er på lageret og kan reserveres.</p>
    <a class="btn btn-primary" href="<?= e(lg_url(['side' => 'reserver', 'ids' => $id])) ?>"><?= lg_icon('calendar') ?>Reserver</a>
  </section>
  <?php endif; ?>
</div>

<section class="card">
  <h2 class="card-title">Historik</h2>
  <?php if (!$hist): ?>
    <p class="muted">Enheden har ikke været reserveret endnu.</p>
  <?php else: ?>
  <ol class="lg-timeline">
    <?php foreach ($hist as $r): [, $l, $k] = $r['status'] === 'aktiv' ? lg_status($r) : ['', 'Returneret', 'muted']; ?>
    <li>
      <span class="lg-dot lg-dot-<?= $k ?>"></span>
      <div>
        <strong><?= e($r['navn']) ?></strong> <span class="badge badge-<?= $k ?>"><?= $l ?></span>
        <p class="muted small"><?= e(lg_dato($r['fra'])) ?> → <?= e(lg_dato($r['til'])) ?><?= $r['returneret'] ? ' · afleveret ' . e(lg_dato($r['returneret'])) : '' ?></p>
        <?php if ($r['note'] !== ''): ?><p class="small"><?= e($r['note']) ?></p><?php endif; ?>
      </div>
    </li>
    <?php endforeach; ?>
  </ol>
  <?php endif; ?>
</section>
<?php
    lg_end();
}

function lg_page_form(array $d, ?int $id): void
{
    $edit = $id !== null;
    $e = null;
    if ($edit) {
        $i = lg_item_index($d, $id);
        if ($i === null) { lg_not_found(); return; }
        $e = $d['enheder'][$i];
    }
    $val = function (string $k) use ($e) { return lg_old($k, $e ? (string)($e[$k] ?? '') : ''); };
    $places = array_values(array_unique(array_filter(array_map(function ($x) { return $x['placering']; }, $d['enheder']))));
    sort($places, SORT_NATURAL | SORT_FLAG_CASE);

    lg_start($edit ? 'Redigér ' . $e['navn'] : 'Tilføj enhed', 'inventar');
    echo '<a class="back" href="' . e($edit ? lg_url(['side' => 'info', 'id' => $id]) : './') . '">' . lg_icon('left') . ($edit ? e($e['navn']) : 'Inventar') . '</a>';
    lg_pagehead($edit ? 'Redigér enhed' : 'Tilføj enhed', $edit ? '' : 'Udfyld ID og navn – resten kan stå tomt.');
    ?>
<form method="post" class="card lg-form" id="lg-item-form">
  <?= lg_csrf() ?>
  <input type="hidden" name="handling" value="<?= $edit ? 'rediger' : 'ny-enhed' ?>">
  <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int)$id ?>"><?php endif; ?>

  <?php if ($edit): ?>
  <div class="field"><label class="label" for="f-sku">ID</label><input class="input" id="f-sku" name="sku" required maxlength="60" value="<?= e($val('sku')) ?>"></div>
  <?php else: ?>
  <div class="grid-2">
    <div class="field"><label class="label" for="f-sek">Sektion</label>
      <select class="input select" id="f-sek" name="sektion">
        <option value="">Ingen</option>
        <?php foreach ($d['sektioner'] as $s): ?><option value="<?= e($s) ?>"<?= lg_old('sektion') === $s ? ' selected' : '' ?>><?= e($s) ?></option><?php endforeach; ?>
      </select>
      <p class="help">Bliver første del af ID'et, fx LYD-012.</p>
    </div>
    <div class="field"><label class="label" for="f-nr">ID-nummer</label>
      <input class="input" id="f-nr" name="nummer" required maxlength="40" value="<?= e(lg_old('nummer')) ?>" placeholder="fx 012" autocomplete="off">
      <p class="help" id="lg-sku-help">Fuldt ID: <strong id="lg-sku-preview">–</strong> <button type="button" class="link-btn" id="lg-next" hidden></button></p>
    </div>
  </div>
  <script type="application/json" id="lg-skus"><?= json_encode(array_map(function ($x) { return $x['sku']; }, $d['enheder']), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
  <?php endif; ?>

  <div class="grid-2">
    <div class="field"><label class="label" for="f-navn">Navn</label><input class="input" id="f-navn" name="navn" required maxlength="200" value="<?= e($val('navn')) ?>" placeholder="fx Trådløs mikrofon"></div>
    <div class="field"><label class="label" for="f-plac">Placering</label><input class="input" id="f-plac" name="placering" maxlength="200" list="lg-places" value="<?= e($val('placering')) ?>" placeholder="fx Reol 3, hylde 2">
      <datalist id="lg-places"><?php foreach ($places as $p): ?><option value="<?= e($p) ?>"><?php endforeach; ?></datalist></div>
  </div>
  <div class="field"><label class="label" for="f-besk">Beskrivelse</label><textarea class="input textarea" id="f-besk" name="beskrivelse" rows="4" placeholder="Fx model, tilbehør eller hvad man skal vide"><?= e($val('beskrivelse')) ?></textarea></div>

  <div class="lg-row-actions">
    <button type="submit" class="btn btn-primary"><?= lg_icon('check') ?><?= $edit ? 'Gem ændringer' : 'Tilføj enhed' ?></button>
    <?php if (!$edit): ?><button type="submit" class="btn btn-secondary" name="igen" value="1"><?= lg_icon('plus') ?>Tilføj og lav en mere</button><?php endif; ?>
    <a class="btn btn-ghost" href="<?= e($edit ? lg_url(['side' => 'info', 'id' => $id]) : './') ?>">Annuller</a>
  </div>
</form>
<?php
    lg_end();
}

/* =========================================================
   Reserver
========================================================= */

function lg_page_reserve(array $d, array $ids): void
{
    $active = lg_active($d);
    $free = [];
    $busy = [];
    foreach ($ids as $id) {
        $i = lg_item_index($d, $id);
        if ($i === null) continue;
        if (isset($active[$id])) $busy[] = $d['enheder'][$i]; else $free[] = $d['enheder'][$i];
    }
    global $me;
    $start = (int)ceil(time() / 900) * 900;
    $fra = lg_old('fra', date('Y-m-d\TH:i', $start));
    $til = lg_old('til', date('Y-m-d\TH:i', $start + 86400));

    lg_start('Reserver', 'inventar');
    echo '<a class="back" href="./">' . lg_icon('left') . 'Inventar</a>';
    lg_pagehead(count($free) === 1 ? 'Reserver ' . $free[0]['navn'] : 'Reserver ' . count($free) . ' enheder');
    if (!$free) {
        echo '<div class="empty">' . lg_icon('alert', 'empty-ico') . '<h2>Intet at reservere</h2><p>De valgte enheder er allerede reserveret.</p><a class="btn btn-primary" href="./">Til inventaret</a></div>';
        lg_end();
        return;
    }
    ?>
<div class="lg-reserve">
  <section class="card">
    <h2 class="card-title"><?= count($free) === 1 ? 'Enhed' : 'Enheder (' . count($free) . ')' ?></h2>
    <ul class="lg-picked">
      <?php foreach ($free as $e): ?><li><span class="lg-sku-pill"><?= e($e['sku']) ?></span><span><?= e($e['navn']) ?></span><?php if ($e['placering'] !== ''): ?><span class="muted small"><?= e($e['placering']) ?></span><?php endif; ?></li><?php endforeach; ?>
    </ul>
    <?php if ($busy): ?>
    <p class="banner banner-warn"><?= lg_icon('alert') ?><span><?= count($busy) ?> af de valgte er allerede reserveret og springes over: <?= e(implode(', ', array_map(function ($x) { return $x['navn']; }, $busy))) ?>.</span></p>
    <?php endif; ?>
  </section>

  <form method="post" class="card lg-form" id="lg-reserve-form">
    <?= lg_csrf() ?>
    <input type="hidden" name="handling" value="reserver">
    <?php foreach ($free as $e): ?><input type="hidden" name="ids[]" value="<?= (int)$e['id'] ?>"><?php endforeach; ?>
    <div class="field"><span class="label">Reserveres af</span>
      <div class="lg-person"><span class="avatar avatar-sm"><?= e(lg_initials($me['navn'])) ?></span><strong><?= e($me['navn']) ?></strong></div></div>
    <div class="field"><label class="label" for="f-note">Hvad skal det bruges til?<?= count($free) > 1 ? '' : ' <span class="muted">(valgfri)</span>' ?></label>
      <textarea class="input textarea" id="f-note" name="note" rows="3"<?= count($free) > 1 ? ' required' : '' ?> placeholder="fx Prøve på Final Call"><?= e(lg_old('note')) ?></textarea></div>
    <div class="grid-2">
      <div class="field"><label class="label" for="f-fra">Fra</label><input class="input" type="datetime-local" id="f-fra" name="fra" required value="<?= e($fra) ?>"></div>
      <div class="field"><label class="label" for="f-til">Til</label><input class="input" type="datetime-local" id="f-til" name="til" required value="<?= e($til) ?>">
        <div class="chips lg-quick" aria-label="Hurtigvalg">
          <button type="button" class="chip" data-add="1">1 dag</button><button type="button" class="chip" data-add="3">3 dage</button><button type="button" class="chip" data-add="7">1 uge</button><button type="button" class="chip" data-add="14">2 uger</button>
        </div></div>
    </div>
    <p class="form-error" id="lg-date-error" hidden>“Til” skal ligge efter “Fra”.</p>
    <div class="lg-row-actions">
      <button type="submit" class="btn btn-primary"><?= lg_icon('calendar') ?><?= count($free) === 1 ? 'Reserver' : 'Reserver ' . count($free) . ' enheder' ?></button>
      <a class="btn btn-ghost" href="./">Annuller</a>
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
    $names = [];
    foreach ($d['enheder'] as $e) $names[(int)$e['id']] = $e;
    $hist = $d['reservationer'];
    usort($hist, function ($a, $b) { return strcmp($b['oprettet'], $a['oprettet']); });
    $hist = array_slice($hist, 0, 1500);
    $n = ['aktiv' => 0, 'overskredet' => 0, 'returneret' => 0];
    foreach ($hist as $r) {
        if ($r['status'] === 'aktiv') $n[lg_overdue($r) ? 'overskredet' : 'aktiv']++; else $n['returneret']++;
    }

    lg_start('Historik', 'historik');
    lg_pagehead('Historik', 'Alle reservationer – de nyeste først.');
    if (!$hist) {
        echo '<div class="empty">' . lg_icon('history', 'empty-ico') . '<h2>Ingen reservationer endnu</h2><p>Når nogen reserverer udstyr, kan du se det her.</p></div>';
        lg_end();
        return;
    }
    ?>
<section class="card lg-toolbar" aria-label="Søg og filtrér">
  <label class="lg-search"><?= lg_icon('search') ?><span class="sr">Søg</span>
    <input type="search" id="lg-hq" placeholder="Søg på enhed, ID eller person" autocomplete="off"></label>
  <div class="chips" data-hfilter role="group" aria-label="Status">
    <button type="button" class="chip on" data-value="">Alle</button>
    <button type="button" class="chip" data-value="aktiv">Aktive (<?= $n['aktiv'] + $n['overskredet'] ?>)</button>
    <?php if ($n['overskredet']): ?><button type="button" class="chip chip-danger" data-value="overskredet">Overskredne (<?= $n['overskredet'] ?>)</button><?php endif; ?>
    <button type="button" class="chip" data-value="returneret">Returnerede (<?= $n['returneret'] ?>)</button>
  </div>
</section>
<div class="card lg-table-card">
  <table class="lg-table lg-history" id="lg-historik">
    <thead><tr><th>Enhed</th><th>Reserveret af</th><th>Fra</th><th>Til</th><th>Status</th></tr></thead>
    <tbody>
    <?php foreach ($hist as $r):
        $e = $names[(int)$r['enhed']] ?? null;
        $st = $r['status'] === 'aktiv' ? (lg_overdue($r) ? 'overskredet' : 'aktiv') : 'returneret';
        [$label, $kind] = ['aktiv' => ['Aktiv', 'warn'], 'overskredet' => ['Overskredet', 'danger'], 'returneret' => ['Returneret', 'muted']][$st];
        $search = ($e ? $e['sku'] . ' ' . $e['navn'] : '') . ' ' . $r['navn'] . ' ' . $r['note'];
    ?>
      <tr data-status="<?= $st ?>" data-search="<?= e(sc_lower($search)) ?>">
        <td class="lg-name"><?php if ($e): ?><span class="lg-sku-pill"><?= e($e['sku']) ?></span> <a href="<?= e(lg_url(['side' => 'info', 'id' => $e['id']])) ?>"><?= e($e['navn']) ?></a><?php else: ?><span class="muted">Slettet enhed</span><?php endif; ?>
          <?php if ($r['note'] !== ''): ?><span class="lg-who"><?= e(sc_short($r['note'], 90)) ?></span><?php endif; ?></td>
        <td data-label="Af"><?= e($r['navn']) ?></td>
        <td data-label="Fra"><?= e(lg_dato($r['fra'])) ?></td>
        <td data-label="Til"><?= e(lg_dato($r['til'])) ?></td>
        <td class="lg-status"><span class="badge badge-<?= $kind ?>"><?= $label ?></span></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <p class="lg-none" id="lg-none" hidden>Ingen reservationer passer til søgningen.</p>
</div>
<?php
    lg_end();
}

/* =========================================================
   Indstillinger
========================================================= */

function lg_page_settings(array $d): void
{
    lg_start('Indstillinger', 'indstillinger');
    lg_pagehead('Indstillinger', 'Sektioner, import og eksport.');
    ?>
<section class="card">
  <h2 class="card-title">Sektioner</h2>
  <p class="card-desc">Sektionen er første del af en enheds ID (fx <strong>LYD</strong>-012) og bruges til at filtrere inventaret.</p>
  <?php if ($d['sektioner']): ?>
  <ul class="lg-sections">
    <?php foreach ($d['sektioner'] as $s): ?>
    <li><span><?= e($s) ?></span>
      <form method="post" data-confirm="Fjern sektionen <?= e($s) ?>? Enhederne bliver ikke slettet." data-danger><?= lg_csrf() ?><input type="hidden" name="handling" value="sektion-slet"><input type="hidden" name="navn" value="<?= e($s) ?>">
        <button type="submit" class="icon-btn danger" aria-label="Fjern <?= e($s) ?>" title="Fjern"><?= lg_icon('x') ?></button></form></li>
    <?php endforeach; ?>
  </ul>
  <?php else: ?><p class="muted">Der er ingen sektioner endnu.</p><?php endif; ?>
  <form method="post" class="lg-inline-form"><?= lg_csrf() ?><input type="hidden" name="handling" value="sektion-ny">
    <label class="sr" for="f-sek-ny">Ny sektion</label>
    <input class="input" id="f-sek-ny" name="navn" maxlength="30" required placeholder="fx VIDEO" value="<?= e(lg_old('navn')) ?>">
    <button type="submit" class="btn btn-secondary"><?= lg_icon('plus') ?>Tilføj sektion</button>
  </form>
</section>

<section class="card">
  <h2 class="card-title">Importér fra Excel</h2>
  <p class="card-desc">Tilføj mange enheder på én gang. Filen skal have kolonnerne <strong>ID</strong>, <strong>Navn</strong>, <strong>Placering</strong> og eventuelt <strong>Beskrivelse</strong> – i den rækkefølge. Enheder med et ID, der allerede findes, springes over.</p>
  <form method="post" enctype="multipart/form-data" class="lg-inline-form"><?= lg_csrf() ?><input type="hidden" name="handling" value="import">
    <label class="sr" for="f-fil">Fil</label>
    <input class="input lg-file" id="f-fil" type="file" name="fil" accept=".xlsx,.csv" required>
    <button type="submit" class="btn btn-primary"><?= lg_icon('upload') ?>Importér</button>
  </form>
</section>

<section class="card">
  <h2 class="card-title">Eksportér</h2>
  <p class="card-desc">Hent hele lageret som en fil, der kan åbnes i Excel – med status og hvem der har tingene lige nu.</p>
  <form method="post"><?= lg_csrf() ?><input type="hidden" name="handling" value="eksport">
    <button type="submit" class="btn btn-secondary"><?= lg_icon('download') ?>Hent som Excel-fil (<?= count($d['enheder']) ?> enheder)</button>
  </form>
</section>
<?php
    lg_end();
}

/* =========================================================
   Brugere (administratorer) – virker som i editoren
========================================================= */

function lg_page_users(): void
{
    global $me;
    $users = lg_users();
    usort($users, function ($a, $b) { return strcasecmp($a['navn'], $b['navn']); });
    $creds = $_SESSION['lg_creds'] ?? null;
    unset($_SESSION['lg_creds']);

    lg_start('Brugere', 'brugere');
    lg_pagehead('Brugere', 'Alle, der kan logge ind på lageret.');
    ?>
<section class="card">
  <ul class="user-list">
    <?php foreach ($users as $u): $self = strcasecmp($u['brugernavn'], $me['brugernavn']) === 0; ?>
    <li class="user">
      <span class="avatar"><?= e(lg_initials($u['navn'])) ?></span>
      <div class="user-text">
        <strong><?= e($u['navn']) ?><?= $self ? ' <span class="muted">(dig)</span>' : '' ?></strong>
        <span class="muted">Brugernavn: <?= e($u['brugernavn']) ?> · <?= $u['sidstLoggetInd'] ? 'Sidst logget ind ' . e(lg_dato($u['sidstLoggetInd'])) : 'Har ikke logget ind endnu' ?></span>
      </div>
      <div class="user-tools">
        <?php if ($self): ?>
          <span class="badge <?= $u['rolle'] === 'admin' ? 'badge-accent' : '' ?>"><?= $u['rolle'] === 'admin' ? 'Administrator' : 'Bruger' ?></span>
        <?php else: ?>
          <form method="post" data-autosubmit><?= lg_csrf() ?><input type="hidden" name="handling" value="bruger-rolle"><input type="hidden" name="brugernavn" value="<?= e($u['brugernavn']) ?>">
            <select class="input select select-sm" name="rolle" aria-label="Rolle for <?= e($u['navn']) ?>">
              <option value="bruger"<?= $u['rolle'] !== 'admin' ? ' selected' : '' ?>>Bruger</option>
              <option value="admin"<?= $u['rolle'] === 'admin' ? ' selected' : '' ?>>Administrator</option>
            </select>
            <noscript><button class="btn btn-ghost btn-sm" type="submit">Gem</button></noscript>
          </form>
          <form method="post" data-confirm="<?= e($u['navn']) ?> får et nyt kodeord, og det gamle holder op med at virke."><?= lg_csrf() ?><input type="hidden" name="handling" value="bruger-kodeord"><input type="hidden" name="brugernavn" value="<?= e($u['brugernavn']) ?>">
            <button class="btn btn-ghost btn-sm" type="submit"><?= lg_icon('key') ?>Nyt kodeord</button></form>
          <form method="post" data-confirm="Slet <?= e($u['navn']) ?>? De kan ikke længere logge ind. Deres reservationer bliver i historikken." data-danger><?= lg_csrf() ?><input type="hidden" name="handling" value="bruger-slet"><input type="hidden" name="brugernavn" value="<?= e($u['brugernavn']) ?>">
            <button class="icon-btn danger" type="submit" aria-label="Slet <?= e($u['navn']) ?>" title="Slet bruger"><?= lg_icon('trash') ?></button></form>
        <?php endif; ?>
      </div>
    </li>
    <?php endforeach; ?>
  </ul>
</section>

<section class="card">
  <h2 class="card-title">Tilføj en person</h2>
  <p class="card-desc">Opret et login. Bagefter får du en besked, du kan sende til personen.</p>
  <form method="post" class="stack" id="lg-new-user"><?= lg_csrf() ?><input type="hidden" name="handling" value="bruger-ny">
    <div class="grid-2">
      <div class="field"><label class="label" for="u-navn">Navn</label><input class="input" id="u-navn" name="navn" required placeholder="fx Anna Hansen" value="<?= e(lg_old('navn')) ?>" autocomplete="off"></div>
      <div class="field"><label class="label" for="u-bn">Brugernavn</label><input class="input" id="u-bn" name="brugernavn" required placeholder="fx anna" value="<?= e(lg_old('brugernavn')) ?>" autocomplete="off" autocapitalize="none" spellcheck="false"><p class="help">Små bogstaver, uden mellemrum.</p></div>
      <div class="field-with-btn"><div class="field"><label class="label" for="u-pw">Midlertidigt kodeord</label><input class="input" id="u-pw" name="kodeord" required value="<?= e(lg_make_password()) ?>" autocomplete="off" spellcheck="false"><p class="help">Personen kan selv skifte det under “Min konto”.</p></div>
        <button type="button" class="btn btn-ghost btn-sm" id="u-pw-new"><?= lg_icon('spark') ?>Lav et nyt</button></div>
      <div class="field"><label class="label" for="u-rolle">Rolle</label>
        <select class="input select" id="u-rolle" name="rolle">
          <option value="bruger">Bruger – kan se, reservere og rette udstyr</option>
          <option value="admin"<?= lg_old('rolle') === 'admin' ? ' selected' : '' ?>>Administrator – kan også styre brugere</option>
        </select></div>
    </div>
    <div><button type="submit" class="btn btn-primary"><?= lg_icon('plus') ?>Opret bruger</button></div>
  </form>
</section>
<?php
    if ($creds) {
        $host = preg_replace('~[^A-Za-z0-9.\-:\[\]]~', '', $_SERVER['HTTP_HOST'] ?? 'localhost');
        $url = (sc_is_https() ? 'https' : 'http') . '://' . $host . rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/lager/index.php')), '/') . '/';
        $text = 'Hej ' . explode(' ', $creds['navn'])[0] . "!\n\nDu kan nu bruge Teater Scenarios lager her:\n$url\n\nBrugernavn: {$creds['brugernavn']}\nKodeord: {$creds['kodeord']}\n\nSkift gerne kodeordet under “Min konto”, når du er logget ind.";
        ?>
<dialog class="modal" id="lg-creds" data-autoopen aria-labelledby="lg-creds-title">
  <div class="modal-head"><h2 id="lg-creds-title">Login er klar</h2></div>
  <div class="modal-body stack">
    <p>Send denne besked til <?= e($creds['navn']) ?>, fx på sms eller mail. Kodeordet bliver ikke vist igen.</p>
    <pre class="creds" id="lg-creds-text"><?= e($text) ?></pre>
  </div>
  <div class="modal-actions">
    <button type="button" class="btn btn-secondary" value="luk">Luk</button>
    <button type="button" class="btn btn-primary" id="lg-copy"><?= lg_icon('copy') ?><span>Kopiér beskeden</span></button>
  </div>
</dialog>
<?php
    }
    lg_end();
}

function lg_page_account(): void
{
    global $me;
    lg_start('Min konto', 'konto');
    lg_pagehead('Min konto', 'Du er logget ind som ' . e($me['brugernavn']) . ' (' . ($me['rolle'] === 'admin' ? 'administrator' : 'bruger') . ').');
    ?>
<section class="card">
  <h2 class="card-title">Navn</h2>
  <form method="post" class="stack"><?= lg_csrf() ?><input type="hidden" name="handling" value="min-konto">
    <div class="field"><label class="label" for="k-navn">Dit navn</label><input class="input" id="k-navn" name="navn" required autocomplete="name" value="<?= e(lg_old('navn', $me['navn'])) ?>"><p class="help">Vises, når du reserverer noget.</p></div>
    <div><button class="btn btn-secondary" type="submit">Gem navn</button></div>
  </form>
</section>
<section class="card">
  <h2 class="card-title">Skift kodeord</h2>
  <form method="post" class="stack"><?= lg_csrf() ?><input type="hidden" name="handling" value="min-konto">
    <div class="field"><label class="label" for="k-pw">Nuværende kodeord</label><input class="input" id="k-pw" name="kodeord" type="password" required autocomplete="current-password"></div>
    <div class="grid-2">
      <div class="field"><label class="label" for="k-pw1">Nyt kodeord</label><input class="input" id="k-pw1" name="nytKodeord" type="password" required autocomplete="new-password"><p class="help">Mindst 8 tegn.</p></div>
      <div class="field"><label class="label" for="k-pw2">Gentag nyt kodeord</label><input class="input" id="k-pw2" name="gentag" type="password" required autocomplete="new-password"></div>
    </div>
    <div><button class="btn btn-secondary" type="submit"><?= lg_icon('key') ?>Skift kodeord</button></div>
  </form>
</section>
<?php
    lg_end();
}
