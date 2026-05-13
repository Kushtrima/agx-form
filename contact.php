<?php
/**
 * Step 4 — Contact info
 * Name, email, phone, ZIP, optional notes. Final step before submit.
 */
require_once __DIR__ . '/includes/config.php';

// Inline the options catalog so the review modal can resolve internal values
// (e.g. "chip_crack") into readable labels without an extra request.
$options = agx_options();

$formCssV     = @filemtime(__DIR__ . '/assets/css/form.css');
$selectorCssV = @filemtime(__DIR__ . '/assets/css/selector.css');
$serviceCssV  = @filemtime(__DIR__ . '/assets/css/service.css');
$contactCssV  = @filemtime(__DIR__ . '/assets/css/contact.css');
$contactJsV   = @filemtime(__DIR__ . '/assets/js/contact.js');
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>AGX — Your Contact Info</title>
  <link rel="stylesheet" href="assets/css/form.css?v=<?= $formCssV ?>">
  <link rel="stylesheet" href="assets/css/selector.css?v=<?= $selectorCssV ?>">
  <link rel="stylesheet" href="assets/css/service.css?v=<?= $serviceCssV ?>">
  <link rel="stylesheet" href="assets/css/contact.css?v=<?= $contactCssV ?>">
</head>
<body>
  <div class="page-wrap">

    <h2 class="headline">Free Instant Estimates - No Commitment Required</h2>

    <ol class="stepper" aria-label="Form progress">
      <li class="step-circle done"><svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="3,8.5 6.5,12 13,4"/></svg></li>
      <li class="step-circle done"><svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="3,8.5 6.5,12 13,4"/></svg></li>
      <li class="step-circle done"><svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="3,8.5 6.5,12 13,4"/></svg></li>
      <li class="step-circle done" aria-current="step"><svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="3,8.5 6.5,12 13,4"/></svg></li>
      <li class="step-circle pending"><svg viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><polygon points="5,3 13,8 5,13"/></svg></li>
    </ol>

    <div class="card service-card contact-card">

      <header class="service-header">
        <h1 class="service-title">Your contact info</h1>
        <p class="service-subtitle">We'll send your quote and booking confirmation here.</p>
      </header>

      <form id="contact-form" autocomplete="on" novalidate>

        <div class="contact-row">
          <div class="field">
            <label for="first-name">First name<span class="required-mark" aria-hidden="true">*</span></label>
            <input type="text" id="first-name" name="first_name" placeholder="Jane" maxlength="80" autocomplete="given-name" required>
          </div>
          <div class="field">
            <label for="last-name">Last name<span class="required-mark" aria-hidden="true">*</span></label>
            <input type="text" id="last-name" name="last_name" placeholder="Smith" maxlength="80" autocomplete="family-name" required>
          </div>
        </div>

        <div class="field">
          <label for="email">Email address<span class="required-mark" aria-hidden="true">*</span></label>
          <input type="email" id="email" name="email" placeholder="jane@example.com" maxlength="200" autocomplete="email" required>
        </div>

        <div class="field">
          <label for="phone">Phone number<span class="required-mark" aria-hidden="true">*</span></label>
          <input type="tel" id="phone" name="phone" placeholder="(555) 000-0000" maxlength="30" autocomplete="tel" required>
        </div>

        <div class="field">
          <label for="zip">ZIP / postal code<span class="required-mark" aria-hidden="true">*</span></label>
          <input type="text" id="zip" name="zip" placeholder="90210" maxlength="12" autocomplete="postal-code" required>
        </div>

        <div class="field">
          <label for="notes">Additional notes <span class="field-hint">(optional)</span></label>
          <textarea id="notes" name="notes" placeholder="Anything else our technician should know…" maxlength="500" rows="3"></textarea>
        </div>

      </form>

      <div class="selector-actions contact-actions">
        <button type="button" id="reset-btn" class="btn btn-text">
          <span class="arrow">
            <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
              <path d="M14 8a6 6 0 1 1 -6-6 6.3 6.3 0 0 1 4.5 1.85L14 5.5"/>
              <polyline points="14,2 14,5.5 10.5,5.5"/>
            </svg>
          </span>
          Reset
        </button>
        <div class="action-group action-group-wide">
          <button type="button" id="back-btn" class="btn btn-ghost">
            <span class="arrow">
              <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="13" y1="8" x2="3" y2="8"/>
                <polyline points="7,4 3,8 7,12"/>
              </svg>
            </span>
            Back
          </button>
          <button type="button" id="continue-btn" class="btn btn-primary btn-cta-wide" disabled>
            <span class="btn-label">Get my quote</span>
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
      <div class="modal-icon" aria-hidden="true">
        <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M21 12a9 9 0 1 1 -9 -9c2.52 0 4.93 1 6.74 2.74L21 8"/>
          <polyline points="21,3 21,8 16,8"/>
        </svg>
      </div>
      <h3 id="reset-modal-title" class="modal-title">Reset everything and start over?</h3>
      <p class="modal-message">This will clear your vehicle, glass selections, service preferences, and contact info. This cannot be undone.</p>
      <div class="modal-actions">
        <button type="button" id="reset-cancel" class="btn btn-ghost">Cancel</button>
        <button type="button" id="reset-confirm" class="btn btn-danger">Reset</button>
      </div>
    </div>
  </div>

  <!-- Review modal — opens when "Get my quote" is clicked. Lets the user
       verify everything, jump back to a step to fix it, or confirm + submit. -->
  <div id="review-modal" class="modal-overlay" role="dialog" aria-modal="true" aria-labelledby="review-modal-title" hidden>
    <div class="modal-dialog modal-dialog-wide">
      <header class="review-header">
        <h3 id="review-modal-title" class="modal-title">Review your request</h3>
        <p class="modal-message">Make sure everything looks right before sending.</p>
      </header>

      <div class="review-body" id="review-body" tabindex="-1"></div>

      <div class="review-actions">
        <button type="button" id="review-cancel" class="btn btn-ghost">Back to form</button>
        <button type="button" id="review-confirm" class="btn btn-primary btn-cta-wide">
          <span class="btn-label">Get my quote</span>
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

  <!-- Submit modal (same component used on Step 3) -->
  <div id="submit-modal" class="modal-overlay" role="dialog" aria-modal="true" aria-labelledby="submit-modal-title" hidden>
    <div class="modal-dialog">
      <div class="modal-icon submit-icon" data-state="loading">
        <svg class="state-loading" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
          <circle cx="12" cy="12" r="9" stroke-opacity="0.25"/>
          <path d="M21 12a9 9 0 0 0 -9 -9"/>
        </svg>
        <svg class="state-success" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round">
          <polyline points="5,12.5 10,17.5 19,7"/>
        </svg>
        <svg class="state-error" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
          <line x1="6" y1="6" x2="18" y2="18"/>
          <line x1="18" y1="6" x2="6" y2="18"/>
        </svg>
      </div>
      <h3 id="submit-modal-title" class="modal-title">Sending your request…</h3>
      <p id="submit-modal-message" class="modal-message">One moment while we save everything.</p>
      <div class="modal-actions" id="submit-modal-actions" hidden>
        <button type="button" id="submit-cancel" class="btn btn-ghost" hidden>Close</button>
        <button type="button" id="submit-retry" class="btn btn-primary" hidden>Try again</button>
        <button type="button" id="submit-done" class="btn btn-primary" hidden>Done</button>
      </div>
    </div>
  </div>

  <script type="application/json" id="agx-options-data"><?= json_encode($options, JSON_UNESCAPED_UNICODE) ?></script>
  <script src="assets/js/contact.js?v=<?= $contactJsV ?>"></script>
</body>
</html>
