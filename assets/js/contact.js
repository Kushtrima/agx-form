/* ==========================================================
   Step 4 — Contact info
   Final step. Collects name / email / phone / ZIP / notes,
   validates, and POSTs the full payload to submit.php.
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

  const firstNameInput = document.getElementById("first-name");
  const lastNameInput  = document.getElementById("last-name");
  const emailInput     = document.getElementById("email");
  const phoneInput     = document.getElementById("phone");
  const zipInput       = document.getElementById("zip");
  const notesInput     = document.getElementById("notes");

  const continueBtn = document.getElementById("continue-btn");
  const backBtn     = document.getElementById("back-btn");
  const resetBtn    = document.getElementById("reset-btn");

  const reviewModal   = document.getElementById("review-modal");
  const reviewBody    = document.getElementById("review-body");
  const reviewCancel  = document.getElementById("review-cancel");
  const reviewConfirm = document.getElementById("review-confirm");

  const resetModal   = document.getElementById("reset-modal");
  const resetCancel  = document.getElementById("reset-cancel");
  const resetConfirm = document.getElementById("reset-confirm");

  // Options catalog — inlined by PHP so we can render readable labels in the
  // review modal without an extra fetch.
  let optionsCatalog = {};
  try {
    optionsCatalog = JSON.parse(
      document.getElementById("agx-options-data").textContent || "{}"
    );
  } catch (_) { optionsCatalog = {}; }
  const LABEL_BY_VALUE = buildLabelMap(optionsCatalog);

  function buildLabelMap(opts) {
    const map = {};
    ["damage_types", "features", "crack_sizes", "service_modes", "payment_modes", "time_slots"].forEach((bucket) => {
      (opts[bucket] || []).forEach((o) => { map[o.value] = o.label; });
    });
    // glass_specific_options has its own structure
    Object.values(opts.glass_specific_options || {}).forEach((cfg) => {
      (cfg.options || []).forEach((o) => { map[o.value] = o.label; });
    });
    return map;
  }
  function label(v) {
    if (v == null || v === "") return "—";
    return LABEL_BY_VALUE[v] || v;
  }

  const submitModal     = document.getElementById("submit-modal");
  const submitIcon      = submitModal.querySelector(".submit-icon");
  const submitTitle     = document.getElementById("submit-modal-title");
  const submitMessage   = document.getElementById("submit-modal-message");
  const submitActionBox = document.getElementById("submit-modal-actions");
  const submitClose     = document.getElementById("submit-cancel");
  const submitRetry     = document.getElementById("submit-retry");
  const submitDone      = document.getElementById("submit-done");

  let contact = loadContact();
  let submitInFlight = false;

  // Guards: prior steps must be complete.
  const vehicleRaw = sessionStorage.getItem(STORAGE_KEYS.VEHICLE);
  const damagesRaw = sessionStorage.getItem(STORAGE_KEYS.DAMAGES);
  const serviceRaw = sessionStorage.getItem(STORAGE_KEYS.SERVICE);
  if (!vehicleRaw) { window.location.replace(pageUrl("index")); return; }
  if (!damagesRaw || Object.keys(JSON.parse(damagesRaw) || {}).length === 0) {
    window.location.replace(pageUrl("selector")); return;
  }
  if (!serviceRaw) { window.location.replace(pageUrl("service")); return; }

  // Restore any previously-typed values.
  applyContactState();
  validate();

  [firstNameInput, lastNameInput, emailInput, phoneInput, zipInput, notesInput].forEach((el) => {
    el.addEventListener("input", () => {
      contact[el.name] = el.value;
      saveContact();
      validate();
    });
    el.addEventListener("blur", () => {
      el.classList.toggle("invalid", !isFieldValid(el));
    });
  });

  backBtn.addEventListener("click", () => { window.location.href = pageUrl("service"); });

  // Styled reset confirmation (mirrors the selector page's pattern).
  let lastFocusedBeforeReset = null;
  resetBtn.addEventListener("click", () => {
    lastFocusedBeforeReset = document.activeElement;
    resetModal.hidden = false;
    requestAnimationFrame(() => {
      resetModal.classList.add("open");
      resetCancel.focus();
    });
  });
  resetCancel.addEventListener("click", closeResetModal);
  resetModal.addEventListener("click", (e) => {
    if (e.target === resetModal) closeResetModal();
  });
  resetConfirm.addEventListener("click", () => {
    closeResetModal();
    sessionStorage.removeItem(STORAGE_KEYS.VEHICLE);
    sessionStorage.removeItem(STORAGE_KEYS.DAMAGES);
    sessionStorage.removeItem(STORAGE_KEYS.SERVICE);
    sessionStorage.removeItem(STORAGE_KEYS.CONTACT);
    window.location.href = pageUrl("index");
  });
  function closeResetModal() {
    resetModal.classList.remove("open");
    setTimeout(() => {
      resetModal.hidden = true;
      if (lastFocusedBeforeReset && document.body.contains(lastFocusedBeforeReset)) {
        lastFocusedBeforeReset.focus();
      }
      lastFocusedBeforeReset = null;
    }, 200);
  }

  continueBtn.addEventListener("click", openReviewModal);

  reviewCancel.addEventListener("click", closeReviewModal);
  reviewModal.addEventListener("click", (e) => {
    if (e.target === reviewModal && !submitInFlight) closeReviewModal();
  });
  reviewBody.addEventListener("click", (e) => {
    const editLink = e.target.closest("[data-edit-step]");
    if (!editLink) return;
    e.preventDefault();
    const step = editLink.getAttribute("data-edit-step");
    closeReviewModal();
    if (step === "vehicle") { window.location.href = pageUrl("index");    return; }
    if (step === "damages") { window.location.href = pageUrl("selector"); return; }
    if (step === "service") { window.location.href = pageUrl("service");  return; }
    // step === "contact" — stay on this page; close the modal, focus the
    // first input so the user is right where they need to edit.
    setTimeout(() => {
      if (firstNameInput) {
        firstNameInput.focus();
        firstNameInput.scrollIntoView({ behavior: "smooth", block: "center" });
      }
    }, 220);
  });
  reviewConfirm.addEventListener("click", submitAll);
  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape" && !reviewModal.hidden && !submitInFlight) closeReviewModal();
  });

  submitClose.addEventListener("click", closeSubmitModal);
  submitDone.addEventListener("click", () => {
    closeSubmitModal();
    window.location.href = pageUrl("index");
  });
  submitRetry.addEventListener("click", submitAll);
  submitModal.addEventListener("click", (e) => {
    if (e.target === submitModal && submitIcon.dataset.state !== "loading") closeSubmitModal();
  });

  /* ---------- State helpers ---------- */
  function loadContact() {
    try { return JSON.parse(sessionStorage.getItem(STORAGE_KEYS.CONTACT) || "{}"); }
    catch (_) { return {}; }
  }
  function saveContact() {
    sessionStorage.setItem(STORAGE_KEYS.CONTACT, JSON.stringify(contact));
  }
  function applyContactState() {
    firstNameInput.value = contact.first_name || "";
    lastNameInput.value  = contact.last_name  || "";
    emailInput.value     = contact.email      || "";
    phoneInput.value     = contact.phone      || "";
    zipInput.value       = contact.zip        || "";
    notesInput.value     = contact.notes      || "";
  }

  /* ---------- Validation ---------- */
  function isFieldValid(el) {
    const v = (el.value || "").trim();
    if (el === firstNameInput || el === lastNameInput) return v.length > 0 && v.length <= 80;
    if (el === emailInput) return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v);
    if (el === phoneInput) {
      const digits = v.replace(/\D/g, "");
      return digits.length >= 7 && digits.length <= 20;
    }
    if (el === zipInput) return /^[A-Za-z0-9 \-]{3,12}$/.test(v);
    if (el === notesInput) return v.length <= 500;
    return true;
  }

  function validate() {
    const allValid =
      isFieldValid(firstNameInput) &&
      isFieldValid(lastNameInput) &&
      isFieldValid(emailInput) &&
      isFieldValid(phoneInput) &&
      isFieldValid(zipInput) &&
      isFieldValid(notesInput);
    continueBtn.disabled = !allValid;
    continueBtn.title = allValid ? "" : "Please fill in all required fields with valid values.";
  }

  /* ---------- Review modal ---------- */
  let lastFocusedBeforeReview = null;
  function openReviewModal() {
    if (continueBtn.disabled) return;
    // Persist current contact state so renderReview sees fresh values.
    saveContact();
    renderReview();
    lastFocusedBeforeReview = document.activeElement;
    reviewModal.hidden = false;
    requestAnimationFrame(() => {
      reviewModal.classList.add("open");
      reviewCancel.focus();
    });
  }
  function closeReviewModal() {
    reviewModal.classList.remove("open");
    setTimeout(() => {
      reviewModal.hidden = true;
      if (lastFocusedBeforeReview && document.body.contains(lastFocusedBeforeReview)) {
        lastFocusedBeforeReview.focus();
      }
      lastFocusedBeforeReview = null;
    }, 200);
  }

  // Focus trap inside the review modal — Tab cycles within the dialog.
  reviewModal.addEventListener("keydown", (e) => {
    if (e.key !== "Tab") return;
    const focusable = reviewModal.querySelectorAll(
      'button:not([disabled]):not([hidden]), [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
    );
    if (focusable.length === 0) return;
    const first = focusable[0];
    const last  = focusable[focusable.length - 1];
    if (e.shiftKey && document.activeElement === first) {
      e.preventDefault(); last.focus();
    } else if (!e.shiftKey && document.activeElement === last) {
      e.preventDefault(); first.focus();
    }
  });

  function renderReview() {
    const vehicle  = JSON.parse(sessionStorage.getItem(STORAGE_KEYS.VEHICLE)  || "{}");
    const damages  = JSON.parse(sessionStorage.getItem(STORAGE_KEYS.DAMAGES)  || "{}");
    const service  = JSON.parse(sessionStorage.getItem(STORAGE_KEYS.SERVICE)  || "{}");
    const c        = contact || {};

    const vehicleLine = [vehicle.year, vehicle.brand, vehicle.model].filter(Boolean).join(" ");
    const damageIds   = Object.keys(damages);

    const damagesContent = damageIds.length === 0
      ? '<p class="review-empty">No glasses selected.</p>'
      : `<div class="review-glasses">${damageIds.map((id) => renderGlassBlock(id, damages[id])).join("")}</div>`;

    const sections = [
      renderKvSection("Vehicle", "vehicle", `
        ${kv("Year",       vehicle.year)}
        ${kv("Brand",      vehicle.brand)}
        ${kv("Model",      vehicle.model)}
        ${kv("Body style", vehicle.body_style ? cap(vehicle.body_style) : null)}
        ${kv("VIN",        vehicle.vin || null)}
      `),

      renderRawSection(
        damageIds.length > 1 ? `Damages (${damageIds.length} glasses)` : "Damage",
        "damages",
        damagesContent
      ),

      renderKvSection("Service & scheduling", "service", `
        ${kv("Service mode",       label(service.service_mode))}
        ${kv("Payment / coverage", label(service.payment_mode))}
        ${service.payment_mode === "insurance" ? kv("Insurance provider", service.insurance_provider) : ""}
        ${kv("Preferred date",     service.preferred_date)}
        ${kv("Preferred time",     label(service.preferred_time))}
      `),

      renderKvSection("Contact info", "contact", `
        ${kv("Name",  ((c.first_name || "") + " " + (c.last_name || "")).trim())}
        ${kv("Email", c.email)}
        ${kv("Phone", c.phone)}
        ${kv("ZIP",   c.zip)}
        ${c.notes ? kv("Notes", c.notes) : ""}
      `)
    ];

    reviewBody.innerHTML = sections.join("");
  }

  // For sections whose content is a list of key/value pairs (dt/dd inside a grid dl).
  function renderKvSection(title, step, kvHtml) {
    return `
      <section class="review-section">
        ${sectionHeader(title, step)}
        <dl class="review-list">${kvHtml}</dl>
      </section>
    `;
  }
  // For sections with arbitrary block content (e.g. the per-glass cards).
  function renderRawSection(title, step, html) {
    return `
      <section class="review-section">
        ${sectionHeader(title, step)}
        ${html}
      </section>
    `;
  }
  function sectionHeader(title, step) {
    return `
      <header class="review-section-header">
        <h4>${escapeHtml(title)}</h4>
        <a href="#" class="review-edit-link" data-edit-step="${escapeHtml(step)}">Edit</a>
      </header>
    `;
  }

  function renderGlassBlock(id, rec) {
    const features = Array.isArray(rec.features) && rec.features.length
      ? rec.features.map(label).join(", ")
      : "—";
    // Pick up any glass-specific field (glass_style / rear_defrost / sunroof_type)
    const extraEntries = [];
    Object.keys(rec).forEach((k) => {
      if (["name", "damage_type", "crack_size", "features", "notes", "photos"].includes(k)) return;
      if (rec[k] == null || rec[k] === "") return;
      extraEntries.push(kv(prettyKey(k), label(rec[k])));
    });
    return `
      <div class="review-glass">
        <strong class="review-glass-name">${escapeHtml(rec.name || id)}</strong>
        <dl class="review-list review-list-inline">
          ${kv("Damage", label(rec.damage_type))}
          ${kv("Size",   label(rec.crack_size))}
          ${kv("Features", features)}
          ${extraEntries.join("")}
        </dl>
      </div>
    `;
  }

  function kv(k, v) {
    if (v == null || v === "") return `<dt>${escapeHtml(k)}</dt><dd class="review-empty-cell">—</dd>`;
    return `<dt>${escapeHtml(k)}</dt><dd>${escapeHtml(String(v))}</dd>`;
  }
  function cap(s)        { return s ? s[0].toUpperCase() + s.slice(1) : s; }
  function prettyKey(k)  { return k.replace(/_/g, " ").replace(/\b\w/g, (c) => c.toUpperCase()); }
  function escapeHtml(s) {
    return String(s).replace(/[&<>"']/g, (c) => ({
      "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;"
    }[c]));
  }

  /* ---------- Submit ---------- */
  function submitAll() {
    if (submitInFlight) return;
    if (continueBtn.disabled) return;

    const vehicle = JSON.parse(sessionStorage.getItem(STORAGE_KEYS.VEHICLE) || "{}");
    const damages = JSON.parse(sessionStorage.getItem(STORAGE_KEYS.DAMAGES) || "{}");
    const service = JSON.parse(sessionStorage.getItem(STORAGE_KEYS.SERVICE) || "{}");
    const payload = {
      vehicle: vehicle,
      damages: damages,
      service: service,
      contact: {
        first_name: (contact.first_name || "").trim(),
        last_name:  (contact.last_name  || "").trim(),
        email:      (contact.email      || "").trim(),
        phone:      (contact.phone      || "").trim(),
        zip:        (contact.zip        || "").trim(),
        notes:      (contact.notes      || "").trim()
      }
    };
    sendPayload(payload);
  }

  function sendPayload(payload) {
    submitInFlight = true;
    setBtnLoading(true);
    // The review modal is open at this point — keep it on screen while the
    // request flies, but hide it once we have a result so the submit modal
    // takes over cleanly.
    setSubmitState("loading", {
      title:   "Sending your request…",
      message: "One moment while we save everything."
    });
    closeReviewModal();
    openSubmitModal();

    // On GitHub Pages there's no PHP backend, so short-circuit the network
    // call with a fake "ok" response that looks identical to the real one.
    // This lets the client click through the entire flow end-to-end without
    // a server. The 800ms delay mimics a real round-trip so the loading
    // state is actually visible.
    const request = IS_STATIC
      ? new Promise((resolve) => {
          try { console.log("[AGX demo] payload that would be submitted:", payload); } catch (_) {}
          setTimeout(() => resolve({
            json: () => Promise.resolve({
              ok: true,
              id: "agx_demo_" + Math.random().toString(36).slice(2, 12)
            })
          }), 800);
        })
      : fetch("submit.php", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify(payload)
        });

    request
      .then((r) => r.json().catch(() => ({ ok: false })))
      .then((res) => {
        submitInFlight = false;
        setBtnLoading(false);
        if (res && res.ok) {
          sessionStorage.removeItem(STORAGE_KEYS.VEHICLE);
          sessionStorage.removeItem(STORAGE_KEYS.DAMAGES);
          sessionStorage.removeItem(STORAGE_KEYS.SERVICE);
          sessionStorage.removeItem(STORAGE_KEYS.CONTACT);
          setSubmitState("success", {
            title:   "Thanks! We received your request.",
            message: "Reference: " + (res.id || "—") + ". We'll contact you shortly with the next steps.",
            showDone: true
          });
        } else {
          setSubmitState("error", {
            title:   "Something went wrong.",
            message: (res && res.error) || "Please try again in a moment.",
            showClose: true, showRetry: true
          });
        }
      })
      .catch(() => {
        submitInFlight = false;
        setBtnLoading(false);
        setSubmitState("error", {
          title:   "Network error.",
          message: "We couldn't reach the server. Check your connection and try again.",
          showClose: true, showRetry: true
        });
      });
  }

  function setBtnLoading(loading) {
    [continueBtn, reviewConfirm].forEach((b) => {
      if (!b) return;
      b.classList.toggle("is-loading", loading);
      b.disabled = loading;
      const spinner = b.querySelector(".btn-spinner");
      if (spinner) spinner.hidden = !loading;
    });
    if (!loading) {
      continueBtn.disabled = hasInvalidField();
      reviewConfirm.disabled = false;
    }
  }
  function hasInvalidField() {
    return !(
      isFieldValid(firstNameInput) && isFieldValid(lastNameInput) &&
      isFieldValid(emailInput) && isFieldValid(phoneInput) &&
      isFieldValid(zipInput) && isFieldValid(notesInput)
    );
  }

  function setSubmitState(state, opts) {
    opts = opts || {};
    submitIcon.dataset.state = state;
    submitTitle.textContent   = opts.title   || "";
    submitMessage.textContent = opts.message || "";
    submitClose.hidden = !opts.showClose;
    submitRetry.hidden = !opts.showRetry;
    submitDone.hidden  = !opts.showDone;
    submitActionBox.hidden = !(opts.showClose || opts.showRetry || opts.showDone);
  }
  function openSubmitModal() {
    submitModal.hidden = false;
    requestAnimationFrame(() => {
      submitModal.classList.add("open");
      // Focus the first visible action so screen-reader users hear the title.
      const focusable = submitModal.querySelector(
        '.modal-actions button:not([hidden]):not([disabled])'
      );
      if (focusable) focusable.focus();
    });
  }
  function closeSubmitModal() {
    submitModal.classList.remove("open");
    setTimeout(() => { submitModal.hidden = true; }, 200);
  }

  // Focus trap + Escape inside the submit modal.
  submitModal.addEventListener("keydown", (e) => {
    if (e.key === "Escape") {
      if (submitIcon.dataset.state === "loading") return; // can't dismiss mid-submit
      closeSubmitModal();
      return;
    }
    if (e.key !== "Tab") return;
    const focusable = submitModal.querySelectorAll(
      'button:not([disabled]):not([hidden]), [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
    );
    if (focusable.length === 0) return;
    const first = focusable[0];
    const last  = focusable[focusable.length - 1];
    if (e.shiftKey && document.activeElement === first) {
      e.preventDefault(); last.focus();
    } else if (!e.shiftKey && document.activeElement === last) {
      e.preventDefault(); first.focus();
    }
  });
})();
