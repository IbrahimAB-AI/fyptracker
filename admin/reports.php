<?php
/**
 * FYPTracker — Admin: Reports
 * admin/reports.php
 *
 * Provides:
 *   - Department-wide statistics dashboard
 *   - Per-student progress table
 *   - PDF export (full department or single student) via FPDF
 *   - Audit log viewer
 *
 * Principles:
 *  - coding-standards: early returns, named constants, DRY
 *  - backend-patterns: repository pattern, service layer for PDF
 */

define('BASE_URL', '..');
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/repositories/AdminRepository.php';
require_once __DIR__ . '/../includes/repositories/ProjectRepository.php';

requireRole('admin');

$db              = getDB();
$adminRepository = new AdminRepository($db);

// ── Named constants ───────────────────────────────────────────────────────
const REPORT_TYPE_ALL    = 'all';
const REPORT_TYPE_SINGLE = 'single';

// ── PDF export handler ────────────────────────────────────────────────────
if (isset($_GET['export']) && $_GET['export'] === 'pdf') {
    verifyCsrf();

    $reportType = ($_GET['type'] ?? REPORT_TYPE_ALL) === REPORT_TYPE_SINGLE
        ? REPORT_TYPE_SINGLE
        : REPORT_TYPE_ALL;

    $studentId  = $reportType === REPORT_TYPE_SINGLE ? (int) ($_GET['student'] ?? 0) : null;

    // FPDF path — installed via Composer or manually in includes/
    $fpdfPath = __DIR__ . '/../includes/fpdf/fpdf.php';

    if (!file_exists($fpdfPath)) {
        setFlash('error', 'FPDF library not found. Please install FPDF in includes/fpdf/fpdf.php.');
        redirect('reports.php');
    }

    require_once $fpdfPath;

    $reportData = $adminRepository->fetchStudentReportData($studentId);
    $systemStats = $adminRepository->fetchSystemStats();

    // ── Build PDF ─────────────────────────────────────────────────────────
    $pdf = new FPDF('L', 'mm', 'A4'); // Landscape for wide table
    $pdf->SetMargins(12, 12, 12);
    $pdf->SetAutoPageBreak(true, 15);
    $pdf->AddPage();

    // ── Header ────────────────────────────────────────────────────────────
    $pdf->SetFont('Arial', 'B', 16);
    $pdf->SetTextColor(10, 61, 46);
    $pdf->Cell(0, 9, 'FYPTracker - Department of Computer Science, FULafia', 0, 1, 'C');

    $pdf->SetFont('Arial', '', 10);
    $pdf->SetTextColor(100, 100, 100);
    $reportTitle = $reportType === REPORT_TYPE_SINGLE && $studentId
        ? 'Individual Student Progress Report'
        : 'Departmental Progress Report';
    $pdf->Cell(0, 6, $reportTitle . ' — Generated: ' . date('d F Y, g:i A'), 0, 1, 'C');

    $pdf->Ln(4);

    // ── System stats summary ──────────────────────────────────────────────
    if ($reportType === REPORT_TYPE_ALL) {
        $pdf->SetFont('Arial', 'B', 11);
        $pdf->SetTextColor(30, 30, 30);
        $pdf->Cell(0, 7, 'System Summary', 0, 1);

        $pdf->SetFont('Arial', '', 9);
        $pdf->SetTextColor(60, 60, 60);

        $summaryItems = [
            "Total Students: {$systemStats['total_students']}",
            "Total Supervisors: {$systemStats['total_supervisors']}",
            "Total Projects: {$systemStats['total_projects']}",
            "Active Projects: {$systemStats['active_projects']}",
            "Completed Projects: {$systemStats['completed_projects']}",
            "Total Milestones: {$systemStats['total_milestones']}",
        ];

        $colW = 85;
        foreach (array_chunk($summaryItems, 3) as $row) {
            foreach ($row as $item) {
                $pdf->Cell($colW, 6, $item, 0, 0);
            }
            $pdf->Ln();
        }

        $pdf->Ln(4);
    }

    // ── Table header ──────────────────────────────────────────────────────
    $pdf->SetFont('Arial', 'B', 8);
    $pdf->SetFillColor(10, 61, 46);
    $pdf->SetTextColor(255, 255, 255);

    $columns = [
        ['label' => 'Student Name',   'width' => 45],
        ['label' => 'Matric No.',     'width' => 30],
        ['label' => 'Supervisor',     'width' => 42],
        ['label' => 'Project Title',  'width' => 72],
        ['label' => 'Status',         'width' => 25],
        ['label' => 'Milestones',     'width' => 22],
        ['label' => 'Progress',       'width' => 18],
        ['label' => 'Meetings',       'width' => 18],
    ];

    foreach ($columns as $col) {
        $pdf->Cell($col['width'], 7, $col['label'], 1, 0, 'C', true);
    }
    $pdf->Ln();

    // ── Table rows ────────────────────────────────────────────────────────
    $pdf->SetFont('Arial', '', 8);
    $fillRow = false;

    foreach ($reportData as $row) {
        $pdf->SetTextColor(30, 30, 30);
        $pdf->SetFillColor($fillRow ? 240 : 255, $fillRow ? 247 : 255, $fillRow ? 244 : 255);

        $totalMs     = (int) ($row['total_milestones']    ?? 0);
        $completedMs = (int) ($row['completed_milestones'] ?? 0);
        $progressPct = $totalMs > 0 ? (int) round($completedMs / $totalMs * 100) : 0;
        $msLabel     = "{$completedMs}/{$totalMs}";
        $status      = ucwords(str_replace('_', ' ', $row['project_status'] ?? 'N/A'));

        $pdf->Cell(45, 6, $this->truncateText($row['student_name'], 28),  1, 0, 'L', $fillRow);
        $pdf->Cell(30, 6, $row['matric_number'],                           1, 0, 'C', $fillRow);
        $pdf->Cell(42, 6, $this->truncateText($row['supervisor_name'] ?? 'Unassigned', 25), 1, 0, 'L', $fillRow);
        $pdf->Cell(72, 6, $this->truncateText($row['project_title'] ?? 'No project', 42),  1, 0, 'L', $fillRow);
        $pdf->Cell(25, 6, $status,                                         1, 0, 'C', $fillRow);
        $pdf->Cell(22, 6, $msLabel,                                        1, 0, 'C', $fillRow);
        $pdf->Cell(18, 6, $progressPct . '%',                              1, 0, 'C', $fillRow);
        $pdf->Cell(18, 6, (string) ((int) ($row['total_meetings'] ?? 0)),  1, 0, 'C', $fillRow);
        $pdf->Ln();

        $fillRow = !$fillRow;
    }

    if (empty($reportData)) {
        $pdf->SetTextColor(120, 120, 120);
        $pdf->Cell(0, 8, 'No data available.', 1, 1, 'C');
    }

    // ── Footer ────────────────────────────────────────────────────────────
    $pdf->Ln(4);
    $pdf->SetFont('Arial', 'I', 7);
    $pdf->SetTextColor(150, 150, 150);
    $pdf->Cell(0, 5, 'Generated by FYPTracker · Department of Computer Science · Federal University of Lafia · ' . date('Y'), 0, 1, 'C');

    $filename = 'FYPTracker_Report_' . date('Y-m-d_His') . '.pdf';
    $pdf->Output('D', $filename);
    exit;
}

