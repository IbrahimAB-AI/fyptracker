<?php
/**
 * FYPTracker — Supervisor Dashboard
 * supervisor/dashboard.php
 */

define('BASE_URL', '..');
require_once __DIR__ . '/../includes/functions.php';
requireRole('supervisor');

$db  = getDB();
$sid = $_SESSION['user_id'];

// Assigned students count
$stmtStudents = $db->prepare(
    'SELECT COUNT(*) FROM projects WHERE supervisor_id = ?'
);
$stmtStudents->execute([$sid]);
$totalStudents = (int) $stmtStudents->fetchColumn();

// Pending proposals
$stmtPending = $db->prepare(
    'SELECT COUNT(*) FROM projects WHERE supervisor_id = ? AND status = ?'
);
$stmtPending->execute([$sid, 'pending']);
$pendingCount = (int) $stmtPending->fetchColumn();

// Active projects
$stmtActive = $db->prepare(
    'SELECT COUNT(*) FROM projects WHERE supervisor_id = ? AND status = ?'
);
$stmtActive->execute([$sid, 'in_progress']);
$activeCount = (int) $stmtActive->fetchColumn();

// Upcoming meetings
$stmtMeet = $db->prepare(
    'SELECT COUNT(*) FROM meetings mt
      JOIN projects p ON p.project_id = mt.project_id
     WHERE p.supervisor_id = ? AND mt.status = ? AND mt.scheduled_date >= NOW()'
);
$stmtMeet->execute([$sid, 'scheduled']);
$meetingCount = (int) $stmtMeet->fetchColumn();

// Student list with progress
$stmtStudList = $db->prepare(
    'SELECT u.full_name, u.user_id, st.matric_number,
            p.project_id, p.title AS project_title, p.status,
            COUNT(m.milestone_id) AS total_m,
            SUM(m.completion_status = ?) AS done_m
       FROM projects p
       JOIN users u    ON u.user_id = p.student_id
       JOIN students st ON st.student_id = p.student_id
       LEFT JOIN milestones m ON m.project_id = p.project_id
      WHERE p.supervisor_id = ?
      GROUP BY p.project_id
      ORDER BY u.full_name ASC'
);
$stmtStudList->execute(['completed', $sid]);
$students = $stmtStudList->fetchAll();

$pageTitle = 'Dashboard';
$activeNav = 'dashboard';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="main-content">
    <div class="topbar">
        <div class="topbar-left">
            <button class="topbar-menu-btn" onclick="toggleSidebar()">☰</button>
            <span class="page-title-bar">Supervisor Dashboard</span>
        </div>
        <div class="topbar-right">
            <?php $nb = unreadNotificationCount(); ?>
            <a href="../student/notifications.php" class="topbar-notif">🔔<?php if ($nb): ?><span class="topbar-badge"><?= $nb ?></span><?php endif; ?></a>
            <span class="topbar-user">👤 <?= e(explode(' ',$_SESSION['full_name'])[0]) ?></span>
        </div>
    </div>

    <div class="page-body">
        <?php renderFlash(); ?>

        <div style="margin-bottom:1.5rem;">
            <h1 style="font-family:'DM Serif Display',serif; font-size:1.55rem; color:var(--green-deep);">
                Good day, <?= e(explode(' ', $_SESSION['full_name'])[0]) ?> 👋
            </h1>
            <p style="color:var(--text-muted); font-size:0.875rem;">Here's a summary of your supervision workload.</p>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">👥</div>
                <div class="stat-value"><?= $totalStudents ?></div>
                <div class="stat-label">Assigned Students</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">📋</div>
                <div class="stat-value"><?= $pendingCount ?></div>
                <div class="stat-label">Pending Reviews</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">🔄</div>
                <div class="stat-value"><?= $activeCount ?></div>
                <div class="stat-label">Active Projects</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">📅</div>
                <div class="stat-value"><?= $meetingCount ?></div>
                <div class="stat-label">Upcoming Meetings</div>
            </div>
        </div>

        <?php if ($pendingCount > 0): ?>
        <div class="flash-message flash-warning" style="margin-bottom:1.25rem;">
            <span class="flash-icon">⚠</span>
            <span class="flash-text">
                You have <strong><?= $pendingCount ?> pending proposal(s)</strong> awaiting your review.
                <a href="review_proposals.php">Review now →</a>
            </span>
        </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">
                <div class="card-title">Assigned Students</div>
            </div>
            <?php if ($students): ?>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Matric No.</th>
                            <th>Project Title</th>
                            <th>Status</th>
                            <th>Progress</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $s):
                            $pct = $s['total_m'] > 0 ? round($s['done_m'] / $s['total_m'] * 100) : 0;
                        ?>
                        <tr>
                            <td><strong><?= e($s['full_name']) ?></strong></td>
                            <td style="font-size:0.8rem; color:var(--text-muted);"><?= e($s['matric_number']) ?></td>
                            <td style="max-width:220px; font-size:0.85rem;"><?= e(mb_strimwidth($s['project_title'] ?? '—', 0, 60, '…')) ?></td>
                            <td><span class="badge badge-<?= e(str_replace('_','-',$s['status'])) ?>"><?= e(ucfirst(str_replace('_',' ',$s['status']))) ?></span></td>
                            <td style="min-width:120px;">
                                <div style="display:flex; align-items:center; gap:0.5rem;">
                                    <div class="progress-bar" style="flex:1;">
                                        <div class="progress-fill" style="width:<?= $pct ?>%;"></div>
                                    </div>
                                    <span style="font-size:0.75rem; font-weight:700; color:var(--green-deep); white-space:nowrap;"><?= $pct ?>%</span>
                                </div>
                            </td>
                            <td>
                                <a href="milestones.php?student=<?= (int)$s['user_id'] ?>" class="btn btn-outline btn-sm">Milestones</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="empty-state">
                <div class="empty-state-icon">👥</div>
                <h3>No students assigned yet</h3>
                <p>The administrator will assign students to you.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
