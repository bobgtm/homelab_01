<?php
// Shared catalog helpers: formatting, badges, and bottle images (photo or generated art).
if (!isset($abs_us_root)) { http_response_code(404); exit; }

/** Raw query-string value as a string (UserSpice's Input::get HTML-escapes, which breaks searching). */
function bb_get($key) {
  $v = $_GET[$key] ?? '';
  return is_string($v) ? trim($v) : '';
}

function bb_h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function bb_url($path, array $params = []) {
  global $us_url_root;
  return $us_url_root . $path . ($params ? '?' . http_build_query($params) : '');
}

function bb_proof($p) {
  if ($p->proof === null) return $p->is_barrel_proof ? 'Barrel proof' : null;
  return rtrim(rtrim(number_format((float)$p->proof, 1), '0'), '.') . ' proof';
}
function bb_age($p) { return $p->age_years ? $p->age_years . ' yr' : null; }
function bb_price($p) { return $p->msrp !== null ? '$' . number_format((float)$p->msrp, 0) : null; }

/** Short facts line, e.g. "100 proof · 10 yr · ~$45". */
function bb_facts($p) {
  $bits = array_filter([bb_proof($p), bb_age($p), $p->msrp !== null ? '~' . bb_price($p) : null]);
  return implode(' · ', $bits);
}

/** Feature tags shown as badges. */
function bb_tags($p) {
  $t = [];
  if ($p->is_bottled_in_bond) $t[] = 'Bottled-in-Bond';
  if ($p->is_single_barrel)   $t[] = 'Single Barrel';
  if ($p->is_barrel_proof)    $t[] = 'Barrel Proof';
  if ($p->is_small_batch)     $t[] = 'Small Batch';
  if ($p->is_finished)        $t[] = 'Cask Finished';
  if ($p->mash_style === 'wheated')  $t[] = 'Wheated';
  if ($p->mash_style === 'high-rye') $t[] = 'High Rye';
  if ($p->is_limited)         $t[] = 'Limited';
  return $t;
}

function bb_place($d) {
  return implode(', ', array_filter([$d->city ?? null, $d->state ?? null]));
}

/** <img> for an uploaded/licensed photo, or the generated illustration. */
function bb_bottle_image($p, $class = '', $eager = false) {
  global $us_url_root;
  if (!empty($p->image_file)) {
    // Uploaded photos ("u-*") live on the persistent data volume; Commons photos ship with the code.
    $dir = str_starts_with($p->image_file, 'u-') ? 'bourbon/images/uploads/' : 'bourbon/images/products/';
    $src = $us_url_root . $dir . rawurlencode($p->image_file);
    return '<img class="bb-bottle-photo ' . bb_h($class) . '" src="' . bb_h($src) . '" alt="Photo of ' . bb_h($p->name) . '"' . ($eager ? ' fetchpriority="high"' : ' loading="lazy" decoding="async"') . '>';
  }
  return bb_bottle_art($p, $class);
}

/**
 * Generated bottle illustration (inline SVG). Deterministic per product:
 * shape and label color come from the slug, liquid color from proof.
 */
