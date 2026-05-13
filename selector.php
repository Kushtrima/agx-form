<?php
/**
 * Step 2 + 3 — Glass selector + damage panel (sedan MVP)
 */
require_once __DIR__ . '/includes/config.php';

// Single source of truth for damage types, features, body categories, glass IDs.
$options = agx_options();

// Body category — whitelisted to prevent directory traversal once it becomes dynamic.
$requestedBody = $_GET['body'] ?? 'sedan';
$allowedBodies = $options['body_categories'] ?? ['sedan'];
$bodyCategory  = in_array($requestedBody, $allowedBodies, true) ? $requestedBody : 'sedan';

$svgDir = __DIR__ . '/vehicles/' . basename($bodyCategory);
$views  = ['right', 'front', 'left', 'back', 'top'];

$damageOptions    = $options['damage_types'] ?? [];
$featureOptions   = $options['features']     ?? [];
$crackSizeOptions = $options['crack_sizes']  ?? [];
$glassSpecificCfg = $options['glass_specific_options'] ?? [];

/**
 * Read an SVG file with a per-request static cache.
 * Each unique path is read at most once per request.
 */
function agx_svg(string $path): string {
    static $cache = [];
    if (!array_key_exists($path, $cache)) {
        $cache[$path] = is_file($path) ? (string)file_get_contents($path) : '';
    }
    return $cache[$path];
}
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>AGX — Select Damaged Glass</title>
  <?php
    $formCssV     = @filemtime(__DIR__ . '/assets/css/form.css');
    $selectorCssV = @filemtime(__DIR__ . '/assets/css/selector.css');
    $selectorJsV  = @filemtime(__DIR__ . '/assets/js/selector.js');
  ?>
  <link rel="stylesheet" href="assets/css/form.css?v=<?= $formCssV ?>">
  <link rel="stylesheet" href="assets/css/selector.css?v=<?= $selectorCssV ?>">
