# PawAdopt — Fix Pack (UI, Community, Ads)

Deploy = copy these files over the repo root and push. **No file needs manual editing.**

---

## 1. What was broken and why

### A. "I Agree" button on the privacy notice was dead
`login.php` had a strict-mode JavaScript bug. The gate required
`paPolicyRead` to be true before it would let the checkbox unlock **I Agree**,
but `paPolicyRead` was **never declared** (`'use strict'` at the top of the
script). Ticking the box threw `ReferenceError: paPolicyRead is not defined`,
so `btn.disabled` stayed `true` forever and the click did nothing.

**Fix:** removed the undeclared variable, unlocked **I Agree** on the checkbox
tick. Acceptance still writes `consent.value = '1'` (required by
`api/auth.php`), so the server-side privacy requirement is unchanged.

### B. Pages could not be scrolled (needed zoom-out to reach the bottom)
`assets/css/main.css` set `body { height: 100vh; overflow: hidden }` and made
`.main-content` an **inner scrollbox** under a `position: fixed` 70px header.
The wheel/touch scroll therefore never reached the document, the bottom of long
forms was clipped, and the footer was unreachable.

**Fix (new `assets/css/layout-fix.css`, loaded last):** `html/body` back to
`height:auto; overflow-y:auto`, wrapper `min-height:100vh` with `overflow:visible`,
`.main-content` flows in the document, footer no longer clipped, tall modals
scroll inside `calc(100vh - 40px)`.

### C. Missing Home button, ☰ on the wrong side
The top bar was `justify-content: flex-end` with the toggle *after* the other
icons. **Fix:** Home link added to every sidebar (Adopter / Shelter / Admin),
top bar left-aligned, ☰ pinned left (`margin-right:auto; order:-1`) for all
roles including admin.

---

## 2. Customize UI — rebuilt around exactly six controls

The old page (themes → window/modal → overlay/backdrop → radius → shadow →
grid density → 6 accessibility flags) was replaced. What remains:

| # | Control | Stored column | Validation (server) |
|---|---------|---------------|---------------------|
| 1 | Background colour | `bg_color` | `/^#[0-9a-fA-F]{6}$/` |
| 2 | Background image + fit | `bg_image_url`, `bg_image_fit` | strict local-upload path regex; enum `cover/contain/tile` |
| 3 | Radial blur on/off + strength | `bg_blur`, `bg_blur_strength` | 0–1 boolean, integer 0–40 |
| 4 | Font family | `font_family` | 8-value allowlist |
| 5 | Large text | `large_text` | 0/1 boolean |
| 6 | Header / sidebar colour + texture | `header_color`, `header_texture`, `sidebar_color`, `sidebar_texture` | hex regex; 5-value texture enum |

Deleted: theme picker, base font size, header scaling, accent colour slider,
modal background, overlay RGBA, border radius, shadow elevation, grid density,
contact-visibility flags, high-contrast, disable-animations.

**Background upload hardening** (`api/ui-background.php`):
`requireLogin()` → CSRF → 4 MB cap → `getimagesize()` magic bytes (never the
browser MIME or the extension) → min 320×240, max 6000×6000 → re-encode via GD
(strips EXIF/GPS and any embedded payload) → random `bin2hex(random_bytes(16))`
filename → `uploads/ui/` with a `.htaccess` that disables PHP → the previous
file is unlinked so storage cannot grow without bound.

---

## 3. Community module

**`pages/community.php`** (feed + composer + comments + reports) and
**`api/community.php`** + **`includes/community.php`** (server rules).

| Action | Adopter | Shelter / Veterinary | Admin |
|---|---|---|---|
| Read feed / comment | yes | yes | yes |
| Post `EXPERIENCE` / `INQUIRY` | yes | yes | yes |
| Post `ANNOUNCEMENT` | **no** | yes | yes |
| Post `SYSTEM` (platform update) | no | no | yes |
| Use `@all` mention | **no** | yes | yes |
| Delete own post/comment | yes | yes | yes |
| Delete someone else's | no | **no** | yes |
| Hide / pin / close report | no | no | yes |

Security properties, all enforced **server-side**:

- `requireLogin()` at the top of the page *and* the API — the request itself needs a session.
- CSRF token on every write (`pa_csrf_ok()` rejects an empty token, so the old
  `'' === ''` comparison can no longer pass).
- Role allowlist re-checked in `pa_community_create_post()`, never in the UI.
- Ownership re-checked in SQL before any delete (`WHERE user_id = <session user>`).
- `strip_tags()` + control-character strip + `mb_substr` length cap on input;
  `htmlspecialchars()` + `nl2br()` on output → stored-XSS safe.
- Anti-spam: 6 posts per rolling hour per user; `@all` fan-out capped at 500 notifications.
- **Privacy:** the feed SELECT joins `users` for `username` and `role` **only**.
  No email, phone or address is ever selected, so nothing can leak into a post
  or a JSON response.
