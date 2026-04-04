<?php
/**
 * FYPTracker — Admin Dashboard
 * admin/dashboard.php
 */

define('BASE_URL', '..');
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/repositories/AdminRepository.php';
require_once __DIR__ . '/../includes/repositories/SupervisorRepository.php';

requireRole('admin');

$db                   = getDB();
$adminRepository      = new AdminRepository($db);
$supervisorRepository = new SupervisorRepository($db);

$stats        = $adminRepository->fetchSystemStats();
$recentUsers  = array_slice($adminRepository->findAllUsersWithProfiles(1, '', ''), 0, 6);
$supervisors  = $supervisorRepository->findAllSupervisorsWithWorkload();

$deptProgress = $stats['total_milestones'] > 0
    ? (int) round($stats['completed_milestones'] / $stats['total_milestones'] * 100)
    : 0;

$pageTitle = 'Dashboard';
$activeNav = 'dashboard';
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
          <div class="page-title">FYP Coordinator Dashboard</div>
          <p class="page-subtitle">Department of Computer Science · Federal University of Lafia</p>
        </div>
      </div>
    </div>

    <div class="stats-grid">
      <?php $statCards = [
        ['icon'=>'graduation-cap','value'=>$stats['total_students'],    'label'=>'Students'],
        ['icon'=>'user-check',    'value'=>$stats['total_supervisors'], 'label'=>'Supervisors'],
        ['icon'=>'file-text',     'value'=>$stats['total_projects'],    'label'=>'Projects'],
        ['icon'=>'clock',         'value'=>$stats['pending_projects'],  'label'=>'Pending Review','warn'=>$stats['pending_projects']>0],
        ['icon'=>'loader-2',      'value'=>$stats['active_projects'],   'label'=>'Active'],
        ['icon'=>'check-circle-2','value'=>$stats['completed_projects'],'label'=>'Completed'],
      ];
      foreach ($statCards as $c): ?>
      <div class="stat-card">
        <div class="stat-icon-wrap"><i data-lucide="<?= $c['icon'] ?>" width="18" height="18"></i></div>
        <div class="stat-value tabular" style="<?= !empty($c['warn']) ? 'color:var(--color-warning);' : '' ?>"><?= $c['value'] ?></div>
        <div class="stat-label"><?= $c['label'] ?></div>
      </div>
      <?php endforeach; ?>
    </div>

    <?php if ($stats['unassigned_students'] > 0): ?>
    <div class="alert alert-warning" style="margin-bottom:var(--sp-4);" data-auto-dismiss="0">
      <i data-lucide="alert-triangle" width="16" height="16" class="alert-icon"></i>
      <div class="alert-content"><strong><?= $stats['unassigned_students'] ?> student<?= $stats['unassigned_students']!==1?'s':'' ?></strong> without a supervisor. <a href="assign_supervisors.php">Assign now →</a></div>
    </div>
    <?php endif; ?>

    <div class="content-grid">
      <div>
        <div class="card">
          <div class="card-header">
            <div class="card-title">Department Progress</div>
            <span style="font-size:var(--text-2xl);font-weight:700;color:var(--accent-400);font-variant-numeric:tabular-nums;"><?= $deptProgress ?>%</span>
          </div>
          <div class="card-body" style="padding-top:var(--sp-3);">
            <div class="progress" style="height:8px;margin-bottom:var(--sp-4);" data-progress="<?= $deptProgress ?>">
              <div class="progress-bar" style="width:0%;"></div>
            </div>
            <?php $breakdown=[
              ['label'=>'Pending','value'=>$stats['pending_projects'],  'cls'=>'pending'],
              ['label'=>'Active', 'value'=>$stats['active_projects'],   'cls'=>'in-progress'],
              ['label'=>'Done',   'value'=>$stats['completed_projects'],'cls'=>'completed'],
              ['label'=>'Rejected','value'=>$stats['rejected_projects'],'cls'=>'rejected'],
            ];
            $total=max(1,$stats['total_projects']);
            foreach($breakdown as $b):
              $pct=(int)round($b['value']/$total*100); ?>
            <div style="margin-bottom:var(--sp-3);">
              <div style="display:flex;justify-content:space-between;margin-bottom:4px;">
                <span class="badge badge-<?= $b['cls'] ?>"><?= $b['label'] ?></span>
                <span style="font-size:var(--text-xs);color:var(--text-tertiary);font-variant-numeric:tabular-nums;"><?= $b['value'] ?></span>
              </div>
              <div class="progress" data-progress="<?= $pct ?>"><div class="progress-bar" style="width:0%;"></div></div>
            </div>
            <?php endforeach; ?>
          </div>
          <div class="card-footer">
            <a href="reports.php" class="btn btn-outline btn-sm" style="width:100%;justify-content:center;">
              <i data-lucide="bar-chart-2" width="14" height="14"></i> Full Report
            </a>
          </div>
        </div>
      </div>

      <div>
        <div class="card">
          <div class="card-header"><div class="card-title">Supervisor Workload</div></div>
          <div style="padding:var(--sp-3) var(--sp-5);display:flex;flex-direction:column;gap:var(--sp-3);">
            <?php foreach($supervisors as $sv):
              $available=(int)$sv['available_slots'];$maxS=(int)$sv['max_students'];
              $assigned=(int)$sv['assigned_students'];$pct=$maxS>0?(int)round($assigned/$maxS*100):0;
              $barCls=$available<=0?'progress-bar-error':($pct>=75?'progress-bar-warning':''); ?>
            <div>
              <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px;">
                <span style="font-size:var(--text-sm);font-weight:500;color:var(--text-primary);"><?= e($sv['full_name']) ?></span>
                <span style="font-size:var(--text-xs);color:<?= $available<=0?'var(--color-error)':'var(--text-tertiary)' ?>;font-variant-numeric:tabular-nums;"><?= $assigned ?>/<?= $maxS ?></span>
              </div>
              <div class="progress" data-progress="<?= $pct ?>"><div class="progress-bar <?= $barCls ?>" style="width:0%;"></div></div>
            </div>
            <?php endforeach; ?>
          </div>
          <div class="card-footer">
            <a href="assign_supervisors.php" class="btn btn-outline btn-sm" style="width:100%;justify-content:center;">
              <i data-lucide="git-merge" width="14" height="14"></i> Manage Assignments
            </a>
          </div>
        </div>
      </div>
    </div>

    <div class="card" style="margin-top:var(--sp-4);">
      <div class="card-header"><div class="card-title">Quick Actions</div></div>
      <div class="card-body">
        <div style="display:flex;gap:var(--sp-3);flex-wrap:wrap;">
          <a href="manage_users.php"       class="btn btn-secondary"><i data-lucide="users" width="16" height="16"></i> Manage Users</a>
          <a href="assign_supervisors.php" class="btn btn-secondary"><i data-lucide="git-merge" width="16" height="16"></i> Assign Supervisors</a>
          <a href="reports.php"            class="btn btn-secondary"><i data-lucide="bar-chart-2" width="16" height="16"></i> Reports</a>
          <a href="reports.php?export=pdf&type=all&<?= csrfToken() ?>" class="btn btn-secondary"><i data-lucide="download" width="16" height="16"></i> Export PDF</a>
        </div>
      </div>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
