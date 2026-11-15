<?php
require __DIR__ . '/inc/bootstrap.php';

if (!is_installed()) {
    redirect('install.php');
}
if (current_user()) {
    redirect('admin/index.php');
}

const MAX_ATTEMPTS = 5;      // failed tries allowed...
const WINDOW_MINUTES = 15;   // ...within this many minutes, per IP address

$ip = substr($_SERVER['REMOTE_ADDR'] ?? 'unknown', 0, 45);
$since = date('Y-m-d H:i:s', time() - WINDOW_MINUTES * 60);
$error = null;

// Clear out old attempts.
db()->prepare('DELETE FROM login_attempts WHERE attempted_at < ?')->execute([$since]);

$count = db()->prepare('SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND attempted_at >= ?');
$count->execute([$ip, $since]);
$locked = (int) $count->fetchColumn() >= MAX_ATTEMPTS;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if ($locked) {
        $error = 'Too many failed attempts. Wait ' . WINDOW_MINUTES . ' minutes and try again.';
    } else {
        $username = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $st = db()->prepare('SELECT * FROM users WHERE username = ?');
        $st->execute([$username]);
        $user = $st->fetch();
        if ($user && password_verify($password, $user['password_hash'])) {
            if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
                db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
                    ->execute([password_hash($password, PASSWORD_DEFAULT), $user['id']]);
            }
            db()->prepare('DELETE FROM login_attempts WHERE ip = ?')->execute([$ip]);
            session_regenerate_id(true);
            $_SESSION['user'] = ['id' => (int) $user['id'], 'username' => $user['username']];
            unset($_SESSION['csrf']);
            redirect('admin/index.php');
        }
        db()->prepare('INSERT INTO login_attempts (ip, attempted_at) VALUES (?, ?)')->execute([$ip, now()]);
        usleep(500000); // slow down guessing
        $error = 'Username or password is incorrect.';
    }
} elseif ($locked) {
    $error = 'Too many failed attempts. Wait ' . WINDOW_MINUTES . ' minutes and try again.';
}

render_header('Log in');
?>
<div class="narrow">
  <h1>Log in</h1>
  <p class="muted">Logging in lets you add goals and record progress. The chart is public and needs no login.</p>
  <?php if ($error): ?><div class="flash flash-error" role="alert"><?= e($error) ?></div><?php endif; ?>
  <form method="post" class="card form">
    <?= csrf_field() ?>
    <label>Username <input name="username" required autocomplete="username" autofocus value="<?= e($_POST['username'] ?? '') ?>"></label>
    <label>Password <input type="password" name="password" required autocomplete="current-password"></label>
    <button class="btn" type="submit">Log in</button>
  </form>
</div>
<?php render_footer();
