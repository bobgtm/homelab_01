<?php
// Profiles, privacy and follows. Privacy rule (single source of truth): bb_can_view().
if (!isset($abs_us_root)) { http_response_code(404); exit; }
require_once __DIR__ . '/catalog.php';

function bb_me() {
  global $user;
  return (isset($user) && $user->isLoggedIn()) ? (int)$user->data()->id : 0;
}

/** Profile settings for a user (defaults when the user never saved any). */
function bb_profile($uid) {
  global $db;
  $p = $db->query("SELECT * FROM bb_profiles WHERE user_id = ?", [$uid])->first();
  return $p ?: (object)['user_id' => $uid, 'is_private' => 0, 'bio' => null, 'location' => null];
}

function bb_user_by_username($username) {
  global $db;
  return $db->query("SELECT id, username, fname, lname, join_date, permissions FROM users WHERE username = ? AND permissions > 0", [$username])->first();
}

function bb_display_name($u) {
  $n = trim(($u->fname ?? '') . ' ' . (isset($u->lname) && $u->lname !== '' ? mb_substr($u->lname, 0, 1) . '.' : ''));
  return $n !== '' ? $n : $u->username;
}

/** 'none' | 'pending' | 'accepted' */
function bb_follow_status($followerId, $followeeId) {
  global $db;
  if (!$followerId || !$followeeId) return 'none';
  $r = $db->query("SELECT status FROM bb_follows WHERE follower_id = ? AND followee_id = ?", [$followerId, $followeeId])->first();
  return $r ? $r->status : 'none';
}

/** Can $viewerId (0 = logged out) see $ownerId's lists? Owner always; public profiles: everyone; private: accepted followers. */
function bb_can_view($viewerId, $ownerId) {
  if ($viewerId && $viewerId == $ownerId) return true;
  if (!bb_profile($ownerId)->is_private) return true;
  return bb_follow_status($viewerId, $ownerId) === 'accepted';
}

function bb_profile_url($username) { return bb_url('bourbon/profile.php', ['u' => $username]); }

function bb_avatar($u, $size = 'md') {
  $initial = mb_strtoupper(mb_substr($u->fname ?: $u->username, 0, 1));
  $hue = crc32($u->username) % 360;
  return '<span class="bb-avatar bb-avatar-' . $size . '" style="--h:' . $hue . '" aria-hidden="true">' . bb_h($initial) . '</span>';
}

/** Read-only star display for a 0.5–5 rating. */
function bb_stars($rating) {
  if ($rating === null) return '<span class="text-secondary small">No rating</span>';
  $r = (float)$rating; $out = '';
  for ($i = 1; $i <= 5; $i++) {
    $out .= $r >= $i ? '<i class="fa-solid fa-star"></i>' : ($r >= $i - 0.5 ? '<i class="fa-solid fa-star-half-stroke"></i>' : '<i class="fa-regular fa-star"></i>');
  }
  return '<span class="bb-stars" title="' . $r . ' out of 5"><span class="visually-hidden">' . $r . ' out of 5</span>' . $out . '</span>';
}

function bb_csrf() { return '<input type="hidden" name="csrf" value="' . Token::generate() . '">'; }

function bb_money($v) { return $v === null ? '' : '$' . number_format((float)$v, 2); }

function bb_date($d) { return $d ? date('M j, Y', strtotime($d)) : ''; }

/** Counts used on profile headers. */
function bb_profile_counts($uid) {
  global $db;
  return $db->query("SELECT
      (SELECT COALESCE(SUM(quantity),0) FROM bb_collection WHERE user_id = ? AND status <> 'finished') AS bottles,
      (SELECT COUNT(DISTINCT product_id) FROM bb_tastings WHERE user_id = ?) AS tried,
      (SELECT COUNT(*) FROM bb_wishlist WHERE user_id = ?) AS wishlist,
      (SELECT COUNT(*) FROM bb_follows WHERE followee_id = ? AND status = 'accepted') AS followers,
      (SELECT COUNT(*) FROM bb_follows WHERE follower_id = ? AND status = 'accepted') AS following",
    [$uid, $uid, $uid, $uid, $uid])->first();
}

function bb_pending_requests_count($uid) {
  global $db;
  return (int)$db->query("SELECT COUNT(*) AS n FROM bb_follows WHERE followee_id = ? AND status = 'pending'", [$uid])->first()->n;
}
