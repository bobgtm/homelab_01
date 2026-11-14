<?php
require_once '../users/init.php';
$pageTitle = 'Photo credits —';
require_once $abs_us_root . $us_url_root . 'users/includes/template/prep.php';
if (!securePage(Server::get('PHP_SELF'))) { die(); }
require_once __DIR__ . '/inc/catalog.php';

$rows = $db->query(
  "SELECT id, name, image_credit, image_license, image_license_url, image_source_url
     FROM bb_products WHERE image_file IS NOT NULL AND status = 'approved' ORDER BY name")->results();
?>
<div class="bb-page-head">
  <h1>Photo credits</h1>
  <p>Bottle photos come from Wikimedia Commons under free licenses, or were uploaded by Barrel Book with permission. Thank you to the photographers below.
     Photos were resized and placed on a blurred background. All other bottles use generated illustrations.</p>
</div>

<div class="table-responsive">
  <table class="table table-sm align-middle">
    <thead><tr><th>Bottle</th><th>Photographer</th><th>License</th><th>Source</th></tr></thead>
    <tbody>
      <?php foreach ($rows as $r) { ?>
        <tr>
          <td><a href="<?= bb_url('bourbon/bottle.php', ['id' => $r->id]) ?>"><?= bb_h($r->name) ?></a></td>
          <td><?= bb_h(preg_replace('/, via Wikimedia Commons$/', '', $r->image_credit)) ?></td>
          <td><?php if ($r->image_license_url) { ?><a href="<?= bb_h($r->image_license_url) ?>" rel="license noopener" target="_blank"><?= bb_h($r->image_license) ?></a><?php } else { echo $r->image_license ? bb_h($r->image_license) : '<span class="text-secondary">Used with permission</span>'; } ?></td>
          <td><?php if ($r->image_source_url) { ?><a href="<?= bb_h($r->image_source_url) ?>" rel="noopener" target="_blank">Wikimedia Commons</a><?php } else { ?><span class="text-secondary">Uploaded</span><?php } ?></td>
        </tr>
      <?php } ?>
    </tbody>
  </table>
</div>
<p class="small text-secondary">Brand names and label designs belong to their respective owners. Barrel Book is not affiliated with any distillery.</p>

<?php require_once $abs_us_root . $us_url_root . 'users/includes/html_footer.php'; ?>
