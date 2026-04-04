<?php
/**
 * FYPTracker — Admin: Assign Supervisors
 * admin/assign_supervisors.php
 *
 * Admin can:
 *   - View all students without a supervisor
 *   - Assign a supervisor to a student's project
 *   - Reassign supervisor on existing projects
 *   - View supervisor workload (capacity indicators)
 *
 * Principles:
 *  - coding-standards: early returns, named constants, descriptive vars
 *  - backend-patterns: repository pattern, N+1 prevention
 */

define('BASE_URL', '..');
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/repositories/AdminRepository.php';
require_once __DIR__ . '/../includes/repositories/SupervisorRepository.php';
require_once __DIR__ . '/../includes/repositories/ProjectRepository.php';

requireRole('admin');

$db                   = getDB();
$adminRepository      = new AdminRepository($db);
$supervisorRepository = new SupervisorRepository($db);
$projectRepository    = new ProjectRepository($db);

// ── Named constant ────────────────────────────────────────────────────────
const ACTION_ASSIGN_SUPERVISOR = 'assign_supervisor';

// ── POST: assign supervisor ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $action       = post('action');
    $projectId    = (int) post('project_id');
    $supervisorId = (int) post('supervisor_id');

    if ($action !== ACTION_ASSIGN_SUPERVISOR) {
        setFlash('error', 'Invalid action.');
        redirect('assign_supervisors.php');
    }

    if ($projectId === 0 || $supervisorId === 0) {
        setFlash('error', 'Please select both a student project and a supervisor.');
        redirect('assign_supervisors.php');
    }

    // Verify project exists
    $project = $projectRepository->findProjectById($projectId);
    if (!$project) {
        setFlash('error', 'Project not found.');
        redirect('assign_supervisors.php');
    }

    // Verify supervisor exists and has capacity
    $supervisors     = $supervisorRepository->findAllSupervisorsWithWorkload();
    $selectedSupervisor = null;

    foreach ($supervisors as $sv) {
        if ((int) $sv['user_id'] === $supervisorId) {
            $selectedSupervisor = $sv;
            break;
        }
    }

    if (!$selectedSupervisor) {
        setFlash('error', 'Supervisor not found.');
        redirect('assign_supervisors.php');
    }

    if ((int) $selectedSupervisor['available_slots'] <= 0
        && (int) $project['supervisor_id'] !== $supervisorId) {
        setFlash('error', "{$selectedSupervisor['full_name']} has no available slots. Increase their max students or choose another supervisor.");
        redirect('assign_supervisors.php');
    }

    $adminRepository->assignSupervisorToProject($projectId, $supervisorId);

    // Notify student
    createNotification(
        (int) $project['student_id'],
        "{$selectedSupervisor['full_name']} has been assigned as your FYP supervisor.",
        '../student/dashboard.php'
    );

    // Notify supervisor
    createNotification(
        $supervisorId,
        "You have been assigned to supervise {$project['student_name']}'s project: \"{$project['title']}\".",
        '../supervisor/review_proposals.php'
    );

    auditLog('supervisor_assigned', 'projects', $projectId);
    setFlash('success', "Supervisor assigned successfully. Both the student and supervisor have been notified.");
    redirect('assign_supervisors.php');
}

// ── GET: load data ────────────────────────────────────────────────────────
$unassignedStudents  = $adminRepository->findUnassignedStudents();
$assignedStudents    = $adminRepository->findAssignedStudentsOverview();
$supervisorWorkloads = $supervisorRepository->findAllSupervisorsWithWorkload();

// ── View helpers ──────────────────────────────────────────────────────────

// Capacity colour based on available slots
function capacityColor(int $available, int $max): string
{
    if ($available <= 0)              return 'var(--color-error)';
    if ($available / $max <= 0.25)   return 'var(--color-warning)';
    return 'var(--color-success)';
}

function capacityLabel(int $available, int $max): string
{
    if ($available <= 0)   return 'Full';
    return "{$available}/{$max} slots";
}

