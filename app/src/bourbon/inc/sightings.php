<?php
// Stores, shared price sightings, price stats and wishlist deals.
if (!isset($abs_us_root)) { http_response_code(404); exit; }
require_once __DIR__ . '/social.php';

const BB_STATES = ['AL','AK','AZ','AR','CA','CO','CT','DC','DE','FL','GA','HI','ID','IL','IN','IA','KS','KY','LA','ME','MD','MA','MI','MN','MS',
  'MO','MT','NE','NV','NH','NJ','NM','NY','NC','ND','OH','OK','OR','PA','RI','SC','SD','TN','TX','UT','VT','VA','WA','WV','WI','WY'];

/** How far back "recent" prices and deals look. */
const BB_RECENT_DAYS = 90;
const BB_DEAL_DAYS = 30;

function bb_store_key($name, $city, $state) {
  $n = fn($s) => trim(preg_replace('/[^a-z0-9]+/', ' ', strtolower($s)));
  return $n($name) . '|' . $n($city) . '|' . strtoupper($state);
}

/** Find a store by name+city+state (case/punctuation-insensitive) or create it. Returns id or null. */
function bb_store_find_or_create($name, $city, $state, $uid) {
  global $db;
  $name = trim(mb_substr($name, 0, 120)); $city = trim(mb_substr($city, 0, 80)); $state = strtoupper(trim($state));
  if ($name === '' || $city === '' || !in_array($state, BB_STATES, true)) return null;
  $key = bb_store_key($name, $city, $state);
  $row = $db->query("SELECT id FROM bb_stores WHERE name_key = ?", [$key])->first();
  if ($row) return (int)$row->id;
  $db->insert('bb_stores', ['name' => $name, 'city' => $city, 'state' => $state, 'name_key' => $key, 'created_by' => $uid]);
  return (int)$db->lastId();
}

function bb_store_label($s) { return $s->name . ' — ' . $s->city . ', ' . $s->state; }

function bb_all_stores() {
  global $db;
  return $db->query("SELECT id, name, city, state FROM bb_stores ORDER BY name, city")->results();
}

/** Reporter shown on a sighting: public profiles by @username, private ones anonymously. */
function bb_reporter($row) {
  if (!empty($row->reporter_private)) return 'a member';
  return '<a href="' . bb_h(bb_profile_url($row->reporter)) . '">@' . bb_h($row->reporter) . '</a>';
}

/** SELECT list + joins for sighting rows with product, store and reporter. */
const BB_SIGHTING_SELECT = "SELECT s.id AS sighting_id, s.price, s.seen_on, s.notes, s.user_id, s.created_at AS logged_at,
    st.id AS store_id, st.name AS store_name, st.city, st.state,
    u.username AS reporter, COALESCE(pr.is_private, 0) AS reporter_private,
    p.id, p.slug, p.name, p.brand, p.proof, p.age_years, p.msrp, p.is_barrel_proof, p.is_bottled_in_bond, p.is_single_barrel,
    p.is_small_batch, p.is_finished, p.is_limited, p.mash_style, p.image_file, d.name AS distillery_name
  FROM bb_sightings s
  JOIN bb_stores st ON st.id = s.store_id
  JOIN bb_products p ON p.id = s.product_id
  JOIN bb_distilleries d ON d.id = p.distillery_id
  JOIN users u ON u.id = s.user_id
  LEFT JOIN bb_profiles pr ON pr.user_id = s.user_id";

/** Price stats for one bottle over the last $days days. */
function bb_price_stats($pid, $days = BB_RECENT_DAYS) {
  global $db;
  $rows = $db->query("SELECT price FROM bb_sightings WHERE product_id = ? AND seen_on >= DATE_SUB(CURDATE(), INTERVAL ? DAY) ORDER BY price", [$pid, $days])->results();
  $p = array_map(fn($r) => (float)$r->price, $rows);
  if (!$p) return null;
  $n = count($p);
  $median = $n % 2 ? $p[intdiv($n, 2)] : ($p[$n / 2 - 1] + $p[$n / 2]) / 2;
  return (object)['n' => $n, 'low' => $p[0], 'high' => $p[$n - 1], 'median' => $median];
}

