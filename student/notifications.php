<?php
/**
 * FYPTracker — Student: Notifications Inbox
 * student/notifications.php
 *
 * Displays all notifications for the current user.
 * Supports:
 *   - Mark single notification as read
 *   - Mark all as read
 *   - Pagination (coding-standards: no magic numbers for limit)
 *
 * Principles:
 *  - coding-standards: named constants, descriptive vars, early returns
 *  - backend-patterns: repository-style queries, N+1 prevention
 */

define('BASE_URL', '..');
require_once __DIR__ . '/../includes/functions.php';

requireLogin(); // All roles can access their notifications

// ── Named constants ──────────────────────────────────────────────────────
const NOTIFICATIONS_PER_PAGE = 20;
const ACTION_MARK_READ       = 'mark_read';
const ACTION_MARK_ALL_READ   = 'mark_all_read';

$db     = getDB();
$userId = $_SESSION['user_id'];

// ── POST: handle read actions ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $action = post('action');

    if ($action === ACTION_MARK_ALL_READ) {
        $stmt = $db->prepare(
            'UPDATE notifications SET is_read = 1 WHERE user_id = ?'
        );
        $stmt->execute([$userId]);
        auditLog('notifications_all_marked_read');
        setFlash('success', 'All notifications marked as read.');

    } elseif ($action === ACTION_MARK_READ) {
        $notificationId = (int) post('notification_id');

        // Authorization: ensure notification belongs to this user
        $stmt = $db->prepare(
            'UPDATE notifications SET is_read = 1
              WHERE notification_id = ? AND user_id = ?'
        );
        $stmt->execute([$notificationId, $userId]);
    }

    redirect('notifications.php');
}

// ── GET: paginate notifications ──────────────────────────────────────────
$currentPage  = max(1, (int) ($_GET['page'] ?? 1));
$offset       = ($currentPage - 1) * NOTIFICATIONS_PER_PAGE;

// Total count for pagination
$stmtTotal = $db->prepare(
    'SELECT COUNT(*) FROM notifications WHERE user_id = ?'
);
$stmtTotal->execute([$userId]);
$totalCount   = (int) $stmtTotal->fetchColumn();
$totalPages   = (int) ceil($totalCount / NOTIFICATIONS_PER_PAGE);

// Fetch page of notifications
$stmtNotifs = $db->prepare(
    'SELECT * FROM notifications
      WHERE user_id = ?
      ORDER BY is_read ASC, created_at DESC
      LIMIT ? OFFSET ?'
);
$stmtNotifs->execute([$userId, NOTIFICATIONS_PER_PAGE, $offset]);
$notifications = $stmtNotifs->fetchAll();

// Counts for summary
$stmtUnread = $db->prepare(
    'SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0'
);
$stmtUnread->execute([$userId]);
$unreadCount = (int) $stmtUnread->fetchColumn();

// ── Notification icon helper ─────────────────────────────────────────────
function getNotificationIcon(string $message): string
{
    // Match on message keywords — no magic strings, early return pattern
    if (str_contains($message, 'approved'))   return 'check-circle-2';
    if (str_contains($message, 'rejected'))   return 'x-circle';
    if (str_contains($message, 'feedback'))   return 'message-square';
    if (str_contains($message, 'meeting'))    return 'calendar';
    if (str_contains($message, 'milestone'))  return 'target';
    if (str_contains($message, 'submitted'))  return 'file-text';
    if (str_contains($message, 'assigned'))   return 'user-check';
    if (str_contains($message, 'completed'))  return 'check-circle-2';

    return 'bell';
}

