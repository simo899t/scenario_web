<?php
require __DIR__ . '/inc/layout.php';

$c = sc_content();
$id = isset($_GET['id']) ? sc_slug((string)$_GET['id']) : '';
$ev = $id !== '' ? sc_find_event($c, $id) : null;

if (!$ev) {
    http_response_code(404);
    sc_page_start(['titel' => 'Forestillingen findes ikke', 'aktiv' => 'forestillinger']);
    ?>
<section class="page-head" style="--img:url('<?= e($c['forside']['baggrund'] ?: 'billeder/praesentation.png') ?>')">
  <div class="wrap">
    <h1>Tæppet er gået ned</h1>
    <div class="page-intro"><p>Vi kunne ikke finde den forestilling, du leder efter. Måske er den flyttet eller taget af plakaten.</p></div>
    <a class="btn" href="forestillinger.php">Se alle forestillinger →</a>
  </div>
</section>
<?php
    sc_page_end();
    exit;
}

$when = sc_event_when($ev);
$video = $ev['video'] !== '' ? sc_video_embed($ev['video']) : '';
$andre = array_slice(array_values(array_filter(sc_public_events($c), function ($e) use ($ev) {
    return $e['id'] !== $ev['id'];
})), 0, 3);

// Strukturerede data, så Google kan vise forestillingen som et event
$jsonld = null;
if ($ev['start'] !== '') {
    $jsonld = [
        '@context' => 'https://schema.org',
        '@type' => 'TheaterEvent',
        'name' => $ev['titel'],
        'startDate' => date('c', strtotime($ev['start'])),
        'eventStatus' => 'https://schema.org/EventScheduled',
        'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
        'description' => sc_short($ev['tekst'] ?: $ev['beskrivelse'], 300),
        'organizer' => ['@type' => 'Organization', 'name' => $c['generelt']['sidenavn'], 'url' => sc_base_url()],
    ];
    if ($ev['billede'] !== '') $jsonld['image'] = [sc_abs_url($ev['billede'])];
    if ($ev['sted'] !== '' || $ev['adresse'] !== '') {
        $jsonld['location'] = ['@type' => 'Place', 'name' => $ev['sted'] ?: $ev['adresse'], 'address' => $ev['adresse'] ?: $ev['sted']];
    }
    if ($ev['billetLink'] !== '') $jsonld['offers'] = ['@type' => 'Offer', 'url' => $ev['billetLink'], 'availability' => 'https://schema.org/InStock'];
}

sc_page_start([
    'titel' => $ev['titel'],
    'beskrivelse' => trim($ev['type'] . ($when !== '' ? ' · ' . $when : '') . '. ' . ($ev['spoergsmaal'] ?: $ev['tekst']), ' .'),
    'billede' => $ev['billede'],
    'aktiv' => 'forestillinger',
    'jsonld' => $jsonld,
]);
?>
<section class="show-head" style="--img:url('<?= e($ev['billede'] ?: ($c['forside']['baggrund'] ?: 'billeder/praesentation.png')) ?>')">
  <div class="wrap">
    <a class="back-link" href="forestillinger.php">← Alle forestillinger</a>
    <?php if ($ev['type'] !== ''): ?><p class="show-type"><?= e($ev['type']) ?></p><?php endif; ?>
    <h1><?= e($ev['titel']) ?></h1>
    <p class="show-meta"><?= e(implode(' · ', array_filter([$when, $ev['sted']]))) ?></p>
  </div>
</section>

<?php if ($ev['billede'] !== ''): ?>
<div class="wrap show-poster reveal">
  <img src="<?= e($ev['billede']) ?>" alt="<?= e($ev['titel']) ?>">
</div>
<?php endif; ?>

<section class="show-body">
  <div class="wrap show-grid">
    <aside class="show-facts reveal">
      <dl>
        <?php if ($when !== ''): ?><div><dt>Hvornår</dt><dd><?= e($when) ?></dd></div><?php endif; ?>
        <?php if ($ev['sted'] !== ''): ?><div><dt>Hvor</dt><dd><?= e($ev['sted']) ?></dd></div><?php endif; ?>
        <?php if ($ev['adresse'] !== ''): ?>
        <div><dt>Adresse</dt><dd><a href="https://www.google.com/maps/search/?api=1&amp;query=<?= e(rawurlencode($ev['adresse'])) ?>" target="_blank" rel="noopener"><?= e($ev['adresse']) ?> ↗</a></dd></div>
        <?php endif; ?>
      </dl>
      <?= sc_countdown($ev) ?>
      <div class="show-buttons">
        <?php if ($ev['billetLink'] !== ''): ?><a class="btn" href="<?= e($ev['billetLink']) ?>" target="_blank" rel="noopener">Køb billet</a><?php endif; ?>
        <?php if ($ev['eksterntLink'] !== ''): ?><a class="btn btn-ghost" href="<?= e($ev['eksterntLink']) ?>" target="_blank" rel="noopener">Mere info ↗</a><?php endif; ?>
      </div>
    </aside>

    <div class="show-text">
      <?php if ($ev['spoergsmaal'] !== ''): ?><p class="show-q reveal"><?= e($ev['spoergsmaal']) ?></p><?php endif; ?>
      <div class="show-lead reveal"><?= sc_paragraphs($ev['tekst']) ?></div>
      <?php if ($ev['beskrivelse'] !== ''): ?><div class="show-desc reveal"><?= sc_paragraphs($ev['beskrivelse']) ?></div><?php endif; ?>
    </div>
  </div>
</section>

<?php if ($video !== ''): ?>
<section class="show-video">
  <div class="wrap reveal">
    <div class="video"><iframe src="<?= e($video) ?>" title="Video: <?= e($ev['titel']) ?>" loading="lazy" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; fullscreen" allowfullscreen></iframe></div>
  </div>
</section>
<?php endif; ?>

<?php if ($ev['hold']): ?>
<section class="show-cast">
  <div class="wrap">
    <h2 class="big">Medvirkende &amp; hold</h2>
    <dl class="cast-list">
      <?php foreach ($ev['hold'] as $p): ?>
      <div class="reveal"><dt><?= e($p['rolle']) ?></dt><dd><?= e($p['navn']) ?></dd></div>
      <?php endforeach; ?>
    </dl>
  </div>
</section>
<?php endif; ?>

<?php if ($ev['galleri']): ?>
<section class="show-gallery">
  <div class="wrap">
    <h2 class="big">Billeder</h2>
    <ul class="gallery" data-gallery>
      <?php foreach ($ev['galleri'] as $i => $img): ?>
      <li class="reveal"><a href="<?= e($img) ?>" data-index="<?= $i ?>"><img src="<?= e($img) ?>" alt="<?= e($ev['titel']) ?> – billede <?= $i + 1 ?>" loading="lazy"></a></li>
      <?php endforeach; ?>
    </ul>
  </div>
</section>
<?php endif; ?>

<?php if ($andre): ?>
<section class="events events-more">
  <div class="wrap">
    <h2 class="big">Andre forestillinger</h2>
    <div class="event-list">
      <?php foreach ($andre as $a) echo sc_event_row($a); ?>
    </div>
  </div>
</section>
<?php endif; ?>
<?php
sc_page_end();
