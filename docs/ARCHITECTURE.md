# AGX Form — Architecture

*A brief technical overview of the system.*

---

## 1. What it is

A **4-step web form** that lets a vehicle owner request a free quote for damaged automotive glass. The user selects their vehicle, points to the damaged glass on an interactive SVG diagram, picks service preferences, enters contact info, and submits — receiving a tracking reference number.

Live form: 4 functional steps, 11 clickable glass regions per vehicle, full validation client- and server-side, admin dashboard for incoming submissions.

---

## 2. Architecture at a glance

```
┌─────────────────────────────────────────────────────────────┐
│                       BROWSER (Client)                       │
│                                                              │
│   Step 1            Step 2            Step 3        Step 4   │
│   index.php   →   selector.php   →   service.php → contact.php
│   (vehicle)       (clickable SVG)    (booking)     (review)  │
│                                                              │
│              ◄── sessionStorage carries state ──►            │
│       (agx_vehicle, agx_damages, agx_service, agx_contact)   │
└────────────────────────┬─────────────────────────────────────┘
                         │ JSON POST (same-origin, ≤256 KB)
                         ▼
┌─────────────────────────────────────────────────────────────┐
│                       PHP BACKEND                            │
│                                                              │
│    submit.php   ◄── Origin/Referer + whitelist validation    │
│    upload.php   ◄── MIME-checked photo uploads               │
│    admin.php    ◄── HTTP Basic auth + safety check           │
│                                                              │
│              ▼                                               │
│        data/submissions.jsonl  (append-only, lock-protected) │
│        uploads/YYYY/MM/...     (per-month photo bucket)      │
└─────────────────────────────────────────────────────────────┘

Single source of truth:  data/options.json + data/vehicles.json
        (drives BOTH the rendered UI AND the server whitelist)
```

---

## 3. Tech stack

| Layer | Tool | Why |
|---|---|---|
| Frontend | **Plain HTML + CSS + vanilla JavaScript** | Zero build step, instant editing, no framework lock-in |
| Visual selector | **Inline SVG** with semantic IDs (`front_windshield`, `right_quarter_window`, …) | Clickable, stylable, accessible, no image map hacks |
| State | **`sessionStorage`** | Survives reloads, scoped to the tab, no cookies/PII leaks |
| Backend | **PHP 8** (single files, no framework) | Trivially deployable to any shared host or Docker container |
| Persistence | **JSONL log file** for MVP; **PDO MySQL stub** ready for swap-in | Painless local dev; clear migration path |
| Auth (admin) | **HTTP Basic** | Sufficient for an internal-only triage page |

No npm, no Composer dependencies. The entire app runs with `php -S` on any laptop.

---

## 4. Key design decisions

### 4.1 — Single source of truth for options

Damage types, features, crack sizes, glass-specific questions, time slots, body styles, and the per-body glass whitelist all live in **`data/options.json`**. The same file feeds:

- The HTML rendering (server-side PHP loops)
- The browser's JavaScript (inlined via `<script type="application/json">`)
- The server's validation whitelists (rejects anything not in the file)
- The admin view's label resolution (e.g. `chip_crack → "Chip / Crack"`)

**Consequence:** adding a new damage type or a new glass-specific question is **a single JSON edit** — no code changes anywhere.

### 4.2 — Stable glass IDs as a contract

Every clickable window on every SVG has a semantic id (`front_windshield`, `right_quarter_window`, etc.) that is referenced **identically** in:

- The SVG markup
- The JavaScript view-routing map
- The PHP validation whitelist
- The JSON record format that the future DB will receive

That keeps SVG, JS, PHP, DB, and admin perfectly aligned without a translation layer.

### 4.3 — Body-style SVGs, not per-brand

Instead of needing an SVG for every BMW/Mercedes/Audi/etc., the form maps **brand → body style → SVG folder**. BMW sedan, Audi sedan, and Mercedes sedan all share `vehicles/sedan/*.svg`. Future body styles (combi, pickup, SUV, van) drop in the same way without touching code.

### 4.4 — Progressive multi-step flow with full back-navigation

State is persisted on every change to `sessionStorage`, with **guards** on each step that bounce users back if they skip a prerequisite. The user can hit Back at any point, fix something, and step forward again with selections intact. The final "Review your request" modal lets them jump directly to any step to edit.

### 4.5 — Glass-specific questions, data-driven

A 4th column appears **only when the active glass has additional questions defined** in `glass_specific_options`:

- **Windshield** → "Glass style" (Standard / Split / bench)
- **Rear window** → "Defrost" (Yes / No / Not sure)
- **Sunroof** → "Sunroof type" (Single / Dual + visual divider overlay)

Adding a glass-specific question for a new glass is — again — a JSON edit.

---

## 5. Security & validation

