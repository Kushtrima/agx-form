<?php
/**
 * Step 5 — Estimate / quote review.
 * Reads the in-progress form state from sessionStorage on the client and
 * shows a placeholder pricing breakdown. Final POST to submit.php happens
 * when the customer clicks "Book this appointment" here.
 */
require_once __DIR__ . '/includes/config.php';

// Inline the options catalog so we can map internal values to readable
// labels in the tiles (service mode, etc.) without an extra request.
$options = agx_options();

$formCssV     = @filemtime(__DIR__ . '/assets/css/form.css');
$selectorCssV = @filemtime(__DIR__ . '/assets/css/selector.css');
$serviceCssV  = @filemtime(__DIR__ . '/assets/css/service.css');
$contactCssV  = @filemtime(__DIR__ . '/assets/css/contact.css');
$estimateCssV = @filemtime(__DIR__ . '/assets/css/estimate.css');
$estimateJsV  = @filemtime(__DIR__ . '/assets/js/estimate.js');
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>AGX — Your Estimate</title>
  <link rel="stylesheet" href="assets/css/form.css?v=<?= $formCssV ?>">
  <link rel="stylesheet" href="assets/css/selector.css?v=<?= $selectorCssV ?>">
  <link rel="stylesheet" href="assets/css/service.css?v=<?= $serviceCssV ?>">
  <link rel="stylesheet" href="assets/css/contact.css?v=<?= $contactCssV ?>">
  <link rel="stylesheet" href="assets/css/estimate.css?v=<?= $estimateCssV ?>">
</head>
<body>
  <div class="page-wrap">

    <!-- Headline + stepper intentionally omitted on the final step. -->
    <div class="estimate-top-spacer" aria-hidden="true"></div>

    <!-- Outer white card wraps the entire estimate content -->
    <div class="card estimate-card">

    <!-- Big summary card (price + breakdown) -->
    <section class="estimate-summary-card" aria-labelledby="estimate-total-label">
      <!-- "Ready" chip pinned top-right inside the card -->
      <div class="estimate-ready">
        <span class="estimate-ready-check" aria-hidden="true">
          <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><polyline points="3,8.5 6.5,12 13,4"/></svg>
        </span>
        <span>Your estimate is ready</span>
      </div>
      <div class="estimate-summary-content">
        <div class="estimate-summary-label" id="estimate-total-label">Estimated total</div>
        <div class="estimate-summary-total" id="estimate-total">$—</div>
        <div class="estimate-summary-range" id="estimate-range">
          Typical range for this job: <span id="estimate-range-low">$—</span> – <span id="estimate-range-high">$—</span>
        </div>

        <dl class="estimate-breakdown" id="estimate-breakdown" aria-label="Estimate breakdown">
          <!-- filled by JS -->
        </dl>
      </div>
    </section>

    <!-- Tiles (vehicle / glass / service / date) -->
    <div class="estimate-tiles" id="estimate-tiles">
      <div class="estimate-tile">
        <div class="estimate-tile-label">VEHICLE</div>
        <div class="estimate-tile-value" id="tile-vehicle">—</div>
      </div>
      <div class="estimate-tile">
        <div class="estimate-tile-label">GLASS</div>
        <div class="estimate-tile-value" id="tile-glass">—</div>
      </div>
      <div class="estimate-tile">
        <div class="estimate-tile-label">SERVICE</div>
        <div class="estimate-tile-value" id="tile-service">—</div>
      </div>
      <div class="estimate-tile">
        <div class="estimate-tile-label">DATE</div>
        <div class="estimate-tile-value" id="tile-date">—</div>
      </div>
    </div>

    <!-- Info rows -->
    <div class="estimate-info">
      <div class="estimate-info-row">
        <span class="estimate-info-icon" aria-hidden="true">
          <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.91.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92Z"/>
          </svg>
        </span>
        <span class="estimate-info-text">A technician will call within 1 hour to confirm your appointment.</span>
      </div>
      <div class="estimate-info-row">
        <span class="estimate-info-icon" aria-hidden="true">
          <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <rect x="3" y="5" width="18" height="14" rx="2"/>
            <polyline points="3,7 12,13 21,7"/>
          </svg>
        </span>
        <span class="estimate-info-text">You'll receive a confirmation email with all booking details.</span>
      </div>
      <div class="estimate-info-row">
        <span class="estimate-info-icon" aria-hidden="true">
          <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M14.7 6.3a4 4 0 0 0-5.4 5.4L3 18l3 3 6.3-6.3a4 4 0 0 0 5.4-5.4l-2.3 2.3-2.7-2.7Z"/>
          </svg>
        </span>
        <span class="estimate-info-text">All repairs and replacements carry a <strong>lifetime workmanship warranty</strong>.</span>
      </div>
    </div>

    <!-- Actions -->
    <div class="estimate-actions">
      <button type="button" id="adjust-btn" class="btn btn-ghost">
        <span class="arrow">
          <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <line x1="13" y1="8" x2="3" y2="8"/>
            <polyline points="7,4 3,8 7,12"/>
          </svg>
        </span>
        Adjust details
      </button>
      <button type="button" id="book-btn" class="btn btn-primary btn-cta-wide">
        <span class="btn-icon" aria-hidden="true">
          <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <rect x="3" y="5" width="18" height="16" rx="2"/>
            <line x1="3" y1="10" x2="21" y2="10"/>
            <line x1="8" y1="3" x2="8" y2="7"/>
            <line x1="16" y1="3" x2="16" y2="7"/>
            <polyline points="9,15 11,17 15,13"/>
          </svg>
        </span>
        <span class="btn-label">Book this appointment</span>
        <span class="btn-spinner" hidden>
          <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
            <circle cx="12" cy="12" r="9" stroke-opacity="0.25"/>
            <path d="M21 12a9 9 0 0 0 -9 -9"/>
          </svg>
        </span>
      </button>
    </div>

    <p class="estimate-disclaimer">
      Final pricing confirmed after vehicle inspection. Insurance-covered repairs may be $0 out of pocket. Prices include OEM or OEM-equivalent glass and installation.
    </p>

    </div> <!-- /.estimate-card -->

  </div>

  <!-- Submit modal (loading / success / error) — same component used on Step 4 -->
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
      <h3 id="submit-modal-title" class="modal-title">Booking your appointment…</h3>
      <p id="submit-modal-message" class="modal-message">One moment while we save everything.</p>
      <div class="modal-actions" id="submit-modal-actions" hidden>
        <button type="button" id="submit-cancel" class="btn btn-ghost" hidden>Close</button>
        <button type="button" id="submit-retry" class="btn btn-primary" hidden>Try again</button>
        <button type="button" id="submit-done" class="btn btn-primary" hidden>Done</button>
      </div>
    </div>
  </div>

  <script type="application/json" id="agx-options-data"><?= json_encode($options, JSON_UNESCAPED_UNICODE) ?></script>
  <script src="assets/js/estimate.js?v=<?= $estimateJsV ?>"></script>
</body>
</html>
