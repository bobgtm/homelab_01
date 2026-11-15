<?php
require_once '../users/init.php';
require_once __DIR__ . '/inc/catalog.php';

$id = (int)bb_get('id');
$d = $id ? $db->query("SELECT * FROM bb_distilleries WHERE id = ?", [$id])->first() : null;
if ($d) $pageTitle = $d->name . ' —';

require_once $abs_us_root . $us_url_root . 'users/includes/template/prep.php';
if (!securePage(Server::get('PHP_SELF'))) { die(); }

if (!$d) { http_response_code(404); ?>
  <div class="bb-empty my-5">
    <h1 class="h4">We couldn’t find that distillery</h1>
    <a class="btn btn-primary btn-sm" href="<?= bb_url('bourbon/browse.php') ?>">Browse the catalog</a>
  </div>
  <?php require_once $abs_us_root . $us_url_root . 'users/includes/html_footer.php'; exit;
}

$rows = $db->query(
  "SELECT p.*, ? AS distillery_name FROM bb_products p WHERE p.distillery_id = ? AND p.status = 'approved'
    ORDER BY p.brand, p.name", [$d->name, $d->id])->results();
$brands = [];
foreach ($rows as $r) $brands[$r->brand][] = $r;
// Optional brand filter (?brand=...). Unknown values fall back to "see all".
$pick = bb_get('brand');
if (!isset($brands[$pick])) $pick = '';
$shown = $pick !== '' ? [$pick => $brands[$pick]] : $brands;
$place = bb_place($d);
?>

<nav aria-label="breadcrumb" class="mb-3">
  <ol class="breadcrumb small mb-0">
    <li class="breadcrumb-item"><a href="<?= bb_url('bourbon/browse.php') ?>">Catalog</a></li>
    <li class="breadcrumb-item active" aria-current="page"><?= bb_h($d->name) ?></li>
  </ol>
</nav>

<div class="bb-page-head">
  <div class="bb-eyebrow mb-2"><?= $d->kind === 'producer' ? 'Brand / producer' : 'Distillery' ?></div>
  <h1><?= bb_h($d->name) ?></h1>
  <p>
    <?php if ($place) { ?><i class="fa-solid fa-location-dot me-1"></i><?= bb_h($place) ?><?php } ?>
    <?php if ($d->owner) { ?><span class="mx-2">·</span>Owned by <?= bb_h($d->owner) ?><?php } ?>
    <span class="mx-2">·</span><?= count($rows) ?> bottle<?= count($rows) === 1 ? '' : 's' ?> in the catalog
  </p>
  <?php if ($d->kind === 'producer') { ?>
    <p class="small mt-2">This brand sources or blends whiskey rather than (or in addition to) distilling it all itself.</p>
  <?php } ?>
</div>

<?php if (count($brands) > 1) { ?>
  <nav class="bb-brandfilter mb-4" aria-label="Filter by brand">
    <span class="bb-brandfilter-label">Filter by brand:</span>
    <?php foreach ($brands as $brand => $list) { $on = $brand === $pick; ?>
      <a class="btn btn-sm <?= $on ? 'btn-primary' : 'btn-outline-primary' ?>" href="<?= bb_h(bb_url('bourbon/distillery.php', ['id' => $d->id, 'brand' => $brand])) ?>"<?= $on ? ' aria-current="true"' : '' ?>>
        <?= bb_h($brand) ?> <span class="bb-brandfilter-n"><?= count($list) ?></span>
      </a>
    <?php } ?>
    <a class="btn btn-sm <?= $pick === '' ? 'btn-primary' : 'btn-outline-primary' ?> bb-brandfilter-all" href="<?= bb_h(bb_url('bourbon/distillery.php', ['id' => $d->id])) ?>"<?= $pick === '' ? ' aria-current="true"' : '' ?>>
      <i class="fa-solid fa-layer-group me-1"></i>See all <span class="bb-brandfilter-n"><?= count($rows) ?></span>
    </a>
  </nav>
<?php } ?>

<?php foreach ($shown as $brand => $list) { ?>
  <section class="mb-5" id="brand-<?= bb_h(md5($brand)) ?>">
    <?php if (count($brands) > 1) { ?><h2 class="h4 mb-3"><?= bb_h($brand) ?></h2><?php } ?>
    <div class="bb-grid bb-grid-4">
      <?php foreach ($list as $p) echo bb_product_card($p); ?>
    </div>
  </section>
<?php } ?>

<?php require_once $abs_us_root . $us_url_root . 'users/includes/html_footer.php'; ?>
