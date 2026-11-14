<?php
// Shared add/edit dialogs for collection, tastings and wishlist.
// Usage: bb_list_forms() once per page, then buttons with bb_open_button().
if (!isset($abs_us_root)) { http_response_code(404); exit; }
require_once __DIR__ . '/sightings.php';

/**
 * A button that opens one of the dialogs.
 * $kind: collection | tasting | wishlist; $product: object with id, name; $item: existing row (edit) or null (add).
 */
function bb_open_button($kind, $product, $item = null, $label = null, $class = 'btn btn-outline-primary btn-sm', $extra = []) {
  $payload = ['product' => $product ? ['id' => (int)$product->id, 'name' => $product->name] : null, 'item' => $item ? (array)$item : null] + $extra;
  return '<button type="button" class="' . bb_h($class) . '" data-bb-open="' . bb_h($kind) . '" data-bb=\'' . bb_h(json_encode($payload)) . '\'>'
    . ($label ?? 'Edit') . '</button>';
}

function bb_list_forms() {
  global $us_url_root, $userspice_nonce;
  $action = $us_url_root . 'bourbon/action.php';
  $return = bb_h($_SERVER['REQUEST_URI'] ?? ($us_url_root . 'bourbon/collection.php'));
  $csrf = bb_csrf();
  $today = date('Y-m-d');
  $stores = bb_all_stores();
  ob_start(); ?>

<!-- Collection -->
<div class="modal fade" id="bbModal-collection" tabindex="-1" aria-labelledby="bbModal-collection-title" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
    <form class="modal-content" method="post" action="<?= $action ?>">
      <div class="modal-header">
        <div>
          <h2 class="modal-title h5" id="bbModal-collection-title" data-add="Add to collection" data-edit="Edit bottle">Add to collection</h2>
          <div class="small text-secondary" data-bb-name></div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <?= $csrf ?><input type="hidden" name="do" value="collection_save"><input type="hidden" name="return" value="<?= $return ?>">
        <input type="hidden" name="product_id"><input type="hidden" name="id">
        <label class="form-label">Status</label>
        <div class="bb-segment mb-3" role="radiogroup">
          <?php foreach (['sealed' => 'Sealed', 'open' => 'Open', 'finished' => 'Finished'] as $v => $l) { ?>
            <input type="radio" class="btn-check" name="status" id="bbc-st-<?= $v ?>" value="<?= $v ?>"<?= $v === 'sealed' ? ' checked' : '' ?>>
            <label class="btn btn-outline-primary btn-sm" for="bbc-st-<?= $v ?>"><?= $l ?></label>
          <?php } ?>
        </div>
        <div class="mb-3" data-bb-fill>
          <label class="form-label" for="bbc-fill">How much is left? <span class="text-secondary" data-bb-fill-out>100%</span></label>
          <input type="range" class="form-range" id="bbc-fill" name="fill_level" min="0" max="100" step="5" value="100">
        </div>
        <div class="row g-2">
          <div class="col-4"><label class="form-label" for="bbc-qty">Bottles</label><input class="form-control" id="bbc-qty" type="number" name="quantity" min="1" max="99" value="1"></div>
          <div class="col-8"><label class="form-label" for="bbc-price">Price paid (each)</label>
            <div class="input-group"><span class="input-group-text">$</span><input class="form-control" id="bbc-price" name="purchase_price" inputmode="decimal" placeholder="Optional"></div></div>
          <div class="col-6"><label class="form-label" for="bbc-date">Purchased</label><input class="form-control" id="bbc-date" type="date" name="purchase_date" max="<?= $today ?>"></div>
          <div class="col-6"><label class="form-label" for="bbc-store">Store</label><input class="form-control" id="bbc-store" name="store_name" maxlength="120" placeholder="Optional"></div>
          <div class="col-12"><label class="form-label" for="bbc-notes">Notes</label><textarea class="form-control" id="bbc-notes" name="notes" rows="2" maxlength="1000" placeholder="Gift, store pick, barrel number…"></textarea></div>
        </div>
        <div class="form-check mt-3" data-bb-wishflag hidden>
          <input class="form-check-input" type="checkbox" name="remove_from_wishlist" value="1" id="bbc-wish" checked>
          <label class="form-check-label" for="bbc-wish">Remove it from my wishlist</label>
        </div>
        <p class="small text-secondary mt-3 mb-0"><i class="fa-solid fa-lock me-1"></i>What you paid and your notes are only ever visible to you.</p>
      </div>
      <div class="modal-footer justify-content-between">
        <button type="submit" class="btn btn-link text-danger px-0" form="bbDel-collection" data-bb-delete hidden>Remove bottle</button>
        <div class="ms-auto d-flex gap-2"><button type="button" class="btn btn-outline-primary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Save</button></div>
      </div>
    </form>
  </div>
</div>
<form id="bbDel-collection" method="post" action="<?= $action ?>" data-bb-confirm="Remove this bottle from your collection?" hidden><?= $csrf ?><input type="hidden" name="do" value="collection_delete"><input type="hidden" name="return" value="<?= $return ?>"><input type="hidden" name="id"></form>

<!-- Tasting -->
<div class="modal fade" id="bbModal-tasting" tabindex="-1" aria-labelledby="bbModal-tasting-title" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
    <form class="modal-content" method="post" action="<?= $action ?>">
      <div class="modal-header">
        <div>
          <h2 class="modal-title h5" id="bbModal-tasting-title" data-add="Log a tasting" data-edit="Edit tasting">Log a tasting</h2>
          <div class="small text-secondary" data-bb-name></div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <?= $csrf ?><input type="hidden" name="do" value="tasting_save"><input type="hidden" name="return" value="<?= $return ?>">
        <input type="hidden" name="product_id"><input type="hidden" name="id">
        <fieldset class="mb-3">
          <legend class="form-label fs-6">Your rating</legend>
          <div class="bb-rate" data-bb-rate>
            <?php for ($i = 1; $i <= 10; $i++) { $v = $i / 2; ?>
              <input type="radio" name="rating" id="bbt-r<?= $i ?>" value="<?= $v ?>"><label for="bbt-r<?= $i ?>" class="<?= $i % 2 ? 'half' : 'full' ?>" title="<?= $v ?> stars"><span class="visually-hidden"><?= $v ?> stars</span></label>
            <?php } ?>
            <span class="bb-rate-out ms-2 text-secondary small" data-bb-rate-out>Not rated</span>
            <button type="button" class="btn btn-link btn-sm text-secondary p-0 ms-2" data-bb-rate-clear>Clear</button>
          </div>
        </fieldset>
        <div class="row g-2">
          <div class="col-12"><label class="form-label" for="bbt-date">Tasted on</label><input class="form-control" id="bbt-date" type="date" name="tasted_on" max="<?= $today ?>" value="<?= $today ?>"></div>
          <div class="col-12"><label class="form-label" for="bbt-nose">Nose</label><input class="form-control" id="bbt-nose" name="nose" maxlength="500" placeholder="Caramel, cherry, oak…"></div>
          <div class="col-12"><label class="form-label" for="bbt-palate">Palate</label><input class="form-control" id="bbt-palate" name="palate" maxlength="500"></div>
          <div class="col-12"><label class="form-label" for="bbt-finish">Finish</label><input class="form-control" id="bbt-finish" name="finish" maxlength="500"></div>
          <div class="col-12"><label class="form-label" for="bbt-notes">Other notes</label><textarea class="form-control" id="bbt-notes" name="notes" rows="2" maxlength="1000" placeholder="Neat, with a drop of water, at a friend’s…"></textarea></div>
        </div>
        <p class="small text-secondary mt-3 mb-0"><i class="fa-solid fa-eye me-1"></i>Ratings and tasting notes appear on your profile for anyone who can see it.</p>
      </div>
      <div class="modal-footer justify-content-between">
        <button type="submit" class="btn btn-link text-danger px-0" form="bbDel-tasting" data-bb-delete hidden>Delete tasting</button>
        <div class="ms-auto d-flex gap-2"><button type="button" class="btn btn-outline-primary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Save</button></div>
      </div>
    </form>
  </div>
</div>
<form id="bbDel-tasting" method="post" action="<?= $action ?>" data-bb-confirm="Delete this tasting?" hidden><?= $csrf ?><input type="hidden" name="do" value="tasting_delete"><input type="hidden" name="return" value="<?= $return ?>"><input type="hidden" name="id"></form>

<!-- Wishlist -->
<div class="modal fade" id="bbModal-wishlist" tabindex="-1" aria-labelledby="bbModal-wishlist-title" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form class="modal-content" method="post" action="<?= $action ?>">
      <div class="modal-header">
        <div>
          <h2 class="modal-title h5" id="bbModal-wishlist-title" data-add="Add to wishlist" data-edit="Edit wishlist item">Add to wishlist</h2>
          <div class="small text-secondary" data-bb-name></div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <?= $csrf ?><input type="hidden" name="do" value="wishlist_save"><input type="hidden" name="return" value="<?= $return ?>">
        <input type="hidden" name="product_id">
        <label class="form-label" for="bbw-target">Most you’d pay</label>
        <div class="input-group mb-3"><span class="input-group-text">$</span><input class="form-control" id="bbw-target" name="target_price" inputmode="decimal" placeholder="Optional"></div>
        <label class="form-label" for="bbw-notes">Notes</label>
        <textarea class="form-control" id="bbw-notes" name="notes" rows="2" maxlength="500" placeholder="Looking for a store pick, gift idea…"></textarea>
        <p class="small text-secondary mt-3 mb-0"><i class="fa-solid fa-lock me-1"></i>Notes are private. Your target price shows on your profile.</p>
      </div>
      <div class="modal-footer justify-content-between">
        <button type="submit" class="btn btn-link text-danger px-0" form="bbDel-wishlist" data-bb-delete hidden>Remove from wishlist</button>
        <div class="ms-auto d-flex gap-2"><button type="button" class="btn btn-outline-primary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Save</button></div>
      </div>
    </form>
  </div>
</div>
<form id="bbDel-wishlist" method="post" action="<?= $action ?>" data-bb-confirm="Remove this bottle from your wishlist?" hidden><?= $csrf ?><input type="hidden" name="do" value="wishlist_remove"><input type="hidden" name="return" value="<?= $return ?>"><input type="hidden" name="product_id"></form>


<!-- Sighting -->
<div class="modal fade" id="bbModal-sighting" tabindex="-1" aria-labelledby="bbModal-sighting-title" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
    <form class="modal-content" method="post" action="<?= $action ?>" autocomplete="off">
      <div class="modal-header">
        <div>
          <h2 class="modal-title h5" id="bbModal-sighting-title" data-add="Log a store sighting" data-edit="Log a store sighting">Log a store sighting</h2>
          <div class="small text-secondary" data-bb-name></div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <?= $csrf ?><input type="hidden" name="do" value="sighting_save"><input type="hidden" name="return" value="<?= $return ?>">
        <input type="hidden" name="product_id"><input type="hidden" name="id">
        <div class="mb-3" data-bb-picker>
          <label class="form-label" for="bbs-find">Bottle</label>
          <input class="form-control" id="bbs-find" type="search" placeholder="Start typing a bottle name…" aria-describedby="bbs-find-help" role="combobox" aria-expanded="false" aria-controls="bbs-results" aria-autocomplete="list">
          <div class="list-group bb-picker-results mt-1" id="bbs-results" role="listbox"></div>
          <div class="form-text" id="bbs-find-help" data-bb-picked>Pick from the catalog.</div>
        </div>
        <div class="row g-2">
          <div class="col-6"><label class="form-label" for="bbs-price">Shelf price</label>
            <div class="input-group"><span class="input-group-text">$</span><input class="form-control" id="bbs-price" name="price" inputmode="decimal" required placeholder="39.99"></div></div>
          <div class="col-6"><label class="form-label" for="bbs-date">Seen on</label><input class="form-control" id="bbs-date" type="date" name="seen_on" max="<?= $today ?>" value="<?= $today ?>"></div>
          <div class="col-12"><label class="form-label" for="bbs-store">Store</label>
            <input class="form-control" id="bbs-store" name="store_name" list="bbStoreList" maxlength="120" required placeholder="Pick a store or type a new one">
            <input type="hidden" name="store_id">
            <datalist id="bbStoreList"><?php foreach ($stores as $st) { ?><option value="<?= bb_h(bb_store_label($st)) ?>" data-id="<?= (int)$st->id ?>"></option><?php } ?></datalist>
          </div>
          <div class="col-8" data-bb-newstore><label class="form-label" for="bbs-city">City</label><input class="form-control" id="bbs-city" name="store_city" maxlength="80"></div>
          <div class="col-4" data-bb-newstore><label class="form-label" for="bbs-state">State</label>
            <select class="form-select" id="bbs-state" name="store_state"><option value="">—</option><?php foreach (BB_STATES as $stt) { ?><option><?= $stt ?></option><?php } ?></select></div>
          <div class="col-12"><label class="form-label" for="bbs-notes">Notes</label><input class="form-control" id="bbs-notes" name="notes" maxlength="300" placeholder="Store pick, limit 1 per customer, last bottle…"></div>
        </div>
        <p class="small text-secondary mt-3 mb-0"><i class="fa-solid fa-eye me-1"></i>Sightings are shared with all members. Your username shows only if your profile is public.</p>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-outline-primary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Save sighting</button></div>
    </form>
  </div>
</div>
<form id="bbDel-sighting" hidden></form>
<script nonce="<?= bb_h($userspice_nonce ?? '') ?>">
(function () {
  var LOOKUP = <?= json_encode($us_url_root . 'bourbon/lookup.php') ?>;
  function setVal(form, name, value) {
    var els = form.querySelectorAll('[name="' + name + '"]');
    els.forEach(function (el) {
      if (el.type === 'radio' || el.type === 'checkbox') {
        var a = String(el.value), b = value === null || value === undefined ? '' : String(value);
        el.checked = a === b || (a !== '' && b !== '' && !isNaN(a) && !isNaN(b) && parseFloat(a) === parseFloat(b));
      }
      else el.value = value === null || value === undefined ? '' : value;
    });
  }
  function refresh(modal) {
    var st = modal.querySelector('[name="status"]:checked');
    var fill = modal.querySelector('[data-bb-fill]');
    if (fill) fill.hidden = !st || st.value !== 'open';
    var range = modal.querySelector('[name="fill_level"]'), out = modal.querySelector('[data-bb-fill-out]');
    if (range && out) out.textContent = range.value + '%';
    var store = modal.querySelector('[name="store_name"][list]');
    if (store) {
      var opt = Array.prototype.find.call(document.querySelectorAll('#bbStoreList option'), function (o) { return o.value === store.value; });
      modal.querySelector('[name="store_id"]').value = opt ? opt.getAttribute('data-id') : '';
      modal.querySelectorAll('[data-bb-newstore]').forEach(function (el) { el.hidden = !!opt || store.value.trim() === ''; });
      modal.querySelector('[name="store_city"]').required = !opt && store.value.trim() !== '';
      modal.querySelector('[name="store_state"]').required = !opt && store.value.trim() !== '';
    }
    var r = modal.querySelector('[name="rating"]:checked'), ro = modal.querySelector('[data-bb-rate-out]');
    if (ro) ro.textContent = r ? r.value + ' of 5' : 'Not rated';
    modal.querySelectorAll('[data-bb-rate] label').forEach(function (l) {
      var inp = document.getElementById(l.htmlFor);
      l.classList.toggle('on', !!r && parseFloat(inp.value) <= parseFloat(r.value));
    });
  }
  document.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-bb-open]');
    if (!btn) return;
    var kind = btn.getAttribute('data-bb-open'), data = JSON.parse(btn.getAttribute('data-bb'));
    var modal = document.getElementById('bbModal-' + kind), form = modal.querySelector('form'), del = document.getElementById('bbDel-' + kind);
    form.reset();
    var item = data.item, editing = !!item && (kind !== 'wishlist' || data.onWishlist !== false);
    var title = modal.querySelector('.modal-title');
    title.textContent = title.getAttribute(editing ? 'data-edit' : 'data-add');
    modal.querySelector('[data-bb-name]').textContent = data.product ? data.product.name : '';
    setVal(form, 'product_id', data.product ? data.product.id : '');
    var picker = modal.querySelector('[data-bb-picker]');
    if (picker) { picker.hidden = !!data.product; picker.querySelector('#bbs-results').innerHTML = ''; picker.querySelector('[data-bb-picked]').textContent = 'Pick from the catalog.'; }
    setVal(form, 'id', item && item.id ? item.id : '');
    if (item) Object.keys(item).forEach(function (k) { if (k !== 'id' && k !== 'product_id') setVal(form, k, item[k]); });
    if (kind === 'collection' && item && item.fill_level === null) setVal(form, 'fill_level', 100);
    if (kind === 'sighting' && data.store) setVal(form, 'store_name', data.store);
    var wf = modal.querySelector('[data-bb-wishflag]'); if (wf) wf.hidden = !data.onWishlist || editing;
    var delField = del.querySelector('[name="' + (kind === 'wishlist' ? 'product_id' : 'id') + '"]');
    if (delField) delField.value = kind === 'wishlist' ? data.product.id : (item ? item.id : '');
    var delBtn = modal.querySelector('[data-bb-delete]'); if (delBtn) delBtn.hidden = !editing;
    refresh(modal);
    bootstrap.Modal.getOrCreateInstance(modal).show();
  });
  document.addEventListener('change', function (e) { var m = e.target.closest('.modal'); if (m) refresh(m); });
  document.addEventListener('input', function (e) { var m = e.target.closest('.modal'); if (m) refresh(m); });
  document.addEventListener('click', function (e) {
    var c = e.target.closest('[data-bb-rate-clear]');
    if (c) { c.closest('form').querySelectorAll('[name="rating"]').forEach(function (r) { r.checked = false; }); refresh(c.closest('.modal')); }
  });
  // Bottle picker for sightings logged without a bottle page.
  var find = document.getElementById('bbs-find'), results = document.getElementById('bbs-results'), timer;
  if (find) {
    find.addEventListener('input', function () {
      clearTimeout(timer);
      var q = find.value.trim();
      if (q.length < 2) { results.innerHTML = ''; find.setAttribute('aria-expanded', 'false'); return; }
      timer = setTimeout(function () {
        fetch(LOOKUP + '?q=' + encodeURIComponent(q), { credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (list) {
          results.innerHTML = '';
          list.forEach(function (p) {
            var b = document.createElement('button');
            b.type = 'button'; b.className = 'list-group-item list-group-item-action'; b.setAttribute('role', 'option');
            b.innerHTML = '<span class="fw-semibold"></span><span class="d-block small text-secondary"></span>';
            b.firstChild.textContent = p.name; b.lastChild.textContent = p.distillery;
            b.addEventListener('click', function () {
              var form = find.closest('form');
              form.querySelector('[name="product_id"]').value = p.id;
              find.value = p.name; results.innerHTML = ''; find.setAttribute('aria-expanded', 'false');
              form.querySelector('[data-bb-picked]').textContent = 'Selected: ' + p.name + ' (' + p.distillery + ')';
            });
            results.appendChild(b);
          });
          find.setAttribute('aria-expanded', list.length ? 'true' : 'false');
          if (!list.length) results.innerHTML = '<div class="list-group-item small text-secondary">No bottles match. Try fewer words.</div>';
        });
      }, 180);
    });
    find.closest('form').addEventListener('submit', function (e) {
      if (!this.querySelector('[name="product_id"]').value) { e.preventDefault(); find.focus(); this.querySelector('[data-bb-picked]').textContent = 'Please pick a bottle from the list.'; }
    });
  }
  document.querySelectorAll('form[data-bb-confirm]').forEach(function (f) {
    f.addEventListener('submit', function (e) { if (!confirm(f.getAttribute('data-bb-confirm'))) e.preventDefault(); });
  });
})();
</script>
<?php
  return ob_get_clean();
}
