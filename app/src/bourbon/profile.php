<?php
require_once '../users/init.php';
require_once __DIR__ . '/inc/lists.php';

$me = bb_me();
$uname = bb_get('u');
if ($uname === '' && $me) { Redirect::to(bb_profile_url($user->data()->username)); exit; }
$owner = $uname !== '' ? bb_user_by_username($uname) : null;
if ($owner) $pageTitle = bb_display_name($owner) . ' (@' . $owner->username . ') —';

require_once $abs_us_root . $us_url_root . 'users/includes/template/prep.php';
if (!securePage(Server::get('PHP_SELF'))) { die(); }

if (!$owner) { http_response_code(404); ?>
  <div class="bb-empty my-5"><h1 class="h4">Member not found</h1><a class="btn btn-primary btn-sm" href="<?= bb_url('bourbon/people.php') ?>">Find people</a></div>
  <?php require_once $abs_us_root . $us_url_root . 'users/includes/html_footer.php'; exit;
}

$isMe = $me && $me == $owner->id;
$prof = bb_profile($owner->id);
$canView = bb_can_view($me, $owner->id);
$follow = bb_follow_status($me, $owner->id);
$theyFollowMe = bb_follow_status($owner->id, $me) === 'accepted';
$counts = bb_profile_counts($owner->id);
$tab = in_array(bb_get('tab'), ['collection', 'tried', 'wishlist'], true) ? bb_get('tab') : 'collection';
$self = $_SERVER['REQUEST_URI'];
$post = function ($do, $label, $class) use ($owner, $self, $us_url_root) {
  return '<form method="post" action="' . $us_url_root . 'bourbon/action.php" class="d-inline">' . bb_csrf()
    . '<input type="hidden" name="do" value="' . $do . '"><input type="hidden" name="user_id" value="' . (int)$owner->id . '">'
    . '<input type="hidden" name="return" value="' . bb_h($self) . '"><button class="' . $class . '" type="submit">' . $label . '</button></form>';
};
?>

<section class="bb-profile-head">
  <?= bb_avatar($owner, 'lg') ?>
  <div class="flex-grow-1 min-w-0">
    <h1 class="mb-0"><?= bb_h(bb_display_name($owner)) ?>
      <?php if ($prof->is_private) { ?><i class="fa-solid fa-lock small text-secondary ms-1" title="Private profile"></i><?php } ?></h1>
    <div class="text-secondary">@<?= bb_h($owner->username) ?>
      <?php if ($prof->location) { ?><span class="mx-2">·</span><i class="fa-solid fa-location-dot me-1"></i><?= bb_h($prof->location) ?><?php } ?>
      <span class="mx-2">·</span>Member since <?= date('M Y', strtotime($owner->join_date)) ?>
      <?php if ($theyFollowMe && !$isMe) { ?><span class="badge bb-badge ms-2">Follows you</span><?php } ?>
    </div>
    <?php if ($prof->bio && $canView) { ?><p class="mt-2 mb-0"><?= nl2br(bb_h($prof->bio)) ?></p><?php } ?>
    <div class="bb-profile-counts mt-2">
      <span><strong><?= (int)$counts->followers ?></strong> follower<?= $counts->followers == 1 ? '' : 's' ?></span>
      <span><strong><?= (int)$counts->following ?></strong> following</span>
    </div>
  </div>
  <div class="bb-profile-actions">
    <?php if ($isMe) { ?>
      <button class="btn btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#bbEditProfile" aria-expanded="false" aria-controls="bbEditProfile"><i class="fa-solid fa-pen me-2"></i>Edit profile</button>
    <?php } elseif (!$me) { ?>
      <a class="btn btn-primary" href="<?= $us_url_root ?>users/login.php">Log in to follow</a>
    <?php } elseif ($follow === 'accepted') { ?>
      <?= $post('unfollow', '<i class="fa-solid fa-check me-2"></i>Following', 'btn btn-outline-primary') ?>
    <?php } elseif ($follow === 'pending') { ?>
      <?= $post('unfollow', '<i class="fa-regular fa-clock me-2"></i>Requested · cancel', 'btn btn-outline-primary') ?>
    <?php } else { ?>
      <?= $post('follow', $prof->is_private ? '<i class="fa-solid fa-user-plus me-2"></i>Request to follow' : '<i class="fa-solid fa-user-plus me-2"></i>Follow', 'btn btn-primary') ?>
    <?php } ?>
  </div>