$pageTitle = 'Assign Supervisors';
$activeNav = 'assign';
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
          <div class="page-title">Assign Supervisors</div>
          <p class="page-subtitle">
            <?= count($unassignedStudents) ?> student<?= count($unassignedStudents) !== 1 ? 's' : '' ?> awaiting supervisor assignment.
          </p>
        </div>
      </div>
    </div>

    <!-- Supervisor capacity overview -->
    <div style="margin-bottom:var(--sp-5);">
      <h2 style="font-size:var(--text-sm);font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:var(--text-tertiary);margin-bottom:var(--sp-3);display:flex;align-items:center;gap:var(--sp-2);">
        <i data-lucide="users" width="14" height="14"></i>
        Supervisor Capacity
      </h2>
      <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:var(--sp-3);">
        <?php foreach ($supervisorWorkloads as $sv):
          $available   = (int) $sv['available_slots'];
          $maxStudents = (int) $sv['max_students'];
          $assigned    = (int) $sv['assigned_students'];
          $pct         = $maxStudents > 0 ? (int) round($assigned / $maxStudents * 100) : 0;
          $capColor    = capacityColor($available, $maxStudents);
          $barCls      = $available <= 0 ? 'progress-bar-error' : ($available / $maxStudents <= 0.25 ? 'progress-bar-warning' : '');
        ?>
        <div class="card" style="padding:var(--sp-4);">
          <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:var(--sp-2);">
            <p style="font-size:var(--text-sm);font-weight:600;color:var(--text-primary);line-height:1.3;">
              <?= e($sv['title'] ?? '') ?> <?= e($sv['full_name']) ?>
            </p>
            <span style="font-size:0.65rem;font-weight:700;color:<?= $capColor ?>;white-space:nowrap;padding:2px 7px;background:<?= $available <= 0 ? 'var(--color-error-subtle)' : 'rgba(16,185,129,0.1)' ?>;border-radius:var(--radius-full);">
              <?= capacityLabel($available, $maxStudents) ?>
            </span>
          </div>
          <?php if ($sv['specialisation']): ?>
          <p style="font-size:var(--text-xs);color:var(--text-tertiary);margin-bottom:var(--sp-2);"><?= e(mb_strimwidth($sv['specialisation'], 0, 40, '…')) ?></p>
          <?php endif; ?>
          <div class="progress" data-progress="<?= $pct ?>">
            <div class="progress-bar <?= $barCls ?>" style="width:0%;"></div>
          </div>
          <div style="display:flex;justify-content:space-between;margin-top:var(--sp-1);">
            <span style="font-size:0.65rem;color:var(--text-tertiary);"><?= $assigned ?> assigned</span>
            <span style="font-size:0.65rem;color:var(--text-tertiary);">max <?= $maxStudents ?></span>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Unassigned students -->
    <?php if (!empty($unassignedStudents)): ?>
    <div style="margin-bottom:var(--sp-5);">
      <h2 style="font-size:var(--text-sm);font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:var(--color-warning);margin-bottom:var(--sp-3);display:flex;align-items:center;gap:var(--sp-2);">
        <i data-lucide="alert-triangle" width="14" height="14"></i>
        Unassigned Students (<?= count($unassignedStudents) ?>)
      </h2>
      <div class="card">
        <div class="table-wrapper">
          <table>
            <thead>
              <tr>
                <th>Student</th>
                <th>Matric No.</th>
                <th>Project Title</th>
                <th>Submitted</th>
                <th>Assign Supervisor</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($unassignedStudents as $student): ?>
              <tr>
                <td class="cell-primary"><?= e($student['full_name']) ?></td>
                <td class="cell-muted font-mono"><?= e($student['matric_number']) ?></td>
                <td style="max-width:200px;">
                  <span class="truncate" style="display:block;font-size:var(--text-sm);" title="<?= e($student['project_title'] ?? '') ?>">
                    <?= $student['project_title'] ? e(mb_strimwidth($student['project_title'], 0, 45, '…')) : '<em style="color:var(--text-tertiary)">No proposal yet</em>' ?>
                  </span>
                </td>
                <td class="cell-muted"><?= $student['submission_date'] ? formatDate($student['submission_date']) : '—' ?></td>
                <td>
                  <?php if ($student['project_id']): ?>
                  <form method="POST" action="assign_supervisors.php" style="display:flex;gap:var(--sp-2);align-items:center;">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="<?= ACTION_ASSIGN_SUPERVISOR ?>">
                    <input type="hidden" name="project_id" value="<?= (int) $student['project_id'] ?>">
                    <select
                      class="form-control"
                      name="supervisor_id"
                      style="min-width:180px;padding:6px 10px;height:auto;"
                      required>
                      <option value="">— Select supervisor —</option>
                      <?php foreach ($supervisorWorkloads as $sv):
                        $isFull = (int) $sv['available_slots'] <= 0;
                      ?>
                      <option
                        value="<?= (int) $sv['user_id'] ?>"
                        <?= $isFull ? 'disabled' : '' ?>>
                        <?= e($sv['full_name']) ?> (<?= capacityLabel((int)$sv['available_slots'], (int)$sv['max_students']) ?>)
                      </option>
                      <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn-primary btn-sm">
                      <i data-lucide="link" width="13" height="13"></i> Assign
                    </button>
                  </form>
                  <?php else: ?>
                  <span class="cell-muted" style="font-size:var(--text-xs);">Awaiting proposal</span>
                  <?php endif; ?>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <!-- All assigned students -->
    <?php if (!empty($assignedStudents)): ?>
    <div>
      <h2 style="font-size:var(--text-sm);font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:var(--text-tertiary);margin-bottom:var(--sp-3);display:flex;align-items:center;gap:var(--sp-2);">
        <i data-lucide="check-circle-2" width="14" height="14"></i>
        Assigned (<?= count($assignedStudents) ?>)
      </h2>
      <div class="card">
        <div class="table-wrapper">
          <table>
            <thead>
              <tr>
                <th>Student</th>
                <th>Matric No.</th>
                <th>Supervisor</th>
                <th>Status</th>
                <th>Progress</th>
                <th>Reassign</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($assignedStudents as $student):
                $pct = $student['total_milestones'] > 0
                    ? (int) round($student['completed_milestones'] / $student['total_milestones'] * 100)
                    : 0;
                $statusMap = [
                    'pending'     => 'pending',
                    'approved'    => 'approved',
                    'in_progress' => 'in-progress',
                    'completed'   => 'completed',
                    'rejected'    => 'rejected',
                ];
                $badgeCls = $statusMap[$student['project_status']] ?? 'not-started';
              ?>
              <tr>
                <td class="cell-primary"><?= e($student['student_name']) ?></td>
                <td class="cell-muted font-mono"><?= e($student['matric_number']) ?></td>
                <td><?= e($student['supervisor_name']) ?></td>
                <td>
                  <span class="badge badge-<?= $badgeCls ?>">
                    <?= ucwords(str_replace('_', ' ', $student['project_status'])) ?>
                  </span>
                </td>
                <td style="min-width:100px;">
                  <div style="display:flex;align-items:center;gap:var(--sp-2);">
                    <div class="progress" style="flex:1;" data-progress="<?= $pct ?>">
                      <div class="progress-bar" style="width:0%;"></div>
                    </div>
                    <span style="font-size:var(--text-xs);color:var(--text-tertiary);font-variant-numeric:tabular-nums;"><?= $pct ?>%</span>
                  </div>
                </td>
                <td>
                  <button
                    class="btn btn-ghost btn-sm"
                    onclick="openReassignModal(<?= (int) $student['project_id'] ?>, <?= htmlspecialchars(json_encode($student['student_name']), ENT_QUOTES) ?>, <?= (int) $student['supervisor_id'] ?>)">
                    <i data-lucide="refresh-cw" width="13" height="13"></i> Reassign
                  </button>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
    <?php endif; ?>

  </div><!-- /page-body -->
