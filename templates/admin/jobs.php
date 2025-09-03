<nav style="margin-bottom: 1rem;">
    <ul>
        <li><a href="/admin">Dashboard</a></li>
        <li><strong><a href="/admin/jobs" style="text-decoration: none;">Jobs</a></strong></li>
        <li><a href="/admin/settings">Settings</a></li>
    </ul>
</nav>

<h1>Jobs <small style="color: #666; font-weight: normal;"><?= \OpenWishlist\Support\Version::formatDisplay() ?></small></h1>

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
  <h2>Job Management</h2>
  <div style="margin-bottom: 1rem;">
    <p>Run a small batch of image jobs from the browser (use Cron for production).</p>
    <form action="/admin/jobs/run" method="post" style="margin-bottom: 1.5rem;">
      <?= \OpenWishlist\Support\Csrf::field() ?>
      <button type="submit" style="width: auto;">Run small batch now</button>
    </form>
    
    <p>Clean up jobs by status or age.</p>
    <div style="display: flex; gap: 0.5rem; flex-wrap: wrap; margin-bottom: 0.5rem;">
      <button type="button" onclick="cleanupJobsByStatus('completed')" 
              style="background: #28a745; color: white; padding: 0.5rem 1rem; border: none; border-radius: 4px;">
        Cleanup All Completed (<?= $stats['completed'] ?>)
      </button>
      <button type="button" onclick="cleanupJobsByStatus('failed')" 
              style="background: #dc3545; color: white; padding: 0.5rem 1rem; border: none; border-radius: 4px;">
        Cleanup All Failed (<?= $stats['failed'] ?>)
      </button>
      <button type="button" onclick="cleanupJobsByStatus('queued')" 
              style="background: #ffc107; color: black; padding: 0.5rem 1rem; border: none; border-radius: 4px;">
        Cleanup All Queued (<?= $stats['queued'] ?>)
      </button>
    </div>
    <button type="button" onclick="cleanupOldJobs()" style="background: #6c757d; color: white;">Cleanup Old Jobs (by age)</button>
  </div>
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
        <th style="padding: 0.5rem; border: 1px solid #ddd; text-align: left;">Actions</th>
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
        <td style="padding: 0.5rem; border: 1px solid #ddd;">
          <button type="button" onclick="deleteJob(<?= $job['id'] ?>)" 
                  style="background: #dc3545; color: white; border: none; padding: 0.25rem 0.5rem; border-radius: 3px; cursor: pointer; font-size: 0.8em;"
                  title="Delete this job">Delete</button>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</section>
<?php endif; ?>

<script>
async function deleteJob(jobId) {
    if (!confirm('Are you sure you want to delete this job?')) {
        return;
    }
    
    try {
        const response = await fetch(`/api/admin/jobs/${jobId}`, {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                'X-Csrf-Token': '<?= \OpenWishlist\Support\Csrf::token() ?>'
            }
        });
        
        if (response.ok) {
            location.reload();
        } else {
            const error = await response.json();
            alert(`Error: ${error.detail || 'Failed to delete job'}`);
        }
    } catch (e) {
        alert('Network error: Failed to delete job');
    }
}

async function cleanupJobsByStatus(status) {
    const counts = {
        completed: <?= $stats['completed'] ?>,
        failed: <?= $stats['failed'] ?>,
        queued: <?= $stats['queued'] ?>
    };
    
    if (counts[status] === 0) {
        alert(`No ${status} jobs to cleanup.`);
        return;
    }
    
    if (!confirm(`This will delete ALL ${counts[status]} ${status} jobs. Continue?`)) {
        return;
    }
    
    try {
        const response = await fetch('/api/admin/jobs/cleanup-by-status', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Csrf-Token': '<?= \OpenWishlist\Support\Csrf::token() ?>'
            },
            body: JSON.stringify({ status: status })
        });
        
        if (response.ok) {
            const result = await response.json();
            alert(result.message);
            location.reload();
        } else {
            const error = await response.json();
            alert(`Error: ${error.detail || 'Failed to cleanup jobs'}`);
        }
    } catch (e) {
        alert('Network error: Failed to cleanup jobs');
    }
}

async function cleanupOldJobs() {
    if (!confirm('This will delete completed jobs older than 7 days and failed jobs older than 30 days. Continue?')) {
        return;
    }
    
    try {
        const response = await fetch('/api/admin/jobs/cleanup', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Csrf-Token': '<?= \OpenWishlist\Support\Csrf::token() ?>'
            }
        });
        
        if (response.ok) {
            const result = await response.json();
            alert(result.message);
            location.reload();
        } else {
            const error = await response.json();
            alert(`Error: ${error.detail || 'Failed to cleanup jobs'}`);
        }
    } catch (e) {
        alert('Network error: Failed to cleanup jobs');
    }
}
</script>