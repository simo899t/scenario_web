<?php
/*
 * Lagersystemet på /lager: data, brugere og hjælpefunktioner.
 * Alt gemmes i data/lager.php (enheder, reservationer, sektioner)
 * og data/lager-brugere.php (logins). Ingen database nødvendig.
 */

require_once __DIR__ . '/auth.php';

const LG_SEKTIONER = ['LYD', 'LYS', 'REKVISIT'];
const LG_MAX_BACKUPS = 50;

/* ---------- Data ---------- */

function lg_empty(): array
{
    return ['naesteId' => 1, 'naesteResId' => 1, 'sektioner' => LG_SEKTIONER, 'enheder' => [], 'reservationer' => []];
}

function lg_data(): array
{
    $d = sc_data_read('lager');
    if (!is_array($d)) return lg_empty();
    $d = array_merge(lg_empty(), $d);
    foreach (['sektioner', 'enheder', 'reservationer'] as $k) {
        if (!is_array($d[$k])) $d[$k] = [];
    }
    return $d;
}

/** Gemmer lageret og lægger den forrige version i data/lager-backups (de seneste 50). */
function lg_save(array $d): void
{
    $file = sc_data_file('lager');
    if (is_file($file)) {
        $dir = SC_DATA . '/lager-backups';
        if (!is_dir($dir)) @mkdir($dir, 0755, true);
        @copy($file, $dir . '/' . date('Ymd-His') . '-' . bin2hex(random_bytes(2)) . '.php');
        $files = glob($dir . '/*.php') ?: [];
        rsort($files);
        foreach (array_slice($files, LG_MAX_BACKUPS) as $old) @unlink($old);
    }
    sc_data_write('lager', $d);
}

/** Ændrer lageret med eneret, så to personer ikke gemmer oven i hinanden. $fn får data som reference. */
function lg_change(callable $fn)
{
    return sc_locked(function () use ($fn) {
        $d = lg_data();
        $result = $fn($d);
        lg_save($d);
        return $result;
    });
}

function lg_item_index(array $d, int $id): ?int
{
    foreach ($d['enheder'] as $i => $e) {
        if ((int)$e['id'] === $id) return $i;
    }
    return null;
}

/** Aktive reservationer pr. enhed: [enhedId => reservation] */
function lg_active(array $d): array
{
    $out = [];
    foreach ($d['reservationer'] as $r) {
        if ($r['status'] === 'aktiv') $out[(int)$r['enhed']] = $r;
    }
    return $out;
}

function lg_sku_taken(array $d, string $sku, int $except = 0): bool
{
    foreach ($d['enheder'] as $e) {
        if ((int)$e['id'] !== $except && strcasecmp($e['sku'], $sku) === 0) return true;
    }
    return false;
}

/** Sektionen er det, der står før første bindestreg i ID'et (fx LYD-012 -> LYD). */
function lg_section_of(string $sku, array $sections): string
{
    $pos = strpos($sku, '-');
    if ($pos === false) return '';
    $prefix = strtoupper(substr($sku, 0, $pos));
    return in_array($prefix, $sections, true) ? $prefix : '';
}

function lg_overdue(array $r): bool
{
    $t = strtotime($r['til'] ?? '');
    return ($r['status'] ?? '') === 'aktiv' && $t !== false && $t < time();
}

/** Tjekker en dato fra et datetime-local-felt: "2026-11-04T10:00" */
function lg_clean_dt($v): string
{
    $v = trim((string)$v);
    if (preg_match('~^(\d{4}-\d{2}-\d{2})[T ](\d{2}:\d{2})~', $v, $m) && strtotime($m[1] . ' ' . $m[2]) !== false) {
        return $m[1] . 'T' . $m[2];
    }
    return '';
}

/** "2026-11-04T10:00" -> "4. nov. 2026 kl. 10.00" */
function lg_dato(string $v, bool $time = true): string
{
    $t = strtotime($v);
    if ($v === '' || $t === false) return '–';
    $m = ['jan.', 'feb.', 'mar.', 'apr.', 'maj', 'jun.', 'jul.', 'aug.', 'sep.', 'okt.', 'nov.', 'dec.'][(int)date('n', $t) - 1];
    return date('j', $t) . '. ' . $m . ' ' . date('Y', $t) . ($time ? ' kl. ' . date('H.i', $t) : '');
}

/* ---------- Brugere ---------- */

function lg_users(): array
{
    $d = sc_data_read('lager-brugere');
    return isset($d['brugere']) && is_array($d['brugere']) ? $d['brugere'] : [];
}

function lg_users_save(array $users): void
{
    sc_data_write('lager-brugere', ['brugere' => array_values($users)]);
}

