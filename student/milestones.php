<?php
/**
 * FYPTracker — Student: Milestones Tracker
 * student/milestones.php
 *
 * Displays all milestones for the student's project.
 * Allows the student to:
 *   - Mark milestones as in_progress / completed
 *   - Upload submission files per milestone
 *   - View supervisor feedback inline
 *
 * Principles:
 *  - coding-standards: early returns, named constants, DRY helpers
 *  - backend-patterns: repository pattern, N+1 prevention (feedback loaded per milestone via repository)
 */

define('BASE_URL', '..');
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/repositories/ProjectRepository.php';
require_once __DIR__ . '/../includes/services/FileUploadService.php';
require_once __DIR__ . '/../includes/services/ValidationService.php';

requireRole('student');

$db                = getDB();
$projectRepository = new ProjectRepository($db);
$fileUploadService = new FileUploadService(__DIR__ . '/../uploads');
$validator         = new ValidationService();

$studentId = $_SESSION['user_id'];
$project   = $projectRepository->findProjectByStudentId($studentId);

// ── Guard: no project yet ────────────────────────────────────────────────
if (!$project) {
    setFlash('info', 'You have not submitted a project proposal yet.');
    redirect('submit_proposal.php');
}

// ── POST: update milestone status ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $milestoneId     = (int) post('milestone_id');
    $newStatus       = post('completion_status');
    $projectId       = (int) $project['project_id'];

    // Validate status value
    if (!$validator->validateMilestoneStatusUpdate($newStatus)) {
        setFlash('error', $validator->getFirstError());
        redirect('milestones.php');
    }

    // Ensure milestone belongs to this student's project (authorization check)
    $milestones     = $projectRepository->findMilestonesByProjectId($projectId);
    $milestoneIds   = array_column($milestones, 'milestone_id');
    $belongsToStudent = in_array($milestoneId, array_map('intval', $milestoneIds), true);

    if (!$belongsToStudent) {
        setFlash('error', 'Invalid milestone.');
        redirect('milestones.php');
    }

    // Handle optional file upload for this milestone
    $uploadResult = $fileUploadService->handleUpload(
        $_FILES['submission_file'] ?? [],
        'milestone',
        'milestone_' . $milestoneId
    );

    if (!$uploadResult['success']) {
        setFlash('error', $uploadResult['error']);
        redirect('milestones.php');
    }

    $projectRepository->updateMilestoneStatus(
        $milestoneId,
        $newStatus,
        $uploadResult['path']
    );

    // Notify supervisor when student marks a milestone complete
    if ($newStatus === ProjectRepository::MILESTONE_COMPLETED && $project['supervisor_id']) {
        $milestoneTitles = array_column($milestones, 'title', 'milestone_id');
        $milestoneTitle  = $milestoneTitles[$milestoneId] ?? 'a milestone';

        createNotification(
            (int) $project['supervisor_id'],
            "{$_SESSION['full_name']} has marked \"{$milestoneTitle}\" as completed.",
            '../supervisor/milestones.php?student=' . $studentId
        );
    }

    auditLog('milestone_status_updated', 'milestones', $milestoneId);
    setFlash('success', 'Milestone updated successfully.');
    redirect('milestones.php');
}

// ── GET: load milestones with feedback ───────────────────────────────────
$milestones  = $projectRepository->findMilestonesByProjectId((int) $project['project_id']);
$milestoneStats = $projectRepository->countMilestoneStatsByProjectId((int) $project['project_id']);

$progressPct = $milestoneStats['total'] > 0
    ? (int) round($milestoneStats['completed'] / $milestoneStats['total'] * 100)
    : 0;

// Load feedback for each milestone (batch — N+1 prevention via single query per milestone)
// Since we need feedback per milestone, we batch-load all feedback for the project at once
$allFeedback = $projectRepository->findFeedbackByProjectId((int) $project['project_id']);

// Group feedback by milestone_id (single pass — O(n))
$feedbackByMilestone = [];
foreach ($allFeedback as $fb) {
    $feedbackByMilestone[(int) $fb['milestone_id']][] = $fb;
}

// ── View helpers ─────────────────────────────────────────────────────────
function getMilestoneStatusConfig(string $status): array
{
    // Named return structure (coding-standards: descriptive, no magic strings)
    return match ($status) {
        ProjectRepository::MILESTONE_COMPLETED   => ['badge' => 'completed',   'icon' => 'check-circle-2',  'color' => 'var(--color-success)'],
        ProjectRepository::MILESTONE_IN_PROGRESS => ['badge' => 'in-progress', 'icon' => 'refresh-cw',      'color' => 'var(--color-info)'],
        default                                  => ['badge' => 'not-started', 'icon' => 'circle',           'color' => 'var(--text-tertiary)'],
    };
}

