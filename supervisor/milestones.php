<?php
/**
 * FYPTracker — Supervisor: Milestones
 * supervisor/milestones.php
 *
 * Supervisor can:
 *   - Select a student (via ?student= query param)
 *   - View that student's milestone progress
 *   - Create new milestones for the student's project
 *   - Mark milestones in_progress / completed
 *   - Add feedback directly on this page
 *
 * Principles:
 *  - coding-standards: early returns, named constants, DRY view helpers
 *  - backend-patterns: repository pattern, N+1 prevention
 */

define('BASE_URL', '..');
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/repositories/ProjectRepository.php';
require_once __DIR__ . '/../includes/repositories/SupervisorRepository.php';
require_once __DIR__ . '/../includes/services/ValidationService.php';

requireRole('supervisor');

$db                   = getDB();
$projectRepository    = new ProjectRepository($db);
$supervisorRepository = new SupervisorRepository($db);
$validator            = new ValidationService();

$supervisorId = $_SESSION['user_id'];

// ── Named constants ──────────────────────────────────────────────────────
const ACTION_CREATE_MILESTONE   = 'create_milestone';
const ACTION_UPDATE_MILESTONE   = 'update_milestone';
const ACTION_CREATE_FEEDBACK    = 'create_feedback';

// ── POST handler ─────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $action    = post('action');
    $projectId = (int) post('project_id');

    // Authorization: verify supervisor owns this project
    $project = $projectRepository->findProjectById($projectId);
    if (!$project || (int) $project['supervisor_id'] !== $supervisorId) {
        setFlash('error', 'Access denied or project not found.');
        redirect('milestones.php');
    }

    $studentId = (int) $project['student_id'];

    // ── Create milestone ─────────────────────────────────────────────────
    if ($action === ACTION_CREATE_MILESTONE) {
        $title       = post('title');
        $description = post('description');
        $dueDate     = post('due_date');

        if (!$validator->validateMilestoneForm($title, $description, $dueDate)) {
            setFlash('error', implode(' ', $validator->getErrors()));
            redirect("milestones.php?student={$studentId}");
        }

        $milestoneId = $projectRepository->createMilestone(
            $projectId,
            $title,
            $description,
            $dueDate,
            $supervisorId
        );

        createNotification(
            $studentId,
            "{$_SESSION['full_name']} has added a new milestone: \"{$title}\" (due " . formatDate($dueDate) . ').',
            '../student/milestones.php'
        );

        auditLog('milestone_created', 'milestones', $milestoneId);
        setFlash('success', "Milestone \"{$title}\" created successfully.");
        redirect("milestones.php?student={$studentId}");
    }

    // ── Update milestone status ──────────────────────────────────────────
    if ($action === ACTION_UPDATE_MILESTONE) {
        $milestoneId = (int) post('milestone_id');
        $newStatus   = post('completion_status');

        $allowedStatuses = [
            ProjectRepository::MILESTONE_NOT_STARTED,
            ProjectRepository::MILESTONE_IN_PROGRESS,
            ProjectRepository::MILESTONE_COMPLETED,
        ];

        if (!in_array($newStatus, $allowedStatuses, true)) {
            setFlash('error', 'Invalid milestone status.');
            redirect("milestones.php?student={$studentId}");
        }

        $projectRepository->updateMilestoneStatus($milestoneId, $newStatus);

        auditLog('milestone_updated', 'milestones', $milestoneId);
        setFlash('success', 'Milestone status updated.');
        redirect("milestones.php?student={$studentId}");
    }

    // ── Create feedback ──────────────────────────────────────────────────
    if ($action === ACTION_CREATE_FEEDBACK) {
        $milestoneId = (int) post('milestone_id');
        $comment     = post('comment');
        $rating      = post('rating') ?: null;

        if (!$validator->validateFeedbackForm($comment, $rating)) {
            setFlash('error', implode(' ', $validator->getErrors()));
            redirect("milestones.php?student={$studentId}");
        }

        $feedbackId = $projectRepository->createFeedback(
            $milestoneId,
            $supervisorId,
            $comment,
            $rating !== null ? (int) $rating : null
        );

        // Get milestone title for notification
        $milestones    = $projectRepository->findMilestonesByProjectId($projectId);
        $milestoneMap  = array_column($milestones, 'title', 'milestone_id');
        $milestoneTitle = $milestoneMap[$milestoneId] ?? 'a milestone';

        createNotification(
            $studentId,
            "{$_SESSION['full_name']} left feedback on \"{$milestoneTitle}\".",
            '../student/milestones.php'
        );

        auditLog('feedback_created', 'feedback', $feedbackId);
        setFlash('success', 'Feedback submitted successfully.');
        redirect("milestones.php?student={$studentId}");
    }

    setFlash('error', 'Unknown action.');
    redirect('milestones.php');
}

