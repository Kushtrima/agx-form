# AGX — Vehicle Glass Estimate Form

A two-step web form that lets a customer pick the damaged glass on their car and request a quote. The user fills out vehicle info, then clicks the affected window on a clickable SVG diagram and describes the damage.

Live demo: run locally (see below). Source: [github.com/Kushtrima/agx-form](https://github.com/Kushtrima/agx-form).

---

## Features

- **Step 1 — Vehicle info**: Year, Brand, Model, Body Style, optional VIN. Brand → Model is cascading.
- **Step 2 — Glass selector**: 5 SVG views per body style (right, front, left, back, top/sunroof) with a single rotate control.
- **Multi-glass selection**: click any window to mark damage; switch views and mark more glasses; a chip list shows everything selected.
- **Per-glass options**: damage type (chip / shattered / scratched / leaking) and special features (heated, ADAS, acoustic, factory tint, rain sensor, not sure).
- **Reset & Submit**: in-app modal confirms a destructive reset; final payload is POSTed as JSON to the backend.
- **Persistence**: vehicle info and damage selections are kept in `sessionStorage`, so the page can be reloaded mid-flow without losing progress.

The visual selector uses **body-style SVGs only**, not per-brand. BMW sedan, Mercedes sedan, and Audi sedan all share `vehicles/sedan/*.svg`.

---

## Tech stack

| Layer | Tool |
|---|---|
| Frontend | Plain HTML + CSS + vanilla JavaScript (no build step) |
| SVG | Inline-embedded via PHP `file_get_contents`, semantic IDs, `vector-effect: non-scaling-stroke` |
| Backend | PHP 8 (single file `submit.php`) |
| Storage | `data/submissions.jsonl` (one JSON record per submission). PDO MySQL stub in `includes/db.php` for when a database is wired up. |

No frameworks, no npm install — open in any PHP-capable host.

---

## Folder structure

```
agx-form/
├── index.php              Step 1 — vehicle info form
├── selector.php           Step 2 — glass selector + damage panel
├── submit.php             POST endpoint that saves the submission
│
├── assets/
│   ├── css/
│   │   ├── form.css       Shared layout (background, stepper, card, buttons, modal)
│   │   └── selector.css   Selector-specific (car stage, chips, pills)
│   └── js/
│       ├── form.js        Step 1 logic (cascading dropdowns, validation, sessionStorage)
│       └── selector.js    Step 2 logic (view rotation, click-to-select, panel sync, modal, submit)
│
├── vehicles/
│   └── sedan/
│       ├── right.svg      All five views — each window has a stable ID
│       ├── front.svg
│       ├── left.svg
│       ├── back.svg
│       └── top.svg
│
├── data/
│   ├── vehicles.json      Years, brands → models, body styles
│   └── submissions.jsonl  (created at runtime, gitignored)
│
├── uploads/               Future: customer-uploaded damage photos (gitignored)
│
└── includes/
    ├── config.php         Paths and DB constants
    └── db.php             Lazy PDO MySQL connection (placeholder)
```

---

## Running locally

Requires PHP 8+ on your machine.

```bash
git clone https://github.com/Kushtrima/agx-form.git
cd agx-form
php -S 127.0.0.1:8765
```

Then open:

- Step 1: <http://127.0.0.1:8765/>
- Step 2 (skips Step 1 only if you already have vehicle data in sessionStorage): <http://127.0.0.1:8765/selector.php>

---

## Glass naming convention

Every clickable window inside an SVG must have a clean, semantic `id`. The exact same names are used across SVG, JavaScript, PHP, and the future database — so the contract stays single-sourced.

For sedan:

| ID | View it appears on |
|---|---|
| `front_windshield` | front |
| `rear_window` | back |
| `right_front_door_window` | right |
| `right_rear_door_window` | right |
| `left_front_door_window` | left |
| `left_rear_door_window` | left |
| `sunroof_glass` | top |

> Never ship random IDs like `path123`, `shape45`, or `group88`. The SVG is part of the API.

The mapping `glass_id → view` lives in `assets/js/selector.js` so the chip list can navigate the user back to the right view when re-editing a glass.

---

## Adding a new body style

The MVP ships sedan only. To add **combi**, **pickup**, **SUV**, etc.:

1. Create `vehicles/<style>/` with `right.svg`, `left.svg`, `front.svg`, `back.svg`, `top.svg`.
2. Each SVG must:
   - Use a `viewBox` (no fixed mm width/height).
   - Apply `class="car-body"` to outline strokes and `class="glass-window"` + a unique `id` + `data-glass-name="..."` to each clickable window.
   - Use stroke `#1F5AAD` with `vector-effect: non-scaling-stroke` so line weight stays consistent across views.
3. Flip `available: true` for that body style in `data/vehicles.json`.
4. Update `GLASS_TO_VIEW` in `assets/js/selector.js` if you introduce new glass IDs.

Currently the selector page reads `vehicles/sedan/*` directly. To support multiple styles, change the `$bodyCategory` line near the top of `selector.php` to read from session/POST data.

---

## Submission payload

`POST /submit.php` with `Content-Type: application/json`:

```json
{
  "vehicle": {
    "year":       "2022",
    "brand":      "BMW",
    "model":      "3 Series",
    "body_style": "sedan",
    "vin":        ""
  },
  "damages": {
    "right_front_door_window": {
      "name":        "Right Front Door Window",
      "damage_type": "chip_crack",
      "features":    ["heated_glass", "rain_sensor"]
    },
    "sunroof_glass": {
      "name":        "Sunroof Glass",
      "damage_type": "shattered_broken",
      "features":    []
    }
  }
}
```

Response:

```json
{ "ok": true, "id": "agx_..." }
```

The record is appended to `data/submissions.jsonl` for now. Swap to a DB by filling in `includes/config.php` constants and using `get_db()` from `includes/db.php` inside `submit.php`.

---

## What's intentionally out of scope (MVP)

- Real 3D rotation
- Per-brand SVGs
- Automatic price calculation
- AI damage detection
- Multiple body styles (only sedan ships in v1)

These can be layered on without changing the data model — the IDs and JSON shape are designed to extend cleanly.

---

## License

Private — all rights reserved.
