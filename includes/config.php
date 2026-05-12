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

// Hard size cap on POST body for the JSON submit endpoint (defends against
// memory-DoS from a malicious client; default 256 KB is plenty for a quote).
define('AGX_MAX_SUBMIT_BYTES', 256 * 1024);

/**
 * Read and cache data/options.json. Per-request memoised so multiple includes
 * (PHP page + JS bootstrap + submit.php during the same request) don't
 * re-read the file.
 */
function agx_options(): array {
    static $cached = null;
    if ($cached === null) {
        $raw = @file_get_contents(AGX_DATA_DIR . '/options.json');
        $cached = $raw ? (json_decode($raw, true) ?: []) : [];
    }
    return $cached;
}

/**
 * Same idea for data/vehicles.json.
 */
function agx_vehicles(): array {
    static $cached = null;
    if ($cached === null) {
        $raw = @file_get_contents(AGX_DATA_DIR . '/vehicles.json');
        $cached = $raw ? (json_decode($raw, true) ?: []) : [];
    }
    return $cached;
}

/**
 * Lightweight CSRF defense for the submit / upload endpoints. The form is
 * a same-origin POST initiated by JavaScript on our own pages, so any POST
 * lacking a matching Origin or Referer header is almost certainly a CSRF
 * attempt from another site. This is a defense-in-depth measure — it does
 * NOT replace a real per-session CSRF token, but it shuts down the easy
 * attack and is appropriate for the MVP.
 *
 * @return bool  true if the request looks same-origin; false otherwise.
 */
function agx_same_origin(): bool {
    // Build the expected scheme://host
    $scheme = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['SERVER_PORT'] ?? null) == 443)
        ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? '';
    if ($host === '') return false;
    $self = $scheme . '://' . $host;

    $origin  = $_SERVER['HTTP_ORIGIN']  ?? '';
    $referer = $_SERVER['HTTP_REFERER'] ?? '';

    if ($origin !== '') {
        return strcasecmp($origin, $self) === 0;
    }
    if ($referer !== '') {
        // Match scheme://host prefix; ignore path and query.
        return strncasecmp($referer, $self . '/', strlen($self) + 1) === 0;
    }
    // No Origin AND no Referer — extremely unusual for a real browser POST.
    return false;
}
