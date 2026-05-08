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
    error_log('[agx submit] malformed payload: ' . substr((string)$raw, 0, 500));
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Missing vehicle or damages']);
    exit;
}

// ---------- Load whitelists ----------
$options = json_decode(@file_get_contents(AGX_DATA_DIR . '/options.json'), true) ?: [];
$validDamageTypes = array_column($options['damage_types'] ?? [], 'value');
$validFeatures    = array_column($options['features']     ?? [], 'value');
$validBodies      = $options['body_categories']           ?? ['sedan'];
$glassesByBody    = $options['glasses_by_body']           ?? [];

$vehicles = json_decode(@file_get_contents(AGX_DATA_DIR . '/vehicles.json'), true) ?: [];
$validYears  = array_map('strval', $vehicles['years'] ?? []);
$validBrands = array_keys($vehicles['brands'] ?? []);

// ---------- Validate vehicle ----------
$vehicle = $payload['vehicle'];

$bodyStyle = is_string($vehicle['body_style'] ?? null) ? $vehicle['body_style'] : '';
if (!in_array($bodyStyle, $validBodies, true)) {
    error_log('[agx submit] invalid body_style: ' . $bodyStyle);
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Unsupported body style']);
    exit;
}

$year  = is_string($vehicle['year']  ?? null) ? trim($vehicle['year'])  : '';
$brand = is_string($vehicle['brand'] ?? null) ? trim($vehicle['brand']) : '';
$model = is_string($vehicle['model'] ?? null) ? trim($vehicle['model']) : '';
$vin   = is_string($vehicle['vin']   ?? null) ? trim($vehicle['vin'])   : '';

// Year must be one of the listed years.
if ($validYears && !in_array($year, $validYears, true)) {
    error_log('[agx submit] invalid year: ' . $year);
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid year']);
    exit;
}

// Brand must be in the catalog (if catalog is non-empty).
if ($validBrands && !in_array($brand, $validBrands, true)) {
    error_log('[agx submit] invalid brand: ' . $brand);
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid brand']);
    exit;
}

// Model gets a soft check (free text but bounded).
if ($model === '' || strlen($model) > 80) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid model']);
    exit;
}

// VIN: optional, max 17 chars, ASCII alphanumerics only.
if ($vin !== '') {
    if (strlen($vin) > 17 || !preg_match('/^[A-HJ-NPR-Z0-9]+$/i', $vin)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Invalid VIN']);
        exit;
    }
}

// ---------- Validate damages ----------
$damagesIn  = $payload['damages'];
if (!is_array($damagesIn) || count($damagesIn) === 0 || count($damagesIn) > 50) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid damage list']);
    exit;
}

$validGlasses = $glassesByBody[$bodyStyle] ?? [];
$cleanDamages = [];

foreach ($damagesIn as $glassId => $rec) {
    if (!is_string($glassId) || ($validGlasses && !in_array($glassId, $validGlasses, true))) {
        error_log('[agx submit] unknown glass id: ' . $glassId);
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Unknown glass: ' . htmlspecialchars($glassId)]);
        exit;
    }
    if (!is_array($rec)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Damage entry must be an object']);
        exit;
    }

    $name        = is_string($rec['name'] ?? null) ? substr($rec['name'], 0, 80) : $glassId;
    $damageType  = $rec['damage_type'] ?? null;
    $featuresIn  = is_array($rec['features'] ?? null) ? $rec['features'] : [];

    if ($damageType !== null && !in_array($damageType, $validDamageTypes, true)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Invalid damage type for ' . $glassId]);
        exit;
    }

    $cleanFeatures = [];
    foreach ($featuresIn as $f) {
        if (is_string($f) && in_array($f, $validFeatures, true) && !in_array($f, $cleanFeatures, true)) {
            $cleanFeatures[] = $f;
        }
    }

    $cleanDamages[$glassId] = [
        'name'        => $name,
        'damage_type' => $damageType,
        'features'    => $cleanFeatures,
    ];
}

// ---------- Build the record ----------
$record = [
    'id'         => uniqid('agx_', true),
    'created_at' => date('c'),
    'vehicle'    => [
        'selected_year'       => $year,
        'selected_brand'      => $brand,
        'selected_model'      => $model,
        'selected_body_style' => $bodyStyle,
        'vin_optional'        => $vin,
    ],
    'damages'    => $cleanDamages,
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
    error_log('[agx submit] persistence failed for ' . $record['id'] . ' to ' . AGX_SUBMISSIONS_FILE);
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Failed to persist submission']);
    exit;
}

echo json_encode(['ok' => true, 'id' => $record['id']]);
