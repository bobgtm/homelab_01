<?php
require_once '../users/init.php';
require_once __DIR__ . '/inc/forms.php';

$id = (int)bb_get('id');
$p = $id ? $db->query(
  "SELECT p.*, d.name AS distillery_name, d.id AS dist_id, d.city, d.state, d.kind, d.owner
     FROM bb_products p JOIN bb_distilleries d ON d.id = p.distillery_id
    WHERE p.id = ?", [$id])->first() : null;
// Hidden bottles stay reachable (members may have them in their lists); pending or
// declined suggestions are only visible to admins and the member who suggested them.
$viewerIsAdmin = isset($user) && $user->isLoggedIn() && hasPerm([2]);
if ($p && in_array($p->status, ['pending', 'rejected'], true)
    && !($viewerIsAdmin || (isset($user) && $user->isLoggedIn() && (int)$user->data()->id === (int)$p->submitted_by))) $p = null;
if ($p) $pageTitle = $p->name . ' —';

require_once $abs_us_root . $us_url_root . 'users/includes/template/prep.php';
if (!securePage(Server::get('PHP_SELF'))) { die(); }

if (!$p) { http_response_code(404); ?>
  <div class="bb-empty my-5">
    <div class="bb-feature-icon"><i class="fa-solid fa-whiskey-glass"></i></div>
    <h1 class="h4">We couldn’t find that bottle</h1>
    <p>It may have been removed, or the link is incomplete.</p>
    <a class="btn btn-primary btn-sm" href="<?= bb_url('bourbon/browse.php') ?>">Browse the catalog</a>
  </div>
  <?php require_once $abs_us_root . $us_url_root . 'users/includes/html_footer.php'; exit;
}

$more = $db->query(
  "SELECT p.*, d.name AS distillery_name FROM bb_products p JOIN bb_distilleries d ON d.id = p.distillery_id
    WHERE p.distillery_id = ? AND p.id <> ? AND p.status = 'approved'
    ORDER BY (p.brand = ?) DESC, (p.image_file IS NOT NULL) DESC, p.name LIMIT 4", [$p->distillery_id, $p->id, $p->brand])->results();
$tags = bb_tags($p);
$in = $user->isLoggedIn();
$me = bb_me();
$mine = $theirs = null;
if ($me) {
  $mine = (object)[
    'bottles'  => $db->query("SELECT * FROM bb_collection WHERE user_id = ? AND product_id = ? ORDER BY purchase_date DESC, id DESC", [$me, $p->id])->results(),
    'tastings' => $db->query("SELECT * FROM bb_tastings WHERE user_id = ? AND product_id = ? ORDER BY tasted_on DESC, id DESC", [$me, $p->id])->results(),
    'wish'     => $db->query("SELECT * FROM bb_wishlist WHERE user_id = ? AND product_id = ?", [$me, $p->id])->first(),
  ];
}
$sightings = $db->query(BB_SIGHTING_SELECT . " WHERE s.product_id = ? ORDER BY s.seen_on DESC, s.id DESC LIMIT 60", [$p->id])->results();
$recentForChart = array_reverse(array_filter($sightings, fn($r) => $r->seen_on >= date('Y-m-d', strtotime('-' . BB_RECENT_DAYS . ' days'))));
$stats = bb_price_stats($p->id);
$isAdmin = hasPerm([2]);
// Community numbers are aggregates only, so they reveal nothing about any one person.
$community = $db->query("SELECT
    (SELECT COUNT(DISTINCT user_id) FROM bb_collection WHERE product_id = ?) AS owners,
    (SELECT COUNT(*) FROM bb_tastings WHERE product_id = ? AND rating IS NOT NULL) AS ratings,
    (SELECT AVG(rating) FROM bb_tastings WHERE product_id = ? AND rating IS NOT NULL) AS avg_rating,
    (SELECT COUNT(DISTINCT user_id) FROM bb_wishlist WHERE product_id = ?) AS wishers", [$p->id, $p->id, $p->id, $p->id])->first();
$facts = [
  ['Proof', $p->proof !== null ? rtrim(rtrim(number_format((float)$p->proof, 1), '0'), '.') . ($p->is_barrel_proof ? ' <span class="text-secondary small">(varies by batch)</span>' : '') : ($p->is_barrel_proof ? 'Barrel proof, varies by batch' : '<span class="text-secondary">Not listed</span>')],
  ['Age', $p->age_years ? $p->age_years . ' years' : '<span class="text-secondary">No age statement</span>'],
  ['Typical price', $p->msrp !== null ? '~' . bb_price($p) . ' <span class="text-secondary small">(' . (int)$p->size_ml . ' ml, varies by store)</span>' : '<span class="text-secondary">Not listed</span>'],
  ['Mash bill', $p->mash_style === 'wheated' ? 'Wheated' : ($p->mash_style === 'high-rye' ? 'High rye' : '<span class="text-secondary">Not listed</span>')],
];
?>

<nav aria-label="breadcrumb" class="mb-3">
  <ol class="breadcrumb small mb-0">
    <li class="breadcrumb-item"><a href="<?= bb_url('bourbon/browse.php') ?>">Catalog</a></li>
    <li class="breadcrumb-item"><a href="<?= bb_url('bourbon/distillery.php', ['id' => $p->dist_id]) ?>"><?= bb_h($p->distillery_name) ?></a></li>
    <li class="breadcrumb-item active" aria-current="page"><?= bb_h($p->name) ?></li>
  </ol>
</nav>

<?php if ($p->status !== 'approved') { ?>
  <div class="alert <?= $p->status === 'hidden' ? 'alert-secondary' : 'alert-info' ?> d-flex gap-2 align-items-center" role="status">
    <i class="fa-solid fa-circle-info"></i>
    <span><?= ['hidden' => 'This bottle is no longer listed in the catalog. It still appears in lists that already include it.',
               'pending' => 'This suggestion is waiting for an admin to review it. Only you and admins can see this page.',
               'rejected' => 'This suggestion was declined' . ($p->review_note ? ': ' . bb_h($p->review_note) : '.')][$p->status] ?></span>
  </div>
<?php } ?>
<?php if ($viewerIsAdmin) { ?>
  <div class="d-flex justify-content-end mb-2"><a class="btn btn-outline-primary btn-sm" href="<?= bb_url('bourbon/admin_bottle.php', ['id' => $p->id]) ?>"><i class="fa-solid fa-pen me-2"></i>Edit bottle</a></div>
<?php } ?>
<div class="row g-4 g-lg-5 align-items-start">
  <div class="col-md-5 col-lg-4">
    <figure class="bb-detail-media mb-0">
      <?= bb_bottle_image($p, '', true) ?>
    </figure>
    <?php if ($p->image_file && !$p->image_license) { ?>
      <p class="bb-credit mt-2 mb-0">Photo: <?= bb_h($p->image_credit ?: 'Barrel Book') ?>.</p>
    <?php } elseif ($p->image_file) { ?>
      <p class="bb-credit mt-2 mb-0">
        Photo: <?= bb_h($p->image_credit) ?>,
        <?php if ($p->image_license_url) { ?><a href="<?= bb_h($p->image_license_url) ?>" rel="license noopener" target="_blank"><?= bb_h($p->image_license) ?></a><?php } else { echo bb_h($p->image_license); } ?>.
        <a href="<?= bb_h($p->image_source_url) ?>" rel="noopener" target="_blank">Source</a>; resized and framed.
        The bottle you buy may have a newer label.
      </p>
    <?php } else { ?>
      <p class="bb-credit mt-2 mb-0">Illustration only. No licensed photo of this bottle yet.</p>
    <?php } ?>
  </div>

  <div class="col-md-7 col-lg-8">
    <div class="bb-eyebrow mb-2"><?= bb_h($p->brand) ?></div>
    <h1 class="bb-detail-title"><?= bb_h($p->name) ?></h1>
    <p class="text-secondary mb-3">
      <a href="<?= bb_url('bourbon/distillery.php', ['id' => $p->dist_id]) ?>"><?= bb_h($p->distillery_name) ?></a>
      <?php if ($place = bb_place($p)) { ?> · <?= bb_h($place) ?><?php } ?>
    </p>
    <?php if ($tags) { ?>
      <div class="d-flex flex-wrap gap-2 mb-4">
        <?php foreach ($tags as $t) { ?><span class="badge bb-badge"><?= bb_h($t) ?></span><?php } ?>
      </div>
    <?php } ?>

    <dl class="bb-facts">
      <?php foreach ($facts as [$k, $v]) { ?>
        <div><dt><?= $k ?></dt><dd><?= $v ?></dd></div>
      <?php } ?>
    </dl>

    <?php if ($community->owners || $community->ratings || $community->wishers) { ?>
      <p class="bb-community mt-3 mb-0">
        <?php if ($community->ratings) { ?><?= bb_stars(round($community->avg_rating * 2) / 2) ?> <strong><?= number_format($community->avg_rating, 1) ?></strong> from <?= $community->ratings ?> rating<?= $community->ratings == 1 ? '' : 's' ?><span class="mx-2">·</span><?php } ?>
        <?= (int)$community->owners ?> collector<?= $community->owners == 1 ? '' : 's' ?> own it<span class="mx-2">·</span><?= (int)$community->wishers ?> want it
      </p>
    <?php } ?>

    <div class="bb-actions mt-4">
      <?php if ($in) {
        $onWish = (bool)$mine->wish; ?>
        <?= bb_open_button('collection', $p, null, '<i class="fa-solid fa-box-archive me-2"></i>Add to collection', 'btn btn-primary', ['onWishlist' => $onWish]) ?>
        <?= bb_open_button('tasting', $p, null, '<i class="fa-solid fa-whiskey-glass me-2"></i>Log a tasting', 'btn btn-outline-primary') ?>
        <?= bb_open_button('sighting', $p, null, '<i class="fa-solid fa-tag me-2"></i>Saw it in a store', 'btn btn-outline-primary') ?>
        <?= $onWish
          ? bb_open_button('wishlist', $p, $mine->wish, '<i class="fa-solid fa-star me-2"></i>On your wishlist', 'btn btn-outline-primary active')
          : bb_open_button('wishlist', $p, null, '<i class="fa-regular fa-star me-2"></i>Add to wishlist', 'btn btn-outline-primary') ?>
      <?php } else { ?>
        <a class="btn btn-primary" href="<?= $us_url_root ?>users/login.php"><i class="fa-solid fa-right-to-bracket me-2"></i>Log in to track this bottle</a>
        <?php if ($settings->registration == 1) { ?><a class="btn btn-outline-primary" href="<?= $us_url_root ?>users/join.php">Create a free account</a><?php } ?>
      <?php } ?>
    </div>

    <?php if ($in && ($mine->bottles || $mine->tastings || $mine->wish)) { ?>
      <section class="bb-mine mt-4" aria-labelledby="bb-mine-h">
        <h2 class="bb-mine-h mb-3" id="bb-mine-h">Your bottle</h2>
        <?php foreach ($mine->bottles as $b) { ?>
          <div class="bb-mine-row">
            <i class="fa-solid fa-box-archive text-primary"></i>
            <div class="flex-grow-1">
              <strong><?= (int)$b->quantity ?> × <?= ucfirst($b->status) ?></strong><?= $b->status === 'open' && $b->fill_level !== null ? ' · ' . (int)$b->fill_level . '% left' : '' ?>
              <div class="small text-secondary"><?= bb_h(implode(' · ', array_filter([$b->purchase_price !== null ? 'Paid ' . bb_money($b->purchase_price) : null, $b->store_name, bb_date($b->purchase_date)]))) ?></div>
            </div>
            <?= bb_open_button('collection', $p, $b) ?>
          </div>
        <?php } ?>
        <?php foreach ($mine->tastings as $t) { ?>
          <div class="bb-mine-row">
            <i class="fa-solid fa-whiskey-glass text-primary"></i>
            <div class="flex-grow-1">
              <?= bb_stars($t->rating) ?> <span class="small text-secondary ms-1"><?= bb_date($t->tasted_on) ?></span>
              <?php $n = array_filter([$t->nose ? 'Nose: ' . $t->nose : null, $t->palate ? 'Palate: ' . $t->palate : null, $t->finish ? 'Finish: ' . $t->finish : null, $t->notes]); ?>
              <?php if ($n) { ?><div class="small text-secondary"><?= bb_h(implode(' · ', $n)) ?></div><?php } ?>
            </div>
            <?= bb_open_button('tasting', $p, $t) ?>
          </div>
        <?php } ?>
        <?php if ($mine->wish) { ?>
          <div class="bb-mine-row">
            <i class="fa-solid fa-star text-primary"></i>
            <div class="flex-grow-1">On your wishlist<?= $mine->wish->target_price !== null ? ' · up to ' . bb_money($mine->wish->target_price) : '' ?>
              <?php if ($mine->wish->notes) { ?><div class="small text-secondary"><?= bb_h($mine->wish->notes) ?></div><?php } ?></div>
            <?= bb_open_button('wishlist', $p, $mine->wish) ?>
          </div>
        <?php } ?>
      </section>
    <?php } ?>
  </div>
</div>

<section class="bb-prices mt-5" aria-labelledby="prices-h">
  <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3">
    <div>
      <h2 class="h4 mb-1" id="prices-h">Prices on the shelf</h2>
      <p class="text-secondary small mb-0">What members have actually seen in stores<?= $stats ? ' over the last ' . BB_RECENT_DAYS . ' days' : '' ?>.</p>
    </div>
    <?php if ($in) echo bb_open_button('sighting', $p, null, '<i class="fa-solid fa-plus me-2"></i>Log a sighting', 'btn btn-outline-primary btn-sm'); ?>
  </div>
  <?php if (!$sightings) { ?>
    <div class="bb-empty py-4"><p class="mb-0">No one has logged a shelf price for this bottle yet.<?= $in ? ' Seen it in a store? Log it above.' : ' <a href="' . $us_url_root . 'users/login.php">Log in</a> to add one.' ?></p></div>
  <?php } else { ?>
    <?php if ($stats) { ?>
      <div class="bb-statrow mb-3">
        <div><strong><?= bb_money($stats->low) ?></strong><span>lowest seen</span></div>
        <div><strong><?= bb_money($stats->median) ?></strong><span>typical (median)</span></div>
        <div><strong><?= bb_money($stats->high) ?></strong><span>highest seen</span></div>
        <div><strong><?= $stats->n ?></strong><span>sighting<?= $stats->n == 1 ? '' : 's' ?></span></div>
      </div>
    <?php } ?>
    <?php $chart = bb_price_chart(array_values($recentForChart), $in && $mine->wish && $mine->wish->target_price !== null ? (float)$mine->wish->target_price : null, $p->msrp !== null ? (float)$p->msrp : null);
    if ($chart) { ?>
      <figure class="bb-chart-wrap mb-3"><?= $chart ?><div class="bb-chart-tip" role="status" aria-live="polite" hidden></div>
        <figcaption class="visually-hidden">Each dot is one sighting; the list below has the same data.</figcaption></figure>
    <?php } ?>
    <ul class="bb-sight-list">
      <?php foreach (array_slice($sightings, 0, 12) as $r) { ?>
        <li>
          <span class="bb-sight-price"><?= bb_money($r->price) ?></span>
          <span class="flex-grow-1 min-w-0"><?= bb_store_link($r) ?>
            <span class="d-block small text-secondary"><?= bb_date($r->seen_on) ?> · by <?= bb_reporter($r) ?><?= $r->notes ? ' · “' . bb_h($r->notes) . '”' : '' ?></span></span>
          <?php if ($in && ($r->user_id == $me || $isAdmin)) { ?>
            <form method="post" action="<?= $us_url_root ?>bourbon/action.php" data-bb-confirm="Remove this sighting?"><?= bb_csrf() ?>
              <input type="hidden" name="do" value="sighting_delete"><input type="hidden" name="id" value="<?= (int)$r->sighting_id ?>"><input type="hidden" name="return" value="<?= bb_h($_SERVER['REQUEST_URI']) ?>">
              <button class="btn btn-link btn-sm p-0 text-secondary" type="submit">Remove</button></form>
          <?php } ?>
        </li>
      <?php } ?>
    </ul>
  <?php } ?>
</section>

<?php if ($more) { ?>
  <section class="mt-5">
    <h2 class="h4 mb-3">More from <?= bb_h($p->distillery_name) ?></h2>
    <div class="bb-grid bb-grid-4">
      <?php foreach ($more as $m) echo bb_product_card($m); ?>
    </div>
  </section>
<?php } ?>

<?php if ($in) echo bb_list_forms(); ?>
<script nonce="<?= bb_h($userspice_nonce ?? '') ?>">
// Chart tooltip: hover or keyboard focus on a dot shows its price, store and date.
(function () {
  var wrap = document.querySelector('.bb-chart-wrap'); if (!wrap) return;
  var tip = wrap.querySelector('.bb-chart-tip'), svg = wrap.querySelector('svg');
  // Keep text and dots a constant on-screen size however wide the chart is drawn.
  function rescale() {
    var k = svg.viewBox.baseVal.width / Math.max(1, svg.clientWidth);
    svg.querySelectorAll('text').forEach(function (t) { t.style.fontSize = (12 * k) + 'px'; });
    svg.querySelectorAll('.bb-chart-dot').forEach(function (c) { c.setAttribute('r', 6 * k); c.dataset.r = 6 * k; });
    svg.querySelectorAll('.bb-chart-hit').forEach(function (c) { c.setAttribute('r', 14 * k); });
  }
  rescale(); window.addEventListener('resize', rescale);
  function show(g) {
    var dot = g.querySelector('.bb-chart-dot'), b = dot.getBoundingClientRect(), w = wrap.getBoundingClientRect();
    tip.textContent = g.getAttribute('data-tip'); tip.hidden = false;
    var x = b.left - w.left + wrap.scrollLeft + b.width / 2, y = b.top - w.top;
    tip.style.left = Math.max(8 + wrap.scrollLeft, Math.min(x - tip.offsetWidth / 2, wrap.scrollLeft + w.width - tip.offsetWidth - 8)) + 'px';
    tip.style.top = Math.max(0, y - tip.offsetHeight - 10) + 'px';
    wrap.querySelectorAll('.bb-chart-pt').forEach(function (o) { var d = o.querySelector('.bb-chart-dot'); d.setAttribute('r', o === g ? d.dataset.r * 1.45 : d.dataset.r); });
  }
  function hide() { tip.hidden = true; wrap.querySelectorAll('.bb-chart-dot').forEach(function (d) { d.setAttribute('r', d.dataset.r); }); }
  wrap.querySelectorAll('.bb-chart-pt').forEach(function (g) {
    g.querySelector('title').remove(); // our tooltip replaces the native one
    g.addEventListener('mouseenter', function () { show(g); });
    g.addEventListener('focus', function () { show(g); });
    g.addEventListener('mouseleave', hide); g.addEventListener('blur', hide);
  });
})();
</script>
<?php require_once $abs_us_root . $us_url_root . 'users/includes/html_footer.php'; ?>
