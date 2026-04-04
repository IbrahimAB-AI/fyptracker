<?php
/**
 * FYPTracker — Supervisor: Review Proposals
 * supervisor/review_proposals.php
 *
 * Displays all proposals assigned to this supervisor.
 * Actions:
 *   - Approve a proposal  → status: approved, notifies student
 *   - Reject a proposal   → status: rejected + reason, notifies student
 *   - View full details of any proposal
 *
 * Principles:
 *  - coding-standards: early returns, named constants, verb-noun methods
 *  - backend-patterns: repository + service layers, centralized errors
 */

define('BASE_URL', '..');
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/repositories/ProjectRepository.php';
require_once __DIR__ . '/../includes/repositories/SupervisorRepository.php';

requireRole('supervisor');

$db                   = getDB();
$projectRepository    = new ProjectRepository($db);
$supervisorRepository = new SupervisorRepository($db);

$supervisorId = $_SESSION['user_id'];

// ── Named constants ──────────────────────────────────────────────────────
const ACTION_APPROVE = 'approve';
const ACTION_REJECT  = 'reject';

// ── POST: process approval or rejection ──────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $action    = post('action');
    $projectId = (int) post('project_id');

    // Validate action is one of the allowed values
    $allowedActions = [ACTION_APPROVE, ACTION_REJECT];
    if (!in_array($action, $allowedActions, true)) {
        setFlash('error', 'Invalid action.');
        redirect('review_proposals.php');
    }

    // Verify this project actually belongs to this supervisor
    $project = $projectRepository->findProjectById($projectId);
    if (!$project || (int) $project['supervisor_id'] !== $supervisorId) {
        setFlash('error', 'Project not found or access denied.');
        redirect('review_proposals.php');
    }

    // Guard: can only act on pending proposals
    if ($project['status'] !== ProjectRepository::STATUS_PENDING) {
        setFlash('error', 'This proposal has already been reviewed.');
        redirect('review_proposals.php');
    }

    if ($action === ACTION_APPROVE) {
        $projectRepository->updateProjectStatus(
            $projectId,
            ProjectRepository::STATUS_APPROVED
        );

        createNotification(
            (int) $project['student_id'],
            "Your project proposal \"{$project['title']}\" has been approved by {$_SESSION['full_name']}. You may now view your milestones.",
            '../student/milestones.php'
        );

        auditLog('proposal_approved', 'projects', $projectId);
        setFlash('success', "Proposal approved successfully. The student has been notified.");

    } elseif ($action === ACTION_REJECT) {
        $rejectionReason = trim(post('rejection_reason'));

        if (mb_strlen($rejectionReason) < 10) {
            setFlash('error', 'Please provide a detailed rejection reason (minimum 10 characters).');
            redirect('review_proposals.php');
        }

        $projectRepository->updateProjectStatus(
            $projectId,
            ProjectRepository::STATUS_REJECTED,
            $rejectionReason
        );

        createNotification(
            (int) $project['student_id'],
            "Your project proposal \"{$project['title']}\" was not approved. Please read the feedback and resubmit.",
            '../student/submit_proposal.php'
        );

        auditLog('proposal_rejected', 'projects', $projectId);
        setFlash('success', 'Proposal rejected. The student has been notified and may resubmit.');
    }

    redirect('review_proposals.php');
}

// ── GET: load proposals grouped by status ────────────────────────────────
$allProjects = $projectRepository->findProjectsBySupervisorId($supervisorId);

// Group by status in a single pass — DRY, no repeated array_filter calls
$projectsByStatus = [
    ProjectRepository::STATUS_PENDING     => [],
    ProjectRepository::STATUS_APPROVED    => [],
    ProjectRepository::STATUS_IN_PROGRESS => [],
    ProjectRepository::STATUS_REJECTED    => [],
    ProjectRepository::STATUS_COMPLETED   => [],
];

foreach ($allProjects as $project) {
    $status = $project['status'];
    if (array_key_exists($status, $projectsByStatus)) {
        $projectsByStatus[$status][] = $project;
    }
}

$pendingCount = count($projectsByStatus[ProjectRepository::STATUS_PENDING]);

// Load milestone stats for each project — single query per project
// (small N for supervisor so acceptable; alternative is one big GROUP BY query)
$milestoneStatsByProject = [];
foreach ($allProjects as $project) {
    $milestoneStatsByProject[$project['project_id']] =
        $projectRepository->countMilestoneStatsByProjectId((int) $project['project_id']);
}