$pageTitle = 'Notifications';
$activeNav = 'notifications';
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
          <div class="page-title">
            Notifications
            <?php if ($unreadCount > 0): ?>
            <span style="display:inline-flex;align-items:center;justify-content:center;background:var(--accent-600);color:#fff;font-size:var(--text-xs);font-weight:700;padding:2px 8px;border-radius:var(--radius-full);margin-left:var(--sp-2);vertical-align:middle;font-variant-numeric:tabular-nums;">
              <?= $unreadCount ?>
            </span>
            <?php endif; ?>
          </div>
          <p class="page-subtitle"><?= $totalCount ?> total notification<?= $totalCount !== 1 ? 's' : '' ?>.</p>
        </div>

        <?php if ($unreadCount > 0): ?>
        <form method="POST" action="notifications.php">
          <?= csrfField() ?>
          <input type="hidden" name="action" value="<?= ACTION_MARK_ALL_READ ?>">
          <button type="submit" class="btn btn-secondary btn-sm">
            <i data-lucide="check-check" width="14" height="14"></i>
            Mark All Read
          </button>
        </form>
        <?php endif; ?>
      </div>
    </div>

    <?php if (empty($notifications)): ?>
    <!-- Empty state -->
    <div class="card">
      <div class="card-body">
        <div class="empty-state">
          <div class="empty-icon"><i data-lucide="bell-off" width="24" height="24"></i></div>
          <div class="empty-title">No notifications yet</div>
          <p class="empty-desc">You'll be notified here about proposal updates, feedback, meeting requests, and milestone changes.</p>
        </div>
      </div>
    </div>

    <?php else: ?>
    <!-- Notification list -->
    <div class="card">
      <div style="display:flex;flex-direction:column;">
        <?php foreach ($notifications as $index => $notif):
          $isUnread    = !(bool) $notif['is_read'];
          $icon        = getNotificationIcon($notif['message']);
          $isLast      = $index === count($notifications) - 1;
        ?>
        <div style="display:flex;align-items:flex-start;gap:var(--sp-4);padding:var(--sp-4) var(--sp-5);<?= !$isLast ? 'border-bottom:1px solid var(--surface-divider);' : '' ?><?= $isUnread ? 'background:rgba(16,185,129,0.04);' : '' ?>transition:background var(--dur-fast);">

          <!-- Icon -->
          <div style="width:36px;height:36px;border-radius:var(--radius-full);background:<?= $isUnread ? 'rgba(16,185,129,0.12)' : 'var(--surface-overlay)' ?>;border:1px solid <?= $isUnread ? 'rgba(16,185,129,0.2)' : 'var(--surface-border)' ?>;display:flex;align-items:center;justify-content:center;flex-shrink:0;color:<?= $isUnread ? 'var(--accent-400)' : 'var(--text-tertiary)' ?>;">
            <i data-lucide="<?= e($icon) ?>" width="16" height="16"></i>
          </div>

          <!-- Content -->
          <div style="flex:1;min-width:0;">
            <p style="font-size:var(--text-sm);color:<?= $isUnread ? 'var(--text-primary)' : 'var(--text-secondary)' ?>;line-height:1.55;<?= $isUnread ? 'font-weight:500;' : '' ?>margin-bottom:var(--sp-1);">
              <?= e($notif['message']) ?>
            </p>
            <div style="display:flex;align-items:center;gap:var(--sp-3);">
              <span style="font-size:var(--text-xs);color:var(--text-tertiary);">
                <?= timeAgo($notif['created_at']) ?>
              </span>
              <?php if ($isUnread): ?>
              <span style="display:inline-flex;align-items:center;gap:3px;font-size:0.65rem;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:var(--accent-500);">
                <span style="width:5px;height:5px;background:var(--accent-500);border-radius:50%;"></span>
                New
              </span>
              <?php endif; ?>
            </div>
          </div>

          <!-- Actions -->
          <div style="display:flex;align-items:center;gap:var(--sp-2);flex-shrink:0;">
            <?php if ($notif['link']): ?>
            <a
              href="<?= e($notif['link']) ?>"
              class="btn btn-ghost btn-sm"
              title="Go to related page">
              <i data-lucide="arrow-right" width="14" height="14"></i>
            </a>
            <?php endif; ?>

            <?php if ($isUnread): ?>
            <form method="POST" action="notifications.php" style="display:inline;">
              <?= csrfField() ?>
              <input type="hidden" name="action" value="<?= ACTION_MARK_READ ?>">
              <input type="hidden" name="notification_id" value="<?= (int) $notif['notification_id'] ?>">
              <button
                type="submit"
                class="btn btn-ghost btn-sm"
                title="Mark as read">
                <i data-lucide="check" width="14" height="14"></i>
              </button>
            </form>
            <?php endif; ?>
          </div>

        </div>
        <?php endforeach; ?>
      </div>

      <!-- Pagination -->
      <?php if ($totalPages > 1): ?>
      <div class="card-footer" style="display:flex;align-items:center;justify-content:space-between;gap:var(--sp-4);">
        <span style="font-size:var(--text-xs);color:var(--text-tertiary);">
          Page <?= $currentPage ?> of <?= $totalPages ?> · <?= $totalCount ?> notifications
        </span>
        <div style="display:flex;gap:var(--sp-2);">
          <?php if ($currentPage > 1): ?>
          <a href="?page=<?= $currentPage - 1 ?>" class="btn btn-secondary btn-sm">
            <i data-lucide="chevron-left" width="14" height="14"></i> Previous
          </a>
          <?php endif; ?>
          <?php if ($currentPage < $totalPages): ?>
          <a href="?page=<?= $currentPage + 1 ?>" class="btn btn-secondary btn-sm">
            Next <i data-lucide="chevron-right" width="14" height="14"></i>
          </a>
          <?php endif; ?>
        </div>
      </div>
      <?php endif; ?>
    </div>
    <?php endif; ?>

  </div><!-- /page-body -->
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
