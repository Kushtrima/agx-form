<?php
/**
 * Receive the final glass-damage submission and persist it.
 *
 * For MVP this writes one JSON line per submission to data/submissions.jsonl.
 * Swap in a PDO insert via includes/db.php once a MySQL schema is set up.
 */

require_once __DIR__ . '/includes/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

$raw = file_get_contents('php://input');
$payload = json_decode($raw, true);

if (!is_array($payload) || empty($payload['vehicle']) || empty($payload['damages'])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Missing vehicle or damages']);
    exit;
}

$vehicle = $payload['vehicle'];
$damages = $payload['damages'];

$record = [
    'id'         => uniqid('agx_', true),
    'created_at' => date('c'),
    'vehicle'    => [
        'selected_year'       => isset($vehicle['year'])       ? (string)$vehicle['year']       : null,
        'selected_brand'      => isset($vehicle['brand'])      ? (string)$vehicle['brand']      : null,
        'selected_model'      => isset($vehicle['model'])      ? (string)$vehicle['model']      : null,
        'selected_body_style' => isset($vehicle['body_style']) ? (string)$vehicle['body_style'] : null,
        'vin_optional'        => isset($vehicle['vin'])        ? (string)$vehicle['vin']        : null,
    ],
    'damages'    => $damages,
];

if (!is_dir(AGX_DATA_DIR)) {
    @mkdir(AGX_DATA_DIR, 0775, true);
}

$ok = @file_put_contents(
    AGX_SUBMISSIONS_FILE,
    json_encode($record, JSON_UNESCAPED_UNICODE) . PHP_EOL,
    FILE_APPEND | LOCK_EX
);

if ($ok === false) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Failed to persist submission']);
    exit;
}

echo json_encode(['ok' => true, 'id' => $record['id']]);
