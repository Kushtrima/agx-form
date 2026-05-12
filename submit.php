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

if (!agx_same_origin()) {
    error_log('[agx submit] cross-origin POST blocked. Origin=' . ($_SERVER['HTTP_ORIGIN'] ?? '') . ' Referer=' . ($_SERVER['HTTP_REFERER'] ?? ''));
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Forbidden']);
    exit;
}

$raw = file_get_contents('php://input');
if (strlen((string)$raw) > AGX_MAX_SUBMIT_BYTES) {
    error_log('[agx submit] payload too large: ' . strlen((string)$raw) . ' bytes');
    http_response_code(413);
    echo json_encode(['ok' => false, 'error' => 'Payload too large']);
    exit;
}
$payload = json_decode($raw, true);

if (!is_array($payload) || empty($payload['vehicle']) || empty($payload['damages'])) {
    error_log('[agx submit] malformed payload: ' . substr((string)$raw, 0, 500));
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Missing vehicle or damages']);
    exit;
}

// ---------- Load whitelists ----------
$options = agx_options();
$validDamageTypes  = array_column($options['damage_types']  ?? [], 'value');
$validFeatures     = array_column($options['features']      ?? [], 'value');
$validCrackSizes   = array_column($options['crack_sizes']   ?? [], 'value');
$validBodies       = $options['body_categories']            ?? ['sedan'];
$glassesByBody     = $options['glasses_by_body']            ?? [];
$glassSpecificCfg  = $options['glass_specific_options']     ?? [];
$validServiceModes = array_column($options['service_modes'] ?? [], 'value');
$validPaymentModes = array_column($options['payment_modes'] ?? [], 'value');
$validTimeSlots    = array_column($options['time_slots']    ?? [], 'value');
$validProviders    = $options['insurance_providers']        ?? [];

$vehicles = agx_vehicles();
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

    $name       = is_string($rec['name'] ?? null) ? substr($rec['name'], 0, 80) : $glassId;
    $damageType = $rec['damage_type'] ?? null;
    $crackSize  = $rec['crack_size']  ?? null;
    $featuresIn = is_array($rec['features'] ?? null) ? $rec['features'] : [];
    $notes      = is_string($rec['notes'] ?? null) ? substr(trim($rec['notes']), 0, 500) : '';
    $photosIn   = is_array($rec['photos']   ?? null) ? $rec['photos']   : [];

    if ($damageType !== null && !in_array($damageType, $validDamageTypes, true)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Invalid damage type for ' . $glassId]);
        exit;
    }

    if ($crackSize !== null && !in_array($crackSize, $validCrackSizes, true)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Invalid crack size for ' . $glassId]);
        exit;
    }

    $cleanFeatures = [];
    foreach ($featuresIn as $f) {
        if (is_string($f) && in_array($f, $validFeatures, true) && !in_array($f, $cleanFeatures, true)) {
            $cleanFeatures[] = $f;
        }
    }

    // Validate photo URLs: must be relative paths under uploads/ that exist on disk.
    $cleanPhotos = [];
    foreach ($photosIn as $p) {
        if (!is_array($p) || !is_string($p['url'] ?? null)) continue;
        $url = $p['url'];
        // Relative path only, no scheme, no parent traversal, must start with uploads/.
        if (preg_match('#^uploads/[A-Za-z0-9_\-/\.]+$#', $url) !== 1) continue;
        if (strpos($url, '..') !== false) continue;
        $absolute = AGX_BASE_DIR . '/' . $url;
        if (!is_file($absolute)) continue;
        $cleanPhotos[] = [
            'url'  => $url,
            'name' => is_string($p['name'] ?? null) ? substr($p['name'], 0, 200) : '',
            'size' => is_int($p['size'] ?? null)    ? max(0, (int)$p['size'])    : 0,
        ];
        if (count($cleanPhotos) >= 8) break;   // cap per-glass photos
    }

    $cleanRec = [
        'name'        => $name,
        'damage_type' => $damageType,
        'crack_size'  => $crackSize,
        'features'    => $cleanFeatures,
        'notes'       => $notes,
        'photos'      => $cleanPhotos,
    ];

    // Glass-specific extra field (e.g., front_windshield → glass_style).
    // Validate against this glass's own whitelist, drop anything unknown.
    if (isset($glassSpecificCfg[$glassId])) {
        $cfg            = $glassSpecificCfg[$glassId];
        $field          = is_string($cfg['field'] ?? null) ? $cfg['field'] : null;
        $allowedValues  = array_column($cfg['options'] ?? [], 'value');
        $incomingValue  = ($field !== null && isset($rec[$field])) ? $rec[$field] : null;
        if ($field !== null) {
            if ($incomingValue !== null && !in_array($incomingValue, $allowedValues, true)) {
                error_log('[agx submit] invalid glass-specific value for ' . $glassId . '.' . $field . ': ' . $incomingValue);
                http_response_code(400);
                echo json_encode(['ok' => false, 'error' => 'Invalid value for ' . $glassId]);
                exit;
            }
            $cleanRec[$field] = $incomingValue;
        }
    }

    $cleanDamages[$glassId] = $cleanRec;
}