// ── GET: load student list and selected student's data ───────────────────
$assignedStudents = $supervisorRepository->findAssignedStudentsWithProgress($supervisorId);

// Determine selected student
$selectedStudentId = (int) ($_GET['student'] ?? 0);

// Default to first student if none specified
if ($selectedStudentId === 0 && !empty($assignedStudents)) {
    $selectedStudentId = (int) $assignedStudents[0]['user_id'];
}

$selectedStudent = null;
$selectedProject = null;
$milestones      = [];
$allFeedback     = [];
$milestoneStats  = ['total' => 0, 'completed' => 0, 'in_progress' => 0, 'not_started' => 0];
$feedbackByMilestone = [];

if ($selectedStudentId > 0) {
    // Find the student in assigned list — no extra query needed
    foreach ($assignedStudents as $student) {
        if ((int) $student['user_id'] === $selectedStudentId) {
            $selectedStudent = $student;
            break;
        }
    }

    if ($selectedStudent) {
        $selectedProject = $projectRepository->findProjectByStudentId($selectedStudentId);

        if ($selectedProject) {
            $milestones     = $projectRepository->findMilestonesByProjectId((int) $selectedProject['project_id']);
            $milestoneStats = $projectRepository->countMilestoneStatsByProjectId((int) $selectedProject['project_id']);
            $allFeedback    = $projectRepository->findFeedbackByProjectId((int) $selectedProject['project_id']);

            // Group feedback by milestone — single pass O(n)
            foreach ($allFeedback as $fb) {
                $feedbackByMilestone[(int) $fb['milestone_id']][] = $fb;
            }
        }
    }
}

$progressPct = $milestoneStats['total'] > 0
    ? (int) round($milestoneStats['completed'] / $milestoneStats['total'] * 100)
    : 0;

