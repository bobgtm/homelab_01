<?php
require_once '../users/init.php';
$pageTitle = 'Browse the catalog —';
require_once $abs_us_root . $us_url_root . 'users/includes/template/prep.php';
if (!securePage(Server::get('PHP_SELF'))) { die(); }
require_once __DIR__ . '/inc/catalog.php';

// ---------- Read filters ----------
$f = [
  'q'     => bb_get('q'),
  'd'     => (int)bb_get('d'),
  'style' => bb_get('style'),
  'proof' => bb_get('proof'),
  'age'   => bb_get('age'),
  'price' => bb_get('price'),
  'sort'  => bb_get('sort'),
];
$styles = [
  'bib' => ['Bottled-in-Bond', 'p.is_bottled_in_bond = 1'],
  'single' => ['Single Barrel', 'p.is_single_barrel = 1'],
  'barrel' => ['Barrel Proof', 'p.is_barrel_proof = 1'],
  'small' => ['Small Batch', 'p.is_small_batch = 1'],
  'finished' => ['Cask Finished', 'p.is_finished = 1'],
  'wheated' => ['Wheated', "p.mash_style = 'wheated'"],
  'highrye' => ['High Rye', "p.mash_style = 'high-rye'"],
  'limited' => ['Limited / Allocated', 'p.is_limited = 1'],
];
$proofs = [
  'lt90' => ['Under 90', 'p.proof < 90'], '90' => ['90 – 99', 'p.proof >= 90 AND p.proof < 100'],
  '100' => ['100 – 114', 'p.proof >= 100 AND p.proof < 115'],
  '115' => ['115 and up', '(p.proof >= 115 OR (p.proof IS NULL AND p.is_barrel_proof = 1))'],
];
$ages = ['stated' => ['Age stated', 'p.age_years IS NOT NULL'], '10' => ['10 years +', 'p.age_years >= 10'], '15' => ['15 years +', 'p.age_years >= 15']];
$prices = [
  'lt30' => ['Under $30', 'p.msrp < 30'], '30' => ['$30 – $59', 'p.msrp >= 30 AND p.msrp < 60'],
  '60' => ['$60 – $99', 'p.msrp >= 60 AND p.msrp < 100'], '100' => ['$100 +', 'p.msrp >= 100'],
];
$sorts = [
  ''      => ['Name (A–Z)', 'p.name ASC'],
  'proof' => ['Proof (high → low)', 'p.proof IS NULL, p.proof DESC, p.name'],
  'age'   => ['Age (oldest first)', 'p.age_years IS NULL, p.age_years DESC, p.name'],
  'cheap' => ['Price (low → high)', 'p.msrp IS NULL, p.msrp ASC, p.name'],
  'pricey'=> ['Price (high → low)', 'p.msrp IS NULL, p.msrp DESC, p.name'],
];
foreach (['style' => $styles, 'proof' => $proofs, 'age' => $ages, 'price' => $prices, 'sort' => $sorts] as $k => $opts) {
  if (!isset($opts[$f[$k]])) $f[$k] = '';
}

// ---------- Build query ----------
$where = ["p.status = 'approved'"]; $params = [];
if ($f['q'] !== '') {
  foreach (preg_split('/\s+/', mb_substr($f['q'], 0, 80)) as $word) {
    $where[] = '(p.name LIKE ? OR p.brand LIKE ? OR d.name LIKE ?)';
    $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $word) . '%';
    array_push($params, $like, $like, $like);
  }
}
if ($f['d'])     { $where[] = 'p.distillery_id = ?'; $params[] = $f['d']; }
if ($f['style']) $where[] = $styles[$f['style']][1];
if ($f['proof']) $where[] = $proofs[$f['proof']][1];
if ($f['age'])   $where[] = $ages[$f['age']][1];
if ($f['price']) $where[] = $prices[$f['price']][1];
$whereSql = implode(' AND ', $where);

