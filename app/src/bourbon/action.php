<?php
// All write actions for lists, profiles and follows. POST only; CSRF-checked; every
// row is scoped to the logged-in user so nobody can edit someone else's data.
require_once '../users/init.php';
if (!securePage(Server::get('PHP_SELF'))) { die(); }
require_once __DIR__ . '/inc/sightings.php';

$me = bb_me();

// Only redirect back to pages on this site.
$back = (string)($_POST['return'] ?? '');
if ($back === '' || $back[0] !== '/' || str_starts_with($back, '//') || preg_match('/[\r\n]/', $back)) {
  $back = $us_url_root . 'bourbon/collection.php';
}
$done = function ($msg = null, $ok = true) use ($back) {
  if ($msg) { $ok ? usSuccess($msg) : usError($msg); }
  Redirect::to($back);
  exit;
};

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$me) $done();
if (!Token::check($_POST['csrf'] ?? '')) $done('Your session expired. Please try again.', false);

// ---------- input helpers (return null for empty) ----------
$str = function ($k, $max) {
  $v = trim((string)($_POST[$k] ?? ''));
  return $v === '' ? null : mb_substr($v, 0, $max);
};
$money = function ($k) {
  $v = str_replace([',', '$', ' '], '', (string)($_POST[$k] ?? ''));
  if ($v === '' || !is_numeric($v)) return null;
  $v = round((float)$v, 2);
  return ($v >= 0 && $v < 100000) ? $v : null;
};
$date = function ($k) {
  $v = (string)($_POST[$k] ?? '');
  $d = DateTime::createFromFormat('!Y-m-d', $v);
  if (!$d || $d->format('Y-m-d') !== $v) return null;
  return ($v >= '1900-01-01' && $v <= date('Y-m-d', strtotime('+1 day'))) ? $v : null;
};
$int = function ($k, $min, $max, $default = null) {
  $v = filter_var($_POST[$k] ?? null, FILTER_VALIDATE_INT);
  return ($v === false || $v === null || $v < $min || $v > $max) ? $default : $v;
};
$product = function () use ($db) {
  $id = (int)($_POST['product_id'] ?? 0);
  return $id ? $db->query("SELECT id, name FROM bb_products WHERE id = ? AND status = 'approved'", [$id])->first() : null;
};
$own = function ($table, $id) use ($db, $me) {
  return $db->query("SELECT * FROM $table WHERE id = ? AND user_id = ?", [(int)$id, $me])->first();
};

