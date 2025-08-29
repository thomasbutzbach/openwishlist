<h1>Jobs</h1>

<section>
  <h2>Statistics</h2>
  <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; margin: 1rem 0;">
    <div style="padding: 1rem; border: 1px solid #ddd; border-radius: 4px;">
      <strong>Queued</strong><br>
      <span style="font-size: 2em; color: #007bff;"><?= $stats['queued'] ?></span>
    </div>
    <div style="padding: 1rem; border: 1px solid #ddd; border-radius: 4px;">
      <strong>Processing</strong><br>
      <span style="font-size: 2em; color: #ffc107;"><?= $stats['processing'] ?></span>
    </div>
    <div style="padding: 1rem; border: 1px solid #ddd; border-radius: 4px;">
      <strong>Completed</strong><br>
      <span style="font-size: 2em; color: #28a745;"><?= $stats['completed'] ?></span>
    </div>
    <div style="padding: 1rem; border: 1px solid #ddd; border-radius: 4px;">
      <strong>Failed</strong><br>
      <span style="font-size: 2em; color: #dc3545;"><?= $stats['failed'] ?></span>
    </div>
  </div>
</section>

<section>
  <h2>Run Jobs</h2>
  <p>Run a small batch of image jobs from the browser (use Cron for production).</p>
  <form action="/admin/jobs/run" method="post">
    <?= \OpenWishlist\Support\Csrf::field() ?>
    <button type="submit">Run small batch now</button>
  </form>
  <p><small>Tip: set up a cron to run <code>php bin/worker --max-seconds=50 --max-jobs=20</code> every minute.</small></p>
</section>

<?php if (!empty($recentJobs)): ?>
<section>
  <h2>Recent Jobs</h2>
  <table style="width: 100%; border-collapse: collapse;">
    <thead>
      <tr style="background: #f8f9fa;">
        <th style="padding: 0.5rem; border: 1px solid #ddd; text-align: left;">ID</th>
        <th style="padding: 0.5rem; border: 1px solid #ddd; text-align: left;">Type</th>
        <th style="padding: 0.5rem; border: 1px solid #ddd; text-align: left;">Status</th>
        <th style="padding: 0.5rem; border: 1px solid #ddd; text-align: left;">Attempts</th>
        <th style="padding: 0.5rem; border: 1px solid #ddd; text-align: left;">Run At</th>
        <th style="padding: 0.5rem; border: 1px solid #ddd; text-align: left;">Error</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($recentJobs as $job): 
        $statusColor = match($job['status']) {
          'queued' => '#007bff',
          'processing' => '#ffc107', 
          'completed' => '#28a745',
          'failed' => '#dc3545',
          default => '#6c757d'
        };
        $runAt = new DateTime($job['run_at']);
        $isOverdue = $job['status'] === 'queued' && $runAt < new DateTime();
      ?>
      <tr>
        <td style="padding: 0.5rem; border: 1px solid #ddd;"><?= $job['id'] ?></td>
        <td style="padding: 0.5rem; border: 1px solid #ddd;"><?= htmlspecialchars($job['type']) ?></td>
        <td style="padding: 0.5rem; border: 1px solid #ddd; color: <?= $statusColor ?>; font-weight: bold;">
          <?= htmlspecialchars($job['status']) ?>
          <?= $isOverdue ? ' (overdue)' : '' ?>
        </td>
        <td style="padding: 0.5rem; border: 1px solid #ddd;"><?= $job['attempts'] ?></td>
        <td style="padding: 0.5rem; border: 1px solid #ddd; font-family: monospace; font-size: 0.9em;">
          <?= $runAt->format('H:i:s') ?>
        </td>
        <td style="padding: 0.5rem; border: 1px solid #ddd; font-size: 0.9em; max-width: 300px; overflow: hidden; text-overflow: ellipsis;">
          <?= htmlspecialchars($job['last_error'] ?: '-') ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</section>
<?php endif; ?>