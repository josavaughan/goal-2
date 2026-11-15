<?php
/*
 * Create, edit or delete a goal.
 *   goal.php          -> new goal form
 *   goal.php?id=5     -> edit goal 5
 *   POST action=delete -> delete goal and all its log entries
 */
require __DIR__ . '/../inc/bootstrap.php';
require_login();

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$goal = $id ? find_goal($id) : null;
if ($id && !$goal) {
    flash('That goal no longer exists.', 'error');
    redirect('admin/index.php');
}

$errors = [];
$form = $goal ?: [
    'title' => '', 'description' => '', 'unit' => 'hours', 'target' => '',
    'start_date' => today(), 'deadline' => '', 'is_public' => 1, 'sort_order' => 0,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    if (($_POST['action'] ?? '') === 'delete' && $goal) {
        $pdo = db();
        $pdo->beginTransaction();
        $pdo->prepare('DELETE FROM entries WHERE goal_id = ?')->execute([$id]);
        $pdo->prepare('DELETE FROM goals WHERE id = ?')->execute([$id]);
        $pdo->commit();
        flash('Deleted "' . $goal['title'] . '" and its log entries.');
        redirect('admin/index.php');
    }

    $form = [
        'title' => trim((string) ($_POST['title'] ?? '')),
        'description' => trim((string) ($_POST['description'] ?? '')),
        'unit' => trim((string) ($_POST['unit'] ?? '')),
        'target' => trim((string) ($_POST['target'] ?? '')),
        'start_date' => trim((string) ($_POST['start_date'] ?? '')),
        'deadline' => trim((string) ($_POST['deadline'] ?? '')),
        'is_public' => isset($_POST['is_public']) ? 1 : 0,
        'sort_order' => trim((string) ($_POST['sort_order'] ?? '0')),
    ];

    if ($form['title'] === '' || mb_strlen($form['title']) > 200) {
        $errors[] = 'Enter a goal name (up to 200 characters).';
    }
    if ($form['unit'] === '' || mb_strlen($form['unit']) > 50) {
        $errors[] = 'Enter a unit, such as hours, CEUs, PDUs, courses or chapters.';
    }
    if (!is_numeric($form['target']) || (float) $form['target'] <= 0 || (float) $form['target'] > 9999999999) {
        $errors[] = 'Target must be a number greater than zero.';
    }
    if ($form['start_date'] !== '' && !valid_date($form['start_date'])) {
        $errors[] = 'Start date is not a valid date.';
    }
    if ($form['deadline'] !== '' && !valid_date($form['deadline'])) {
        $errors[] = 'Deadline is not a valid date.';
    }
    if ($form['start_date'] !== '' && $form['deadline'] !== '' && $form['deadline'] <= $form['start_date']) {
        $errors[] = 'Deadline must be after the start date.';
    }
    if (!preg_match('/^-?\d{1,6}$/', $form['sort_order'])) {
        $errors[] = 'Display order must be a whole number.';
    }

    if (!$errors) {
        $vals = [
            $form['title'], $form['description'], $form['unit'], (float) $form['target'],
            $form['start_date'] ?: null, $form['deadline'] ?: null, $form['is_public'], (int) $form['sort_order'], now(),
        ];
        if ($goal) {
            $vals[] = $id;
            db()->prepare('UPDATE goals SET title=?, description=?, unit=?, target=?, start_date=?, deadline=?, is_public=?, sort_order=?, updated_at=? WHERE id=?')
                ->execute($vals);
            flash('Saved changes to "' . $form['title'] . '".');
            redirect('admin/index.php');
        }
        $vals[] = now();
        db()->prepare('INSERT INTO goals (title, description, unit, target, start_date, deadline, is_public, sort_order, updated_at, created_at) VALUES (?,?,?,?,?,?,?,?,?,?)')
            ->execute($vals);
        $newId = (int) db()->lastInsertId();
        flash('Created "' . $form['title'] . '". Now log your first progress entry.');
        redirect('admin/log.php?goal=' . $newId);
    }
}

$targetValue = $form['target'] === '' ? '' : (is_numeric($form['target']) ? rtrim(rtrim((string) $form['target'], '0'), '.') : $form['target']);

render_header($goal ? 'Edit goal' : 'New goal', true);
?>
<div class="narrow">
  <p><a href="<?= e(url('admin/index.php')) ?>">← All goals</a></p>
  <h1><?= $goal ? 'Edit goal' : 'New goal' ?></h1>

  <?php foreach ($errors as $err): ?><div class="flash flash-error" role="alert"><?= e($err) ?></div><?php endforeach; ?>

  <form method="post" class="card form">
    <?= csrf_field() ?>
    <?php if ($goal): ?><input type="hidden" name="id" value="<?= (int) $id ?>"><?php endif; ?>

    <label>Goal name
      <input name="title" required maxlength="200" value="<?= e($form['title']) ?>" placeholder="e.g. PMP renewal PDUs">
    </label>

    <label>Description <span class="muted">(optional, shown on the public page)</span>
      <textarea name="description" rows="3"><?= e($form['description']) ?></textarea>
    </label>

    <div class="row-2">
      <label>Target amount
        <input name="target" required inputmode="decimal" value="<?= e($targetValue) ?>" placeholder="e.g. 60">
      </label>
      <label>Unit
        <input name="unit" required maxlength="50" list="unit-list" value="<?= e($form['unit']) ?>">
        <datalist id="unit-list">
          <option value="hours"><option value="CEUs"><option value="PDUs"><option value="CEs">
          <option value="credit hours"><option value="courses"><option value="chapters"><option value="modules"><option value="books">
        </datalist>
      </label>
    </div>

    <div class="row-2">
      <label>Start date <span class="muted">(optional)</span>
        <input type="date" name="start_date" value="<?= e($form['start_date']) ?>">
      </label>
      <label>Deadline <span class="muted">(optional)</span>
        <input type="date" name="deadline" value="<?= e($form['deadline']) ?>">
      </label>
    </div>

    <div class="row-2">
      <label>Display order <span class="muted">(lower shows first)</span>
        <input name="sort_order" inputmode="numeric" value="<?= e($form['sort_order']) ?>">
      </label>
      <label class="check">
        <input type="checkbox" name="is_public" value="1" <?= $form['is_public'] ? 'checked' : '' ?>>
        Show on the public chart
      </label>
    </div>

    <div class="form-actions">
      <button class="btn" type="submit"><?= $goal ? 'Save changes' : 'Create goal' ?></button>
      <a class="btn btn-secondary" href="<?= e(url('admin/index.php')) ?>">Cancel</a>
    </div>
  </form>

  <?php if ($goal): ?>
    <form method="post" class="card danger-zone" data-confirm="Delete this goal and ALL of its log entries? This cannot be undone.">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="<?= (int) $id ?>">
      <input type="hidden" name="action" value="delete">
      <h2>Delete goal</h2>
      <p>Removes this goal and all of its log entries permanently.</p>
      <button class="btn btn-danger" type="submit">Delete goal</button>
    </form>
  <?php endif; ?>
</div>
<?php render_footer();