// ---------- Validate service block (optional — older clients may omit it) ----------
$serviceIn = is_array($payload['service'] ?? null) ? $payload['service'] : [];

$serviceMode      = $serviceIn['service_mode']       ?? null;
$paymentMode      = $serviceIn['payment_mode']       ?? null;
$insuranceProvider= $serviceIn['insurance_provider'] ?? null;
$preferredDate    = $serviceIn['preferred_date']     ?? null;
$preferredTime    = $serviceIn['preferred_time']     ?? null;

if ($serviceMode !== null && !in_array($serviceMode, $validServiceModes, true)) {
    error_log('[agx submit] invalid service_mode: ' . $serviceMode);
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid service mode']);
    exit;
}
if ($paymentMode !== null && !in_array($paymentMode, $validPaymentModes, true)) {
    error_log('[agx submit] invalid payment_mode: ' . $paymentMode);
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid payment mode']);
    exit;
}
if ($preferredTime !== null && !in_array($preferredTime, $validTimeSlots, true)) {
    error_log('[agx submit] invalid preferred_time: ' . $preferredTime);
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid preferred time']);
    exit;
}
if ($insuranceProvider !== null && $insuranceProvider !== '' && !in_array($insuranceProvider, $validProviders, true)) {
    error_log('[agx submit] unknown insurance_provider: ' . $insuranceProvider);
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Unknown insurance provider']);
    exit;
}
if ($preferredDate !== null && $preferredDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$preferredDate)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid preferred date']);
    exit;
}
// Clear insurance_provider if payment_mode isn't insurance — keeps the record tidy.
if ($paymentMode !== 'insurance') $insuranceProvider = null;

$service = [
    'service_mode'       => $serviceMode,
    'payment_mode'       => $paymentMode,
    'insurance_provider' => $insuranceProvider,
    'preferred_date'     => $preferredDate,
    'preferred_time'     => $preferredTime,
];

// ---------- Validate contact block ----------
$contactIn = is_array($payload['contact'] ?? null) ? $payload['contact'] : [];

$firstName = is_string($contactIn['first_name'] ?? null) ? trim($contactIn['first_name']) : '';
$lastName  = is_string($contactIn['last_name']  ?? null) ? trim($contactIn['last_name'])  : '';
$email     = is_string($contactIn['email']      ?? null) ? trim($contactIn['email'])      : '';
$phone     = is_string($contactIn['phone']      ?? null) ? trim($contactIn['phone'])      : '';
$contactZip= is_string($contactIn['zip']        ?? null) ? trim($contactIn['zip'])        : '';
$contactNotes = is_string($contactIn['notes']   ?? null) ? substr(trim($contactIn['notes']), 0, 500) : '';

if ($firstName === '' || strlen($firstName) > 80) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid first name']);
    exit;
}
if ($lastName === '' || strlen($lastName) > 80) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid last name']);
    exit;
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 200) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid email']);
    exit;
}
$phoneDigits = preg_replace('/\D+/', '', $phone);
if (strlen($phoneDigits) < 7 || strlen($phoneDigits) > 20) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid phone number']);
    exit;
}
if ($contactZip === '' || !preg_match('/^[A-Za-z0-9 \-]{3,12}$/', $contactZip)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid ZIP / postal code']);
    exit;
}

$contact = [
    'first_name' => $firstName,
    'last_name'  => $lastName,
    'email'      => $email,
    'phone'      => $phone,
    'zip'        => $contactZip,
    'notes'      => $contactNotes,
];

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
    'service'    => $service,
    'contact'    => $contact,
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
