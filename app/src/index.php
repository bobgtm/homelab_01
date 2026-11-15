<?php
// Barrel Book home page (public).
$bb_full_width = true;
require_once 'users/init.php';
require_once $abs_us_root . $us_url_root . 'users/includes/template/prep.php';

$bb_in = $user->isLoggedIn();
$bb_name = $bb_in ? htmlspecialchars($user->data()->fname ?: $user->data()->username) : '';
$bb_count = 0;
try { $bb_count = (int)$db->query("SELECT COUNT(*) AS n FROM bb_products WHERE status = 'approved'")->first()->n; } catch (Throwable $e) {}

// Sample data for the interactive demo. Prices and store names are illustrative only.
$demo = [
  'collection' => [
    ['Eagle Rare 10 Year', 'Buffalo Trace · 90 proof', '<span class="badge bb-badge">Sealed</span>', 'Paid <span class="bb-price">$39.99</span>'],
    ['Wild Turkey Rare Breed', 'Wild Turkey · 116.8 proof', '<span class="badge bb-badge">Open · ½ left</span>', 'Paid <span class="bb-price">$54.99</span>'],
    ['Four Roses Single Barrel', 'Four Roses · 100 proof', '<span class="badge bb-badge">Open</span>', 'Paid <span class="bb-price">$44.99</span>'],
    ['Weller Special Reserve', 'Buffalo Trace · 90 proof', '<span class="badge bb-badge">Sealed</span>', 'Paid <span class="bb-price">$29.99</span>'],
  ],
  'tried' => [
    ["Blanton's Original Single Barrel", '“Orange peel, honey, a little clove.”', '<span class="bb-stars">★★★★½</span>', 'Tasted Oct 28'],
    ['Old Forester 1920 Prohibition Style', '“Dark cherry and chocolate. Big.”', '<span class="bb-stars">★★★★</span>', 'Tasted Oct 12'],
    ["Maker's Mark 46", '“Baking spice, vanilla, soft finish.”', '<span class="bb-stars">★★★½</span>', 'Tasted Sep 30'],
    ['Russell’s Reserve 10 Year', '“Caramel and oak, easy sipper.”', '<span class="bb-stars">★★★★</span>', 'Tasted Sep 14'],
  ],
  'wishlist' => [
    ['Elijah Craig Barrel Proof', 'Heaven Hill · batch varies', 'Target <span class="bb-price">$75</span>', '<span class="bb-down"><i class="fa-solid fa-bell"></i> Seen at $72</span>'],
    ['Russell’s Reserve 13 Year', 'Wild Turkey · barrel proof', 'Target <span class="bb-price">$70</span>', 'Not seen yet'],
    ['Booker’s', 'Jim Beam · barrel proof', 'Target <span class="bb-price">$90</span>', '<span class="bb-up">Seen at $109</span>'],
    ['Stagg', 'Buffalo Trace · barrel proof', 'Target <span class="bb-price">$60</span>', 'Not seen yet'],
  ],
  'sightings' => [
    ['Oak Street Liquor', 'Eagle Rare 10 Year · 2 days ago', '<span class="bb-price">$39.99</span>', '<span class="bb-down"><i class="fa-solid fa-arrow-down"></i> lowest</span>'],
    ['Riverside Wine & Spirits', 'Eagle Rare 10 Year · 5 days ago', '<span class="bb-price">$44.99</span>', 'average'],
    ['Market Square Bottle Shop', 'Eagle Rare 10 Year · 1 week ago', '<span class="bb-price">$59.99</span>', '<span class="bb-up"><i class="fa-solid fa-arrow-up"></i> high</span>'],
    ['Northside Package Store', 'Eagle Rare 10 Year · 3 weeks ago', '<span class="bb-price">$42.99</span>', 'average'],
  ],
];
$demo_tabs = [
  'collection' => ['Collection', 'fa-box-archive'],
  'tried'      => ['Tried', 'fa-whiskey-glass'],
  'wishlist'   => ['Wishlist', 'fa-star'],
  'sightings'  => ['Sightings', 'fa-tag'],
];
$demo_icons = ['collection' => 'fa-bottle-droplet', 'tried' => 'fa-whiskey-glass', 'wishlist' => 'fa-star', 'sightings' => 'fa-store'];
?>

