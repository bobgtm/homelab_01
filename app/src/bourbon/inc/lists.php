<?php
// List renderers shared by the owner's pages (editable) and public profiles (read-only).
// Read-only views never include purchase prices, stores, dates bought, or private notes.
if (!isset($abs_us_root)) { http_response_code(404); exit; }
require_once __DIR__ . '/forms.php';

function bb_thumb($p) {
  return '<a class="bb-thumb" href="' . bb_h(bb_url('bourbon/bottle.php', ['id' => $p->product_id ?? $p->id])) . '" tabindex="-1" aria-hidden="true">' . bb_bottle_image($p) . '</a>';
}

function bb_product_link($p) {
  return '<a class="bb-row-name" href="' . bb_h(bb_url('bourbon/bottle.php', ['id' => $p->product_id])) . '">' . bb_h($p->name) . '</a>';
}

/** Product columns joined onto a list query (aliased so bb_bottle_image() works). */
const BB_PRODUCT_COLS = "p.id AS pid, p.slug, p.name, p.brand, p.proof, p.age_years, p.msrp, p.is_barrel_proof, p.is_bottled_in_bond,
  p.is_single_barrel, p.is_small_batch, p.is_finished, p.is_limited, p.mash_style, p.image_file, d.name AS distillery_name";

function bb_fix_ids($rows) {
  // bb_bottle_art() keys gradients by ->id; give it the product id, keep the row id separately.
  foreach ($rows as $r) { $r->row_id = $r->id; $r->id = $r->pid; }
  return $rows;
}

function bb_empty($icon, $title, $text, $cta = true) {
  return '<div class="bb-empty"><div class="bb-feature-icon"><i class="fa-solid ' . $icon . '"></i></div><h2 class="h5">' . $title . '</h2><p class="mb-3">' . $text . '</p>'
    . ($cta ? '<a class="btn btn-primary btn-sm" href="' . bb_url('bourbon/browse.php') . '">Browse the catalog</a>' : '') . '</div>';
}

