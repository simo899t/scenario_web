<?php
/*
 * Fælles funktioner for hjemmesiden og redigeringsværktøjet (admin/).
 * Indholdet ligger i data/indhold.php og redigeres via /admin.
 * Kræver PHP 7.4 eller nyere.
 */

if (defined('SC_ROOT')) return;

define('SC_ROOT', dirname(__DIR__));
define('SC_DATA', SC_ROOT . '/data');
define('SC_UPLOADS', SC_ROOT . '/uploads');
// Datafiler gemmes som .php med denne første linje, så de aldrig kan hentes i en browser.
define('SC_GUARD', "<?php exit; ?>\n");

date_default_timezone_set('Europe/Copenhagen');
if (function_exists('mb_internal_encoding')) mb_internal_encoding('UTF-8');

/* ---------------------------------------------------------------
   Små hjælpere
--------------------------------------------------------------- */

function e($s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function sc_substr(string $s, int $start, int $len): string
{
    return function_exists('mb_substr') ? mb_substr($s, $start, $len) : substr($s, $start, $len);
}

function sc_lower(string $s): string
{
    return function_exists('mb_strtolower') ? mb_strtolower($s) : strtolower($s);
}

function sc_asset(string $file): string
{
    $path = SC_ROOT . '/' . $file;
    return $file . (is_file($path) ? '?v=' . filemtime($path) : '');
}

function sc_is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
        || (($_SERVER['SERVER_PORT'] ?? '') === '443');
}

/** Absolut adresse til sidens rodmappe, fx https://teaterscenario.dk/ */
function sc_base_url(): string
{
    $host = preg_replace('~[^A-Za-z0-9.\-:\[\]]~', '', $_SERVER['HTTP_HOST'] ?? 'localhost');
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/index.php');
    $dir = rtrim(dirname($script), '/');
    if (basename($dir) === 'admin') $dir = rtrim(dirname($dir), '/');
    return (sc_is_https() ? 'https' : 'http') . '://' . $host . $dir . '/';
}

function sc_abs_url(string $path): string
{
    if ($path === '' || preg_match('~^https?://~i', $path)) return $path;
    return sc_base_url() . ltrim($path, '/');
}

/* ---------------------------------------------------------------
   Datafiler
--------------------------------------------------------------- */

function sc_ensure_data_dir(): void
{
    if (!is_dir(SC_DATA)) @mkdir(SC_DATA, 0755, true);
    if (!is_file(SC_DATA . '/.htaccess')) {
        @file_put_contents(SC_DATA . '/.htaccess', "<IfModule mod_authz_core.c>\n  Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n  Order allow,deny\n  Deny from all\n</IfModule>\n");
    }
    if (!is_file(SC_DATA . '/index.html')) @file_put_contents(SC_DATA . '/index.html', '');
}

function sc_data_file(string $name): string
{
    return SC_DATA . '/' . $name . '.php';
}

function sc_read_guarded(string $file)
{
    if (!is_file($file)) return null;
    $raw = @file_get_contents($file);
    if ($raw === false) return null;
    if (strncmp($raw, SC_GUARD, strlen(SC_GUARD)) === 0) $raw = substr($raw, strlen(SC_GUARD));
    $data = json_decode($raw, true);
    return is_array($data) ? $data : null;
}

function sc_write_guarded(string $file, array $data): void
{
    $dir = dirname($file);
    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
        throw new RuntimeException('Kan ikke oprette mappen ' . basename($dir) . '.');
    }
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) throw new RuntimeException('Indholdet kunne ikke omdannes til JSON.');
    $tmp = $file . '.' . bin2hex(random_bytes(4)) . '.tmp';
    if (@file_put_contents($tmp, SC_GUARD . $json, LOCK_EX) === false) {
        throw new RuntimeException('Serveren kan ikke skrive i mappen data/. Tjek at mappen findes og har skriveadgang.');
    }
    if (!@rename($tmp, $file)) {
        @unlink($file);
        if (!@rename($tmp, $file)) {
            @unlink($tmp);
            throw new RuntimeException('Filen kunne ikke gemmes.');
        }
    }
}

function sc_data_read(string $name)
{
    return sc_read_guarded(sc_data_file($name));
}

function sc_data_write(string $name, array $data): void
{
    sc_ensure_data_dir();
    sc_write_guarded(sc_data_file($name), $data);
}

/** Kører $fn med eneret på data-mappen, så to personer ikke gemmer oven i hinanden. */
function sc_locked(callable $fn)
{
    sc_ensure_data_dir();
    $h = @fopen(SC_DATA . '/.lock', 'c');
    if (!$h) return $fn();
    flock($h, LOCK_EX);
    try {
        return $fn();
    } finally {
        flock($h, LOCK_UN);
        fclose($h);
    }
}

