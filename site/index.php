<?php
require __DIR__ . '/inc/layout.php';

$c = sc_content();
$aktuel = sc_featured($c);
$events = sc_public_events($c);
$visEvents = array_slice($events, 0, 3);
$hoved = sc_sponsors_by_level($c, 'hoved');
$baand = array_merge(sc_sponsors_by_level($c, 'sponsor'), sc_sponsors_by_level($c, 'netvaerk'));
$m = $c['medvirk'];
$mLink = $m['link'] !== '' ? $m['link'] : ($c['kontakt']['email'] !== '' ? 'mailto:' . $c['kontakt']['email'] : '#kontakt');

sc_page_start(['aktiv' => 'forside', 'taeppe' => true]);
?>
<section class="hero" id="top">
  <div class="hero-bg" style="--hero:url('<?= e($c['forside']['baggrund'] ?: 'billeder/praesentation.png') ?>')"></div>
  <h1 class="hero-title"><img src="billeder/logo.png" alt="<?= e($c['generelt']['sidenavn']) ?>" width="838" height="216"></h1>
  <?php if ($c['forside']['undertitel'] !== ''): ?><p class="hero-sub"><?= e($c['forside']['undertitel']) ?></p><?php endif; ?>
  <a href="<?= $aktuel ? '#aktuelt' : '#om' ?>" class="scroll-hint" aria-label="Scroll ned"></a>
</section>

<?php if ($aktuel): ?>
<section class="feature" id="aktuelt">
  <div class="wrap">
    <article class="ticket reveal">
      <?php if ($aktuel['billede'] !== ''): ?>
      <a class="ticket-img" href="<?= e(sc_event_url($aktuel)) ?>" tabindex="-1"><img src="<?= e($aktuel['billede']) ?>" alt="<?= e($aktuel['titel']) ?>"></a>
      <?php endif; ?>
      <div class="ticket-body">
        <?php if ($c['forside']['aktuelOverskrift'] !== ''): ?><p class="ticket-label"><?= e($c['forside']['aktuelOverskrift']) ?></p><?php endif; ?>
        <h2 class="ticket-title"><?= e($aktuel['titel']) ?> <span class="ticket-type"><?= e($aktuel['type']) ?></span></h2>
        <?php if (sc_event_when($aktuel) !== '' || $aktuel['sted'] !== ''): ?>
        <dl class="ticket-facts">
          <?php if (sc_event_when($aktuel) !== ''): ?><div><dt>Hvornår</dt><dd><?= e(sc_event_when($aktuel)) ?></dd></div><?php endif; ?>
          <?php if ($aktuel['sted'] !== ''): ?><div><dt>Hvor</dt><dd><?= e($aktuel['sted']) ?></dd></div><?php endif; ?>
        </dl>
        <?php endif; ?>
        <?= sc_countdown($aktuel) ?>
        <?php if ($aktuel['spoergsmaal'] !== ''): ?><p class="ticket-q"><?= e($aktuel['spoergsmaal']) ?></p><?php endif; ?>
        <div class="ticket-text"><?= sc_paragraphs($aktuel['tekst']) ?></div>
        <div class="ticket-buttons">
          <?php if ($aktuel['billetLink'] !== ''): ?><a class="btn" href="<?= e($aktuel['billetLink']) ?>" target="_blank" rel="noopener">Køb billet</a><?php endif; ?>
          <a class="btn btn-dark" href="<?= e(sc_event_url($aktuel)) ?>">Læs mere om <?= e($aktuel['titel']) ?> →</a>
        </div>
      </div>
    </article>
  </div>
</section>
<?php endif; ?>

<section class="about" id="om">
  <div class="wrap reveal">
    <h2 class="big"><?= e($c['om']['overskrift']) ?></h2>
    <div class="about-short"><?= sc_paragraphs($c['om']['kort']) ?></div>
    <?php if ($c['om']['knap'] !== ''): ?><a class="btn btn-dark" href="om.php"><?= e($c['om']['knap']) ?> →</a><?php endif; ?>
  </div>
</section>

