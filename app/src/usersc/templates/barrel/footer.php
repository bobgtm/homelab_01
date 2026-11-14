<?php
require_once $abs_us_root.$us_url_root.'usersc/templates/barrel/container_close.php';
require_once $abs_us_root.$us_url_root.'users/includes/page_footer.php';
?>
<footer class="bb-footer mt-auto">
  <div class="container d-flex flex-column flex-md-row justify-content-between align-items-center gap-2 py-4">
    <div class="d-flex align-items-center gap-2">
      <?php include __DIR__.'/assets/logo.svg.php'; ?>
      <span>&copy; <?=date('Y')?> <?=htmlspecialchars($settings->copyright)?></span>
    </div>
    <div class="bb-footer-note">Please enjoy responsibly. You must be of legal drinking age to use this site.</div>
  </div>
</footer>
</body>
<?php require_once($abs_us_root.$us_url_root.'users/includes/html_footer.php'); ?>
