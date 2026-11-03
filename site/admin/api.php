<?php
/*
 * API til redigeringsværktøjet. Alle svar er JSON.
 * Alle POST-kald kræver headeren X-CSRF-Token (fås fra ?action=status).
 */

require dirname(__DIR__) . '/inc/auth.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('X-Robots-Tag: noindex');

function sc_json($data, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function sc_input(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === '' || $raw === false) return [];
    $d = json_decode($raw, true);
    if (!is_array($d)) throw new ScApiError('Ugyldig forespørgsel.');
    return $d;
}

/** Indholdet som editoren ser det (gemt indhold eller standardindholdet). */
function sc_editor_content(): array
{
    $c = sc_data_read('indhold');
    return sc_normalize(is_array($c) ? $c : sc_default_content());
}

function sc_data_writable(): bool
{
    sc_ensure_data_dir();
    return is_dir(SC_DATA) && is_writable(SC_DATA);
}

try {
    sc_session_start();
    $action = (string)($_GET['action'] ?? '');
    $isPost = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
    if ($isPost) sc_csrf_check();
    $in = ($isPost && $action !== 'upload') ? sc_input() : [];

    switch ($action) {

        case 'status': {
            $u = sc_current_user();
            sc_json([
                'csrf' => $_SESSION['csrf'],
                'bruger' => $u ? sc_user_public($u) : null,
                'opsaetning' => !sc_users(),
                'skrivbar' => sc_data_writable() && (is_dir(SC_UPLOADS) ? is_writable(SC_UPLOADS) : is_writable(SC_ROOT)),
                'maksUpload' => sc_max_upload(),
                'php' => PHP_VERSION,
            ]);
        }

        case 'opsaetning': { // Første administrator – kun mulig, mens der ingen brugere er
            if (!$isPost) throw new ScApiError('Forkert metode.', 405);
            $user = sc_locked(function () use ($in) {
                if (sc_users()) throw new ScApiError('Der er allerede oprettet brugere. Log ind i stedet.', 409);
                $u = [
                    'brugernavn' => sc_validate_username($in['brugernavn'] ?? ''),
                    'navn' => sc_validate_name($in['navn'] ?? ''),
                    'rolle' => 'admin',
                    'hash' => password_hash(sc_validate_password($in['kodeord'] ?? ''), PASSWORD_DEFAULT),
                    'oprettet' => date('c'),
                    'sidstLoggetInd' => date('c'),
                ];
                sc_users_save([$u]);
                return $u;
            });
            session_regenerate_id(true);
            $_SESSION['bruger'] = $user['brugernavn'];
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
            sc_json(['bruger' => sc_user_public($user), 'csrf' => $_SESSION['csrf']]);
        }

        case 'login': {
            if (!$isPost) throw new ScApiError('Forkert metode.', 405);
            $min = sc_login_blocked();
            if ($min > 0) throw new ScApiError("For mange forkerte forsøg. Vent $min minutter og prøv igen.", 429);
            $users = sc_users();
            $i = sc_user_find($users, trim((string)($in['brugernavn'] ?? '')));
            $pw = (string)($in['kodeord'] ?? '');
            // Tjek altid et kodeord, så svartiden ikke afslører om brugeren findes
            $hash = $i !== null ? $users[$i]['hash'] : '$2y$10$abcdefghijklmnopqrstuuJ3ZVd0pN4sUiOJXWvC0iCpZ6aD8Wq6e';
            if (!password_verify($pw, $hash) || $i === null) {
                sc_login_register(false);
                usleep(400000);
                throw new ScApiError('Forkert brugernavn eller kodeord.', 401);
            }
            sc_login_register(true);
            $user = sc_locked(function () use ($i, $pw) {
                $users = sc_users();
                if (!isset($users[$i])) throw new ScApiError('Brugeren findes ikke længere.', 401);
                if (password_needs_rehash($users[$i]['hash'], PASSWORD_DEFAULT)) {
                    $users[$i]['hash'] = password_hash($pw, PASSWORD_DEFAULT);
                }
                $users[$i]['sidstLoggetInd'] = date('c');
                sc_users_save($users);
                return $users[$i];
            });
            session_regenerate_id(true);
            $_SESSION['bruger'] = $user['brugernavn'];
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
            sc_json(['bruger' => sc_user_public($user), 'csrf' => $_SESSION['csrf']]);
        }

        case 'logout': {
            $_SESSION = [];
            session_regenerate_id(true);
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
            sc_json(['ok' => true, 'csrf' => $_SESSION['csrf']]);
        }

        case 'ping': {
            sc_require_user();
            sc_json(['ok' => true]);
        }

        case 'indhold': {
            sc_require_user();
            sc_json(['indhold' => sc_editor_content()]);
        }

        case 'gem': {
            if (!$isPost) throw new ScApiError('Forkert metode.', 405);
            $u = sc_require_user();
            if (!isset($in['indhold']) || !is_array($in['indhold'])) throw new ScApiError('Der mangler indhold.');
            $base = (string)($in['version'] ?? '');
            $force = !empty($in['tving']);
            $saved = sc_locked(function () use ($in, $base, $force, $u) {
                $raw = sc_data_read('indhold');
                $current = sc_normalize(is_array($raw) ? $raw : sc_default_content());
                if (!$force && $current['meta']['version'] !== $base) {
                    throw new ScApiError('Nogen har gemt ændringer, mens du redigerede.', 409, [
                        'af' => $current['meta']['af'], 'gemt' => $current['meta']['gemt'],
                    ]);
                }
                sc_backup_write($current, is_array($raw) ? '' : 'Oprindeligt indhold');
                $new = sc_normalize($in['indhold']);
                $new['meta'] = ['version' => bin2hex(random_bytes(6)), 'gemt' => date('c'), 'af' => $u['navn']];
                sc_data_write('indhold', $new);
                return $new;
            });
            sc_json(['indhold' => $saved]);
        }

        case 'historik': {
            sc_require_user();
            sc_json(['nuvaerende' => sc_editor_content()['meta'], 'versioner' => sc_backup_list()]);
        }

        case 'gendan': {
            if (!$isPost) throw new ScApiError('Forkert metode.', 405);
            $u = sc_require_user();
            $id = (string)($in['id'] ?? '');
            $saved = sc_locked(function () use ($id, $u) {
                $old = sc_backup_read($id);
                if (!$old) throw new ScApiError('Versionen findes ikke.', 404);
                $raw = sc_data_read('indhold');
                sc_backup_write(sc_normalize(is_array($raw) ? $raw : sc_default_content()), is_array($raw) ? '' : 'Oprindeligt indhold');
                $new = sc_normalize($old);
                $when = isset($old['meta']['gemt']) && $old['meta']['gemt'] !== '' ? strtotime($old['meta']['gemt']) : false;
                $new['meta'] = [
                    'version' => bin2hex(random_bytes(6)),
                    'gemt' => date('c'),
                    'af' => $u['navn'] . ($when ? ' (gendannet fra ' . date('d.m.Y H:i', $when) . ')' : ' (gendannet)'),
                ];
                sc_data_write('indhold', $new);
                return $new;
            });
            sc_json(['indhold' => $saved]);
        }

        case 'upload': {
            if (!$isPost) throw new ScApiError('Forkert metode.', 405);
            sc_require_user();
            if (empty($_FILES['fil'])) {
                throw new ScApiError('Billedet er for stort eller nåede ikke frem. Serveren tager højst ' . round(sc_max_upload() / 1048576) . ' MB.');
            }
            $kind = ($_POST['type'] ?? '') === 'logo' ? 'logo' : 'billede';
            sc_json(sc_handle_upload($_FILES['fil'], $kind));
        }

        case 'billeder': {
            sc_require_user();
            sc_json(['billeder' => sc_media_list()]);
        }

        case 'brugere': {
            sc_require_admin();
            sc_json(['brugere' => array_map('sc_user_public', sc_users())]);
        }

        case 'gem-bruger': {
            if (!$isPost) throw new ScApiError('Forkert metode.', 405);
            $me = sc_require_admin();
            $list = sc_locked(function () use ($in, $me) {
                $users = sc_users();
                $rolle = ($in['rolle'] ?? '') === 'admin' ? 'admin' : 'redaktoer';
                if (!empty($in['ny'])) {
                    $name = sc_validate_username($in['brugernavn'] ?? '');
                    if (sc_user_find($users, $name) !== null) throw new ScApiError('Brugernavnet er allerede i brug.');
                    $users[] = [
                        'brugernavn' => $name,
                        'navn' => sc_validate_name($in['navn'] ?? ''),
                        'rolle' => $rolle,
                        'hash' => password_hash(sc_validate_password($in['kodeord'] ?? ''), PASSWORD_DEFAULT),
                        'oprettet' => date('c'),
                        'sidstLoggetInd' => '',
                    ];
                } else {
                    $i = sc_user_find($users, (string)($in['brugernavn'] ?? ''));
                    if ($i === null) throw new ScApiError('Brugeren findes ikke.', 404);
                    if (isset($in['navn'])) $users[$i]['navn'] = sc_validate_name($in['navn']);
                    if (isset($in['rolle'])) {
                        if (strcasecmp($users[$i]['brugernavn'], $me['brugernavn']) === 0 && $rolle !== 'admin') {
                            throw new ScApiError('Du kan ikke fjerne din egen administrator-adgang.');
                        }
                        $users[$i]['rolle'] = $rolle;
                    }
                    if (isset($in['kodeord']) && $in['kodeord'] !== '') {
                        $users[$i]['hash'] = password_hash(sc_validate_password($in['kodeord']), PASSWORD_DEFAULT);
                    }
                }
                sc_users_save($users);
                return $users;
            });
            sc_json(['brugere' => array_map('sc_user_public', $list)]);
        }

        case 'slet-bruger': {
            if (!$isPost) throw new ScApiError('Forkert metode.', 405);
            $me = sc_require_admin();
            $list = sc_locked(function () use ($in, $me) {
                $users = sc_users();
                $i = sc_user_find($users, (string)($in['brugernavn'] ?? ''));
                if ($i === null) throw new ScApiError('Brugeren findes ikke.', 404);
                if (strcasecmp($users[$i]['brugernavn'], $me['brugernavn']) === 0) throw new ScApiError('Du kan ikke slette dig selv.');
                array_splice($users, $i, 1);
                sc_users_save($users);
                return $users;
            });
            sc_json(['brugere' => array_map('sc_user_public', $list)]);
        }

        case 'min-konto': {
            if (!$isPost) throw new ScApiError('Forkert metode.', 405);
            $me = sc_require_user();
            $user = sc_locked(function () use ($in, $me) {
                $users = sc_users();
                $i = sc_user_find($users, $me['brugernavn']);
                if ($i === null) throw new ScApiError('Brugeren findes ikke.', 404);
                if (isset($in['navn'])) $users[$i]['navn'] = sc_validate_name($in['navn']);
                if (!empty($in['nytKodeord'])) {
                    if (!password_verify((string)($in['kodeord'] ?? ''), $users[$i]['hash'])) {
                        throw new ScApiError('Dit nuværende kodeord er forkert.');
                    }
                    $users[$i]['hash'] = password_hash(sc_validate_password($in['nytKodeord']), PASSWORD_DEFAULT);
                }
                sc_users_save($users);
                return $users[$i];
            });
            sc_json(['bruger' => sc_user_public($user)]);
        }

        default:
            throw new ScApiError('Ukendt handling.', 404);
    }
} catch (ScApiError $e) {
    sc_json(array_merge(['fejl' => $e->getMessage()], $e->extra), $e->status);
} catch (Throwable $e) {
    error_log('[Scenario admin] ' . $e->getMessage() . ' i ' . $e->getFile() . ':' . $e->getLine());
    $msg = $e instanceof RuntimeException ? $e->getMessage() : 'Der skete en uventet fejl på serveren. Prøv igen om lidt.';
    sc_json(['fejl' => $msg], 500);
}
