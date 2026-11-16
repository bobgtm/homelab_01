<?php
// Creates or updates the app's own MySQL account, so the app never needs the server admin login.
// Runs as a short-lived Kubernetes Job on each deploy (k8s/app/db-setup-job.yaml); idempotent.
//   DB_HOST, DB_NAME          the server and the one database the app may use
//   ADMIN_USER, ADMIN_PASSWORD the server admin, used only here
//   APP_USER, APP_PASSWORD     the account the app logs in with
//   DB_SSL_CA                  CA bundle path: connect over TLS and make the app account require it
foreach (['DB_HOST', 'DB_NAME', 'ADMIN_USER', 'ADMIN_PASSWORD', 'APP_USER', 'APP_PASSWORD'] as $k) {
  if ((string)getenv($k) === '') { fwrite(STDERR, "db-setup: $k is not set\n"); exit(1); }
}

$opts = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION];
$tls = (string)getenv('DB_SSL_CA') !== '';
if ($tls) {
  $opts[defined('Pdo\Mysql::ATTR_SSL_CA') ? \Pdo\Mysql::ATTR_SSL_CA : PDO::MYSQL_ATTR_SSL_CA] = getenv('DB_SSL_CA');
  $opts[defined('Pdo\Mysql::ATTR_SSL_VERIFY_SERVER_CERT') ? \Pdo\Mysql::ATTR_SSL_VERIFY_SERVER_CERT : PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = true;
}

try {
  $pdo = new PDO('mysql:host=' . getenv('DB_HOST') . ';charset=utf8mb4', getenv('ADMIN_USER'), getenv('ADMIN_PASSWORD'), $opts);
  $account = $pdo->quote(getenv('APP_USER')) . "@'%'";
  $password = $pdo->quote(getenv('APP_PASSWORD'));
  $database = '`' . str_replace('`', '``', getenv('DB_NAME')) . '`';
  $require = $tls ? ' REQUIRE SSL' : '';

  $pdo->exec("CREATE USER IF NOT EXISTS $account IDENTIFIED BY $password$require");
  $pdo->exec("ALTER USER $account IDENTIFIED BY $password$require");   // keeps the password in sync with the Secret
  $pdo->exec("GRANT ALL PRIVILEGES ON $database.* TO $account");        // this database only; no server-wide rights
} catch (PDOException $e) {
  fwrite(STDERR, 'db-setup: ' . $e->getMessage() . "\n");
  exit(1);
}
echo 'db-setup: ', getenv('APP_USER'), ' can use database ', getenv('DB_NAME'), ' only', $tls ? ', over TLS' : '', "\n";
