<?php
require_once '../users/init.php';
$pageTitle = 'Store sightings —';
require_once $abs_us_root . $us_url_root . 'users/includes/template/prep.php';
if (!securePage(Server::get('PHP_SELF'))) { die(); }
require_once __DIR__ . '/inc/lists.php';

$me = bb_me();
$isAdmin = hasPerm([2]);
$q = mb_substr(bb_get('q'), 0, 60);
$state = in_array(bb_get('state'), BB_STATES, true) ? bb_get('state') : '';
$mine = bb_get('mine') === '1';

$where = ['1']; $params = [];
if ($q !== '') {
  foreach (preg_split('/\s+/', $q) as $w) {
    $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $w) . '%';
    $where[] = '(p.name LIKE ? OR p.brand LIKE ? OR st.name LIKE ? OR st.city LIKE ?)';
    array_push($params, $like, $like, $like, $like);
  }
}
if ($state) { $where[] = 'st.state = ?'; $params[] = $state; }
if ($mine) { $where[] = 's.user_id = ?'; $params[] = $me; }
$whereSql = implode(' AND ', $where);

$per = 30;
$total = (int)$db->query("SELECT COUNT(*) AS n FROM bb_sightings s JOIN bb_stores st ON st.id = s.store_id JOIN bb_products p ON p.id = s.product_id WHERE $whereSql", $params)->first()->n;
$pages = max(1, (int)ceil($total / $per));
$page = max(1, min($pages, (int)bb_get('page')));
$rows = $db->query(BB_SIGHTING_SELECT . " WHERE $whereSql ORDER BY s.seen_on DESC, s.id DESC LIMIT $per OFFSET " . (($page - 1) * $per), $params)->results();
$states = $db->query("SELECT DISTINCT st.state FROM bb_sightings s JOIN bb_stores st ON st.id = s.store_id ORDER BY st.state")->results();
$deals = bb_wishlist_deals($me);
$self = $_SERVER['REQUEST_URI'];
$link = fn($chg) => bb_url('bourbon/sightings.php', array_filter(array_merge(['q' => $q, 'state' => $state, 'mine' => $mine ? '1' : ''], $chg)));
?>
<div class="bb-page-head d-flex flex-wrap justify-content-between align-items-end gap-3">
  <div>
    <h1>Store sightings</h1>
    <p>Prices members have seen on real shelves. Spot a bottle? Log it and help everyone find a fair price.</p>
  </div>
  <?= bb_open_button('sighting', null, null, '<i class="fa-solid fa-plus me-2"></i>Log a sighting', 'btn btn-primary') ?>
</div>

<?php if ($deals) { ?>
  <section class="bb-deals mb-4" aria-labelledby="deals-h">
    <h2 class="bb-mine-h mb-2" id="deals-h"><i class="fa-solid fa-bell me-1"></i>Deals on your wishlist</h2>
    <ul class="bb-deal-list">
      <?php foreach (array_slice($deals, 0, 6, true) as $d) { ?>
        <li><a href="<?= bb_h(bb_url('bourbon/bottle.php', ['id' => $d->id])) ?>" class="fw-semibold"><?= bb_h($d->name) ?></a>
          <span class="bb-price ms-1"><?= bb_money($d->price) ?></span> at <?= bb_store_link($d) ?> · <?= bb_ago($d->seen_on) ?></li>
      <?php } ?>
    </ul>
    <?php if (count($deals) > 6) { ?><a class="small" href="<?= bb_url('bourbon/wishlist.php') ?>">See all on your wishlist</a><?php } ?>
  </section>
<?php } ?>

