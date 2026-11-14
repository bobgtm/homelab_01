<?php
require_once '../users/init.php';
$pageTitle = 'People —';
require_once $abs_us_root . $us_url_root . 'users/includes/template/prep.php';
if (!securePage(Server::get('PHP_SELF'))) { die(); }
require_once __DIR__ . '/inc/social.php';
require_once __DIR__ . '/inc/lists.php';

$me = bb_me();
$q = mb_substr(bb_get('q'), 0, 60);
$pending = bb_pending_requests_count($me);
$tab = in_array(bb_get('tab'), ['find', 'requests', 'followers', 'following'], true) ? bb_get('tab') : ($pending ? 'requests' : 'find');
$self = $_SERVER['REQUEST_URI'];
$cols = "u.id, u.username, u.fname, u.lname, COALESCE(pr.is_private, 0) AS is_private, pr.bio";
$join = "LEFT JOIN bb_profiles pr ON pr.user_id = u.id";

if ($tab === 'requests') {
  $people = $db->query("SELECT $cols FROM bb_follows f JOIN users u ON u.id = f.follower_id $join
    WHERE f.followee_id = ? AND f.status = 'pending' AND u.permissions > 0 ORDER BY f.created_at DESC", [$me])->results();
} elseif ($tab === 'followers') {
  $people = $db->query("SELECT $cols FROM bb_follows f JOIN users u ON u.id = f.follower_id $join
    WHERE f.followee_id = ? AND f.status = 'accepted' AND u.permissions > 0 ORDER BY f.accepted_at DESC", [$me])->results();
} elseif ($tab === 'following') {
  $people = $db->query("SELECT $cols, f.status AS my_status FROM bb_follows f JOIN users u ON u.id = f.followee_id $join
    WHERE f.follower_id = ? AND u.permissions > 0 ORDER BY f.status, f.created_at DESC", [$me])->results();
} else {
  $params = [$me];
  $where = '';
  if ($q !== '') {
    $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%';
    $where = 'AND (u.username LIKE ? OR u.fname LIKE ? OR u.lname LIKE ?)';
    array_push($params, $like, $like, $like);
  }
  $people = $db->query("SELECT $cols FROM users u $join WHERE u.id <> ? AND u.permissions > 0 $where ORDER BY u.join_date DESC LIMIT 48", $params)->results();
}

// My relationship with each person shown.
$ids = array_map(fn($p) => (int)$p->id, $people);
$rel = [];
if ($ids) {
  $in = implode(',', $ids);
  foreach ($db->query("SELECT followee_id, status FROM bb_follows WHERE follower_id = ? AND followee_id IN ($in)", [$me])->results() as $r) $rel[$r->followee_id] = $r->status;
}
$btn = function ($do, $uid, $label, $class) use ($self, $us_url_root) {
  return '<form method="post" action="' . $us_url_root . 'bourbon/action.php" class="d-inline">' . bb_csrf()
    . '<input type="hidden" name="do" value="' . $do . '"><input type="hidden" name="user_id" value="' . (int)$uid . '">'
    . '<input type="hidden" name="return" value="' . bb_h($self) . '"><button class="' . $class . '" type="submit">' . $label . '</button></form>';
};
$tabs = [
  'find' => 'Find people',
  'requests' => 'Requests' . ($pending ? ' <span class="bb-count bb-count-hot">' . $pending . '</span>' : ''),
  'followers' => 'Followers',
  'following' => 'Following',
];
?>
<div class="bb-page-head d-flex flex-wrap justify-content-between align-items-end gap-3">
  <div>
    <h1>People</h1>
    <p>Follow other collectors to see what they’re pouring.</p>
  </div>
  <a class="btn btn-outline-primary" href="<?= bb_profile_url($user->data()->username) ?>"><i class="fa-solid fa-circle-user me-2"></i>My profile</a>
</div>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
  <?= bb_tabs($tabs, $tab, 'tab') ?>
  <?php if ($tab === 'find') { ?>
    <form method="get" class="d-flex gap-2" role="search">
      <input type="hidden" name="tab" value="find">
      <input class="form-control form-control-sm" type="search" name="q" value="<?= bb_h($q) ?>" placeholder="Name or username" aria-label="Search people">
      <button class="btn btn-primary btn-sm" type="submit">Search</button>
    </form>
  <?php } ?>
</div>

<?php if (!$people) {
  $msgs = ['find' => ['No members found', $q !== '' ? 'Nobody matches “' . bb_h($q) . '”.' : 'No other members yet. Invite a friend!'],
           'requests' => ['No pending requests', 'When someone asks to follow your private profile, it shows up here.'],
           'followers' => ['No followers yet', 'Share your profile so friends can find you.'],
           'following' => ['Not following anyone', 'Find people to follow from the “Find people” tab.']];
  echo bb_empty('fa-user-group', $msgs[$tab][0], $msgs[$tab][1], false);
} else { ?>
  <ul class="bb-people">
    <?php foreach ($people as $p) {
      $st = $rel[$p->id] ?? 'none'; ?>
      <li class="bb-person">
        <a href="<?= bb_profile_url($p->username) ?>" class="d-flex align-items-center gap-3 text-decoration-none flex-grow-1 min-w-0">
          <?= bb_avatar($p) ?>
          <span class="min-w-0">
            <span class="d-block fw-semibold text-truncate bb-person-name"><?= bb_h(bb_display_name($p)) ?>
              <?php if ($p->is_private) { ?><i class="fa-solid fa-lock small text-secondary ms-1" title="Private profile"></i><?php } ?></span>
            <span class="d-block small text-secondary text-truncate">@<?= bb_h($p->username) ?><?= $p->bio && !$p->is_private ? ' · ' . bb_h($p->bio) : '' ?></span>
          </span>
        </a>
        <div class="d-flex gap-2 flex-shrink-0">
          <?php if ($tab === 'requests') { ?>
            <?= $btn('request_accept', $p->id, 'Accept', 'btn btn-primary btn-sm') ?>
            <?= $btn('request_decline', $p->id, 'Decline', 'btn btn-outline-primary btn-sm') ?>
          <?php } else {
            if ($tab === 'followers') echo $btn('follower_remove', $p->id, 'Remove', 'btn btn-link btn-sm text-secondary');
            if ($st === 'accepted') echo $btn('unfollow', $p->id, 'Following', 'btn btn-outline-primary btn-sm');
            elseif ($st === 'pending') echo $btn('unfollow', $p->id, 'Requested', 'btn btn-outline-primary btn-sm');
            else echo $btn('follow', $p->id, $p->is_private ? 'Request' : 'Follow', 'btn btn-primary btn-sm');
          } ?>
        </div>
      </li>
    <?php } ?>
  </ul>
<?php } ?>

<?php require_once $abs_us_root . $us_url_root . 'users/includes/html_footer.php'; ?>
