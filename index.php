<?php
/**
 * Step 1 — Vehicle information form
 */
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>AGX — Vehicle Glass Estimate</title>
  <link rel="stylesheet" href="assets/css/form.css">
</head>
<body>
  <div class="page-wrap">

    <h2 class="headline">Free Instant Estimates - No Commitment Required</h2>

    <!-- Stepper -->
    <div class="stepper" aria-label="Progress">
      <div class="step-circle done" aria-current="step">
        <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><polyline points="3,8.5 6.5,12 13,4"/></svg>
      </div>
      <div class="step-circle pending"><svg viewBox="0 0 16 16" fill="currentColor"><polygon points="5,3 13,8 5,13"/></svg></div>
      <div class="step-circle pending"><svg viewBox="0 0 16 16" fill="currentColor"><polygon points="5,3 13,8 5,13"/></svg></div>
      <div class="step-circle pending"><svg viewBox="0 0 16 16" fill="currentColor"><polygon points="5,3 13,8 5,13"/></svg></div>
      <div class="step-circle pending"><svg viewBox="0 0 16 16" fill="currentColor"><polygon points="5,3 13,8 5,13"/></svg></div>
    </div>

    <!-- Form card -->
    <div class="card glow">
      <form id="vehicle-form" autocomplete="off" novalidate>
        <div class="form-grid">

          <div class="form-intro">
            <p class="step-tag">Step 1 of 5</p>
            <h1>Tell us about<br>your vehicle</h1>
            <p>We'll find the exact glass for your car's year and model.</p>
          </div>

          <div class="form-fields">
            <div class="field">
              <label for="year">Year</label>
              <select id="year" name="year" required>
                <option value="" selected disabled>Select Year</option>
              </select>
            </div>

            <div class="field">
              <label for="brand">Brand</label>
              <select id="brand" name="brand" required>
                <option value="" selected disabled>Select Brand</option>
              </select>
            </div>

            <div class="field">
              <label for="model">Model</label>
              <select id="model" name="model" required disabled>
                <option value="" selected disabled>Select Model</option>
              </select>
            </div>

            <div class="field">
              <label for="body_style">Body Style</label>
              <select id="body_style" name="body_style" required>
                <option value="" selected disabled>Select Body Style</option>
              </select>
            </div>

            <div class="field">
              <label for="vin">VIN <span class="field-hint">(optional-improves accuracy)</span></label>
              <input type="text" id="vin" name="vin" placeholder="e.g H1GTF98539856786" maxlength="17">
            </div>

            <div class="form-actions">
              <button type="button" id="cancel-btn" class="btn btn-ghost">Cancel</button>
              <button type="submit" id="continue-btn" class="btn btn-primary" disabled>
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
      </form>
    </div>

  </div>

  <script src="assets/js/form.js"></script>
</body>
</html>
