<?php
require_once '../users/init.php';
require_once __DIR__ . '/inc/lists.php';

$id = (int)bb_get('id');
$st = $id ? $db->query("SELECT * FROM bb_stores WHERE id = ?", [$id])->first() : null;
if ($st) $pageTitle = $st->name . ' —';
require_once $abs_us_root . $us_url_root . 'users/includes/template/prep.php';
if (!securePage(Server::get('PHP_SELF'))) { die(); }

if (!$st) { http_response_code(404); ?>
  <div class="bb-empty my-5"><h1 class="h4">Store not found</h1><a class="btn btn-primary btn-sm" href="<?= bb_url('bourbon/sightings.php') ?>">All sightings</a></div>
  <?php require_once $abs_us_root . $us_url_root . 'users/includes/html_footer.php'; exit;
}

// Latest sighting per bottle at this store, newest first.
$rows = $db->query(BB_SIGHTING_SELECT . " WHERE s.store_id = ? ORDER BY s.seen_on DESC, s.id DESC LIMIT 200", [$st->id])->results();
$latest = [];
foreach ($rows as $r) if (!isset($latest[$r->id])) $latest[$r->id] = $r;
$in = bb_me() > 0;
?>
<nav aria-label="breadcrumb" class="mb-3">
  <ol class="breadcrumb small mb-0">
    <li class="breadcrumb-item"><a href="<?= bb_url('bourbon/sightings.php') ?>">Sightings</a></li>
    <li class="breadcrumb-item active" aria-current="page"><?= bb_h($st->name) ?></li>
  </ol>
</nav>
<div class="bb-page-head d-flex flex-wrap justify-content-between align-items-end gap-3">
  <div>
    <div class="bb-eyebrow mb-2">Store</div>
    <h1><?= bb_h($st->name) ?></h1>
    <p><i class="fa-solid fa-location-dot me-1"></i><?= bb_h($st->city . ', ' . $st->state) ?><span class="mx-2">·</span><?= count($latest) ?> bottle<?= count($latest) === 1 ? '' : 's' ?> spotted here</p>
  </div>
  <?php if ($in) echo bb_open_button('sighting', null, null, '<i class="fa-solid fa-plus me-2"></i>Log a sighting here', 'btn btn-primary', ['store' => bb_store_label($st)]); ?>
</div>

<?php if (!$latest) {
  echo bb_empty('fa-store', 'Nothing logged here yet', 'Seen a bottle at this store? Log it.', false);
} else { ?>
  <ul class="bb-rows">
    <?php foreach ($latest as $r) { ?>
      <li class="bb-row">
        <?= bb_thumb($r) ?>
        <div class="bb-row-main">
          <div class="bb-card-dist"><?= bb_h($r->distillery_name) ?></div>
          <a class="bb-row-name" href="<?= bb_h(bb_url('bourbon/bottle.php', ['id' => $r->id])) ?>"><?= bb_h($r->name) ?></a>
          <div class="bb-row-private">Last seen <?= bb_ago($r->seen_on) ?> by <?= bb_reporter($r) ?><?= $r->notes ? ' · “' . bb_h($r->notes) . '”' : '' ?></div>
        </div>
        <div class="text-end flex-none"><div class="bb-sight-price"><?= bb_money($r->price) ?></div>
          <?php if ($r->msrp !== null) { ?><div class="small text-secondary">typical ~<?= '$' . number_format($r->msrp) ?></div><?php } ?></div>
      </li>
    <?php } ?>
  </ul>
<?php } ?>
<?php if ($in) echo bb_list_forms(); ?>
<?php require_once $abs_us_root . $us_url_root . 'users/includes/html_footer.php'; ?>
