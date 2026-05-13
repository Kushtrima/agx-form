<?php
/**
 * Step 3 — Service & scheduling
 * How they want to be served, payment method, preferred date / time.
 */
require_once __DIR__ . '/includes/config.php';

$options = agx_options();

$serviceModes       = $options['service_modes']       ?? [];
$paymentModes       = $options['payment_modes']       ?? [];
$timeSlots          = $options['time_slots']          ?? [];
$insuranceProviders = $options['insurance_providers'] ?? [];

// Inline SVG icons (line-art, blue stroke, matches the rest of the app).
$icons = [
  'mobile'    => '<svg viewBox="0 0 28 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="1.5" y="6" width="15" height="11" rx="1.5"/><path d="M16.5 9.5 H21 L25.5 14.5 V17 H16.5 Z"/><circle cx="7" cy="19.5" r="2.2"/><circle cx="20" cy="19.5" r="2.2"/></svg>',
  'shop'      => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M3 10 L3 21 L21 21 L21 10"/><path d="M2 10 L4 4 H20 L22 10 Z"/><path d="M9 21 L9 14 L15 14 L15 21"/></svg>',
  'insurance' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2 L20 5.5 V12 C20 16.5 16.5 20.5 12 22 C7.5 20.5 4 16.5 4 12 V5.5 Z"/><polyline points="8.5,12 11,14.5 15.5,9.5"/></svg>',
  'wallet'    => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="2.5" y="6.5" width="19" height="13" rx="2"/><path d="M2.5 10.5 H21.5"/><circle cx="17" cy="15" r="1.2" fill="currentColor"/></svg>',
  'info'      => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><line x1="12" y1="11" x2="12" y2="16"/><circle cx="12" cy="8" r="0.6" fill="currentColor" stroke="none"/></svg>',
  'calendar'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="16" rx="2"/><line x1="3" y1="10" x2="21" y2="10"/><line x1="8" y1="3" x2="8" y2="7"/><line x1="16" y1="3" x2="16" y2="7"/></svg>',
  'clock'     => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><polyline points="12,7 12,12 15.5,14"/></svg>',
];

$formCssV     = @filemtime(__DIR__ . '/assets/css/form.css');
$selectorCssV = @filemtime(__DIR__ . '/assets/css/selector.css');
$serviceCssV  = @filemtime(__DIR__ . '/assets/css/service.css');
$serviceJsV   = @filemtime(__DIR__ . '/assets/js/service.js');

$iconKeyFor = ['mobile' => 'mobile', 'shop' => 'shop', 'insurance' => 'insurance', 'out_of_pocket' => 'wallet'];
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>AGX — Service &amp; Scheduling</title>
  <link rel="stylesheet" href="assets/css/form.css?v=<?= $formCssV ?>">
  <link rel="stylesheet" href="assets/css/selector.css?v=<?= $selectorCssV ?>">
  <link rel="stylesheet" href="assets/css/service.css?v=<?= $serviceCssV ?>">
