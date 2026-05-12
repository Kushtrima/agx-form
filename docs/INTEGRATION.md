# Integration Fit Assessment

*Will this form merge cleanly into a larger PHP web app?*

---

## Short answer

**Yes — with low integration cost.** The architecture was kept deliberately framework-free and convention-friendly precisely so it can slot into any host PHP app. Most of the work is **plumbing** (routing, layout, auth, DB), not rewriting.

Below is what's compatible out of the box, what will need adapting, and the suggested integration path.

---

## ✅ What's already compatible

| Aspect | Why it fits |
|---|---|
| **Pure PHP** (no framework) | No Composer dependencies; no Laravel/Symfony/Yii bootstrap to fight with. Drops into any PHP host or module. |
| **No build step** | No webpack, npm, or compile step to coordinate with the host app's pipeline. Files ship as-is. |
| **All globals are prefixed** | PHP: `AGX_*` constants (`AGX_BASE_DIR`, `AGX_DATA_DIR`, …), `agx_*` helper functions (`agx_options()`, `agx_same_origin()`). JS: `STORAGE_KEYS.*` constants, every script wrapped in `(function() {...})()` IIFEs — **zero `window.foo` pollution**. Won't collide with the parent app's globals. |
| **sessionStorage namespaced** | All keys prefixed `agx_*` (`agx_vehicle`, `agx_damages`, `agx_service`, `agx_contact`). Safe alongside the parent app's storage. |
| **PHP 8 syntax, modern conventions** | Type hints, null-coalescing, strict comparisons. Compatible with any modern PHP host. |
| **Data layer is pluggable** | Persistence today is a JSONL file with one append per submission. Already provisioned `includes/db.php` (PDO stub) for trivial migration to the host app's DB. |
| **Validation is self-contained** | Every input is whitelisted server-side against `data/options.json`. No assumptions about the host app's validation library. |
| **No PHP sessions required** | The form is **stateless on the server** — all step-to-step state lives in the browser's `sessionStorage`. So it won't conflict with the parent app's session handling. |
| **Static assets follow standard paths** | `assets/css/`, `assets/js/`, `vehicles/sedan/*.svg`, `uploads/`. The parent app's web server can already serve these as-is. |

---

## ⚠️ What will need adapting

These are routine integration tasks, none are blockers.

### 1. Routing
- **Today:** plain URLs — `/index.php`, `/selector.php`, `/service.php`, `/contact.php`, `/submit.php`
- **In a larger app:** probably routed through `/quote/step-1`, `/quote/step-2`, … or `index.php?page=quote.step1`
- **Adapt:** the file content stays; either wrap each page in the host app's router (return rendered HTML from a controller), or rename to the parent app's URL scheme. The four JS files reference `index.php`, `selector.php` etc. by name in `window.location.href = "..."` — those strings would need to be replaced with the host app's URLs (single search-and-replace, ~12 callsites total).

### 2. Page layout / header & footer
- **Today:** each PHP page renders its own `<html>`, `<head>`, `<body>`, the blue background, the headline ("Free Instant Estimates"), and the stepper
- **In a larger app:** there's usually a global header/footer/nav. The form pages would need to live **inside** that shell, not replace it.
- **Adapt:** extract the unique content of each page (`<div class="page-wrap">…</div>`) and inject it into the host app's layout. The headline + stepper + card structure can stay as a partial. ~30 minutes per page.

### 3. CSS namespacing
- **Today:** generic class names: `.btn`, `.card`, `.field`, `.pill`, `.modal-overlay`
- **In a larger app:** these names might collide with existing styles. Most are scoped enough (e.g. `.pill-stack`, `.choice-card`, `.car-view`) but `.btn`, `.field`, `.card` are common.
- **Adapt options:**
  - (a) Wrap everything in a parent class `.agx-form { … }` and prefix selectors — ~1 hour of CSS find-and-replace
  - (b) Use the existing names if the host app doesn't define them — zero work, just verify
  - (c) Adopt the host app's design system entirely — bigger rewrite but better long-term

### 4. Admin authentication
- **Today:** `admin.php` uses HTTP Basic auth with credentials from env vars
- **In a larger app:** the parent app already has user/role/login
- **Adapt:** replace the Basic-auth block at the top of `admin.php` with a call to the host app's `require_role('admin')` or equivalent. ~10 lines of code.

### 5. Database persistence
- **Today:** `submit.php` does `file_put_contents(submissions.jsonl, …, FILE_APPEND | LOCK_EX)`
- **In a larger app:** use the host app's DB connection (Doctrine ORM / Eloquent / PDO)
- **Adapt:** replace the persistence block at the bottom of `submit.php` (~15 lines) with an `INSERT INTO submissions …`. The `$record` array is already shaped exactly like a row. Helper `includes/db.php` is the obvious place to hook in.

### 6. CSRF strategy
- **Today:** Origin/Referer check (sufficient for same-origin form posts)
- **In a larger app:** likely uses per-session CSRF tokens
- **Adapt:** emit the host app's CSRF token in each form page; have `submit.php` validate it. ~30 minutes.

