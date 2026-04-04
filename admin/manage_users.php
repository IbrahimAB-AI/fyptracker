<?php
/**
 * FYPTracker — Admin: Manage Users
 * admin/manage_users.php
 *
 * Admin can:
 *   - View all users (students, supervisors, admin) with pagination + search
 *   - Create new supervisor accounts
 *   - Suspend / reactivate any account
 *
 * Principles:
 *  - coding-standards: early returns, named constants, DRY helpers
 *  - backend-patterns: repository pattern, service layer, centralized errors
 */

define('BASE_URL', '..');
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/repositories/AdminRepository.php';
require_once __DIR__ . '/../includes/services/ValidationService.php';

requireRole('admin');

$db              = getDB();
$adminRepository = new AdminRepository($db);
$validator       = new ValidationService();

// ── Named constants ──────────────────────────────────────────────────────
const ACTION_CREATE_SUPERVISOR = 'create_supervisor';
const ACTION_TOGGLE_STATUS     = 'toggle_status';

// ── POST handler ─────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $action = post('action');

    // ── Create supervisor account ────────────────────────────────────────
    if ($action === ACTION_CREATE_SUPERVISOR) {
        $fullName       = post('full_name');
        $email          = post('email');
        $staffId        = post('staff_id');
        $title          = post('title');
        $specialisation = post('specialisation');
        $maxStudents    = (int) post('max_students');
        $password       = post('password');
        $errors         = [];

        // Validation — inline here since supervisor creation is admin-only
        // and has unique fields not covered by ValidationService
        if (mb_strlen($fullName) < 3)                       $errors[] = 'Full name is too short.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL))     $errors[] = 'Invalid email address.';
        if (mb_strlen($staffId) < 3)                        $errors[] = 'Staff ID is required.';
        if (mb_strlen($password) < 8)                       $errors[] = 'Password must be at least 8 characters.';
        if ($maxStudents < 1 || $maxStudents > 20)          $errors[] = 'Max students must be between 1 and 20.';

        if (!empty($errors)) {
            setFlash('error', implode(' ', $errors));
            redirect('manage_users.php');
        }

        // Check email uniqueness
        $checkEmail = $db->prepare('SELECT user_id FROM users WHERE email = ? LIMIT 1');
        $checkEmail->execute([$email]);
        if ($checkEmail->fetch()) {
            setFlash('error', 'A user with this email already exists.');
            redirect('manage_users.php');
        }

        // Check staff ID uniqueness
        $checkStaff = $db->prepare('SELECT supervisor_id FROM supervisors WHERE staff_id = ? LIMIT 1');
        $checkStaff->execute([$staffId]);
        if ($checkStaff->fetch()) {
            setFlash('error', 'A supervisor with this staff ID already exists.');
            redirect('manage_users.php');
        }

        try {
            $userId = $adminRepository->createSupervisorAccount(
                $fullName,
                $email,
                password_hash($password, PASSWORD_BCRYPT),
                strtoupper($staffId),
                $title,
                $specialisation,
                $maxStudents
            );

            auditLog('supervisor_created', 'users', $userId);
            setFlash('success', "Supervisor account for {$fullName} created successfully.");

        } catch (PDOException $e) {
            error_log('Create supervisor error: ' . $e->getMessage());
            setFlash('error', 'Account creation failed. Please try again.');
        }

        redirect('manage_users.php');
    }

    // ── Toggle user active status ────────────────────────────────────────
    if ($action === ACTION_TOGGLE_STATUS) {
        $userId   = (int) post('user_id');
        $isActive = (bool) post('is_active');

        // Guard: admin cannot suspend themselves
        if ($userId === $_SESSION['user_id']) {
            setFlash('error', 'You cannot suspend your own account.');
            redirect('manage_users.php');
        }

        $adminRepository->toggleUserActiveStatus($userId, $isActive);

        $statusLabel = $isActive ? 'reactivated' : 'suspended';
        auditLog("user_{$statusLabel}", 'users', $userId);
        setFlash('success', "User account {$statusLabel} successfully.");
        redirect('manage_users.php');
    }
}

