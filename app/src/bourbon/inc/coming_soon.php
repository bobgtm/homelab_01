<?php
// Placeholder body for pages that arrive in later build stages.
if (!isset($abs_us_root)) { http_response_code(404); exit; }
?>
<div class="bb-page-head">
  <h1><i class="fa-solid <?= $bb_icon ?> me-2 text-primary"></i><?= htmlspecialchars($bb_title) ?></h1>
  <p><?= htmlspecialchars($bb_desc) ?></p>
</div>
<div class="bb-empty">
  <div class="bb-feature-icon"><i class="fa-solid fa-hammer"></i></div>
  <h2 class="h4">Coming soon</h2>
  <p class="mb-0">This part of the site is still being built.</p>
</div>
