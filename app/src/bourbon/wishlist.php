<?php
require_once '../users/init.php';
$pageTitle = 'Wishlist —';
require_once __DIR__ . '/inc/lists.php';
// Count new deals and mark them seen before the nav renders, so its badge clears right away.
$me = bb_me();
$newDeals = 0;
if ($me) { $newDeals = bb_new_deals_count($me); bb_mark_deals_seen($me); }
require_once $abs_us_root . $us_url_root . 'users/includes/template/prep.php';
if (!securePage(Server::get('PHP_SELF'))) { die(); }

$sort = in_array(bb_get('sort'), ['recent', 'name', 'price'], true) ? bb_get('sort') : 'recent';
$rows = bb_wishlist_rows($me, $sort);
?>
<div class="bb-page-head d-flex flex-wrap justify-content-between align-items-end gap-3">
  <div>
    <h1>Wishlist</h1>
    <p>Bottles you’re hunting for. Tap “Got it” when you find one and it moves to your collection.</p>
  </div>
  <a class="btn btn-primary" href="<?= bb_url('bourbon/browse.php') ?>"><i class="fa-solid fa-plus me-2"></i>Add a bottle</a>
</div>

<?php if ($newDeals) { ?>
  <div class="bb-deals mb-3"><i class="fa-solid fa-bell me-2"></i><strong><?= $newDeals ?> new deal<?= $newDeals === 1 ? '' : 's' ?></strong> since you last looked. Look for the <span class="badge bb-good">Deal</span> badge below.</div>
<?php } ?>
<?php if ($rows) { ?>
  <div class="d-flex justify-content-between align-items-center mb-3">
    <span class="text-secondary small"><?= count($rows) ?> bottle<?= count($rows) === 1 ? '' : 's' ?></span>
    <form method="get" class="d-flex align-items-center gap-2">
      <label class="small text-secondary" for="sort">Sort</label>
      <select class="form-select form-select-sm w-auto" id="sort" name="sort" data-autosubmit>
        <option value="recent"<?= $sort === 'recent' ? ' selected' : '' ?>>Recently added</option>
        <option value="name"<?= $sort === 'name' ? ' selected' : '' ?>>Name</option>
        <option value="price"<?= $sort === 'price' ? ' selected' : '' ?>>Price</option>
      </select>
    </form>
  </div>
<?php } ?>

<?= $rows ? bb_render_wishlist($rows, true) : bb_empty('fa-star', 'Your wishlist is empty', 'Open any bottle in the catalog and tap “Add to wishlist”.') ?>
<p class="small text-secondary mt-4"><i class="fa-solid fa-bell me-1"></i>Set a target price and you’ll get a badge on Wishlist when another member spots the bottle at or below it (sightings from the last <?= BB_DEAL_DAYS ?> days).</p>

<?= bb_list_forms() ?>
<script nonce="<?= bb_h($userspice_nonce ?? '') ?>">document.querySelectorAll('[data-autosubmit]').forEach(function (s) { s.addEventListener('change', function () { s.form.submit(); }); });</script>
<?php require_once $abs_us_root . $us_url_root . 'users/includes/html_footer.php'; ?>
