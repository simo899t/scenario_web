<?php
require __DIR__ . '/inc/layout.php';

$c = sc_content();
$t = $c['forestillingerTekst'];
$events = sc_public_events($c);

sc_page_start([
    'titel' => $t['overskrift'],
    'beskrivelse' => $t['intro'] !== '' ? $t['intro'] : 'Alle forestillinger og events fra ' . $c['generelt']['sidenavn'] . '.',
    'aktiv' => 'forestillinger',
]);
?>
<section class="page-head" style="--img:url('<?= e($c['forside']['baggrund'] ?: 'billeder/praesentation.png') ?>')">
  <div class="wrap">
    <h1><?= e($t['overskrift']) ?></h1>
    <?php if ($t['intro'] !== ''): ?><div class="page-intro"><?= sc_paragraphs($t['intro']) ?></div><?php endif; ?>
  </div>
</section>

<section class="events events-grid-section">
  <div class="wrap">
    <?php if (!$events): ?>
      <p class="empty">Der er ingen forestillinger lige nu. Kig forbi igen snart!</p>
    <?php endif; ?>
    <ul class="event-grid">
      <?php foreach ($events as $ev): ?>
      <li class="reveal">
        <a class="event-card" href="<?= e(sc_event_url($ev)) ?>">
          <?= sc_media($ev['billede'], '', 'media event-card-media') ?>
          <span class="event-card-body">
            <?php if (sc_is_upcoming($ev)): ?><span class="badge">Kommende</span><?php endif; ?>
            <?php if ($ev['type'] !== ''): ?><span class="event-type"><?= e($ev['type']) ?></span><?php endif; ?>
            <span class="event-title"><?= e($ev['titel']) ?></span>
            <span class="event-meta"><?= e(implode(' · ', array_filter([sc_event_when($ev), $ev['sted']]))) ?></span>
          </span>
        </a>
      </li>
      <?php endforeach; ?>
    </ul>
  </div>
</section>
<?php
sc_page_end();