/* ---------------------------------------------------------------
   Indhold: skema, standardindhold og rensning
--------------------------------------------------------------- */

/*
 * Skemaet beskriver alt indhold på siden. Det bruges til at rense alt,
 * der gemmes fra editoren, så siden altid får gyldige data.
 *   'line'  = én linje tekst       'text' / 'longtext' = tekst med afsnit
 *   'image' = sti til billede      'link' = webadresse
 *   [ ... ] = liste af elementer   [ 'felt' => type ] = objekt
 */
function sc_schema(): array
{
    return [
        'generelt' => ['sidenavn' => 'line', 'beskrivelse' => 'line'],
        'forside' => ['undertitel' => 'line', 'baggrund' => 'image', 'aktuelOverskrift' => 'line'],
        'om' => [
            'overskrift' => 'line', 'kort' => 'text', 'knap' => 'line',
            'afsnit' => [['overskrift' => 'line', 'undertitel' => 'line', 'tekst' => 'longtext']],
        ],
        'forestillingerTekst' => ['overskrift' => 'line', 'intro' => 'text'],
        'events' => [[
            'id' => 'slug', 'titel' => 'line', 'type' => 'line', 'datoer' => 'line', 'start' => 'datetime',
            'sted' => 'line', 'adresse' => 'line', 'spoergsmaal' => 'line',
            'tekst' => 'text', 'beskrivelse' => 'longtext',
            'billede' => 'image', 'galleri' => ['image'],
            'hold' => [['rolle' => 'line', 'navn' => 'line']],
            'video' => 'link', 'billetLink' => 'link', 'proeveplan' => 'link',
            'fremhaevet' => 'bool', 'skjult' => 'bool',
        ]],
        'medvirk' => ['overskrift' => 'line', 'tekst' => 'text', 'roller' => ['line'], 'knap' => 'line', 'link' => 'link'],
        'sponsorTekst' => ['overskrift' => 'line', 'tekst' => 'text'],
        'sponsorer' => [['id' => 'slug', 'navn' => 'line', 'logo' => 'image', 'link' => 'link', 'niveau' => 'niveau']],
        'kontakt' => [
            'overskrift' => 'line', 'tekst' => 'text', 'email' => 'email', 'telefon' => 'line',
            'adresse' => 'line', 'cvr' => 'line',
            'instagram' => 'link', 'facebook' => 'link', 'tiktok' => 'link', 'youtube' => 'link',
        ],
    ];
}

function sc_default_content(): array
{
    static $d = null;
    if ($d === null) {
        $d = json_decode((string)@file_get_contents(__DIR__ . '/standard-indhold.json'), true);
        if (!is_array($d)) $d = [];
    }
    return $d;
}

/** Alt indhold til hjemmesiden (renset og komplet). */
function sc_content(): array
{
    static $cache = null;
    if ($cache === null) {
        $c = sc_data_read('indhold');
        $cache = sc_normalize(is_array($c) ? $c : sc_default_content());
    }
    return $cache;
}

function sc_normalize(array $c): array
{
    $out = sc_norm($c, sc_schema(), sc_default_content());

    // Unikke id'er til forestillinger (bruges i adressen forestilling.php?id=...)
    $used = [];
    foreach ($out['events'] as $i => $ev) {
        $id = $ev['id'] !== '' ? $ev['id'] : sc_slug($ev['titel']);
        if ($id === '') $id = 'forestilling';
        $base = $id;
        $n = 2;
        while (isset($used[$id])) $id = $base . '-' . $n++;
        $used[$id] = true;
        $out['events'][$i]['id'] = $id;
    }
    // Kun én forestilling kan være fremhævet på forsiden
    $found = false;
    foreach ($out['events'] as $i => $ev) {
        if ($ev['fremhaevet'] && !$found) { $found = true; continue; }
        $out['events'][$i]['fremhaevet'] = false;
    }
    $used = [];
    foreach ($out['sponsorer'] as $i => $s) {
        $id = $s['id'] !== '' ? $s['id'] : (sc_slug($s['navn']) ?: 'sponsor');
        $base = $id;
        $n = 2;
        while (isset($used[$id])) $id = $base . '-' . $n++;
        $used[$id] = true;
        $out['sponsorer'][$i]['id'] = $id;
    }

    $meta = isset($c['meta']) && is_array($c['meta']) ? $c['meta'] : [];
    $out['meta'] = [
        'version' => sc_line($meta['version'] ?? '', 40),
        'gemt' => sc_line($meta['gemt'] ?? '', 40),
        'af' => sc_line($meta['af'] ?? '', 120),
    ];
    return $out;
}

