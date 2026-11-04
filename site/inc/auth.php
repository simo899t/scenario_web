<?php
/* Login, brugere og sikkerhed til redigeringsværktøjet. */

require_once __DIR__ . '/lib.php';

const SC_MAX_FORSOEG = 8;          // forkerte logins før midlertidig spærring
const SC_SPAERRETID = 15 * 60;     // sekunder
const SC_INAKTIV_GRAENSE = 12 * 3600; // log ud efter 12 timers inaktivitet

class ScApiError extends Exception
{
    public $status;
    public $extra;

    public function __construct(string $msg, int $status = 400, array $extra = [])
    {
        parent::__construct($msg);
        $this->status = $status;
        $this->extra = $extra;
    }
}

/** Starter en session. Editoren og lageret har hver deres (navn og cookie-sti). */
function sc_session_start(string $name = 'scenario_redaktoer'): void
{
    if (session_status() === PHP_SESSION_ACTIVE) return;
    $path = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/admin/api.php')), '/') . '/';
    @ini_set('session.use_strict_mode', '1');
    @ini_set('session.use_only_cookies', '1');
    @ini_set('session.gc_maxlifetime', (string)SC_INAKTIV_GRAENSE);
    session_name($name);
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => $path,
        'secure' => sc_is_https(),
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();

    if ((!empty($_SESSION['bruger']) || !empty($_SESSION['lg_bruger'])) && time() - (int)($_SESSION['sidst'] ?? 0) > SC_INAKTIV_GRAENSE) {
        $_SESSION = [];
        session_regenerate_id(true);
    }
    $_SESSION['sidst'] = time();
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

function sc_csrf_check(): void
{
    $sent = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!is_string($sent) || !hash_equals($_SESSION['csrf'] ?? '', $sent)) {
        throw new ScApiError('Sikkerhedstjekket fejlede. Genindlæs siden og prøv igen.', 403);
    }
}

/* ---------- Brugere ---------- */

function sc_users(): array
{
    $d = sc_data_read('brugere');
    return isset($d['brugere']) && is_array($d['brugere']) ? $d['brugere'] : [];
}

function sc_users_save(array $users): void
{
    sc_data_write('brugere', ['brugere' => array_values($users)]);
}

function sc_user_find(array $users, string $username): ?int
{
    foreach ($users as $i => $u) {
        if (strcasecmp($u['brugernavn'], $username) === 0) return $i;
    }
    return null;
}

function sc_user_public(array $u): array
{
    return [
        'brugernavn' => $u['brugernavn'],
        'navn' => $u['navn'],
        'rolle' => $u['rolle'],
        'oprettet' => $u['oprettet'] ?? '',
        'sidstLoggetInd' => $u['sidstLoggetInd'] ?? '',
    ];
}

/** Den bruger, der er logget ind (læses frisk fra filen, så slettede brugere ryger ud med det samme). */
function sc_current_user(): ?array
{
    $name = $_SESSION['bruger'] ?? '';
    if ($name === '') return null;
    $users = sc_users();
    $i = sc_user_find($users, $name);
    if ($i === null) {
        unset($_SESSION['bruger']);
        return null;
    }
    return $users[$i];
}

function sc_require_user(): array
{
    $u = sc_current_user();
    if (!$u) throw new ScApiError('Du er blevet logget ud. Log ind igen for at fortsætte.', 401);
    return $u;
}

function sc_require_admin(): array
{
    $u = sc_require_user();
    if ($u['rolle'] !== 'admin') throw new ScApiError('Kun administratorer kan gøre det her.', 403);
    return $u;
}

function sc_validate_username($v): string
{
    $v = strtolower(trim((string)$v));
    if (!preg_match('~^[a-z0-9._\-]{2,40}$~', $v)) {
        throw new ScApiError('Brugernavnet skal være 2–40 tegn og må kun indeholde små bogstaver (a–z), tal, punktum, bindestreg og understreg.');
    }
    return $v;
}

function sc_validate_password($v): string
{
    $v = (string)$v;
    $len = function_exists('mb_strlen') ? mb_strlen($v) : strlen($v);
    if ($len < 8) throw new ScApiError('Kodeordet skal være på mindst 8 tegn.');
    if ($len > 200) throw new ScApiError('Kodeordet er for langt.');
    return $v;
}

function sc_validate_name($v): string
{
    $v = sc_line($v, 80);
    if ($v === '') throw new ScApiError('Skriv et navn.');
    return $v;
}

/* ---------- Beskyttelse mod gætteri ---------- */

function sc_login_key(): string
{
    return hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . '|scenario');
}

function sc_login_attempts(): array
{
    $d = sc_data_read('loginforsoeg');
    return is_array($d) ? $d : [];
}

function sc_login_blocked(): int
{
    $list = sc_login_attempts()[sc_login_key()] ?? [];
    $recent = array_filter($list, function ($t) { return $t > time() - SC_SPAERRETID; });
    if (count($recent) < SC_MAX_FORSOEG) return 0;
    return (int)ceil((min($recent) + SC_SPAERRETID - time()) / 60);
}

