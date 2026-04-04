<?php
/**
 * FYPTracker — Topbar partial
 * includes/topbar.php
 *
 * Set before including:
 *   $pageTitle (string)   — shown as page heading
 *   $topbarActions (string, optional) — additional HTML for right side
 *
 * Features:
 *   - Mobile sidebar hamburger
 *   - Page title
 *   - Notification bell with unread count
 *   - Theme toggle (dark/light)
 *   - User identity chip
 */

$notifUrl = match ($_SESSION['role'] ?? '') {
    'student'    => 'notifications.php',
    'supervisor',
    'admin'      => '../student/notifications.php',
    default      => '#',
};

$unread = unreadNotificationCount();
$fname  = explode(' ', $_SESSION['full_name'] ?? 'User')[0];
?>

<header class="topbar" role="banner">
  <div class="topbar-left">
    <!-- Hamburger — mobile only (skill: adaptive-navigation) -->
    <button
      class="topbar-menu-btn"
      onclick="toggleSidebar()"
      aria-label="Open navigation menu"
      aria-expanded="false"
      aria-controls="sidebar">
      <i data-lucide="menu" width="20" height="20"></i>
    </button>

    <h1 class="topbar-title"><?= e($pageTitle ?? 'Dashboard') ?></h1>
  </div>

  <div class="topbar-right">
    <!-- Notification bell -->
    <a
      href="<?= e($notifUrl) ?>"
      class="topbar-btn"
      aria-label="Notifications<?= $unread > 0 ? " ({$unread} unread)" : '' ?>"
      title="Notifications">
      <i data-lucide="bell" width="18" height="18"></i>
      <?php if ($unread > 0): ?>
      <span class="topbar-badge" aria-hidden="true"></span>
      <?php endif; ?>
    </a>

    <!-- Theme toggle -->
    <button
      class="theme-toggle"
      id="themeToggle"
      onclick="toggleTheme()"
      aria-label="Toggle theme"
      title="Toggle theme">
      <i data-lucide="sun"  width="16" height="16" id="icon-sun"  style="display:none"></i>
      <i data-lucide="moon" width="16" height="16" id="icon-moon"></i>
    </button>

    <!-- User chip -->
    <div class="topbar-user" aria-label="Signed in as <?= e($_SESSION['full_name'] ?? '') ?>">
      <i data-lucide="user" width="14" height="14" style="color:var(--text-tertiary)" aria-hidden="true"></i>
      <span class="topbar-user-name"><?= e($fname) ?></span>
    </div>
  </div>
</header>