// ── GET: load paginated users ─────────────────────────────────────────────
$currentPage  = max(1, (int) ($_GET['page']   ?? 1));
$filterRole   = in_array($_GET['role'] ?? '', ['student', 'supervisor', 'admin', ''], true)
    ? ($_GET['role'] ?? '')
    : '';
$searchQuery  = trim($_GET['search'] ?? '');

$users        = $adminRepository->findAllUsersWithProfiles($currentPage, $filterRole, $searchQuery);
$totalUsers   = $adminRepository->countUsers($filterRole, $searchQuery);
$totalPages   = (int) ceil($totalUsers / AdminRepository::USERS_PER_PAGE);

// Role counts for filter tabs — single extra query
$roleCounts = $db->query(
    "SELECT role, COUNT(*) AS cnt FROM users WHERE is_active = 1 GROUP BY role"
)->fetchAll(PDO::FETCH_KEY_PAIR);

// Role icon helper
function getRoleIcon(string $role): string
{
    return match ($role) {
        'admin'      => 'shield',
        'supervisor' => 'user-check',
        'student'    => 'graduation-cap',
        default      => 'user',
    };
}

$pageTitle = 'Manage Users';
$activeNav = 'manage_users';
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
          <div class="page-title">Manage Users</div>
          <p class="page-subtitle"><?= $totalUsers ?> total account<?= $totalUsers !== 1 ? 's' : '' ?> in the system.</p>
        </div>
        <button class="btn btn-primary" onclick="openModal('modal-create-supervisor')">
          <i data-lucide="user-plus" width="16" height="16"></i>
          Add Supervisor
        </button>
      </div>
    </div>

    <!-- Role filter tabs -->
    <div style="display:flex;gap:var(--sp-2);margin-bottom:var(--sp-5);flex-wrap:wrap;">
      <?php
      $tabOptions = [
          ''           => 'All Users',
          'student'    => 'Students',
          'supervisor' => 'Supervisors',
          'admin'      => 'Admins',
      ];
      foreach ($tabOptions as $roleKey => $label):
        $isActive = $filterRole === $roleKey;
        $count    = $roleKey === '' ? $totalUsers : ($roleCounts[$roleKey] ?? 0);
        $href     = 'manage_users.php?' . http_build_query(['role' => $roleKey, 'search' => $searchQuery]);
      ?>
      <a
        href="<?= e($href) ?>"
        class="btn <?= $isActive ? 'btn-primary' : 'btn-secondary' ?> btn-sm"
        style="<?= $isActive ? '' : 'opacity:0.75;' ?>">
        <?= e($label) ?>
        <span style="font-size:0.65rem;font-weight:700;opacity:0.7;margin-left:2px;">(<?= $count ?>)</span>
      </a>
      <?php endforeach; ?>
    </div>

    <!-- Search bar -->
    <form method="GET" action="manage_users.php" style="margin-bottom:var(--sp-4);">
      <input type="hidden" name="role" value="<?= e($filterRole) ?>">
      <div style="display:flex;gap:var(--sp-3);max-width:480px;">
        <div style="position:relative;flex:1;">
          <i data-lucide="search" width="15" height="15" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--text-tertiary);pointer-events:none;"></i>
          <input
            class="form-control"
            type="text"
            name="search"
            placeholder="Search by name or email…"
            value="<?= e($searchQuery) ?>"
            style="padding-left:36px;">
        </div>
        <button type="submit" class="btn btn-secondary">Search</button>
        <?php if ($searchQuery): ?>
        <a href="manage_users.php?role=<?= e($filterRole) ?>" class="btn btn-ghost">
          <i data-lucide="x" width="14" height="14"></i>
        </a>
        <?php endif; ?>
      </div>
    </form>

    <!-- Users table -->
    <div class="card">
      <?php if (empty($users)): ?>
      <div class="card-body">
        <div class="empty-state">
          <div class="empty-icon"><i data-lucide="users" width="24" height="24"></i></div>
          <div class="empty-title">No users found</div>
          <p class="empty-desc">Try adjusting your search or filter.</p>
        </div>
      </div>

      <?php else: ?>
      <div class="table-wrapper">
        <table>
          <thead>
            <tr>
              <th>User</th>
              <th>Role</th>
              <th>Details</th>
              <th>Status</th>
              <th>Joined</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($users as $user):
              $isCurrentUser = (int) $user['user_id'] === $_SESSION['user_id'];
            ?>
            <tr style="<?= !$user['is_active'] ? 'opacity:0.55;' : '' ?>">
              <td>
                <div style="display:flex;align-items:center;gap:var(--sp-3);">
                  <div style="width:34px;height:34px;border-radius:50%;background:<?= $user['is_active'] ? 'rgba(16,185,129,0.12)' : 'var(--surface-overlay)' ?>;border:1px solid <?= $user['is_active'] ? 'rgba(16,185,129,0.2)' : 'var(--surface-border)' ?>;display:flex;align-items:center;justify-content:center;font-size:var(--text-xs);font-weight:700;color:<?= $user['is_active'] ? 'var(--accent-400)' : 'var(--text-tertiary)' ?>;flex-shrink:0;">
                    <?= strtoupper(mb_substr($user['full_name'], 0, 1)) ?>
                  </div>
                  <div>
                    <div class="cell-primary"><?= e($user['full_name']) ?></div>
                    <div class="cell-muted"><?= e($user['email']) ?></div>
                  </div>
                </div>
              </td>
              <td>
                <span style="display:inline-flex;align-items:center;gap:var(--sp-1);font-size:var(--text-xs);font-weight:600;text-transform:capitalize;color:var(--text-secondary);">
                  <i data-lucide="<?= getRoleIcon($user['role']) ?>" width="12" height="12"></i>
                  <?= e($user['role']) ?>
                </span>
              </td>
              <td>
                <?php if ($user['role'] === 'student' && $user['matric_number']): ?>
                <span class="cell-muted font-mono"><?= e($user['matric_number']) ?></span>
                <?php elseif ($user['role'] === 'supervisor' && $user['staff_id']): ?>
                <span class="cell-muted"><?= e($user['supervisor_title'] ?? '') ?> · <?= e($user['staff_id']) ?></span>
                <?php else: ?>
                <span class="cell-muted">—</span>
                <?php endif; ?>
              </td>
              <td>
                <?php if ($user['is_active']): ?>
                <span class="badge badge-approved">Active</span>
                <?php else: ?>
                <span class="badge badge-cancelled">Suspended</span>
                <?php endif; ?>
              </td>
              <td class="cell-muted"><?= formatDate($user['created_at']) ?></td>
              <td>
                <?php if (!$isCurrentUser): ?>
                <div class="cell-actions">
                  <form method="POST" action="manage_users.php" style="display:inline;">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="<?= ACTION_TOGGLE_STATUS ?>">
                    <input type="hidden" name="user_id" value="<?= (int) $user['user_id'] ?>">
                    <input type="hidden" name="is_active" value="<?= $user['is_active'] ? '0' : '1' ?>">
                    <button
                      type="submit"
                      class="btn btn-ghost btn-sm"
                      data-confirm="<?= $user['is_active'] ? 'Suspend this user? They will not be able to log in.' : 'Reactivate this user?' ?>"
                      style="color:<?= $user['is_active'] ? 'var(--color-error)' : 'var(--color-success)' ?>;">
                      <i data-lucide="<?= $user['is_active'] ? 'user-x' : 'user-check' ?>" width="13" height="13"></i>
                      <?= $user['is_active'] ? 'Suspend' : 'Reactivate' ?>
                    </button>
                  </form>
                </div>
                <?php else: ?>
                <span class="cell-muted" style="font-size:var(--text-xs);">You</span>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <?php if ($totalPages > 1): ?>
      <div class="card-footer" style="display:flex;align-items:center;justify-content:space-between;gap:var(--sp-4);">
        <span style="font-size:var(--text-xs);color:var(--text-tertiary);">
          Page <?= $currentPage ?> of <?= $totalPages ?> · <?= $totalUsers ?> users
        </span>
        <div style="display:flex;gap:var(--sp-2);">
          <?php if ($currentPage > 1): ?>
          <a href="?<?= http_build_query(['page' => $currentPage - 1, 'role' => $filterRole, 'search' => $searchQuery]) ?>"
             class="btn btn-secondary btn-sm">
            <i data-lucide="chevron-left" width="14" height="14"></i> Previous
          </a>
          <?php endif; ?>
          <?php if ($currentPage < $totalPages): ?>
          <a href="?<?= http_build_query(['page' => $currentPage + 1, 'role' => $filterRole, 'search' => $searchQuery]) ?>"
             class="btn btn-secondary btn-sm">
            Next <i data-lucide="chevron-right" width="14" height="14"></i>
          </a>
          <?php endif; ?>
        </div>
      </div>
      <?php endif; ?>
      <?php endif; ?>
    </div>

  </div><!-- /page-body -->
