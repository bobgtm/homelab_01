<?php
// Catalog admin helpers: slugs, the shared bottle form, and photo processing.
if (!isset($abs_us_root)) { http_response_code(404); exit; }
require_once __DIR__ . '/sightings.php';

const BB_FLAGS = [
  'is_bottled_in_bond' => 'Bottled-in-Bond', 'is_single_barrel' => 'Single Barrel', 'is_barrel_proof' => 'Barrel / cask strength',
  'is_small_batch' => 'Small Batch', 'is_finished' => 'Finished in a second cask', 'is_limited' => 'Limited / allocated',
];

function bb_slugify($s) {
  $s = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', str_replace(['&', "'", '’'], [' and ', '', ''], $s));
  return trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($s)), '-') ?: 'bottle';
}

/** A slug unique in $table (ignoring row $exceptId). */
function bb_unique_slug($table, $name, $exceptId = 0) {
  global $db;
  $base = mb_substr(bb_slugify($name), 0, 140); $slug = $base; $i = 2;
  while ($db->query("SELECT id FROM $table WHERE slug = ? AND id <> ?", [$slug, (int)$exceptId])->count()) $slug = $base . '-' . $i++;
  return $slug;
}

/** Read + validate the bottle fields shared by the admin editor and member suggestions. Returns [fields, errors]. */
function bb_read_bottle_form() {
  global $db;
  $e = []; $f = [];
  $txt = fn($k, $max) => mb_substr(trim((string)($_POST[$k] ?? '')), 0, $max);
  $f['name'] = $txt('name', 200);
  $f['brand'] = $txt('brand', 120);
  if ($f['name'] === '') $e[] = 'Name is required.';
  if ($f['brand'] === '') $f['brand'] = $f['name'];
  $num = function ($k, $min, $max, $label, $int = false) use (&$e) {
    $v = trim(str_replace(['$', ','], '', (string)($_POST[$k] ?? '')));
    if ($v === '') return null;
    if (!is_numeric($v) || $v < $min || $v > $max) { $e[] = "$label must be between $min and $max."; return null; }
    return $int ? (int)$v : round((float)$v, $k === 'proof' ? 1 : 2);
  };
  $f['proof'] = $num('proof', 80, 160, 'Proof');
  $f['age_years'] = $num('age_years', 1, 50, 'Age', true);
  $f['msrp'] = $num('msrp', 1, 50000, 'Typical price');
  foreach (BB_FLAGS as $k => $_) $f[$k] = !empty($_POST[$k]) ? 1 : 0;
  $mash = $_POST['mash_style'] ?? '';
  $f['mash_style'] = in_array($mash, ['traditional', 'wheated', 'high-rye'], true) ? $mash : null;

  // Distillery: an existing one, or a new one (name + city + state).
  $did = (int)($_POST['distillery_id'] ?? 0);
  if ($did > 0) {
    if (!$db->query("SELECT id FROM bb_distilleries WHERE id = ?", [$did])->count()) $e[] = 'Pick a distillery from the list.';
    $f['distillery_id'] = $did;
  } else {
    $f['_new_distillery'] = ['name' => $txt('new_dist_name', 160), 'city' => $txt('new_dist_city', 80), 'state' => strtoupper($txt('new_dist_state', 2))];
    if ($f['_new_distillery']['name'] === '') $e[] = 'Pick a distillery, or enter the name of a new one.';
    if ($f['_new_distillery']['state'] !== '' && !in_array($f['_new_distillery']['state'], BB_STATES, true)) $e[] = 'Pick a valid state for the new distillery.';
  }
  return [$f, $e];
}

/** Create the "new distillery" from bb_read_bottle_form() if needed; returns its id. */
function bb_resolve_distillery(array &$f, $uid, $locked) {
  global $db;
  if (!empty($f['distillery_id'])) { unset($f['_new_distillery']); return $f['distillery_id']; }
  $n = $f['_new_distillery']; unset($f['_new_distillery']);
  $existing = $db->query("SELECT id FROM bb_distilleries WHERE LOWER(name) = LOWER(?)", [$n['name']])->first();
  if ($existing) return $f['distillery_id'] = (int)$existing->id;
  $db->insert('bb_distilleries', ['slug' => bb_unique_slug('bb_distilleries', $n['name']), 'name' => $n['name'], 'kind' => 'distillery',
    'city' => $n['city'] ?: null, 'state' => $n['state'] ?: null, 'admin_locked' => $locked ? 1 : 0, 'submitted_by' => $uid]);
  return $f['distillery_id'] = (int)$db->lastId();
}

