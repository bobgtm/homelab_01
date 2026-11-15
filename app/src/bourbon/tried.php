<?php
require_once '../users/init.php';
$pageTitle = 'Tried —';
require_once $abs_us_root . $us_url_root . 'users/includes/template/prep.php';
if (!securePage(Server::get('PHP_SELF'))) { die(); }
require_once __DIR__ . '/inc/lists.php';

$me = bb_me();
$sort = in_array(bb_get('sort'), ['recent', 'rating', 'name'], true) ? bb_get('sort') : 'recent';
$groups = bb_tried_groups($me, $sort);
$n = array_sum(array_map(fn($g) => count($g->tastings), $groups));
$rated = array_filter($groups, fn($g) => $g->avg !== null);
$avg = $rated ? array_sum(array_map(fn($g) => $g->avg, $rated)) / count($rated) : null;
$fav = $rated ? array_values($rated) : [];
usort($fav, fn($a, $b) => $b->avg <=> $a->avg);
?>
<div class="bb-page-head d-flex flex-wrap justify-content-between align-items-end gap-3">
  <div>
    <h1>Tried</h1>
    <p>Everything you’ve tasted, with your ratings and notes.</p>
  </div>
  <a class="btn btn-primary" href="<?= bb_url('bourbon/browse.php') ?>"><i class="fa-solid fa-plus me-2"></i>Log a tasting</a>
</div>

<div class="bb-statrow mb-4">
  <div><strong><?= count($groups) ?></strong><span>bourbons tried</span></div>
  <div><strong><?= $n ?></strong><span>tastings logged</span></div>
  <div><strong><?= $avg !== null ? number_format($avg, 1) : '—' ?></strong><span>average rating</span></div>
  <div class="bb-stat-wide"><strong class="text-truncate"><?= $fav ? bb_h($fav[0]->p->name) : '—' ?></strong><span>top rated</span></div>
</div>

<?php if ($groups) { ?>
  <div class="d-flex justify-content-end mb-3">
    <form method="get" class="d-flex align-items-center gap-2">
      <label class="small text-secondary" for="sort">Sort</label>
      <select class="form-select form-select-sm w-auto" id="sort" name="sort" data-autosubmit>
        <option value="recent"<?= $sort === 'recent' ? ' selected' : '' ?>>Recently tasted</option>
        <option value="rating"<?= $sort === 'rating' ? ' selected' : '' ?>>Highest rated</option>
        <option value="name"<?= $sort === 'name' ? ' selected' : '' ?>>Name</option>
      </select>
    </form>
  </div>
<?php } ?>

<?= $groups ? bb_render_tried($groups, true) : bb_empty('fa-whiskey-glass', 'No tastings yet', 'Open any bottle in the catalog and tap “Log a tasting”.') ?>

<?= bb_list_forms() ?>
<script nonce="<?= bb_h($userspice_nonce ?? '') ?>">document.querySelectorAll('[data-autosubmit]').forEach(function (s) { s.addEventListener('change', function () { s.form.submit(); }); });</script>
<?php require_once $abs_us_root . $us_url_root . 'users/includes/html_footer.php'; ?>
