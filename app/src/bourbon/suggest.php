<?php
// Members suggest a bottle that's missing from the catalog; admins review it in the catalog admin.
require_once '../users/init.php';
require_once __DIR__ . '/inc/admin.php';

$me = bb_me();
$errors = []; $form = ['name' => bb_get('name')];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $me) {
  if (!Token::check($_POST['csrf'] ?? '')) $errors[] = 'Your session expired. Please try again.';
  else {
    [$f, $errors] = bb_read_bottle_form();
    $open = (int)$db->query("SELECT COUNT(*) AS n FROM bb_products WHERE submitted_by = ? AND status = 'pending'", [$me])->first()->n;
    if ($open >= 20) $errors[] = 'You have 20 suggestions waiting for review. Please wait for some to be reviewed first.';
    if (!$errors && $db->query("SELECT id FROM bb_products WHERE LOWER(name) = LOWER(?) AND status IN ('approved','pending')", [$f['name']])->count()) {
      $errors[] = 'A bottle with that exact name is already in the catalog or waiting for review.';
    }
    if (!$errors) {
      bb_resolve_distillery($f, $me, false);
      $f += ['slug' => bb_unique_slug('bb_products', $f['name']), 'status' => 'pending', 'submitted_by' => $me,
             'submitter_notes' => mb_substr(trim((string)($_POST['submitter_notes'] ?? '')), 0, 500) ?: null];
      $db->insert('bb_products', $f);
      usSuccess('Thanks! An admin will review “' . $f['name'] . '” and add it to the catalog.');
      Redirect::to(bb_url('bourbon/suggest.php')); exit;
    }
    $form = array_merge($form, $_POST);
    if (($form['distillery_id'] ?? '') === '-1') $form['distillery_id'] = -1;
  }
}

$pageTitle = 'Suggest a bottle —';
require_once $abs_us_root . $us_url_root . 'users/includes/template/prep.php';
if (!securePage(Server::get('PHP_SELF'))) { die(); }
$mine = $db->query("SELECT p.id, p.name, p.status, p.review_note, p.created_at FROM bb_products p WHERE p.submitted_by = ? AND p.status IN ('pending','approved','rejected') ORDER BY p.created_at DESC LIMIT 30", [$me])->results();
$form = (object)$form;
?>
<div class="bb-page-head">
  <h1>Suggest a bottle</h1>
  <p>Can’t find a bourbon in the catalog? Tell us about it and an admin will add it. Please <a href="<?= bb_url('bourbon/browse.php') ?>">search the catalog</a> first.</p>
</div>
<?php if ($errors) { ?><div class="alert alert-danger" role="alert"><?php foreach ($errors as $e) echo '<div>' . bb_h($e) . '</div>'; ?></div><?php } ?>

<div class="row g-4">
  <div class="col-lg-8">
    <form method="post" class="bb-panel">
      <?= bb_csrf() ?>
      <?= bb_bottle_fields($form) ?>
      <div class="mt-3"><?= bb_distillery_picker($form->distillery_id ?? 0, ['name' => $form->new_dist_name ?? '', 'city' => $form->new_dist_city ?? '', 'state' => $form->new_dist_state ?? '']) ?></div>
      <label class="form-label mt-3" for="f-notes">Anything else? <span class="text-secondary fw-normal">(optional)</span></label>
      <textarea class="form-control" id="f-notes" name="submitter_notes" rows="2" maxlength="500" placeholder="A link to the distillery’s page, where you saw it, release year…"><?= bb_h($form->submitter_notes ?? '') ?></textarea>
      <p class="small text-secondary mt-2 mb-3">Fill in what you know; leave the rest blank. Admins check details before a bottle goes live.</p>
      <button class="btn btn-primary" type="submit">Send suggestion</button>
    </form>
  </div>
  <div class="col-lg-4">
    <div class="bb-panel">
      <h2 class="bb-mine-h mb-3">Your suggestions</h2>
      <?php if (!$mine) { ?><p class="small text-secondary mb-0">Nothing yet.</p><?php } ?>
      <ul class="list-unstyled mb-0 bb-suggest-list">
        <?php foreach ($mine as $m) { ?>
          <li>
            <?php if ($m->status === 'approved') { ?><a href="<?= bb_url('bourbon/bottle.php', ['id' => $m->id]) ?>"><?= bb_h($m->name) ?></a><?php } else { echo bb_h($m->name); } ?>
            <span class="badge <?= $m->status === 'approved' ? 'bb-good' : 'bb-badge' ?> ms-1"><?= ['pending' => 'Waiting for review', 'approved' => 'Added', 'rejected' => 'Declined'][$m->status] ?></span>
            <?php if ($m->status === 'rejected' && $m->review_note) { ?><div class="small text-secondary"><?= bb_h($m->review_note) ?></div><?php } ?>
          </li>
        <?php } ?>
      </ul>
    </div>
  </div>
</div>
<?= bb_distpick_js() ?>
<?php require_once $abs_us_root . $us_url_root . 'users/includes/html_footer.php'; ?>
