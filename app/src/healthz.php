<?php
// Kubernetes health check: 200 when PHP is up and the database answers, else 503.
// Deliberately standalone (no UserSpice bootstrap) so it stays cheap and side-effect free.
header('Content-Type: text/plain');
header('Cache-Control: no-store');
mysqli_report(MYSQLI_REPORT_OFF);
$db = @mysqli_init();
if ($db) { @$db->options(MYSQLI_OPT_CONNECT_TIMEOUT, 2); }
$ok = $db && @$db->real_connect(getenv('DB_HOST') ?: 'mysql', getenv('DB_USER'), getenv('DB_PASSWORD'), getenv('DB_NAME'));
if ($ok && @$db->query('SELECT 1')) { echo "ok\n"; exit; }
http_response_code(503);
echo "database unavailable\n";
