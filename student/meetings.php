<?php
/**
 * FYPTracker — Student: Meetings
 * student/meetings.php
 *
 * Allows students to:
 *   - Request new supervision meetings
 *   - View upcoming scheduled meetings
 *   - View past meeting history with minutes
 *
 * Principles:
 *  - coding-standards: named constants, early returns, descriptive vars
 *  - backend-patterns: repository pattern, service layer validation
 */

define('BASE_URL', '..');
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/repositories/ProjectRepository.php';
require_once __DIR__ . '/../includes/services/ValidationService.php';

requireRole('student');

$db                = getDB();
$projectRepository = new ProjectRepository($db);
$validator         = new ValidationService();

$studentId = $_SESSION['user_id'];
$project   = $projectRepository->findProjectByStudentId($studentId);

// ── Guard: needs an approved project to schedule meetings ────────────────
if (!$project) {
    setFlash('info', 'Submit a project proposal first before scheduling meetings.');
    redirect('submit_proposal.php');
}

$hasApprovedProject = in_array(
    $project['status'],
    [
        ProjectRepository::STATUS_APPROVED,
        ProjectRepository::STATUS_IN_PROGRESS,
        ProjectRepository::STATUS_COMPLETED,
    ],
    true
);

// ── POST: create meeting request ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    // Early return: no supervisor assigned
    if (!$project['supervisor_id']) {
        setFlash('error', 'You cannot request a meeting until a supervisor is assigned to your project.');
        redirect('meetings.php');
    }

    $scheduledDate = post('scheduled_date');
    $venue         = post('venue');
    $agenda        = post('agenda');

    if (!$validator->validateMeetingForm($scheduledDate, $venue, $agenda)) {
        setFlash('error', implode(' ', $validator->getErrors()));
        redirect('meetings.php');
    }

    $meetingId = $projectRepository->createMeeting(
        (int) $project['project_id'],
        $scheduledDate,
        $venue,
        $agenda,
        $studentId
    );

    // Notify supervisor
    createNotification(
        (int) $project['supervisor_id'],
        "{$_SESSION['full_name']} has requested a meeting on " . date('d M Y, g:i A', strtotime($scheduledDate)) . '.',
        '../supervisor/meetings.php'
    );

    auditLog('meeting_requested', 'meetings', $meetingId);
    setFlash('success', 'Meeting request submitted. Your supervisor will confirm shortly.');
    redirect('meetings.php');
}

// ── GET: load meetings ───────────────────────────────────────────────────
$allMeetings      = $projectRepository->findMeetingsByProjectId((int) $project['project_id']);
$upcomingMeetings = $projectRepository->findUpcomingMeetingsByProjectId((int) $project['project_id']);

// Separate into past and upcoming for display (single array pass — DRY)
$pastMeetings = array_filter(
    $allMeetings,
    fn($m) => in_array($m['status'], [
        ProjectRepository::MEETING_COMPLETED,
        ProjectRepository::MEETING_CANCELLED,
    ], true)
);

