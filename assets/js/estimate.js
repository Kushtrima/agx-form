/* ==========================================================
   Step 5 — Estimate / quote review
   - Reads vehicle / damages / service / contact from sessionStorage
   - Calculates a placeholder breakdown using simple numbers
     (will be replaced with real pricing later)
   - "Book this appointment" POSTs the full payload to submit.php
     (mocked on static GitHub Pages)
   - "Adjust details" returns to Step 4 (contact)
   ========================================================== */
(function () {
  "use strict";

  // Shared sessionStorage keys (mirrored across form.js / selector.js / service.js / contact.js / estimate.js)
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

  // ----- Placeholder pricing constants (replace with real logic later) -----
  const PARTS_PER_GLASS    = 150;
  const REPAIR_CREDIT      = 75;    // applied when any chip/crack glass is repairable
  const MOBILE_SERVICE_FEE = 25;
  const TAX_RATE           = 0.08;
  const RANGE_SPREAD       = 0.10;  // ±10% for "typical range"

  // ----- Read state -----
  const vehicle = safeRead(STORAGE_KEYS.VEHICLE, {});
  const damages = safeRead(STORAGE_KEYS.DAMAGES, {});
  const service = safeRead(STORAGE_KEYS.SERVICE, {});
  const contact = safeRead(STORAGE_KEYS.CONTACT, {});

  // ---- Guards: previous steps must be complete. If not, bounce back. ----
  if (!vehicle || !vehicle.year || !vehicle.brand) {
    window.location.replace(pageUrl("index")); return;
  }
  if (!damages || Object.keys(damages).length === 0) {
    window.location.replace(pageUrl("selector")); return;
  }
  if (!service || !service.service_mode || !service.payment_mode) {
    window.location.replace(pageUrl("service")); return;
  }
  if (!contact || !contact.first_name || !contact.email) {
    window.location.replace(pageUrl("contact")); return;
  }

  // Options catalog — inlined by PHP so we can show readable labels in tiles.
  let optionsCatalog = {};
  try {
    optionsCatalog = JSON.parse(
      document.getElementById("agx-options-data").textContent || "{}"
    );
  } catch (_) { optionsCatalog = {}; }
  const LABEL_BY_VALUE = buildLabelMap(optionsCatalog);

  // ----- Render -----
  const estimate = calculateEstimate(damages, service);
  renderSummary(estimate);
  renderTiles(vehicle, damages, service);

  // ----- DOM hooks -----
  const bookBtn   = document.getElementById("book-btn");
  const adjustBtn = document.getElementById("adjust-btn");

  const submitModal     = document.getElementById("submit-modal");
  const submitIcon      = submitModal.querySelector(".submit-icon");
  const submitTitle     = document.getElementById("submit-modal-title");
  const submitMessage   = document.getElementById("submit-modal-message");
  const submitActionBox = document.getElementById("submit-modal-actions");
  const submitClose     = document.getElementById("submit-cancel");
  const submitRetry     = document.getElementById("submit-retry");
  const submitDone      = document.getElementById("submit-done");

  let submitInFlight = false;

  bookBtn.addEventListener("click", book);
  adjustBtn.addEventListener("click", () => { window.location.href = pageUrl("contact"); });

  submitClose.addEventListener("click", closeSubmitModal);
  submitDone.addEventListener("click", () => {
    sessionStorage.removeItem(STORAGE_KEYS.VEHICLE);
    sessionStorage.removeItem(STORAGE_KEYS.DAMAGES);
    sessionStorage.removeItem(STORAGE_KEYS.SERVICE);
    sessionStorage.removeItem(STORAGE_KEYS.CONTACT);
    window.location.href = pageUrl("index");
  });
  submitRetry.addEventListener("click", book);
  submitModal.addEventListener("click", (e) => {
    if (e.target === submitModal && submitIcon.dataset.state !== "loading") closeSubmitModal();
  });

  // Focus trap + Escape inside the submit modal
  submitModal.addEventListener("keydown", (e) => {
    if (e.key === "Escape") {
      if (submitIcon.dataset.state === "loading") return;
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

  /* ---------- Pricing (placeholder) ---------- */
  function calculateEstimate(damages, service) {
    const ids = Object.keys(damages);
    const numGlasses    = ids.length;
    const subtotalParts = numGlasses * PARTS_PER_GLASS;

    // Repair credit: applied once if ANY damage is a small chip/crack
    // (the cheaper to repair vs replace path).
    const anyChip = ids.some((id) => damages[id] && damages[id].damage_type === "chip_crack");
    const repairCredit = anyChip ? -REPAIR_CREDIT : 0;

    const mobileFee = service.service_mode === "mobile" ? MOBILE_SERVICE_FEE : 0;

    const preTax = subtotalParts + repairCredit + mobileFee;
    const tax    = Math.round(preTax * TAX_RATE);
    const total  = preTax + tax;

    const rangeLow  = Math.max(0, Math.round(total * (1 - RANGE_SPREAD)));
    const rangeHigh = Math.round(total * (1 + RANGE_SPREAD));

    return {
      numGlasses,
      subtotalParts,
      repairCredit,
      mobileFee,
      tax,
      total,
      rangeLow,
      rangeHigh
    };
  }

  function renderSummary(est) {
    document.getElementById("estimate-total").textContent      = fmt(est.total);
    document.getElementById("estimate-range-low").textContent  = fmt(est.rangeLow);
    document.getElementById("estimate-range-high").textContent = fmt(est.rangeHigh);

    const dl = document.getElementById("estimate-breakdown");
    const rows = [];
    rows.push(line("OEM glass (parts)", est.subtotalParts));
    if (est.repairCredit !== 0) rows.push(line("Repair credit (vs replacement)", est.repairCredit));
    if (est.mobileFee   !== 0)  rows.push(line("Mobile service fee", est.mobileFee, "+"));
    rows.push(line("Tax (estimated " + Math.round(TAX_RATE * 100) + "%)", est.tax));
    rows.push('<div class="row-divider"></div>');
    rows.push('<div class="row-total"><dt>Total estimate</dt><dd>' + fmt(est.total) + '</dd></div>');
    dl.innerHTML = rows.join("");
  }

  function line(label, amount, forceSign) {
    let display;
    if (amount < 0) {
      display = "-" + fmt(Math.abs(amount));
    } else if (forceSign === "+") {
      display = "+" + fmt(amount);
    } else {
      display = fmt(amount);
    }
    return "<dt>" + escapeHtml(label) + "</dt><dd>" + display + "</dd>";
  }

  /* ---------- Tiles ---------- */
  function renderTiles(vehicle, damages, service) {
    // VEHICLE
    const vText = [vehicle.year, vehicle.brand, vehicle.model].filter(Boolean).join(" ")
      || "—";
    document.getElementById("tile-vehicle").textContent = vText;

    // GLASS
    const ids = Object.keys(damages);
    let glassText = "—";
    if (ids.length === 1) {
      glassText = damages[ids[0]].name || ids[0];
    } else if (ids.length > 1) {
      glassText = ids.length + " glasses";
    }
    document.getElementById("tile-glass").textContent = glassText;

    // SERVICE
    document.getElementById("tile-service").textContent = label(service.service_mode);

    // DATE
    const date = service.preferred_date || "Flexible";
    const time = service.preferred_time ? " · " + label(service.preferred_time) : "";
    document.getElementById("tile-date").textContent = date + time;
  }

  /* ---------- Submit ---------- */
  function book() {
    if (submitInFlight) return;

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
      },
      estimate: {
        total: calculateEstimate(damages, service).total
      }
    };

    submitInFlight = true;
    setBtnLoading(true);
    setSubmitState("loading", {
      title:   "Booking your appointment…",
      message: "One moment while we save everything."
    });
    openSubmitModal();

    // On GitHub Pages there's no PHP backend — short-circuit with a fake
    // success after a brief delay so the click-through still feels right.
    const request = IS_STATIC
      ? new Promise((resolve) => {
          try { console.log("[AGX demo] booking payload:", payload); } catch (_) {}
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
          setSubmitState("success", {
            title:   "Booking confirmed!",
            message: "Reference: " + (res.id || "—") + ". A technician will reach out shortly.",
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
    bookBtn.classList.toggle("is-loading", loading);
    bookBtn.disabled = loading;
    const spinner = bookBtn.querySelector(".btn-spinner");
    if (spinner) spinner.hidden = !loading;
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

  /* ---------- Helpers ---------- */
  function safeRead(key, fallback) {
    try { return JSON.parse(sessionStorage.getItem(key) || "null") || fallback; }
    catch (_) { return fallback; }
  }
  function buildLabelMap(opts) {
    const map = {};
    ["damage_types", "features", "crack_sizes", "service_modes", "payment_modes", "time_slots"].forEach((bucket) => {
      (opts[bucket] || []).forEach((o) => { map[o.value] = o.label; });
    });
    Object.values(opts.glass_specific_options || {}).forEach((cfg) => {
      (cfg.options || []).forEach((o) => { map[o.value] = o.label; });
    });
    return map;
  }
  function label(v) {
    if (v == null || v === "") return "—";
    return LABEL_BY_VALUE[v] || v;
  }
  function fmt(amount) {
    return "$" + Math.abs(Math.round(amount)).toLocaleString("en-US");
  }
  function escapeHtml(s) {
    return String(s).replace(/[&<>"']/g, (c) => ({
      "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;"
    }[c]));
  }
})();
