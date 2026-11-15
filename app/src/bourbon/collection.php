<?php
require_once '../users/init.php';
$pageTitle = 'My collection —';
require_once $abs_us_root . $us_url_root . 'users/includes/template/prep.php';
if (!securePage(Server::get('PHP_SELF'))) { die(); }
require_once __DIR__ . '/inc/lists.php';

$me = bb_me();
$status = in_array(bb_get('status'), ['shelf', 'open', 'sealed', 'finished', 'all'], true) ? bb_get('status') : 'shelf';
$sort = in_array(bb_get('sort'), ['recent', 'name', 'proof'], true) ? bb_get('sort') : 'recent';
$rows = bb_collection_rows($me, $status, $sort);
$s = $db->query("SELECT
    COALESCE(SUM(CASE WHEN c.status <> 'finished' THEN c.quantity END),0) AS shelf,
    COALESCE(SUM(CASE WHEN c.status = 'open' THEN c.quantity END),0) AS open_n,
    COALESCE(SUM(CASE WHEN c.status = 'sealed' THEN c.quantity END),0) AS sealed,
    COALESCE(SUM(CASE WHEN c.status = 'finished' THEN c.quantity END),0) AS finished,
    SUM(c.purchase_price * c.quantity) AS spent,
    SUM(CASE WHEN c.status <> 'finished' THEN p.msrp * c.quantity END) AS value
  FROM bb_collection c JOIN bb_products p ON p.id = c.product_id WHERE c.user_id = ?", [$me])->first();
?>
<div class="bb-page-head d-flex flex-wrap justify-content-between align-items-end gap-3">
  <div>
    <h1>My collection</h1>
    <p>Bottles on your shelf. Add more from any bottle page in the catalog.</p>
  </div>
  <a class="btn btn-primary" href="<?= bb_url('bourbon/browse.php') ?>"><i class="fa-solid fa-plus me-2"></i>Add a bottle</a>
</div>

<div class="bb-statrow mb-4">
  <div><strong><?= (int)$s->shelf ?></strong><span>on the shelf</span></div>
  <div><strong><?= (int)$s->open_n ?></strong><span>open</span></div>
  <div><strong><?= (int)$s->sealed ?></strong><span>sealed</span></div>
  <div><strong><?= (int)$s->finished ?></strong><span>finished</span></div>
  <div><strong><?= $s->spent !== null ? '$' . number_format($s->spent) : '—' ?></strong><span>spent <i class="fa-solid fa-lock small" title="Only you can see this"></i></span></div>
  <div><strong><?= $s->value !== null ? '~$' . number_format($s->value) : '—' ?></strong><span>shelf at typical retail</span></div>
</div>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
  <?= bb_tabs(['shelf' => 'On the shelf', 'open' => 'Open', 'sealed' => 'Sealed', 'finished' => 'Finished', 'all' => 'All'], $status, 'status', ['sort' => $sort === 'recent' ? '' : $sort]) ?>
  <form method="get" class="d-flex align-items-center gap-2">
    <input type="hidden" name="status" value="<?= bb_h($status) ?>">
    <label class="small text-secondary" for="sort">Sort</label>
    <select class="form-select form-select-sm w-auto" id="sort" name="sort" data-autosubmit>
      <option value="recent"<?= $sort === 'recent' ? ' selected' : '' ?>>Recently added</option>
      <option value="name"<?= $sort === 'name' ? ' selected' : '' ?>>Name</option>
      <option value="proof"<?= $sort === 'proof' ? ' selected' : '' ?>>Proof</option>
    </select>
  </form>
</div>

<?= $rows ? bb_render_collection($rows, true)
  : ($s->shelf + $s->finished ? bb_empty('fa-box-archive', 'Nothing here', 'No bottles match this filter.', false)
     : bb_empty('fa-box-archive', 'Your shelf is empty', 'Find a bottle in the catalog and tap “Add to collection”.')) ?>

<?= bb_list_forms() ?>
<script nonce="<?= bb_h($userspice_nonce ?? '') ?>">document.querySelectorAll('[data-autosubmit]').forEach(function (s) { s.addEventListener('change', function () { s.form.submit(); }); });</script>
<?php require_once $abs_us_root . $us_url_root . 'users/includes/html_footer.php'; ?>