<?php if ($events): ?>
<section class="events" id="events">
  <div class="wrap">
    <h2 class="big"><?= e($c['forestillingerTekst']['overskrift']) ?></h2>
    <div class="event-list">
      <?php foreach ($visEvents as $ev) echo sc_event_row($ev); ?>
    </div>
    <a class="all-link" href="forestillinger.php"><?= count($events) > count($visEvents) ? 'Se alle ' . count($events) . ' forestillinger' : 'Se alle forestillinger' ?> →</a>
  </div>
</section>
<?php endif; ?>

<section class="join" id="medvirk">
  <div class="wrap reveal<?= $m['billeder'] ? ' join-split' : '' ?>">
    <div class="join-text">
      <h2 class="big"><?= e($m['overskrift']) ?></h2>
      <div class="lead"><?= sc_paragraphs($m['tekst']) ?></div>
      <?php if ($m['roller']): ?>
      <ul class="roles"><?php foreach ($m['roller'] as $r): ?><li><?= e($r) ?></li><?php endforeach; ?></ul>
      <?php endif; ?>
      <?php if ($m['knap'] !== ''): ?><a class="btn btn-light" href="<?= e($mLink) ?>"><?= e($m['knap']) ?></a><?php endif; ?>
    </div>
    <?php if ($m['billeder']): ?>
    <ul class="join-photos" data-n="<?= min(count($m['billeder']), 5) ?>" data-gallery>
      <?php foreach ($m['billeder'] as $i => $img): ?>
      <li><a href="<?= e($img) ?>" data-index="<?= $i ?>"><img src="<?= e($img) ?>" alt="<?= e($m['overskrift']) ?> – billede <?= $i + 1 ?>" loading="lazy"></a></li>
      <?php endforeach; ?>
    </ul>
    <?php endif; ?>
  </div>
</section>

<?php if ($hoved || $baand): ?>
<section class="sponsors" id="sponsorer">
  <div class="wrap">
    <h2 class="big"><?= e($c['sponsorTekst']['overskrift']) ?></h2>
    <?php if ($c['sponsorTekst']['tekst'] !== ''): ?><div class="sponsors-text"><?= sc_paragraphs($c['sponsorTekst']['tekst']) ?></div><?php endif; ?>
    <?php if ($hoved): ?>
    <p class="sponsors-label"><?= count($hoved) > 1 ? 'Hovedsponsorer' : 'Hovedsponsor' ?></p>
    <ul class="sponsors-main">
      <?php foreach ($hoved as $s): ?>
      <li><?= sc_sponsor($s, 'sponsor sponsor-hoved') ?></li>
      <?php endforeach; ?>
    </ul>
    <?php endif; ?>
  </div>
  <?php if ($baand):
      // Gentag logoerne, så båndet altid er fyldt ud – også med få sponsorer
      $loop = $baand;
      while (count($loop) < 10) $loop = array_merge($loop, $baand);
  ?>
  <div class="marquee" style="--dur:<?= count($loop) * 5 ?>s">
    <div class="marquee-track">
      <?php foreach ([false, true] as $kopi): ?>
      <ul class="marquee-group"<?= $kopi ? ' aria-hidden="true"' : '' ?>>
        <?php foreach ($loop as $i => $s): ?>
        <li<?= !$kopi && $i >= count($baand) ? ' aria-hidden="true"' : '' ?>><?= sc_sponsor($s, 'sponsor', $kopi || $i >= count($baand)) ?></li>
        <?php endforeach; ?>
      </ul>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>
</section>
<?php endif; ?>

<?php
sc_page_end();

function sc_sponsor(array $s, string $class, bool $hidden = false): string
{
    $tag = $s['link'] !== '' ? 'a' : 'figure';
    $attr = $s['link'] !== '' ? ' href="' . e($s['link']) . '" target="_blank" rel="noopener"' . ($hidden ? ' tabindex="-1"' : '') : '';
    $html = '<' . $tag . ' class="' . e($class) . '"' . $attr . '>';
    if ($s['logo'] !== '') $html .= '<img src="' . e($s['logo']) . '" alt="" width="190" height="190">';
    else $html .= '<span class="sponsor-ph" aria-hidden="true">' . e(sc_substr($s['navn'], 0, 1)) . '</span>';
    $html .= ($tag === 'a' ? '<span class="sponsor-name">' : '<figcaption>') . e($s['navn']) . ($tag === 'a' ? '</span>' : '</figcaption>');
    return $html . '</' . $tag . '>';
}
