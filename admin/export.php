<?php
/*
 * Download every log entry as a CSV file (opens in Excel / LibreOffice).
 * Useful as a backup and for CEU / PDU renewal documentation.
 */
require __DIR__ . '/../inc/bootstrap.php';
require_login();

$rows = db()->query('SELECT g.title, g.unit, e.entry_date, e.units, e.description
                     FROM entries e JOIN goals g ON g.id = e.goal_id
                     ORDER BY g.title, e.entry_date, e.id')->fetchAll();

/** Stop spreadsheet programs from treating text as a formula. */
function csv_safe(string $s): string
{
    return preg_match('/^[=+\-@\t\r]/', $s) ? "'" . $s : $s;
}

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="goal-log-' . today() . '.csv"');
$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF"); // UTF-8 marker so Excel shows accents correctly
fputcsv($out, ['Goal', 'Date', 'Units', 'Unit', 'Description']);
foreach ($rows as $r) {
    fputcsv($out, [csv_safe($r['title']), $r['entry_date'], (string) (float) $r['units'], csv_safe($r['unit']), csv_safe($r['description'])]);
}
fclose($out);