- Reporting + admin moderation queue (`close_report`, `dismiss_report`,
  `hide_post`, `pin_post`, `hide_comment`).

---

## 4. Google AdSense slot

`includes/ad-slot.php`, included on **both dashboards** (adopter + shelter).

No publisher ID is invented. Set these in the host environment and the live
`<ins class="adsbygoogle">` unit renders:

```
ADSENSE_CLIENT_ID = ca-pub-XXXXXXXXXXXXXXXX
ADSENSE_SLOT_ID   = 0000000000
```

While unset it shows a neutral "Ad slot ready" placeholder, so the layout is
visible and nothing third-party loads. `.htaccess` now carries a **ready-to-use,
commented-out CSP** that already allowlists
`pagead2.googlesyndication.com`, `*.googlesyndication.com`, `*.doubleclick.net`
and `googleads.g.doubleclick.net` — uncomment it once the ad unit is live.
(`config/env.php` is now denied in `.htaccess` too.)

---

## 5. Deploy

```bash
# from your repo root
unzip -o PawAdopt_FixPack.zip -d .
git add -A
git commit -m "Fix privacy gate, page scrolling, nav; simplify Customize UI; add community + AdSense slot"
git push origin main
```

Then in Render → **pawadopt-web → Environment**, add (optional but recommended):

```
ADSENSE_CLIENT_ID   (only when you have an approved AdSense account)
ADSENSE_SLOT_ID
```

**Database — run these two files in MySQL Workbench, in this order:**

```
sql/ui_simplify.sql     -- adds the 11 appearance columns (idempotent, MySQL 8 + MariaDB safe)
sql/community.sql       -- creates community_posts / community_comments / community_reports
```

`ui_simplify.sql` uses a temporary stored procedure instead of
`ADD COLUMN IF NOT EXISTS`, so it runs on **MySQL 8** (Aiven) as well as
MariaDB — the old `sql/ui_customization.sql` throws Error 1064 on MySQL 8.

Without `community.sql` the Community page loads with a "tables not installed"
banner instead of a 500.

---

## 6. Files in this pack

### New (11)
```
assets/css/layout-fix.css          page-scroll + left ☰ fix (all roles)
assets/css/user-theme.css          applies each user's saved appearance
includes/ui-runtime.php            reads the user's row, prints safe CSS vars
includes/community.php             community rules, validation, ownership, moderation
includes/ad-slot.php               dashboard AdSense slot / placeholder
api/community.php                  community JSON API (posts, comments, reports, moderation)
api/ui-background.php              hardened background-image upload
pages/community.php                community feed + composer UI
sql/ui_simplify.sql                appearance columns (MySQL 8 + MariaDB safe)
sql/community.sql                  community tables
README.md                          this file
```

### Modified (7)
```
login.php                          privacy gate: removed undeclared paPolicyRead (dead I Agree)
includes/header.php                layout-fix + user-theme CSS, theme runtime, Home + Community nav, ☰ left, all roles
pages/customize.php                rebuilt: exactly the six requested controls
api/ui-settings.php                rewritten for the new columns, CSRF, hex/enum/range validation
pages/adopter/dashboard.php        AdSense slot include
pages/shelter/dashboard.php        AdSense slot include
.htaccess                          config/env.php denied, commented AdSense-ready CSP
```

### Untouched
Routes, queries, form field names, session logic, includes paths, the rest of
the UI, and every existing feature. `admin` pages keep their original look —
the user appearance layer only adds a background/header tint, it does not
replace their styles.

---

## 7. Verification performed (local, real logins, 100% zoom)

| Check | Result |
|---|---|
| Tick box → **I Agree** enabled | `disabled: true → false` |
| Real click on **I Agree** | gate closes, `privacyAcceptedField = "1"`, register inputs enable |
| JS console errors on login | none |
| Wheel scroll on dashboard | `scrollY 0 → 626` (previously stuck at 0) |
| Bottom / footer reachable without zoom | yes |
| Home link present | yes (Adopter, Shelter, Admin) |
| ☰ left of the mail icon | `x = 18` vs mail icon further right; also at 390px |
| Customize save | HTTP 200, values persist and apply (`--pa-bg` `#0B3D35`, `--pa-font` Georgia, `--pa-bg-blur` 22px, header `rgb(15,59,51)`, sidebar `rgb(247,251,250)`, `large-text`) |
| Invalid hex `red` | HTTP 400 |
| Save with no CSRF token | HTTP 403 |
| Adopter posts ANNOUNCEMENT | HTTP 400 refused |
| Adopter uses `@all` | HTTP 400 refused |
| Vet posts ANNOUNCEMENT `@all` | HTTP 200 |
| Vet deletes another user's post | HTTP 403 refused |
| Community post with no CSRF | HTTP 403 |
| Email/phone in feed | not present |
| Mobile 390px | no horizontal overflow, ☰ visible, drawer opens full-height |
| AdSense slot on dashboard | placeholder renders when env vars unset |
