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
          <button type="button" id="rotate-prev" class="rotate-btn" aria-label="Previous view">
            <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <line x1="13" y1="8" x2="3" y2="8"/>
              <polyline points="7,4 3,8 7,12"/>
            </svg>
          </button>
          <button type="button" id="rotate-next" class="rotate-btn" aria-label="Next view">
            <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <line x1="3" y1="8" x2="13" y2="8"/>
              <polyline points="9,4 13,8 9,12"/>
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

  <script src="assets/js/selector.js"></script>
</body>
</html>