$pageTitle = 'Milestones';
$activeNav = 'milestones';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="main-content" id="main-content">
  <?php require_once __DIR__ . '/../includes/topbar.php'; ?>

  <div class="page-body">
    <?php renderFlash(); ?>

    <div class="page-header">
      <div class="page-header-row">
        <div>
          <div class="page-title">Milestones</div>
          <p class="page-subtitle">Create and manage chapter milestones for your students.</p>
        </div>
      </div>
    </div>

    <?php if (empty($assignedStudents)): ?>
    <div class="card">
      <div class="card-body">
        <div class="empty-state">
          <div class="empty-icon"><i data-lucide="users" width="24" height="24"></i></div>
          <div class="empty-title">No students assigned</div>
          <p class="empty-desc">You have no students assigned yet. The FYP Coordinator will assign them.</p>
        </div>
      </div>
    </div>

    <?php else: ?>

    <div style="display:grid;grid-template-columns:260px 1fr;gap:var(--sp-4);align-items:start;">

      <!-- Student selector sidebar -->
      <div class="card" style="position:sticky;top:calc(var(--topbar-h) + var(--sp-4));">
        <div class="card-header">
          <div class="card-title">Students</div>
        </div>
        <div style="padding:var(--sp-2);">
          <?php foreach ($assignedStudents as $student):
            $isSelected   = (int) $student['user_id'] === $selectedStudentId;
            $studentPct   = $student['total_milestones'] > 0
                ? (int) round($student['completed_milestones'] / $student['total_milestones'] * 100)
                : 0;
            $statusMap = [
                'pending'     => 'pending',
                'approved'    => 'approved',
                'in_progress' => 'in-progress',
                'completed'   => 'completed',
                'rejected'    => 'rejected',
            ];
            $badgeCls = $statusMap[$student['project_status'] ?? 'pending'] ?? 'not-started';
          ?>
          <a
            href="milestones.php?student=<?= (int) $student['user_id'] ?>"
            style="display:flex;flex-direction:column;gap:var(--sp-1);padding:var(--sp-3);border-radius:var(--radius-md);text-decoration:none;margin-bottom:2px;background:<?= $isSelected ? 'rgba(16,185,129,0.1)' : 'transparent' ?>;border:1px solid <?= $isSelected ? 'rgba(16,185,129,0.25)' : 'transparent' ?>;transition:background var(--dur-fast);">
            <div style="display:flex;align-items:center;justify-content:space-between;">
              <span style="font-size:var(--text-sm);font-weight:<?= $isSelected ? '600' : '500' ?>;color:<?= $isSelected ? 'var(--accent-400)' : 'var(--text-secondary)' ?>;">
                <?= e($student['student_name']) ?>
              </span>
              <span class="badge badge-<?= $badgeCls ?>" style="font-size:0.6rem;padding:2px 6px;">
                <?= ucwords(str_replace('_', ' ', $student['project_status'] ?? 'pending')) ?>
              </span>
            </div>
            <div style="display:flex;align-items:center;gap:var(--sp-2);">
              <div class="progress" style="flex:1;height:4px;">
                <div class="progress-bar" style="width:<?= $studentPct ?>%;"></div>
              </div>
              <span style="font-size:0.65rem;color:var(--text-tertiary);font-variant-numeric:tabular-nums;"><?= $studentPct ?>%</span>
            </div>
          </a>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Milestone detail panel -->
      <div>
        <?php if (!$selectedStudent || !$selectedProject): ?>
        <div class="card">
          <div class="card-body">
            <div class="empty-state">
              <div class="empty-icon"><i data-lucide="target" width="24" height="24"></i></div>
              <div class="empty-title">Select a student</div>
            </div>
          </div>
        </div>

        <?php else: ?>

        <!-- Student + project header -->
        <div class="card" style="margin-bottom:var(--sp-4);">
          <div style="padding:var(--sp-5);display:flex;align-items:center;justify-content:space-between;gap:var(--sp-4);flex-wrap:wrap;">
            <div>
              <p style="font-size:var(--text-lg);font-weight:700;color:var(--text-primary);letter-spacing:-0.01em;margin-bottom:var(--sp-1);">
                <?= e($selectedStudent['student_name']) ?>
              </p>
              <p style="font-size:var(--text-sm);color:var(--text-tertiary);max-width:400px;"><?= e($selectedProject['title']) ?></p>
            </div>
            <?php if (in_array($selectedProject['status'], [ProjectRepository::STATUS_APPROVED, ProjectRepository::STATUS_IN_PROGRESS], true)): ?>
            <button class="btn btn-primary btn-sm" onclick="openModal('modal-create-milestone')">
              <i data-lucide="plus" width="14" height="14"></i>
              Add Milestone
            </button>
            <?php endif; ?>
          </div>

          <!-- Progress bar -->
          <div style="padding:0 var(--sp-5) var(--sp-4);">
            <div style="display:flex;justify-content:space-between;margin-bottom:var(--sp-2);">
              <span style="font-size:var(--text-xs);font-weight:600;color:var(--text-tertiary);text-transform:uppercase;letter-spacing:0.08em;">Overall Progress</span>
              <span style="font-size:var(--text-xs);font-weight:700;color:var(--accent-400);font-variant-numeric:tabular-nums;"><?= $progressPct ?>% · <?= $milestoneStats['completed'] ?>/<?= $milestoneStats['total'] ?> done</span>
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
              <p class="empty-desc">Add chapter milestones to guide <?= e(explode(' ', $selectedStudent['student_name'])[0]) ?>'s progress.</p>
            </div>
          </div>
        </div>

        <?php else: ?>
        <div style="display:flex;flex-direction:column;gap:var(--sp-3);">
          <?php foreach ($milestones as $index => $milestone):
            $milestoneId  = (int) $milestone['milestone_id'];
            $milestoneFb  = $feedbackByMilestone[$milestoneId] ?? [];
            $isCompleted  = $milestone['completion_status'] === ProjectRepository::MILESTONE_COMPLETED;
            $statusMap2   = [
                'completed'   => ['badge' => 'completed',   'color' => 'var(--color-success)'],
                'in_progress' => ['badge' => 'in-progress', 'color' => 'var(--color-info)'],
                'not_started' => ['badge' => 'not-started', 'color' => 'var(--text-tertiary)'],
            ];
            $cfg = $statusMap2[$milestone['completion_status']] ?? $statusMap2['not_started'];
            $daysRemaining = (int) ceil((strtotime($milestone['due_date']) - time()) / 86400);
          ?>
          <div class="card">
            <!-- Milestone header -->
            <div style="padding:var(--sp-4) var(--sp-5);border-bottom:1px solid var(--surface-divider);">
              <div style="display:flex;align-items:flex-start;gap:var(--sp-3);">
                <div style="width:28px;height:28px;border-radius:50%;background:<?= $isCompleted ? 'var(--accent-600)' : 'var(--surface-overlay)' ?>;border:2px solid <?= $isCompleted ? 'var(--accent-600)' : 'var(--surface-border)' ?>;display:flex;align-items:center;justify-content:center;flex-shrink:0;margin-top:2px;">
                  <?php if ($isCompleted): ?>
                  <i data-lucide="check" width="12" height="12" style="color:#fff;"></i>
                  <?php else: ?>
                  <span style="font-size:0.65rem;font-weight:700;color:var(--text-tertiary);"><?= $index + 1 ?></span>
                  <?php endif; ?>
                </div>
                <div style="flex:1;min-width:0;">
                  <div style="display:flex;align-items:center;gap:var(--sp-3);flex-wrap:wrap;margin-bottom:var(--sp-2);">
                    <h3 style="font-size:var(--text-md);font-weight:600;color:var(--text-primary);"><?= e($milestone['title']) ?></h3>
                    <span class="badge badge-<?= $cfg['badge'] ?>"><?= ucwords(str_replace('_', ' ', $milestone['completion_status'])) ?></span>
                  </div>
                  <?php if ($milestone['description']): ?>
                  <p style="font-size:var(--text-sm);color:var(--text-tertiary);margin-bottom:var(--sp-2);line-height:1.5;"><?= e($milestone['description']) ?></p>
                  <?php endif; ?>
                  <div style="display:flex;gap:var(--sp-4);flex-wrap:wrap;">
                    <span style="font-size:var(--text-xs);color:var(--text-tertiary);display:flex;align-items:center;gap:var(--sp-1);">
                      <i data-lucide="calendar" width="11" height="11"></i>
                      Due <?= formatDate($milestone['due_date']) ?>
                    </span>
                    <?php if (!$isCompleted): ?>
                    <span style="font-size:var(--text-xs);font-weight:600;color:<?= $daysRemaining < 0 ? 'var(--color-error)' : ($daysRemaining <= 7 ? 'var(--color-warning)' : 'var(--text-tertiary)') ?>;display:flex;align-items:center;gap:var(--sp-1);">
                      <i data-lucide="clock" width="11" height="11"></i>
                      <?= $daysRemaining < 0 ? abs($daysRemaining) . 'd overdue' : $daysRemaining . 'd remaining' ?>
                    </span>
                    <?php endif; ?>
                    <?php if ($milestone['submission_file']): ?>
                    <a href="<?= BASE_URL ?>/<?= e($milestone['submission_file']) ?>" target="_blank"
                       style="font-size:var(--text-xs);color:var(--accent-500);display:flex;align-items:center;gap:var(--sp-1);">
                      <i data-lucide="paperclip" width="11" height="11"></i> Student file
                    </a>
                    <?php endif; ?>
                  </div>
                </div>

                <!-- Supervisor actions -->
                <div style="display:flex;gap:var(--sp-2);flex-shrink:0;">
                  <button
                    class="btn btn-secondary btn-sm"
                    onclick="openModal('modal-update-milestone-<?= $milestoneId ?>')">
                    <i data-lucide="edit-2" width="13" height="13"></i>
                  </button>
                  <button
                    class="btn btn-outline btn-sm"
                    onclick="openModal('modal-feedback-<?= $milestoneId ?>')">
                    <i data-lucide="message-square" width="13" height="13"></i>
                    Feedback
                  </button>
                </div>
              </div>
            </div>

            <!-- Existing feedback -->
            <?php if (!empty($milestoneFb)): ?>
            <div style="padding:var(--sp-3) var(--sp-5);">
              <p style="font-size:0.65rem;font-weight:700;text-transform:uppercase;letter-spacing:0.1em;color:var(--text-tertiary);margin-bottom:var(--sp-2);">Your Feedback</p>
              <div style="display:flex;flex-direction:column;gap:var(--sp-2);">
                <?php foreach ($milestoneFb as $fb): ?>
                <div style="padding:var(--sp-3) var(--sp-4);background:var(--surface-overlay);border-radius:var(--radius-sm);border-left:2px solid var(--accent-600);">
                  <div style="display:flex;justify-content:space-between;margin-bottom:var(--sp-1);">
                    <span style="font-size:var(--text-xs);color:var(--text-tertiary);"><?= timeAgo($fb['created_at']) ?></span>
                    <?php if ($fb['rating']): ?>
                    <span style="font-size:var(--text-xs);color:var(--color-warning);"><?= str_repeat('★', (int)$fb['rating']) ?></span>
                    <?php endif; ?>
                  </div>
                  <p style="font-size:var(--text-sm);color:var(--text-secondary);line-height:1.55;"><?= e($fb['comment']) ?></p>
                </div>
                <?php endforeach; ?>
              </div>
            </div>
            <?php endif; ?>
          </div>

          <!-- Update status modal -->
          <div class="modal-backdrop" id="modal-update-milestone-<?= $milestoneId ?>">
            <div class="modal">
              <div class="modal-header">
                <div class="modal-title">Update Status</div>
                <button class="modal-close" onclick="closeModal('modal-update-milestone-<?= $milestoneId ?>')" aria-label="Close">
                  <i data-lucide="x" width="16" height="16"></i>
                </button>
              </div>
              <form method="POST" action="milestones.php">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="<?= ACTION_UPDATE_MILESTONE ?>">
                <input type="hidden" name="project_id" value="<?= (int) $selectedProject['project_id'] ?>">
                <input type="hidden" name="milestone_id" value="<?= $milestoneId ?>">
                <div class="modal-body">
                  <div class="form-group">
                    <label class="form-label" for="ms-status-<?= $milestoneId ?>">Status for: <?= e($milestone['title']) ?></label>
                    <select class="form-control" id="ms-status-<?= $milestoneId ?>" name="completion_status">
                      <option value="<?= ProjectRepository::MILESTONE_NOT_STARTED ?>" <?= $milestone['completion_status'] === ProjectRepository::MILESTONE_NOT_STARTED ? 'selected' : '' ?>>Not Started</option>
                      <option value="<?= ProjectRepository::MILESTONE_IN_PROGRESS ?>" <?= $milestone['completion_status'] === ProjectRepository::MILESTONE_IN_PROGRESS ? 'selected' : '' ?>>In Progress</option>
                      <option value="<?= ProjectRepository::MILESTONE_COMPLETED ?>" <?= $isCompleted ? 'selected' : '' ?>>Completed</option>
                    </select>
                  </div>
                </div>
                <div class="modal-footer">
                  <button type="button" class="btn btn-secondary" onclick="closeModal('modal-update-milestone-<?= $milestoneId ?>')">Cancel</button>
                  <button type="submit" class="btn btn-primary"><i data-lucide="save" width="14" height="14"></i> Save</button>
                </div>
              </form>
            </div>
          </div>

          <!-- Feedback modal -->
          <div class="modal-backdrop" id="modal-feedback-<?= $milestoneId ?>">
            <div class="modal">
              <div class="modal-header">
                <div class="modal-title">Add Feedback</div>
                <button class="modal-close" onclick="closeModal('modal-feedback-<?= $milestoneId ?>')" aria-label="Close">
                  <i data-lucide="x" width="16" height="16"></i>
                </button>
              </div>
              <form method="POST" action="milestones.php">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="<?= ACTION_CREATE_FEEDBACK ?>">
                <input type="hidden" name="project_id" value="<?= (int) $selectedProject['project_id'] ?>">
                <input type="hidden" name="milestone_id" value="<?= $milestoneId ?>">
                <div class="modal-body">
                  <div class="form-stack">
                    <div class="form-group">
                      <label class="form-label form-label-required" for="comment-<?= $milestoneId ?>">
                        Feedback on: <?= e($milestone['title']) ?>
                      </label>
                      <textarea
                        class="form-control"
                        id="comment-<?= $milestoneId ?>"
                        name="comment"
                        rows="5"
                        placeholder="Provide constructive feedback on the student's work..."
                        minlength="10"
                        maxlength="3000"
                        required></textarea>
                    </div>
                    <div class="form-group">
                      <label class="form-label" for="rating-<?= $milestoneId ?>">Rating (optional)</label>
                      <select class="form-control" id="rating-<?= $milestoneId ?>" name="rating">
                        <option value="">— No rating —</option>
                        <option value="5">★★★★★ Excellent</option>
                        <option value="4">★★★★☆ Good</option>
                        <option value="3">★★★☆☆ Satisfactory</option>
                        <option value="2">★★☆☆☆ Needs Improvement</option>
                        <option value="1">★☆☆☆☆ Poor</option>
                      </select>
                    </div>
                  </div>
                </div>
                <div class="modal-footer">
                  <button type="button" class="btn btn-secondary" onclick="closeModal('modal-feedback-<?= $milestoneId ?>')">Cancel</button>
                  <button type="submit" class="btn btn-primary">
                    <i data-lucide="send" width="14" height="14"></i> Submit Feedback
                  </button>
                </div>
              </form>
            </div>
          </div>

          <?php endforeach; ?>
        </div>
        <?php endif; // end empty milestones check ?>
        <?php endif; // end selectedProject check ?>
      </div><!-- /detail panel -->
    </div><!-- /grid -->
    <?php endif; // end empty assignedStudents check ?>

  </div><!-- /page-body -->