</div>

<!-- Create supervisor modal -->
<div class="modal-backdrop" id="modal-create-supervisor">
  <div class="modal" style="max-width:560px;">
    <div class="modal-header">
      <div class="modal-title">Add Supervisor Account</div>
      <button class="modal-close" onclick="closeModal('modal-create-supervisor')" aria-label="Close">
        <i data-lucide="x" width="16" height="16"></i>
      </button>
    </div>
    <form method="POST" action="manage_users.php">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="<?= ACTION_CREATE_SUPERVISOR ?>">
      <div class="modal-body">
        <div class="form-stack">
          <div class="form-row">
            <div class="form-group">
              <label class="form-label form-label-required" for="sv-name">Full Name</label>
              <input class="form-control" type="text" id="sv-name" name="full_name"
                placeholder="Dr. Emeka Okonkwo" maxlength="150" required>
            </div>
            <div class="form-group">
              <label class="form-label form-label-required" for="sv-title">Title</label>
              <select class="form-control" id="sv-title" name="title" required>
                <option value="Dr.">Dr.</option>
                <option value="Prof.">Prof.</option>
                <option value="Mr.">Mr.</option>
                <option value="Mrs.">Mrs.</option>
                <option value="Ms.">Ms.</option>
              </select>
            </div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label class="form-label form-label-required" for="sv-email">Email Address</label>
              <input class="form-control" type="email" id="sv-email" name="email"
                placeholder="name@fulafia.edu.ng" required>
            </div>
            <div class="form-group">
              <label class="form-label form-label-required" for="sv-staff">Staff ID</label>
              <input class="form-control" type="text" id="sv-staff" name="staff_id"
                placeholder="FUL/CS/STAFF/001" maxlength="30" required>
            </div>
          </div>
          <div class="form-group">
            <label class="form-label" for="sv-spec">Specialisation</label>
            <input class="form-control" type="text" id="sv-spec" name="specialisation"
              placeholder="e.g. Software Engineering, Machine Learning" maxlength="255">
          </div>
          <div class="form-row">
            <div class="form-group">
              <label class="form-label form-label-required" for="sv-max">Max Students</label>
              <input class="form-control" type="number" id="sv-max" name="max_students"
                value="5" min="1" max="20" required>
            </div>
            <div class="form-group">
              <label class="form-label form-label-required" for="sv-pass">Password</label>
              <input class="form-control" type="password" id="sv-pass" name="password"
                placeholder="Min. 8 characters" minlength="8" required>
              <span class="form-hint">Share this with the supervisor to let them in.</span>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modal-create-supervisor')">Cancel</button>
        <button type="submit" class="btn btn-primary">
          <i data-lucide="user-plus" width="14" height="14"></i> Create Account
        </button>
      </div>
    </form>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
