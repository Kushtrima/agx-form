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

  rotatePrev.addEventListener("click", () => rotate(-1));
  rotateNext.addEventListener("click", () => rotate(1));
  removeBtn.addEventListener("click", removeActive);
  continueBtn.addEventListener("click", submitAll);
  backBtn.addEventListener("click", () => { window.location.href = "index.php"; });
  resetBtn.addEventListener("click", resetAll);

  bindPillGroup(damageGroup, "damage_type", true);   // single-select
  bindPillGroup(featuresGroup, "features", false);   // multi-select

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
    });
  }

  function selectWindow(el) {
    const id   = el.id;
    const name = el.getAttribute("data-glass-name") || id;
    activeGlassId = id;

    if (!damages[id]) {
      damages[id] = { name, damage_type: null, features: [] };
    }

    selectedName.textContent = name;
    editingRow.hidden = false;
    damagePanel.classList.add("open");

    syncPanelFromDamage(damages[id]);
    saveDamages();           // persists + applies highlights
    renderSelectedChips();
  }

  function syncPanelFromDamage(rec) {
    damageGroup.querySelectorAll(".pill").forEach((p) => {
      p.classList.toggle("active", rec.damage_type === p.dataset.value);
    });
    featuresGroup.querySelectorAll(".pill").forEach((p) => {
      p.classList.toggle("active", rec.features.includes(p.dataset.value));
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
        rec.damage_type = (rec.damage_type === val) ? null : val;
        container.querySelectorAll(".pill").forEach((p) => {
          p.classList.toggle("active", rec.damage_type === p.dataset.value);
        });
      } else {
        const i = rec.features.indexOf(val);
        if (i >= 0) rec.features.splice(i, 1); else rec.features.push(val);
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

  function openResetModal() {
    resetModal.hidden = false;
    requestAnimationFrame(() => resetModal.classList.add("open"));
  }
  function closeResetModal() {
    resetModal.classList.remove("open");
    setTimeout(() => { resetModal.hidden = true; }, 200);
  }

  resetCancel.addEventListener("click", closeResetModal);
  resetConfirm.addEventListener("click", () => {
    closeResetModal();
    performReset();
  });
  resetModal.addEventListener("click", (e) => {
    if (e.target === resetModal) closeResetModal();
  });
  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape" && resetModal.classList.contains("open")) closeResetModal();
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
    renderSelectedChips();
    currentViewIndex = 0;
    showView(VIEWS[0]);
  }

  /* ---------- Persistence ---------- */
  function saveDamages() {
    sessionStorage.setItem("agx_damages", JSON.stringify(damages));
    applyHighlights();
    renderSelectedChips();
  }

  function loadDamages() {
    try {
      return JSON.parse(sessionStorage.getItem("agx_damages") || "{}");
    } catch (e) {
      return {};
    }
  }

  /* ---------- Submit ---------- */
  function submitAll() {
    const vehicleRaw = sessionStorage.getItem("agx_vehicle");
    if (!vehicleRaw) {
      window.location.href = "index.php";
      return;
    }
    const vehicle = JSON.parse(vehicleRaw);
    const payload = { vehicle: vehicle, damages: damages };

    fetch("submit.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload)
    })
      .then((r) => r.json())
      .then((res) => {
        if (res && res.ok) {
          alert("Submission saved. Reference: " + res.id);
          sessionStorage.removeItem("agx_vehicle");
          sessionStorage.removeItem("agx_damages");
        } else {
          alert("Submission failed.");
        }
      })
      .catch(() => alert("Network error."));
  }

  /* ---------- Utility ---------- */
  function escapeHtml(s) {
    return String(s).replace(/[&<>"']/g, (c) => ({
      "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;"
    }[c]));
  }
})();
