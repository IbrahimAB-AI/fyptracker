<?php
/**
 * FYPTracker — Student Dashboard
 * student/dashboard.php
 */

define('BASE_URL', '..');
require_once __DIR__ . '/../includes/functions.php';
requireRole('student');

$db        = getDB();
$studentId = $_SESSION['user_id'];

// Project
$stmtProj = $db->prepare(
    'SELECT p.*, u.full_name AS supervisor_name
       FROM projects p
       LEFT JOIN users u ON u.user_id = p.supervisor_id
      WHERE p.student_id = ? LIMIT 1'
);
$stmtProj->execute([$studentId]);
$project = $stmtProj->fetch();

// Milestone stats
$ms = ['total' => 0, 'completed' => 0, 'in_progress' => 0, 'not_started' => 0];
if ($project) {
    $stmtM = $db->prepare(
        'SELECT completion_status, COUNT(*) AS cnt FROM milestones
          WHERE project_id = ? GROUP BY completion_status'
    );
    $stmtM->execute([$project['project_id']]);
    foreach ($stmtM->fetchAll() as $row) {
        $ms[$row['completion_status']] = (int) $row['cnt'];
        $ms['total'] += (int) $row['cnt'];
    }
}
$progressPct = $ms['total'] > 0 ? round($ms['completed'] / $ms['total'] * 100) : 0;

// Upcoming milestones
$upcomingMilestones = [];
if ($project) {
    $stmtUp = $db->prepare(
        'SELECT * FROM milestones WHERE project_id = ?
           AND completion_status != ? AND due_date >= CURDATE()
         ORDER BY due_date ASC LIMIT 4'
    );
    $stmtUp->execute([$project['project_id'], 'completed']);
    $upcomingMilestones = $stmtUp->fetchAll();
}

// Upcoming meetings
$upcomingMeetings = [];
if ($project) {
    $stmtMt = $db->prepare(
        'SELECT * FROM meetings WHERE project_id = ?
           AND status = ? AND scheduled_date >= NOW()
         ORDER BY scheduled_date ASC LIMIT 3'
    );
    $stmtMt->execute([$project['project_id'], 'scheduled']);
    $upcomingMeetings = $stmtMt->fetchAll();
}

// Recent feedback
$recentFeedback = [];
if ($project) {
    $stmtFb = $db->prepare(
        'SELECT f.*, m.title AS milestone_title, u.full_name AS supervisor_name
           FROM feedback f
           JOIN milestones m ON m.milestone_id = f.milestone_id
           JOIN users u ON u.user_id = f.supervisor_id
          WHERE m.project_id = ?
          ORDER BY f.created_at DESC LIMIT 3'
    );
    $stmtFb->execute([$project['project_id']]);
    $recentFeedback = $stmtFb->fetchAll();
}

// Status badge helper
function statusBadge(string $status): string {
    $map = [
        'pending'      => 'pending',
        'approved'     => 'approved',
        'rejected'     => 'rejected',
        'in_progress'  => 'in-progress',
        'completed'    => 'completed',
        'not_started'  => 'not-started',
        'in progress'  => 'in-progress',
    ];
    $cls   = $map[strtolower($status)] ?? 'not-started';
    $label = ucwords(str_replace('_', ' ', $status));
    return '<span class="badge badge-' . $cls . '">' . e($label) . '</span>';
}

