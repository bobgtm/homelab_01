<?php
// Catalog admin hub: review queue, bottles, distilleries. Administrator permission only (securePage).
require_once '../users/init.php';
require_once __DIR__ . '/inc/admin.php';
require_once __DIR__ . '/inc/lists.php';

// Review actions (approve / reject a member suggestion).
if ($_SERVER['REQUEST_METHOD'] === 'POST' && hasPerm([2])) {
  $back = bb_url('bourbon/admin.php', ['tab' => 'queue']);
  if (!Token::check($_POST['csrf'] ?? '')) { usError('Your session expired. Please try again.'); Redirect::to($back); exit; }
  $pid = (int)($_POST['id'] ?? 0);
  $row = $db->query("SELECT * FROM bb_products WHERE id = ? AND status = 'pending'", [$pid])->first();
  if ($row && ($_POST['do'] ?? '') === 'approve') {
    $db->update('bb_products', $row->id, ['status' => 'approved', 'admin_locked' => 1, 'reviewed_by' => bb_me(), 'reviewed_at' => date('Y-m-d H:i:s'), 'review_note' => null]);
    usSuccess('Approved: ' . $row->name . ' is now in the catalog.');
  } elseif ($row && ($_POST['do'] ?? '') === 'reject') {
    $db->update('bb_products', $row->id, ['status' => 'rejected', 'reviewed_by' => bb_me(), 'reviewed_at' => date('Y-m-d H:i:s'),
      'review_note' => mb_substr(trim((string)($_POST['note'] ?? '')), 0, 300) ?: null]);
    usSuccess('Suggestion declined.');
  }
  Redirect::to($back); exit;
}

$pending = bb_pending_count();
$tab = in_array(bb_get('tab'), ['queue', 'bottles', 'distilleries'], true) ? bb_get('tab') : ($pending ? 'queue' : 'bottles');
$pageTitle = 'Catalog admin —';
require_once $abs_us_root . $us_url_root . 'users/includes/template/prep.php';
if (!securePage(Server::get('PHP_SELF'))) { die(); }

