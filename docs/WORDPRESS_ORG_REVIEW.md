# WordPress.org Plugin Review — Fix Tracker

**Plugin:** AssetPilot  
**Slug (current):** `assetpilot`  
**Review ID:** AUTOPREREVIEW — assetpilot/amrelarabi/28May26  
**Status:** Pended — fixes required before approval  
**Last updated:** 2026-05-29

---

## How to use this doc

1. Work through sections in **priority order** (P0 → P2).
2. Check off items as completed.
3. Re-zip with `scripts/zip-plugin.ps1` (update distignore if source-inclusion changes).
4. Upload at [Add your plugin](https://wordpress.org/plugins/developers/add/).
5. Reply to `plugins@wordpress.org` — brief, list slug reservation request if renaming.

---

## P0 — Must fix (blocks approval)

### 1. Plugin display name & slug (too generic)

**Chosen branding:**

| Field | Value |
|-------|--------|
| Display name | **AssetPilot - Granular control over frontend assets** |
| Slug | **`assetpilot`** |
| Text domain | **`assetpilot`** |
| Main file | **`assetpilot.php`** |

**Tasks:**

- [x] Choose name: **AssetPilot** / `assetpilot`.
- [x] Update headers, readme, menus, REST `assetpilot/v1`, JS/CSS prefixes, options prefix `assetpilot_`.
- [x] Main bootstrap: `assetpilot.php` (removed `asset-control.php`).
- [x] **Email reply:** Request slug reservation **`assetpilot`** explicitly.
- [x] Search plugins.wordpress.org for “AssetPilot” conflicts.
- [x] Rename plugin folder to `assetpilot` before org upload (zip must contain `assetpilot/assetpilot.php`).
- [x] Re-upload after other P0 fixes (source, ob_start, paths).

**Also check (reviewer):** username `amrelarabi`, display name, plugin URLs, icons/banners — no misleading trademark use.

---

### 2. Source code & build artifacts (Guidelines #1, #4)

**Issue:** `assets/build/admin.js` and `editor.js` are minified/bundled; automated review found no human-readable source in the package and no public repo link in readme.

**Current zip excludes (see `docs/distignore-list.txt`):**

- `assets/src/`
- `package.json`, `package-lock.json`
- `composer.json`, `composer.lock`

**Choose one approach (or combine A + B):**

| Approach | Action |
|----------|--------|
| **A — Include source in org zip** | Remove `assets/src/` (and optionally `package.json`) from distignore; document build steps in readme |
| **B — Public repository** | Publish source on GitHub/GitLab; add **Development** section in readme with URL + build instructions |
| **Recommended** | **A + B** — include `assets/src/` in zip **and** link public repo |

**Tasks:**

- [x] Add readme section **== Development ==** (build steps, `assets/src/` → `assets/build/`, third-party libs).
- [x] Public source repository: https://github.com/amrelarabi/assetspilot (push code before reviewers check).
- [x] Update `docs/distignore-list.txt` — ship `assets/src/` + `package.json`; still exclude `node_modules/`, lockfiles.
- [x] Document third-party JS (`@xyflow/react`, `react-select`) in readme.
- [x] Verify zip contains `assets/src/` before upload.

---

### 3. Output buffering (`ob_start` not safely paired)

**Issue:** Several places call `ob_start()` then drain **all** buffer levels with `while ( ob_get_level() > 0 ) ob_end_clean()`, which can break other plugins/themes and is not paired to the specific buffer.

| File | Line(s) | Notes |
|------|---------|--------|
| `includes/Assets/FrontendScanner.php` | 170, 299; also 60–93 | Nested drains |
| `includes/Assets/AssetCapture.php` | 30, 69 | `bootstrap_dependency_registry()` drains all in `finally` |
| `includes/API/RulesEndpoint.php` | 447, 866 | `while` drain in `finally` |

**Fix pattern:**

```php
$level = ob_get_level();
ob_start();
try {
    // ...
} finally {
    while ( ob_get_level() > $level ) {
        ob_end_clean();
    }
}
```

Or prefer `ob_get_clean()` when capturing a single buffer’s output.

**Tasks:**

- [x] Refactor all buffers via `OutputBuffer::start()` / `OutputBuffer::end_clean()` (explicit `ob_end_clean()` in helper).
- [x] `FrontendScanner` analyze mode: start on `template_redirect`, `end_clean` in `send_analyze_response`.
- [x] `AssetCapture.php` (2 sites), `RulesEndpoint.php` (2 sites).
- [ ] Test: asset scan, dashboard load, rule save/validation — no stray output, no PHP notices.

---

### 4. Filesystem paths via `ABSPATH` + URL (custom installs)

**Issue:** `AssetMetadataService::url_to_path()` assumes `site_url()` / `home_url()` paths map under `ABSPATH`.

| File | Line(s) |
|------|---------|
| `includes/Assets/AssetMetadataService.php` | 245–259 |

**Tasks:**

- [x] Map URLs via `content_url`, `wp_upload_dir`, `plugins_url`, `site_url` / `home_url` prefix mappings.
- [x] Relative paths resolved against URL path bases (subdirectory-friendly).
- [X] Manual test: estimate file size for plugin/theme/upload asset URLs.

---

## P1 — Should fix (mentioned in review)

### 5. Readme contributors mismatch

**Issue:** Contributors lists `amrabdelkarem` but plugin owner account is `amrelarabi`.

**Tasks:**

- [x] Change readme `Contributors:` to `amrelarabi`.
- [X] Confirm profile display name doesn’t imply false affiliation with trademarks.

---

### 6. Plugin URI vs Author URI

**Status:** Fixed — `Plugin URI` removed from `assetpilot.php`; `Author URI` kept.

- [x] Different or only one URI present.

---

### 7. Use WordPress path helpers consistently

**Reviewer doc:** https://developer.wordpress.org/plugins/plugin-basics/determining-plugin-and-content-directories/

**Tasks:**

- [x] Audit plugin for hardcoded `/wp-content/`, `ABSPATH .`, raw `$_SERVER` paths.
- [x] Prefer `plugin_dir_path()`, `plugin_dir_url()`, `plugins_url()`, `content_url()`, `wp_upload_dir()`.

**Fix:** Shared `includes/Helpers/UrlFilesystemResolver.php` (used by `AssetMetadataService`, `Registry`); `OriginDetector` uses `content_url()` path markers instead of hardcoded `/wp-content/...`. Remaining `ABSPATH .` is core `require_once` for `upgrade.php` / `file.php` only.

---

## P2 — Process / metadata (no code or minimal)

### 8. Guidelines self-check

- [ ] Re-read [Plugin Directory Guidelines](https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/).
- [ ] Run [Plugin Check](https://wordpress.org/plugins/plugin-check/) on release build.
- [ ] Full activation + smoke test on clean WP 6.2+ site.

### 9. Submission hygiene

- [x] Keep **Stable tag 1.0.0** until first .org approval (no version bump pre-approval).
- [ ] Push matching source to https://github.com/amrelarabi/assetspilot before upload.
- [ ] Run `powershell -ExecutionPolicy Bypass -File .\scripts\zip-plugin.ps1` (from `assetpilot/` folder).
- [ ] Upload while logged in as **amrelarabi**.

### 10. Reply email template (keep short)

```
Subject: Re: Review in Progress: AssetPilot

Hi,

I've uploaded an updated build addressing the review feedback:
- Renamed plugin to AssetPilot - Granular control over frontend assets; please reserve slug assetpilot.
- Included assets/src and package.json; Development section in readme with build steps.
- Fixed output buffering (level-safe cleanup via OutputBuffer helper).
- Fixed URL-to-path resolution for content/uploads/plugins directories.
- Updated readme Contributors to amrelarabi.

Tested on WordPress [x.x] with PHP [x.x].

Thanks,
Amr
```

---

## Fix order (recommended sprint)

| Step | Item | Est. effort |
|------|------|-------------|
| 1 | Decide new name + email slug reservation | 30 min |
| 2 | Source/readme + distignore for org zip | 1 hr |
| 3 | `ob_start` pairing (3 files) | 2–3 hr |
| 4 | `AssetMetadataService::url_to_path` | 1–2 hr |
| 5 | Contributors + changelog + version bump | 30 min |
| 6 | Full test + zip + upload + reply | 1 hr |

---

## Testing checklist (before resubmit)

- [ ] Activate plugin — no fatal errors.
- [ ] Dashboard, Assets, Rules, Settings load (no white screen).
- [ ] Scan homepage URL — assets returned.
- [ ] Create/edit/delete rule.
- [ ] Safe mode query arg works (`assetpilot-safe-mode=1`).
- [ ] Dependency graph page loads.
- [ ] Plugin Check: no critical failures.

---

## Notes

- **Do not** rename slug in code only — must request reservation in email.
- **Do not** send partial fixes; reviewers expect all items addressed.
- AI-suggested name is a hint only; human review may still request changes.
- Trademark: avoid “AssetPilot” alone if it implies affiliation with another product; branded prefix helps.

---

## Status log

| Date | Action |
|------|--------|
| 2026-05-29 | Tracker created from review email (28 May 2026) |
| 2026-05-29 | Renamed to AssetPilot / `assetpilot` / text domain `assetpilot` |
| 2026-05-29 | P0 code fixes: OutputBuffer, url_to_path, readme Development, distignore |
| 2026-05-29 | Stable tag kept at 1.0.0; GitHub repo linked in readme |
