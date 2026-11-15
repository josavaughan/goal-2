<?php
/*
 * Public front page: bar chart of percent complete for every public goal.
 * No login required. Nothing on this page can change data.
 */
require __DIR__ . '/inc/bootstrap.php';

if (!is_installed()) {
    redirect('install.php');
}

$goals = load_goals(true);
$complete = count(array_filter($goals, fn($g) => $g['pct'] >= 100));

render_header($CONFIG['site_title']);
?>
<section class="intro">
  <h1><?= e($CONFIG['site_title']) ?></h1>
  <?php if (!empty($CONFIG['site_subtitle'])): ?>
    <p class="lede"><?= e($CONFIG['site_subtitle']) ?></p>
  <?php endif; ?>
</section>

<?php if (!$goals): ?>
  <div class="card empty">
    <p>No goals to show yet.</p>
    <?php if (current_user()): ?><p><a class="btn" href="<?= e(url('admin/goal.php')) ?>">Add your first goal</a></p><?php endif; ?>
  </div>
<?php else: ?>

<div class="stats">
  <div class="stat"><span class="stat-num"><?= count($goals) ?></span><span class="stat-label">Goals</span></div>
  <div class="stat"><span class="stat-num"><?= $complete ?></span><span class="stat-label">Complete</span></div>
  <div class="stat"><span class="stat-num"><?= count($goals) - $complete ?></span><span class="stat-label">Underway</span></div>
</div>

<section class="card chart-card" aria-labelledby="chart-title">
  <h2 id="chart-title">Percent complete</h2>
  <div class="chart" role="list">
    <?php foreach ($goals as $g):
        $label = num($g['done']) . ' of ' . num($g['target']) . ' ' . $g['unit'];
        ?>
      <div class="bar-row" role="listitem">
        <div class="bar-label">
          <span class="bar-title"><?= e($g['title']) ?></span>
          <span class="bar-sub"><?= e($label) ?><?php if ($g['deadline']): ?> · due <?= e(nice_date($g['deadline'])) ?><?php endif; ?></span>
        </div>
        <div class="bar-track" title="<?= e($g['title'] . ': ' . round($g['pct']) . '% — ' . $label) ?>">
          <div class="bar-fill" style="width: <?= e(round($g['pct_bar'], 2)) ?>%"></div>
        </div>
        <div class="bar-value">
          <strong><?= e(round($g['pct'])) ?>%</strong>
        </div>
      </div>
    <?php endforeach; ?>
    <div class="bar-row axis-row" aria-hidden="true">
      <span></span>
      <div class="axis"><span style="left:0%">0%</span><span style="left:25%">25%</span><span style="left:50%">50%</span><span style="left:75%">75%</span><span style="left:100%">100%</span></div>
      <span></span>
    </div>
  </div>
</section>

<section class="card" aria-labelledby="table-title">
  <h2 id="table-title">Details</h2>
  <div class="table-scroll">
    <table class="data">
      <thead>
        <tr><th scope="col">Goal</th><th scope="col" class="num">Progress</th><th scope="col" class="num">Percent</th><th scope="col">Started</th><th scope="col">Deadline</th></tr>
      </thead>
      <tbody>
      <?php foreach ($goals as $g): ?>
        <tr>
          <th scope="row">
            <?= e($g['title']) ?>
            <?php if ($g['description']): ?><div class="muted small"><?= nl2br(e($g['description'])) ?></div><?php endif; ?>
          </th>
          <td class="num"><?= e(num($g['done'])) ?> / <?= e(num($g['target'])) ?> <?= e($g['unit']) ?></td>
          <td class="num"><?= e(round($g['pct'])) ?>%</td>
          <td><?= $g['start_date'] ? e(nice_date($g['start_date'])) : '<span class="muted">—</span>' ?></td>
          <td><?= $g['deadline'] ? e(nice_date($g['deadline'])) : '<span class="muted">—</span>' ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>
<?php endif; ?>
<?php render_footer();