function sc_norm($v, $schema, $def)
{
    if (is_string($schema)) return sc_scalar($v, $schema);

    if (array_key_exists(0, $schema)) { // liste
        $items = is_array($v) ? array_values($v) : (is_array($def) ? array_values($def) : []);
        $out = [];
        foreach (array_slice($items, 0, 300) as $item) {
            $n = sc_norm($item, $schema[0], null);
            if ($n === '' || $n === []) continue;
            $out[] = $n;
        }
        return $out;
    }

    $v = is_array($v) ? $v : [];
    $def = is_array($def) ? $def : [];
    $out = [];
    foreach ($schema as $key => $sub) {
        $val = array_key_exists($key, $v) ? $v[$key] : ($def[$key] ?? null);
        $out[$key] = sc_norm($val, $sub, $def[$key] ?? null);
    }
    return $out;
}

function sc_scalar($v, string $type)
{
    switch ($type) {
        case 'line': return sc_line($v, 300);
        case 'text': return sc_text($v, 6000);
        case 'longtext': return sc_text($v, 30000);
        case 'image': return sc_image_path($v);
        case 'link': return sc_link($v);
        case 'bool': return $v === true || $v === 1 || $v === '1' || $v === 'true';
        case 'slug': return sc_slug($v);
        case 'datetime':
            $v = sc_line($v, 30);
            if (preg_match('~^(\d{4}-\d{2}-\d{2})[T ](\d{2}:\d{2})~', $v, $m)) return $m[1] . 'T' . $m[2];
            if (preg_match('~^\d{4}-\d{2}-\d{2}$~', $v)) return $v . 'T00:00';
            return '';
        case 'email':
            $v = sc_line($v, 200);
            return filter_var($v, FILTER_VALIDATE_EMAIL) ? $v : '';
        case 'niveau':
            return in_array($v, ['hoved', 'sponsor', 'netvaerk'], true) ? $v : 'sponsor';
    }
    return '';
}

function sc_text($v, int $max): string
{
    if (is_int($v) || is_float($v)) $v = (string)$v;
    if (!is_string($v)) return '';
    $v = str_replace(["\r\n", "\r"], "\n", $v);
    $v = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $v);
    if ($v === null) return '';
    $v = preg_replace("/\n{3,}/", "\n\n", $v);
    return trim(sc_substr($v, 0, $max));
}

function sc_line($v, int $max = 300): string
{
    $v = sc_text($v, $max);
    return trim((string)preg_replace('/\s+/u', ' ', $v));
}

function sc_slug($v): string
{
    $v = sc_lower(sc_line($v, 120));
    $v = strtr($v, [
        'æ' => 'ae', 'ø' => 'oe', 'å' => 'aa', 'ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss',
        'é' => 'e', 'è' => 'e', 'ê' => 'e', 'á' => 'a', 'à' => 'a', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n',
    ]);
    if (function_exists('iconv')) {
        $t = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $v);
        if ($t !== false) $v = $t;
    }
    $v = (string)preg_replace('~[^a-z0-9]+~', '-', strtolower($v));
    return trim(substr(trim($v, '-'), 0, 60), '-');
}

/** Tillader kun billeder i billeder/ eller uploads/ – eller en https-adresse. */
function sc_image_path($v): string
{
    $v = sc_line($v, 500);
    if ($v === '') return '';
    if (preg_match('~^https?://[^\s\'"()<>\\\\]+$~i', $v)) return $v;
    $v = ltrim($v, './');
    if (strpos($v, '..') !== false) return '';
    if (preg_match('~^(billeder|uploads)/[\w\-./ ()æøåÆØÅ]+\.(jpe?g|png|webp|gif|avif)$~iu', $v)) return $v;
    return '';
}

/** Gør et link sikkert: https://, mailto:, tel: eller en side på hjemmesiden. */
function sc_link($v): string
{
    $v = sc_line($v, 1000);
    if ($v === '') return '';
    if (preg_match('~^(https?://|mailto:|tel:)[^\s<>"]*$~i', $v)) return $v;
    if (preg_match('~^[a-z][a-z0-9+.\-]*:~i', $v)) return ''; // fx javascript: afvises
    if (preg_match('~^[\w.+\-]+@[\w\-]+(\.[\w\-]+)+$~u', $v)) return 'mailto:' . $v;
    if (preg_match('~^(#|/|\./)?[\w\-]+\.php([?#][^\s<>"]*)?$~', $v) || preg_match('~^#[\w\-]+$~', $v)) return $v;
    if (preg_match('~^[\w\-]+(\.[\w\-]+)+(/[^\s<>"]*)?$~u', $v)) return 'https://' . $v;
    return '';
}

/* ---------------------------------------------------------------
   Visning af tekst
--------------------------------------------------------------- */

