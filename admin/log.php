<?php
/*
 * Progress log for one goal: add, edit and delete entries.
 *   log.php?goal=5            -> log for goal 5, with the "add entry" form
 *   log.php?goal=5&edit=12    -> same page, form filled in to edit entry 12
 */
require __DIR__ . '/../inc/bootstrap.php';
require_login();

$goalId = (int) ($_GET['goal'] ?? $_POST['goal'] ?? 0);
$goal = find_goal($goalId);
if (!$goal) {
    flash('That goal no longer exists.', 'error');
    redirect('admin/index.php');
}
$back = 'admin/log.php?goal=' . $goalId;

function find_entry(int $id, int $goalId): ?array
{
    $st = db()->prepare('SELECT * FROM entries WHERE id = ? AND goal_id = ?');
    $st->execute([$id, $goalId]);
    return $st->fetch() ?: null;
}

$errors = [];
$editId = (int) ($_GET['edit'] ?? $_POST['entry_id'] ?? 0);
$editing = $editId ? find_entry($editId, $goalId) : null;
$form = $editing ?: ['entry_date' => today(), 'description' => '', 'units' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? 'save';

    if ($action === 'delete') {
        $entry = find_entry((int) ($_POST['entry_id'] ?? 0), $goalId);
        if ($entry) {
            db()->prepare('DELETE FROM entries WHERE id = ?')->execute([$entry['id']]);
            flash('Deleted the entry from ' . nice_date($entry['entry_date']) . '.');
        }
        redirect($back);
    }

    $form = [
        'entry_date' => trim((string) ($_POST['entry_date'] ?? '')),
        'description' => trim((string) ($_POST['description'] ?? '')),
        'units' => trim((string) ($_POST['units'] ?? '')),
    ];
    if (!valid_date($form['entry_date'])) {
        $errors[] = 'Enter a valid date.';
    }
    if ($form['description'] === '' || mb_strlen($form['description']) > 2000) {
        $errors[] = 'Describe what you did (up to 2000 characters).';
    }
    if (!is_numeric($form['units']) || (float) $form['units'] == 0 || abs((float) $form['units']) > 9999999999) {
        $errors[] = 'Units must be a number other than zero. Use a negative number to correct an earlier entry.';
    }

    if (!$errors) {
        if ($editing) {
            db()->prepare('UPDATE entries SET entry_date=?, description=?, units=?, updated_at=? WHERE id=?')
                ->execute([$form['entry_date'], $form['description'], (float) $form['units'], now(), $editing['id']]);
            flash('Entry updated.');
        } else {
            db()->prepare('INSERT INTO entries (goal_id, entry_date, description, units, created_at, updated_at) VALUES (?,?,?,?,?,?)')
                ->execute([$goalId, $form['entry_date'], $form['description'], (float) $form['units'], now(), now()]);
            $after = find_goal($goalId);
            flash('Logged ' . num($form['units']) . ' ' . $goal['unit'] . '. "' . $goal['title'] . '" is now ' . round($after['pct']) . '% complete.');
        }
        redirect($back);
    }
}

$st = db()->prepare('SELECT * FROM entries WHERE goal_id = ? ORDER BY entry_date DESC, id DESC');
$st->execute([$goalId]);
$entries = $st->fetchAll();
$remaining = max(0, (float) $goal['target'] - (float) $goal['done']);
$unitsValue = $form['units'] === '' ? '' : (is_numeric($form['units']) ? num($form['units']) : $form['units']);
$unitsValue = str_replace(',', '', $unitsValue);

render_header('Log: ' . $goal['title'], true);
?>
<p><a href="<?= e(url('admin/index.php')) ?>">← All goals</a></p>
<div class="page-head">
  <h1><?= e($goal['title']) ?></h1>
  <div class="actions"><a class="btn btn-secondary" href="<?= e(url('admin/goal.php?id=' . $goalId)) ?>">Edit goal</a></div>
</div>

<div class="card summary">
  <p class="summary-line">
    <span class="big"><?= e(round($goal['pct'])) ?>%</span>
    <span><strong><?= e(num($goal['done'])) ?></strong> of <?= e(num($goal['target'])) ?> <?= e($goal['unit']) ?>
      <?php if ($remaining > 0): ?>· <?= e(num($remaining)) ?> to go<?php endif; ?></span>
    <?= status_badge($goal['status']) ?>
  </p>
  <div class="mini-track" aria-hidden="true"><div class="bar-fill status-<?= e($goal['status']) ?>" style="width: <?= e(round($goal['pct_bar'], 2)) ?>%"></div></div>
</div>

<div class="log-layout">
  <section class="card form-card" aria-labelledby="entry-form-title">
    <h2 id="entry-form-title"><?= $editing ? 'Edit entry' : 'Add progress' ?></h2>
    <?php foreach ($errors as $err): ?><div class="flash flash-error" role="alert"><?= e($err) ?></div><?php endforeach; ?>
    <form method="post" class="form" action="<?= e(url($back)) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="goal" value="<?= $goalId ?>">
      <?php if ($editing): ?><input type="hidden" name="entry_id" value="<?= (int) $editing['id'] ?>"><?php endif; ?>
      <label>Date <input type="date" name="entry_date" required value="<?= e($form['entry_date']) ?>"></label>
      <label>Units (<?= e($goal['unit']) ?>)
        <input name="units" required inputmode="decimal" value="<?= e($unitsValue) ?>" placeholder="e.g. 2.5">
      </label>
      <label>Description
        <textarea name="description" rows="3" required maxlength="2000" placeholder="What did you do? e.g. Completed webinar on risk management"><?= e($form['description']) ?></textarea>
      </label>
      <div class="form-actions">
        <button class="btn" type="submit"><?= $editing ? 'Save entry' : 'Add entry' ?></button>
        <?php if ($editing): ?><a class="btn btn-secondary" href="<?= e(url($back)) ?>">Cancel</a><?php endif; ?>
      </div>
    </form>
  </section>

  <section class="card" aria-labelledby="history-title">
    <h2 id="history-title">History <span class="muted">(<?= count($entries) ?>)</span></h2>
    <?php if (!$entries): ?>
      <p class="muted">No entries yet.</p>
    <?php else: ?>
      <div class="table-scroll">
      <table class="data">
        <thead><tr><th scope="col">Date</th><th scope="col">Description</th><th scope="col" class="num">Units</th><th scope="col"><span class="sr-only">Actions</span></th></tr></thead>
        <tbody>
        <?php foreach ($entries as $en): ?>
          <tr<?= $editing && (int) $editing['id'] === (int) $en['id'] ? ' class="is-editing"' : '' ?>>
            <td class="nowrap"><?= e(nice_date($en['entry_date'])) ?></td>
            <td><?= nl2br(e($en['description'])) ?></td>
            <td class="num"><?= e(num($en['units'])) ?></td>
            <td class="row-actions"><div>
              <a href="<?= e(url($back . '&edit=' . (int) $en['id'])) ?>">Edit</a>
              <form method="post" class="inline" action="<?= e(url($back)) ?>" data-confirm="Delete this entry?">
                <?= csrf_field() ?>
                <input type="hidden" name="goal" value="<?= $goalId ?>">
                <input type="hidden" name="entry_id" value="<?= (int) $en['id'] ?>">
                <input type="hidden" name="action" value="delete">
                <button type="submit" class="linklike danger">Delete</button>
              </form>
            </div></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      </div>
    <?php endif; ?>
  </section>
</div>
<?php render_footer();