function sc_login_register(bool $ok): void
{
    sc_locked(function () use ($ok) {
        $all = sc_login_attempts();
        $key = sc_login_key();
        foreach ($all as $k => $list) {
            $all[$k] = array_values(array_filter((array)$list, function ($t) { return $t > time() - SC_SPAERRETID; }));
            if (!$all[$k]) unset($all[$k]);
        }
        if ($ok) unset($all[$key]);
        else $all[$key][] = time();
        sc_data_write('loginforsoeg', $all);
    });
}

/* ---------- Sikkerhedskopier ---------- */

const SC_MAX_BACKUPS = 100;

function sc_backup_dir(): string
{
    return SC_DATA . '/backups';
}

function sc_backup_write(array $content, string $label = ''): void
{
    $dir = sc_backup_dir();
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    $id = date('Ymd-His') . '-' . bin2hex(random_bytes(3));
    sc_write_guarded($dir . '/' . $id . '.php', [
        'gemt' => $content['meta']['gemt'] ?? '',
        'af' => $content['meta']['af'] ?? '',
        'note' => $label,
        'indhold' => $content,
    ]);
    $files = glob($dir . '/*.php') ?: [];
    rsort($files);
    foreach (array_slice($files, SC_MAX_BACKUPS) as $old) @unlink($old);
}

function sc_backup_list(): array
{
    $files = glob(sc_backup_dir() . '/*.php') ?: [];
    rsort($files);
    $out = [];
    foreach ($files as $f) {
        $d = sc_read_guarded($f);
        if (!$d) continue;
        $ind = $d['indhold'] ?? [];
        $out[] = [
            'id' => basename($f, '.php'),
            'gemt' => $d['gemt'] ?? '',
            'af' => $d['af'] ?? '',
            'note' => $d['note'] ?? '',
            'forestillinger' => is_array($ind['events'] ?? null) ? count($ind['events']) : 0,
            'sponsorer' => is_array($ind['sponsorer'] ?? null) ? count($ind['sponsorer']) : 0,
        ];
    }
    return $out;
}

function sc_backup_read(string $id): ?array
{
    if (!preg_match('~^[0-9]{8}-[0-9]{6}-[a-f0-9]{6}$~', $id)) return null;
    $d = sc_read_guarded(sc_backup_dir() . '/' . $id . '.php');
    return $d && isset($d['indhold']) && is_array($d['indhold']) ? $d['indhold'] : null;
}

/* ---------- Billeder ---------- */

function sc_ini_bytes(string $v): int
{
    $v = trim($v);
    if ($v === '' || $v === '-1') return PHP_INT_MAX;
    $n = (int)$v;
    switch (strtolower(substr($v, -1))) {
        case 'g': $n *= 1024;
        // fall through
        case 'm': $n *= 1024;
        // fall through
        case 'k': $n *= 1024;
    }
    return $n;
}

function sc_max_upload(): int
{
    return (int)min(sc_ini_bytes((string)ini_get('upload_max_filesize')), sc_ini_bytes((string)ini_get('post_max_size')), 25 * 1024 * 1024);
}

/**
 * Gemmer et uploadet billede i uploads/ÅÅÅÅ/. Billedet bliver drejet rigtigt,
 * gjort mindre og gemt som WebP (eller JPG/PNG), så siden loader hurtigt.
 */
