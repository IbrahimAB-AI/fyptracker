<?php
/**
 * FYPTracker — Logout
 * auth/logout.php
 */

require_once __DIR__ . '/../includes/functions.php';

if (isLoggedIn()) {
    auditLog('logout');
}

// Destroy everything
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(
        session_name(), '', time() - 42000,
        $p['path'], $p['domain'], $p['secure'], $p['httponly']
    );
}
session_destroy();

redirect('../index.php?message=You+have+been+logged+out+successfully.');