### 7. Asset URLs / base path
- **Today:** assets referenced relatively: `<link href="assets/css/form.css">`
- **In a larger app:** there's usually a `BASE_URL` or asset versioning system
- **Adapt:** replace bare paths with `<?= asset('assets/css/form.css') ?>` or whatever the host uses. ~5 lines per page.

### 8. Internationalization (if applicable)
- **Today:** English labels in `data/options.json` and inline in the PHP/HTML
- **In a larger app:** if the host is multi-language, labels need to go through a translation function
- **Adapt:** wrap `data/options.json` reads with `t($label)` calls, and move PHP-inline strings into the host's i18n files. Already centralised in JSON, so this is mechanical, not creative.

---

## 🎯 Recommended integration path

In order, smallest risk first:

```
1. Drop the files into the host app's tree    →  smoke test: do they 404 or render?
   (under e.g. /modules/agx-form/)               (probably render fine, paths just need adjustment)

2. Convert pages into the host app's          →  smoke test: does the host app's header/footer
   layout system (extract page-content,           appear around the form, navbar stays consistent?
   inject into parent shell)

3. Replace bare URLs in JS                    →  smoke test: do step transitions still work?
   (window.location.href, fetch endpoints)

4. Replace admin auth + DB persistence        →  smoke test: submit a form, check the DB row;
   (the only TWO PHP files that touch I/O:        log in as a non-admin user, get 403?
    admin.php and submit.php — surgical edits)

5. Apply CSS namespacing if collisions found  →  smoke test: form looks right, parent app
   (visual regression)                           unchanged

6. Add CSRF token integration                 →  smoke test: form still submits with token;
                                                  fails without
```

**Effort estimate:** a competent PHP dev can complete steps 1–4 in **half a day to a day**. Steps 5–6 add another half-day depending on the host app's conventions.

---

## 🟢 What you DON'T need to redo

- The **business logic** (validation rules, glass IDs, options catalog)
- The **SVG glass selector** (it's just HTML — drops in unchanged)
- The **multi-step flow** (sessionStorage approach is host-app agnostic)
- The **accessibility work** (semantic HTML / ARIA travels with the markup)
- The **data shape** for submissions (already designed like a clean DB row)

---

## 🟡 Risks to flag

1. **`sessionStorage` is cleared if the user opens the form in a private/incognito session, then closes the tab.** Same as today; mention it because the host app might use `localStorage` or server-side sessions instead.
2. **The form does not currently integrate with the host app's user identity.** If the parent app has logged-in users, you'd typically want the submission tied to the user (e.g. `user_id` column). Easy to add — just pull the user from the host app's session in `submit.php`.
3. **The JSONL log will need to be migrated to the host app's DB at integration time**, not after — otherwise you'll have submissions in two places.
4. **If the host app uses a template engine** (Twig, Blade, Smarty), the inline `<?= ?>` PHP would need converting. The logic is trivial enough that this is mostly mechanical, not a rewrite.

---

## File-by-file integration map

Quick reference for what touches what during integration:

| File | What it does | Integration touch points |
|---|---|---|
| `index.php` | Step 1 vehicle form | Layout wrap, asset paths |
| `selector.php` | Step 2 glass selector | Layout wrap, asset paths |
| `service.php` | Step 3 service & scheduling | Layout wrap, asset paths |
| `contact.php` | Step 4 contact + review modal | Layout wrap, asset paths |
| `submit.php` | POST endpoint — final submission | Replace JSONL persistence with DB INSERT, add CSRF, optionally tie to logged-in user |
| `upload.php` | POST endpoint — photo upload | Add CSRF, optionally tie to user |
| `admin.php` | HTTP Basic admin view | Replace auth block with host's role check |
| `includes/config.php` | Constants + helpers | Most likely stays as-is; helpers may move to host's autoload |
| `includes/db.php` | Stubbed PDO connection | Replace with host's DB connection method |
| `assets/css/*.css` | Styles | CSS namespacing if collisions |
| `assets/js/*.js` | Page logic | Replace hardcoded URLs (`index.php` etc.) with host's URLs |
| `data/options.json` | Single source of truth — damage types, features, glass IDs, etc. | Stays as-is OR move to DB seeds |
| `data/vehicles.json` | Year/brand/model catalog | Stays as-is OR pull from existing vehicle table |
| `vehicles/sedan/*.svg` | The clickable car illustrations | Stay as-is, served as static assets |
| `uploads/` | Customer photo uploads | Make sure write permissions exist on host |

---

## TL;DR

> "The form is **framework-free PHP + vanilla JS + inline SVG**. All globals are prefixed (`AGX_*`, `agx_*`, `STORAGE_KEYS.*`), all state lives in `sessionStorage` so server sessions aren't touched, and all data flows through `data/options.json` as a single source of truth. It drops into any PHP host with **half a day to a day of plumbing** — routing, layout wrap, swap admin auth for the host app's auth, and swap the JSONL persistence for the host app's DB. No architectural rewrite needed; the data shape and validation rules are already designed like clean DB rows."