$pageTitle = 'Dashboard';
$activeNav = 'dashboard';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="main-content" id="main-content">
  <?php require_once __DIR__ . '/../includes/topbar.php'; ?>

  <div class="page-body">
    <?php renderFlash(); ?>

    <!-- Page heading -->
    <div class="page-header">
      <div class="page-title">
        Good <?= date('H') < 12 ? 'morning' : (date('H') < 18 ? 'afternoon' : 'evening') ?>,
        <?= e(explode(' ', $_SESSION['full_name'])[0]) ?>
      </div>
      <p class="page-subtitle">Here's an overview of your Final Year Project.</p>
    </div>

    <!-- Stat cards -->
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-icon-wrap" aria-hidden="true">
          <i data-lucide="file-text" width="18" height="18"></i>
        </div>
        <div class="stat-value tabular"><?= $project ? 1 : 0 ?></div>
        <div class="stat-label">Project Submitted</div>
      </div>
      <div class="stat-card">
        <div class="stat-icon-wrap" aria-hidden="true">
          <i data-lucide="check-circle-2" width="18" height="18"></i>
        </div>
        <div class="stat-value tabular"><?= $ms['completed'] ?></div>
        <div class="stat-label">Milestones Done</div>
      </div>
      <div class="stat-card">
        <div class="stat-icon-wrap" aria-hidden="true">
          <i data-lucide="loader-2" width="18" height="18"></i>
        </div>
        <div class="stat-value tabular"><?= $ms['in_progress'] ?></div>
        <div class="stat-label">In Progress</div>
      </div>
      <div class="stat-card">
        <div class="stat-icon-wrap" aria-hidden="true">
          <i data-lucide="calendar" width="18" height="18"></i>
        </div>
        <div class="stat-value tabular"><?= count($upcomingMeetings) ?></div>
        <div class="stat-label">Upcoming Meetings</div>
      </div>
    </div>

    <!-- Project status card (full width) -->
    <div class="card col-span-full" style="margin-bottom:var(--sp-4);">
      <div class="card-header">
        <div>
          <div class="card-title">My Project</div>
          <div class="card-subtitle">Proposal status and chapter progress</div>
        </div>
        <?php if (!$project): ?>
        <a href="submit_proposal.php" class="btn btn-primary btn-sm">
          <i data-lucide="plus" width="14" height="14"></i> Submit Proposal
        </a>
        <?php endif; ?>
      </div>
      <div class="card-body">
        <?php if ($project): ?>

          <?php if ($project['status'] === 'rejected' && $project['rejection_reason']): ?>
          <div class="alert alert-error" style="margin-bottom:var(--sp-4);" data-auto-dismiss="0">
            <i data-lucide="alert-circle" width="16" height="16" class="alert-icon"></i>
            <div class="alert-content">
              <div class="alert-title">Proposal Rejected</div>
              <?= e($project['rejection_reason']) ?>
              — <a href="submit_proposal.php">Resubmit →</a>
            </div>
          </div>
          <?php endif; ?>

          <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:var(--sp-4);flex-wrap:wrap;margin-bottom:var(--sp-5);">
            <div style="flex:1;min-width:0;">
              <p style="font-size:var(--text-md);font-weight:600;color:var(--text-primary);margin-bottom:var(--sp-1);letter-spacing:-0.01em;">
                <?= e($project['title']) ?>
              </p>
              <p style="font-size:var(--text-sm);color:var(--text-tertiary);">
                Supervisor:
                <?php if ($project['supervisor_name']): ?>
                  <span style="color:var(--text-secondary);font-weight:500;"><?= e($project['supervisor_name']) ?></span>
                <?php else: ?>
                  <em style="color:var(--text-tertiary);">Not yet assigned</em>
                <?php endif; ?>
              </p>
            </div>
            <?= statusBadge($project['status']) ?>
          </div>

          <!-- Progress -->
          <div>
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:var(--sp-2);">
              <span style="font-size:var(--text-xs);font-weight:600;color:var(--text-tertiary);text-transform:uppercase;letter-spacing:0.08em;">
                Overall Progress
              </span>
              <span style="font-size:var(--text-sm);font-weight:700;color:var(--accent-400);font-variant-numeric:tabular-nums;">
                <?= $progressPct ?>%
              </span>
            </div>
            <div class="progress" data-progress="<?= $progressPct ?>">
              <div class="progress-bar" style="width:0%;"></div>
            </div>
            <div style="display:flex;gap:var(--sp-5);margin-top:var(--sp-3);flex-wrap:wrap;">
              <span style="font-size:var(--text-xs);color:var(--color-success);display:flex;align-items:center;gap:var(--sp-1);">
                <i data-lucide="check-circle-2" width="12" height="12"></i>
                <?= $ms['completed'] ?> completed
              </span>
              <span style="font-size:var(--text-xs);color:var(--color-info);display:flex;align-items:center;gap:var(--sp-1);">
                <i data-lucide="refresh-cw" width="12" height="12"></i>
                <?= $ms['in_progress'] ?> in progress
              </span>
              <span style="font-size:var(--text-xs);color:var(--text-tertiary);display:flex;align-items:center;gap:var(--sp-1);">
                <i data-lucide="clock" width="12" height="12"></i>
                <?= $ms['not_started'] ?> pending
              </span>
            </div>
          </div>

        <?php else: ?>
        <div class="empty-state">
          <div class="empty-icon"><i data-lucide="file-plus-2" width="24" height="24"></i></div>
          <div class="empty-title">No project submitted yet</div>
          <p class="empty-desc">Submit your FYP proposal to get started with your supervision journey.</p>
          <a href="submit_proposal.php" class="btn btn-primary" style="margin-top:var(--sp-4);">
            <i data-lucide="plus" width="16" height="16"></i> Submit Proposal
          </a>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Two-column grid -->
    <div class="content-grid">

      <!-- Upcoming milestones -->
      <div class="card">
        <div class="card-header">
          <div>
            <div class="card-title">Upcoming Milestones</div>
            <div class="card-subtitle">Next deadlines to hit</div>
          </div>
          <a href="milestones.php" class="btn btn-ghost btn-sm">
            View all <i data-lucide="arrow-right" width="14" height="14"></i>
          </a>
        </div>
        <div class="card-body" style="padding-top:var(--sp-3);">
          <?php if ($upcomingMilestones): ?>
          <div style="display:flex;flex-direction:column;gap:1px;">
            <?php foreach ($upcomingMilestones as $m):
              $days    = (int) ceil((strtotime($m['due_date']) - time()) / 86400);
              $urgency = $days <= 3 ? 'var(--color-error)' : ($days <= 7 ? 'var(--color-warning)' : 'var(--text-tertiary)');
              $barCls  = $days <= 3 ? 'progress-bar-error' : ($days <= 7 ? 'progress-bar-warning' : '');
            ?>
            <div style="display:flex;align-items:center;gap:var(--sp-3);padding:var(--sp-3) 0;border-bottom:1px solid var(--surface-divider);">
              <div style="width:3px;height:36px;background:<?= $urgency ?>;border-radius:var(--radius-full);flex-shrink:0;"></div>
              <div style="flex:1;min-width:0;">
                <p style="font-size:var(--text-sm);font-weight:600;color:var(--text-primary);margin-bottom:3px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                  <?= e($m['title']) ?>
                </p>
                <div style="display:flex;align-items:center;gap:var(--sp-3);">
                  <?= statusBadge($m['completion_status']) ?>
                  <span style="font-size:var(--text-xs);color:<?= $urgency ?>;font-weight:500;font-variant-numeric:tabular-nums;white-space:nowrap;">
                    <?= formatDate($m['due_date']) ?>
                    <?= $days <= 14 ? "· {$days}d left" : '' ?>
                  </span>
                </div>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
          <?php else: ?>
          <div class="empty-state" style="padding:var(--sp-8) var(--sp-4);">
            <div class="empty-icon"><i data-lucide="target" width="22" height="22"></i></div>
            <div class="empty-title">No upcoming milestones</div>
          </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Recent feedback -->
      <div class="card">
        <div class="card-header">
          <div>
            <div class="card-title">Recent Feedback</div>
            <div class="card-subtitle">From your supervisor</div>
          </div>
          <a href="milestones.php" class="btn btn-ghost btn-sm">
            View all <i data-lucide="arrow-right" width="14" height="14"></i>
          </a>
        </div>
        <div class="card-body" style="padding-top:var(--sp-3);">
          <?php if ($recentFeedback): ?>
          <div style="display:flex;flex-direction:column;gap:var(--sp-4);">
            <?php foreach ($recentFeedback as $fb): ?>
            <div style="padding:var(--sp-3) var(--sp-4);background:var(--surface-overlay);border:1px solid var(--surface-border);border-radius:var(--radius-md);">
              <div style="display:flex;align-items:center;justify-content:space-between;gap:var(--sp-2);margin-bottom:var(--sp-2);">
                <span style="font-size:var(--text-xs);font-weight:700;text-transform:uppercase;letter-spacing:0.07em;color:var(--accent-500);">
                  <?= e(mb_strimwidth($fb['milestone_title'], 0, 40, '…')) ?>
                </span>
                <?php if ($fb['rating']): ?>
                <span style="font-size:var(--text-xs);color:var(--color-warning);letter-spacing:-1px;">
                  <?= str_repeat('★', (int)$fb['rating']) ?><?= str_repeat('☆', 5 - (int)$fb['rating']) ?>
                </span>
                <?php endif; ?>
              </div>
              <p style="font-size:var(--text-sm);color:var(--text-secondary);line-height:1.55;margin-bottom:var(--sp-2);">
                <?= e(mb_strimwidth($fb['comment'], 0, 140, '…')) ?>
              </p>
              <p style="font-size:var(--text-xs);color:var(--text-tertiary);">
                — <?= e($fb['supervisor_name']) ?> · <?= timeAgo($fb['created_at']) ?>
              </p>
            </div>
            <?php endforeach; ?>
          </div>
          <?php else: ?>
          <div class="empty-state" style="padding:var(--sp-8) var(--sp-4);">
            <div class="empty-icon"><i data-lucide="message-square" width="22" height="22"></i></div>
            <div class="empty-title">No feedback yet</div>
            <p class="empty-desc">Feedback from your supervisor will appear here.</p>
          </div>
          <?php endif; ?>
        </div>
      </div>

    </div><!-- /content-grid -->
  </div><!-- /page-body -->
</div><!-- /main-content -->

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