/**
 * Turn an uploaded image into a 600x800 framed JPEG (same look as the Commons photos:
 * the whole photo, centered over a blurred, darkened copy of itself).
 * Returns [filename, null] on success or [null, error].
 */
function bb_process_photo(array $upload, $slug) {
  global $abs_us_root, $us_url_root;
  if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    return [null, ($upload['error'] ?? 0) === UPLOAD_ERR_INI_SIZE ? 'That photo is too large (15 MB max).' : 'The upload did not arrive. Please try again.'];
  }
  if ($upload['size'] > 15 * 1024 * 1024) return [null, 'That photo is too large (15 MB max).'];
  $info = @getimagesize($upload['tmp_name']);
  $types = [IMAGETYPE_JPEG => 'imagecreatefromjpeg', IMAGETYPE_PNG => 'imagecreatefrompng', IMAGETYPE_WEBP => 'imagecreatefromwebp'];
  if (!$info || !isset($types[$info[2]])) return [null, 'Please upload a JPEG, PNG or WebP photo.'];
  if ($info[0] * $info[1] > 40000000) return [null, 'That photo has too many pixels; please use a smaller version.'];
  $src = @$types[$info[2]]($upload['tmp_name']);
  if (!$src) return [null, 'That photo could not be read.'];

  // Respect phone camera rotation.
  if ($info[2] === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
    $o = (int)(@exif_read_data($upload['tmp_name'])['Orientation'] ?? 1);
    $rot = [3 => 180, 6 => -90, 8 => 90][$o] ?? 0;
    if ($rot) { $r = imagerotate($src, $rot, 0); if ($r) { imagedestroy($src); $src = $r; } }
  }
  $sw = imagesx($src); $sh = imagesy($src); $W = 600; $H = 800;

  // Background: cover-fit, shrunk to a few pixels and scaled back up = soft blur; then darkened.
  $out = imagecreatetruecolor($W, $H);
  $scale = max($W / $sw, $H / $sh); $cw = $W / $scale; $ch = $H / $scale;
  $tiny = imagecreatetruecolor(24, 32);
  imagecopyresampled($tiny, $src, 0, 0, (int)(($sw - $cw) / 2), (int)(($sh - $ch) / 2), 24, 32, (int)$cw, (int)$ch);
  imagecopyresampled($out, $tiny, 0, 0, 0, 0, $W, $H, 24, 32);
  for ($i = 0; $i < 3; $i++) imagefilter($out, IMG_FILTER_GAUSSIAN_BLUR);
  imagefilter($out, IMG_FILTER_BRIGHTNESS, -70);
  imagedestroy($tiny);

  // Foreground: contain-fit, centered.
  $s = min($W / $sw, $H / $sh); $fw = (int)round($sw * $s); $fh = (int)round($sh * $s);
  imagecopyresampled($out, $src, (int)(($W - $fw) / 2), (int)(($H - $fh) / 2), 0, 0, $fw, $fh, $sw, $sh);
  imagedestroy($src);

  $dir = $abs_us_root . $us_url_root . 'bourbon/images/uploads/';   // persistent volume in Kubernetes
  if (!is_dir($dir)) @mkdir($dir, 0775, true);
  $name = 'u-' . mb_substr($slug, 0, 120) . '-' . date('YmdHis') . '.jpg';   // "u-" marks uploads
  imageinterlace($out, true);
  $ok = imagejpeg($out, $dir . $name, 84);
  imagedestroy($out);
  return $ok ? [$name, null] : [null, 'Saving the photo failed (is the images folder writable?).'];
}

/** Delete an uploaded photo file (never Commons files, which are re-created from the seed). */
function bb_delete_upload($file) {
  global $abs_us_root, $us_url_root;
  if ($file && str_starts_with($file, 'u-') && basename($file) === $file) @unlink($abs_us_root . $us_url_root . 'bourbon/images/uploads/' . $file);
}

function bb_pending_count() {
  global $db;
  try { return (int)$db->query("SELECT COUNT(*) AS n FROM bb_products WHERE status = 'pending'")->first()->n; } catch (Throwable $e) { return 0; }
}