</div>

<!-- Create milestone modal (global for page) -->
<?php if ($selectedProject): ?>
<div class="modal-backdrop" id="modal-create-milestone">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title">Add Milestone</div>
      <button class="modal-close" onclick="closeModal('modal-create-milestone')" aria-label="Close">
        <i data-lucide="x" width="16" height="16"></i>
      </button>
    </div>
    <form method="POST" action="milestones.php">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="<?= ACTION_CREATE_MILESTONE ?>">
      <input type="hidden" name="project_id" value="<?= (int) $selectedProject['project_id'] ?>">
      <div class="modal-body">
        <div class="form-stack">
          <div class="form-group">
            <label class="form-label form-label-required" for="ms-title">Milestone Title</label>
            <input
              class="form-control"
              type="text"
              id="ms-title"
              name="title"
              placeholder="e.g. Chapter 1 — Introduction"
              maxlength="255"
              required>
          </div>
          <div class="form-group">
            <label class="form-label form-label-required" for="ms-desc">Description</label>
            <textarea
              class="form-control"
              id="ms-desc"
              name="description"
              rows="4"
              placeholder="What should the student submit or complete for this milestone?"
              maxlength="2000"
              required></textarea>
          </div>
          <div class="form-group">
            <label class="form-label form-label-required" for="ms-due">Due Date</label>
            <input
              class="form-control"
              type="date"
              id="ms-due"
              name="due_date"
              min="<?= date('Y-m-d') ?>"
              required>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modal-create-milestone')">Cancel</button>
        <button type="submit" class="btn btn-primary">
          <i data-lucide="plus" width="14" height="14"></i> Create Milestone
        </button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
