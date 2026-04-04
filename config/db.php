<?php
/**
 * FYPTracker — PDO Database Connection
 * config/db.php
 *
 * Returns a singleton PDO instance.
 * All queries in the system MUST use this connection and prepared statements.
 */

define('DB_HOST', 'localhost');
define('DB_NAME', 'fyptracker');
define('DB_USER', 'root');          // Laragon default
define('DB_PASS', '');              // Laragon default (empty)
define('DB_CHARSET', 'utf8mb4');

/**
 * Returns a shared PDO connection instance.
 *
 * @return PDO
 */
function getDB(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            DB_HOST,
            DB_NAME,
            DB_CHARSET
        );

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,  // Use real prepared statements
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // Never expose DB credentials in production
            error_log('DB connection failed: ' . $e->getMessage());
            die(json_encode([
                'error' => 'Database connection failed. Please contact the administrator.'
            ]));
        }
    }

    return $pdo;
}
