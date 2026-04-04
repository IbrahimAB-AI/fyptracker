<?php
/**
 * FYPTracker — Login Handler
 * auth/login.php
 *
 * POST: processes login form
 * GET:  redirects to index.php (login form lives there)
 */

require_once __DIR__ . '/../includes/functions.php';

// If already logged in, redirect to their dashboard
if (isLoggedIn()) {
    redirect('../' . dashboardUrl($_SESSION['role']));
}

// Only handle POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('../index.php');
}

// ---- CSRF check ----
verifyCsrf();

// ---- Collect & validate input ----
$email    = post('email');
$password = post('password');
$errors   = [];

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Please enter a valid email address.';
}
if (empty($password)) {
    $errors[] = 'Please enter your password.';
}

if (!empty($errors)) {
    setFlash('error', implode(' ', $errors));
    redirect('../index.php');
}

// ---- Lookup user ----
$db   = getDB();
$stmt = $db->prepare(
    'SELECT user_id, full_name, email, password_hash, role, is_active
       FROM users
      WHERE email = ?
      LIMIT 1'
);
$stmt->execute([$email]);
$user = $stmt->fetch();

// Verify password (use constant-time comparison via password_verify)
if (!$user || !password_verify($password, $user['password_hash'])) {
    // Generic message — do not hint which field is wrong
    setFlash('error', 'Invalid email or password. Please try again.');
    auditLog('login_failed');
    redirect('../index.php');
}

// Check account is active
if (!$user['is_active']) {
    setFlash('error', 'Your account has been suspended. Please contact the FYP Coordinator.');
    auditLog('login_blocked_suspended');
    redirect('../index.php');
}

// ---- Build session ----
regenerateSession();

$_SESSION['user_id']   = (int)  $user['user_id'];
$_SESSION['full_name'] = $user['full_name'];
$_SESSION['email']     = $user['email'];
$_SESSION['role']      = $user['role'];

// Rehash password if bcrypt cost has changed (future-proofing)
if (password_needs_rehash($user['password_hash'], PASSWORD_BCRYPT)) {
    $newHash = password_hash($password, PASSWORD_BCRYPT);
    $upd     = $db->prepare('UPDATE users SET password_hash = ? WHERE user_id = ?');
    $upd->execute([$newHash, $user['user_id']]);
}

auditLog('login', 'users', (int) $user['user_id']);

// ---- Role-based redirect ----
redirect('../' . dashboardUrl($user['role']));
