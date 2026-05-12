/* ==========================================================
   Step 1 — Vehicle info form
   ========================================================== */
(function () {
  "use strict";

  // Shared sessionStorage keys (mirrored in form.js / selector.js / service.js / contact.js)
  const STORAGE_KEYS = {
    VEHICLE: "agx_vehicle",
    DAMAGES: "agx_damages",
    SERVICE: "agx_service",
    CONTACT: "agx_contact",
  };

  // Detect runtime: PHP server (URL has .php) vs static GitHub Pages
  // (hostname is github.io or filename ends with .html). Used to route
  // navigation links to the right file extension.
  const IS_STATIC =
    window.location.hostname.endsWith(".github.io") ||
    window.location.pathname.endsWith(".html");
  const PAGE_EXT = IS_STATIC ? ".html" : ".php";
  function pageUrl(name) { return name + PAGE_EXT; }

  const yearSel  = document.getElementById("year");
  const brandSel = document.getElementById("brand");
  const modelSel = document.getElementById("model");
  const bodySel  = document.getElementById("body_style");
  const vinInput = document.getElementById("vin");
  const continueBtn = document.getElementById("continue-btn");
  const cancelBtn   = document.getElementById("cancel-btn");
  const form = document.getElementById("vehicle-form");

  let DATA = null;

  // Vehicles data is inlined by the server into a <script type="application/json"> tag.
  // Falling back to a fetch() for resilience if the inline payload is missing.
  function loadInlineData() {
    const tag = document.getElementById("agx-vehicles-data");
    if (!tag) return null;
    try { return JSON.parse(tag.textContent || "{}"); } catch (_) { return null; }
  }

  function bootstrap(data) {
    DATA = data;
    populateYears(data.years || []);
    populateBrands(Object.keys(data.brands || {}));
    populateBodyStyles(data.body_styles || []);
    restoreFromStorage();
    validate();
  }

  const inline = loadInlineData();
  if (inline && inline.years) {
    bootstrap(inline);
  } else {
    fetch("data/vehicles.json")
      .then((r) => r.json())
      .then(bootstrap)
      .catch(() => console.error("Failed to load data/vehicles.json"));
  }

  function populateYears(years) {
    yearSel.innerHTML = '<option value="" selected disabled>Select Year</option>';
    years.forEach((y) => yearSel.appendChild(new Option(y, y)));
  }

  function populateBrands(brands) {
    brandSel.innerHTML = '<option value="" selected disabled>Select Brand</option>';
    brands.forEach((b) => brandSel.appendChild(new Option(b, b)));
  }

  function populateModels(models) {
    modelSel.innerHTML = '<option value="" selected disabled>Select Model</option>';
    models.forEach((m) => modelSel.appendChild(new Option(m, m)));
    modelSel.disabled = false;
  }

  function populateBodyStyles(list) {
    bodySel.innerHTML = '<option value="" selected disabled>Select Body Style</option>';
    list.forEach((b) => {
      const opt = new Option(b.available ? b.label : `${b.label} (coming soon)`, b.value);
      if (!b.available) opt.disabled = true;
      bodySel.appendChild(opt);
    });
  }

  brandSel.addEventListener("change", () => {
    const brand = brandSel.value;
    if (DATA && DATA.brands[brand]) {
      populateModels(DATA.brands[brand]);
    } else {
      modelSel.innerHTML = '<option value="" selected disabled>Select Model</option>';
      modelSel.disabled = true;
    }
    validate();
  });

  [yearSel, modelSel, bodySel, vinInput].forEach((el) => {
    el.addEventListener("change", validate);
    el.addEventListener("input", validate);
  });

  function validate() {
    const ok = yearSel.value && brandSel.value && modelSel.value && bodySel.value;
    continueBtn.disabled = !ok;
  }

  function restoreFromStorage() {
    try {
      const raw = sessionStorage.getItem(STORAGE_KEYS.VEHICLE);
      if (!raw) return;
      const v = JSON.parse(raw);
      if (v.year && [...yearSel.options].some(o => o.value === v.year)) yearSel.value = v.year;
      if (v.brand && [...brandSel.options].some(o => o.value === v.brand)) {
        brandSel.value = v.brand;
        if (DATA && DATA.brands[v.brand]) populateModels(DATA.brands[v.brand]);
      }
      if (v.model && [...modelSel.options].some(o => o.value === v.model)) modelSel.value = v.model;
      if (v.body_style && [...bodySel.options].some(o => o.value === v.body_style)) bodySel.value = v.body_style;
      if (v.vin) vinInput.value = v.vin;
    } catch (e) { /* ignore */ }
  }

  form.addEventListener("submit", (e) => {
    e.preventDefault();
    if (continueBtn.disabled) return;
    const payload = {
      year:       yearSel.value,
      brand:      brandSel.value,
      model:      modelSel.value,
      body_style: bodySel.value,
      vin:        vinInput.value.trim()
    };
    sessionStorage.setItem(STORAGE_KEYS.VEHICLE, JSON.stringify(payload));
    window.location.href = pageUrl("selector");
  });

  cancelBtn.addEventListener("click", (e) => {
    e.preventDefault();
    sessionStorage.removeItem(STORAGE_KEYS.VEHICLE);
    sessionStorage.removeItem(STORAGE_KEYS.DAMAGES);
    [yearSel, brandSel, modelSel, bodySel].forEach((s) => { s.selectedIndex = 0; });
    modelSel.disabled = true;
    vinInput.value = "";
    validate();
  });
})();
