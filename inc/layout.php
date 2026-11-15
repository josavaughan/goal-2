<?php
/*
 * Page header/footer shared by all pages.
 */

function render_header(string $pageTitle, bool $admin = false): void
{
    global $CONFIG;
    $site = $CONFIG['site_title'] ?? 'Goal Tracker';
    $user = current_user();
    ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle === $site ? $site : "$pageTitle · $site") ?></title>
<script>
  // Apply saved theme / text size before the page paints (per-browser preference).
  try {
    var t = localStorage.getItem('gt-theme'); if (t) document.documentElement.setAttribute('data-theme', t);
    var s = localStorage.getItem('gt-size');  if (s) document.documentElement.setAttribute('data-size', s);
  } catch (err) {}
</script>
<link rel="stylesheet" href="<?= e(url('assets/style.css')) ?>?v=1">
</head>
<body>
<a class="skip" href="#main">Skip to content</a>
<header class="site-header">
  <div class="wrap header-row">
    <a class="brand" href="<?= e(url('index.php')) ?>"><?= e($site) ?></a>
    <nav class="nav" aria-label="Main">
      <?php if ($user): ?>
        <a href="<?= e(url('index.php')) ?>">Public chart</a>
        <a href="<?= e(url('admin/index.php')) ?>">Manage goals</a>
        <a href="<?= e(url('admin/account.php')) ?>">Account</a>
        <form method="post" action="<?= e(url('logout.php')) ?>" class="inline">
          <?= csrf_field() ?><button type="submit" class="linklike">Log out</button>
        </form>
      <?php else: ?>
        <a href="<?= e(url('login.php')) ?>">Log in</a>
      <?php endif; ?>
      <button type="button" class="tool" id="size-toggle" aria-pressed="false" title="Larger text">A+</button>
      <button type="button" class="tool" id="theme-toggle" title="Switch light / dark">Dark</button>
    </nav>
  </div>
</header>
<main id="main" class="wrap">
<?php foreach (flash() as $f): ?>
  <div class="flash flash-<?= e($f['type']) ?>" role="status"><?= e($f['msg']) ?></div>
<?php endforeach;
}

function render_footer(): void
{
    ?>
</main>
<footer class="site-footer wrap">
  <?php if ($updated = last_updated()): ?><p>Last updated <?= e(nice_date($updated)) ?></p><?php endif; ?>
</footer>
<script>
(function () {
  var root = document.documentElement;
  function store(k, v) { try { v ? localStorage.setItem(k, v) : localStorage.removeItem(k); } catch (err) {} }
  function isDark() {
    var t = root.getAttribute('data-theme');
    return t ? t === 'dark' : window.matchMedia('(prefers-color-scheme: dark)').matches;
  }
  var themeBtn = document.getElementById('theme-toggle');
  var sizeBtn = document.getElementById('size-toggle');
  function sync() {
    themeBtn.textContent = isDark() ? 'Light' : 'Dark';
    var big = root.getAttribute('data-size') === 'large';
    sizeBtn.setAttribute('aria-pressed', big ? 'true' : 'false');
    sizeBtn.textContent = big ? 'A−' : 'A+';
    sizeBtn.title = big ? 'Normal text' : 'Larger text';
  }
  themeBtn.addEventListener('click', function () {
    var next = isDark() ? 'light' : 'dark';
    root.setAttribute('data-theme', next); store('gt-theme', next); sync();
  });
  sizeBtn.addEventListener('click', function () {
    var big = root.getAttribute('data-size') === 'large';
    if (big) { root.removeAttribute('data-size'); store('gt-size', null); }
    else { root.setAttribute('data-size', 'large'); store('gt-size', 'large'); }
    sync();
  });
  sync();
  // Confirm before destructive actions.
  document.addEventListener('submit', function (ev) {
    var msg = ev.target.getAttribute('data-confirm');
    if (msg && !window.confirm(msg)) ev.preventDefault();
  });
})();
</script>
</body>
</html>
<?php
}
