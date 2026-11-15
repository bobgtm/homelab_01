<?php
// Admin: add, edit or delete a distillery / producer. Administrator permission only (securePage).
require_once '../users/init.php';
require_once __DIR__ . '/inc/admin.php';

$id = (int)bb_get('id');
$d = $id ? $db->query("SELECT * FROM bb_distilleries WHERE id = ?", [$id])->first() : null;
$isNew = !$d;
// Declined suggestions don't count; they are cleared out if the distillery is deleted.
$count = $d ? (int)$db->query("SELECT COUNT(*) AS n FROM bb_products WHERE distillery_id = ? AND status <> 'rejected'", [$d->id])->first()->n : 0;
$errors = [];
$form = $d ? (array)$d : ['kind' => 'distillery'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && hasPerm([2])) {
  if (!Token::check($_POST['csrf'] ?? '')) $errors[] = 'Your session expired. Please try again.';
  elseif (($_POST['do'] ?? '') === 'delete' && $d) {
    if ($count) $errors[] = 'This distillery still has bottles. Move or remove them first.';
    else { $db->query("DELETE FROM bb_products WHERE distillery_id = ? AND status = 'rejected'", [$d->id]); $db->query("DELETE FROM bb_distilleries WHERE id = ?", [$d->id]); usSuccess('Distillery deleted.'); Redirect::to(bb_url('bourbon/admin.php', ['tab' => 'distilleries'])); exit; }
  } else {
    $t = fn($k, $m) => mb_substr(trim((string)($_POST[$k] ?? '')), 0, $m);
    $f = ['name' => $t('name', 160), 'kind' => ($_POST['kind'] ?? '') === 'producer' ? 'producer' : 'distillery',
          'city' => $t('city', 80) ?: null, 'state' => strtoupper($t('state', 2)) ?: null, 'owner' => $t('owner', 120) ?: null, 'admin_locked' => 1];
    if ($f['name'] === '') $errors[] = 'Name is required.';
    if ($f['state'] && !in_array($f['state'], BB_STATES, true)) $errors[] = 'Pick a valid state.';
    $dupe = $db->query("SELECT id FROM bb_distilleries WHERE LOWER(name) = LOWER(?) AND id <> ?", [$f['name'], $id])->first();
    if ($dupe) $errors[] = 'Another distillery already has that name.';
    if (!$errors) {
      if ($isNew) { $f['slug'] = bb_unique_slug('bb_distilleries', $f['name']); $db->insert('bb_distilleries', $f); $id = (int)$db->lastId(); }
      else $db->update('bb_distilleries', $d->id, $f);
      usSuccess($isNew ? 'Distillery added.' : 'Distillery saved.');
      Redirect::to(bb_url('bourbon/admin_distillery.php', ['id' => $id])); exit;
    }
    $form = array_merge($form, $_POST);
  }
}

$pageTitle = ($isNew ? 'Add a distillery' : 'Edit ' . $d->name) . ' —';
require_once $abs_us_root . $us_url_root . 'users/includes/template/prep.php';
if (!securePage(Server::get('PHP_SELF'))) { die(); }
$form = (object)$form;
?>
<nav aria-label="breadcrumb" class="mb-3"><ol class="breadcrumb small mb-0">
  <li class="breadcrumb-item"><a href="<?= bb_url('bourbon/admin.php', ['tab' => 'distilleries']) ?>">Catalog admin</a></li>
  <li class="breadcrumb-item active" aria-current="page"><?= $isNew ? 'Add a distillery' : bb_h($d->name) ?></li>
</ol></nav>
<div class="bb-page-head">
  <h1><?= $isNew ? 'Add a distillery' : 'Edit distillery' ?></h1>
  <?php if (!$isNew) { ?><p><?= $count ?> bottle<?= $count === 1 ? '' : 's' ?> · <a href="<?= bb_url('bourbon/distillery.php', ['id' => $d->id]) ?>">View public page</a>
    · <a href="<?= bb_url('bourbon/admin_bottle.php', ['distillery' => $d->id]) ?>">Add a bottle here</a></p><?php } ?>
</div>
<?php if ($errors) { ?><div class="alert alert-danger" role="alert"><?php foreach ($errors as $e) echo '<div>' . bb_h($e) . '</div>'; ?></div><?php } ?>

<form method="post" class="bb-panel" style="max-width:44rem">
  <?= bb_csrf() ?>
  <div class="row g-3">
    <div class="col-md-8"><label class="form-label" for="d-name">Name</label><input class="form-control" id="d-name" name="name" maxlength="160" required value="<?= bb_h($form->name ?? '') ?>"></div>
    <div class="col-md-4"><label class="form-label" for="d-kind">Type</label>
      <select class="form-select" id="d-kind" name="kind">
        <option value="distillery"<?= ($form->kind ?? '') === 'distillery' ? ' selected' : '' ?>>Distillery</option>
        <option value="producer"<?= ($form->kind ?? '') === 'producer' ? ' selected' : '' ?>>Brand / producer (sources whiskey)</option>
      </select></div>
    <div class="col-8 col-md-5"><label class="form-label" for="d-city">City</label><input class="form-control" id="d-city" name="city" maxlength="80" value="<?= bb_h($form->city ?? '') ?>"></div>
    <div class="col-4 col-md-2"><label class="form-label" for="d-state">State</label>
      <select class="form-select" id="d-state" name="state"><option value="">—</option><?php foreach (BB_STATES as $s) { ?><option<?= ($form->state ?? '') === $s ? ' selected' : '' ?>><?= $s ?></option><?php } ?></select></div>
    <div class="col-md-5"><label class="form-label" for="d-owner">Parent company</label><input class="form-control" id="d-owner" name="owner" maxlength="120" value="<?= bb_h($form->owner ?? '') ?>"></div>
  </div>
  <div class="d-flex gap-2 mt-4">
    <button class="btn btn-primary" type="submit"><?= $isNew ? 'Add distillery' : 'Save changes' ?></button>
    <a class="btn btn-outline-primary" href="<?= bb_url('bourbon/admin.php', ['tab' => 'distilleries']) ?>">Cancel</a>
  </div>
</form>
<?php if (!$isNew && !$count) { ?>
  <form method="post" class="mt-3" data-bb-confirm="Delete this distillery?"><?= bb_csrf() ?><input type="hidden" name="do" value="delete">
    <button class="btn btn-link text-danger px-0" type="submit">Delete distillery</button></form>
  <script nonce="<?= bb_h($userspice_nonce ?? '') ?>">document.querySelectorAll('form[data-bb-confirm]').forEach(function (f) { f.addEventListener('submit', function (e) { if (!confirm(f.getAttribute('data-bb-confirm'))) e.preventDefault(); }); });</script>
<?php } ?>
<?php require_once $abs_us_root . $us_url_root . 'users/includes/html_footer.php'; ?>