| Concern | Defense |
|---|---|
| Path traversal in photo upload URLs | Whitelisted path prefix + `..` check + `is_file()` confirmation (3 layers) |
| Cross-site form submission (CSRF) | Origin/Referer check on `submit.php` and `upload.php` (rejects cross-origin POST with 403) |
| Denial-of-service via huge payload | Hard 256 KB cap before parsing JSON |
| Malicious file upload | `finfo` MIME detection (not client-supplied type) + 6 MB size cap + server-generated filenames |
| Default admin password leak | `admin.php` returns 503 if password is still `change-me` AND request is non-localhost |
| XSS in rendered user input | `htmlspecialchars` (PHP) + `escapeHtml` (JS) on every interpolation |
| Injection via dropdown values | Every enum is whitelisted server-side against `options.json` |
| Invalid VIN / email / phone / ZIP | Regex + format checks server-side on every submission |

---

## 6. Accessibility

Built in from the start, not retrofitted:

- Every clickable SVG glass is a `role="button"` with `tabindex`, `aria-label`, `<title>`, and keyboard activation (Enter / Space)
- Stepper uses semantic `<ol>` / `<li>` with `aria-current="step"`
- All three modals (Reset, Submit, Review) implement **focus trapping**, **focus restoration on close**, and **Escape-to-dismiss**
- All text contrast verified against **WCAG AA** (muted text, green CTA were both adjusted to pass)
- The "Preferred time" pill group uses a proper `role="radiogroup"` + `aria-labelledby` + per-pill `aria-checked`

---

## 7. Future scaling path

Already designed for the next steps **without re-architecting**:

| Coming addition | What's needed |
|---|---|
| **Combi / pickup / SUV body styles** | Add `vehicles/<style>/*.svg` with the same id convention + flip `available: true` in `vehicles.json` |
| **MySQL persistence** | `includes/db.php` is a PDO stub; flip `submit.php` to use `INSERT` instead of `file_put_contents` |
| **Email/SMS notification** | Drop a `mail()` or queue dispatch into `submit.php` between validation and persistence |
| **Photo uploads, per-glass notes, repair-vs-replace** | UI components already built and tested; just re-add their rows to the damage panel |
| **Pagination + search in admin** | Linear scan of JSONL today; trivial to replace with a DB query |
| **Internationalization** | Labels are already centralised in JSON files — wrap with a translation map |

---

## 8. Project metrics (current state)

- **PHP**: 7 files (pages + endpoints + config) — ~700 lines total
- **JavaScript**: 4 files — ~1,100 lines total
- **CSS**: 5 files — ~1,100 lines total
- **SVGs**: 5 sedan views with 11 semantic clickable regions
- **JSON config**: `options.json` (~80 lines) + `vehicles.json` (~30 lines)
- **Zero external dependencies** — no `vendor/`, no `node_modules/`

---

## 9. Approach summary

- **Data-driven, not hard-coded.** Changes to options, glasses, vehicles, or labels are JSON edits, not code edits.
- **Layered validation.** Client (UX feedback) → server (truth). Never trust the client.
- **Stable contracts.** Glass IDs and field names stay constant from SVG to database; no translation tables.
- **Progressive enhancement, not lock-in.** Pure PHP / JS / CSS today; ready for React / MySQL / Docker tomorrow without rewriting the data model.
- **Accessibility & security as defaults**, not afterthoughts. Every input validated, every modal traps focus, every endpoint enforces same-origin.

---

## 10. File map

| Path | Purpose |
|---|---|
| `index.php` | Step 1 — Vehicle info form |
| `selector.php` | Step 2 — Clickable glass selector with damage panel |
| `service.php` | Step 3 — Service mode, payment, scheduling |
| `contact.php` | Step 4 — Contact info + review modal + final submit |
| `submit.php` | POST endpoint — receives & persists submissions |
| `upload.php` | POST endpoint — photo upload (multipart) |
| `admin.php` | HTTP-Basic-auth admin dashboard of submissions |
| `includes/config.php` | Constants, helpers (`agx_options()`, `agx_vehicles()`, `agx_same_origin()`) |
| `includes/db.php` | Stubbed PDO connection (for future MySQL integration) |
| `assets/css/form.css` | Shared base (stepper, card, buttons, modals) |
| `assets/css/selector.css` | Selector-specific (car stage, chips, pills, damage grid) |
| `assets/css/service.css` | Service page (choice cards, info note, schedule) |
| `assets/css/contact.css` | Contact form + review modal |
| `assets/css/admin.css` | Admin table + per-row detail |
| `assets/js/form.js` | Step 1 logic |
| `assets/js/selector.js` | Step 2 logic — view switching, click-to-select, panel sync |
| `assets/js/service.js` | Step 3 logic — pills, date/time, validation |
| `assets/js/contact.js` | Step 4 logic — contact form, review modal, submit pipeline |
| `data/options.json` | Single source of truth for damage types, features, glass IDs, etc. |
| `data/vehicles.json` | Year / brand → models / body styles catalog |
| `vehicles/sedan/*.svg` | 5 views of a sedan with semantic clickable glass IDs |
| `uploads/` | Runtime photo storage (gitignored) |
| `data/submissions.jsonl` | Append-only submission log (gitignored) |

---

*Repository: [github.com/Kushtrima/agx-form](https://github.com/Kushtrima/agx-form)*
