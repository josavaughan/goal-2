<?php
/*
 * Admin home: list of all goals with links to edit, log progress, or delete.
 */
require __DIR__ . '/../inc/bootstrap.php';
require_login();

$goals = load_goals(false);

render_header('Manage goals', true);
?>
<div class="page-head">
  <h1>Manage goals</h1>
  <div class="actions">
    <a class="btn" href="<?= e(url('admin/goal.php')) ?>">+ New goal</a>
    <a class="btn btn-secondary" href="<?= e(url('admin/export.php')) ?>">Export log (CSV)</a>
  </div>
</div>

<?php if (!$goals): ?>
  <div class="card empty"><p>You have no goals yet. Start with <a href="<?= e(url('admin/goal.php')) ?>">New goal</a>.</p></div>
<?php else: ?>
  <div class="goal-list">
  <?php foreach ($goals as $g): ?>
    <article class="card goal-item">
      <div class="goal-main">
        <h2 class="goal-title">
          <?= e($g['title']) ?>
          <?php if (!$g['is_public']): ?><span class="tag">Private</span><?php endif; ?>
        </h2>
        <p class="goal-meta">
          <strong><?= e(num($g['done'])) ?></strong> of <?= e(num($g['target'])) ?> <?= e($g['unit']) ?>
          · <strong><?= e(round($g['pct'])) ?>%</strong>
          <?php if ($g['deadline']): ?>· due <?= e(nice_date($g['deadline'])) ?><?php endif; ?>
        </p>
        <div class="mini-track" aria-hidden="true"><div class="bar-fill" style="width: <?= e(round($g['pct_bar'], 2)) ?>%"></div></div>
        <p class="muted small"><?= (int) $g['entry_count'] ?> log entr<?= (int) $g['entry_count'] === 1 ? 'y' : 'ies' ?><?php if ($g['last_entry']): ?>, latest <?= e(nice_date($g['last_entry'])) ?><?php endif; ?></p>
      </div>
      <div class="goal-actions">
        <a class="btn" href="<?= e(url('admin/log.php?goal=' . (int) $g['id'])) ?>">Log progress</a>
        <a class="btn btn-secondary" href="<?= e(url('admin/goal.php?id=' . (int) $g['id'])) ?>">Edit goal</a>
      </div>
    </article>
  <?php endforeach; ?>
  </div>
<?php endif; ?>
<?php render_footer();
