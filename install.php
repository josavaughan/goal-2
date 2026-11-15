<?php
/*
 * One-time installer: creates the database tables and your login.
 * Once an account exists this page refuses to do anything.
 */
require __DIR__ . '/inc/bootstrap.php';

function create_tables(): void
{
    $mysql = db_driver() === 'mysql';
    $id = $mysql ? 'INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    $fk = $mysql ? 'INT UNSIGNED NOT NULL' : 'INTEGER NOT NULL';
    $tail = $mysql ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4' : '';

    $pdo = db();
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id $id,
        username VARCHAR(100) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        created_at VARCHAR(19) NOT NULL
    )$tail");
    $pdo->exec("CREATE TABLE IF NOT EXISTS goals (
        id $id,
        title VARCHAR(200) NOT NULL,
        description TEXT,
        unit VARCHAR(50) NOT NULL,
        target DECIMAL(12,2) NOT NULL,
        start_date DATE NULL,
        deadline DATE NULL,
        is_public TINYINT NOT NULL DEFAULT 1,
        sort_order INT NOT NULL DEFAULT 0,
        created_at VARCHAR(19) NOT NULL,
        updated_at VARCHAR(19) NOT NULL
    )$tail");
    $pdo->exec("CREATE TABLE IF NOT EXISTS entries (
        id $id,
        goal_id $fk,
        entry_date DATE NOT NULL,
        description TEXT NOT NULL,
        units DECIMAL(12,2) NOT NULL,
        created_at VARCHAR(19) NOT NULL,
        updated_at VARCHAR(19) NOT NULL
    )$tail");
    $pdo->exec("CREATE TABLE IF NOT EXISTS login_attempts (
        id $id,
        ip VARCHAR(45) NOT NULL,
        attempted_at VARCHAR(19) NOT NULL
    )$tail");
    try {
        $pdo->exec('CREATE INDEX idx_entries_goal ON entries (goal_id)');
    } catch (PDOException $e) {
        // Index already exists.
    }
}

$errors = [];
$dbError = null;
try {
    create_tables();
} catch (PDOException $e) {
    $dbError = $e->getMessage();
}

$done = !$dbError && is_installed();

if (!$dbError && !$done && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $confirm = (string) ($_POST['confirm'] ?? '');
    if ($username === '' || strlen($username) > 100) {
        $errors[] = 'Enter a username (up to 100 characters).';
    }
    if (strlen($password) < 10) {
        $errors[] = 'Password must be at least 10 characters.';
    }
    if ($password !== $confirm) {
        $errors[] = 'The two passwords do not match.';
    }
    if (!$errors) {
        $st = db()->prepare('INSERT INTO users (username, password_hash, created_at) VALUES (?, ?, ?)');
        $st->execute([$username, password_hash($password, PASSWORD_DEFAULT), now()]);
        flash('Setup complete. Log in to add your goals. For safety, delete install.php from your server.');
        redirect('login.php');
    }
}

render_header('Install');
?>
<div class="narrow">
<h1>Install</h1>
<?php if ($dbError): ?>
  <div class="flash flash-error" role="alert">
    <p><strong>Could not connect to or set up the database.</strong></p>
    <p class="small"><?= e($dbError) ?></p>
    <p>Check the <code>db</code> settings in <code>config.php</code>. For SQLite, make sure the <code>data</code> folder exists and is writable (permissions 755).</p>
  </div>
<?php elseif ($done): ?>
  <div class="card">
    <p>The app is already installed. You can <a href="<?= e(url('login.php')) ?>">log in</a>.</p>
    <p><strong>Delete <code>install.php</code> from your server</strong> — it is no longer needed.</p>
  </div>
<?php else: ?>
  <p>Database tables are ready (<?= e(db_driver()) ?>). Create the account you will use to manage goals.</p>
  <?php foreach ($errors as $err): ?><div class="flash flash-error" role="alert"><?= e($err) ?></div><?php endforeach; ?>
  <form method="post" class="card form">
    <?= csrf_field() ?>
    <label>Username <input name="username" required maxlength="100" autocomplete="username" value="<?= e($_POST['username'] ?? '') ?>"></label>
    <label>Password (at least 10 characters) <input type="password" name="password" required minlength="10" autocomplete="new-password"></label>
    <label>Confirm password <input type="password" name="confirm" required minlength="10" autocomplete="new-password"></label>
    <button class="btn" type="submit">Create account</button>
  </form>
<?php endif; ?>
</div>
<?php render_footer();