// ── GET: load dashboard data ──────────────────────────────────────────────
$systemStats  = $adminRepository->fetchSystemStats();
$studentData  = $adminRepository->fetchStudentReportData();
$recentLogs   = $adminRepository->fetchRecentAuditLogs(30);

// Derive overall department progress percentage
$deptProgress = $systemStats['total_milestones'] > 0
    ? (int) round($systemStats['completed_milestones'] / $systemStats['total_milestones'] * 100)
    : 0;

// Helper: truncate text for PDF cells — also used in the page
function truncateText(string $text, int $maxLength): string
{
    return mb_strlen($text) > $maxLength ? mb_substr($text, 0, $maxLength - 1) . '…' : $text;
}

function getAuditIcon(string $action): string
{
    return match (true) {
        str_contains($action, 'login')       => 'log-in',
        str_contains($action, 'logout')      => 'log-out',
        str_contains($action, 'approved')    => 'check-circle-2',
        str_contains($action, 'rejected')    => 'x-circle',
        str_contains($action, 'created')     => 'plus-circle',
        str_contains($action, 'submitted')   => 'send',
        str_contains($action, 'completed')   => 'check',
        str_contains($action, 'assigned')    => 'link',
        default                              => 'activity',
    };
}

$pageTitle = 'Reports';
$activeNav = 'reports';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="main-content" id="main-content">
  <?php require_once __DIR__ . '/../includes/topbar.php'; ?>

  <div class="page-body">
    <?php renderFlash(); ?>

    <!-- Header -->
    <div class="page-header">
      <div class="page-header-row">
        <div>
          <div class="page-title">Reports</div>
          <p class="page-subtitle">Departmental FYP progress · Academic Year <?= date('Y') ?>/<?= date('Y') + 1 ?></p>
        </div>
        <!-- PDF export — full department -->
        <a
          href="reports.php?export=pdf&type=<?= REPORT_TYPE_ALL ?>&<?= csrfToken() ?>"
          class="btn btn-primary">
          <i data-lucide="download" width="16" height="16"></i>
          Export PDF Report
        </a>
      </div>
    </div>

    <!-- Key metrics -->
    <div class="stats-grid" style="grid-template-columns:repeat(auto-fill,minmax(160px,1fr));margin-bottom:var(--sp-5);">
      <?php
      $metrics = [
          ['icon' => 'graduation-cap', 'value' => $systemStats['total_students'],     'label' => 'Students'],
          ['icon' => 'user-check',     'value' => $systemStats['total_supervisors'],  'label' => 'Supervisors'],
          ['icon' => 'file-text',      'value' => $systemStats['total_projects'],     'label' => 'Projects'],
          ['icon' => 'loader-2',       'value' => $systemStats['active_projects'],    'label' => 'Active'],
          ['icon' => 'check-circle-2', 'value' => $systemStats['completed_projects'], 'label' => 'Completed'],
          ['icon' => 'calendar',       'value' => $systemStats['total_meetings'],     'label' => 'Meetings'],
      ];
      foreach ($metrics as $m):
      ?>
      <div class="stat-card">
        <div class="stat-icon-wrap"><i data-lucide="<?= $m['icon'] ?>" width="18" height="18"></i></div>
        <div class="stat-value tabular"><?= $m['value'] ?></div>
        <div class="stat-label"><?= $m['label'] ?></div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Department progress -->
    <div class="card" style="margin-bottom:var(--sp-5);">
      <div class="card-header">
        <div class="card-title">Department Progress</div>
        <span style="font-size:var(--text-2xl);font-weight:700;color:var(--accent-400);font-variant-numeric:tabular-nums;"><?= $deptProgress ?>%</span>
      </div>
      <div class="card-body" style="padding-top:var(--sp-3);">
        <div class="progress" style="height:10px;" data-progress="<?= $deptProgress ?>">
          <div class="progress-bar" style="width:0%;"></div>
        </div>
        <div style="display:flex;justify-content:space-between;margin-top:var(--sp-2);">
          <span style="font-size:var(--text-xs);color:var(--text-tertiary);">
            <?= $systemStats['completed_milestones'] ?> of <?= $systemStats['total_milestones'] ?> milestones completed
          </span>
          <?php if ($systemStats['unassigned_students'] > 0): ?>
          <span style="font-size:var(--text-xs);color:var(--color-warning);font-weight:600;">
            <i data-lucide="alert-triangle" width="11" height="11" style="display:inline;"></i>
            <?= $systemStats['unassigned_students'] ?> student<?= $systemStats['unassigned_students'] !== 1 ? 's' : '' ?> unassigned
          </span>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="content-grid">

      <!-- Student progress table -->
      <div class="col-span-2">
        <div class="card">
          <div class="card-header">
            <div>
              <div class="card-title">Student Progress Table</div>
              <div class="card-subtitle"><?= count($studentData) ?> students</div>
            </div>
          </div>
          <?php if (empty($studentData)): ?>
          <div class="card-body">
            <div class="empty-state">
              <div class="empty-icon"><i data-lucide="bar-chart-2" width="24" height="24"></i></div>
              <div class="empty-title">No student data yet</div>
            </div>
          </div>
          <?php else: ?>
          <div class="table-wrapper">
            <table>
              <thead>
                <tr>
                  <th>Student</th>
                  <th>Supervisor</th>
                  <th>Project Status</th>
                  <th>Milestones</th>
                  <th>Progress</th>
                  <th>Meetings</th>
                  <th>Export</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($studentData as $row):
                  $totalMs     = (int) ($row['total_milestones']    ?? 0);
                  $completedMs = (int) ($row['completed_milestones'] ?? 0);
                  $pct         = $totalMs > 0 ? (int) round($completedMs / $totalMs * 100) : 0;
                  $statusMap   = [
                      'pending'     => 'pending',
                      'approved'    => 'approved',
                      'in_progress' => 'in-progress',
                      'completed'   => 'completed',
                      'rejected'    => 'rejected',
                  ];
                  $badgeCls = $statusMap[$row['project_status'] ?? ''] ?? 'not-started';
                ?>
                <tr>
                  <td>
                    <span class="cell-primary"><?= e($row['student_name']) ?></span>
                    <span class="cell-muted font-mono" style="display:block;"><?= e($row['matric_number']) ?></span>
                  </td>
                  <td class="cell-muted"><?= e($row['supervisor_name'] ?? 'Unassigned') ?></td>
                  <td>
                    <span class="badge badge-<?= $badgeCls ?>">
                      <?= ucwords(str_replace('_', ' ', $row['project_status'] ?? 'N/A')) ?>
                    </span>
                  </td>
                  <td>
                    <span style="font-size:var(--text-sm);font-variant-numeric:tabular-nums;"><?= $completedMs ?>/<?= $totalMs ?></span>
                  </td>
                  <td style="min-width:100px;">
                    <div style="display:flex;align-items:center;gap:var(--sp-2);">
                      <div class="progress" style="flex:1;" data-progress="<?= $pct ?>">
                        <div class="progress-bar" style="width:0%;"></div>
                      </div>
                      <span style="font-size:var(--text-xs);font-weight:700;color:var(--text-tertiary);font-variant-numeric:tabular-nums;"><?= $pct ?>%</span>
                    </div>
                  </td>
                  <td>
                    <span style="font-size:var(--text-sm);font-variant-numeric:tabular-nums;"><?= (int) ($row['total_meetings'] ?? 0) ?></span>
                  </td>
                  <td>
                    <a
                      href="reports.php?export=pdf&type=<?= REPORT_TYPE_SINGLE ?>&student=<?= (int) $row['user_id'] ?>&<?= csrfToken() ?>"
                      class="btn btn-ghost btn-sm"
                      title="Export individual report">
                      <i data-lucide="download" width="13" height="13"></i>
                    </a>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Project status breakdown -->
      <div>
        <div class="card">
          <div class="card-header">
            <div class="card-title">Project Breakdown</div>
          </div>
          <div class="card-body">
            <?php
            $breakdownItems = [
                ['label' => 'Pending Review', 'value' => $systemStats['pending_projects'],   'cls' => 'pending'],
                ['label' => 'Approved',       'value' => $systemStats['approved_projects'],  'cls' => 'approved'],
                ['label' => 'In Progress',    'value' => $systemStats['active_projects'],    'cls' => 'in-progress'],
                ['label' => 'Completed',      'value' => $systemStats['completed_projects'], 'cls' => 'completed'],
                ['label' => 'Rejected',       'value' => $systemStats['rejected_projects'],  'cls' => 'rejected'],
            ];
            $total = max(1, $systemStats['total_projects']);
            foreach ($breakdownItems as $item):
              $pct = (int) round($item['value'] / $total * 100);
            ?>
            <div style="margin-bottom:var(--sp-4);">
              <div style="display:flex;justify-content:space-between;margin-bottom:var(--sp-1);">
                <span class="badge badge-<?= $item['cls'] ?>"><?= $item['label'] ?></span>
                <span style="font-size:var(--text-xs);color:var(--text-tertiary);font-variant-numeric:tabular-nums;"><?= $item['value'] ?> (<?= $pct ?>%)</span>
              </div>
              <div class="progress" data-progress="<?= $pct ?>">
                <div class="progress-bar" style="width:0%;"></div>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <!-- Audit log -->
      <div>
        <div class="card">
          <div class="card-header">
            <div class="card-title">Recent Activity</div>
            <div class="card-subtitle">Last 30 entries</div>
          </div>
          <div style="max-height:380px;overflow-y:auto;">
            <?php if (empty($recentLogs)): ?>
            <div class="card-body">
              <div class="empty-state" style="padding:var(--sp-8) var(--sp-4);">
                <div class="empty-icon"><i data-lucide="activity" width="22" height="22"></i></div>
                <div class="empty-title">No activity yet</div>
              </div>
            </div>
            <?php else: ?>
            <?php foreach ($recentLogs as $log): ?>
            <div style="display:flex;align-items:flex-start;gap:var(--sp-3);padding:var(--sp-3) var(--sp-5);border-bottom:1px solid var(--surface-divider);">
              <div style="width:28px;height:28px;border-radius:50%;background:var(--surface-overlay);border:1px solid var(--surface-border);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <i data-lucide="<?= getAuditIcon($log['action']) ?>" width="12" height="12" style="color:var(--accent-500);"></i>
              </div>
              <div style="flex:1;min-width:0;">
                <p style="font-size:var(--text-xs);color:var(--text-secondary);margin-bottom:2px;">
                  <strong><?= e($log['full_name'] ?? 'System') ?></strong>
                  · <?= e(str_replace('_', ' ', $log['action'])) ?>
                </p>
                <p style="font-size:0.65rem;color:var(--text-tertiary);"><?= timeAgo($log['created_at']) ?></p>
              </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>
      </div>

    </div><!-- /content-grid -->
  </div><!-- /page-body -->
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