$tabs = ['queue' => 'Suggestions' . ($pending ? ' <span class="bb-count bb-count-hot">' . $pending . '</span>' : ''), 'bottles' => 'Bottles', 'distilleries' => 'Distilleries'];
$totals = $db->query("SELECT (SELECT COUNT(*) FROM bb_products WHERE status='approved') AS live, (SELECT COUNT(*) FROM bb_products WHERE status='hidden') AS hidden,
  (SELECT COUNT(*) FROM bb_products WHERE status='approved' AND image_file IS NOT NULL) AS photos, (SELECT COUNT(*) FROM bb_distilleries) AS dists")->first();
?>
<div class="bb-page-head d-flex flex-wrap justify-content-between align-items-end gap-3">
  <div>
    <h1>Catalog admin</h1>
    <p><?= number_format($totals->live) ?> bottles live · <?= $totals->photos ?> with photos · <?= $totals->hidden ?> hidden · <?= $totals->dists ?> distilleries</p>
  </div>
  <div class="d-flex gap-2">
    <a class="btn btn-outline-primary" href="<?= bb_url('bourbon/admin_distillery.php') ?>"><i class="fa-solid fa-plus me-2"></i>Distillery</a>
    <a class="btn btn-primary" href="<?= bb_url('bourbon/admin_bottle.php') ?>"><i class="fa-solid fa-plus me-2"></i>Bottle</a>
  </div>
</div>
<div class="mb-3"><?= bb_tabs($tabs, $tab, 'tab') ?></div>

<?php if ($tab === 'queue') {
  $rows = $db->query("SELECT p.*, d.name AS distillery_name, d.submitted_by AS dist_submitted_by, d.city AS dcity, d.state AS dstate, u.username
      FROM bb_products p JOIN bb_distilleries d ON d.id = p.distillery_id LEFT JOIN users u ON u.id = p.submitted_by
     WHERE p.status = 'pending' ORDER BY p.created_at")->results();
  if (!$rows) echo bb_empty('fa-inbox', 'No suggestions waiting', 'When members suggest a missing bottle, it shows up here for review.', false);
  foreach ($rows as $r) {
    // Possible duplicates already in the catalog.
    $words = array_slice(array_filter(preg_split('/\s+/', preg_replace('/[^a-z0-9 ]/', ' ', strtolower($r->name))), fn($w) => strlen($w) > 2), 0, 3);
    $dupes = [];
    if ($words) {
      $cond = implode(' AND ', array_fill(0, count($words), 'LOWER(name) LIKE ?'));
      $dupes = $db->query("SELECT id, name FROM bb_products WHERE status = 'approved' AND $cond LIMIT 5", array_map(fn($w) => "%$w%", $words))->results();
    } ?>
    <article class="bb-panel mb-3">
      <div class="d-flex flex-wrap justify-content-between gap-3">
        <div class="min-w-0">
          <div class="bb-card-dist"><?= bb_h($r->distillery_name) ?><?= $r->dist_submitted_by && $r->dist_submitted_by == $r->submitted_by ? ' <span class="badge bb-badge ms-1">new distillery</span>' : '' ?></div>
          <h2 class="h5 mb-1"><?= bb_h($r->name) ?></h2>
          <div class="small text-secondary"><?= bb_h(implode(' · ', array_filter([bb_facts($r), implode(', ', bb_tags($r))]))) ?: 'No details given' ?></div>
          <div class="small mt-1">Suggested by <?= $r->username ? '<a href="' . bb_h(bb_profile_url($r->username)) . '">@' . bb_h($r->username) . '</a>' : 'a member' ?> · <?= bb_date($r->created_at) ?></div>
          <?php if ($r->submitter_notes) { ?><div class="small mt-1"><strong>Note:</strong> <?= bb_h($r->submitter_notes) ?></div><?php } ?>
          <?php if ($dupes) { ?><div class="small mt-2 bb-bad-note"><i class="fa-solid fa-triangle-exclamation me-1"></i>Similar bottles already in the catalog:
            <?= implode(', ', array_map(fn($x) => '<a href="' . bb_h(bb_url('bourbon/bottle.php', ['id' => $x->id])) . '">' . bb_h($x->name) . '</a>', $dupes)) ?></div><?php } ?>
        </div>
        <div class="d-flex flex-column gap-2 flex-none" style="min-width:14rem">
          <form method="post" class="d-flex gap-2"><?= bb_csrf() ?><input type="hidden" name="id" value="<?= $r->id ?>"><input type="hidden" name="do" value="approve">
            <button class="btn btn-primary btn-sm flex-grow-1" type="submit">Approve</button>
            <a class="btn btn-outline-primary btn-sm flex-grow-1" href="<?= bb_url('bourbon/admin_bottle.php', ['id' => $r->id]) ?>">Edit first</a></form>
          <form method="post" class="d-flex gap-2"><?= bb_csrf() ?><input type="hidden" name="id" value="<?= $r->id ?>"><input type="hidden" name="do" value="reject">
            <input class="form-control form-control-sm" name="note" maxlength="300" placeholder="Reason (optional, member sees it)" aria-label="Reason for declining">
            <button class="btn btn-outline-primary btn-sm" type="submit">Decline</button></form>
        </div>
      </div>
    </article>
  <?php }

} elseif ($tab === 'bottles') {
  $q = mb_substr(bb_get('q'), 0, 60);
  $show = in_array(bb_get('show'), ['all', 'hidden', 'nophoto', 'photo', 'edited'], true) ? bb_get('show') : 'all';
  $where = ["p.status IN ('approved','hidden')"]; $params = [];
  if ($q !== '') foreach (preg_split('/\s+/', $q) as $w) {
    $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $w) . '%';
    $where[] = '(p.name LIKE ? OR p.brand LIKE ? OR d.name LIKE ?)'; array_push($params, $like, $like, $like);
  }
  $where[] = ['all' => '1', 'hidden' => "p.status = 'hidden'", 'nophoto' => 'p.image_file IS NULL', 'photo' => 'p.image_file IS NOT NULL', 'edited' => 'p.admin_locked = 1'][$show];
  $w = implode(' AND ', $where);
  $total = (int)$db->query("SELECT COUNT(*) AS n FROM bb_products p JOIN bb_distilleries d ON d.id = p.distillery_id WHERE $w", $params)->first()->n;
  $per = 50; $pages = max(1, (int)ceil($total / $per)); $page = max(1, min($pages, (int)bb_get('page')));
  $rows = $db->query("SELECT p.*, d.name AS distillery_name FROM bb_products p JOIN bb_distilleries d ON d.id = p.distillery_id WHERE $w ORDER BY p.name LIMIT $per OFFSET " . (($page - 1) * $per), $params)->results(); ?>
  <form class="bb-toolbar mb-3" method="get"><input type="hidden" name="tab" value="bottles">
    <input class="form-control form-control-sm" type="search" name="q" value="<?= bb_h($q) ?>" placeholder="Search bottles" aria-label="Search bottles">
    <select class="form-select form-select-sm" name="show" aria-label="Show" data-autosubmit>
      <?php foreach (['all' => 'All', 'nophoto' => 'Needs a photo', 'photo' => 'Has a photo', 'hidden' => 'Hidden', 'edited' => 'Edited by admin'] as $k => $l) { ?>
        <option value="<?= $k ?>"<?= $show === $k ? ' selected' : '' ?>><?= $l ?></option><?php } ?></select>
    <button class="btn btn-primary btn-sm" type="submit">Search</button>
    <span class="small text-secondary ms-auto"><?= number_format($total) ?> bottle<?= $total === 1 ? '' : 's' ?></span>
  </form>
  <div class="table-responsive bb-panel p-0">
    <table class="table table-sm align-middle mb-0 bb-admin-table">
      <thead><tr><th>Bottle</th><th>Distillery</th><th class="text-end">Proof</th><th class="text-end">Price</th><th>Photo</th><th>Status</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($rows as $r) { ?>
          <tr>
            <td><a href="<?= bb_url('bourbon/bottle.php', ['id' => $r->id]) ?>"><?= bb_h($r->name) ?></a><?= $r->admin_locked ? ' <i class="fa-solid fa-pen small text-secondary" title="Edited by an admin"></i>' : '' ?></td>
            <td class="text-secondary"><?= bb_h($r->distillery_name) ?></td>
            <td class="text-end"><?= $r->proof !== null ? rtrim(rtrim($r->proof, '0'), '.') : '<span class="text-secondary">—</span>' ?></td>
            <td class="text-end"><?= $r->msrp !== null ? '$' . number_format($r->msrp) : '<span class="text-secondary">—</span>' ?></td>
            <td><?= $r->image_file ? ($r->image_license ? 'Commons' : 'Uploaded') : '<span class="text-secondary">Illustration</span>' ?></td>
            <td><?= $r->status === 'hidden' ? '<span class="badge bb-badge">Hidden</span>' : 'Live' ?></td>
            <td class="text-end"><a class="btn btn-outline-primary btn-sm" href="<?= bb_url('bourbon/admin_bottle.php', ['id' => $r->id]) ?>">Edit</a></td>
          </tr>
        <?php } ?>
      </tbody>
    </table>
  </div>
  <?php if ($pages > 1) { $l = fn($n) => bb_url('bourbon/admin.php', array_filter(['tab' => 'bottles', 'q' => $q, 'show' => $show === 'all' ? '' : $show, 'page' => $n])); ?>
    <nav class="mt-3"><ul class="pagination pagination-sm justify-content-center">
      <li class="page-item<?= $page <= 1 ? ' disabled' : '' ?>"><a class="page-link" href="<?= bb_h($l($page - 1)) ?>">Previous</a></li>
      <li class="page-item disabled"><span class="page-link"><?= $page ?> / <?= $pages ?></span></li>
      <li class="page-item<?= $page >= $pages ? ' disabled' : '' ?>"><a class="page-link" href="<?= bb_h($l($page + 1)) ?>">Next</a></li></ul></nav>
  <?php }

} else {
  $rows = $db->query("SELECT d.*, COUNT(p.id) AS n FROM bb_distilleries d LEFT JOIN bb_products p ON p.distillery_id = d.id AND p.status IN ('approved','hidden')
    GROUP BY d.id ORDER BY d.name")->results(); ?>
  <div class="table-responsive bb-panel p-0">
    <table class="table table-sm align-middle mb-0 bb-admin-table">
      <thead><tr><th>Name</th><th>Type</th><th>Location</th><th>Owner</th><th class="text-end">Bottles</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($rows as $d) { ?>
          <tr>
            <td><a href="<?= bb_url('bourbon/distillery.php', ['id' => $d->id]) ?>"><?= bb_h($d->name) ?></a></td>
            <td class="text-secondary"><?= $d->kind === 'producer' ? 'Producer' : 'Distillery' ?></td>
            <td><?= bb_h(bb_place($d)) ?></td>
            <td class="text-secondary"><?= bb_h($d->owner) ?></td>
            <td class="text-end"><?= (int)$d->n ?></td>
            <td class="text-end"><a class="btn btn-outline-primary btn-sm" href="<?= bb_url('bourbon/admin_distillery.php', ['id' => $d->id]) ?>">Edit</a></td>
          </tr>
        <?php } ?>
      </tbody>
    </table>
  </div>
<?php } ?>
<script nonce="<?= bb_h($userspice_nonce ?? '') ?>">document.querySelectorAll('[data-autosubmit]').forEach(function (s) { s.addEventListener('change', function () { s.form.submit(); }); });</script>
<?php require_once $abs_us_root . $us_url_root . 'users/includes/html_footer.php'; ?>