$total = (int)$db->query("SELECT COUNT(*) AS n FROM bb_products p JOIN bb_distilleries d ON d.id = p.distillery_id WHERE $whereSql", $params)->first()->n;
$per = 24;
$pages = max(1, (int)ceil($total / $per));
$page = max(1, min($pages, (int)bb_get('page')));
$offset = ($page - 1) * $per;
$rows = $db->query(
  "SELECT p.*, d.name AS distillery_name FROM bb_products p JOIN bb_distilleries d ON d.id = p.distillery_id
    WHERE $whereSql ORDER BY {$sorts[$f['sort']][1]} LIMIT $per OFFSET $offset", $params)->results();

$distilleries = $db->query(
  "SELECT d.id, d.name, COUNT(p.id) AS n FROM bb_distilleries d JOIN bb_products p ON p.distillery_id = d.id AND p.status = 'approved'
    GROUP BY d.id, d.name ORDER BY d.name")->results();
$catalogCount = (int)$db->query("SELECT COUNT(*) AS n FROM bb_products WHERE status = 'approved'")->first()->n;

$active = array_filter($f, fn($v) => $v !== '' && $v !== 0);
unset($active['sort']);
$link = function (array $changes) use ($f) {
  $q = array_filter(array_merge($f, $changes), fn($v) => $v !== '' && $v !== 0 && $v !== null);
  return bb_url('bourbon/browse.php', $q);
};
$select = function ($name, $label, $opts, $current, $any = 'Any') {
  $h = '<label class="form-label small text-secondary mb-1" for="f-' . $name . '">' . $label . '</label>'
     . '<select class="form-select form-select-sm mb-3" id="f-' . $name . '" name="' . $name . '">';
  if ($any !== null) $h .= '<option value="">' . $any . '</option>';
  foreach ($opts as $k => $o) $h .= '<option value="' . bb_h($k) . '"' . ((string)$current === (string)$k ? ' selected' : '') . '>' . bb_h($o[0]) . '</option>';
  return $h . '</select>';
};
?>

<div class="bb-page-head d-flex flex-wrap align-items-end justify-content-between gap-2">
  <div>
    <h1>Browse the catalog</h1>
    <p><?= number_format($catalogCount) ?> bourbons from <?= count($distilleries) ?> distilleries and producers.</p>
  </div>
</div>

<div class="row g-4">
  <aside class="col-lg-3">
    <button class="btn btn-outline-primary w-100 d-lg-none mb-3" type="button" data-bs-toggle="collapse" data-bs-target="#bbFilters" aria-expanded="false" aria-controls="bbFilters">
      <i class="fa-solid fa-sliders me-2"></i>Search &amp; filters<?= $active ? ' (' . count($active) . ')' : '' ?>
    </button>
    <form class="bb-filters collapse d-lg-block" id="bbFilters" method="get" action="">
      <label class="form-label small text-secondary mb-1" for="f-q">Search</label>
      <div class="input-group input-group-sm mb-3">
        <span class="input-group-text"><i class="fa-solid fa-magnifying-glass"></i></span>
        <input class="form-control" id="f-q" type="search" name="q" value="<?= bb_h($f['q']) ?>" placeholder="Name, brand or distillery">
      </div>
      <label class="form-label small text-secondary mb-1" for="f-d">Distillery / producer</label>
      <select class="form-select form-select-sm mb-3" id="f-d" name="d">
        <option value="">All</option>
        <?php foreach ($distilleries as $d) { ?>
          <option value="<?= $d->id ?>"<?= $f['d'] == $d->id ? ' selected' : '' ?>><?= bb_h($d->name) ?> (<?= $d->n ?>)</option>
        <?php } ?>
      </select>
      <?= $select('style', 'Style', $styles, $f['style']) ?>
      <?= $select('proof', 'Proof', $proofs, $f['proof']) ?>
      <?= $select('age', 'Age', $ages, $f['age']) ?>
      <?= $select('price', 'Typical price', $prices, $f['price']) ?>
      <?= $select('sort', 'Sort by', $sorts, $f['sort'], null) ?>
      <div class="d-flex gap-2">
        <button class="btn btn-primary btn-sm flex-grow-1" type="submit">Apply</button>
        <?php if ($active) { ?><a class="btn btn-outline-primary btn-sm" href="<?= bb_url('bourbon/browse.php') ?>">Clear</a><?php } ?>
      </div>
    </form>
  </aside>

  <section class="col-lg-9" aria-live="polite">
    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
      <span class="text-secondary small me-1"><?= number_format($total) ?> result<?= $total === 1 ? '' : 's' ?></span>
      <?php foreach ($active as $k => $v) {
        $label = $k === 'q' ? '“' . $v . '”' : ($k === 'd' ? (array_values(array_filter($distilleries, fn($d) => $d->id == $v))[0]->name ?? '') :
                 [$k === 'style' ? $styles : ($k === 'proof' ? $proofs : ($k === 'age' ? $ages : $prices))][0][$v][0]); ?>
        <a class="bb-chip" href="<?= bb_h($link([$k => ''])) ?>" aria-label="Remove filter <?= bb_h($label) ?>"><?= bb_h($label) ?> <i class="fa-solid fa-xmark ms-1"></i></a>
      <?php } ?>
    </div>

    <?php if (!$rows) { ?>
      <div class="bb-empty">
        <div class="bb-feature-icon"><i class="fa-solid fa-whiskey-glass"></i></div>
        <h2 class="h5">No bottles match those filters</h2>
        <p class="mb-3">Try a shorter search or remove a filter. Still missing? Suggest it and we’ll add it.</p>
        <a class="btn btn-outline-primary btn-sm" href="<?= bb_url('bourbon/browse.php') ?>">Clear all filters</a>
        <a class="btn btn-primary btn-sm" href="<?= bb_h(bb_url('bourbon/suggest.php', array_filter(['name' => $f['q']]))) ?>">Suggest a bottle</a>
      </div>
    <?php } else { ?>
      <div class="bb-grid">
        <?php foreach ($rows as $p) echo bb_product_card($p); ?>
      </div>

      <?php if ($pages > 1) { ?>
        <nav class="mt-4" aria-label="Catalog pages">
          <ul class="pagination pagination-sm justify-content-center flex-wrap">
            <li class="page-item<?= $page <= 1 ? ' disabled' : '' ?>"><a class="page-link" href="<?= bb_h($link(['page' => $page - 1])) ?>">Previous</a></li>
            <?php for ($i = 1; $i <= $pages; $i++) {
              if ($i > 2 && $i < $pages - 1 && abs($i - $page) > 1) { if ($i == 3 || $i == $pages - 2) echo '<li class="page-item disabled"><span class="page-link">…</span></li>'; continue; } ?>
              <li class="page-item<?= $i === $page ? ' active' : '' ?>"><a class="page-link" href="<?= bb_h($link(['page' => $i])) ?>"<?= $i === $page ? ' aria-current="page"' : '' ?>><?= $i ?></a></li>
            <?php } ?>
            <li class="page-item<?= $page >= $pages ? ' disabled' : '' ?>"><a class="page-link" href="<?= bb_h($link(['page' => $page + 1])) ?>">Next</a></li>
          </ul>
        </nav>
      <?php } ?>
    <?php } ?>
    <p class="small text-secondary mt-4 mb-0">
      Prices are approximate typical retail and vary by state and store. Bottle art is illustrative unless a photo is shown.
      <a href="<?= bb_url('bourbon/credits.php') ?>">Photo credits</a> · Missing a bottle? <a href="<?= bb_url('bourbon/suggest.php') ?>">Suggest it</a>
    </p>
  </section>
</div>

<script nonce="<?= htmlspecialchars($userspice_nonce ?? '') ?>">
// Apply select filters immediately on change (search still uses the button or Enter).
document.querySelectorAll('#bbFilters select').forEach(function (s) {
  s.addEventListener('change', function () { s.form.submit(); });
});
</script>

<?php require_once $abs_us_root . $us_url_root . 'users/includes/html_footer.php'; ?>
