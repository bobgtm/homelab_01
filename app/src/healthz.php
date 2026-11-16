<?php
// Kubernetes health check: 200 when PHP is up and the database answers, else 503.
// Deliberately standalone (no UserSpice bootstrap) so it stays cheap and side-effect free.
// Uses TLS (verifying the server certificate) when DB_SSL_CA is set, like users/init.php.
header('Content-Type: text/plain');
header('Cache-Control: no-store');
mysqli_report(MYSQLI_REPORT_OFF);
$db = @mysqli_init();
$flags = 0;
if ($db) {
  @$db->options(MYSQLI_OPT_CONNECT_TIMEOUT, 2);
  if (getenv('DB_SSL_CA')) {
    @$db->ssl_set(null, null, getenv('DB_SSL_CA'), null, null);
    @$db->options(MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, true);
    $flags = MYSQLI_CLIENT_SSL;
  }
}
$ok = $db && @$db->real_connect(getenv('DB_HOST') ?: 'mysql', getenv('DB_USER'), getenv('DB_PASSWORD'), getenv('DB_NAME'), 3306, null, $flags);
if ($ok && @$db->query('SELECT 1')) { echo "ok\n"; exit; }
http_response_code(503);
echo "database unavailable\n";
