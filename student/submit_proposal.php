<?php
/**
 * FYPTracker — Student: Submit / Resubmit Proposal
 * student/submit_proposal.php
 *
 * Handles both GET (render form) and POST (process submission).
 * Uses ProjectRepository + FileUploadService + ValidationService.
 *
 * Principles:
 *  - coding-standards: early returns, named constants, descriptive vars
 *  - backend-patterns: service layer, centralized error handling
 */

define('BASE_URL', '..');
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/repositories/ProjectRepository.php';
require_once __DIR__ . '/../includes/services/FileUploadService.php';
require_once __DIR__ . '/../includes/services/ValidationService.php';

requireRole('student');

// ── Bootstrap services (backend-patterns: dependency injection style) ────
$db                = getDB();
$projectRepository = new ProjectRepository($db);
$fileUploadService = new FileUploadService(__DIR__ . '/../uploads');
$validator         = new ValidationService();

$studentId      = $_SESSION['user_id'];
$existingProject = $projectRepository->findProjectByStudentId($studentId);

// ── Guard: student already has an approved/active project ────────────────
$isLockedStatus = $existingProject && in_array(
    $existingProject['status'],
    [
        ProjectRepository::STATUS_APPROVED,
        ProjectRepository::STATUS_IN_PROGRESS,
        ProjectRepository::STATUS_COMPLETED,
    ],
    true
);

// ── POST: process form submission ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    // Early return if project is locked
    if ($isLockedStatus) {
        setFlash('error', 'Your project is already approved and cannot be edited.');
        redirect('dashboard.php');
    }

    $title       = post('title');
    $description = post('description');

    // Validate text fields
    if (!$validator->validateProposalForm($title, $description)) {
        setFlash('error', implode(' ', $validator->getErrors()));
        redirect('submit_proposal.php');
    }

    // Handle file upload (optional for initial submission)
    $uploadResult = $fileUploadService->handleUpload(
        $_FILES['chapter_file'] ?? [],
        'proposal',
        'student_' . $studentId
    );

    if (!$uploadResult['success']) {
        setFlash('error', $uploadResult['error']);
        redirect('submit_proposal.php');
    }

    // Persist — transaction wraps create/update + notification
    try {
        $db->beginTransaction();

        if ($existingProject) {
            // Resubmission after rejection
            $projectRepository->updateProjectProposal(
                $existingProject['project_id'],
                $title,
                $description,
                $uploadResult['path']
            );
            $projectId = $existingProject['project_id'];
            $logAction = 'proposal_resubmitted';
        } else {
            // Brand-new submission
            $projectId = $projectRepository->createProject(
                $studentId,
                $title,
                $description,
                $uploadResult['path']
            );
            $logAction = 'proposal_submitted';
        }

        // Notify supervisor if already assigned
        if (!empty($existingProject['supervisor_id'])) {
            createNotification(
                (int) $existingProject['supervisor_id'],
                "{$_SESSION['full_name']} has submitted a project proposal for review: \"{$title}\".",
                '../supervisor/review_proposals.php'
            );
        }

        // Notify admin if no supervisor assigned yet
        $adminStmt = $db->prepare(
            "SELECT user_id FROM users WHERE role = 'admin' LIMIT 1"
        );
        $adminStmt->execute();
        $admin = $adminStmt->fetch();

        if ($admin && empty($existingProject['supervisor_id'])) {
            createNotification(
                (int) $admin['user_id'],
                "New proposal submitted by {$_SESSION['full_name']}. No supervisor assigned yet.",
                '../admin/assign_supervisors.php'
            );
        }

        $db->commit();

        auditLog($logAction, 'projects', $projectId);
        setFlash('success', 'Your proposal has been submitted successfully and is awaiting review.');
        redirect('dashboard.php');

    } catch (PDOException $e) {
        $db->rollBack();
        error_log('Proposal submission error: ' . $e->getMessage());
        setFlash('error', 'Submission failed due to a server error. Please try again.');
        redirect('submit_proposal.php');
    }
}

// ── GET: determine page state ────────────────────────────────────────────
$pageMode  = 'new';       // 'new' | 'resubmit' | 'view'
$pageTitle = 'Submit Proposal';
$activeNav = 'proposal';

