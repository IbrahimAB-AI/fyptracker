<?php
/**
 * FYPTracker — Sidebar + Bottom Tab Bar Navigation
 * includes/navbar.php
 *
 * Desktop (>=769px): fixed sidebar
 * Mobile  (<=768px): bottom tab bar (primary) + slide-in sidebar (secondary)
 *
 * skill: adaptive-navigation, bottom-nav-limit (<=5), nav-label-icon,
 *        tab-badge, safe-area-awareness, destructive-nav-separation
 */

$role     = $_SESSION['role']      ?? '';
$name     = $_SESSION['full_name'] ?? 'User';
$words    = array_filter(explode(' ', trim($name)));
$initials = '';
foreach (array_slice($words, 0, 2) as $w) {
    $initials .= strtoupper(mb_substr($w, 0, 1));
}

$unread = unreadNotificationCount();

$navItems = [
    'student' => [
        ['key' => 'dashboard',     'label' => 'Home',       'icon' => 'layout-dashboard', 'url' => 'dashboard.php',       'page' => 'dashboard'],
        ['key' => 'proposal',      'label' => 'Proposal',   'icon' => 'file-text',        'url' => 'submit_proposal.php', 'page' => 'submit_proposal'],
        ['key' => 'milestones',    'label' => 'Milestones', 'icon' => 'target',           'url' => 'milestones.php',      'page' => 'milestones'],
        ['key' => 'meetings',      'label' => 'Meetings',   'icon' => 'calendar',         'url' => 'meetings.php',        'page' => 'meetings'],
        ['key' => 'notifications', 'label' => 'Alerts',     'icon' => 'bell',             'url' => 'notifications.php',   'page' => 'notifications', 'badge' => $unread],
    ],
    'supervisor' => [
        ['key' => 'dashboard',     'label' => 'Home',       'icon' => 'layout-dashboard', 'url' => 'dashboard.php',           'page' => 'dashboard'],
        ['key' => 'proposals',     'label' => 'Proposals',  'icon' => 'clipboard-list',   'url' => 'review_proposals.php',    'page' => 'review_proposals'],
        ['key' => 'milestones',    'label' => 'Milestones', 'icon' => 'target',           'url' => 'milestones.php',          'page' => 'milestones'],
        ['key' => 'meetings',      'label' => 'Meetings',   'icon' => 'calendar',         'url' => 'meetings.php',            'page' => 'meetings'],
        ['key' => 'notifications', 'label' => 'Alerts',     'icon' => 'bell',             'url' => '../student/notifications.php', 'page' => 'notifications', 'badge' => $unread],
    ],
    'admin' => [
        ['key' => 'dashboard',    'label' => 'Home',    'icon' => 'layout-dashboard', 'url' => 'dashboard.php',         'page' => 'dashboard'],
        ['key' => 'manage_users', 'label' => 'Users',   'icon' => 'users',            'url' => 'manage_users.php',      'page' => 'manage_users'],
        ['key' => 'assign',       'label' => 'Assign',  'icon' => 'git-merge',        'url' => 'assign_supervisors.php','page' => 'assign_supervisors'],
        ['key' => 'reports',      'label' => 'Reports', 'icon' => 'bar-chart-2',      'url' => 'reports.php',           'page' => 'reports'],
        ['key' => 'notifications','label' => 'Alerts',  'icon' => 'bell',             'url' => '../student/notifications.php', 'page' => 'notifications', 'badge' => $unread],
    ],
];

$items = array_slice($navItems[$role] ?? [], 0, 5);
?>

<!-- DESKTOP SIDEBAR -->
<aside class="sidebar" id="sidebar" role="navigation" aria-label="Main navigation">
  <div class="sidebar-brand">
    <div class="sidebar-logo" aria-label="FYPTracker">
      <span class="sidebar-logo-text">FYP</span>
      <span class="sidebar-logo-accent">Tracker</span>
      <span class="sidebar-logo-dot" aria-hidden="true"></span>
    </div>
    <button class="sidebar-close" onclick="closeSidebar()" aria-label="Close navigation">
      <i data-lucide="x" width="18" height="18"></i>
    </button>
  </div>

  <div class="sidebar-user">
    <div class="user-avatar" aria-hidden="true"><?= e($initials) ?></div>
    <div class="user-info">
      <span class="user-name"><?= e($name) ?></span>
      <span class="user-role"><?= ucfirst(e($role)) ?></span>
    </div>
  </div>

  <nav class="sidebar-nav" aria-label="Primary">
    <span class="nav-section-label" aria-hidden="true">Menu</span>
    <?php foreach ($items as $item):
      $isActive = ($activeNav === $item['key']);
      $badge    = $item['badge'] ?? 0;
    ?>
    <a href="<?= e($item['url']) ?>"
       class="nav-item <?= $isActive ? 'active' : '' ?>"
       aria-current="<?= $isActive ? 'page' : 'false' ?>">
      <span class="nav-icon" aria-hidden="true">
        <i data-lucide="<?= e($item['icon']) ?>" width="16" height="16"></i>
      </span>
      <span class="nav-label"><?= e($item['label']) ?></span>
      <?php if ($badge > 0): ?>
      <span class="nav-badge" aria-hidden="true"><?= (int) $badge ?></span>
      <?php endif; ?>
    </a>
    <?php endforeach; ?>
  </nav>

  <div class="sidebar-footer">
    <a href="<?= BASE_URL ?>/auth/logout.php" class="nav-item nav-item-danger" aria-label="Log out">
      <span class="nav-icon" aria-hidden="true">
        <i data-lucide="log-out" width="16" height="16"></i>
      </span>
      <span class="nav-label">Log Out</span>
    </a>
  </div>
</aside>

<!-- Mobile overlay -->
<div class="sidebar-overlay" id="sidebarOverlay" aria-hidden="true" onclick="closeSidebar()"></div>

<!-- MOBILE BOTTOM TAB BAR
     skill: adaptive-navigation — visible only on <=768px via CSS
            bottom-nav-limit (<=5), nav-label-icon, tab-badge,
            safe-area-awareness (padding-bottom = env safe area) -->
<nav class="bottom-tab-bar" role="navigation" aria-label="Mobile navigation">
  <?php foreach ($items as $item):
    $isActive = ($activeNav === $item['key']);
    $badge    = $item['badge'] ?? 0;
  ?>
  <a href="<?= e($item['url']) ?>"
     class="tab-item <?= $isActive ? 'active' : '' ?>"
     data-page="<?= e($item['page']) ?>"
     aria-label="<?= e($item['label']) ?><?= $badge > 0 ? " ({$badge} unread)" : '' ?>"
     aria-current="<?= $isActive ? 'page' : 'false' ?>">
    <span class="tab-icon" aria-hidden="true">
      <i data-lucide="<?= e($item['icon']) ?>" width="20" height="20"></i>
      <?php if ($badge > 0): ?>
      <span class="tab-badge" aria-hidden="true"><?= (int) $badge ?></span>
      <?php endif; ?>
    </span>
    <span class="tab-label"><?= e($item['label']) ?></span>
  </a>
  <?php endforeach; ?>
</nav>
