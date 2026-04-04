<?php
/**
 * FYPTracker — Registration Handler
 * auth/register.php
 *
 * Students self-register; admin assigns supervisor role via admin panel.
 * Only 'student' role is available via self-registration.
 */

require_once __DIR__ . '/../includes/functions.php';

// Redirect if already logged in
if (isLoggedIn()) {
    redirect('../' . dashboardUrl($_SESSION['role']));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('../index.php?tab=register');
}

// ---- CSRF ----
verifyCsrf();

// ---- Collect input ----
$fullName     = post('full_name');
$email        = post('email');
$matricNumber = post('matric_number');
$password     = post('password');
$confirmPass  = post('confirm_password');
$errors       = [];

// ---- Validate ----
if (mb_strlen($fullName) < 3 || mb_strlen($fullName) > 150) {
    $errors[] = 'Full name must be between 3 and 150 characters.';
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Please enter a valid email address.';
}
if (!preg_match('/^FUL\/CS\/\d{4}\/\d{3,4}$/i', $matricNumber)) {
    $errors[] = 'Matric number format must be FUL/CS/YYYY/NNN (e.g. FUL/CS/2021/001).';
}
if (mb_strlen($password) < 8) {
    $errors[] = 'Password must be at least 8 characters.';
}
if ($password !== $confirmPass) {
    $errors[] = 'Passwords do not match.';
}

if (!empty($errors)) {
    setFlash('error', implode('<br>', $errors));
    redirect('../index.php?tab=register');
}

// ---- Check uniqueness ----
$db = getDB();

$chkEmail = $db->prepare('SELECT user_id FROM users WHERE email = ? LIMIT 1');
$chkEmail->execute([$email]);
if ($chkEmail->fetch()) {
    setFlash('error', 'An account with this email already exists.');
    redirect('../index.php?tab=register');
}

$chkMatric = $db->prepare('SELECT student_id FROM students WHERE matric_number = ? LIMIT 1');
$chkMatric->execute([strtoupper($matricNumber)]);
if ($chkMatric->fetch()) {
    setFlash('error', 'This matric number is already registered.');
    redirect('../index.php?tab=register');
}

// ---- Insert user & student profile in a transaction ----
try {
    $db->beginTransaction();

    $hash = password_hash($password, PASSWORD_BCRYPT);

    $insUser = $db->prepare(
        'INSERT INTO users (full_name, email, password_hash, role)
         VALUES (?, ?, ?, ?)'
    );
    $insUser->execute([$fullName, $email, $hash, 'student']);
    $userId = (int) $db->lastInsertId();

    $insStudent = $db->prepare(
        'INSERT INTO students (student_id, matric_number, department, level)
         VALUES (?, ?, ?, ?)'
    );
    $insStudent->execute([$userId, strtoupper($matricNumber), 'Computer Science', 400]);

    $db->commit();

    // Notify admin
    $adminStmt = $db->prepare(
        'SELECT user_id FROM users WHERE role = ? LIMIT 1'
    );
    $adminStmt->execute(['admin']);
    $admin = $adminStmt->fetch();
    if ($admin) {
        createNotification(
            $admin['user_id'],
            "New student registered: {$fullName} ({$matricNumber}). Please assign a supervisor.",
            'admin/assign_supervisors.php'
        );
    }

    auditLog('register', 'users', $userId);

    setFlash('success', 'Registration successful! You can now log in.');
    redirect('../index.php');

} catch (PDOException $e) {
    $db->rollBack();
    error_log('Registration error: ' . $e->getMessage());
    setFlash('error', 'Registration failed due to a server error. Please try again.');
    redirect('../index.php?tab=register');
}