function lg_current_user(): ?array
{
    $name = $_SESSION['lg_bruger'] ?? '';
    if ($name === '') return null;
    $users = lg_users();
    $i = sc_user_find($users, $name);
    if ($i === null) {
        unset($_SESSION['lg_bruger']);
        return null;
    }
    return $users[$i];
}

/** Et kodeord, der er til at læse og skrive af – samme slags som i editoren. */
function lg_make_password(): string
{
    $words = ['scene', 'lys', 'kulisse', 'rolle', 'sang', 'dans', 'musik', 'billet', 'applaus', 'premiere', 'replik', 'kostume', 'maske', 'drama', 'finale', 'encore', 'prolog', 'regi', 'rampe', 'kor', 'solo', 'duet', 'akt', 'tango'];
    $w = [];
    for ($i = 0; $i < 4; $i++) $w[] = $words[random_int(0, count($words) - 1)];
    $w[0] = ucfirst($w[0]);
    return implode('-', $w) . '-' . random_int(10, 99);
}

/* ---------- Import og eksport ---------- */

/** Læser rækker fra en Excel- (.xlsx) eller CSV-fil. */
function lg_read_rows(array $file): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
        throw new ScApiError('Filen kunne ikke uploades. Prøv igen.');
    }
    if ($file['size'] > 10 * 1024 * 1024) throw new ScApiError('Filen er for stor (højst 10 MB).');
    $ext = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));

    if ($ext === 'xlsx') {
        require_once __DIR__ . '/SimpleXLSX.php';
        $xlsx = \Shuchkin\SimpleXLSX::parse($file['tmp_name']);
        if (!$xlsx) throw new ScApiError('Excel-filen kunne ikke læses: ' . \Shuchkin\SimpleXLSX::parseError());
        return $xlsx->rows();
    }
    if ($ext === 'csv' || $ext === 'txt') {
        $raw = (string)file_get_contents($file['tmp_name']);
        $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw);
        if (!preg_match('//u', $raw) && function_exists('mb_convert_encoding')) {
            $raw = mb_convert_encoding($raw, 'UTF-8', 'Windows-1252'); // Excel på dansk gemmer ofte sådan
        }
        $first = strtok($raw, "\n");
        $delim = substr_count((string)$first, ';') >= substr_count((string)$first, ',') ? ';' : ',';
        $h = fopen('php://temp', 'r+');
        fwrite($h, $raw);
        rewind($h);
        $rows = [];
        while (($r = fgetcsv($h, 0, $delim, '"', '\\')) !== false) $rows[] = $r;
        fclose($h);
        return $rows;
    }
    throw new ScApiError('Brug en Excel-fil (.xlsx) eller en CSV-fil. Gamle .xls-filer skal gemmes som .xlsx først.');
}

/** Tilføjer rækkerne (ID, Navn, Placering, Beskrivelse) til lageret. Svarer med [tilføjet, sprunget over]. */
function lg_import_rows(array &$d, array $rows): array
{
    $added = 0;
    $skipped = 0;
    $now = date('c');
    foreach ($rows as $n => $row) {
        $row = array_values(array_map(function ($c) { return sc_line((string)$c, 300); }, (array)$row));
        $sku = $row[0] ?? '';
        $navn = $row[1] ?? '';
        if ($n === 0 && preg_match('~^(id|sku|nr\.?|nummer)$~i', $sku)) continue; // overskriftsrække
        if ($sku === '' && $navn === '') continue;
        if ($sku === '' || $navn === '' || lg_sku_taken($d, $sku)) { $skipped++; continue; }
        $d['enheder'][] = [
            'id' => $d['naesteId']++, 'sku' => $sku, 'navn' => $navn,
            'placering' => $row[2] ?? '', 'beskrivelse' => sc_text($row[3] ?? '', 4000),
            'oprettet' => $now, 'opdateret' => $now,
        ];
        $added++;
    }
    return [$added, $skipped];
}

/** Sender hele lageret som en CSV-fil, der åbner pænt i Excel. */
function lg_export_csv(array $d): void
{
    $active = lg_active($d);
    $items = $d['enheder'];
    usort($items, function ($a, $b) { return strnatcasecmp($a['sku'], $b['sku']); });
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="scenario-lager-' . date('Y-m-d') . '.csv"');
    header('Cache-Control: no-store');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['ID', 'Navn', 'Placering', 'Beskrivelse', 'Status', 'Reserveret af', 'Reserveret til'], ';', '"', '\\');
    foreach ($items as $e) {
        $r = $active[(int)$e['id']] ?? null;
        fputcsv($out, [
            $e['sku'], $e['navn'], $e['placering'], $e['beskrivelse'],
            $r ? 'Reserveret' : 'Tilgængelig', $r ? $r['navn'] : '', $r ? lg_dato($r['til']) : '',
        ], ';', '"', '\\');
    }
    fclose($out);
}
