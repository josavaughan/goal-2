<?php
/*
 * Shared setup for every page: config, database, session, security helpers.
 */
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));

// ---------------------------------------------------------------- config
$configFile = APP_ROOT . '/config.php';
if (!is_file($configFile)) {
    http_response_code(500);
    echo '<!doctype html><meta charset="utf-8"><title>Setup needed</title>'
        . '<body style="font:18px/1.5 system-ui,sans-serif;max-width:40em;margin:3em auto;padding:0 16px">'
        . '<h1>Setup needed</h1><p>Copy <code>config.sample.php</code> to <code>config.php</code>, '
        . 'edit it, then open <code>install.php</code> in your browser.</p></body>';
    exit;
}
$CONFIG = require $configFile;
date_default_timezone_set($CONFIG['timezone'] ?? 'America/Denver');

// ---------------------------------------------------------------- base URL
// Lets the app live in the web root or in any sub-folder (e.g. /goals).
(function () {
    $dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
    if (basename(dirname($_SERVER['SCRIPT_FILENAME'] ?? '')) === 'admin') {
        $dir = dirname($dir);
    }
    define('BASE_URL', rtrim($dir, '/'));
})();

function url(string $path = ''): string
{
    return BASE_URL . '/' . ltrim($path, '/');
}

// ---------------------------------------------------------------- database
function db(): PDO
{
    static $pdo = null;
    global $CONFIG;
    if ($pdo) {
        return $pdo;
    }
    $c = $CONFIG['db'];
    $opts = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    if ($c['driver'] === 'mysql') {
        $m = $c['mysql'];
        $pdo = new PDO("mysql:host={$m['host']};dbname={$m['name']};charset=utf8mb4", $m['user'], $m['pass'], $opts);
    } else {
        $pdo = new PDO('sqlite:' . $c['sqlite_path'], null, null, $opts);
        $pdo->exec('PRAGMA journal_mode = WAL');
    }
    return $pdo;
}

function db_driver(): string
{
    global $CONFIG;
    return $CONFIG['db']['driver'] === 'mysql' ? 'mysql' : 'sqlite';
}

function is_installed(): bool
{
    try {
        return (int) db()->query('SELECT COUNT(*) FROM users')->fetchColumn() > 0;
    } catch (PDOException $e) {
        return false;
    }
}

function now(): string
{
    return date('Y-m-d H:i:s');
}

function today(): string
{
    return date('Y-m-d');
}

// ---------------------------------------------------------------- session
$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
session_name('goaltracker');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => BASE_URL === '' ? '/' : BASE_URL . '/',
    'secure' => $https,
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

// Basic security headers.
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');

// ---------------------------------------------------------------- helpers
function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $path): void
{
    header('Location: ' . url($path));
    exit;
}

function flash(?string $msg = null, string $type = 'ok')
{
    if ($msg !== null) {
        $_SESSION['flash'][] = ['msg' => $msg, 'type' => $type];
        return null;
    }
    $all = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $all;
}

/** Format a number without pointless trailing zeros: 12.50 -> 12.5, 3.00 -> 3 */
function num($n): string
{
    $s = number_format((float) $n, 2, '.', ',');
    return rtrim(rtrim($s, '0'), '.');
}

function valid_date(string $d): bool
{
    $dt = DateTime::createFromFormat('Y-m-d', $d);
    return $dt && $dt->format('Y-m-d') === $d;
}

function nice_date(?string $d): string
{
    if (!$d) {
        return '';
    }
    $dt = DateTime::createFromFormat('Y-m-d', substr($d, 0, 10));
    return $dt ? $dt->format('M j, Y') : $d;
}

// ---------------------------------------------------------------- CSRF
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function csrf_check(): void
{
    if (!hash_equals($_SESSION['csrf'] ?? '', (string) ($_POST['csrf'] ?? ''))) {
        http_response_code(400);
        exit('Your form expired. Go back, reload the page and try again.');
    }
}

// ---------------------------------------------------------------- auth
function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function require_login(): void
{
    if (!current_user()) {
        redirect('login.php');
    }
}

// ---------------------------------------------------------------- goals
/**
 * All goals with their progress totals and percent complete.
 * $publicOnly = true hides goals marked as private.
 */
function load_goals(bool $publicOnly): array
{
    $sql = 'SELECT g.*, (SELECT COALESCE(SUM(e.units), 0) FROM entries e WHERE e.goal_id = g.id) AS done,
                   (SELECT COUNT(*) FROM entries e WHERE e.goal_id = g.id) AS entry_count,
                   (SELECT MAX(e.entry_date) FROM entries e WHERE e.goal_id = g.id) AS last_entry
            FROM goals g'
        . ($publicOnly ? ' WHERE g.is_public = 1' : '')
        . ' ORDER BY g.sort_order ASC, CASE WHEN g.deadline IS NULL THEN 1 ELSE 0 END, g.deadline ASC, g.title ASC';
    $goals = db()->query($sql)->fetchAll();
    foreach ($goals as &$g) {
        $g += goal_progress($g);
    }
    return $goals;
}

function goal_progress(array $g): array
{
    $target = (float) $g['target'];
    $done = (float) $g['done'];
    $pct = $target > 0 ? max(0, $done / $target * 100) : 0;
    return [
        'pct' => $pct,
        'pct_bar' => min(100, $pct),
    ];
}

/** Date of the most recent change to any goal or log entry. */
function last_updated(): ?string
{
    try {
        $a = db()->query('SELECT MAX(updated_at) FROM goals')->fetchColumn();
        $b = db()->query('SELECT MAX(updated_at) FROM entries')->fetchColumn();
        $max = max((string) $a, (string) $b);
        return $max !== '' ? substr($max, 0, 10) : null;
    } catch (PDOException $e) {
        return null;
    }
}

function find_goal(int $id): ?array
{
    $st = db()->prepare('SELECT g.*, (SELECT COALESCE(SUM(e.units), 0) FROM entries e WHERE e.goal_id = g.id) AS done
                         FROM goals g WHERE g.id = ?');
    $st->execute([$id]);
    $g = $st->fetch();
    return $g ? $g + goal_progress($g) : null;
}

require __DIR__ . '/layout.php';