function bb_bottle_art($p, $class = '') {
  $seed  = crc32($p->slug);
  $uid   = 'b' . $p->id;
  // Liquid: pale gold at 80 proof -> deep copper at 140+.
  $proof = $p->proof !== null ? (float)$p->proof : ($p->is_barrel_proof ? 125 : 95);
  $t     = max(0, min(1, ($proof - 80) / 60));
  $mix   = function ($a, $b) use ($t) {
    $r = ''; for ($i = 0; $i < 3; $i++) $r .= sprintf('%02x', round(hexdec(substr($a, $i * 2, 2)) * (1 - $t) + hexdec(substr($b, $i * 2, 2)) * $t));
    return '#' . $r;
  };
  $liq1 = $mix('f0b456', 'a8451c'); $liq2 = $mix('c47a24', '5e2410');
  $labels = [ // [paper, ink, accent]
    ['#f3e6cc', '#2b1d12', '#9c3d22'], ['#1d1b1a', '#f1e3c4', '#d0a24c'], ['#5b1a1a', '#f6e7c8', '#e0b25e'],
    ['#14283d', '#f1e6cf', '#cfa25a'], ['#1f3a2c', '#f1e6cf', '#d6b16a'], ['#efe7da', '#1c2a3a', '#a33b2a'],
    ['#3a2615', '#f2dfb8', '#e2a84e'], ['#f6f0e4', '#3b1f12', '#2e5e4e'],
  ];
  [$paper, $ink, $accent] = $labels[$seed % count($labels)];
  $caps = ['#2a1a10', '#7a1f1f', '#1c1c1c', '#b8892f', '#203a5a', '#3b2a1a'];
  $cap  = $caps[($seed >> 4) % count($caps)];

  // Four generic silhouettes (body path, neck x/width, cap height, label box).
  $shapes = [
    // classic round-shoulder
    ['M38 70 C38 58 50 54 52 44 L52 26 L68 26 L68 44 C70 54 82 58 82 70 L82 182 Q82 190 74 190 L46 190 Q38 190 38 182 Z', 52, 16, 14, [40, 100, 40, 62]],
    // square shoulder, tall
    ['M34 66 L50 58 L52 30 L68 30 L70 58 L86 66 L86 184 Q86 190 80 190 L40 190 Q34 190 34 184 Z', 52, 16, 16, [36, 96, 48, 60]],
    // squat decanter
    ['M28 104 C28 84 46 78 52 68 L52 40 L68 40 L68 68 C74 78 92 84 92 104 L92 180 Q92 190 82 190 L38 190 Q28 190 28 180 Z', 52, 16, 18, [30, 112, 60, 52]],
    // tall flask with sloped shoulder
    ['M40 78 C40 64 52 60 54 48 L54 22 L66 22 L66 48 C68 60 80 64 80 78 L80 184 Q80 190 74 190 L46 190 Q40 190 40 184 Z', 54, 12, 14, [42, 104, 36, 60]],
  ];
  [$body, $nx, $nw, $ch, $lb] = $shapes[($seed >> 8) % count($shapes)];
  [$lx, $ly, $lw, $lh] = $lb;
  $neckTop = (int)preg_replace('/.*L' . $nx . ' (\d+).*/s', '$1', $body);

  // Label text: brand wrapped to at most 3 short lines.
  $words = preg_split('/\s+/', strtoupper($p->brand));
  $lines = []; $cur = '';
  foreach ($words as $w) {
    if ($cur !== '' && strlen($cur . ' ' . $w) > 10) { $lines[] = $cur; $cur = $w; }
    else { $cur = trim($cur . ' ' . $w); }
  }
  if ($cur !== '') $lines[] = $cur;
  $lines = array_slice($lines, 0, 3);
  // Size text to the label; SVG textLength squeezes anything that would still overflow.
  $avail = $lw - 7;
  $longest = max(array_map('strlen', $lines));
  $fs = max(5, min(9, $avail / max(1, $longest) * 1.35));
  $cx = $lx + $lw / 2;
  $ty = $ly + $lh / 2 - (count($lines) - 1) * $fs * 0.6 - 2;
  $fit = function ($str, $size, $perChar) use ($avail) {
    return strlen($str) * $size * $perChar > $avail ? ' textLength="' . $avail . '" lengthAdjust="spacingAndGlyphs"' : '';
  };
  $text = '';
  foreach ($lines as $i => $ln) {
    $text .= '<text x="' . $cx . '" y="' . round($ty + $i * $fs * 1.15, 1) . '" font-size="' . round($fs, 1) . '"' . $fit($ln, $fs, 0.78)
           . ' fill="' . $ink . '" text-anchor="middle" font-family="Georgia, serif" font-weight="700">' . bb_h($ln) . '</text>';
  }
  $sub = $p->age_years ? $p->age_years . ' YEAR' : ($p->is_bottled_in_bond ? 'BONDED' : ($p->is_single_barrel ? 'SINGLE BARREL' : 'BOURBON'));
  $sfs = min(4.4, $avail / (strlen($sub) * 0.82));
  $text .= '<text x="' . $cx . '" y="' . ($ly + $lh - 6) . '" font-size="' . round($sfs, 2) . '"' . $fit($sub, $sfs, 0.82)
         . ' fill="' . $accent . '" text-anchor="middle" font-family="Helvetica, Arial, sans-serif" font-weight="700" letter-spacing=".4">' . bb_h($sub) . '</text>';

  return '<svg class="bb-bottle-art ' . bb_h($class) . '" viewBox="0 0 120 200" role="img" aria-label="Illustration of ' . bb_h($p->name) . '">'
    . '<defs>'
    . '<linearGradient id="' . $uid . 'l" x1="0" y1="0" x2="1" y2="0"><stop offset="0" stop-color="' . $liq2 . '"/><stop offset=".45" stop-color="' . $liq1 . '"/><stop offset="1" stop-color="' . $liq2 . '"/></linearGradient>'
    . '<linearGradient id="' . $uid . 'g" x1="0" y1="0" x2="1" y2="0"><stop offset="0" stop-color="#fff" stop-opacity=".0"/><stop offset=".22" stop-color="#fff" stop-opacity=".35"/><stop offset=".3" stop-color="#fff" stop-opacity="0"/></linearGradient>'
    . '<clipPath id="' . $uid . 'c"><path d="' . $body . '"/></clipPath>'
    . '</defs>'
    . '<ellipse cx="60" cy="192" rx="34" ry="4" fill="#000" opacity=".35"/>'
    . '<path d="' . $body . '" fill="#2a1d14" opacity=".55"/>'
    . '<rect x="0" y="' . ($neckTop + 24) . '" width="120" height="200" fill="url(#' . $uid . 'l)" clip-path="url(#' . $uid . 'c)"/>'
    . '<path d="' . $body . '" fill="url(#' . $uid . 'g)"/>'
    . '<path d="' . $body . '" fill="none" stroke="#fff" stroke-opacity=".28" stroke-width="1.2"/>'
    . '<rect x="' . ($nx - 1) . '" y="' . ($neckTop - $ch) . '" width="' . ($nw + 2) . '" height="' . ($ch + 2) . '" rx="2" fill="' . $cap . '"/>'
    . '<rect x="' . ($nx - 1) . '" y="' . ($neckTop - $ch) . '" width="' . ($nw + 2) . '" height="3" rx="1.5" fill="#fff" opacity=".18"/>'
    . '<rect x="' . $lx . '" y="' . $ly . '" width="' . $lw . '" height="' . $lh . '" rx="3" fill="' . $paper . '"/>'
    . '<rect x="' . ($lx + 2.5) . '" y="' . ($ly + 2.5) . '" width="' . ($lw - 5) . '" height="' . ($lh - 5) . '" rx="2" fill="none" stroke="' . $accent . '" stroke-width=".7"/>'
    . $text
    . '</svg>';
}

/** Product card used in grids. */
function bb_product_card($p) {
  $url = bb_url('bourbon/bottle.php', ['id' => $p->id]);
  $tags = array_slice(bb_tags($p), 0, 2);
  ob_start(); ?>
  <a class="bb-card" href="<?= bb_h($url) ?>">
    <div class="bb-card-media"><?= bb_bottle_image($p) ?></div>
    <div class="bb-card-body">
      <div class="bb-card-dist"><?= bb_h($p->distillery_name) ?></div>
      <div class="bb-card-name"><?= bb_h($p->name) ?></div>
      <div class="bb-card-facts"><?= bb_h(bb_facts($p)) ?></div>
      <?php if ($tags) { ?><div class="bb-card-tags"><?php foreach ($tags as $t) { ?><span class="badge bb-badge"><?= bb_h($t) ?></span><?php } ?></div><?php } ?>
    </div>
  </a>
  <?php return ob_get_clean();
}
