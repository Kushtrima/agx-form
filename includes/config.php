<?php
/**
 * Application configuration.
 * Override values for production via environment variables.
 */

define('AGX_BASE_DIR',    __DIR__ . '/..');
define('AGX_UPLOAD_DIR',  AGX_BASE_DIR . '/uploads');
define('AGX_DATA_DIR',    AGX_BASE_DIR . '/data');

// Submissions log (used until a real DB is wired up)
define('AGX_SUBMISSIONS_FILE', AGX_DATA_DIR . '/submissions.jsonl');

// Database (placeholder — fill in when connecting MySQL)
define('AGX_DB_HOST', getenv('AGX_DB_HOST') ?: 'localhost');
define('AGX_DB_NAME', getenv('AGX_DB_NAME') ?: 'agx_form');
define('AGX_DB_USER', getenv('AGX_DB_USER') ?: 'root');
define('AGX_DB_PASS', getenv('AGX_DB_PASS') ?: '');

// Admin login. Override in production via env vars.
// Default credentials are a deliberate placeholder — change these before going live.
define('AGX_ADMIN_USER', getenv('AGX_ADMIN_USER') ?: 'admin');
define('AGX_ADMIN_PASS', getenv('AGX_ADMIN_PASS') ?: 'change-me');