<form class="bb-toolbar mb-3" method="get" role="search">
  <input class="form-control form-control-sm" type="search" name="q" value="<?= bb_h($q) ?>" placeholder="Bottle, store or city" aria-label="Search sightings">
  <select class="form-select form-select-sm" name="state" aria-label="State" data-autosubmit>
    <option value="">All states</option>
    <?php foreach ($states as $s) { ?><option<?= $s->state === $state ? ' selected' : '' ?>><?= bb_h($s->state) ?></option><?php } ?>
  </select>
  <div class="form-check form-switch m-0 d-flex align-items-center gap-2">
    <input class="form-check-input m-0" type="checkbox" role="switch" id="mine" name="mine" value="1"<?= $mine ? ' checked' : '' ?> data-autosubmit>
    <label class="form-check-label small text-nowrap" for="mine">Only mine</label>
  </div>
  <button class="btn btn-primary btn-sm" type="submit">Search</button>
</form>

<?php if (!$rows) {
  echo $total || $q || $state || $mine
    ? bb_empty('fa-tag', 'No sightings match', 'Try a different search or state.', false)
    : bb_empty('fa-tag', 'No sightings yet', 'Be the first: tap “Log a sighting” next time you see a bottle on a shelf.', false);
} else { ?>
  <p class="text-secondary small mb-2"><?= number_format($total) ?> sighting<?= $total === 1 ? '' : 's' ?></p>
  <ul class="bb-rows">
    <?php foreach ($rows as $r) { ?>
      <li class="bb-row">
        <?= bb_thumb($r) ?>
        <div class="bb-row-main">
          <a class="bb-row-name" href="<?= bb_h(bb_url('bourbon/bottle.php', ['id' => $r->id])) ?>"><?= bb_h($r->name) ?></a>
          <div class="bb-row-meta"><?= bb_store_link($r) ?></div>
          <div class="bb-row-private">Seen <?= bb_ago($r->seen_on) ?> by <?= bb_reporter($r) ?><?= $r->notes ? ' · “' . bb_h($r->notes) . '”' : '' ?></div>
        </div>
        <div class="text-end flex-none">
          <div class="bb-sight-price"><?= bb_money($r->price) ?></div>
          <?php if ($r->msrp !== null) {
            $diff = $r->price - $r->msrp; $pct = $r->msrp > 0 ? round($diff / $r->msrp * 100) : 0; ?>
            <div class="small <?= $pct <= -5 ? 'bb-down' : ($pct >= 15 ? 'bb-up' : 'text-secondary') ?>">
              <?= $pct <= -5 ? abs($pct) . '% under typical' : ($pct >= 15 ? $pct . '% over typical' : 'about typical') ?></div>
          <?php } ?>
          <?php if ($r->user_id == $me || $isAdmin) { ?>
            <form method="post" action="<?= $us_url_root ?>bourbon/action.php" class="mt-1" data-bb-confirm="Remove this sighting?">
              <?= bb_csrf() ?><input type="hidden" name="do" value="sighting_delete"><input type="hidden" name="id" value="<?= (int)$r->sighting_id ?>"><input type="hidden" name="return" value="<?= bb_h($self) ?>">
              <button class="btn btn-link btn-sm p-0 text-secondary" type="submit">Remove</button>
            </form>
          <?php } ?>
        </div>
      </li>
    <?php } ?>
  </ul>
  <?php if ($pages > 1) { ?>
    <nav class="mt-4" aria-label="Sighting pages"><ul class="pagination pagination-sm justify-content-center">
      <li class="page-item<?= $page <= 1 ? ' disabled' : '' ?>"><a class="page-link" href="<?= bb_h($link(['page' => $page - 1])) ?>">Newer</a></li>
      <li class="page-item disabled"><span class="page-link"><?= $page ?> / <?= $pages ?></span></li>
      <li class="page-item<?= $page >= $pages ? ' disabled' : '' ?>"><a class="page-link" href="<?= bb_h($link(['page' => $page + 1])) ?>">Older</a></li>
    </ul></nav>
  <?php } ?>
<?php } ?>

<?= bb_list_forms() ?>
<script nonce="<?= bb_h($userspice_nonce ?? '') ?>">document.querySelectorAll('[data-autosubmit]').forEach(function (s) { s.addEventListener('change', function () { s.form.submit(); }); });</script>
<?php require_once $abs_us_root . $us_url_root . 'users/includes/html_footer.php'; ?>
