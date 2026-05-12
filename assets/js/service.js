/* ==========================================================
   Step 3 — Service & scheduling
   - Collects service_mode, payment_mode, insurance_provider,
     preferred_date, preferred_time
   - Validates that all required fields are filled
   - On Submit, posts vehicle + damages + service to submit.php
   ========================================================== */
(function () {
  "use strict";

  // Shared sessionStorage keys (mirrored across form.js / selector.js / service.js / contact.js)
  const STORAGE_KEYS = {
    VEHICLE: "agx_vehicle",
    DAMAGES: "agx_damages",
    SERVICE: "agx_service",
    CONTACT: "agx_contact",
  };

  // Runtime detection — see form.js for explanation.
  const IS_STATIC =
    window.location.hostname.endsWith(".github.io") ||
    window.location.pathname.endsWith(".html");
  const PAGE_EXT = IS_STATIC ? ".html" : ".php";
  function pageUrl(name) { return name + PAGE_EXT; }

  const serviceModeGroup = document.getElementById("service-mode-group");
  const paymentModeGroup = document.getElementById("payment-mode-group");
  const insuranceSection = document.getElementById("insurance-section");
  const insuranceSelect  = document.getElementById("insurance-provider-select");
  const dateInput        = document.getElementById("preferred-date");
  const timeGroup        = document.getElementById("time-slot-group");
  const continueBtn      = document.getElementById("continue-btn");
  const backBtn          = document.getElementById("back-btn");
  const resetBtn         = document.getElementById("reset-btn");

  // ---- Load any previously saved service state from sessionStorage ----
  let service = loadService();

  // ---- Guard: if user landed here without completing earlier steps, send them back ----
  const vehicleRaw = sessionStorage.getItem(STORAGE_KEYS.VEHICLE);
  const damagesRaw = sessionStorage.getItem(STORAGE_KEYS.DAMAGES);
  if (!vehicleRaw) {
    window.location.replace(pageUrl("index"));
    return;
  }
  if (!damagesRaw || Object.keys(JSON.parse(damagesRaw) || {}).length === 0) {
    window.location.replace(pageUrl("selector"));
    return;
  }

  // Compute today's date in the user's LOCAL timezone for the date input's
  // min attribute (server-side `date('Y-m-d')` could be off by a day from
  // the user's perspective if the server timezone differs).
  (function setDateMin() {
    const now = new Date();
    const pad = (n) => String(n).padStart(2, "0");
    dateInput.min = now.getFullYear() + "-" + pad(now.getMonth() + 1) + "-" + pad(now.getDate());
  })();

  // ---- Initial render ----
  applyServiceState();
  updateContinueState();

  // ---- Single-select helper (same no-deselect rule as the rest of the app) ----
  function bindSingleSelect(container, field, childSelector) {
    container.addEventListener("click", (e) => {
      const pill = e.target.closest(childSelector);
      if (!pill) return;
      const val = pill.dataset.value;
      if (service[field] === val) return;          // can't deselect, only switch
      service[field] = val;
      container.querySelectorAll(childSelector).forEach((p) => {
        const isActive = service[field] === p.dataset.value;
        p.classList.toggle("active", isActive);
        if (p.hasAttribute("aria-checked")) p.setAttribute("aria-checked", isActive ? "true" : "false");
      });
      onStateChange();
    });
  }

  bindSingleSelect(serviceModeGroup, "service_mode", ".choice-card");
  bindSingleSelect(paymentModeGroup, "payment_mode", ".choice-card");
  bindSingleSelect(timeGroup,        "preferred_time", ".time-pill");

  insuranceSelect.addEventListener("change", () => {
    service.insurance_provider = insuranceSelect.value || null;
    onStateChange();
  });

  dateInput.addEventListener("change", () => {
    service.preferred_date = dateInput.value || null;
    onStateChange();
  });

  backBtn.addEventListener("click", () => { window.location.href = pageUrl("selector"); });

  // Reset everything (same destructive action as on selector page) — send user to Step 1.
  resetBtn.addEventListener("click", () => {
    if (!window.confirm("Reset everything and start over? This cannot be undone.")) return;
    sessionStorage.removeItem(STORAGE_KEYS.VEHICLE);
    sessionStorage.removeItem(STORAGE_KEYS.DAMAGES);
    sessionStorage.removeItem(STORAGE_KEYS.SERVICE);
    window.location.href = pageUrl("index");
  });

  continueBtn.addEventListener("click", advanceToContact);

  // ---- Helpers ----
  function loadService() {
    try {
      return JSON.parse(sessionStorage.getItem(STORAGE_KEYS.SERVICE) || "{}");
    } catch (_) {
      return {};
    }
  }

  function saveService() {
    sessionStorage.setItem(STORAGE_KEYS.SERVICE, JSON.stringify(service));
  }

  function applyServiceState() {
    serviceModeGroup.querySelectorAll(".choice-card").forEach((p) => {
      p.classList.toggle("active", service.service_mode === p.dataset.value);
    });
    paymentModeGroup.querySelectorAll(".choice-card").forEach((p) => {
      p.classList.toggle("active", service.payment_mode === p.dataset.value);
    });
    timeGroup.querySelectorAll(".time-pill").forEach((p) => {
      const isActive = service.preferred_time === p.dataset.value;
      p.classList.toggle("active", isActive);
      p.setAttribute("aria-checked", isActive ? "true" : "false");
    });

    if (service.preferred_date) dateInput.value = service.preferred_date;
    if (service.insurance_provider) insuranceSelect.value = service.insurance_provider;

    insuranceSection.hidden = service.payment_mode !== "insurance";
  }

  function onStateChange() {
    insuranceSection.hidden = service.payment_mode !== "insurance";
    // If switching away from insurance, drop the provider value so we don't ship stale data
    if (service.payment_mode !== "insurance") {
      service.insurance_provider = null;
      insuranceSelect.value = "";
    }
    saveService();
    updateContinueState();
  }

  function updateContinueState() {
    const missing = missingFields();
    continueBtn.disabled = missing.length > 0;
    continueBtn.title = missing.length === 0 ? "" : "Pick: " + missing.join(", ");
  }

  function missingFields() {
    const missing = [];
    if (!service.service_mode)       missing.push("How would you like to be served?");
    if (!service.payment_mode)       missing.push("Payment / coverage");
    if (service.payment_mode === "insurance" && !service.insurance_provider) {
      missing.push("Insurance provider");
    }
    if (!service.preferred_date)     missing.push("Preferred date");
    if (!service.preferred_time)     missing.push("Preferred time");
    return missing;
  }

  // Step 3 now advances to Step 4 (contact); the final POST happens there.
  function advanceToContact() {
    if (missingFields().length > 0) return;
    saveService();
    window.location.href = pageUrl("contact");
  }
})();