/** Tekst med tomme linjer bliver til afsnit. **fed** og links virker. */
function sc_paragraphs(string $text, string $class = ''): string
{
    $text = trim($text);
    if ($text === '') return '';
    $html = '';
    foreach (preg_split("/\n\s*\n/", $text) as $p) {
        $p = trim($p);
        if ($p === '') continue;
        $html .= '<p' . ($class !== '' ? ' class="' . e($class) . '"' : '') . '>' . sc_inline($p) . "</p>\n";
    }
    return $html;
}

/** Escaper teksten og gør webadresser og e-mails klikbare. */
function sc_inline(string $s): string
{
    $re = '~(https?://[^\s<>"\']*[^\s<>"\'.,:;!?)\]])|([\w.+\-]+@[\w\-]+(?:\.[\w\-]+)+)~u';
    $out = '';
    $pos = 0;
    if (preg_match_all($re, $s, $all, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) {
        foreach ($all as $m) {
            $start = $m[0][1];
            $txt = $m[0][0];
            $out .= sc_fmt(substr($s, $pos, $start - $pos));
            $isMail = isset($m[2]) && $m[2][1] >= 0;
            $href = $isMail ? 'mailto:' . $txt : $txt;
            $out .= '<a href="' . e($href) . '"' . ($isMail ? '' : ' target="_blank" rel="noopener"') . '>' . e($txt) . '</a>';
            $pos = $start + strlen($txt);
        }
    }
    $out .= sc_fmt(substr($s, $pos));
    return nl2br($out, false);
}

function sc_fmt(string $s): string
{
    return (string)preg_replace('/\*\*(.+?)\*\*/su', '<strong>$1</strong>', e($s));
}

function sc_short(string $text, int $max = 180): string
{
    $text = trim((string)preg_replace('/\s+/u', ' ', str_replace('**', '', $text)));
    if ((function_exists('mb_strlen') ? mb_strlen($text) : strlen($text)) <= $max) return $text;
    $cut = sc_substr($text, 0, $max);
    $sp = strrpos($cut, ' ');
    if ($sp !== false && $sp > $max * 0.6) $cut = substr($cut, 0, $sp);
    return rtrim($cut, ' ,.;:–-') . '…';
}

const SC_MAANEDER = ['januar', 'februar', 'marts', 'april', 'maj', 'juni', 'juli', 'august', 'september', 'oktober', 'november', 'december'];

/** "2026-12-17T19:30" -> "17. december 2026 kl. 19.30" */
function sc_date_text(string $start, bool $withTime = true): string
{
    $t = strtotime($start);
    if (!$start || $t === false) return '';
    $s = date('j', $t) . '. ' . SC_MAANEDER[(int)date('n', $t) - 1] . ' ' . date('Y', $t);
    if ($withTime && date('H:i', $t) !== '00:00') $s .= ' kl. ' . date('H.i', $t);
    return $s;
}

/* ---------------------------------------------------------------
   Forestillinger og sponsorer
--------------------------------------------------------------- */

function sc_public_events(array $c): array
{
    return array_values(array_filter($c['events'], function ($ev) {
        return !$ev['skjult'] && $ev['titel'] !== '';
    }));
}

function sc_featured(array $c): ?array
{
    foreach (sc_public_events($c) as $ev) {
        if ($ev['fremhaevet']) return $ev;
    }
    return null;
}

function sc_find_event(array $c, string $id): ?array
{
    foreach (sc_public_events($c) as $ev) {
        if ($ev['id'] === $id) return $ev;
    }
    return null;
}

function sc_event_url(array $ev): string
{
    return 'forestilling.php?id=' . rawurlencode($ev['id']);
}

function sc_event_when(array $ev): string
{
    return $ev['datoer'] !== '' ? $ev['datoer'] : sc_date_text($ev['start']);
}

function sc_is_upcoming(array $ev): bool
{
    if ($ev['start'] === '') return false;
    $t = strtotime($ev['start']);
    return $t !== false && $t > time();
}

/** YouTube- eller Vimeo-link -> adresse til indlejret afspiller. */
function sc_video_embed(string $url): string
{
    if (preg_match('~(?:youtube\.com/(?:watch\?(?:.*&)?v=|shorts/|embed/|live/)|youtu\.be/)([\w\-]{6,15})~i', $url, $m)) {
        return 'https://www.youtube-nocookie.com/embed/' . $m[1];
    }
    if (preg_match('~vimeo\.com/(?:video/)?(\d{5,12})~i', $url, $m)) {
        return 'https://player.vimeo.com/video/' . $m[1];
    }
    return '';
}

const SC_NIVEAUER = ['hoved' => 'Hovedsponsor', 'sponsor' => 'Sponsor', 'netvaerk' => 'Netværk & partnere'];

function sc_sponsors_by_level(array $c, string $level): array
{
    return array_values(array_filter($c['sponsorer'], function ($s) use ($level) {
        return $s['niveau'] === $level && ($s['navn'] !== '' || $s['logo'] !== '');
    }));
}
