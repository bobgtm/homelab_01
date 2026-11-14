<?php
// Admin: add or edit one bottle, including its photo. Administrator permission only (securePage).
require_once '../users/init.php';
require_once __DIR__ . '/inc/admin.php';

$id = (int)bb_get('id');
$p = $id ? $db->query("SELECT p.*, d.name AS distillery_name FROM bb_products p JOIN bb_distilleries d ON d.id = p.distillery_id WHERE p.id = ?", [$id])->first() : null;
$isNew = !$p;
$errors = [];
$form = $p ? (array)$p : ['distillery_id' => (int)bb_get('distillery')];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && hasPerm([2])) {
  if (!Token::check($_POST['csrf'] ?? '')) {
    $errors[] = 'Your session expired. Please try again.';
  } else {
    [$f, $errors] = bb_read_bottle_form();
    $status = in_array($_POST['status'] ?? '', ['approved', 'hidden', 'pending', 'rejected'], true) ? $_POST['status'] : 'approved';
    $upload = $_FILES['photo'] ?? null;
    $hasUpload = $upload && ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
    if (!$errors) {
      bb_resolve_distillery($f, bb_me(), true);
      $f += ['status' => $status, 'admin_locked' => 1];
      if ($isNew) { $f['slug'] = bb_unique_slug('bb_products', $f['name']); $f['submitted_by'] = bb_me(); }
      $slug = $isNew ? $f['slug'] : $p->slug;
      if ($hasUpload) {
        [$file, $err] = bb_process_photo($upload, $slug);
        if ($err) $errors[] = $err;
        else {
          if ($p) bb_delete_upload($p->image_file);
          $credit = trim(mb_substr((string)($_POST['photo_credit'] ?? ''), 0, 300));
          $f += ['image_file' => $file, 'image_credit' => $credit !== '' ? $credit : 'Barrel Book', 'image_license' => null, 'image_license_url' => null, 'image_source_url' => null];
        }
      } elseif (!$isNew && !empty($_POST['remove_photo'])) {
        bb_delete_upload($p->image_file);
        $f += ['image_file' => null, 'image_credit' => null, 'image_license' => null, 'image_license_url' => null, 'image_source_url' => null];
      } elseif (!$isNew && $p->image_file && !$p->image_license && isset($_POST['photo_credit'])) {
        $credit = trim(mb_substr((string)$_POST['photo_credit'], 0, 300));
        $f['image_credit'] = $credit !== '' ? $credit : 'Barrel Book';
      }
    }
    if (!$errors) {
      if ($isNew) { $db->insert('bb_products', $f); $id = (int)$db->lastId(); }
      else $db->update('bb_products', $p->id, $f);
      if ($db->error()) $errors[] = 'Saving failed: ' . $db->errorString();
      else {
        usSuccess($isNew ? 'Bottle added.' : 'Bottle saved.');
        Redirect::to(bb_url('bourbon/admin_bottle.php', ['id' => $id]));
        exit;
      }
    }
    $form = array_merge($form, $_POST);
    if (($form['distillery_id'] ?? '') === '-1') $form['distillery_id'] = -1;
  }
}

$pageTitle = ($isNew ? 'Add a bottle' : 'Edit ' . $p->name) . ' —';
require_once $abs_us_root . $us_url_root . 'users/includes/template/prep.php';
if (!securePage(Server::get('PHP_SELF'))) { die(); }
$form = (object)$form;
?>
<nav aria-label="breadcrumb" class="mb-3"><ol class="breadcrumb small mb-0">
  <li class="breadcrumb-item"><a href="<?= bb_url('bourbon/admin.php', ['tab' => 'bottles']) ?>">Catalog admin</a></li>
  <li class="breadcrumb-item active" aria-current="page"><?= $isNew ? 'Add a bottle' : bb_h($p->name) ?></li>
</ol></nav>

<div class="bb-page-head d-flex flex-wrap justify-content-between align-items-end gap-3">
  <div>
    <h1><?= $isNew ? 'Add a bottle' : 'Edit bottle' ?></h1>
    <?php if (!$isNew) { ?><p><?= bb_h($p->name) ?> · <a href="<?= bb_url('bourbon/bottle.php', ['id' => $p->id]) ?>">View public page</a>
      <?php if ($p->status === 'pending') { ?><span class="badge bb-badge ms-2">Pending review</span><?php } ?></p><?php } ?>
  </div>
