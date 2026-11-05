<?php
/* Fælles top og bund for alle sider. */

require_once __DIR__ . '/lib.php';

/**
 * $o: titel, beskrivelse, billede, aktiv ('forside'|'om'|'forestillinger'), taeppe (bool), jsonld (array)
 */
function sc_page_start(array $o): void
{
    $c = sc_content();
    $site = $c['generelt']['sidenavn'] ?: 'Teater Scenario';
    $title = !empty($o['titel']) ? $o['titel'] . ' – ' . $site : $site;
    $desc = $o['beskrivelse'] ?? '' ?: $c['generelt']['beskrivelse'];
    $image = sc_abs_url($o['billede'] ?? '' ?: $c['forside']['baggrund'] ?: 'billeder/praesentation.png');
    $aktiv = $o['aktiv'] ?? '';
    $home = $aktiv === 'forside' ? '' : 'index.php';
    $featured = sc_featured($c);
    ?>
<!doctype html>
<html lang="da">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($title) ?></title>
  <meta name="description" content="<?= e(sc_short($desc, 300)) ?>">
  <meta property="og:type" content="website">
  <meta property="og:site_name" content="<?= e($site) ?>">
  <meta property="og:title" content="<?= e($o['titel'] ?? '' ?: $site) ?>">
  <meta property="og:description" content="<?= e(sc_short($desc, 300)) ?>">
  <meta property="og:image" content="<?= e($image) ?>">
  <meta property="og:locale" content="da_DK">
  <meta name="twitter:card" content="summary_large_image">
  <meta name="theme-color" content="#120e0d">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Work+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= e(sc_asset('style.css')) ?>">
  <script>document.documentElement.classList.add("js");<?php if (!empty($o['taeppe'])): ?>/* Tæppet åbner kun første gang pr. besøg */try{if(sessionStorage.getItem("sc-taeppe"))document.documentElement.classList.add("uden-taeppe");sessionStorage.setItem("sc-taeppe","1")}catch(e){}<?php endif; ?></script>
<?php if (!empty($o['jsonld'])): ?>
  <script type="application/ld+json"><?= json_encode($o['jsonld'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?></script>
<?php endif; ?>
</head>
<body class="side-<?= e($aktiv ?: 'andet') ?>">
<a class="skip" href="#indhold">Spring til indhold</a>
<?php if (!empty($o['taeppe'])): ?>
<div class="curtain" aria-hidden="true"><div class="curtain-half"></div><div class="curtain-half"></div></div>
<?php endif; ?>
<header class="nav">
  <a href="<?= $home ?: '#top' ?>" class="nav-logo"><img src="billeder/logo.png" alt="<?= e($site) ?> – til forsiden" width="838" height="216"></a>
  <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="menu"><span></span><span></span><span class="sr">Menu</span></button>
  <nav class="nav-links" id="menu" aria-label="Hovedmenu">
<?php if ($featured): ?>
    <a href="<?= $home ?>#aktuelt">Aktuelt</a>
<?php endif; ?>
    <a href="om.php"<?= $aktiv === 'om' ? ' aria-current="page"' : '' ?>>Om os</a>
    <a href="forestillinger.php"<?= $aktiv === 'forestillinger' ? ' aria-current="page"' : '' ?>>Forestillinger</a>
    <a href="<?= $home ?>#medvirk">Vær med</a>
    <a href="#kontakt">Kontakt</a>
  </nav>
</header>
<main id="indhold">
<?php
}

function sc_page_end(): void
{
    $c = sc_content();
    $k = $c['kontakt'];
    $site = $c['generelt']['sidenavn'] ?: 'Teater Scenario';
    $some = array_filter([
        'Instagram' => $k['instagram'], 'Facebook' => $k['facebook'],
        'TikTok' => $k['tiktok'], 'YouTube' => $k['youtube'],
    ]);
    ?>
</main>
<footer class="footer" id="kontakt">
  <div class="wrap">
    <h2 class="big"><?= e($k['overskrift']) ?></h2>
    <?php if ($k['tekst'] !== ''): ?><div class="footer-text"><?= sc_paragraphs($k['tekst']) ?></div><?php endif; ?>
    <?php if ($k['email'] !== ''): ?><a class="footer-mail" href="mailto:<?= e($k['email']) ?>"><?= e($k['email']) ?></a><?php endif; ?>
    <?php if ($k['telefon'] !== '' || $k['adresse'] !== ''): ?>
    <p class="footer-contact">
      <?php if ($k['telefon'] !== ''): ?><a href="tel:<?= e(preg_replace('~[^\d+]~', '', $k['telefon'])) ?>"><?= e($k['telefon']) ?></a><?php endif; ?>
      <?php if ($k['adresse'] !== ''): ?><span><?= e($k['adresse']) ?></span><?php endif; ?>
    </p>
    <?php endif; ?>
    <?php if ($some): ?>
    <ul class="footer-some">
      <?php foreach ($some as $navn => $url): ?><li><a href="<?= e($url) ?>" target="_blank" rel="noopener"><?= e($navn) ?> ↗</a></li><?php endforeach; ?>
    </ul>
    <?php endif; ?>
    <div class="footer-row">
      <span><?= $k['cvr'] !== '' ? 'CVR ' . e($k['cvr']) : '' ?></span>
      <span>© <?= date('Y') ?> <?= e($site) ?></span>
    </div>
  </div>
</footer>
<script src="<?= e(sc_asset('script.js')) ?>"></script>
</body>
</html>
<?php
}

/** Billede med sløret baggrund – passer til både brede bannere og plakater. */
function sc_media(string $src, string $alt, string $class = 'media'): string
{
    if ($src === '') return '<div class="' . e($class) . ' media-tom" aria-hidden="true"></div>';
    return '<div class="' . e($class) . '" style="--img:url(\'' . e($src) . '\')"><img src="' . e($src) . '" alt="' . e($alt) . '" loading="lazy"></div>';
}

/** Knapperne i "Vær med": "Bliv medlem" (fx til Unioo) og "Skriv til os". */
function sc_join_buttons(array $m, string $mLink): string
{
    $medlem = $m['medlemLink'] !== '';
    $html = '';
    if ($medlem) {
        $html .= '<a class="btn btn-light" href="' . e($m['medlemLink']) . '" target="_blank" rel="noopener">' . e($m['medlemKnap'] !== '' ? $m['medlemKnap'] : 'Bliv medlem') . '</a>';
    }
    if ($m['knap'] !== '') {
        $html .= '<a class="btn ' . ($medlem ? 'btn-ghost' : 'btn-light') . '" href="' . e($mLink) . '">' . e($m['knap']) . '</a>';
    }
    return $html === '' ? '' : '<div class="join-actions">' . $html . '</div>';
}

/** Liste-række til en forestilling (forside og "andre forestillinger"). */
function sc_event_row(array $ev): string
{
    $html = '<a class="event-row" href="' . e(sc_event_url($ev)) . '">';
    $html .= '<span class="event-year">' . e(sc_event_when($ev)) . '</span>';
    $html .= '<span class="event-main"><span class="event-title">' . e($ev['titel']) . '</span>';
    $html .= '<span class="event-type">' . e($ev['type']) . ($ev['sted'] !== '' ? ' · ' . e($ev['sted']) : '') . '</span></span>';
    $html .= '<span class="event-more">' . (sc_is_upcoming($ev) ? '<span class="badge">Kommende</span> ' : '') . 'Læs mere →</span>';
    return $html . '</a>';
}

function sc_countdown(array $ev): string
{
    if (!sc_is_upcoming($ev)) return '';
    $t = strtotime($ev['start']);
    return '<p class="countdown" data-start="' . e(date('c', $t)) . '" aria-label="Nedtælling til premieren">'
        . '<span><b data-u="d">0</b> dage</span><span><b data-u="h">0</b> timer</span>'
        . '<span><b data-u="m">0</b> min</span><span><b data-u="s">0</b> sek</span></p>';
}
