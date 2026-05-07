<?php
/**
 * DB connection (PDO). Lazy: only connects when get_db() is first called.
 * For MVP we are not requiring a database — submissions can be logged to
 * a JSONL file via includes/config.php. Wire this up when MySQL is ready.
 */

require_once __DIR__ . '/config.php';

function get_db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', AGX_DB_HOST, AGX_DB_NAME);
        $pdo = new PDO($dsn, AGX_DB_USER, AGX_DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}