/** Distillery <select> + "new distillery" fields, shared by both forms. */
function bb_distillery_picker($selected, $new = []) {
  global $db;
  $list = $db->query("SELECT id, name, city, state FROM bb_distilleries ORDER BY name")->results();
  ob_start(); ?>
  <label class="form-label" for="f-dist">Distillery / producer</label>
  <select class="form-select" id="f-dist" name="distillery_id" data-bb-distpick>
    <option value="">Choose…</option>
    <?php foreach ($list as $d) { ?><option value="<?= $d->id ?>"<?= (int)$selected === (int)$d->id ? ' selected' : '' ?>><?= bb_h($d->name) ?><?= $d->state ? ' (' . bb_h($d->state) . ')' : '' ?></option><?php } ?>
    <option value="-1"<?= (int)$selected === -1 ? ' selected' : '' ?>>Not listed: add a new one…</option>
  </select>
  <div class="row g-2 mt-1" data-bb-newdist<?= (int)$selected === -1 ? '' : ' hidden' ?>>
    <div class="col-md-6"><input class="form-control" name="new_dist_name" maxlength="160" placeholder="New distillery name" value="<?= bb_h($new['name'] ?? '') ?>" aria-label="New distillery name"></div>
    <div class="col-8 col-md-4"><input class="form-control" name="new_dist_city" maxlength="80" placeholder="City" value="<?= bb_h($new['city'] ?? '') ?>" aria-label="City"></div>
    <div class="col-4 col-md-2"><select class="form-select" name="new_dist_state" aria-label="State"><option value="">State</option>
      <?php foreach (BB_STATES as $s) { ?><option<?= ($new['state'] ?? '') === $s ? ' selected' : '' ?>><?= $s ?></option><?php } ?></select></div>
  </div>
  <?php return ob_get_clean();
}

/** The bottle fields (everything except distillery and photo). $v = current values object/array. */
function bb_bottle_fields($v) {
  $v = (object)$v;
  if (isset($v->proof) && is_numeric($v->proof)) $v->proof = rtrim(rtrim((string)$v->proof, '0'), '.');
  if (isset($v->msrp) && is_numeric($v->msrp)) $v->msrp = preg_replace('/\.00$/', '', (string)$v->msrp);
  ob_start(); ?>
  <div class="row g-3">
    <div class="col-md-7"><label class="form-label" for="f-name">Bottle name</label>
      <input class="form-control" id="f-name" name="name" maxlength="200" required value="<?= bb_h($v->name ?? '') ?>" placeholder="e.g. Eagle Rare 10 Year"></div>
    <div class="col-md-5"><label class="form-label" for="f-brand">Brand</label>
      <input class="form-control" id="f-brand" name="brand" maxlength="120" value="<?= bb_h($v->brand ?? '') ?>" placeholder="e.g. Eagle Rare"></div>
    <div class="col-4"><label class="form-label" for="f-proof">Proof</label>
      <input class="form-control" id="f-proof" name="proof" inputmode="decimal" value="<?= bb_h($v->proof ?? '') ?>" placeholder="90"></div>
    <div class="col-4"><label class="form-label" for="f-age">Age (years)</label>
      <input class="form-control" id="f-age" name="age_years" inputmode="numeric" value="<?= bb_h($v->age_years ?? '') ?>" placeholder="NAS"></div>
    <div class="col-4"><label class="form-label" for="f-msrp">Typical price</label>
      <div class="input-group"><span class="input-group-text">$</span><input class="form-control" id="f-msrp" name="msrp" inputmode="decimal" value="<?= bb_h($v->msrp ?? '') ?>"></div></div>
    <div class="col-md-4"><label class="form-label" for="f-mash">Mash bill</label>
      <select class="form-select" id="f-mash" name="mash_style">
        <?php foreach (['' => 'Not listed', 'traditional' => 'Traditional', 'wheated' => 'Wheated', 'high-rye' => 'High rye'] as $k => $l) { ?>
          <option value="<?= $k ?>"<?= ($v->mash_style ?? '') === $k ? ' selected' : '' ?>><?= $l ?></option><?php } ?>
      </select></div>
    <div class="col-md-8"><span class="form-label d-block">Style</span>
      <div class="d-flex flex-wrap gap-3">
        <?php foreach (BB_FLAGS as $k => $l) { ?>
          <div class="form-check"><input class="form-check-input" type="checkbox" id="f-<?= $k ?>" name="<?= $k ?>" value="1"<?= !empty($v->$k) ? ' checked' : '' ?>>
            <label class="form-check-label" for="f-<?= $k ?>"><?= $l ?></label></div>
        <?php } ?>
      </div></div>
  </div>
  <?php return ob_get_clean();
}

function bb_distpick_js() {
  global $userspice_nonce;
  return '<script nonce="' . bb_h($userspice_nonce ?? '') . '">document.querySelectorAll("[data-bb-distpick]").forEach(function(s){var box=s.parentNode.querySelector("[data-bb-newdist]");'
    . 'function u(){box.hidden=s.value!=="-1";box.querySelector("[name=new_dist_name]").required=s.value==="-1";}s.addEventListener("change",u);u();});</script>';
}
