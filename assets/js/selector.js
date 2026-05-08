/* ==========================================================
   Step 2 — Glass selector + damage panel (sedan MVP)
   - Multi-glass selection: damages on different windows are
     remembered and shown as chips at the top of the panel.
     Click a chip to switch view + re-edit that glass.
   ========================================================== */
(function () {
  "use strict";

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
    left_front_door_window:  "left",
    left_rear_door_window:   "left",
    front_windshield:        "front",
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
  const serviceGroup  = document.getElementById("service-group");
  const notesInput    = document.getElementById("notes-input");
  const notesCount    = document.getElementById("notes-count");
  const photoInput    = document.getElementById("photo-input");
  const photoList     = document.getElementById("photo-list");
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
  }
  updateContinueState();

  rotatePrev.addEventListener("click", () => rotate(-1));
  rotateNext.addEventListener("click", () => rotate(1));
  removeBtn.addEventListener("click", removeActive);
  continueBtn.addEventListener("click", submitAll);
  backBtn.addEventListener("click", () => { window.location.href = "index.php"; });
  resetBtn.addEventListener("click", resetAll);

  bindPillGroup(damageGroup, "damage_type", true);   // single-select
  bindPillGroup(featuresGroup, "features", false);   // multi-select
  bindPillGroup(serviceGroup, "service_type", true); // single-select

  // Notes textarea — update counter + persist per glass
  notesInput.addEventListener("input", () => {
    notesCount.textContent = notesInput.value.length;
    if (!activeGlassId || !damages[activeGlassId]) return;
    damages[activeGlassId].notes = notesInput.value;
    saveDamages();
  });

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
      damages[id] = { name, damage_type: null, features: [], service_type: null, notes: "", photos: [] };
    } else {
      // Backfill new fields for damage records saved before service/notes/photos existed.
      if (damages[id].service_type === undefined) damages[id].service_type = null;
      if (damages[id].notes        === undefined) damages[id].notes        = "";
      if (!Array.isArray(damages[id].photos))     damages[id].photos       = [];
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
    serviceGroup.querySelectorAll(".pill").forEach((p) => {
      p.classList.toggle("active", rec.service_type === p.dataset.value);
    });
    notesInput.value = rec.notes || "";
    notesCount.textContent = notesInput.value.length;
    renderPhotos(rec.photos || []);
  }

  /* ---------- Photo upload ---------- */
  photoInput.addEventListener("change", () => {
    const file = photoInput.files[0];
    photoInput.value = "";   // allow re-selecting the same file
    if (!file || !activeGlassId || !damages[activeGlassId]) return;
    uploadPhoto(file);
  });

  function renderPhotos(photos) {
    photoList.innerHTML = "";
    photos.forEach((p, idx) => {
      const thumb = document.createElement("div");
      thumb.className = "photo-thumb" + (p.uploading ? " is-uploading" : "") + (p.error ? " has-error" : "");
      if (p.url || p.preview) {
        const img = document.createElement("img");
        img.src = p.url || p.preview;
        img.alt = p.name || "Damage photo";
        thumb.appendChild(img);
      }
      const x = document.createElement("button");
      x.type = "button";
      x.className = "photo-thumb-remove";
      x.setAttribute("aria-label", "Remove photo");
      x.textContent = "×";
      x.addEventListener("click", (e) => {
        e.stopPropagation();
        if (!activeGlassId || !damages[activeGlassId]) return;
        damages[activeGlassId].photos.splice(idx, 1);
        saveDamages();
        renderPhotos(damages[activeGlassId].photos);
      });
      thumb.appendChild(x);
      photoList.appendChild(thumb);
    });
  }

  function uploadPhoto(file) {
    const rec = damages[activeGlassId];
    const photoEntry = { name: file.name, size: file.size, uploading: true, preview: null, url: null };

    // Show an immediate preview (data URL).
    const reader = new FileReader();
    reader.onload = () => {
      photoEntry.preview = reader.result;
      renderPhotos(rec.photos);
    };
    reader.readAsDataURL(file);

    rec.photos.push(photoEntry);
    renderPhotos(rec.photos);

    const fd = new FormData();
    fd.append("photo", file);

    fetch("upload.php", { method: "POST", body: fd })
      .then((r) => r.json().catch(() => ({ ok: false })))
      .then((res) => {
        photoEntry.uploading = false;
        if (res && res.ok && res.url) {
          photoEntry.url = res.url;
          photoEntry.preview = null;     // drop the heavy base64 once we have a server URL
        } else {
          photoEntry.error = res && res.error ? res.error : "Upload failed";
        }
        saveDamages();
        renderPhotos(rec.photos);
      })
      .catch(() => {
        photoEntry.uploading = false;
        photoEntry.error = "Network error";
        saveDamages();
        renderPhotos(rec.photos);
      });
  }

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
  }

  /* ---------- Pill group handlers ---------- */
  function bindPillGroup(container, field, single) {
    container.addEventListener("click", (e) => {
      const pill = e.target.closest(".pill");
      if (!pill || !activeGlassId) return;
      const val = pill.dataset.value;
      const rec = damages[activeGlassId];
      if (!rec) return;

      if (single) {
        // Toggle: clicking the active pill again deselects it.
        rec[field] = (rec[field] === val) ? null : val;
        container.querySelectorAll(".pill").forEach((p) => {
          p.classList.toggle("active", rec[field] === p.dataset.value);
        });
      } else {
        if (!Array.isArray(rec[field])) rec[field] = [];
        const i = rec[field].indexOf(val);
        if (i >= 0) rec[field].splice(i, 1); else rec[field].push(val);
        pill.classList.toggle("active");
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
    if (!activeGlassId) {
      editingRow.hidden = true;
      damageGroup.querySelectorAll(".pill.active").forEach((p) => p.classList.remove("active"));
      featuresGroup.querySelectorAll(".pill.active").forEach((p) => p.classList.remove("active"));
      serviceGroup.querySelectorAll(".pill.active").forEach((p) => p.classList.remove("active"));
      notesInput.value = "";
      notesCount.textContent = "0";
      photoList.innerHTML = "";
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
    damages = {};
    activeGlassId = null;
    sessionStorage.removeItem("agx_damages");
    document.querySelectorAll(".glass-window.selected, .glass-window.has-damage").forEach((el) => {
      el.classList.remove("selected", "has-damage");
    });
    damageGroup.querySelectorAll(".pill.active").forEach((p) => p.classList.remove("active"));
    featuresGroup.querySelectorAll(".pill.active").forEach((p) => p.classList.remove("active"));
    serviceGroup.querySelectorAll(".pill.active").forEach((p) => p.classList.remove("active"));
    notesInput.value = "";
    notesCount.textContent = "0";
    photoList.innerHTML = "";
    renderSelectedChips();
    currentViewIndex = 0;
    showView(VIEWS[0]);
  }

  /* ---------- Persistence ---------- */
  function saveDamages() {
    // Strip transient photo fields (preview base64, uploading/error flags) before
    // persisting to sessionStorage. Only photos with a server URL survive a refresh.
    const slim = {};
    Object.keys(damages).forEach((id) => {
      const r = damages[id];
      slim[id] = {
        name:         r.name,
        damage_type:  r.damage_type,
        service_type: r.service_type || null,
        features:     Array.isArray(r.features) ? r.features.slice() : [],
        notes:        r.notes || "",
        photos:       (Array.isArray(r.photos) ? r.photos : [])
          .filter((p) => p && p.url)
          .map((p) => ({ url: p.url, name: p.name || "", size: p.size || 0 })),
      };
    });
    try {
      sessionStorage.setItem("agx_damages", JSON.stringify(slim));
    } catch (e) {
      console.warn("[agx] sessionStorage quota exceeded; dropping photo previews.", e);
    }
    applyHighlights();
    renderSelectedChips();
    updateContinueState();
  }

  function updateContinueState() {
    const hasAny = Object.keys(damages).length > 0;
    continueBtn.disabled = !hasAny;
    continueBtn.title = hasAny ? "" : "Select at least one window first";
  }

  function loadDamages() {
    try {
      return JSON.parse(sessionStorage.getItem("agx_damages") || "{}");
    } catch (e) {
      return {};
    }
  }

  /* ---------- Submit ---------- */
  const submitModal     = document.getElementById("submit-modal");
  const submitIcon      = submitModal.querySelector(".submit-icon");
  const submitTitle     = document.getElementById("submit-modal-title");
  const submitMessage   = document.getElementById("submit-modal-message");
  const submitActionBox = document.getElementById("submit-modal-actions");
  const submitClose     = document.getElementById("submit-cancel");
  const submitRetry     = document.getElementById("submit-retry");
  const submitDone      = document.getElementById("submit-done");

  let submitInFlight = false;
  let lastPayload    = null;

  submitClose.addEventListener("click", closeSubmitModal);
  submitDone.addEventListener("click", () => {
    closeSubmitModal();
    // After a successful submission, send the user back to Step 1 fresh.
    window.location.href = "index.php";
  });
  submitRetry.addEventListener("click", () => {
    if (lastPayload) sendPayload(lastPayload);
  });
  submitModal.addEventListener("click", (e) => {
    if (e.target === submitModal && submitIcon.dataset.state !== "loading") closeSubmitModal();
  });

  function setSubmitState(state, opts) {
    opts = opts || {};
    submitIcon.dataset.state = state;
    submitTitle.textContent   = opts.title || "";
    submitMessage.textContent = opts.message || "";

    submitClose.hidden = !opts.showClose;
    submitRetry.hidden = !opts.showRetry;
    submitDone.hidden  = !opts.showDone;
    submitActionBox.hidden = !(opts.showClose || opts.showRetry || opts.showDone);
  }

  function openSubmitModal() {
    submitModal.hidden = false;
    requestAnimationFrame(() => submitModal.classList.add("open"));
  }

  function closeSubmitModal() {
    submitModal.classList.remove("open");
    setTimeout(() => { submitModal.hidden = true; }, 200);
  }

  function setContinueLoading(loading) {
    continueBtn.classList.toggle("is-loading", loading);
    continueBtn.disabled = loading || Object.keys(damages).length === 0;
    const spinner = continueBtn.querySelector(".btn-spinner");
    if (spinner) spinner.hidden = !loading;
  }

  function submitAll() {
    if (submitInFlight) return;            // double-submit guard
    if (Object.keys(damages).length === 0) return;

    const vehicleRaw = sessionStorage.getItem("agx_vehicle");
    if (!vehicleRaw) {
      window.location.href = "index.php";
      return;
    }
    let vehicle;
    try { vehicle = JSON.parse(vehicleRaw); } catch (_) { vehicle = {}; }
    const payload = { vehicle: vehicle, damages: damages };
    lastPayload = payload;
    sendPayload(payload);
  }

  function sendPayload(payload) {
    submitInFlight = true;
    setContinueLoading(true);
    setSubmitState("loading", {
      title:   "Sending your request…",
      message: "One moment while we save your glass selection."
    });
    openSubmitModal();

    fetch("submit.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload)
    })
      .then((r) => r.json().catch(() => ({ ok: false })))
      .then((res) => {
        submitInFlight = false;
        setContinueLoading(false);
        if (res && res.ok) {
          sessionStorage.removeItem("agx_vehicle");
          sessionStorage.removeItem("agx_damages");
          setSubmitState("success", {
            title:   "Thanks! We received your request.",
            message: "Reference: " + (res.id || "—") + ". We'll contact you shortly with the next steps.",
            showDone: true
          });
        } else {
          setSubmitState("error", {
            title:   "Something went wrong.",
            message: (res && res.error) || "Please try again in a moment.",
            showClose: true,
            showRetry: true
          });
        }
      })
      .catch(() => {
        submitInFlight = false;
        setContinueLoading(false);
        setSubmitState("error", {
          title:   "Network error.",
          message: "We couldn't reach the server. Check your connection and try again.",
          showClose: true,
          showRetry: true
        });
      });
  }

  /* ---------- Utility ---------- */
  function escapeHtml(s) {
    return String(s).replace(/[&<>"']/g, (c) => ({
      "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;"
    }[c]));
  }
})();