<section class="bb-hero">
  <div class="container">
    <div class="row align-items-center g-5">
      <div class="col-lg-6">
        <?php if ($bb_in) { ?>
          <div class="bb-eyebrow mb-3">Welcome back, <?= $bb_name ?></div>
        <?php } else { ?>
          <div class="bb-eyebrow mb-3">Your bourbon, organized</div>
        <?php } ?>
        <h1 class="mb-4">Every bottle you own, sip, and <em>hunt&nbsp;for</em>.</h1>
        <p class="bb-lede mb-4">
          Track your shelf, keep tasting notes, build a wishlist, and see what bottles
          are going for at stores near you. Pick from a ready-made catalog of distilleries
          and bottles instead of typing them in yourself.
        </p>
        <div class="d-flex flex-wrap gap-2">
          <?php if ($bb_in) { ?>
            <a class="btn btn-primary btn-lg px-4" href="<?= $us_url_root ?>bourbon/collection.php">Open my collection</a>
            <a class="btn btn-outline-primary btn-lg px-4" href="<?= $us_url_root ?>bourbon/browse.php">Browse the catalog</a>
          <?php } else { ?>
            <?php if ($settings->registration == 1) { ?>
              <a class="btn btn-primary btn-lg px-4" href="<?= $us_url_root ?>users/join.php">Start your collection</a>
            <?php } ?>
            <a class="btn btn-outline-primary btn-lg px-4" href="<?= $us_url_root ?>bourbon/browse.php">Browse the catalog</a>
          <?php } ?>
        </div>
        <div class="bb-stats">
          <div class="bb-stat"><strong>Free</strong><span>to use, always</span></div>
          <div class="bb-stat"><strong><?= $bb_count ? number_format($bb_count) : 'Preloaded' ?></strong><span>bourbons preloaded</span></div>
          <div class="bb-stat"><strong>Your call</strong><span>public or private profile</span></div>
        </div>
      </div>

      <div class="col-lg-6">
        <div class="bb-demo" aria-label="Interactive preview of the app">
          <div class="bb-demo-bar"><i class="dot"></i><i class="dot"></i><i class="dot"></i><span class="ms-2">Preview with sample data</span></div>
          <div class="bb-demo-tabs" role="tablist">
            <?php $first = true; foreach ($demo_tabs as $key => [$label, $icon]) { ?>
              <button class="bb-demo-tab" type="button" role="tab" id="demo-tab-<?= $key ?>"
                      aria-controls="demo-<?= $key ?>" aria-selected="<?= $first ? 'true' : 'false' ?>" tabindex="<?= $first ? '0' : '-1' ?>">
                <i class="fa-solid <?= $icon ?> me-1"></i><?= $label ?>
              </button>
            <?php $first = false; } ?>
          </div>
          <?php $first = true; foreach ($demo as $key => $rows) { ?>
            <div class="bb-demo-panel" role="tabpanel" id="demo-<?= $key ?>" aria-labelledby="demo-tab-<?= $key ?>" <?= $first ? '' : 'hidden' ?>>
              <?php foreach ($rows as [$name, $meta, $right1, $right2]) { ?>
                <div class="bb-bottle">
                  <div class="bb-bottle-swatch"><i class="fa-solid <?= $demo_icons[$key] ?>"></i></div>
                  <div class="min-w-0">
                    <div class="bb-bottle-name"><?= htmlspecialchars($name) ?></div>
                    <div class="bb-bottle-meta"><?= htmlspecialchars($meta) ?></div>
                  </div>
                  <div class="bb-bottle-right">
                    <div><?= $right1 ?></div>
                    <div class="bb-bottle-meta"><?= $right2 ?></div>
                  </div>
                </div>
              <?php } ?>
            </div>
          <?php $first = false; } ?>
        </div>
      </div>
    </div>
  </div>
</section>