// ── View helper ──────────────────────────────────────────────────────────
function getMeetingStatusConfig(string $status): array
{
    return match ($status) {
        ProjectRepository::MEETING_COMPLETED   => ['badge' => 'completed',   'icon' => 'check-circle-2'],
        ProjectRepository::MEETING_CANCELLED   => ['badge' => 'cancelled',   'icon' => 'x-circle'],
        ProjectRepository::MEETING_RESCHEDULED => ['badge' => 'in-progress', 'icon' => 'refresh-cw'],
        default                                => ['badge' => 'scheduled',   'icon' => 'calendar'],
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
          <p class="page-subtitle">Supervision sessions with <?= $project['supervisor_name'] ? e($project['supervisor_name']) : 'your supervisor' ?>.</p>
        </div>
        <?php if ($hasApprovedProject && $project['supervisor_id']): ?>
        <button class="btn btn-primary" onclick="openModal('modal-request-meeting')">
          <i data-lucide="plus" width="16" height="16"></i>
          Request Meeting
        </button>
        <?php endif; ?>
      </div>
    </div>

    <?php if (!$hasApprovedProject): ?>
    <div class="alert alert-info" data-auto-dismiss="0">
      <i data-lucide="info" width="16" height="16" class="alert-icon"></i>
      <div class="alert-content">
        Your proposal must be approved before you can request supervision meetings.
      </div>
    </div>

    <?php elseif (!$project['supervisor_id']): ?>
    <div class="alert alert-warning" data-auto-dismiss="0">
      <i data-lucide="alert-triangle" width="16" height="16" class="alert-icon"></i>
      <div class="alert-content">
        <div class="alert-title">No Supervisor Assigned</div>
        A supervisor has not been assigned to your project yet. Contact the FYP Coordinator.
      </div>
    </div>

    <?php else: ?>

    <!-- Upcoming meetings -->
    <div class="card" style="margin-bottom:var(--sp-4);">
      <div class="card-header">
        <div>
          <div class="card-title">Upcoming Meetings</div>
          <div class="card-subtitle"><?= count($upcomingMeetings) ?> scheduled</div>
        </div>
      </div>
      <div class="card-body" style="padding-top:var(--sp-2);">
        <?php if (empty($upcomingMeetings)): ?>
        <div class="empty-state" style="padding:var(--sp-8) var(--sp-4);">
          <div class="empty-icon"><i data-lucide="calendar" width="22" height="22"></i></div>
          <div class="empty-title">No upcoming meetings</div>
          <p class="empty-desc">Request a meeting with your supervisor to discuss your progress.</p>
        </div>
        <?php else: ?>
        <div style="display:flex;flex-direction:column;gap:var(--sp-3);">
          <?php foreach ($upcomingMeetings as $meeting): ?>
          <div style="display:flex;align-items:flex-start;gap:var(--sp-4);padding:var(--sp-4);background:var(--surface-overlay);border:1px solid var(--surface-border);border-radius:var(--radius-md);">
            <!-- Date block -->
            <div style="width:52px;text-align:center;flex-shrink:0;">
              <div style="font-size:var(--text-2xl);font-weight:700;color:var(--accent-400);letter-spacing:-0.03em;line-height:1;font-variant-numeric:tabular-nums;">
                <?= date('d', strtotime($meeting['scheduled_date'])) ?>
              </div>
              <div style="font-size:var(--text-xs);font-weight:600;color:var(--text-tertiary);text-transform:uppercase;letter-spacing:0.06em;">
                <?= date('M', strtotime($meeting['scheduled_date'])) ?>
              </div>
            </div>

            <!-- Meeting details -->
            <div style="flex:1;min-width:0;">
              <div style="display:flex;align-items:center;gap:var(--sp-3);margin-bottom:var(--sp-2);flex-wrap:wrap;">
                <span style="font-size:var(--text-sm);font-weight:600;color:var(--text-primary);">
                  <?= date('g:i A', strtotime($meeting['scheduled_date'])) ?>
                </span>
                <span class="badge badge-scheduled">Scheduled</span>
              </div>
              <div style="display:flex;flex-direction:column;gap:var(--sp-1);">
                <div style="display:flex;align-items:center;gap:var(--sp-2);font-size:var(--text-xs);color:var(--text-tertiary);">
                  <i data-lucide="map-pin" width="12" height="12"></i>
                  <?= e($meeting['venue']) ?>
                </div>
                <?php if ($meeting['agenda']): ?>
                <div style="display:flex;align-items:flex-start;gap:var(--sp-2);font-size:var(--text-xs);color:var(--text-tertiary);">
                  <i data-lucide="file-text" width="12" height="12" style="margin-top:1px;flex-shrink:0;"></i>
                  <?= e(mb_strimwidth($meeting['agenda'], 0, 120, '…')) ?>
                </div>
                <?php endif; ?>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Meeting history -->
    <?php if (!empty($pastMeetings)): ?>
    <div class="card">
      <div class="card-header">
        <div class="card-title">Meeting History</div>
      </div>
      <div class="table-wrapper">
        <table>
          <thead>
            <tr>
              <th>Date & Time</th>
              <th>Venue</th>
              <th>Status</th>
              <th>Minutes</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($pastMeetings as $meeting):
              $cfg = getMeetingStatusConfig($meeting['status']);
            ?>
            <tr>
              <td>
                <span class="cell-primary"><?= formatDateTime($meeting['scheduled_date']) ?></span>
              </td>
              <td><?= e($meeting['venue'] ?: '—') ?></td>
              <td><span class="badge badge-<?= e($cfg['badge']) ?>"><?= e(ucfirst($meeting['status'])) ?></span></td>
              <td>
                <?php if ($meeting['minutes']): ?>
                <button
                  class="btn btn-ghost btn-sm"
                  onclick="viewMinutes(<?= $meeting['meeting_id'] ?>, <?= htmlspecialchars(json_encode($meeting['minutes']), ENT_QUOTES) ?>)">
                  <i data-lucide="file-text" width="13" height="13"></i> View
                </button>
                <?php else: ?>
                <span style="font-size:var(--text-xs);color:var(--text-tertiary);">—</span>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>

    <?php endif; // end hasApprovedProject check ?>

  </div><!-- /page-body -->
</div>

<!-- ── Request meeting modal ─────────────────────────────────────────── -->
<div class="modal-backdrop" id="modal-request-meeting">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title">Request Supervision Meeting</div>
      <button class="modal-close" onclick="closeModal('modal-request-meeting')" aria-label="Close">
        <i data-lucide="x" width="16" height="16"></i>
      </button>
    </div>
    <form method="POST" action="meetings.php">
      <?= csrfField() ?>
      <div class="modal-body">
        <div class="form-stack">

          <div class="form-group">
            <label class="form-label form-label-required" for="scheduled_date">Preferred Date & Time</label>
            <input
              class="form-control"
              type="datetime-local"
              id="scheduled_date"
              name="scheduled_date"
              min="<?= date('Y-m-d\TH:i', strtotime('+1 hour')) ?>"
              required>
            <span class="form-hint">Must be at least 1 hour from now.</span>
          </div>

          <div class="form-group">
            <label class="form-label form-label-required" for="venue">Venue / Meeting Link</label>
            <input
              class="form-control"
              type="text"
              id="venue"
              name="venue"
              placeholder="e.g. Supervisor's Office, Room 204 or Google Meet link"
              maxlength="255"
              required>
          </div>

          <div class="form-group">
            <label class="form-label form-label-required" for="agenda">Meeting Agenda</label>
            <textarea
              class="form-control"
              id="agenda"
              name="agenda"
              placeholder="Describe what you want to discuss in this meeting..."
              rows="4"
              maxlength="1000"
              required></textarea>
            <span class="form-hint">Minimum 10 characters.</span>
          </div>

        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modal-request-meeting')">Cancel</button>
        <button type="submit" class="btn btn-primary">
          <i data-lucide="send" width="14" height="14"></i> Submit Request
        </button>
      </div>
    </form>
  </div>
</div>

<!-- ── View minutes modal ─────────────────────────────────────────────── -->
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
function viewMinutes(meetingId, minutesText) {
  document.getElementById('minutes-content').textContent = minutesText;
  openModal('modal-view-minutes');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