/**
 * Wishlist deals: recent sightings (by anyone else) at or below the member's target price.
 * Returns the cheapest qualifying sighting per wishlist bottle, newest first.
 */
function bb_wishlist_deals($uid, $sinceSeen = false) {
  global $db;
  $extra = '';
  $params = [$uid, $uid, BB_DEAL_DAYS];
  if ($sinceSeen) {
    $extra = "AND s.created_at > COALESCE((SELECT deals_seen_at FROM bb_profiles WHERE user_id = ?), '1970-01-01')";
    $params[] = $uid;
  }
  $rows = $db->query(BB_SIGHTING_SELECT . " JOIN bb_wishlist w ON w.product_id = s.product_id AND w.user_id = ?
      WHERE s.user_id <> ? AND w.target_price IS NOT NULL AND s.price <= w.target_price
        AND s.seen_on >= DATE_SUB(CURDATE(), INTERVAL ? DAY) $extra
      ORDER BY s.price ASC, s.seen_on DESC", $params)->results();
  $best = [];
  foreach ($rows as $r) if (!isset($best[$r->id])) $best[$r->id] = $r;
  uasort($best, fn($a, $b) => strcmp($b->seen_on, $a->seen_on));
  return $best;
}

function bb_new_deals_count($uid) {
  try { return count(bb_wishlist_deals($uid, true)); } catch (Throwable $e) { return 0; }
}

function bb_mark_deals_seen($uid) {
  global $db;
  $db->query("INSERT INTO bb_profiles (user_id, deals_seen_at) VALUES (?, NOW()) ON DUPLICATE KEY UPDATE deals_seen_at = NOW()", [$uid]);
}

/** Lowest recent sighting per product for a set of product ids: [pid => row]. */
function bb_best_recent($pids) {
  global $db;
  $pids = array_values(array_unique(array_map('intval', $pids)));
  if (!$pids) return [];
  $in = implode(',', $pids);
  $rows = $db->query("SELECT s.product_id, s.price, s.seen_on, st.id AS store_id, st.name AS store_name, st.city, st.state
      FROM bb_sightings s JOIN bb_stores st ON st.id = s.store_id
     WHERE s.product_id IN ($in) AND s.seen_on >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
     ORDER BY s.price ASC, s.seen_on DESC", [BB_RECENT_DAYS])->results();
  $out = [];
  foreach ($rows as $r) if (!isset($out[$r->product_id])) $out[$r->product_id] = $r;
  return $out;
}

function bb_store_link($r) {
  return '<a href="' . bb_h(bb_url('bourbon/store.php', ['id' => $r->store_id])) . '">' . bb_h($r->store_name) . '</a>'
    . ' <span class="text-secondary">' . bb_h($r->city . ', ' . $r->state) . '</span>';
}

function bb_ago($date) {
  $d = (int)floor((strtotime(date('Y-m-d')) - strtotime($date)) / 86400);
  return $d <= 0 ? 'today' : ($d === 1 ? 'yesterday' : ($d < 14 ? "$d days ago" : ($d < 60 ? floor($d / 7) . ' weeks ago' : bb_date($date))));
}

/**
 * Price-over-time chart (inline SVG): one dot per sighting, a dashed line for the
 * member's target price when given. Single series, so no legend; the list below is the table view.
 */
function bb_price_chart($rows, $target = null, $msrp = null) {
  if (count($rows) < 2) return '';
  $W = 1000; $H = 300; $L = 56; $R = 112; $T = 16; $B = 34;
  $t = array_map(fn($r) => strtotime($r->seen_on), $rows);
  $v = array_map(fn($r) => (float)$r->price, $rows);
  $refs = array_filter([$target, $msrp], fn($x) => $x !== null);
  $lo = min(array_merge($v, $refs)); $hi = max(array_merge($v, $refs));
  // "Nice" y range with 4 ticks.
  $span = max(1, $hi - $lo);
  foreach ([1, 2, 5, 10, 20, 25, 50, 100, 200, 500, 1000] as $s) { $step = $s; if ($span / $s <= 4) break; }
  $y0 = floor(($lo - $step * 0.25) / $step) * $step; $y1 = ceil(($hi + $step * 0.25) / $step) * $step;
  if ($y0 < 0) $y0 = 0;
  $x0 = min($t); $x1 = max($t); if ($x1 - $x0 < 86400 * 7) { $x0 -= 86400 * 3; $x1 += 86400 * 3; }
  $px = fn($ts) => $L + ($ts - $x0) / ($x1 - $x0) * ($W - $L - $R);
  $py = fn($val) => $T + (1 - ($val - $y0) / ($y1 - $y0)) * ($H - $T - $B);
  $o = '<svg class="bb-chart" viewBox="0 0 ' . $W . ' ' . $H . '" role="img" aria-label="Price sightings over time, from $'
     . number_format(min($v), 2) . ' to $' . number_format(max($v), 2) . '">';
  for ($y = $y0; $y <= $y1 + 0.001; $y += $step) {
    $yy = round($py($y), 1);
    $o .= '<line x1="' . $L . '" x2="' . ($W - $R) . '" y1="' . $yy . '" y2="' . $yy . '" class="bb-chart-grid"/>'
        . '<text x="' . ($L - 8) . '" y="' . ($yy + 4) . '" class="bb-chart-tick" text-anchor="end">$' . number_format($y) . '</text>';
  }
  // x ticks: first, middle, last dates
  foreach (array_unique([$x0, (int)(($x0 + $x1) / 2), $x1]) as $i => $ts) {
    $anchor = $i === 0 ? 'start' : ($ts === $x1 ? 'end' : 'middle');
    $o .= '<text x="' . round($px($ts), 1) . '" y="' . ($H - 8) . '" class="bb-chart-tick" text-anchor="' . $anchor . '">' . date('M j', $ts) . '</text>';
  }
  // Keep the two margin labels from overlapping when the prices are close.
  $nudge = 0;
  if ($msrp !== null && $target !== null && abs($py($msrp) - $py($target)) < 18) $nudge = (18 - abs($py($msrp) - $py($target))) / 2;
  $sign = ($msrp !== null && $target !== null && $py($msrp) >= $py($target)) ? 1 : -1;
  if ($msrp !== null) {
    $yy = round($py($msrp), 1);
    $o .= '<line x1="' . $L . '" x2="' . ($W - $R) . '" y1="' . $yy . '" y2="' . $yy . '" class="bb-chart-ref"/>'
        . '<text x="' . ($W - $R + 8) . '" y="' . round($yy + 4 + $sign * $nudge, 1) . '" class="bb-chart-reflabel">typical ~$' . number_format($msrp) . '</text>';
  }
  if ($target !== null) {
    $yy = round($py($target), 1);
    $o .= '<line x1="' . $L . '" x2="' . ($W - $R) . '" y1="' . $yy . '" y2="' . $yy . '" class="bb-chart-target"/>'
        . '<text x="' . ($W - $R + 8) . '" y="' . round($yy + 4 - $sign * $nudge, 1) . '" class="bb-chart-reflabel bb-chart-targetlabel">target $' . number_format($target) . '</text>';
  }
  foreach ($rows as $i => $r) {
    $cx = round($px($t[$i]), 1); $cy = round($py($v[$i]), 1);
    $tip = '$' . number_format($v[$i], 2) . ' · ' . $r->store_name . ', ' . $r->city . ' · ' . date('M j, Y', $t[$i]);
    $o .= '<g class="bb-chart-pt" tabindex="0" data-tip="' . bb_h($tip) . '"><circle cx="' . $cx . '" cy="' . $cy . '" r="12" class="bb-chart-hit"/>'
        . '<circle cx="' . $cx . '" cy="' . $cy . '" r="5" class="bb-chart-dot"/><title>' . bb_h($tip) . '</title></g>';
  }
  return $o . '</svg>';
}
