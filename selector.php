<?php
/**
 * Step 2 + 3 — Glass selector + damage panel (sedan MVP)
 */
$bodyCategory = 'sedan';
$svgDir       = __DIR__ . '/vehicles/' . $bodyCategory;
$views        = ['right', 'front', 'left', 'back', 'top'];

$damageOptions = [
  ['value' => 'chip_crack',       'label' => 'Chip / Crack'],
  ['value' => 'shattered_broken', 'label' => 'Shattered / Broken'],
  ['value' => 'scratched',        'label' => 'Scratched'],
  ['value' => 'leaking',          'label' => 'Leaking'],
];

$featureOptions = [
  ['value' => 'heated_glass',  'label' => 'Heated Glass'],
  ['value' => 'adas_cameras',  'label' => 'ADAS / Cameras'],
  ['value' => 'acoustic_glass','label' => 'Acoustic Glass'],
  ['value' => 'factory_tint',  'label' => 'Factory Tint'],
  ['value' => 'rain_sensor',   'label' => 'Rain Sensor'],
  ['value' => 'not_sure',      'label' => 'Not sure?'],
];
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>AGX — Select Damaged Glass</title>
  <link rel="stylesheet" href="assets/css/form.css">
  <link rel="stylesheet" href="assets/css/selector.css">
</head>
<body>
  <div class="page-wrap">

    <h2 class="headline">Free Instant Estimates - No Commitment Required</h2>

    <!-- Stepper: step 1 + 2 done -->
    <div class="stepper" aria-label="Progress">
      <div class="step-circle done">
        <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><polyline points="3,8.5 6.5,12 13,4"/></svg>
      </div>
      <div class="step-circle done" aria-current="step">
        <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><polyline points="3,8.5 6.5,12 13,4"/></svg>
      </div>
      <div class="step-circle pending"><svg viewBox="0 0 16 16" fill="currentColor"><polygon points="5,3 13,8 5,13"/></svg></div>
      <div class="step-circle pending"><svg viewBox="0 0 16 16" fill="currentColor"><polygon points="5,3 13,8 5,13"/></svg></div>
      <div class="step-circle pending"><svg viewBox="0 0 16 16" fill="currentColor"><polygon points="5,3 13,8 5,13"/></svg></div>
    </div>

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
            <?php if (is_file($svgPath)) {
              echo file_get_contents($svgPath);
            } ?>
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

        <div class="panel-row">
          <div class="panel-label">Type of Damage</div>
          <div class="pill-group" id="damage-group">
            <?php foreach ($damageOptions as $opt): ?>
              <button type="button" class="pill" data-value="<?= htmlspecialchars($opt['value']) ?>"><?= htmlspecialchars(strtoupper($opt['label'])) ?></button>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="panel-row">
          <div class="panel-label">
            Special features<br>on this glass
            <span class="sub">(select all that apply)</span>
          </div>
          <div class="pill-group features" id="features-group">
            <?php foreach ($featureOptions as $opt): ?>
              <button type="button" class="pill" data-value="<?= htmlspecialchars($opt['value']) ?>"><?= htmlspecialchars($opt['label']) ?></button>
            <?php endforeach; ?>
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
          <button type="button" id="continue-btn" class="btn btn-primary">
            Continue
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
      <h3 id="reset-modal-title" class="modal-title">Reset all selected glasses?</h3>
      <p class="modal-message">This will clear every glass you've marked along with its damage type and features. This cannot be undone.</p>
      <div class="modal-actions">
        <button type="button" id="reset-cancel" class="btn btn-ghost">Cancel</button>
        <button type="button" id="reset-confirm" class="btn btn-danger">Reset</button>
      </div>
    </div>
  </div>

  <script src="assets/js/selector.js"></script>
</body>
</html>