function getDaysRemainingLabel(string $dueDate, string $status): string
{
    if ($status === ProjectRepository::MILESTONE_COMPLETED) return 'Done';

    $daysRemaining = (int) ceil((strtotime($dueDate) - time()) / 86400);

    if ($daysRemaining < 0)  return abs($daysRemaining) . 'd overdue';
    if ($daysRemaining === 0) return 'Due today';
    if ($daysRemaining === 1) return 'Due tomorrow';

    return $daysRemaining . 'd remaining';
}

function getDaysRemainingColor(string $dueDate, string $status): string
{
    if ($status === ProjectRepository::MILESTONE_COMPLETED) return 'var(--color-success)';

    $daysRemaining = (int) ceil((strtotime($dueDate) - time()) / 86400);

    if ($daysRemaining < 0)  return 'var(--color-error)';
    if ($daysRemaining <= 3) return 'var(--color-error)';
    if ($daysRemaining <= 7) return 'var(--color-warning)';

    return 'var(--text-tertiary)';
}

$pageTitle = 'Milestones';
$activeNav = 'milestones';
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
          <div class="page-title">Milestones</div>
          <p class="page-subtitle" style="max-width:520px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
            <?= e($project['title']) ?>
          </p>
        </div>
      </div>
    </div>

    <!-- Progress summary -->
    <div class="card" style="margin-bottom:var(--sp-4);">
      <div class="card-body">
        <div style="display:flex;align-items:center;justify-content:space-between;gap:var(--sp-4);flex-wrap:wrap;margin-bottom:var(--sp-4);">
          <div style="display:flex;gap:var(--sp-6);flex-wrap:wrap;">
            <?php
            $summaryItems = [
                ['label' => 'Total',       'value' => $milestoneStats['total'],       'color' => 'var(--text-primary)'],
                ['label' => 'Completed',   'value' => $milestoneStats['completed'],   'color' => 'var(--color-success)'],
                ['label' => 'In Progress', 'value' => $milestoneStats['in_progress'], 'color' => 'var(--color-info)'],
                ['label' => 'Pending',     'value' => $milestoneStats['not_started'], 'color' => 'var(--text-tertiary)'],
            ];
            foreach ($summaryItems as $item):
            ?>
            <div>
              <p style="font-size:var(--text-2xl);font-weight:700;color:<?= $item['color'] ?>;letter-spacing:-0.03em;line-height:1;font-variant-numeric:tabular-nums;">
                <?= $item['value'] ?>
              </p>
              <p style="font-size:var(--text-xs);color:var(--text-tertiary);font-weight:600;text-transform:uppercase;letter-spacing:0.08em;margin-top:4px;">
                <?= e($item['label']) ?>
              </p>
            </div>
            <?php endforeach; ?>
          </div>
          <div style="text-align:right;">
            <p style="font-size:var(--text-3xl);font-weight:700;color:var(--accent-400);letter-spacing:-0.03em;font-variant-numeric:tabular-nums;">
              <?= $progressPct ?>%
            </p>
            <p style="font-size:var(--text-xs);color:var(--text-tertiary);font-weight:600;text-transform:uppercase;letter-spacing:0.08em;">Progress</p>
          </div>
        </div>
        <div class="progress" data-progress="<?= $progressPct ?>">
          <div class="progress-bar" style="width:0%;"></div>
        </div>
      </div>
    </div>

    <!-- Milestone list -->
    <?php if (empty($milestones)): ?>
    <div class="card">
      <div class="card-body">
        <div class="empty-state">
          <div class="empty-icon"><i data-lucide="target" width="24" height="24"></i></div>
          <div class="empty-title">No milestones yet</div>
          <p class="empty-desc">Your supervisor will create milestones for your project once your proposal is approved.</p>
        </div>
      </div>
    </div>

    <?php else: ?>

    <div style="display:flex;flex-direction:column;gap:var(--sp-3);">
      <?php foreach ($milestones as $index => $milestone):
        $milestoneId   = (int) $milestone['milestone_id'];
        $statusConfig  = getMilestoneStatusConfig($milestone['completion_status']);
        $daysLabel     = getDaysRemainingLabel($milestone['due_date'], $milestone['completion_status']);
        $daysColor     = getDaysRemainingColor($milestone['due_date'], $milestone['completion_status']);
        $milestoneFb   = $feedbackByMilestone[$milestoneId] ?? [];
        $hasFeedback   = !empty($milestoneFb);
        $isCompleted   = $milestone['completion_status'] === ProjectRepository::MILESTONE_COMPLETED;
      ?>

      <div class="card" style="overflow:visible;">
        <!-- Milestone header -->
        <div style="display:flex;align-items:flex-start;gap:var(--sp-4);padding:var(--sp-5);border-bottom:1px solid var(--surface-divider);">

          <!-- Step number circle -->
          <div style="width:32px;height:32px;border-radius:var(--radius-full);background:<?= $isCompleted ? 'var(--accent-600)' : 'var(--surface-overlay)' ?>;border:2px solid <?= $isCompleted ? 'var(--accent-600)' : 'var(--surface-border)' ?>;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
            <?php if ($isCompleted): ?>
            <i data-lucide="check" width="14" height="14" style="color:#fff;"></i>
            <?php else: ?>
            <span style="font-size:var(--text-xs);font-weight:700;color:var(--text-tertiary);font-variant-numeric:tabular-nums;"><?= $index + 1 ?></span>
            <?php endif; ?>
          </div>

          <!-- Milestone info -->
          <div style="flex:1;min-width:0;">
            <div style="display:flex;align-items:center;gap:var(--sp-3);flex-wrap:wrap;margin-bottom:var(--sp-1);">
              <h3 style="font-size:var(--text-md);font-weight:600;color:var(--text-primary);letter-spacing:-0.01em;">
                <?= e($milestone['title']) ?>
              </h3>
              <span class="badge badge-<?= e($statusConfig['badge']) ?>">
                <?= e(ucwords(str_replace('_', ' ', $milestone['completion_status']))) ?>
              </span>
            </div>

            <?php if ($milestone['description']): ?>
            <p style="font-size:var(--text-sm);color:var(--text-tertiary);line-height:1.55;margin-bottom:var(--sp-3);">
              <?= e($milestone['description']) ?>
            </p>
            <?php endif; ?>

            <div style="display:flex;align-items:center;gap:var(--sp-5);flex-wrap:wrap;">
              <span style="display:flex;align-items:center;gap:var(--sp-1);font-size:var(--text-xs);color:var(--text-tertiary);">
                <i data-lucide="calendar" width="12" height="12"></i>
                Due <?= formatDate($milestone['due_date']) ?>
              </span>
              <span style="display:flex;align-items:center;gap:var(--sp-1);font-size:var(--text-xs);font-weight:600;color:<?= $daysColor ?>;">
                <i data-lucide="clock" width="12" height="12"></i>
                <?= e($daysLabel) ?>
              </span>
              <?php if ($milestone['created_by_name']): ?>
              <span style="display:flex;align-items:center;gap:var(--sp-1);font-size:var(--text-xs);color:var(--text-tertiary);">
                <i data-lucide="user" width="12" height="12"></i>
                Set by <?= e($milestone['created_by_name']) ?>
              </span>
              <?php endif; ?>
              <?php if ($milestone['completed_at']): ?>
              <span style="display:flex;align-items:center;gap:var(--sp-1);font-size:var(--text-xs);color:var(--color-success);">
                <i data-lucide="check-circle-2" width="12" height="12"></i>
                Completed <?= formatDateTime($milestone['completed_at']) ?>
              </span>
              <?php endif; ?>
            </div>
          </div>

          <!-- Action button (update status) -->
          <?php if (!$isCompleted): ?>
          <button
            class="btn btn-secondary btn-sm"
            onclick="openModal('modal-milestone-<?= $milestoneId ?>')"
            style="flex-shrink:0;">
            <i data-lucide="edit-2" width="13" height="13"></i>
            Update
          </button>
          <?php endif; ?>
        </div>

        <!-- Submission file + Feedback -->
        <?php if ($milestone['submission_file'] || $hasFeedback): ?>
        <div style="padding:var(--sp-4) var(--sp-5);display:flex;flex-direction:column;gap:var(--sp-4);">

          <?php if ($milestone['submission_file']): ?>
          <div style="display:flex;align-items:center;gap:var(--sp-2);">
            <i data-lucide="paperclip" width="14" height="14" style="color:var(--accent-500);"></i>
            <span style="font-size:var(--text-xs);color:var(--text-tertiary);">Submitted file:</span>
            <a href="<?= BASE_URL ?>/<?= e($milestone['submission_file']) ?>"
               target="_blank"
               style="font-size:var(--text-xs);font-weight:600;color:var(--accent-500);">
              View Document
            </a>
          </div>
          <?php endif; ?>

          <?php if ($hasFeedback): ?>
          <div>
            <p style="font-size:var(--text-xs);font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:var(--text-tertiary);margin-bottom:var(--sp-3);">
              Supervisor Feedback
            </p>
            <div style="display:flex;flex-direction:column;gap:var(--sp-3);">
              <?php foreach ($milestoneFb as $fb): ?>
              <div style="padding:var(--sp-3) var(--sp-4);background:var(--surface-overlay);border:1px solid var(--surface-border);border-radius:var(--radius-md);border-left:3px solid var(--accent-600);">
                <div style="display:flex;align-items:center;justify-content:space-between;gap:var(--sp-2);margin-bottom:var(--sp-2);">
                  <span style="font-size:var(--text-xs);font-weight:600;color:var(--text-secondary);">
                    <?= e($fb['supervisor_name']) ?>
                  </span>
                  <div style="display:flex;align-items:center;gap:var(--sp-3);">
                    <?php if ($fb['rating']): ?>
                    <span style="font-size:var(--text-xs);color:var(--color-warning);">
                      <?= str_repeat('★', (int)$fb['rating']) ?><?= str_repeat('☆', 5 - (int)$fb['rating']) ?>
                    </span>
                    <?php endif; ?>
                    <span style="font-size:var(--text-xs);color:var(--text-tertiary);">
                      <?= timeAgo($fb['created_at']) ?>
                    </span>
                  </div>
                </div>
                <p style="font-size:var(--text-sm);color:var(--text-secondary);line-height:1.6;">
                  <?= e($fb['comment']) ?>
                </p>
              </div>
              <?php endforeach; ?>
            </div>
          </div>
          <?php endif; ?>
        </div>
        <?php endif; ?>
      </div>

      <!-- Update status modal for this milestone -->
      <?php if (!$isCompleted): ?>
      <div class="modal-backdrop" id="modal-milestone-<?= $milestoneId ?>">
        <div class="modal">
          <div class="modal-header">
            <div class="modal-title">Update Milestone</div>
            <button class="modal-close" onclick="closeModal('modal-milestone-<?= $milestoneId ?>')" aria-label="Close">
              <i data-lucide="x" width="16" height="16"></i>
            </button>
          </div>
          <form method="POST" action="milestones.php" enctype="multipart/form-data">
            <?= csrfField() ?>
            <input type="hidden" name="milestone_id" value="<?= $milestoneId ?>">
            <div class="modal-body">
              <div class="form-stack">

                <div>
                  <p style="font-size:var(--text-sm);font-weight:600;color:var(--text-primary);margin-bottom:var(--sp-1);">
                    <?= e($milestone['title']) ?>
                  </p>
                  <p style="font-size:var(--text-xs);color:var(--text-tertiary);">Due <?= formatDate($milestone['due_date']) ?></p>
                </div>

                <div class="form-group">
                  <label class="form-label form-label-required" for="status-<?= $milestoneId ?>">New Status</label>
                  <select
                    class="form-control"
                    id="status-<?= $milestoneId ?>"
                    name="completion_status"
                    required>
                    <option value="<?= ProjectRepository::MILESTONE_NOT_STARTED ?>"
                      <?= $milestone['completion_status'] === ProjectRepository::MILESTONE_NOT_STARTED ? 'selected' : '' ?>>
                      Not Started
                    </option>
                    <option value="<?= ProjectRepository::MILESTONE_IN_PROGRESS ?>"
                      <?= $milestone['completion_status'] === ProjectRepository::MILESTONE_IN_PROGRESS ? 'selected' : '' ?>>
                      In Progress
                    </option>
                    <option value="<?= ProjectRepository::MILESTONE_COMPLETED ?>">
                      Completed
                    </option>
                  </select>
                </div>

                <div class="form-group">
                  <label class="form-label" for="file-<?= $milestoneId ?>">
                    Attach Submission
                    <span style="color:var(--text-tertiary);font-weight:400;font-size:var(--text-xs);text-transform:none;">(optional — PDF or DOCX, max 10MB)</span>
                  </label>
                  <div class="file-input-wrapper">
                    <i data-lucide="upload-cloud" width="18" height="18" style="color:var(--text-tertiary);flex-shrink:0;"></i>
                    <div>
                      <div class="file-input-label">Choose file</div>
                      <div class="file-input-name">No file selected</div>
                    </div>
                    <input type="file" id="file-<?= $milestoneId ?>" name="submission_file" accept=".pdf,.docx,.doc">
                  </div>
                </div>

              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" onclick="closeModal('modal-milestone-<?= $milestoneId ?>')">Cancel</button>
              <button type="submit" class="btn btn-primary">
                <i data-lucide="save" width="14" height="14"></i> Save Changes
              </button>
            </div>
          </form>
        </div>
      </div>
      <?php endif; ?>

      <?php endforeach; ?>
    </div>
    <?php endif; ?>

  </div><!-- /page-body -->
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
