<?php
require __DIR__ . '/inc/layout.php';

$c = sc_content();
$om = $c['om'];
$m = $c['medvirk'];
$mLink = $m['link'] !== '' ? $m['link'] : ($c['kontakt']['email'] !== '' ? 'mailto:' . $c['kontakt']['email'] : '#kontakt');

sc_page_start(['titel' => $om['overskrift'], 'beskrivelse' => $om['kort'], 'aktiv' => 'om']);
?>
<section class="page-head" style="--img:url('<?= e($c['forside']['baggrund'] ?: 'billeder/praesentation.png') ?>')">
  <div class="wrap">
    <h1><?= e($om['overskrift']) ?></h1>
  </div>
</section>

<?php foreach ($om['afsnit'] as $a): ?>
<section class="om-section">
  <div class="wrap om-grid reveal">
    <div>
      <h2 class="big"><?= e($a['overskrift']) ?></h2>
      <?php if ($a['undertitel'] !== ''): ?><p class="om-sub"><?= e($a['undertitel']) ?></p><?php endif; ?>
      <?php if ($a['billede'] !== ''): ?><img class="om-img" src="<?= e($a['billede']) ?>" alt="" loading="lazy"><?php endif; ?>
    </div>
    <div class="om-body"><?= sc_paragraphs($a['tekst']) ?></div>
  </div>
</section>
<?php endforeach; ?>

<section class="join join-small">
  <div class="wrap reveal">
    <h2 class="big"><?= e($m['overskrift']) ?></h2>
    <div class="lead"><?= sc_paragraphs($m['tekst']) ?></div>
    <?= sc_join_buttons($m, $mLink) ?>
  </div>
</section>
<?php
sc_page_end();
