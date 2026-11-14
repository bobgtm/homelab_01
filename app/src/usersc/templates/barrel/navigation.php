<?php
// Barrel Book top navigation (file-based; ignores UserSpice's menu manager).
$bb_root  = $us_url_root;
$bb_self  = ltrim(substr(Server::get('PHP_SELF'), strlen($us_url_root)), '/');
$bb_in    = isset($user) && $user->isLoggedIn();
$bb_admin = $bb_in && hasPerm([2]);
$bb_pending = 0; $bb_deals = 0; $bb_queue = 0;
if ($bb_in) {
  try { $bb_pending = (int)$db->query("SELECT COUNT(*) AS n FROM bb_follows WHERE followee_id = ? AND status = 'pending'", [$user->data()->id])->first()->n; }
  catch (Throwable $e) {}
  if (file_exists($abs_us_root . $us_url_root . 'bourbon/inc/sightings.php')) {
    require_once $abs_us_root . $us_url_root . 'bourbon/inc/sightings.php';
    $bb_deals = bb_new_deals_count((int)$user->data()->id);
  }
  if ($bb_admin) {
    try { $bb_queue = (int)$db->query("SELECT COUNT(*) AS n FROM bb_products WHERE status = 'pending'")->first()->n; } catch (Throwable $e) {}
  }
}
$bb_active = function ($page) use ($bb_self) {
  return $bb_self === $page ? ' active" aria-current="page' : '';
};
$bb_links = $bb_in
  ? [
      'bourbon/browse.php'     => ['Browse', 'fa-magnifying-glass'],
      'bourbon/collection.php' => ['Collection', 'fa-box-archive'],
      'bourbon/tried.php'      => ['Tried', 'fa-whiskey-glass'],
      'bourbon/wishlist.php'   => ['Wishlist', 'fa-star'],
      'bourbon/sightings.php'  => ['Sightings', 'fa-tag'],
      'bourbon/people.php'     => ['People', 'fa-user-group'],
    ]
  : [
      'bourbon/browse.php' => ['Browse the catalog', 'fa-magnifying-glass'],
      'index.php#features' => ['How it works', 'fa-circle-info'],
    ];
?>
<nav class="navbar navbar-expand-xl bb-navbar sticky-top">
  <div class="container">
    <a class="navbar-brand bb-brand" href="<?=$bb_root?>">
      <?php include __DIR__.'/assets/logo.svg.php'; ?>
      <span><?=htmlspecialchars($settings->site_name)?></span>
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#bbNav"
            aria-controls="bbNav" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="bbNav">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">
        <?php foreach ($bb_links as $href => [$label, $icon]) { ?>
          <li class="nav-item">
            <a class="nav-link<?=$bb_active($href)?>" href="<?=$bb_root.$href?>">
              <i class="fa-solid <?=$icon?> me-1"></i><?=$label?>
              <?php if ($href === 'bourbon/people.php' && $bb_pending) { ?><span class="bb-count bb-count-hot ms-1" title="Follow requests"><?=$bb_pending?></span><?php } ?>
              <?php if ($href === 'bourbon/wishlist.php' && $bb_deals) { ?><span class="bb-count bb-count-hot ms-1" title="New deals on your wishlist"><?=$bb_deals?></span><?php } ?>
            </a>
          </li>
        <?php } ?>
      </ul>
      <ul class="navbar-nav align-items-xl-center gap-xl-1">
        <li class="nav-item d-flex align-items-center py-2 py-xl-0 me-xl-1">
          <button type="button" class="bb-theme-toggle" id="bbThemeToggle" aria-label="Switch to dark mode" title="Switch theme">
            <i class="fa-solid fa-moon bb-icon-moon" aria-hidden="true"></i><i class="fa-solid fa-sun bb-icon-sun" aria-hidden="true"></i>
          </button>
        </li>
        <?php if ($bb_in) { ?>
          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
              <i class="fa-solid fa-circle-user me-1"></i><?=htmlspecialchars($user->data()->username)?><?php if ($bb_queue) { ?><span class="bb-count bb-count-hot ms-1" title="Suggestions to review"><?=$bb_queue?></span><?php } ?>
            </a>
            <ul class="dropdown-menu dropdown-menu-end">
              <li><a class="dropdown-item" href="<?=$bb_root?>bourbon/profile.php?u=<?=urlencode($user->data()->username)?>"><i class="fa-solid fa-circle-user me-2"></i>My profile</a></li>
              <li><a class="dropdown-item" href="<?=$bb_root?>bourbon/suggest.php"><i class="fa-solid fa-circle-plus me-2"></i>Suggest a bottle</a></li>
              <li><a class="dropdown-item" href="<?=$bb_root?>users/user_settings.php"><i class="fa-solid fa-gear me-2"></i>Account settings</a></li>
              <?php if ($bb_admin) { ?>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item d-flex align-items-center" href="<?=$bb_root?>bourbon/admin.php"><i class="fa-solid fa-book-open me-2"></i>Catalog admin<?php if ($bb_queue) { ?><span class="bb-count bb-count-hot ms-auto" title="Suggestions to review"><?=$bb_queue?></span><?php } ?></a></li>
                <li><a class="dropdown-item" href="<?=$bb_root?>users/admin.php"><i class="fa-solid fa-screwdriver-wrench me-2"></i>Site admin (users)</a></li>
              <?php } ?>
              <li><hr class="dropdown-divider"></li>
              <li><a class="dropdown-item" href="<?=$bb_root?>users/logout.php"><i class="fa-solid fa-right-from-bracket me-2"></i>Log out</a></li>
            </ul>
          </li>
        <?php } else { ?>
          <li class="nav-item"><a class="nav-link<?=$bb_active('users/login.php')?>" href="<?=$bb_root?>users/login.php">Log in</a></li>
          <?php if ($settings->registration == 1) { ?>
            <li class="nav-item ms-lg-2"><a class="btn btn-primary btn-sm px-3" href="<?=$bb_root?>users/join.php">Sign up free</a></li>
          <?php } ?>
        <?php } ?>
      </ul>
    </div>
  </div>
</nav>
<script nonce="<?=htmlspecialchars($userspice_nonce ?? '')?>">
(function () {
  var root = document.documentElement, btn = document.getElementById('bbThemeToggle');
  function label() {
    var dark = root.getAttribute('data-bs-theme') === 'dark';
    btn.setAttribute('aria-label', dark ? 'Switch to light mode' : 'Switch to dark mode');
    btn.setAttribute('aria-pressed', dark ? 'true' : 'false');
  }
  btn.addEventListener('click', function () {
    var next = root.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';
    root.setAttribute('data-bs-theme', next);
    try { localStorage.setItem('bbTheme', next); } catch (e) {}
    label();
  });
  // Follow the OS setting live until the visitor picks a theme themselves.
  if (window.matchMedia) matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function (e) {
    var saved = null; try { saved = localStorage.getItem('bbTheme'); } catch (err) {}
    if (!saved) { root.setAttribute('data-bs-theme', e.matches ? 'dark' : 'light'); label(); }
  });
  label();
})();
</script>
