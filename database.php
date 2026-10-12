<?php
/*
 * Database connection settings.
 *
 * These are the default XAMPP settings: user "root" with an empty
 * password. If you set a MySQL password in XAMPP, type it in DB_PASS.
 * If your MySQL runs on another port (for example 3307), change DB_PORT.
 */
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'medicare_db');
define('DB_USER', 'root');
define('DB_PASS', '');

// Indian time, so "today" in the queue matches the clock on the wall.
date_default_timezone_set('Asia/Kolkata');

/*
 * Returns one shared PDO connection.
 * PDO is used everywhere in this project, always with prepared statements.
 */
function db()
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // errors become exceptions
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // rows come back as arrays
        PDO::ATTR_EMULATE_PREPARES   => false,                  // real prepared statements
    ];

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    } catch (PDOException $e) {
        // Write the real reason to the PHP error log, show a friendly page.
        error_log('MediCare DB connection failed: ' . $e->getMessage());
        http_response_code(500);
        echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Database error</title>'
           . '<style>body{font-family:Segoe UI,Arial,sans-serif;background:#f3f6fa;color:#16324f;padding:40px}'
           . '.box{max-width:620px;margin:auto;background:#fff;border-radius:14px;padding:28px;box-shadow:0 4px 18px rgba(0,0,0,.08)}'
           . 'h1{margin-top:0;font-size:22px}li{margin:6px 0}</style></head><body><div class="box">'
           . '<h1>Cannot connect to the database</h1>'
           . '<p>MediCare could not reach MySQL. Please check:</p><ol>'
           . '<li>MySQL is started (green) in the XAMPP Control Panel.</li>'
           . '<li>The database <b>medicare_db</b> was imported in phpMyAdmin.</li>'
           . '<li>The user, password and port in <b>config/database.php</b> are correct.</li>'
           . '</ol></div></body></html>';
        exit;
    }
    return $pdo;
}