function sc_handle_upload(array $file, string $kind): array
{
    $err = $file['error'] ?? UPLOAD_ERR_NO_FILE;
    if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) {
        throw new ScApiError('Billedet er for stort. Serveren tager højst ' . round(sc_max_upload() / 1048576) . ' MB.');
    }
    if ($err === UPLOAD_ERR_NO_FILE) throw new ScApiError('Vælg et billede først.');
    if ($err !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
        throw new ScApiError('Billedet kunne ikke uploades (fejlkode ' . (int)$err . '). Prøv igen.');
    }
    $tmp = $file['tmp_name'];
    $info = @getimagesize($tmp);
    $types = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_GIF => 'gif'];
    if (defined('IMAGETYPE_WEBP')) $types[IMAGETYPE_WEBP] = 'webp';
    if (!$info || !isset($types[$info[2]])) {
        throw new ScApiError('Filen er ikke et billede, vi kan bruge. Brug JPG, PNG eller WebP.');
    }
    [$w, $h, $type] = $info;
    if ($w * $h > 50000000) throw new ScApiError('Billedet har for høj opløsning. Gør det mindre og prøv igen.');

    $year = date('Y');
    $dir = SC_UPLOADS . '/' . $year;
    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
        throw new ScApiError('Serveren kan ikke gemme billeder i mappen uploads/. Tjek skriveadgangen.', 500);
    }
    $base = sc_slug(pathinfo((string)($file['name'] ?? ''), PATHINFO_FILENAME));
    $base = substr($base !== '' ? $base : 'billede', 0, 40) . '-' . bin2hex(random_bytes(3));
    $max = $kind === 'logo' ? 800 : 2400;

    $loaders = [IMAGETYPE_JPEG => 'imagecreatefromjpeg', IMAGETYPE_PNG => 'imagecreatefrompng', IMAGETYPE_GIF => 'imagecreatefromgif'];
    if (defined('IMAGETYPE_WEBP')) $loaders[IMAGETYPE_WEBP] = 'imagecreatefromwebp';
    $loader = $loaders[$type] ?? '';

    if ($loader !== '' && function_exists($loader) && function_exists('imagecreatetruecolor')) {
        @ini_set('memory_limit', '512M');
        $src = @$loader($tmp);
        if (!$src) throw new ScApiError('Billedet kunne ikke læses. Prøv at gemme det som JPG og upload igen.');

        if ($type === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
            $exif = @exif_read_data($tmp);
            $rot = [3 => 180, 6 => -90, 8 => 90][(int)($exif['Orientation'] ?? 1)] ?? 0;
            if ($rot) {
                $r = imagerotate($src, $rot, 0);
                if ($r) { imagedestroy($src); $src = $r; }
            }
        }
        $sw = imagesx($src);
        $sh = imagesy($src);
        $scale = min(1, $max / max($sw, $sh));
        $dw = max(1, (int)round($sw * $scale));
        $dh = max(1, (int)round($sh * $scale));
        $dst = imagecreatetruecolor($dw, $dh);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 0, 0, 0, 127));
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $dw, $dh, $sw, $sh);
        imagedestroy($src);

        $hasAlpha = $type !== IMAGETYPE_JPEG;
        if (function_exists('imagewebp')) {
            $ext = 'webp';
            $ok = imagewebp($dst, "$dir/$base.webp", 82);
        } elseif ($hasAlpha) {
            $ext = 'png';
            $ok = imagepng($dst, "$dir/$base.png", 8);
        } else {
            $ext = 'jpg';
            imageinterlace($dst, true);
            $ok = imagejpeg($dst, "$dir/$base.jpg", 85);
        }
        imagedestroy($dst);
        if (!$ok) throw new ScApiError('Billedet kunne ikke gemmes på serveren.', 500);
        return ['sti' => "uploads/$year/$base.$ext", 'bredde' => $dw, 'hoejde' => $dh];
    }

    // Serveren har ikke GD – gem originalen (den er allerede tjekket som et ægte billede)
    $ext = $types[$type];
    if (!move_uploaded_file($tmp, "$dir/$base.$ext")) throw new ScApiError('Billedet kunne ikke gemmes på serveren.', 500);
    @chmod("$dir/$base.$ext", 0644);
    return ['sti' => "uploads/$year/$base.$ext", 'bredde' => $w, 'hoejde' => $h];
}

/** Sletter et billede fra uploads/ – men kun hvis det ikke bruges på hjemmesiden. */
function sc_delete_upload(string $path): void
{
    $sti = sc_image_path($path);
    if ($sti === '' || strpos($sti, 'uploads/') !== 0) {
        throw new ScApiError('Kun billeder, der er uploadet i editoren, kan slettes.');
    }
    $base = realpath(SC_UPLOADS);
    $file = realpath(SC_ROOT . '/' . $sti);
    if (!$base || !$file || strpos($file, $base . DIRECTORY_SEPARATOR) !== 0 || !is_file($file)) {
        throw new ScApiError('Billedet findes ikke længere.', 404);
    }
    sc_locked(function () use ($sti, $file) {
        $raw = sc_data_read('indhold');
        $json = json_encode(sc_normalize(is_array($raw) ? $raw : sc_default_content()), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (strpos((string)$json, '"' . $sti . '"') !== false) {
            throw new ScApiError('Billedet bruges stadig på hjemmesiden. Fjern det dér og tryk “Gem og udgiv”, før du sletter det.', 409);
        }
        if (!@unlink($file)) throw new ScApiError('Billedet kunne ikke slettes. Prøv igen.', 500);
    });
}

function sc_media_list(): array
{
    $out = [];
    foreach (['uploads', 'billeder'] as $dir) {
        $base = SC_ROOT . '/' . $dir;
        if (!is_dir($base)) continue;
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if (!$f->isFile() || !preg_match('~\.(jpe?g|png|webp|gif|avif)$~i', $f->getFilename())) continue;
            $rel = $dir . '/' . str_replace('\\', '/', substr($f->getPathname(), strlen($base) + 1));
            if (sc_image_path($rel) === '') continue;
            $dim = @getimagesize($f->getPathname());
            $out[] = [
                'sti' => $rel, 'tid' => $f->getMTime(), 'mappe' => $dir, 'str' => $f->getSize(),
                'bredde' => $dim ? $dim[0] : 0, 'hoejde' => $dim ? $dim[1] : 0,
            ];
        }
    }
    usort($out, function ($a, $b) {
        if ($a['mappe'] !== $b['mappe']) return $a['mappe'] === 'uploads' ? -1 : 1;
        return $b['tid'] <=> $a['tid'];
    });
    return array_slice($out, 0, 600);
}