if ($existingProject) {
    if ($existingProject['status'] === ProjectRepository::STATUS_REJECTED) {
        $pageMode  = 'resubmit';
        $pageTitle = 'Resubmit Proposal';
    } else {
        $pageMode  = 'view';
        $pageTitle = 'My Proposal';
    }
}

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
          <div class="page-title"><?= e($pageTitle) ?></div>
          <p class="page-subtitle">
            <?php if ($pageMode === 'new'): ?>
              Fill in your project details and submit for supervisor review.
            <?php elseif ($pageMode === 'resubmit'): ?>
              Your proposal was rejected. Read the feedback below and resubmit.
            <?php else: ?>
              Your submitted proposal and its current review status.
            <?php endif; ?>
          </p>
        </div>
        <?php if ($pageMode === 'view' && !$isLockedStatus): ?>
        <a href="dashboard.php" class="btn btn-secondary btn-sm">
          <i data-lucide="arrow-left" width="14" height="14"></i> Back
        </a>
        <?php endif; ?>
      </div>
    </div>

    <?php if ($pageMode === 'resubmit' && $existingProject['rejection_reason']): ?>
    <!-- Rejection reason alert -->
    <div class="alert alert-error" style="margin-bottom:var(--sp-5);" data-auto-dismiss="0">
      <i data-lucide="alert-circle" width="16" height="16" class="alert-icon"></i>
      <div class="alert-content">
        <div class="alert-title">Rejection Reason from Supervisor</div>
        <?= e($existingProject['rejection_reason']) ?>
      </div>
    </div>
    <?php endif; ?>

    <?php if ($pageMode === 'view'): ?>
    <!-- ══ VIEW MODE — read-only display ══ -->
    <div class="card">
      <div class="card-header">
        <div>
          <div class="card-title">Proposal Details</div>
          <div class="card-subtitle">Submitted <?= formatDate($existingProject['submission_date']) ?></div>
        </div>
        <?php
        $statusMap = [
            'pending'     => 'pending',
            'approved'    => 'approved',
            'in_progress' => 'in-progress',
            'completed'   => 'completed',
        ];
        $badgeCls = $statusMap[$existingProject['status']] ?? 'not-started';
        ?>
        <span class="badge badge-<?= e($badgeCls) ?>">
          <?= e(ucwords(str_replace('_', ' ', $existingProject['status']))) ?>
        </span>
      </div>
      <div class="card-body">
        <div style="display:flex;flex-direction:column;gap:var(--sp-5);">

          <div>
            <p style="font-size:var(--text-xs);font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:var(--text-tertiary);margin-bottom:var(--sp-2);">Project Title</p>
            <p style="font-size:var(--text-lg);font-weight:600;color:var(--text-primary);letter-spacing:-0.01em;"><?= e($existingProject['title']) ?></p>
          </div>

          <div>
            <p style="font-size:var(--text-xs);font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:var(--text-tertiary);margin-bottom:var(--sp-2);">Description</p>
            <p style="font-size:var(--text-base);color:var(--text-secondary);line-height:1.7;white-space:pre-line;"><?= e($existingProject['description']) ?></p>
          </div>

          <div class="divider"></div>

          <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:var(--sp-4);">
            <div>
              <p style="font-size:var(--text-xs);font-weight:600;color:var(--text-tertiary);text-transform:uppercase;letter-spacing:0.08em;margin-bottom:4px;">Supervisor</p>
              <p style="font-size:var(--text-sm);font-weight:500;color:var(--text-primary);">
                <?= $existingProject['supervisor_name'] ? e($existingProject['supervisor_name']) : '<em style="color:var(--text-tertiary)">Not yet assigned</em>' ?>
              </p>
            </div>
            <div>
              <p style="font-size:var(--text-xs);font-weight:600;color:var(--text-tertiary);text-transform:uppercase;letter-spacing:0.08em;margin-bottom:4px;">Submission Date</p>
              <p style="font-size:var(--text-sm);font-weight:500;color:var(--text-primary);"><?= $existingProject['submission_date'] ? formatDate($existingProject['submission_date']) : '—' ?></p>
            </div>
            <?php if ($existingProject['approval_date']): ?>
            <div>
              <p style="font-size:var(--text-xs);font-weight:600;color:var(--text-tertiary);text-transform:uppercase;letter-spacing:0.08em;margin-bottom:4px;">Approval Date</p>
              <p style="font-size:var(--text-sm);font-weight:500;color:var(--color-success);"><?= formatDate($existingProject['approval_date']) ?></p>
            </div>
            <?php endif; ?>
            <?php if ($existingProject['chapter_file']): ?>
            <div>
              <p style="font-size:var(--text-xs);font-weight:600;color:var(--text-tertiary);text-transform:uppercase;letter-spacing:0.08em;margin-bottom:4px;">Attached File</p>
              <a href="<?= BASE_URL ?>/<?= e($existingProject['chapter_file']) ?>"
                 target="_blank"
                 class="btn btn-outline btn-sm"
                 style="width:fit-content;">
                <i data-lucide="paperclip" width="13" height="13"></i> View File
              </a>
            </div>
            <?php endif; ?>
          </div>

        </div>
      </div>
    </div>

    <?php else: ?>
    <!-- ══ FORM MODE — new or resubmit ══ -->
    <div class="card">
      <div class="card-header">
        <div class="card-title">
          <?= $pageMode === 'resubmit' ? 'Update Your Proposal' : 'New Project Proposal' ?>
        </div>
      </div>
      <div class="card-body">
        <form method="POST" action="submit_proposal.php" enctype="multipart/form-data" novalidate>
          <?= csrfField() ?>

          <div class="form-stack">

            <!-- Title -->
            <div class="form-group">
              <label class="form-label form-label-required" for="title">Project Title</label>
              <input
                class="form-control"
                type="text"
                id="title"
                name="title"
                placeholder="e.g. A Web-Based FYP Supervision System for FULafia"
                value="<?= $pageMode === 'resubmit' ? e($existingProject['title']) : '' ?>"
                maxlength="255"
                required>
              <span class="form-hint">Minimum 10 characters. Be specific and descriptive.</span>
            </div>

            <!-- Description -->
            <div class="form-group">
              <label class="form-label form-label-required" for="description">Project Description</label>
              <textarea
                class="form-control"
                id="description"
                name="description"
                placeholder="Describe your project — background, objectives, what problem it solves, and your proposed approach..."
                rows="7"
                maxlength="5000"
                required><?= $pageMode === 'resubmit' ? e($existingProject['description']) : '' ?></textarea>
              <span class="form-hint">Minimum 50 characters. Include your objectives, scope, and methodology.</span>
            </div>

            <!-- File upload -->
            <div class="form-group">
              <label class="form-label" for="chapter_file">
                Attach Document <span style="color:var(--text-tertiary);font-weight:400;font-size:var(--text-xs);text-transform:none;">(optional — PDF or DOCX, max 10MB)</span>
              </label>
              <div class="file-input-wrapper">
                <i data-lucide="upload-cloud" width="20" height="20" style="color:var(--text-tertiary);flex-shrink:0;"></i>
                <div>
                  <div class="file-input-label">Click to choose file or drag and drop</div>
                  <div class="file-input-name" id="file-name-display">No file selected</div>
                </div>
                <input
                  type="file"
                  id="chapter_file"
                  name="chapter_file"
                  accept=".pdf,.docx,.doc"
                  onchange="updateFileName(this)">
              </div>
            </div>

            <!-- Actions -->
            <div style="display:flex;gap:var(--sp-3);flex-wrap:wrap;padding-top:var(--sp-2);">
              <button type="submit" class="btn btn-primary">
                <i data-lucide="send" width="16" height="16"></i>
                <?= $pageMode === 'resubmit' ? 'Resubmit Proposal' : 'Submit Proposal' ?>
              </button>
              <a href="dashboard.php" class="btn btn-secondary">Cancel</a>
            </div>

          </div>
        </form>
      </div>
    </div>

    <!-- Guidelines card -->
    <div class="card" style="margin-top:var(--sp-4);">
      <div class="card-header">
        <div class="card-title">Proposal Guidelines</div>
      </div>
      <div class="card-body">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:var(--sp-4);">
          <?php
          $guidelines = [
              ['icon' => 'check-circle-2', 'title' => 'Clear Objectives',      'desc' => 'State specific, measurable, achievable, relevant, and time-bound (SMART) objectives.'],
              ['icon' => 'check-circle-2', 'title' => 'Defined Scope',          'desc' => 'Clearly state what the project covers and what it does not.'],
              ['icon' => 'check-circle-2', 'title' => 'Feasibility',            'desc' => 'Ensure the project is completable within the academic session.'],
              ['icon' => 'check-circle-2', 'title' => 'Original Work',          'desc' => 'The project must be your own original contribution to the field.'],
          ];
          foreach ($guidelines as $g):
          ?>
          <div style="display:flex;align-items:flex-start;gap:var(--sp-3);">
            <i data-lucide="<?= $g['icon'] ?>" width="16" height="16" style="color:var(--accent-500);flex-shrink:0;margin-top:2px;"></i>
            <div>
              <p style="font-size:var(--text-sm);font-weight:600;color:var(--text-primary);margin-bottom:2px;"><?= e($g['title']) ?></p>
              <p style="font-size:var(--text-xs);color:var(--text-tertiary);line-height:1.5;"><?= e($g['desc']) ?></p>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <?php endif; ?>

  </div><!-- /page-body -->
</div>

<script>
function updateFileName(input) {
  const display = document.getElementById('file-name-display');
  display.textContent = input.files.length ? input.files[0].name : 'No file selected';
  display.style.color = input.files.length ? 'var(--accent-500)' : '';
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
