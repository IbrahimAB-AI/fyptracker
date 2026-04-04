<?php
/**
 * FYPTracker — Supervisor: Feedback Hub
 * supervisor/feedback.php
 *
 * Aggregated view of all feedback given by this supervisor.
 * Also allows adding feedback on any milestone from this page.
 *
 * Principles:
 *  - coding-standards: early returns, named constants, descriptive vars
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

// ── POST: add feedback from this page ────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $milestoneId = (int) post('milestone_id');
    $projectId   = (int) post('project_id');
    $comment     = post('comment');
    $rating      = post('rating') ?: null;

    // Authorization: verify project belongs to this supervisor
    $project = $projectRepository->findProjectById($projectId);
    if (!$project || (int) $project['supervisor_id'] !== $supervisorId) {
        setFlash('error', 'Access denied.');
        redirect('feedback.php');
    }

    if (!$validator->validateFeedbackForm($comment, $rating)) {
        setFlash('error', implode(' ', $validator->getErrors()));
        redirect('feedback.php');
    }

    $feedbackId = $projectRepository->createFeedback(
        $milestoneId,
        $supervisorId,
        $comment,
        $rating !== null ? (int) $rating : null
    );

    createNotification(
        (int) $project['student_id'],
        "{$_SESSION['full_name']} has added feedback on a milestone.",
        '../student/milestones.php'
    );

    auditLog('feedback_created', 'feedback', $feedbackId);
    setFlash('success', 'Feedback submitted successfully.');
    redirect('feedback.php');
}

// ── GET: load all feedback given by this supervisor ───────────────────────
// Single query — N+1 prevention: joins feedback + milestones + users
$stmt = $db->prepare(
    'SELECT f.*,
            m.title        AS milestone_title,
            m.project_id,
            p.title        AS project_title,
            u.full_name    AS student_name,
            st.matric_number
       FROM feedback f
       JOIN milestones m  ON m.milestone_id  = f.milestone_id
       JOIN projects p    ON p.project_id    = m.project_id
       JOIN users u       ON u.user_id       = p.student_id
       JOIN students st   ON st.student_id   = p.student_id
      WHERE f.supervisor_id = ?
      ORDER BY f.created_at DESC'
);
$stmt->execute([$supervisorId]);
$allFeedback = $stmt->fetchAll();

// Load all assigned students + their milestones for the "add feedback" dropdown
$assignedStudents = $supervisorRepository->findAssignedStudentsWithProgress($supervisorId);

// Build milestones per project for the form — batch load to avoid N+1
$milestonesByProject = [];
foreach ($assignedStudents as $student) {
    if (!empty($student['project_id'])) {
        $milestonesByProject[$student['project_id']] = [
            'student_name' => $student['student_name'],
            'project_title' => $student['project_title'],
            'milestones'   => $projectRepository->findMilestonesByProjectId((int) $student['project_id']),
        ];
    }
}

// Summary stats — single pass over feedback array
$totalFeedback    = count($allFeedback);
$ratedFeedback    = array_filter($allFeedback, fn($f) => $f['rating'] !== null);
$averageRating    = count($ratedFeedback) > 0
    ? round(array_sum(array_column($ratedFeedback, 'rating')) / count($ratedFeedback), 1)
    : null;

$pageTitle = 'Feedback';
$activeNav = 'feedback';
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
          <div class="page-title">Feedback Hub</div>
          <p class="page-subtitle">All feedback you have given across your students.</p>
        </div>
        <?php if (!empty($milestonesByProject)): ?>
        <button class="btn btn-primary" onclick="openModal('modal-add-feedback')">
          <i data-lucide="message-square-plus" width="16" height="16"></i>
          Add Feedback
        </button>
        <?php endif; ?>
      </div>
    </div>

    <!-- Summary stats -->
    <div class="stats-grid" style="grid-template-columns:repeat(3,1fr);margin-bottom:var(--sp-5);">
      <div class="stat-card">
        <div class="stat-icon-wrap"><i data-lucide="message-square" width="18" height="18"></i></div>
        <div class="stat-value tabular"><?= $totalFeedback ?></div>
        <div class="stat-label">Total Feedback Given</div>
      </div>
      <div class="stat-card">
        <div class="stat-icon-wrap"><i data-lucide="users" width="18" height="18"></i></div>
        <div class="stat-value tabular"><?= count($assignedStudents) ?></div>
        <div class="stat-label">Students Supervised</div>
      </div>
      <div class="stat-card">
        <div class="stat-icon-wrap"><i data-lucide="star" width="18" height="18"></i></div>
        <div class="stat-value tabular"><?= $averageRating !== null ? number_format($averageRating, 1) : '—' ?></div>
        <div class="stat-label">Average Rating Given</div>
      </div>
    </div>

    <!-- Feedback list -->
    <?php if (empty($allFeedback)): ?>
    <div class="card">
      <div class="card-body">
        <div class="empty-state">
          <div class="empty-icon"><i data-lucide="message-square" width="24" height="24"></i></div>
          <div class="empty-title">No feedback given yet</div>
          <p class="empty-desc">Use the milestones page or the button above to give feedback to your students.</p>
        </div>
      </div>
    </div>

    <?php else: ?>

    <!-- Group header by student for readability — group in a single PHP pass -->
    <?php
    $feedbackByStudent = [];
    foreach ($allFeedback as $fb) {
        $feedbackByStudent[$fb['student_name']][] = $fb;
    }
    ?>

    <div style="display:flex;flex-direction:column;gap:var(--sp-5);">
      <?php foreach ($feedbackByStudent as $studentName => $feedbackItems): ?>
      <div>
        <h2 style="font-size:var(--text-sm);font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:var(--text-tertiary);margin-bottom:var(--sp-3);display:flex;align-items:center;gap:var(--sp-2);">
          <i data-lucide="user" width="13" height="13"></i>
          <?= e($studentName) ?> · <?= count($feedbackItems) ?> feedback entr<?= count($feedbackItems) !== 1 ? 'ies' : 'y' ?>
        </h2>
        <div class="card">
          <div class="table-wrapper">
            <table>
              <thead>
                <tr>
                  <th>Milestone</th>
                  <th>Project</th>
                  <th>Rating</th>
                  <th>Comment</th>
                  <th>Date</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($feedbackItems as $fb): ?>
                <tr>
                  <td>
                    <span class="cell-primary"><?= e($fb['milestone_title']) ?></span>
                  </td>
                  <td style="max-width:160px;">
                    <span class="truncate" style="display:block;font-size:var(--text-xs);color:var(--text-tertiary);" title="<?= e($fb['project_title']) ?>">
                      <?= e(mb_strimwidth($fb['project_title'], 0, 40, '…')) ?>
                    </span>
                  </td>
                  <td>
                    <?php if ($fb['rating']): ?>
                    <span style="color:var(--color-warning);font-size:var(--text-sm);letter-spacing:-1px;">
                      <?= str_repeat('★', (int)$fb['rating']) ?><?= str_repeat('☆', 5 - (int)$fb['rating']) ?>
                    </span>
                    <?php else: ?>
                    <span style="color:var(--text-tertiary);font-size:var(--text-xs);">—</span>
                    <?php endif; ?>
                  </td>
                  <td style="max-width:300px;">
                    <span style="font-size:var(--text-sm);color:var(--text-secondary);line-height:1.5;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;">
                      <?= e($fb['comment']) ?>
                    </span>
                    <?php if (mb_strlen($fb['comment']) > 120): ?>
                    <button
                      class="btn btn-ghost btn-sm"
                      style="padding:2px 0;font-size:var(--text-xs);"
                      onclick="viewFullFeedback(<?= htmlspecialchars(json_encode($fb['comment']), ENT_QUOTES) ?>)">
                      Read more
                    </button>
                    <?php endif; ?>
                  </td>
                  <td class="cell-muted" style="white-space:nowrap;"><?= timeAgo($fb['created_at']) ?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

  </div><!-- /page-body -->
</div>

<!-- Add feedback modal -->
<div class="modal-backdrop" id="modal-add-feedback">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title">Add Feedback</div>
      <button class="modal-close" onclick="closeModal('modal-add-feedback')" aria-label="Close">
        <i data-lucide="x" width="16" height="16"></i>
      </button>
    </div>
    <form method="POST" action="feedback.php">
      <?= csrfField() ?>
      <div class="modal-body">
        <div class="form-stack">

          <!-- Student / project / milestone cascade -->
          <div class="form-group">
            <label class="form-label form-label-required" for="fb-project">Student & Project</label>
            <select
              class="form-control"
              id="fb-project"
              name="project_id"
              onchange="updateMilestoneDropdown(this)"
              required>
              <option value="">— Select student —</option>
              <?php foreach ($milestonesByProject as $pid => $data): ?>
              <option value="<?= (int) $pid ?>">
                <?= e($data['student_name']) ?> — <?= e(mb_strimwidth($data['project_title'], 0, 40, '…')) ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-group">
            <label class="form-label form-label-required" for="fb-milestone">Milestone</label>
            <select
              class="form-control"
              id="fb-milestone"
              name="milestone_id"
              required
              disabled>
              <option value="">— Select project first —</option>
            </select>
          </div>

          <div class="form-group">
            <label class="form-label form-label-required" for="fb-comment">Feedback</label>
            <textarea
              class="form-control"
              id="fb-comment"
              name="comment"
              rows="5"
              placeholder="Provide constructive, specific feedback..."
              minlength="10"
              maxlength="3000"
              required></textarea>
          </div>

          <div class="form-group">
            <label class="form-label" for="fb-rating">Rating (optional)</label>
            <select class="form-control" id="fb-rating" name="rating">
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
        <button type="button" class="btn btn-secondary" onclick="closeModal('modal-add-feedback')">Cancel</button>
        <button type="submit" class="btn btn-primary">
          <i data-lucide="send" width="14" height="14"></i> Submit Feedback
        </button>
      </div>
    </form>
  </div>
</div>

<!-- Read full feedback modal -->
<div class="modal-backdrop" id="modal-full-feedback">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title">Full Feedback</div>
      <button class="modal-close" onclick="closeModal('modal-full-feedback')" aria-label="Close">
        <i data-lucide="x" width="16" height="16"></i>
      </button>
    </div>
    <div class="modal-body">
      <p id="full-feedback-content" style="font-size:var(--text-sm);color:var(--text-secondary);line-height:1.7;white-space:pre-line;"></p>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn btn-secondary" onclick="closeModal('modal-full-feedback')">Close</button>
    </div>
  </div>
</div>

<script>
// Milestone data keyed by project_id (from PHP — no extra XHR needed)
const milestonesData = <?= json_encode(
    array_map(fn($d) => array_map(
        fn($m) => ['id' => $m['milestone_id'], 'title' => $m['title']],
        $d['milestones']
    ), $milestonesByProject),
    JSON_HEX_TAG
) ?>;

function updateMilestoneDropdown(projectSelect) {
  const milestoneSelect = document.getElementById('fb-milestone');
  const projectId       = projectSelect.value;

  milestoneSelect.innerHTML = '';
  milestoneSelect.disabled  = !projectId;

  if (!projectId) {
    milestoneSelect.innerHTML = '<option value="">— Select project first —</option>';
    return;
  }

  const milestones = milestonesData[projectId] || [];

  if (milestones.length === 0) {
    milestoneSelect.innerHTML = '<option value="">No milestones found</option>';
    return;
  }

  milestoneSelect.innerHTML = '<option value="">— Select milestone —</option>';
  milestones.forEach(m => {
    const opt   = document.createElement('option');
    opt.value   = m.id;
    opt.textContent = m.title;
    milestoneSelect.appendChild(opt);
  });
}

function viewFullFeedback(commentText) {
  document.getElementById('full-feedback-content').textContent = commentText;
  openModal('modal-full-feedback');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
