<?php
// Barrel Book front-end template. Light/dark: the visitor's saved choice, else their OS setting.
$html_data_bs_theme = 'light';
require_once($abs_us_root.$us_url_root.'users/includes/template/header1_must_include.php');
$bb_asset = $us_url_root.'usersc/templates/barrel/assets/';
$bb_css_ver = @filemtime(__DIR__.'/assets/css/barrel.css') ?: 1;
?>
<meta name="viewport" content="width=device-width, initial-scale=1">
<script nonce="<?=htmlspecialchars($userspice_nonce ?? '')?>">
(function () { // runs before first paint so there is no flash of the wrong theme
  var m = null;
  try { m = localStorage.getItem('bbTheme'); } catch (e) {}
  if (m !== 'light' && m !== 'dark') m = (window.matchMedia && matchMedia('(prefers-color-scheme: dark)').matches) ? 'dark' : 'light';
  document.documentElement.setAttribute('data-bs-theme', m);
})();
</script>
<meta name="theme-color" content="#f7f7f5" media="(prefers-color-scheme: light)">
<meta name="theme-color" content="#121315" media="(prefers-color-scheme: dark)">
<link rel="stylesheet" href="<?=$us_url_root?>users/css/bootstrap.min.css">
<link rel="stylesheet" href="<?=$us_url_root?>users/css/datatables.css">
<link rel="stylesheet" href="<?=$us_url_root?>users/fonts/css/fontawesome.min.css">
<link rel="stylesheet" href="<?=$us_url_root?>users/fonts/css/brands.min.css">
<link rel="stylesheet" href="<?=$us_url_root?>users/fonts/css/solid.min.css">
<link rel="stylesheet" href="<?=$us_url_root?>users/fonts/css/regular.min.css">
<link rel="stylesheet" href="<?=$us_url_root?>users/fonts/css/v4-shims.min.css">
<?php require_once $abs_us_root.$us_url_root."users/js/jquery.php"; ?>
<script nonce="<?=htmlspecialchars($userspice_nonce ?? '')?>" src="<?=$us_url_root?>users/js/bootstrap.bundle.min.js"></script>
<link rel="stylesheet" href="<?=$bb_asset?>css/barrel.css?v=<?=$bb_css_ver?>">
</head>
<body class="d-flex flex-column min-vh-100 bb-body">
<?php require_once($abs_us_root.$us_url_root.'users/includes/template/header3_must_include.php'); ?>