// ── View helpers ─────────────────────────────────────────────────────────
function renderStatusBadge(string $status): string
{
    $map = [
        'pending'     => 'pending',
        'approved'    => 'approved',
        'in_progress' => 'in-progress',
        'rejected'    => 'rejected',
        'completed'   => 'completed',
    ];
    $cls   = $map[$status] ?? 'not-started';
    $label = ucwords(str_replace('_', ' ', $status));
    return "<span class=\"badge badge-{$cls}\">{$label}</span>";
}

$pageTitle = 'Review Proposals';
$activeNav = 'proposals';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="main-content" id="main-content">
  <?php require_once __DIR__ . '/../includes/topbar.php'; ?>

  <div class="page-body">
    <?php renderFlash(); ?>

    <!-- Page header -->
    <div class="page-header">
      <div class="page-header-row">
        <div>
          <div class="page-title">Review Proposals</div>
          <p class="page-subtitle">
            <?= count($allProjects) ?> total project<?= count($allProjects) !== 1 ? 's' : '' ?> assigned
            <?= $pendingCount > 0 ? " · <strong style='color:var(--color-warning);'>{$pendingCount} awaiting review</strong>" : '' ?>
          </p>
        </div>
      </div>
    </div>

    <!-- Pending proposals — shown first, most urgent -->
    <?php if (!empty($projectsByStatus[ProjectRepository::STATUS_PENDING])): ?>
    <div style="margin-bottom:var(--sp-6);">
      <h2 style="font-size:var(--text-sm);font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:var(--color-warning);margin-bottom:var(--sp-3);display:flex;align-items:center;gap:var(--sp-2);">
        <i data-lucide="clock" width="14" height="14"></i>
        Awaiting Review (<?= $pendingCount ?>)
      </h2>
      <div style="display:flex;flex-direction:column;gap:var(--sp-3);">
        <?php foreach ($projectsByStatus[ProjectRepository::STATUS_PENDING] as $project): ?>
        <div class="card">
          <div style="padding:var(--sp-5);">
            <div style="display:flex;align-items:flex-start;gap:var(--sp-4);flex-wrap:wrap;">
              <div style="flex:1;min-width:0;">
                <div style="display:flex;align-items:center;gap:var(--sp-3);margin-bottom:var(--sp-2);flex-wrap:wrap;">
                  <h3 style="font-size:var(--text-md);font-weight:600;color:var(--text-primary);letter-spacing:-0.01em;">
                    <?= e($project['title']) ?>
                  </h3>
                  <?= renderStatusBadge($project['status']) ?>
                </div>
                <div style="display:flex;align-items:center;gap:var(--sp-4);margin-bottom:var(--sp-3);flex-wrap:wrap;">
                  <span style="display:flex;align-items:center;gap:var(--sp-1);font-size:var(--text-xs);color:var(--text-tertiary);">
                    <i data-lucide="user" width="12" height="12"></i>
                    <?= e($project['student_name']) ?>
                  </span>
                  <span style="display:flex;align-items:center;gap:var(--sp-1);font-size:var(--text-xs);color:var(--text-tertiary);">
                    <i data-lucide="hash" width="12" height="12"></i>
                    <?= e($project['matric_number']) ?>
                  </span>
                  <span style="display:flex;align-items:center;gap:var(--sp-1);font-size:var(--text-xs);color:var(--text-tertiary);">
                    <i data-lucide="calendar" width="12" height="12"></i>
                    Submitted <?= formatDate($project['submission_date']) ?>
                  </span>
                </div>

                <!-- View full proposal button -->
                <button
                  class="btn btn-ghost btn-sm"
                  onclick="openModal('modal-proposal-<?= (int) $project['project_id'] ?>')"
                  style="padding-left:0;">
                  <i data-lucide="eye" width="13" height="13"></i>
                  View Full Proposal
                </button>
              </div>

              <!-- Action buttons -->
              <div style="display:flex;gap:var(--sp-2);flex-shrink:0;flex-wrap:wrap;">
                <!-- Approve -->
                <form method="POST" action="review_proposals.php">
                  <?= csrfField() ?>
                  <input type="hidden" name="project_id" value="<?= (int) $project['project_id'] ?>">
                  <input type="hidden" name="action" value="<?= ACTION_APPROVE ?>">
                  <button
                    type="submit"
                    class="btn btn-primary btn-sm"
                    data-confirm="Approve this proposal? The student will be notified immediately.">
                    <i data-lucide="check" width="14" height="14"></i>
                    Approve
                  </button>
                </form>

                <!-- Reject — opens modal for reason -->
                <button
                  class="btn btn-danger btn-sm"
                  onclick="openRejectModal(<?= (int) $project['project_id'] ?>, <?= htmlspecialchars(json_encode($project['title']), ENT_QUOTES) ?>)">
                  <i data-lucide="x" width="14" height="14"></i>
                  Reject
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- Full proposal view modal -->
        <div class="modal-backdrop" id="modal-proposal-<?= (int) $project['project_id'] ?>">
          <div class="modal" style="max-width:640px;">
            <div class="modal-header">
              <div class="modal-title">Proposal Details</div>
              <button class="modal-close" onclick="closeModal('modal-proposal-<?= (int) $project['project_id'] ?>')" aria-label="Close">
                <i data-lucide="x" width="16" height="16"></i>
              </button>
            </div>
            <div class="modal-body" style="display:flex;flex-direction:column;gap:var(--sp-4);">
              <div>
                <p style="font-size:var(--text-xs);font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:var(--text-tertiary);margin-bottom:var(--sp-1);">Student</p>
                <p style="font-size:var(--text-sm);font-weight:600;color:var(--text-primary);"><?= e($project['student_name']) ?> — <?= e($project['matric_number']) ?></p>
              </div>
              <div>
                <p style="font-size:var(--text-xs);font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:var(--text-tertiary);margin-bottom:var(--sp-1);">Project Title</p>
                <p style="font-size:var(--text-md);font-weight:600;color:var(--text-primary);"><?= e($project['title']) ?></p>
              </div>
              <?php
              // Fetch full description — not in list query (select only what's needed)
              $fullProject = $projectRepository->findProjectById((int) $project['project_id']);
              ?>
              <div>
                <p style="font-size:var(--text-xs);font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:var(--text-tertiary);margin-bottom:var(--sp-1);">Description</p>
                <p style="font-size:var(--text-sm);color:var(--text-secondary);line-height:1.7;white-space:pre-line;"><?= e($fullProject['description'] ?? '') ?></p>
              </div>
              <?php if (!empty($project['chapter_file'])): ?>
              <div>
                <a href="<?= BASE_URL ?>/<?= e($project['chapter_file']) ?>" target="_blank" class="btn btn-outline btn-sm">
                  <i data-lucide="paperclip" width="13" height="13"></i> View Attached Document
                </a>
              </div>
              <?php endif; ?>
              <div style="display:flex;gap:var(--sp-2);padding-top:var(--sp-2);border-top:1px solid var(--surface-divider);">
                <!-- Approve from modal -->
                <form method="POST" action="review_proposals.php" style="flex:1;">
                  <?= csrfField() ?>
                  <input type="hidden" name="project_id" value="<?= (int) $project['project_id'] ?>">
                  <input type="hidden" name="action" value="<?= ACTION_APPROVE ?>">
                  <button type="submit" class="btn btn-primary" style="width:100%;" data-confirm="Approve this proposal?">
                    <i data-lucide="check" width="16" height="16"></i> Approve
                  </button>
                </form>
                <button
                  class="btn btn-danger"
                  style="flex:1;"
                  onclick="closeModal('modal-proposal-<?= (int) $project['project_id'] ?>'); openRejectModal(<?= (int) $project['project_id'] ?>, <?= htmlspecialchars(json_encode($project['title']), ENT_QUOTES) ?>)">
                  <i data-lucide="x" width="16" height="16"></i> Reject
                </button>
              </div>
            </div>
          </div>
        </div>

        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- All other projects as a table -->
    <?php
    $reviewedProjects = array_merge(
        $projectsByStatus[ProjectRepository::STATUS_APPROVED],
        $projectsByStatus[ProjectRepository::STATUS_IN_PROGRESS],
        $projectsByStatus[ProjectRepository::STATUS_COMPLETED],
        $projectsByStatus[ProjectRepository::STATUS_REJECTED]
    );
    ?>

    <?php if (!empty($reviewedProjects)): ?>
    <div>
      <h2 style="font-size:var(--text-sm);font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:var(--text-tertiary);margin-bottom:var(--sp-3);display:flex;align-items:center;gap:var(--sp-2);">
        <i data-lucide="list" width="14" height="14"></i>
        All Projects (<?= count($reviewedProjects) ?>)
      </h2>
      <div class="card">
        <div class="table-wrapper">
          <table>
            <thead>
              <tr>
                <th>Student</th>
                <th>Matric No.</th>
                <th>Project Title</th>
                <th>Status</th>
                <th>Progress</th>
                <th>Submitted</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($reviewedProjects as $project):
                $stats  = $milestoneStatsByProject[$project['project_id']] ?? ['total' => 0, 'completed' => 0];
                $pct    = $stats['total'] > 0 ? (int) round($stats['completed'] / $stats['total'] * 100) : 0;
              ?>
              <tr>
                <td class="cell-primary"><?= e($project['student_name']) ?></td>
                <td class="cell-muted font-mono"><?= e($project['matric_number']) ?></td>
                <td style="max-width:220px;">
                  <span class="truncate" style="display:block;font-size:var(--text-sm);" title="<?= e($project['title']) ?>">
                    <?= e(mb_strimwidth($project['title'], 0, 55, '…')) ?>
                  </span>
                </td>
                <td><?= renderStatusBadge($project['status']) ?></td>
                <td style="min-width:120px;">
                  <div style="display:flex;align-items:center;gap:var(--sp-2);">
                    <div class="progress" style="flex:1;" data-progress="<?= $pct ?>">
                      <div class="progress-bar" style="width:0%;"></div>
                    </div>
                    <span style="font-size:var(--text-xs);font-weight:700;color:var(--text-tertiary);font-variant-numeric:tabular-nums;white-space:nowrap;">
                      <?= $pct ?>%
                    </span>
                  </div>
                </td>
                <td class="cell-muted"><?= $project['submission_date'] ? formatDate($project['submission_date']) : '—' ?></td>
                <td>
                  <div class="cell-actions">
                    <a href="milestones.php?student=<?= (int) $project['student_id'] ?>" class="btn btn-ghost btn-sm">
                      <i data-lucide="target" width="13" height="13"></i> Milestones
                    </a>
                  </div>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <?php if (empty($allProjects)): ?>
    <div class="card">
      <div class="card-body">
        <div class="empty-state">
          <div class="empty-icon"><i data-lucide="clipboard-list" width="24" height="24"></i></div>
          <div class="empty-title">No projects assigned</div>
          <p class="empty-desc">The FYP Coordinator has not assigned any students to you yet.</p>
        </div>
      </div>
    </div>
    <?php endif; ?>

  </div><!-- /page-body -->