// ---------------- Collection ----------------
function bb_collection_rows($uid, $status = 'shelf', $sort = 'recent') {
  global $db;
  $where = $status === 'shelf' ? "c.status <> 'finished'" : ($status === 'all' ? '1' : 'c.status = ?');
  $params = [$uid]; if (!in_array($status, ['shelf', 'all'], true)) $params[] = $status;
  $order = ['recent' => 'c.created_at DESC', 'name' => 'p.name ASC', 'proof' => 'p.proof IS NULL, p.proof DESC'][$sort] ?? 'c.created_at DESC';
  return bb_fix_ids($db->query("SELECT c.*, " . BB_PRODUCT_COLS . " FROM bb_collection c JOIN bb_products p ON p.id = c.product_id
     JOIN bb_distilleries d ON d.id = p.distillery_id WHERE c.user_id = ? AND $where ORDER BY $order, c.id DESC", $params)->results());
}

function bb_render_collection($rows, $editable) {
  if (!$rows) return '';
  ob_start(); ?>
  <ul class="bb-rows">
    <?php foreach ($rows as $r) { $item = ['id' => $r->row_id, 'quantity' => $r->quantity, 'status' => $r->status, 'fill_level' => $r->fill_level,
      'purchase_price' => $r->purchase_price, 'purchase_date' => $r->purchase_date, 'store_name' => $r->store_name, 'notes' => $r->notes]; ?>
      <li class="bb-row">
        <?= bb_thumb($r) ?>
        <div class="bb-row-main">
          <div class="bb-card-dist"><?= bb_h($r->distillery_name) ?></div>
          <?= bb_product_link($r) ?>
          <div class="bb-row-meta">
            <span class="badge bb-badge bb-status-<?= $r->status ?>"><?= ucfirst($r->status) ?></span>
            <?php if ($r->quantity > 1) { ?><span>× <?= (int)$r->quantity ?></span><?php } ?>
            <?php if ($r->status === 'open' && $r->fill_level !== null) { ?>
              <span class="bb-fill" title="<?= (int)$r->fill_level ?>% left"><span style="width:<?= (int)$r->fill_level ?>%"></span></span><span><?= (int)$r->fill_level ?>% left</span>
            <?php } ?>
            <?php if ($f = bb_facts($r)) { ?><span class="text-secondary"><?= bb_h($f) ?></span><?php } ?>
          </div>
          <?php if ($editable) {
            $priv = array_filter([$r->purchase_price !== null ? 'Paid ' . bb_money($r->purchase_price) : null, $r->store_name, $r->purchase_date ? 'Bought ' . bb_date($r->purchase_date) : null]); ?>
            <?php if ($priv) { ?><div class="bb-row-private"><?= bb_h(implode(' · ', $priv)) ?></div><?php } ?>
            <?php if ($r->notes) { ?><div class="bb-row-private fst-italic"><?= bb_h($r->notes) ?></div><?php } ?>
          <?php } ?>
        </div>
        <?php if ($editable) { ?><div class="bb-row-actions"><?= bb_open_button('collection', (object)['id' => $r->pid, 'name' => $r->name], (object)$item) ?></div><?php } ?>
      </li>
    <?php } ?>
  </ul>
  <?php return ob_get_clean();
}

// ---------------- Tried ----------------
function bb_tried_groups($uid, $sort = 'recent') {
  global $db;
  $rows = bb_fix_ids($db->query("SELECT t.*, " . BB_PRODUCT_COLS . " FROM bb_tastings t JOIN bb_products p ON p.id = t.product_id
     JOIN bb_distilleries d ON d.id = p.distillery_id WHERE t.user_id = ? ORDER BY t.tasted_on DESC, t.id DESC", [$uid])->results());
  $groups = [];
  foreach ($rows as $r) {
    $g = $groups[$r->pid] ?? ($groups[$r->pid] = (object)['p' => $r, 'tastings' => [], 'sum' => 0, 'n' => 0, 'last' => $r->tasted_on]);
    $g->tastings[] = $r;
    if ($r->rating !== null) { $g->sum += $r->rating; $g->n++; }
  }
  foreach ($groups as $g) $g->avg = $g->n ? $g->sum / $g->n : null;
  if ($sort === 'rating') uasort($groups, fn($a, $b) => [$b->avg ?? -1, $b->last] <=> [$a->avg ?? -1, $a->last]);
  if ($sort === 'name') uasort($groups, fn($a, $b) => strcasecmp($a->p->name, $b->p->name));
  return $groups;
}

function bb_render_tried($groups, $editable) {
  if (!$groups) return '';
  ob_start(); ?>
  <ul class="bb-rows">
    <?php foreach ($groups as $g) { $p = $g->p; ?>
      <li class="bb-row align-items-start">
        <?= bb_thumb($p) ?>
        <div class="bb-row-main">
          <div class="bb-card-dist"><?= bb_h($p->distillery_name) ?></div>
          <?= bb_product_link($p) ?>
          <div class="bb-row-meta">
            <?= bb_stars($g->avg === null ? null : round($g->avg * 2) / 2) ?>
            <?php if ($g->avg !== null) { ?><strong><?= number_format($g->avg, 1) ?></strong><?php } ?>
            <span class="text-secondary"><?= count($g->tastings) ?> tasting<?= count($g->tastings) === 1 ? '' : 's' ?></span>
          </div>
          <ul class="bb-tastings">
            <?php foreach ($g->tastings as $t) {
              $notes = array_filter(['Nose' => $t->nose, 'Palate' => $t->palate, 'Finish' => $t->finish]); ?>
              <li>
                <div class="d-flex flex-wrap align-items-center gap-2">
                  <span class="small text-secondary"><?= bb_date($t->tasted_on) ?: 'Undated' ?></span>
                  <?php if ($t->rating !== null) { ?><span class="small"><?= bb_stars($t->rating) ?></span><?php } ?>
                  <?php if ($editable) { ?><span class="ms-auto"><?= bb_open_button('tasting', (object)['id' => $t->pid, 'name' => $t->name],
                    (object)['id' => $t->row_id, 'rating' => $t->rating, 'tasted_on' => $t->tasted_on, 'nose' => $t->nose, 'palate' => $t->palate, 'finish' => $t->finish, 'notes' => $t->notes],
                    'Edit', 'btn btn-link btn-sm p-0') ?></span><?php } ?>
                </div>
                <?php foreach ($notes as $k => $v) { ?><div class="small"><span class="text-secondary"><?= $k ?>:</span> <?= bb_h($v) ?></div><?php } ?>
                <?php if ($t->notes) { ?><div class="small fst-italic"><?= bb_h($t->notes) ?></div><?php } ?>
              </li>
            <?php } ?>
          </ul>
        </div>
        <?php if ($editable) { ?><div class="bb-row-actions"><?= bb_open_button('tasting', (object)['id' => $p->pid, 'name' => $p->name], null, 'Log again') ?></div><?php } ?>
      </li>
    <?php } ?>
  </ul>
  <?php return ob_get_clean();
}

// ---------------- Wishlist ----------------
function bb_wishlist_rows($uid, $sort = 'recent') {
  global $db;
  $order = ['recent' => 'w.created_at DESC', 'name' => 'p.name ASC', 'price' => 'COALESCE(w.target_price, p.msrp) IS NULL, COALESCE(w.target_price, p.msrp) ASC'][$sort] ?? 'w.created_at DESC';
  return bb_fix_ids($db->query("SELECT w.*, " . BB_PRODUCT_COLS . " FROM bb_wishlist w JOIN bb_products p ON p.id = w.product_id
     JOIN bb_distilleries d ON d.id = p.distillery_id WHERE w.user_id = ? ORDER BY $order, w.id DESC", [$uid])->results());
}

function bb_render_wishlist($rows, $editable) {
  if (!$rows) return '';
  $best = bb_best_recent(array_map(fn($r) => $r->pid, $rows));
  ob_start(); ?>
  <ul class="bb-rows">
    <?php foreach ($rows as $r) { $prod = (object)['id' => $r->pid, 'name' => $r->name]; ?>
      <li class="bb-row">
        <?= bb_thumb($r) ?>
        <div class="bb-row-main">
          <div class="bb-card-dist"><?= bb_h($r->distillery_name) ?></div>
          <?= bb_product_link($r) ?>
          <div class="bb-row-meta">
            <?php if ($r->target_price !== null) { ?><span class="badge bb-badge">Target <?= bb_money($r->target_price) ?></span><?php } ?>
            <?php if ($f = bb_facts($r)) { ?><span class="text-secondary"><?= bb_h($f) ?></span><?php } ?>
          </div>
          <?php if ($b = $best[$r->pid] ?? null) { $deal = $r->target_price !== null && $b->price <= $r->target_price && $b->seen_on >= date('Y-m-d', strtotime('-' . BB_DEAL_DAYS . ' days')); ?>
            <div class="bb-row-meta">
              <?php if ($deal) { ?><span class="badge bb-good"><i class="fa-solid fa-bell me-1"></i>Deal</span><?php } ?>
              <span>Lowest recent <strong><?= bb_money($b->price) ?></strong> at <?= bb_store_link($b) ?> · <?= bb_ago($b->seen_on) ?></span>
            </div>
          <?php } else { ?>
            <div class="bb-row-private">No recent store sightings.</div>
          <?php } ?>
          <?php if ($editable && $r->notes) { ?><div class="bb-row-private fst-italic"><?= bb_h($r->notes) ?></div><?php } ?>
        </div>
        <?php if ($editable) { ?>
          <div class="bb-row-actions">
            <?= bb_open_button('collection', $prod, null, '<i class="fa-solid fa-check me-1"></i>Got it', 'btn btn-primary btn-sm', ['onWishlist' => true]) ?>
            <?= bb_open_button('wishlist', $prod, (object)['target_price' => $r->target_price, 'notes' => $r->notes]) ?>
          </div>
        <?php } ?>
      </li>
    <?php } ?>
  </ul>
  <?php return ob_get_clean();
}

/** Tab-style filter links. */
function bb_tabs(array $tabs, $current, $param, array $keep = []) {
  $h = '<nav class="bb-tabs" aria-label="Filter">';
  foreach ($tabs as $k => $label) {
    $url = '/' . ltrim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/') . '?' . http_build_query(array_filter($keep + [$param => $k]));
    $h .= '<a href="' . bb_h($url) . '" class="bb-tab' . ($k === $current ? ' active" aria-current="page' : '') . '">' . $label . '</a>';
  }
  return $h . '</nav>';
}