</div>

<?php if ($errors) { ?><div class="alert alert-danger" role="alert"><?php foreach ($errors as $e) echo '<div>' . bb_h($e) . '</div>'; ?></div><?php } ?>

<form method="post" enctype="multipart/form-data" class="row g-4">
  <?= bb_csrf() ?>
  <div class="col-lg-8">
    <div class="bb-panel">
      <?= bb_bottle_fields($form) ?>
      <div class="mt-3"><?= bb_distillery_picker($form->distillery_id ?? 0, ['name' => $form->new_dist_name ?? '', 'city' => $form->new_dist_city ?? '', 'state' => $form->new_dist_state ?? '']) ?></div>
      <div class="row g-3 mt-1">
        <div class="col-md-5"><label class="form-label" for="f-status">Status</label>
          <select class="form-select" id="f-status" name="status">
            <?php foreach (['approved' => 'In the catalog', 'hidden' => 'Hidden from the catalog', 'pending' => 'Pending review', 'rejected' => 'Rejected suggestion'] as $k => $l) {
              if (in_array($k, ['pending', 'rejected'], true) && ($form->status ?? '') !== $k) continue; ?>
              <option value="<?= $k ?>"<?= ($form->status ?? 'approved') === $k ? ' selected' : '' ?>><?= $l ?></option><?php } ?>
          </select>
          <div class="form-text">Hidden bottles disappear from browsing and search, but stay in members’ lists.</div></div>
      </div>
      <?php if (!empty($p->submitter_notes)) { ?><div class="mt-3 small"><strong>Member’s note:</strong> <?= bb_h($p->submitter_notes) ?></div><?php } ?>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="bb-panel">
      <h2 class="bb-mine-h mb-3">Photo</h2>
      <div class="bb-detail-media mb-3" style="max-width:15rem;margin-inline:auto">
        <?= $p ? bb_bottle_image($p, '', true) : bb_bottle_art((object)['id' => 0, 'slug' => 'new', 'brand' => 'New bottle', 'proof' => null, 'is_barrel_proof' => 0, 'age_years' => null, 'is_bottled_in_bond' => 0, 'is_single_barrel' => 0, 'name' => 'New bottle']) ?>
      </div>
      <?php if ($p && $p->image_file && $p->image_license) { ?>
        <p class="small text-secondary">Current photo: <?= bb_h($p->image_credit) ?>, <?= bb_h($p->image_license) ?>.</p>
      <?php } ?>
      <label class="form-label" for="f-photo"><?= $p && $p->image_file ? 'Replace photo' : 'Upload a photo' ?></label>
      <input class="form-control" type="file" id="f-photo" name="photo" accept="image/jpeg,image/png,image/webp">
      <div class="form-text">JPEG, PNG or WebP up to 15 MB. It’s framed automatically to match the others: the whole photo, centered.</div>
      <label class="form-label mt-3" for="f-credit">Photo credit</label>
      <input class="form-control" id="f-credit" name="photo_credit" maxlength="300" value="<?= bb_h($p && $p->image_file && !$p->image_license ? $p->image_credit : '') ?>" placeholder="Barrel Book">
      <div class="form-text">Only upload photos you took or have permission to use.</div>
      <?php if ($p && $p->image_file) { ?>
        <div class="form-check mt-3"><input class="form-check-input" type="checkbox" id="f-rm" name="remove_photo" value="1">
          <label class="form-check-label" for="f-rm">Remove photo (use the illustration)</label></div>
      <?php } ?>
    </div>
  </div>

  <div class="col-12 d-flex gap-2">
    <button class="btn btn-primary" type="submit"><?= $isNew ? 'Add bottle' : 'Save changes' ?></button>
    <a class="btn btn-outline-primary" href="<?= bb_url('bourbon/admin.php', ['tab' => $p && $p->status === 'pending' ? 'queue' : 'bottles']) ?>">Cancel</a>
  </div>
</form>
<?= bb_distpick_js() ?>
<?php require_once $abs_us_root . $us_url_root . 'users/includes/html_footer.php'; ?>
