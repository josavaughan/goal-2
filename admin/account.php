<?php
/*
 * Change your password.
 */
require __DIR__ . '/../inc/bootstrap.php';
require_login();

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $st = db()->prepare('SELECT * FROM users WHERE id = ?');
    $st->execute([current_user()['id']]);
    $user = $st->fetch();
    $current = (string) ($_POST['current'] ?? '');
    $new = (string) ($_POST['new'] ?? '');
    if (!$user || !password_verify($current, $user['password_hash'])) {
        $errors[] = 'Current password is incorrect.';
    }
    if (strlen($new) < 10) {
        $errors[] = 'New password must be at least 10 characters.';
    }
    if ($new !== (string) ($_POST['confirm'] ?? '')) {
        $errors[] = 'The new passwords do not match.';
    }
    if (!$errors) {
        db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
            ->execute([password_hash($new, PASSWORD_DEFAULT), $user['id']]);
        session_regenerate_id(true);
        flash('Password changed.');
        redirect('admin/account.php');
    }
}

render_header('Account', true);
?>
<div class="narrow">
  <h1>Account</h1>
  <p>Logged in as <strong><?= e(current_user()['username']) ?></strong>.</p>
  <?php foreach ($errors as $err): ?><div class="flash flash-error" role="alert"><?= e($err) ?></div><?php endforeach; ?>
  <form method="post" class="card form">
    <?= csrf_field() ?>
    <h2>Change password</h2>
    <label>Current password <input type="password" name="current" required autocomplete="current-password"></label>
    <label>New password (at least 10 characters) <input type="password" name="new" required minlength="10" autocomplete="new-password"></label>
    <label>Confirm new password <input type="password" name="confirm" required minlength="10" autocomplete="new-password"></label>
    <button class="btn" type="submit">Change password</button>
  </form>
</div>
<?php render_footer();