</section>

<?php if ($isMe) { ?>
  <div class="collapse" id="bbEditProfile">
    <form class="bb-panel mb-4" method="post" action="<?= $us_url_root ?>bourbon/action.php">
      <?= bb_csrf() ?><input type="hidden" name="do" value="profile_save"><input type="hidden" name="return" value="<?= bb_h($self) ?>">
      <div class="row g-3">
        <div class="col-md-8"><label class="form-label" for="pf-bio">Bio</label>
          <textarea class="form-control" id="pf-bio" name="bio" rows="2" maxlength="280" placeholder="Wheated fan, store-pick hunter…"><?= bb_h($prof->bio) ?></textarea></div>
        <div class="col-md-4"><label class="form-label" for="pf-loc">Location</label>
          <input class="form-control" id="pf-loc" name="location" maxlength="80" value="<?= bb_h($prof->location) ?>" placeholder="City, State"></div>
        <div class="col-12">
          <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" role="switch" id="pf-private" name="is_private" value="1"<?= $prof->is_private ? ' checked' : '' ?>>
            <label class="form-check-label" for="pf-private"><strong>Private profile</strong></label>
          </div>
          <p class="small text-secondary mb-0">Public: anyone can see your collection, tastings and wishlist, and anyone can follow you.
            Private: only people whose follow request you approve can see them. What you paid and your private notes are never shown to anyone.</p>
        </div>
        <div class="col-12 d-flex gap-2">
          <button class="btn btn-primary" type="submit">Save profile</button>
          <a class="btn btn-outline-primary" href="<?= $us_url_root ?>users/user_settings.php">Account &amp; password</a>
        </div>
      </div>
    </form>
  </div>
<?php } ?>

<?php if (!$canView) { ?>
  <div class="bb-empty mt-4">
    <div class="bb-feature-icon"><i class="fa-solid fa-lock"></i></div>
    <h2 class="h5">This profile is private</h2>
    <p class="mb-0"><?= $follow === 'pending' ? 'Your follow request is waiting for approval.' : 'Follow ' . bb_h(bb_display_name($owner)) . ' to see their collection, tastings and wishlist once they approve.' ?></p>
  </div>
<?php } else {
  $tabs = [
    'collection' => 'Collection <span class="bb-count">' . (int)$counts->bottles . '</span>',
    'tried' => 'Tried <span class="bb-count">' . (int)$counts->tried . '</span>',
    'wishlist' => 'Wishlist <span class="bb-count">' . (int)$counts->wishlist . '</span>',
  ]; ?>
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-4 mb-3">
    <?= bb_tabs($tabs, $tab, 'tab', ['u' => $owner->username]) ?>
    <?php if ($isMe) { ?><a class="small" href="<?= bb_url('bourbon/' . ($tab === 'tried' ? 'tried' : $tab) . '.php') ?>">Manage my <?= $tab === 'tried' ? 'tastings' : $tab ?> <i class="fa-solid fa-arrow-right ms-1"></i></a><?php } ?>
  </div>
  <?php
  $name = bb_h(bb_display_name($owner));
  if ($tab === 'collection') {
    $rows = bb_collection_rows($owner->id, 'shelf', 'recent');
    echo $rows ? bb_render_collection($rows, false) : bb_empty('fa-box-archive', 'No bottles yet', "$name hasn’t added any bottles.", false);
  } elseif ($tab === 'tried') {
    $groups = bb_tried_groups($owner->id, 'recent');
    echo $groups ? bb_render_tried($groups, false) : bb_empty('fa-whiskey-glass', 'No tastings yet', "$name hasn’t logged any tastings.", false);
  } else {
    $rows = bb_wishlist_rows($owner->id, 'recent');
    echo $rows ? bb_render_wishlist($rows, false) : bb_empty('fa-star', 'Wishlist is empty', "$name hasn’t added any bottles to their wishlist.", false);
  }
} ?>

<?php require_once $abs_us_root . $us_url_root . 'users/includes/html_footer.php'; ?>