</head>
<body>
  <div class="page-wrap">

    <h2 class="headline">Free Instant Estimates - No Commitment Required</h2>

    <!-- Stepper: step 1 done, step 2 current -->
    <ol class="stepper" aria-label="Form progress">
      <li class="step-circle done" aria-label="Step 1: Vehicle info, completed">
        <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="3,8.5 6.5,12 13,4"/></svg>
      </li>
      <li class="step-circle done" aria-current="step" aria-label="Step 2: Glass selector, current">
        <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="3,8.5 6.5,12 13,4"/></svg>
      </li>
      <li class="step-circle pending" aria-label="Step 3, not started"><svg viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><polygon points="5,3 13,8 5,13"/></svg></li>
      <li class="step-circle pending" aria-label="Step 4, not started"><svg viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><polygon points="5,3 13,8 5,13"/></svg></li>
      <li class="step-circle pending" aria-label="Step 5, not started"><svg viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><polygon points="5,3 13,8 5,13"/></svg></li>
    </ol>

    <!-- Selector card -->
    <div class="card selector-card">

      <div class="header-row">
        <h2 id="view-title" class="view-title">RIGHT SIDE</h2>
        <div class="rotate-control">
          <span class="rotate-label">Rotate</span>
          <button type="button" id="rotate-prev" class="rotate-btn" aria-label="Rotate left">
            <svg viewBox="0 0 6467.51 9524.01" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
              <polygon points="6467.51,4782.6 0,9524.01 1313.17,4762 0,0"/>
            </svg>
          </button>
          <button type="button" id="rotate-next" class="rotate-btn" aria-label="Rotate right">
            <svg viewBox="0 0 6467.51 9524.01" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
              <polygon points="6467.51,4782.6 0,9524.01 1313.17,4762 0,0"/>
            </svg>
          </button>
        </div>
      </div>

      <div id="car-stage" class="car-stage">
        <?php foreach ($views as $i => $v):
          $svgPath = $svgDir . '/' . $v . '.svg';
          $isActive = ($i === 0) ? 'active' : '';
        ?>
          <div class="car-view <?= $isActive ?>" data-view="<?= $v ?>">
            <?= agx_svg($svgPath) ?>
          </div>
        <?php endforeach; ?>
      </div>

      <!-- Damage panel (hidden until window clicked) -->
      <div id="damage-panel" class="damage-panel">

        <div class="selected-list" id="selected-list" hidden>
          <span class="selected-list-label">Selected glasses:</span>
          <div class="selected-chips" id="selected-chips"></div>
        </div>

        <div class="editing-row" id="editing-row" hidden>
          <span class="editing-label">Editing:</span>
          <span class="editing-name" id="selected-name">Window</span>
          <button type="button" class="remove-x" id="remove-selected" aria-label="Remove this glass">×</button>
        </div>

        <div class="damage-grid" id="damage-grid">

          <div class="damage-column">
            <div class="panel-label">Type of Damage<span class="required-mark" aria-hidden="true">*</span></div>
            <div class="pill-group pill-group-column pill-group-stack" id="damage-group">
              <?php foreach ($damageOptions as $opt): ?>
                <button type="button" class="pill pill-stack" data-value="<?= htmlspecialchars($opt['value']) ?>">
                  <span class="pill-title"><?= htmlspecialchars($opt['label']) ?></span>
                  <?php if (!empty($opt['sub'])): ?>
                    <span class="pill-sub"><?= htmlspecialchars($opt['sub']) ?></span>
                  <?php endif; ?>
                </button>
              <?php endforeach; ?>
            </div>
          </div>

          <div class="damage-column">
            <div class="panel-label">Crack / chip size<span class="required-mark" aria-hidden="true">*</span></div>
            <div class="pill-group pill-group-column pill-group-stack" id="crack-size-group">
              <?php foreach ($crackSizeOptions as $opt): ?>
                <button type="button" class="pill pill-stack" data-value="<?= htmlspecialchars($opt['value']) ?>">
                  <span class="pill-title"><?= htmlspecialchars($opt['label']) ?></span>
                  <?php if (!empty($opt['sub'])): ?>
                    <span class="pill-sub"><?= htmlspecialchars($opt['sub']) ?></span>
                  <?php endif; ?>
                </button>
              <?php endforeach; ?>
            </div>
          </div>

          <div class="damage-column">
            <div class="panel-label">
              Special features on this glass<span class="required-mark" aria-hidden="true">*</span>
              <span class="sub">(select all that apply)</span>
            </div>
            <div class="pill-group pill-group-column features" id="features-group">
              <?php foreach ($featureOptions as $opt): ?>
                <button type="button" class="pill" data-value="<?= htmlspecialchars($opt['value']) ?>"><?= htmlspecialchars($opt['label']) ?></button>
              <?php endforeach; ?>
            </div>
          </div>

          <!-- 4th column — glass-specific options (conditional). Hidden by default;
               JS reveals it when the active glass has options defined. -->
          <div class="damage-column damage-column-glass-options" id="glass-options-column">
            <div class="panel-label" id="glass-options-label">Glass style<span class="required-mark" aria-hidden="true">*</span></div>
            <div class="pill-group pill-group-column pill-group-stack" id="glass-options-group"></div>
          </div>

        </div>

      </div>

      <div class="selector-actions">
        <button type="button" id="reset-btn" class="btn btn-text">
          <span class="arrow">
            <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
              <path d="M14 8a6 6 0 1 1 -6-6 6.3 6.3 0 0 1 4.5 1.85L14 5.5"/>
              <polyline points="14,2 14,5.5 10.5,5.5"/>
            </svg>
          </span>
          Reset
        </button>
        <div class="action-group">
          <button type="button" id="back-btn" class="btn btn-ghost">
            <span class="arrow">
              <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="13" y1="8" x2="3" y2="8"/>
                <polyline points="7,4 3,8 7,12"/>
              </svg>
            </span>
            Back
          </button>
          <button type="button" id="continue-btn" class="btn btn-primary" disabled>
            <span class="btn-label">Continue</span>
            <span class="btn-spinner" hidden>
              <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
                <circle cx="12" cy="12" r="9" stroke-opacity="0.25"/>
                <path d="M21 12a9 9 0 0 0 -9 -9"/>
              </svg>
            </span>
            <span class="arrow">
              <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="2" y1="8" x2="13" y2="8"/>
                <polyline points="9,4 13,8 9,12"/>
              </svg>
            </span>
          </button>
        </div>
      </div>

    </div>

  </div>

  <!-- Reset confirmation modal -->
  <div id="reset-modal" class="modal-overlay" role="dialog" aria-modal="true" aria-labelledby="reset-modal-title" hidden>
    <div class="modal-dialog">
      <div class="modal-icon">
        <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M21 12a9 9 0 1 1 -9 -9c2.52 0 4.93 1 6.74 2.74L21 8"/>
          <polyline points="21,3 21,8 16,8"/>
        </svg>
      </div>
      <h3 id="reset-modal-title" class="modal-title">Reset <span id="reset-modal-count">all</span> selected <span id="reset-modal-noun">glasses</span>?</h3>
      <p class="modal-message">This will clear every glass you've marked along with its damage type and features. This cannot be undone.</p>
      <div class="modal-actions">
        <button type="button" id="reset-cancel" class="btn btn-ghost">Cancel</button>
        <button type="button" id="reset-confirm" class="btn btn-danger">Reset</button>
      </div>
    </div>
  </div>

  <script type="application/json" id="agx-glass-options-data"><?= json_encode($glassSpecificCfg, JSON_UNESCAPED_UNICODE) ?></script>
  <script src="assets/js/selector.js?v=<?= $selectorJsV ?>"></script>
</body>
</html>
