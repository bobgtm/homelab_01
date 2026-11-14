<?php
// Small JSON lookup for the bottle picker in the sighting form: ?q=eagle rare
require_once '../users/init.php';
if (!securePage(Server::get('PHP_SELF'))) { die(); }
require_once __DIR__ . '/inc/catalog.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, max-age=60');
$q = mb_substr(bb_get('q'), 0, 60);
if (mb_strlen($q) < 2) { echo '[]'; exit; }

$where = []; $params = [];
foreach (preg_split('/\s+/', $q) as $w) {
  $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $w) . '%';
  $where[] = '(p.name LIKE ? OR p.brand LIKE ? OR d.name LIKE ?)';
  array_push($params, $like, $like, $like);
}
$rows = $db->query("SELECT p.id, p.name, d.name AS distillery FROM bb_products p JOIN bb_distilleries d ON d.id = p.distillery_id
   WHERE p.status = 'approved' AND " . implode(' AND ', $where) . " ORDER BY (p.name LIKE ?) DESC, p.name LIMIT 12",
   array_merge($params, [str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%']))->results();
echo json_encode(array_map(fn($r) => ['id' => (int)$r->id, 'name' => $r->name, 'distillery' => $r->distillery], $rows));
