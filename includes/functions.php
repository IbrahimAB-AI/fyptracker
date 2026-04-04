<?php
/**
 * FYPTracker — Core Helper Functions
 * includes/functions.php
 *
 * Loaded on every page. Provides:
 *  - Session bootstrap & role guards
 *  - CSRF token generation & validation
 *  - Input sanitisation
 *  - Notification helpers
 *  - Audit logging
 *  - Flash messages
 */

require_once __DIR__ . '/../config/db.php';

// ---------------------------------------------------------------------------
// SESSION BOOTSTRAP
// ---------------------------------------------------------------------------

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => false,   // Set true when on HTTPS
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}


// ---------------------------------------------------------------------------
// AUTHENTICATION HELPERS
// ---------------------------------------------------------------------------

/**
 * Returns true if the current visitor has an active session.
 */
function isLoggedIn(): bool
{
    return isset($_SESSION['user_id'], $_SESSION['role']);
}

/**
 * Redirect to login if not authenticated.
 */
function requireLogin(): void
{
    if (!isLoggedIn()) {
        redirect('../index.php?error=Please+log+in+to+continue.');
    }
}

/**
 * Redirect if the logged-in user's role doesn't match.
 *
 * @param string $requiredRole  'student' | 'supervisor' | 'admin'
 */
function requireRole(string $requiredRole): void
{
    requireLogin();
    if ($_SESSION['role'] !== $requiredRole) {
        redirect('../index.php?error=Access+denied.');
    }
}

/**
 * Returns the role-specific dashboard URL.
 */
function dashboardUrl(string $role): string
{
    return match ($role) {
        'student'    => 'student/dashboard.php',
        'supervisor' => 'supervisor/dashboard.php',
        'admin'      => 'admin/dashboard.php',
        default      => 'index.php',
    };
}

/**
 * Regenerate session ID — call after successful login.
 */
function regenerateSession(): void
{
    session_regenerate_id(true);
}


// ---------------------------------------------------------------------------
// CSRF PROTECTION
// ---------------------------------------------------------------------------

/**
 * Returns (and creates if needed) the CSRF token for the current session.
 */
function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Outputs a hidden CSRF input field.
 */
function csrfField(): string
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrfToken()) . '">';
}

/**
 * Validates a submitted CSRF token. Kills request on failure.
 */
function verifyCsrf(): void
{
    $submitted = $_POST['csrf_token'] ?? '';
    if (!hash_equals(csrfToken(), $submitted)) {
        http_response_code(403);
        die('Invalid CSRF token. Please go back and try again.');
    }
}


// ---------------------------------------------------------------------------
// INPUT SANITISATION
// ---------------------------------------------------------------------------

/**
 * Sanitise a string for safe HTML output.
 */
function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * Get and sanitise a POST value.
 */
function post(string $key, string $default = ''): string
{
    return trim($_POST[$key] ?? $default);
}

/**
 * Get and sanitise a GET value.
 */
function get(string $key, string $default = ''): string
{
    return trim($_GET[$key] ?? $default);
}


// ---------------------------------------------------------------------------
// REDIRECT
// ---------------------------------------------------------------------------

function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}


// ---------------------------------------------------------------------------
// FLASH MESSAGES
// ---------------------------------------------------------------------------

/**
 * Set a flash message to display on the next page load.
 *
 * @param string $type  'success' | 'error' | 'info' | 'warning'
 */
function setFlash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

/**
 * Retrieve and clear the flash message.
 *
 * @return array|null  ['type' => ..., 'message' => ...] or null
 */
function getFlash(): ?array
{
    if (!empty($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * Render flash message HTML (Bootstrap-compatible alert classes).
 */
function renderFlash(): void
{
    $flash = getFlash();
    if (!$flash) return;

    $icons = [
        'success' => '✓',
        'error'   => '✕',
        'warning' => '⚠',
        'info'    => 'ℹ',
    ];

    $icon = $icons[$flash['type']] ?? 'ℹ';
    $type = e($flash['type']);
    $msg  = e($flash['message']);

    echo <<<HTML
    <div class="flash-message flash-{$type}" role="alert">
        <span class="flash-icon">{$icon}</span>
        <span class="flash-text">{$msg}</span>
        <button class="flash-close" onclick="this.parentElement.remove()">×</button>
    </div>
    HTML;
}


// ---------------------------------------------------------------------------
// NOTIFICATION HELPERS
// ---------------------------------------------------------------------------

/**
 * Create an in-app notification for a user.
 */
function createNotification(int $userId, string $message, string $link = ''): void
{
    $db = getDB();
    $stmt = $db->prepare(
        'INSERT INTO notifications (user_id, message, link) VALUES (?, ?, ?)'
    );
    $stmt->execute([$userId, $message, $link]);
}

/**
 * Count unread notifications for the current logged-in user.
 */
function unreadNotificationCount(): int
{
    if (!isLoggedIn()) return 0;

    $db   = getDB();
    $stmt = $db->prepare(
        'SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0'
    );
    $stmt->execute([$_SESSION['user_id']]);
    return (int) $stmt->fetchColumn();
}


// ---------------------------------------------------------------------------
// AUDIT LOGGING
// ---------------------------------------------------------------------------

/**
 * Log a user action to the audit_logs table.
 */
function auditLog(
    string  $action,
    ?string $targetTable = null,
    ?int    $targetId    = null
): void {
    $userId = $_SESSION['user_id'] ?? null;
    $ip     = $_SERVER['REMOTE_ADDR']     ?? null;
    $ua     = $_SERVER['HTTP_USER_AGENT'] ?? null;

    $db   = getDB();
    $stmt = $db->prepare(
        'INSERT INTO audit_logs (user_id, action, target_table, target_id, ip_address, user_agent)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([$userId, $action, $targetTable, $targetId, $ip, $ua]);
}


// ---------------------------------------------------------------------------
// DATE FORMATTING HELPERS
// ---------------------------------------------------------------------------

function formatDate(string $dateStr): string
{
    $d = new DateTime($dateStr);
    return $d->format('d M Y');
}

function formatDateTime(string $dateStr): string
{
    $d = new DateTime($dateStr);
    return $d->format('d M Y, g:i A');
}

/**
 * Returns a human-readable "time ago" string.
 */
function timeAgo(string $dateStr): string
{
    $diff = time() - strtotime($dateStr);

    return match (true) {
        $diff < 60     => 'Just now',
        $diff < 3600   => floor($diff / 60)   . ' min ago',
        $diff < 86400  => floor($diff / 3600)  . ' hr ago',
        $diff < 604800 => floor($diff / 86400) . ' days ago',
        default        => formatDate($dateStr),
    };
}
