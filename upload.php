<?php
/**
 * Photo upload endpoint.
 *
 * Accepts a multipart upload with a single "photo" field.
 * Stores the file under uploads/<YYYY>/<MM>/ with a unique generated name.
 * Returns JSON with the relative path so the client can store it on the
 * damage record and reference it later.
 */

require_once __DIR__ . '/includes/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

if (!agx_same_origin()) {
    error_log('[agx upload] cross-origin POST blocked. Origin=' . ($_SERVER['HTTP_ORIGIN'] ?? '') . ' Referer=' . ($_SERVER['HTTP_REFERER'] ?? ''));
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Forbidden']);
    exit;
}

if (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
    $err = $_FILES['photo']['error'] ?? UPLOAD_ERR_NO_FILE;
    error_log('[agx upload] upload error code: ' . $err);
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'No file received']);
    exit;
}

$file = $_FILES['photo'];

// Hard size cap: 6 MB.
if ($file['size'] > 6 * 1024 * 1024) {
    http_response_code(413);
    echo json_encode(['ok' => false, 'error' => 'File too large (max 6 MB)']);
    exit;
}

// MIME whitelist via finfo (don't trust the client-provided type).
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime  = $finfo ? finfo_file($finfo, $file['tmp_name']) : null;
if ($finfo) finfo_close($finfo);

$allowed = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
    'image/heic' => 'heic',
    'image/heif' => 'heif',
];

if (!isset($allowed[$mime])) {
    error_log('[agx upload] rejected mime: ' . $mime);
    http_response_code(415);
    echo json_encode(['ok' => false, 'error' => 'Only JPEG / PNG / WebP / HEIC images are accepted']);
    exit;
}

$ext    = $allowed[$mime];
$ym     = date('Y') . '/' . date('m');
$dir    = AGX_UPLOAD_DIR . '/' . $ym;
$fname  = uniqid('photo_', true) . '.' . $ext;
$dest   = $dir . '/' . $fname;
$relUrl = 'uploads/' . $ym . '/' . $fname;

if (!is_dir($dir) && !@mkdir($dir, 0775, true)) {
    error_log('[agx upload] failed to mkdir ' . $dir);
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Failed to prepare storage']);
    exit;
}

if (!move_uploaded_file($file['tmp_name'], $dest)) {
    error_log('[agx upload] move_uploaded_file failed: ' . $dest);
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Failed to store file']);
    exit;
}

echo json_encode([
    'ok'   => true,
    'url'  => $relUrl,
    'name' => $file['name'],
    'size' => $file['size'],
    'mime' => $mime,
]);