<section class="bb-section" id="features">
  <div class="container">
    <div class="text-center mb-5">
      <div class="bb-eyebrow mb-2">What you can do</div>
      <h2 class="bb-section-title">Built for people who love bourbon</h2>
    </div>
    <div class="row g-4">
      <?php
      $features = [
        ['fa-book-open', 'A ready-made catalog', 'Distilleries, brands and bottles are already loaded, with proof and age. Search, tap, add. Missing something? Suggest it.'],
        ['fa-box-archive', 'Your collection', 'Log what you bought, where, when and for how much. Mark bottles sealed, open or finished, and see your shelf at a glance.'],
        ['fa-whiskey-glass', 'Tasting notes', 'Rate what you’ve tried and jot down nose, palate and finish, so you remember what you loved and what to skip.'],
        ['fa-star', 'Wishlist', 'Keep a hunting list with the price you’re willing to pay, and see when someone spots it near that price.'],
        ['fa-tag', 'Store sightings', 'Saw a bottle on a shelf? Log the store and the price. Everyone’s sightings build a shared price history.'],
        ['fa-user-group', 'Friends & privacy', 'Follow other collectors and compare shelves. Profiles are public by default, or go private and approve who can see yours.'],
      ];
      foreach ($features as [$icon, $title, $text]) { ?>
        <div class="col-md-6 col-lg-4">
          <div class="bb-feature">
            <div class="bb-feature-icon"><i class="fa-solid <?= $icon ?>"></i></div>
            <h3><?= $title ?></h3>
            <p><?= $text ?></p>
          </div>
        </div>
      <?php } ?>
    </div>
  </div>
</section>

<section class="bb-section">
  <div class="container">
    <div class="text-center mb-5">
      <div class="bb-eyebrow mb-2">How it works</div>
      <h2 class="bb-section-title">Three steps to a better-kept shelf</h2>
    </div>
    <div class="row g-4 text-center text-md-start">
      <div class="col-md-4">
        <div class="bb-step-num mb-2">1</div>
        <h3 class="h5">Find a bottle</h3>
        <p class="text-secondary">Search the catalog by name, distillery, proof or age.</p>
      </div>
      <div class="col-md-4">
        <div class="bb-step-num mb-2">2</div>
        <h3 class="h5">Add it to a list</h3>
        <p class="text-secondary">Put it in your collection, mark it tried, or add it to your wishlist.</p>
      </div>
      <div class="col-md-4">
        <div class="bb-step-num mb-2">3</div>
        <h3 class="h5">Share what you see</h3>
        <p class="text-secondary">Log store prices and follow friends to see what they’re pouring.</p>
      </div>
    </div>
  </div>
</section>

<section class="bb-section pt-0 border-0">
  <div class="container">
    <div class="bb-cta">
      <?php if ($bb_in) { ?>
        <h2 class="bb-section-title mb-3">Pour one and pick up where you left off</h2>
        <a class="btn btn-primary btn-lg px-4" href="<?= $us_url_root ?>bourbon/browse.php">Browse the catalog</a>
      <?php } else { ?>
        <h2 class="bb-section-title mb-3">Start your shelf in under a minute</h2>
        <p class="text-secondary mb-4">Free account. No credit card. Just bourbon.</p>
        <?php if ($settings->registration == 1) { ?>
          <a class="btn btn-primary btn-lg px-4" href="<?= $us_url_root ?>users/join.php">Create a free account</a>
        <?php } else { ?>
          <a class="btn btn-primary btn-lg px-4" href="<?= $us_url_root ?>users/login.php">Log in</a>
        <?php } ?>
      <?php } ?>
    </div>
  </div>
</section>

<script nonce="<?= htmlspecialchars($userspice_nonce ?? '') ?>">
(function () {
  var tabs = Array.prototype.slice.call(document.querySelectorAll('.bb-demo-tab'));
  function select(tab) {
    tabs.forEach(function (t) {
      var on = t === tab;
      t.setAttribute('aria-selected', on ? 'true' : 'false');
      t.tabIndex = on ? 0 : -1;
      document.getElementById(t.getAttribute('aria-controls')).hidden = !on;
    });
  }
  tabs.forEach(function (tab, i) {
    tab.addEventListener('click', function () { select(tab); });
    tab.addEventListener('keydown', function (e) {
      var d = e.key === 'ArrowRight' ? 1 : e.key === 'ArrowLeft' ? -1 : 0;
      if (!d) return;
      var next = tabs[(i + d + tabs.length) % tabs.length];
      select(next); next.focus();
    });
  });
})();
</script>

<?php require_once $abs_us_root . $us_url_root . 'users/includes/html_footer.php'; ?>
