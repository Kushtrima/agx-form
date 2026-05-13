/* ==========================================================
   Step 2 — Glass selector + damage panel (sedan MVP)
   - Multi-glass selection: damages on different windows are
     remembered and shown as chips at the top of the panel.
     Click a chip to switch view + re-edit that glass.
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

  const VIEWS = ["right", "front", "left", "back", "top"];
  const VIEW_LABELS = {
    right: "RIGHT SIDE",
    left:  "LEFT SIDE",
    front: "FRONT",
    back:  "BACK",
    top:   "TOP / SUNROOF"
  };

  // Map every glass id to the view it appears on, so chips can navigate.
  const GLASS_TO_VIEW = {
    right_front_door_window: "right",
    right_rear_door_window:  "right",
    right_quarter_window:    "right",
    left_front_door_window:  "left",
    left_rear_door_window:   "left",
    left_quarter_window:     "left",
    front_windshield:        "front",
    left_mirror_glass:       "front",
    right_mirror_glass:      "front",
    rear_window:             "back",
    sunroof_glass:           "top"
  };

  const stage         = document.getElementById("car-stage");
  const viewTitle     = document.getElementById("view-title");
  const rotatePrev    = document.getElementById("rotate-prev");
  const rotateNext    = document.getElementById("rotate-next");
  const damagePanel   = document.getElementById("damage-panel");
  const selectedList  = document.getElementById("selected-list");
  const selectedChips = document.getElementById("selected-chips");
  const editingRow    = document.getElementById("editing-row");
  const selectedName  = document.getElementById("selected-name");
  const removeBtn     = document.getElementById("remove-selected");
  const damageGroup   = document.getElementById("damage-group");
  const featuresGroup = document.getElementById("features-group");
  const crackSizeGroup = document.getElementById("crack-size-group");
  const damageGrid     = document.getElementById("damage-grid");
  const glassOptsCol   = document.getElementById("glass-options-column");
  const glassOptsLabel = document.getElementById("glass-options-label");
  const glassOptsGroup = document.getElementById("glass-options-group");
  // Notes + Photos sections are currently removed from the UI — keep the
  // references null so any code that touches them no-ops cleanly. The data
  // model still tolerates these fields if they reappear later.
  const notesInput     = document.getElementById("notes-input");
  const notesCount     = document.getElementById("notes-count");
  const photoInput     = document.getElementById("photo-input");
  const photoList      = document.getElementById("photo-list");

  // Glass-specific extra options config, embedded from data/options.json.
  let glassSpecificCfg = {};
  try {
    glassSpecificCfg = JSON.parse(
      document.getElementById("agx-glass-options-data").textContent || "{}"
    );
  } catch (_) { glassSpecificCfg = {}; }
  const continueBtn   = document.getElementById("continue-btn");
  const backBtn       = document.getElementById("back-btn");
  const resetBtn      = document.getElementById("reset-btn");

  let currentViewIndex = 0;
  // damages: { [glass_id]: { name, damage_type, features: [] } }
  let damages = loadDamages();
  let activeGlassId = null;

  /* ---------- Init ---------- */
  showView(VIEWS[currentViewIndex]);
  if (Object.keys(damages).length > 0) {
    damagePanel.classList.add("open");
    renderSelectedChips();
    // Auto-activate the first stored glass so pill clicks are immediately
    // interactive after a page reload. Without this, the panel is open but
    // every pill handler bails on the !activeGlassId guard.
    activateGlassById(Object.keys(damages)[0]);
  }
  updateContinueState();

  rotatePrev.addEventListener("click", () => rotate(-1));
  rotateNext.addEventListener("click", () => rotate(1));
  removeBtn.addEventListener("click", removeActive);
  continueBtn.addEventListener("click", submitAll);
  backBtn.addEventListener("click", () => { window.location.href = pageUrl("index"); });
  resetBtn.addEventListener("click", resetAll);

  bindPillGroup(damageGroup, "damage_type", true);    // single-select
  bindPillGroup(featuresGroup, "features", false);    // multi-select
  bindPillGroup(crackSizeGroup, "crack_size", true);  // single-select

  if (notesInput && notesCount) {
    notesInput.addEventListener("input", () => {
      notesCount.textContent = notesInput.value.length;
      if (!activeGlassId || !damages[activeGlassId]) return;
      damages[activeGlassId].notes = notesInput.value;
      saveDamages();
    });
  }

  selectedChips.addEventListener("click", onChipClick);

  /* ---------- View switching ---------- */
  function rotate(delta) {
    currentViewIndex = (currentViewIndex + delta + VIEWS.length) % VIEWS.length;
    showView(VIEWS[currentViewIndex]);
  }

  function showView(view) {
    document.querySelectorAll(".car-view").forEach((el) => {
      el.classList.toggle("active", el.dataset.view === view);
    });
    viewTitle.textContent = VIEW_LABELS[view];
    bindWindowClicks();
    applyHighlights();
  }

  /* ---------- Window click bindings ---------- */
  function bindWindowClicks() {
    document.querySelectorAll(".car-view.active .glass-window").forEach((el) => {
      if (el.dataset.bound === "1") return;
      el.dataset.bound = "1";
      el.addEventListener("click", (e) => {
        e.stopPropagation();
        selectWindow(el);
      });
      el.addEventListener("keydown", (e) => {
        if (e.key === "Enter" || e.key === " ") {
          e.preventDefault();
          selectWindow(el);
        }
      });
    });
  }

  function selectWindow(el) {
    const id   = el.id;
    const name = el.getAttribute("data-glass-name") || id;
    activeGlassId = id;

    if (!damages[id]) {
      damages[id] = { name, damage_type: null, features: [], crack_size: null, notes: "", photos: [] };
    } else {
      // Backfill fields for damage records saved before they existed.
      if (damages[id].crack_size === undefined) damages[id].crack_size = null;
      if (damages[id].notes      === undefined) damages[id].notes      = "";
      if (!Array.isArray(damages[id].photos))   damages[id].photos     = [];
      // Drop any stale field from earlier versions.
      if ("service_type" in damages[id]) delete damages[id].service_type;
    }

    selectedName.textContent = name;
    editingRow.hidden = false;
    damagePanel.classList.add("open");

    syncPanelFromDamage(damages[id]);
    saveDamages();           // persists + applies highlights
    renderSelectedChips();

    // If the damage panel is below the fold, gently bring it into view.
    requestAnimationFrame(() => {
      damagePanel.scrollIntoView({ behavior: "smooth", block: "nearest" });
    });
  }

  function syncPanelFromDamage(rec) {
    damageGroup.querySelectorAll(".pill").forEach((p) => {
      p.classList.toggle("active", rec.damage_type === p.dataset.value);
    });
    featuresGroup.querySelectorAll(".pill").forEach((p) => {
      p.classList.toggle("active", rec.features.includes(p.dataset.value));
    });
    crackSizeGroup.querySelectorAll(".pill").forEach((p) => {
      p.classList.toggle("active", rec.crack_size === p.dataset.value);
    });
    if (notesInput) {
      notesInput.value = rec.notes || "";
      if (notesCount) notesCount.textContent = notesInput.value.length;
    }
    renderGlassSpecificColumn(rec);
  }

  /* ---------- Glass-specific extra options (4th column) ---------- */
  function renderGlassSpecificColumn(rec) {
    const cfg = glassSpecificCfg[activeGlassId];
    if (!cfg || !Array.isArray(cfg.options) || cfg.options.length === 0) {
      damageGrid.classList.remove("has-glass-options");
      glassOptsGroup.innerHTML = "";
      return;
    }
    damageGrid.classList.add("has-glass-options");
    // Use innerHTML so the required-mark span survives label updates.
    glassOptsLabel.innerHTML = escapeHtml(cfg.label || "") +
      ' <span class="required-mark" aria-hidden="true">*</span>';
    glassOptsGroup.innerHTML = "";
    const currentValue = rec[cfg.field] || null;

    cfg.options.forEach((opt) => {
      const btn = document.createElement("button");
      btn.type = "button";
      btn.className = "pill pill-stack" + (currentValue === opt.value ? " active" : "");
      btn.dataset.value = opt.value;
      btn.dataset.field = cfg.field;
      const title = document.createElement("span");
      title.className = "pill-title";
      title.textContent = opt.label;
      btn.appendChild(title);
      if (opt.sub) {
        const sub = document.createElement("span");
        sub.className = "pill-sub";
        sub.textContent = opt.sub;
        btn.appendChild(sub);
      }
      glassOptsGroup.appendChild(btn);
    });
  }

  // Click handler for the glass-specific column — single-select with no-deselect
  // (same rule as the other single-select sections).
  glassOptsGroup.addEventListener("click", (e) => {
    const pill = e.target.closest(".pill");
    if (!pill || !activeGlassId) return;
    const cfg = glassSpecificCfg[activeGlassId];
    if (!cfg) return;
    const val   = pill.dataset.value;
    const field = pill.dataset.field || cfg.field;
    const rec   = damages[activeGlassId];
    if (!rec) return;
    if (rec[field] === val) return;   // already active → no-op
    rec[field] = val;
    glassOptsGroup.querySelectorAll(".pill").forEach((p) => {
      p.classList.toggle("active", rec[field] === p.dataset.value);
    });
    saveDamages();
  });

  /* ---------- Highlight state ----------
     - Active glass: green   (.selected)
     - Other damaged glasses on this view: blue (.has-damage)
  */
  function applyHighlights() {
    document.querySelectorAll(".glass-window").forEach((el) => {
      const damaged = !!damages[el.id];
      el.classList.toggle("selected", damaged && el.id === activeGlassId);
      el.classList.toggle("has-damage", damaged && el.id !== activeGlassId);
    });

    // Reveal the second-panel divider on the top view when the customer has
    // picked Dual / Panoramic for the sunroof; hide it otherwise.
    const sunroofRec = damages.sunroof_glass;
    const isDual = !!(sunroofRec && sunroofRec.sunroof_type === "dual");
    document.querySelectorAll("#sunroof_dual_divider").forEach((el) => {
      el.classList.toggle("show", isDual);
    });
  }

  /* ---------- Pill group handlers ----------
     Once a selection exists in a group, the user can SWITCH to another value
     (single-select) or ADD more (multi-select) but cannot clear back to zero
     by re-clicking. To wipe all choices, use the Reset button. */
  function bindPillGroup(container, field, single) {
    container.addEventListener("click", (e) => {
      const pill = e.target.closest(".pill");
      if (!pill || !activeGlassId) return;
      const val = pill.dataset.value;
      const rec = damages[activeGlassId];
      if (!rec) return;

      if (single) {
        // Single-select: clicking the already-active pill is a no-op
        // (can't deselect — to clear, use Reset).
        if (rec[field] === val) return;
        rec[field] = val;
        container.querySelectorAll(".pill").forEach((p) => {
          p.classList.toggle("active", rec[field] === p.dataset.value);
        });
      } else {
        // Multi-select: free toggle — features can independently turn on/off,
        // and the list is allowed to go back to empty.
        if (!Array.isArray(rec[field])) rec[field] = [];
        const i = rec[field].indexOf(val);
        if (i >= 0) {
          rec[field].splice(i, 1);
          pill.classList.remove("active");
        } else {
          rec[field].push(val);
          pill.classList.add("active");
        }
      }
      saveDamages();
    });
  }

  /* ---------- Chip interactions ---------- */
  function renderSelectedChips() {
    const ids = Object.keys(damages);
    selectedChips.innerHTML = "";
    if (ids.length === 0) {
      selectedList.hidden = true;
      editingRow.hidden = true;
      damagePanel.classList.remove("open");
      return;
    }
    selectedList.hidden = false;
    ids.forEach((id) => {
      const rec = damages[id];
      const chip = document.createElement("button");
      chip.type = "button";
      chip.className = "chip" + (id === activeGlassId ? " editing" : "");
      chip.dataset.id = id;
      chip.innerHTML =
        "<span>" + escapeHtml(rec.name) + "</span>" +
        '<span class="remove-x" data-remove="' + escapeHtml(id) + '">&times;</span>';
      selectedChips.appendChild(chip);
    });
  }

  function onChipClick(e) {
    const removeEl = e.target.closest("[data-remove]");
    if (removeEl) {
      e.stopPropagation();
      removeGlass(removeEl.getAttribute("data-remove"));
      return;
    }
    const chip = e.target.closest(".chip");
    if (!chip) return;
    const id = chip.dataset.id;
    const view = GLASS_TO_VIEW[id];
    if (view && VIEWS[currentViewIndex] !== view) {
      currentViewIndex = VIEWS.indexOf(view);
      showView(view);
    }
    const el = document.getElementById(id);
    if (el) selectWindow(el);
  }

  function removeGlass(id) {
    delete damages[id];
    if (activeGlassId === id) activeGlassId = null;
    saveDamages();
    renderSelectedChips();

    const remaining = Object.keys(damages);
    if (!activeGlassId && remaining.length > 0) {
      // We just removed the editing glass but others are still selected — keep
      // the panel interactive by promoting one of them.
      activateGlassById(remaining[0]);
    } else if (!activeGlassId) {
      // No glasses left at all — collapse the editing state.
      editingRow.hidden = true;
      damageGroup.querySelectorAll(".pill.active").forEach((p) => p.classList.remove("active"));
      featuresGroup.querySelectorAll(".pill.active").forEach((p) => p.classList.remove("active"));
      crackSizeGroup.querySelectorAll(".pill.active").forEach((p) => p.classList.remove("active"));
      damageGrid.classList.remove("has-glass-options");
      glassOptsGroup.innerHTML = "";
      if (notesInput) notesInput.value = "";
      if (notesCount) notesCount.textContent = "0";
      if (photoList) photoList.innerHTML = "";
    }
  }

  /**
   * Make the given glass id the active one — switches to its view if needed,
   * sets activeGlassId, and syncs the panel pills. Used on page-load
   * rehydration and when the editing glass is removed but others remain.
   */
  function activateGlassById(id) {
    if (!damages[id]) return;
    const targetView = GLASS_TO_VIEW[id];
    if (targetView && VIEWS[currentViewIndex] !== targetView) {
      currentViewIndex = VIEWS.indexOf(targetView);
      showView(targetView);
    }
    const el = document.getElementById(id);
    if (el) {
      selectWindow(el);
    } else {
      // SVG element not in the DOM (extremely unlikely now that all views are
      // inlined) — fall back to direct state update.
      activeGlassId = id;
      syncPanelFromDamage(damages[id]);
      renderSelectedChips();
      applyHighlights();
    }
  }

  function removeActive() {
    if (!activeGlassId) return;
    removeGlass(activeGlassId);
  }

  /* ---------- Reset everything ---------- */
  const resetModal   = document.getElementById("reset-modal");
  const resetCancel  = document.getElementById("reset-cancel");
  const resetConfirm = document.getElementById("reset-confirm");

  let lastFocusedElement = null;

  function openResetModal() {
    const count = Object.keys(damages).length;
    const countEl = document.getElementById("reset-modal-count");
    const nounEl  = document.getElementById("reset-modal-noun");
    if (countEl) countEl.textContent = count;
    if (nounEl)  nounEl.textContent  = count === 1 ? "glass" : "glasses";
    lastFocusedElement = document.activeElement;
    resetModal.hidden = false;
    requestAnimationFrame(() => {
      resetModal.classList.add("open");
      resetCancel.focus();    // start focus inside the modal
    });
  }
  function closeResetModal() {
    resetModal.classList.remove("open");
    setTimeout(() => {
      resetModal.hidden = true;
      // Return focus to the element that opened the modal.
      if (lastFocusedElement && document.body.contains(lastFocusedElement)) {
        lastFocusedElement.focus();
      }
      lastFocusedElement = null;
    }, 200);
  }

  resetCancel.addEventListener("click", closeResetModal);
  resetConfirm.addEventListener("click", () => {
    closeResetModal();
    performReset();
  });
  resetModal.addEventListener("click", (e) => {
    if (e.target === resetModal) closeResetModal();
  });

  // Focus trap inside the reset modal
  resetModal.addEventListener("keydown", (e) => {
    if (e.key === "Escape") {
      closeResetModal();
      return;
    }
    if (e.key !== "Tab") return;
    const focusable = resetModal.querySelectorAll(
      'button:not([disabled]):not([hidden]), [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
    );
    if (focusable.length === 0) return;
    const first = focusable[0];
    const last  = focusable[focusable.length - 1];
    if (e.shiftKey && document.activeElement === first) {
      e.preventDefault();
      last.focus();
    } else if (!e.shiftKey && document.activeElement === last) {
      e.preventDefault();
      first.focus();
    }
  });

  function resetAll() {
    if (Object.keys(damages).length === 0) {
      performReset();
      return;
    }
    openResetModal();
  }

  function performReset() {
    // Clear everything — both selector state AND the vehicle info from Step 1.
    damages = {};
    activeGlassId = null;
    sessionStorage.removeItem(STORAGE_KEYS.DAMAGES);
    sessionStorage.removeItem(STORAGE_KEYS.VEHICLE);
    // Send the user back to the starting page (Step 1 / vehicle form).
    window.location.href = pageUrl("index");
  }

  /* ---------- Persistence ---------- */
  function saveDamages() {
    // Strip transient photo fields (preview base64, uploading/error flags) before
    // persisting to sessionStorage. Only photos with a server URL survive a refresh.
    const slim = {};
    Object.keys(damages).forEach((id) => {
      const r = damages[id];
      slim[id] = {
        name:        r.name,
        damage_type: r.damage_type,
        crack_size:  r.crack_size || null,
        features:    Array.isArray(r.features) ? r.features.slice() : [],
        notes:       r.notes || "",
        photos:      (Array.isArray(r.photos) ? r.photos : [])
          .filter((p) => p && p.url)
          .map((p) => ({ url: p.url, name: p.name || "", size: p.size || 0 })),
      };
      // Include the glass-specific extra field (whatever its name is), if any.
      const cfg = glassSpecificCfg[id];
      if (cfg && cfg.field) {
        slim[id][cfg.field] = r[cfg.field] || null;
      }
    });
    try {
      sessionStorage.setItem(STORAGE_KEYS.DAMAGES, JSON.stringify(slim));
    } catch (e) {
      console.warn("[agx] sessionStorage quota exceeded; dropping photo previews.", e);
    }
    applyHighlights();
    renderSelectedChips();
    updateContinueState();
  }

  function updateContinueState() {
    const ids = Object.keys(damages);
    if (ids.length === 0) {
      continueBtn.disabled = true;
      continueBtn.title = "Select at least one window first";
      return;
    }
    // Every selected glass needs at least one button picked in each required section.
    for (const id of ids) {
      const missing = missingFieldsFor(id);
      if (missing.length > 0) {
        continueBtn.disabled = true;
        const glassName = (damages[id] && damages[id].name) || id;
        continueBtn.title = "Pick at least one option in: " + missing.join(", ") + " (" + glassName + ")";
        return;
      }
    }
    continueBtn.disabled = false;
    continueBtn.title = "";
  }

  function missingFieldsFor(id) {
    const rec = damages[id];
    if (!rec) return ["all sections"];
    const missing = [];
    if (!rec.damage_type) missing.push("Type of Damage");
    if (!Array.isArray(rec.features) || rec.features.length === 0) missing.push("Special features");
    if (!rec.crack_size) missing.push("Crack / chip size");
    const cfg = glassSpecificCfg[id];
    if (cfg && cfg.field && !rec[cfg.field]) missing.push(cfg.label || "Glass style");
    return missing;
  }

  function loadDamages() {
    try {
      return JSON.parse(sessionStorage.getItem(STORAGE_KEYS.DAMAGES) || "{}");
    } catch (e) {
      return {};
    }
  }

  /* ---------- Advance to Step 3 ---------- */
  // The Continue button on the selector page just navigates forward — the
  // final POST happens later from contact.php. (Previously this file had a
  // full submit pipeline + modal wiring; removed once the multi-step flow
  // shipped, so the only behaviour here is "save state and navigate".)
  function submitAll() {
    if (Object.keys(damages).length === 0) return;
    if (!sessionStorage.getItem(STORAGE_KEYS.VEHICLE)) {
      window.location.href = pageUrl("index");
      return;
    }
    saveDamages();
    window.location.href = pageUrl("service");
  }

  /* ---------- Utility ---------- */
  function escapeHtml(s) {
    return String(s).replace(/[&<>"']/g, (c) => ({
      "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;"
    }[c]));
  }
})();
