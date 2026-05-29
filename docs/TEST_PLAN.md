# WP AssetPilot — Full Test Plan

> **How to track progress:** Change `- [ ]` to `- [x]` when a test passes.  
> Mark **FAIL** in the Notes column with a short description; fix and re-run.  
> **Related docs:** [IMPLEMENTATION_PLAN.md](./IMPLEMENTATION_PLAN.md) · [NAVIGATION_UX_PLAN.md](./NAVIGATION_UX_PLAN.md)

---

## Progress overview

| Suite | Area | Status |
|-------|------|--------|
| A | [Environment & smoke](#suite-a--environment--smoke) | - [ ] |
| B | [Admin navigation & UX](#suite-b--admin-navigation--ux) | - [ ] |
| C | [Dashboard](#suite-c--dashboard) | - [ ] |
| D | [Assets Explorer & scanning](#suite-d--assets-explorer--scanning) | - [ ] |
| E | [Rules CRUD & wizard](#suite-e--rules-crud--wizard) | - [ ] |
| F | [Conditions engine](#suite-f--conditions-engine) | - [ ] |
| G | [Validation, conflicts & impact](#suite-g--validation-conflicts--impact) | - [ ] |
| H | [Frontend runtime](#suite-h--frontend-runtime) | - [ ] |
| I | [Verification](#suite-i--verification) | - [ ] |
| J | [Scan history](#suite-j--scan-history) | - [ ] |
| K | [Recommendations](#suite-k--recommendations) | - [ ] |
| L | [Dependency graph](#suite-l--dependency-graph) | - [ ] |
| M | [Safe mode & recovery](#suite-m--safe-mode--recovery) | - [ ] |
| N | [Debug logs](#suite-n--debug-logs) | - [ ] |
| O | [Settings](#suite-o--settings) | - [ ] |
| P | [REST API](#suite-p--rest-api) | - [ ] |
| Q | [Permissions & security](#suite-q--permissions--security) | - [ ] |
| R | [Performance & cache](#suite-r--performance--cache) | - [ ] |
| S | [Compatibility & edge cases](#suite-s--compatibility--edge-cases) | - [ ] |

**Suggested order:** A → B → D → E → F → H → I → (remaining suites in any order).  
**Minimum release gate:** A, B, D, E, F, H, M, Q all pass.

---

## Test environment

### Prerequisites

| Item | Requirement |
|------|-------------|
| WordPress | 6.0+ (match `readme.txt`) |
| PHP | 8.1+ |
| User | Administrator (`manage_options`) |
| Build | `composer install` + `npm run build` completed |
| Theme | Active theme with real enqueues (e.g. Elementor + Hello) |
| Plugins | At least one plugin that registers scripts/styles |
| Front-end | Public homepage + one inner page (post, archive) |
| Browser | Chrome or Firefox (latest); optional Safari smoke |
| Tools | DevTools Network tab; optional Query Monitor |

### Test URLs (customize)

| Label | URL |
|-------|-----|
| Site home | `http://vancedmedia.local/` |
| Sample page | _fill in_ |
| Admin | `http://vancedmedia.local/wp-admin/` |
| REST base | `http://vancedmedia.local/wp-json/assetpilot/v1` |

### Before each full run

- [ ] Clear object/page cache if used (LiteSpeed, WP Rocket, etc.)
- [ ] Disable Safe Mode unless testing Suite M
- [ ] Enable debug logging in Settings if testing Suite N
- [ ] Note ACPRO plugin version and git commit hash: _______________

---

## Suite A — Environment & smoke

**Suite complete:** - [ ]

| ID | Test | Steps | Expected | Pass |
|----|------|-------|----------|------|
| A1 | Plugin activates | Plugins → activate WP AssetPilot | No fatal error; menu **AssetPilot** appears | - [ ] |
| A2 | Admin app loads | Open Dashboard | React UI loads; no console errors | - [ ] |
| A3 | REST reachable | Logged-in GET `/wp-json/assetpilot/v1/` or `/assets?scan_url=…` with `X-WP-Nonce` | 200 JSON (not 401/403) | - [ ] |
| A4 | Front-end unaffected (baseline) | Load homepage with no rules | Page renders normally | - [ ] |
| A5 | Build assets present | Check `assets/build/admin.js` exists | Admin styles/scripts load | - [ ] |

---

## Suite B — Admin navigation & UX

**Suite complete:** - [ ]

| ID | Test | Steps | Expected | Pass |
|----|------|-------|----------|------|
| B1 | Menu structure | Open AssetPilot submenu | Dashboard, Assets, Rules, Recommendations, Graph, Scan History, Settings, Logs — **no** Create Rule / Page Analyzer | - [ ] |
| B2 | Dashboard CTA | Dashboard → **Open Assets Explorer** | Lands on Assets with `scan_url` ≈ homepage | - [ ] |
| B3 | Empty create redirect | Visit `admin.php?page=assetpilot-create` (no params) | Redirect to Assets + notice to select an asset | - [ ] |
| B4 | Hidden create URL | Create rule from asset row | `assetpilot-create` loads; sidebar highlights **Assets** | - [ ] |
| B5 | Analyzer redirect | Visit old `page=assetpilot-analyzer` | Redirect to Assets **Quick analyze** tab | - [ ] |
| B6 | Admin bar (front) | View front page logged in → AssetPilot menu | Analyze → Assets analyze tab; manage assets → Assets with `scan_url` | - [ ] |
| B7 | Bulk vs single | Deselect all → row **Create rule** | Single-asset wizard, not bulk | - [ ] |
| B8 | Post-save actions | Save new rule | Success notice: View rules + Create another on same page | - [ ] |
| B9 | Breadcrumbs | Open bulk or single wizard | Assets › … › step visible | - [ ] |
| B10 | Cancel wizard | Step 3 → Cancel → confirm | Returns to Assets; bulk session cleared if bulk | - [ ] |

---

## Suite C — Dashboard

**Suite complete:** - [ ]

| ID | Test | Steps | Expected | Pass |
|----|------|-------|----------|------|
| C1 | Summary stats | Load Dashboard | Asset count, rules count, enabled rules shown | - [ ] |
| C2 | Homepage scan | Wait for scan panel | Largest assets populate or clear error + retry | - [ ] |
| C3 | Cached notice | Second load within cache TTL | Info notice if from cache | - [ ] |
| C4 | Links | Click View recommendations / Scan history | Correct screens open | - [ ] |
| C5 | Largest asset link | Click handle in table | Assets opens; row highlighted | - [ ] |

---

## Suite D — Assets Explorer & scanning

**Suite complete:** - [ ]

| ID | Test | Steps | Expected | Pass |
|----|------|-------|----------|------|
| D1 | Scan homepage | Enter home URL → **Scan** | Table fills with scripts/styles | - [ ] |
| D2 | Scan fresh | **Scan fresh** | Cache bypass; results update | - [ ] |
| D3 | Scan inner page | Scan a post URL | Different asset set than home | - [ ] |
| D4 | Filters | Type = Scripts; search handle | Table filters correctly | - [ ] |
| D5 | Sort | Click Handle / Size columns | Sort asc/desc works | - [ ] |
| D6 | Pagination | Site with >20 assets | Pages navigate | - [ ] |
| D7 | Drawer | Click row | Drawer opens with handle, src, deps, origin | - [ ] |
| D8 | Drawer → create | Drawer **Create rule** | Wizard step 2 with correct asset | - [ ] |
| D9 | Bulk select | Select 2+ → **Configure bulk rule** | Bulk wizard step 1 lists assets | - [ ] |
| D10 | Change selection | Bulk step 1 → Back to Assets Explorer | Checkboxes still selected | - [ ] |
| D11 | Clear selection | Clear selection | Bar hidden; session cleared | - [ ] |
| D12 | Quick analyze tab | **Quick analyze** tab | Analyzer UI; run analyze on URL | - [ ] |
| D13 | Load scan history | Open scan from Scan History | Assets loads saved scan (`scan_id`) | - [ ] |
| D14 | Highlight deep link | Open Assets with `handle` + `type` | Row highlighted on correct page | - [ ] |

---

## Suite E — Rules CRUD & wizard

**Suite complete:** - [ ]

### Single rule

| ID | Test | Steps | Expected | Pass |
|----|------|-------|----------|------|
| E1 | Create disable (script) | Script → disable → scanned page condition → save | Rule in list; enabled | - [ ] |
| E2 | Create defer | Script → defer → save | Rule saved | - [ ] |
| E3 | Create async | Script → async → save | Rule saved | - [ ] |
| E4 | Create disable (style) | Style → disable → save | Rule saved | - [ ] |
| E5 | Create preload | Style/script → preload (+ optional URL) → save | Rule saved | - [ ] |
| E6 | Create fetchpriority | Image rule → fetchpriority → save | Rule saved | - [ ] |
| E7 | Custom image/font | Step 1 custom URL → rule → save | Rule with custom handle/URL | - [ ] |
| E8 | Edit rule | Rules → Edit | Same wizard; fields prefilled | - [ ] |
| E9 | Update rule | Change action or conditions → save | List reflects change | - [ ] |
| E10 | Toggle enabled | Disable rule via toggle | `enabled` false; runtime off | - [ ] |
| E11 | Duplicate | Duplicate rule | Copy appears | - [ ] |
| E12 | Delete | Delete rule → confirm | Removed from list | - [ ] |
| E13 | Label & notes | Add label/notes on review step | Stored and visible in list | - [ ] |
| E14 | Priority | Set priority 5 vs 20 | Order in list / runtime order sensible | - [ ] |

### Bulk rules

| ID | Test | Steps | Expected | Pass |
|----|------|-------|----------|------|
| E15 | Bulk create | 3 scripts → disable → shared conditions → save | 3 separate rules created | - [ ] |
| E16 | Mixed bulk actions | Mix script + style selection | Only valid shared actions in dropdown | - [ ] |
| E17 | Bulk partial failure | Force validation failure on one (if possible) | Message shows created vs failed count | - [ ] |

### Rules list

| ID | Test | Steps | Expected | Pass |
|----|------|-------|----------|------|
| E18 | Search filter | Search by handle fragment | Matching rules only | - [ ] |
| E19 | Asset handle filter | Partial handle (e.g. `hello`) | Matching handles; debounced ~400ms | - [ ] |
| E20 | Condition filter | Filter “Scanned page URL” | Only scan-page rules | - [ ] |
| E21 | Bulk enable/disable/delete | Select rules → bulk action | Applied to all selected | - [ ] |
| E22 | Add from assets | **Add rule from assets** | Opens Assets, not empty create | - [ ] |

---

## Suite F — Conditions engine

**Suite complete:** - [ ]

Test each condition type with a **disable** rule on a known asset; verify on front-end (Suite H) or via applicable-rules behavior.

| ID | Condition | Setup | Expect rule applies when | Pass |
|----|-----------|-------|--------------------------|------|
| F1 | Entire site (global) | Global scope | All front-end URLs | - [ ] |
| F2 | Scanned page URL | Match exact scan URL | Only that URL | - [ ] |
| F3 | URL path | Path contains `/blog/` | Matching paths only | - [ ] |
| F4 | Query string | Query contains `utm_test=1` | URL with query only | - [ ] |
| F5 | Singular — all | All singular | Single posts/pages | - [ ] |
| F6 | Singular — post type | `post` only | Posts not pages | - [ ] |
| F7 | Singular — specific ID | One post ID | That post only | - [ ] |
| F8 | Archives | Archive scope | Category/tag archives | - [ ] |
| F9 | Post type archive | e.g. `post` archive | Correct archive | - [ ] |
| F10 | User role | Subscriber vs Administrator | Role match | - [ ] |
| F11 | Device | Mobile vs desktop (DevTools) | Device match | - [ ] |
| F12 | Logged-in status | Logged in / out | Auth match | - [ ] |
| F13 | Include + exclude rows | Two rows same group | OR within group logic | - [ ] |
| F14 | Multiple groups | Two groups if supported | Document actual behavior | - [ ] |

---

## Suite G — Validation, conflicts & impact

**Suite complete:** - [ ]

| ID | Test | Steps | Expected | Pass |
|----|------|-------|----------|------|
| G1 | Dependent asset warning | Disable script with dependents | Warning in wizard | - [ ] |
| G2 | Dangerous disable | Disable jQuery or critical script | Danger severity; confirm to save | - [ ] |
| G3 | Duplicate rule | Two rules same handle + overlapping scope | Conflict warning | - [ ] |
| G4 | Defer + async conflict | Same handle defer and async | Validation message | - [ ] |
| G5 | Impact preview | Review step | Impact preview loads (size/deps/risk) | - [ ] |
| G6 | Validate API | Save with `confirm_danger: false` on danger | Blocked until confirm | - [ ] |

---

## Suite H — Frontend runtime

**Suite complete:** - [ ]

Use DevTools → Network / Elements. Clear cache between tests.

| ID | Action | Verification | Pass |
|----|--------|--------------|------|
| H1 | **disable** script | Script not in DOM or not enqueued | - [ ] |
| H2 | **disable** style | Stylesheet absent or not applied | - [ ] |
| H3 | **defer** script | `<script defer` on target handle | - [ ] |
| H4 | **async** script | `<script async` on target handle | - [ ] |
| H5 | **preload** | `<link rel="preload"` in `<head>` | - [ ] |
| H6 | **fetchpriority** | `fetchpriority` on img or preload | - [ ] |
| H7 | Rule disabled in admin | Toggle off | No runtime effect | - [ ] |
| H8 | Wrong page | Scanned-page rule on other URL | Rule does not apply | - [ ] |
| H9 | Priority | Two rules same asset | Lower priority number wins per design | - [ ] |
| H10 | No admin bleed | Load `/wp-admin/` | Admin scripts not modified by plugin | - [ ] |

---

## Suite I — Verification

**Suite complete:** - [ ]

| ID | Test | Steps | Expected | Pass |
|----|------|-------|----------|------|
| I1 | Re-verify all | Rules list → Re-verify all | Badges update per rule | - [ ] |
| I2 | Pass badge | Rule applied on target page | Pass / success state | - [ ] |
| I3 | Fail badge | Rule for wrong page or broken asset | Fail with reason | - [ ] |
| I4 | After site change | Change theme/plugin → re-verify | Status updates | - [ ] |

---

## Suite J — Scan history

**Suite complete:** - [ ]

| ID | Test | Steps | Expected | Pass |
|----|------|-------|----------|------|
| J1 | History list | Scan History screen | Past scans listed with date/URL | - [ ] |
| J2 | Open in Assets | Open scan | Assets loads that snapshot | - [ ] |
| J3 | Retention | Settings max rows / days | Old scans rotated (if testable) | - [ ] |
| J4 | Delete scan | Delete entry if UI exists | Removed from list | - [ ] |

---

## Suite K — Recommendations

**Suite complete:** - [ ]

| ID | Test | Steps | Expected | Pass |
|----|------|-------|----------|------|
| K1 | Load recommendations | Recommendations screen | List with severity/category | - [ ] |
| K2 | Create rule link | Click through to create | Wizard with asset prefilled | - [ ] |
| K3 | Empty state | Fresh site / no scans | Sensible empty message | - [ ] |

---

## Suite L — Dependency graph

**Suite complete:** - [ ]

| ID | Test | Steps | Expected | Pass |
|----|------|-------|----------|------|
| L1 | Graph loads | Dependency Graph + scan URL | Nodes/edges render | - [ ] |
| L2 | Node click | Click node | Details or link to asset | - [ ] |
| L3 | Large graph | Scan busy page | No browser freeze; cache ok | - [ ] |
| L4 | Link to Assets | “View in Assets” (if present) | Correct explorer state | - [ ] |

---

## Suite M — Safe mode & recovery

**Suite complete:** - [ ]

| ID | Test | Steps | Expected | Pass |
|----|------|-------|----------|------|
| M1 | Enable Safe Mode | Settings or `?assetpilot-safe-mode=1` | Runtime off; admin works | - [ ] |
| M2 | Front-end with safe mode | Load homepage | All assets load (no ACPRO changes) | - [ ] |
| M3 | Disable Safe Mode | Settings toggle off | Rules apply again | - [ ] |
| M4 | Auto suspend | Simulate repeated fatals (if test env allows) | Runtime paused + notice | - [ ] |
| M5 | Resume runtime | Settings resume URL | Rules active again | - [ ] |
| M6 | Admin bar indicator | Safe mode on, view front | Admin bar shows safe mode link | - [ ] |

---

## Suite N — Debug logs

**Suite complete:** - [ ]

| ID | Test | Steps | Expected | Pass |
|----|------|-------|----------|------|
| N1 | Enable debug | Settings → debug on | — | - [ ] |
| N2 | Matched rule log | Trigger rule on front | Log entry for matched rule | - [ ] |
| N3 | Filters | Filter by asset, severity, date | List narrows | - [ ] |
| N4 | Clear logs | Delete / clear | Logs emptied | - [ ] |
| N5 | Rotation | Generate many entries | Cap at max rows / retention | - [ ] |

---

## Suite O — Settings

**Suite complete:** - [ ]

| ID | Test | Steps | Expected | Pass |
|----|------|-------|----------|------|
| O1 | Save settings | Change scan retention / debug | Persists after reload | - [ ] |
| O2 | Scan history settings | Max rows / days | Documented in UI | - [ ] |
| O3 | Safe mode controls | Toggle + recovery links | Work as labeled | - [ ] |

---

## Suite P — REST API

**Suite complete:** - [ ]

Use browser or `curl` with cookie auth + `X-WP-Nonce: {wp_rest}` from admin.

| ID | Method | Route | Expected | Pass |
|----|--------|-------|----------|------|
| P1 | GET | `/assets?scan_url={url}` | 200 + assets array | - [ ] |
| P2 | GET | `/assets?scan_id={id}` | 200 + historical assets | - [ ] |
| P3 | GET | `/rules` | 200 + pagination | - [ ] |
| P4 | POST | `/rules` | 201 create | - [ ] |
| P5 | PUT | `/rules/{id}` | 200 update | - [ ] |
| P6 | DELETE | `/rules/{id}` | 200/204 delete | - [ ] |
| P7 | POST | `/rules/bulk-create` | 200 + created count | - [ ] |
| P8 | POST | `/rules/validate` | 200 + validation object | - [ ] |
| P9 | POST | `/rules/{id}/duplicate` | 201 duplicate | - [ ] |
| P10 | POST | `/analyze` | 200 analyze payload | - [ ] |
| P11 | POST | `/verify` | 200 verification map | - [ ] |
| P12 | GET | `/scan-history` | 200 list | - [ ] |
| P13 | GET | `/recommendations` | 200 list | - [ ] |
| P14 | GET | `/dependency-graph?scan_url=` | 200 graph | - [ ] |
| P15 | GET | `/logs` | 200 logs | - [ ] |
| P16 | GET/POST | `/settings` | Read/update settings | - [ ] |
| P17 | GET | `/dashboard?summary_only=1` | 200 summary | - [ ] |

---

## Suite Q — Permissions & security

**Suite complete:** - [ ]

| ID | Test | Steps | Expected | Pass |
|----|------|-------|----------|------|
| Q1 | Subscriber admin | Log in as Subscriber → AssetPilot menu | No access / no menu | - [ ] |
| Q2 | REST unauthenticated | GET `/wp-json/assetpilot/v1/rules` no nonce | 401 | - [ ] |
| Q3 | REST editor | User without `manage_options` | 403 on mutating routes | - [ ] |
| Q4 | XSS in label | Save `<script>alert(1)</script>` in label | Escaped in UI | - [ ] |
| Q5 | External scan URL | Scan non-site URL if blocked | Error or sanitized | - [ ] |

---

## Suite R — Performance & cache

**Suite complete:** - [ ]

| ID | Test | Steps | Expected | Pass |
|----|------|-------|----------|------|
| R1 | Scan cache | Scan same URL twice | Second shows cached (faster) | - [ ] |
| R2 | Scan fresh | Scan fresh after cache | New results | - [ ] |
| R3 | Many rules | 50+ enabled rules | Front-end TTFB acceptable | - [ ] |
| R4 | Graph cache | Reload graph same URL | Faster second load | - [ ] |
| R5 | DB queries | Query Monitor on front request | No N+1 explosion | - [ ] |

---

## Suite S — Compatibility & edge cases

**Suite complete:** - [ ]

| ID | Test | Steps | Expected | Pass |
|----|------|-------|----------|------|
| S1 | Elementor page | Scan Elementor-built URL | Elementor handles appear | - [ ] |
| S2 | WooCommerce | Shop/cart if Woo active | Woo conditions work (F suite) | - [ ] |
| S3 | Multilingual | WPML/Polylang URL if used | Scanned page URL still matches | - [ ] |
| S4 | Page cache | Full-page cache on + rule change | Cache purge or rule visible after purge | - [ ] |
| S5 | Object cache | Redis/Memcached on | No stale rules transients | - [ ] |
| S6 | Missing handle | Rule for dequeued handle | Verification fail / graceful | - [ ] |
| S7 | Session bulk refresh | Bulk wizard mid-flow → F5 | bulk=1 + draft or sensible error | - [ ] |
| S8 | Block editor | Editor loads | `editor.js` bundle no errors | - [ ] |

---

## Test run log

| Run # | Date | Tester | Environment | Suites passed | Notes |
|-------|------|--------|-------------|---------------|-------|
| 1 | | | Local | /19 | |
| 2 | | | Staging | /19 | |

---

## Defect template

When logging failures, copy per issue:

```
ID: (e.g. E5)
Summary:
Steps:
Expected:
Actual:
Browser/PHP/WP versions:
Screenshot/log:
```

---

## Future automation (optional)

| Layer | Tool | Scope |
|-------|------|--------|
| PHP unit | PHPUnit | Conditions, RuleEngine, validators |
| PHP integration | WP test suite | REST endpoints, repository |
| E2E | Playwright / Cypress | Wizard flows B, D, E |
| CI | GitHub Actions | Lint + unit on PR |

Not required for manual QA; track separately if added.

---

## Changelog (plan updates)

| Date | Change |
|------|--------|
| 2026-05-21 | Initial full plugin test plan (Suites A–S) |