</div>

<!-- Reassign modal -->
<div class="modal-backdrop" id="modal-reassign">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title">Reassign Supervisor</div>
      <button class="modal-close" onclick="closeModal('modal-reassign')" aria-label="Close">
        <i data-lucide="x" width="16" height="16"></i>
      </button>
    </div>
    <form method="POST" action="assign_supervisors.php">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="<?= ACTION_ASSIGN_SUPERVISOR ?>">
      <input type="hidden" name="project_id" id="reassign-project-id" value="">
      <div class="modal-body">
        <p id="reassign-student-name" style="font-size:var(--text-sm);font-weight:600;color:var(--text-primary);margin-bottom:var(--sp-4);"></p>
        <div class="form-group">
          <label class="form-label form-label-required" for="reassign-supervisor">New Supervisor</label>
          <select class="form-control" id="reassign-supervisor" name="supervisor_id" required>
            <option value="">— Select supervisor —</option>
            <?php foreach ($supervisorWorkloads as $sv):
              $isFull = (int) $sv['available_slots'] <= 0;
            ?>
            <option
              value="<?= (int) $sv['user_id'] ?>"
              data-id="<?= (int) $sv['user_id'] ?>"
              <?= $isFull ? 'disabled' : '' ?>>
              <?= e($sv['full_name']) ?> — <?= capacityLabel((int)$sv['available_slots'], (int)$sv['max_students']) ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modal-reassign')">Cancel</button>
        <button type="submit" class="btn btn-primary">
          <i data-lucide="link" width="14" height="14"></i> Confirm Reassignment
        </button>
      </div>
    </form>
  </div>
</div>

<script>
function openReassignModal(projectId, studentName, currentSupervisorId) {
  document.getElementById('reassign-project-id').value   = projectId;
  document.getElementById('reassign-student-name').textContent = 'Student: ' + studentName;

  // Pre-select current supervisor
  const select  = document.getElementById('reassign-supervisor');
  Array.from(select.options).forEach(opt => {
    opt.selected = parseInt(opt.dataset.id) === currentSupervisorId;
  });

  openModal('modal-reassign');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