</div>

<!-- ── Shared rejection reason modal ─────────────────────────────────── -->
<div class="modal-backdrop" id="modal-reject">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title">Reject Proposal</div>
      <button class="modal-close" onclick="closeModal('modal-reject')" aria-label="Close">
        <i data-lucide="x" width="16" height="16"></i>
      </button>
    </div>
    <form method="POST" action="review_proposals.php">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="<?= ACTION_REJECT ?>">
      <input type="hidden" name="project_id" id="reject-project-id" value="">
      <div class="modal-body">
        <div class="alert alert-warning" style="margin-bottom:var(--sp-4);" data-auto-dismiss="0">
          <i data-lucide="alert-triangle" width="16" height="16" class="alert-icon"></i>
          <div class="alert-content">The student will be notified and allowed to resubmit.</div>
        </div>
        <div class="form-group">
          <label class="form-label form-label-required" for="rejection_reason">
            Reason for Rejection
          </label>
          <textarea
            class="form-control"
            id="rejection_reason"
            name="rejection_reason"
            rows="5"
            placeholder="Explain specifically what needs to change for the proposal to be approved..."
            minlength="10"
            maxlength="1000"
            required></textarea>
          <span class="form-hint">Be constructive — the student will use this to improve their resubmission.</span>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modal-reject')">Cancel</button>
        <button type="submit" class="btn btn-danger">
          <i data-lucide="x-circle" width="14" height="14"></i> Confirm Rejection
        </button>
      </div>
    </form>
  </div>
</div>

<script>
function openRejectModal(projectId, projectTitle) {
  document.getElementById('reject-project-id').value = projectId;
  document.getElementById('rejection_reason').value  = '';
  openModal('modal-reject');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