</head>
<body>
  <div class="page-wrap">

    <h2 class="headline">Free Instant Estimates - No Commitment Required</h2>

    <ol class="stepper" aria-label="Form progress">
      <li class="step-circle done"><svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="3,8.5 6.5,12 13,4"/></svg></li>
      <li class="step-circle done"><svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="3,8.5 6.5,12 13,4"/></svg></li>
      <li class="step-circle done" aria-current="step"><svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="3,8.5 6.5,12 13,4"/></svg></li>
      <li class="step-circle pending"><svg viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><polygon points="5,3 13,8 5,13"/></svg></li>
      <li class="step-circle pending"><svg viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><polygon points="5,3 13,8 5,13"/></svg></li>
    </ol>

    <div class="card service-card">

      <header class="service-header">
        <h1 class="service-title">Service &amp; scheduling</h1>
        <p class="service-subtitle">We work around your schedule — home, work, or our shop.</p>
      </header>

      <!-- 1. Service mode -->
      <section class="service-section">
        <h2 class="service-section-title">How would you like to be served?<span class="required-mark" aria-hidden="true">*</span></h2>
        <div class="choice-grid" id="service-mode-group">
          <?php foreach ($serviceModes as $opt):
            $iconKey = $iconKeyFor[$opt['value']] ?? null;
          ?>
            <button type="button" class="choice-card" data-value="<?= htmlspecialchars($opt['value']) ?>">
              <span class="choice-icon" aria-hidden="true"><?= $iconKey ? $icons[$iconKey] : '' ?></span>
              <span class="choice-body">
                <span class="choice-title"><?= htmlspecialchars($opt['label']) ?></span>
                <?php if (!empty($opt['sub'])): ?>
                  <span class="choice-sub"><?= htmlspecialchars($opt['sub']) ?></span>
                <?php endif; ?>
              </span>
            </button>
          <?php endforeach; ?>
        </div>
      </section>

      <!-- 2. Payment / coverage -->
      <section class="service-section">
        <h2 class="service-section-title">Payment / coverage<span class="required-mark" aria-hidden="true">*</span></h2>
        <div class="choice-grid" id="payment-mode-group">
          <?php foreach ($paymentModes as $opt):
            $iconKey = $iconKeyFor[$opt['value']] ?? null;
          ?>
            <button type="button" class="choice-card" data-value="<?= htmlspecialchars($opt['value']) ?>">
              <span class="choice-icon" aria-hidden="true"><?= $iconKey ? $icons[$iconKey] : '' ?></span>
              <span class="choice-body">
                <span class="choice-title"><?= htmlspecialchars($opt['label']) ?></span>
                <?php if (!empty($opt['sub'])): ?>
                  <span class="choice-sub"><?= htmlspecialchars($opt['sub']) ?></span>
                <?php endif; ?>
              </span>
            </button>
          <?php endforeach; ?>
        </div>
      </section>

      <!-- 3. Insurance provider (visible when 'insurance' is picked) -->
      <section class="service-section insurance-section" id="insurance-section" hidden>
        <div class="field">
          <label for="insurance-provider-select">Insurance provider<span class="required-mark" aria-hidden="true">*</span></label>
          <select id="insurance-provider-select" name="insurance_provider">
            <option value="" selected disabled>Select your provider</option>
            <?php foreach ($insuranceProviders as $name): ?>
              <option value="<?= htmlspecialchars($name) ?>"><?= htmlspecialchars($name) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="info-note">
          <span class="info-note-icon" aria-hidden="true"><?= $icons['info'] ?></span>
          <span class="info-note-text">Most comprehensive policies cover windshield repair or replacement with little to no deductible. We handle all paperwork and bill your insurer directly.</span>
        </div>
      </section>

      <!-- 4. Schedule -->
      <section class="service-section">
        <h2 class="service-section-title">When works best for you?</h2>
        <div class="schedule-row">
          <div class="schedule-field">
            <label class="field-label" for="preferred-date">
              <span class="field-label-icon" aria-hidden="true"><?= $icons['calendar'] ?></span>
              Preferred date<span class="required-mark" aria-hidden="true">*</span>
            </label>
            <input type="date" id="preferred-date" name="preferred_date">
          </div>
          <div class="schedule-field">
            <label class="field-label" id="preferred-time-label">
              <span class="field-label-icon" aria-hidden="true"><?= $icons['clock'] ?></span>
              Preferred time<span class="required-mark" aria-hidden="true">*</span>
            </label>
            <div class="time-row" id="time-slot-group" role="radiogroup" aria-labelledby="preferred-time-label">
              <?php foreach ($timeSlots as $opt): ?>
                <button type="button" class="time-pill" role="radio" aria-checked="false" data-value="<?= htmlspecialchars($opt['value']) ?>">
                  <span class="time-pill-title"><?= htmlspecialchars($opt['label']) ?></span>
                  <span class="time-pill-sub"><?= htmlspecialchars($opt['sub']) ?></span>
                </button>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
      </section>

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

  <script src="assets/js/service.js?v=<?= $serviceJsV ?>"></script>
</body>
</html>