$do = (string)($_POST['do'] ?? '');
switch ($do) {

  // ---------- Collection ----------
  case 'collection_save': {
    $id = (int)($_POST['id'] ?? 0);
    $row = $id ? $own('bb_collection', $id) : null;
    if ($id && !$row) $done('That bottle was not found in your collection.', false);
    $p = $row ? (object)['id' => $row->product_id] : $product();
    if (!$p) $done('Please pick a bottle from the catalog.', false);
    $status = in_array($_POST['status'] ?? '', ['sealed', 'open', 'finished'], true) ? $_POST['status'] : 'sealed';
    $fields = [
      'quantity' => $int('quantity', 1, 99, 1),
      'status' => $status,
      'fill_level' => $status === 'open' ? $int('fill_level', 0, 100) : ($status === 'finished' ? 0 : null),
      'purchase_price' => $money('purchase_price'),
      'purchase_date' => $date('purchase_date'),
      'store_name' => $str('store_name', 120),
      'notes' => $str('notes', 1000),
    ];
    if ($row) { $db->update('bb_collection', $row->id, $fields); $done('Bottle updated.'); }
    $db->insert('bb_collection', $fields + ['user_id' => $me, 'product_id' => $p->id]);
    // Buying a wishlist bottle checks it off the wishlist.
    if (!empty($_POST['remove_from_wishlist'])) $db->query("DELETE FROM bb_wishlist WHERE user_id = ? AND product_id = ?", [$me, $p->id]);
    $done('Added to your collection.');
  }
  case 'collection_delete': {
    $row = $own('bb_collection', $_POST['id'] ?? 0);
    if ($row) $db->query("DELETE FROM bb_collection WHERE id = ? AND user_id = ?", [$row->id, $me]);
    $done($row ? 'Removed from your collection.' : null);
  }

  // ---------- Tastings ----------
  case 'tasting_save': {
    $id = (int)($_POST['id'] ?? 0);
    $row = $id ? $own('bb_tastings', $id) : null;
    if ($id && !$row) $done('That tasting was not found.', false);
    $p = $row ? (object)['id' => $row->product_id] : $product();
    if (!$p) $done('Please pick a bottle from the catalog.', false);
    $r = $_POST['rating'] ?? '';
    $rating = (is_numeric($r) && (float)$r >= 0.5 && (float)$r <= 5 && fmod((float)$r * 2, 1) == 0) ? (float)$r : null;
    $fields = [
      'rating' => $rating,
      'tasted_on' => $date('tasted_on') ?? ($row ? null : date('Y-m-d')),
      'nose' => $str('nose', 500), 'palate' => $str('palate', 500), 'finish' => $str('finish', 500),
      'notes' => $str('notes', 1000),
    ];
    if ($row) { $db->update('bb_tastings', $row->id, $fields); $done('Tasting updated.'); }
    $db->insert('bb_tastings', $fields + ['user_id' => $me, 'product_id' => $p->id]);
    $done('Tasting saved.');
  }
  case 'tasting_delete': {
    $row = $own('bb_tastings', $_POST['id'] ?? 0);
    if ($row) $db->query("DELETE FROM bb_tastings WHERE id = ? AND user_id = ?", [$row->id, $me]);
    $done($row ? 'Tasting deleted.' : null);
  }

  // ---------- Wishlist ----------
  case 'wishlist_save': {
    $p = $product();
    if (!$p) $done('Please pick a bottle from the catalog.', false);
    $db->query("INSERT INTO bb_wishlist (user_id, product_id, target_price, notes) VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE target_price = VALUES(target_price), notes = VALUES(notes)",
      [$me, $p->id, $money('target_price'), $str('notes', 500)]);
    $done('Wishlist saved.');
  }
  case 'wishlist_remove': {
    $db->query("DELETE FROM bb_wishlist WHERE user_id = ? AND product_id = ?", [$me, (int)($_POST['product_id'] ?? 0)]);
    $done('Removed from your wishlist.');
  }

  // ---------- Store sightings (shared with everyone) ----------
  case 'sighting_save': {
    $p = $product();
    if (!$p) $done('Please pick a bottle from the catalog.', false);
    $price = $money('price');
    if ($price === null || $price < 1 || $price > 20000) $done('Please enter the shelf price, e.g. 39.99.', false);
    $seen = $date('seen_on') ?? date('Y-m-d');
    if ($seen < date('Y-m-d', strtotime('-2 years'))) $done('Sightings older than two years aren’t useful for prices.', false);
    $storeId = (int)($_POST['store_id'] ?? 0);
    if ($storeId && !$db->query("SELECT id FROM bb_stores WHERE id = ?", [$storeId])->count()) $storeId = 0;
    if (!$storeId) $storeId = bb_store_find_or_create((string)($_POST['store_name'] ?? ''), (string)($_POST['store_city'] ?? ''), (string)($_POST['store_state'] ?? ''), $me);
    if (!$storeId) $done('Please pick a store, or enter its name, city and state.', false);
    // Same person, bottle, store, day and price = a duplicate submit.
    $dupe = $db->query("SELECT id FROM bb_sightings WHERE user_id = ? AND product_id = ? AND store_id = ? AND seen_on = ? AND price = ?", [$me, $p->id, $storeId, $seen, $price])->count();
    if (!$dupe) $db->insert('bb_sightings', ['product_id' => $p->id, 'store_id' => $storeId, 'user_id' => $me, 'price' => $price, 'seen_on' => $seen, 'notes' => $str('notes', 300)]);
    $done('Thanks! Your sighting helps everyone find a fair price.');
  }
  case 'sighting_delete': {
    $id = (int)($_POST['id'] ?? 0);
    $isAdmin = hasPerm([2]);
    $row = $db->query("SELECT id FROM bb_sightings WHERE id = ?" . ($isAdmin ? '' : ' AND user_id = ?'), $isAdmin ? [$id] : [$id, $me])->first();
    if ($row) $db->query("DELETE FROM bb_sightings WHERE id = ?", [$row->id]);
    $done($row ? 'Sighting removed.' : null);
  }

  // ---------- Profile ----------
  case 'profile_save': {
    $private = !empty($_POST['is_private']) ? 1 : 0;
    $was = bb_profile($me)->is_private;
    $db->query("INSERT INTO bb_profiles (user_id, is_private, bio, location) VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE is_private = VALUES(is_private), bio = VALUES(bio), location = VALUES(location)",
      [$me, $private, $str('bio', 280), $str('location', 80)]);
    // Going public approves everyone who was waiting.
    if ($was && !$private) $db->query("UPDATE bb_follows SET status = 'accepted', accepted_at = NOW() WHERE followee_id = ? AND status = 'pending'", [$me]);
    $done('Profile saved.');
  }

  // ---------- Follows ----------
  case 'follow': {
    $target = (int)($_POST['user_id'] ?? 0);
    $exists = $target && $target !== $me && $db->query("SELECT id FROM users WHERE id = ? AND permissions > 0", [$target])->count();
    if (!$exists) $done('That member was not found.', false);
    if (bb_follow_status($me, $target) !== 'none') $done();
    $status = bb_profile($target)->is_private ? 'pending' : 'accepted';
    $db->query("INSERT INTO bb_follows (follower_id, followee_id, status, accepted_at) VALUES (?, ?, ?, ?)",
      [$me, $target, $status, $status === 'accepted' ? date('Y-m-d H:i:s') : null]);
    $done($status === 'pending' ? 'Follow request sent.' : 'You are now following them.');
  }
  case 'unfollow': // also cancels a pending request
    $db->query("DELETE FROM bb_follows WHERE follower_id = ? AND followee_id = ?", [$me, (int)($_POST['user_id'] ?? 0)]);
    $done('Done.');
  case 'request_accept':
    $db->query("UPDATE bb_follows SET status = 'accepted', accepted_at = NOW() WHERE follower_id = ? AND followee_id = ? AND status = 'pending'", [(int)($_POST['user_id'] ?? 0), $me]);
    $done('Request accepted.');
  case 'request_decline':
  case 'follower_remove':
    $db->query("DELETE FROM bb_follows WHERE follower_id = ? AND followee_id = ?", [(int)($_POST['user_id'] ?? 0), $me]);
    $done($do === 'request_decline' ? 'Request declined.' : 'Follower removed.');
}
$done('Unknown action.', false);
