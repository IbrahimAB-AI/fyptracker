<?php
/**
 * FYPTracker — Supervisor: Meetings
 * supervisor/meetings.php
 *
 * Supervisor can:
 *   - View all upcoming and past meetings across all students
 *   - Mark a meeting as completed and add minutes
 *   - Cancel a scheduled meeting
 *   - Filter by student
 *
 * Principles:
 *  - coding-standards: early returns, named constants, DRY helpers
 *  - backend-patterns: repository pattern, N+1 prevention
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
const ACTION_COMPLETE_MEETING = 'complete_meeting';
const ACTION_CANCEL_MEETING   = 'cancel_meeting';

// ── POST: handle meeting actions ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $action    = post('action');
    $meetingId = (int) post('meeting_id');

    // Verify meeting belongs to one of this supervisor's projects
    $meeting = $projectRepository->findMeetingById($meetingId);
    if (!$meeting) {
        setFlash('error', 'Meeting not found.');
        redirect('meetings.php');
    }

    $project = $projectRepository->findProjectById((int) $meeting['project_id']);
    if (!$project || (int) $project['supervisor_id'] !== $supervisorId) {
        setFlash('error', 'Access denied.');
        redirect('meetings.php');
    }

    $studentId = (int) $project['student_id'];

    if ($action === ACTION_COMPLETE_MEETING) {
        $minutes = trim(post('minutes'));

        if (mb_strlen($minutes) < 10) {
            setFlash('error', 'Please enter meeting minutes (minimum 10 characters).');
            redirect('meetings.php');
        }

        $projectRepository->updateMeetingStatus(
            $meetingId,
            ProjectRepository::MEETING_COMPLETED,
            $minutes
        );

        createNotification(
            $studentId,
            "Meeting on " . formatDate($meeting['scheduled_date']) . " has been completed. Minutes have been recorded.",
            '../student/meetings.php'
        );

        auditLog('meeting_completed', 'meetings', $meetingId);
        setFlash('success', 'Meeting marked as completed. Minutes saved.');

    } elseif ($action === ACTION_CANCEL_MEETING) {
        $projectRepository->updateMeetingStatus(
            $meetingId,
            ProjectRepository::MEETING_CANCELLED
        );

        createNotification(
            $studentId,
            "Your meeting scheduled for " . formatDate($meeting['scheduled_date']) . " has been cancelled by your supervisor.",
            '../student/meetings.php'
        );

        auditLog('meeting_cancelled', 'meetings', $meetingId);
        setFlash('success', 'Meeting cancelled. The student has been notified.');
    }

    redirect('meetings.php');
}

// ── GET: load meetings ───────────────────────────────────────────────────
$filterStudentId = (int) ($_GET['student'] ?? 0);

$allMeetings      = $supervisorRepository->findAllMeetingsBySupervisorId($supervisorId);
$upcomingMeetings = $supervisorRepository->findUpcomingMeetingsBySupervisorId($supervisorId);

// Filter by student if requested
$displayMeetings  = $filterStudentId > 0
    ? array_filter($allMeetings, fn($m) => (int) $m['student_id'] === $filterStudentId)
    : $allMeetings;

$displayMeetings = array_values($displayMeetings);

// Build unique student list for filter dropdown — single pass
$studentOptions = [];
foreach ($allMeetings as $m) {
    $sid = (int) $m['project_id']; // using project_id as proxy — we have student_name
    if (!isset($studentOptions[$m['student_name']])) {
        $studentOptions[$m['student_name']] = $m;
    }
}

// ── View helper ──────────────────────────────────────────────────────────
function getMeetingStatusConfig2(string $status): array
{
    return match ($status) {
        ProjectRepository::MEETING_COMPLETED   => ['badge' => 'completed',   'icon' => 'check-circle-2', 'label' => 'Completed'],
        ProjectRepository::MEETING_CANCELLED   => ['badge' => 'cancelled',   'icon' => 'x-circle',       'label' => 'Cancelled'],
        ProjectRepository::MEETING_RESCHEDULED => ['badge' => 'in-progress', 'icon' => 'refresh-cw',     'label' => 'Rescheduled'],
        default                                => ['badge' => 'scheduled',   'icon' => 'calendar',       'label' => 'Scheduled'],
    };
}

$pageTitle = 'Meetings';
$activeNav = 'meetings';
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
          <div class="page-title">Meetings</div>
          <p class="page-subtitle"><?= count($upcomingMeetings) ?> upcoming · <?= count($allMeetings) ?> total</p>
        </div>
      </div>
    </div>

    <!-- Upcoming meetings strip -->
    <?php if (!empty($upcomingMeetings)): ?>
    <div style="margin-bottom:var(--sp-5);">
      <h2 style="font-size:var(--text-sm);font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:var(--accent-500);margin-bottom:var(--sp-3);display:flex;align-items:center;gap:var(--sp-2);">
        <i data-lucide="calendar-clock" width="14" height="14"></i>
        Upcoming (<?= count($upcomingMeetings) ?>)
      </h2>
      <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:var(--sp-3);">
        <?php foreach ($upcomingMeetings as $meeting): ?>
        <div class="card">
          <div style="padding:var(--sp-4) var(--sp-5);">
            <div style="display:flex;align-items:center;gap:var(--sp-3);margin-bottom:var(--sp-3);">
              <div style="width:44px;text-align:center;flex-shrink:0;">
                <div style="font-size:var(--text-2xl);font-weight:700;color:var(--accent-400);line-height:1;font-variant-numeric:tabular-nums;">
                  <?= date('d', strtotime($meeting['scheduled_date'])) ?>
                </div>
                <div style="font-size:0.65rem;font-weight:600;color:var(--text-tertiary);text-transform:uppercase;letter-spacing:0.06em;">
                  <?= date('M Y', strtotime($meeting['scheduled_date'])) ?>
                </div>
              </div>
              <div style="flex:1;min-width:0;">
                <p style="font-size:var(--text-sm);font-weight:600;color:var(--text-primary);margin-bottom:2px;">
                  <?= date('g:i A', strtotime($meeting['scheduled_date'])) ?>
                </p>
                <p style="font-size:var(--text-xs);color:var(--text-tertiary);">
                  <?= e($meeting['student_name']) ?>
                </p>
              </div>
            </div>
            <div style="font-size:var(--text-xs);color:var(--text-tertiary);display:flex;align-items:center;gap:var(--sp-1);margin-bottom:var(--sp-3);">
              <i data-lucide="map-pin" width="11" height="11"></i>
              <?= e($meeting['venue'] ?: 'Venue TBD') ?>
            </div>
            <div style="display:flex;gap:var(--sp-2);">
              <button
                class="btn btn-primary btn-sm"
                style="flex:1;"
                onclick="openCompleteMeetingModal(<?= (int) $meeting['meeting_id'] ?>, '<?= date('d M Y', strtotime($meeting['scheduled_date'])) ?>')">
                <i data-lucide="check" width="13" height="13"></i> Complete
              </button>
              <form method="POST" action="meetings.php">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="<?= ACTION_CANCEL_MEETING ?>">
                <input type="hidden" name="meeting_id" value="<?= (int) $meeting['meeting_id'] ?>">
                <button
                  type="submit"
                  class="btn btn-secondary btn-sm"
                  data-confirm="Cancel this meeting? The student will be notified.">
                  <i data-lucide="x" width="13" height="13"></i>
                </button>
              </form>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- Full meeting history table -->
    <div class="card">
      <div class="card-header">
        <div>
          <div class="card-title">All Meetings</div>
          <div class="card-subtitle">Full history across all students</div>
        </div>
      </div>

      <?php if (empty($allMeetings)): ?>
      <div class="card-body">
        <div class="empty-state">
          <div class="empty-icon"><i data-lucide="calendar" width="24" height="24"></i></div>
          <div class="empty-title">No meetings yet</div>
          <p class="empty-desc">Meeting requests from your students will appear here.</p>
        </div>
      </div>

      <?php else: ?>
      <div class="table-wrapper">
        <table>
          <thead>
            <tr>
              <th>Date & Time</th>
              <th>Student</th>
              <th>Project</th>
              <th>Venue</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($allMeetings as $meeting):
              $cfg = getMeetingStatusConfig2($meeting['status']);
            ?>
            <tr>
              <td>
                <span class="cell-primary"><?= date('d M Y', strtotime($meeting['scheduled_date'])) ?></span>
                <span class="cell-muted" style="display:block;"><?= date('g:i A', strtotime($meeting['scheduled_date'])) ?></span>
              </td>
              <td><?= e($meeting['student_name']) ?></td>
              <td style="max-width:180px;">
                <span class="truncate" style="display:block;font-size:var(--text-sm);" title="<?= e($meeting['project_title']) ?>">
                  <?= e(mb_strimwidth($meeting['project_title'], 0, 40, '…')) ?>
                </span>
              </td>
              <td class="cell-muted"><?= e($meeting['venue'] ?: '—') ?></td>
              <td><span class="badge badge-<?= e($cfg['badge']) ?>"><?= $cfg['label'] ?></span></td>
              <td>
                <div class="cell-actions">
                  <?php if ($meeting['status'] === ProjectRepository::MEETING_SCHEDULED): ?>
                  <button
                    class="btn btn-primary btn-sm"
                    onclick="openCompleteMeetingModal(<?= (int) $meeting['meeting_id'] ?>, '<?= date('d M Y', strtotime($meeting['scheduled_date'])) ?>')">
                    <i data-lucide="check" width="13" height="13"></i> Complete
                  </button>
                  <form method="POST" action="meetings.php" style="display:inline;">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="<?= ACTION_CANCEL_MEETING ?>">
                    <input type="hidden" name="meeting_id" value="<?= (int) $meeting['meeting_id'] ?>">
                    <button
                      type="submit"
                      class="btn btn-ghost btn-sm"
                      data-confirm="Cancel this meeting?">
                      <i data-lucide="x" width="13" height="13"></i>
                    </button>
                  </form>
                  <?php elseif ($meeting['status'] === ProjectRepository::MEETING_COMPLETED && $meeting['minutes']): ?>
                  <button
                    class="btn btn-ghost btn-sm"
                    onclick="viewMinutes(<?= htmlspecialchars(json_encode($meeting['minutes']), ENT_QUOTES) ?>)">
                    <i data-lucide="file-text" width="13" height="13"></i> Minutes
                  </button>
                  <?php else: ?>
                  <span style="font-size:var(--text-xs);color:var(--text-tertiary);">—</span>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>

  </div><!-- /page-body -->
</div>

<!-- Complete meeting modal -->
<div class="modal-backdrop" id="modal-complete-meeting">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title">Document Meeting</div>
      <button class="modal-close" onclick="closeModal('modal-complete-meeting')" aria-label="Close">
        <i data-lucide="x" width="16" height="16"></i>
      </button>
    </div>
    <form method="POST" action="meetings.php">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="<?= ACTION_COMPLETE_MEETING ?>">
      <input type="hidden" name="meeting_id" id="complete-meeting-id" value="">
      <div class="modal-body">
        <p id="complete-meeting-date" style="font-size:var(--text-sm);font-weight:600;color:var(--text-primary);margin-bottom:var(--sp-4);"></p>
        <div class="form-group">
          <label class="form-label form-label-required" for="minutes">Meeting Minutes</label>
          <textarea
            class="form-control"
            id="minutes"
            name="minutes"
            rows="6"
            placeholder="Record what was discussed, decisions made, and action items for the student..."
            minlength="10"
            maxlength="3000"
            required></textarea>
          <span class="form-hint">Minutes will be visible to the student.</span>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modal-complete-meeting')">Cancel</button>
        <button type="submit" class="btn btn-primary">
          <i data-lucide="check" width="14" height="14"></i> Mark Complete & Save Minutes
        </button>
      </div>
    </form>
  </div>
</div>

<!-- View minutes modal -->
<div class="modal-backdrop" id="modal-view-minutes">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title">Meeting Minutes</div>
      <button class="modal-close" onclick="closeModal('modal-view-minutes')" aria-label="Close">
        <i data-lucide="x" width="16" height="16"></i>
      </button>
    </div>
    <div class="modal-body">
      <p id="minutes-content" style="font-size:var(--text-sm);color:var(--text-secondary);line-height:1.7;white-space:pre-line;"></p>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn btn-secondary" onclick="closeModal('modal-view-minutes')">Close</button>
    </div>
  </div>
</div>

<script>
function openCompleteMeetingModal(meetingId, dateLabel) {
  document.getElementById('complete-meeting-id').value  = meetingId;
  document.getElementById('complete-meeting-date').textContent = 'Meeting: ' + dateLabel;
  document.getElementById('minutes').value = '';
  openModal('modal-complete-meeting');
}

function viewMinutes(minutesText) {
  document.getElementById('minutes-content').textContent = minutesText;
  openModal('modal-view-minutes');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
