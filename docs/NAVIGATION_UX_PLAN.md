# Navigation & Flow Enhancements — Implementation Plan

> **How to track progress:** Change `- [ ]` to `- [x]` when complete.  
> Phase-level checkboxes summarize the whole phase; task checkboxes track individual deliverables.  
> **Parent plan:** [IMPLEMENTATION_PLAN.md](./IMPLEMENTATION_PLAN.md) (core product features). This document covers **information architecture, wayfinding, and in-app flow** only.

---

## Progress overview

| Step | Phase | Status |
|------|-------|--------|
| 14 | [Phase 14 — Menu & entry-point cleanup](#phase-14--menu--entry-point-cleanup) | - [x] |
| 15 | [Phase 15 — Dashboard as hub](#phase-15--dashboard-as-hub) | - [x] |
| 16 | [Phase 16 — Rule wizard wayfinding](#phase-16--rule-wizard-wayfinding) | - [x] |
| 17 | [Phase 17 — Assets workspace consolidation](#phase-17--assets-workspace-consolidation) | - [x] |
| 18 | [Phase 18 — In-app routing (optional polish)](#phase-18--in-app-routing-optional-polish) | - [ ] |

**Recommended order:** 14 → 15 → 16 → 17 → 18 (18 is optional; do not block shipping 14–17).

---

## Goals

| Goal | Success signal |
|------|----------------|
| One obvious “start here” path | New users reach Assets Explorer from Dashboard without guessing |
| Fewer competing menu items | Sidebar reflects **workflows**, not every screen as a peer |
| Create Rule is contextual | Rules are created from assets/recommendations, not an empty menu page |
| Wizard feels connected | User always knows where they are and how to go back without losing scan context |
| Bulk + single coexist cleanly | No stale session state; mode is obvious from URL + UI |

---

## Current pain points (baseline)

- [x] Documented — 10 submenu items at same visual weight
- [x] Documented — Full `window.location` hops between Assets ↔ Create Rule ↔ Bulk wizard
- [x] Documented — **Create Rule** top-level menu vs create-from-row duplication
- [x] Documented — **Page Analyzer** overlaps **Assets Explorer** (both: URL + asset list)
- [x] Partially fixed — Bulk vs single rule session bleed (`bulk=1`, `clearBulkRuleDraft` on single create)
- [ ] **Create Rule** menu still exposes empty wizard with no asset context
- [ ] No breadcrumbs on Create / Bulk Rule screens
- [ ] Edit rule on Rules page vs create on separate `page=` slug (asymmetric back/save)

---

## Implementation order

| Order | Deliverable | Phase |
|-------|-------------|-------|
| - [x] Step 14 | Menu & entry-point cleanup | Phase 14 |
| - [x] Step 15 | Dashboard primary CTA + quick links | Phase 15 |
| - [x] Step 16 | Rule wizard breadcrumbs, cancel, post-save actions | Phase 16 |
| - [x] Step 17 | Merge/demote Page Analyzer; empty-state redirects | Phase 17 |
| - [ ] Step 18 | Client-side routing shell (optional) | Phase 18 |

---

## Phase 14 — Menu & entry-point cleanup

**Phase complete:** - [x]

### Goal

Reduce “where do I start?” confusion by reorganizing the WordPress admin menu into clear hubs and hiding contextual pages from the sidebar.

### Target menu structure

| Group | Items | Notes |
|-------|--------|------|
| **Overview** | Dashboard | Default landing |
| **Optimize** | Assets, Rules, Recommendations | Primary daily work |
| **Tools** | Dependency Graph, Scan History | Secondary analysis |
| **System** | Settings, Debug Logs | Config + support |

### Requirements

- [x] **Hide Create Rule from submenu** — keep slug `assetpilot-create` for deep links; remove visible menu entry (`includes/Admin/Menu.php`)
- [ ] **Reorder submenu** to match table above (Dashboard → Assets → Rules → …)
- [ ] **Register hidden pages** if needed (`parent_slug` null) so direct URLs still load and highlight correct parent (Assets or Rules)
- [ ] **Admin bar links** — point “create” actions to Assets or contextual create URL, not empty Create menu (`includes/Frontend/AdminBar.php`)
- [ ] **Localized `assetpilotAdmin` URLs** — no hardcoded broken links after menu change (`includes/Admin/Admin.php`)
- [ ] **Docs/readme** — one paragraph on primary path: Assets → rule (`readme.txt` or plugin docblock)

### Acceptance criteria

- Sidebar shows ≤ 8 visible items; Create Rule not listed
- Visiting `admin.php?page=assetpilot-create` without `handle`/`bulk` still works (redirect handled in Phase 17)
- Active menu highlight correct when on create/bulk URLs

### Files (expected)

- `includes/Admin/Menu.php`
- `includes/Admin/Admin.php`
- `includes/Frontend/AdminBar.php`
- `assets/src/admin/App.js` (titles only if needed)

---

## Phase 15 — Dashboard as hub

**Phase complete:** - [ ]

### Goal

Dashboard = status + orientation; **Assets Explorer** = workspace.

### Requirements

- [ ] **Primary CTA card** — “Scan & manage assets” button → Assets Explorer with homepage URL prefilled (`scan_url`)
- [ ] **Secondary actions** — View all rules, View recommendations, Recent scan history (last 1–3 from API or static link)
- [ ] **Copy** — Short line: “Create rules from Assets Explorer after scanning a page.”
- [ ] **Largest assets list** — each row links to Assets with `handle` + `type` query (or opens drawer if later unified routing)
- [ ] **Recent rules** — link to Rules list filtered or edit flow (existing behavior)

### Acceptance criteria

- From Dashboard, one click reaches Assets with scan URL set
- No duplicate “scan” UX that competes with Assets (Dashboard scan remains summary-only or defers to Assets CTA)

### Files (expected)

- `assets/src/admin/screens/Dashboard.js`
- `assets/src/admin/style.scss` (CTA card)
- Optional: `GET /dashboard` payload extension if “recent scans” needed server-side

---

## Phase 16 — Rule wizard wayfinding

**Phase complete:** - [ ]

### Goal

Create / Bulk Rule / Edit feel like one flow with clear back paths and scan context visible.

### Requirements

#### Breadcrumbs

- [ ] **Component** `WizardBreadcrumb` or inline nav: `Assets › [Bulk rule (N assets) | Create rule] › Step name`
- [ ] **Assets link** preserves `scan_url` when present
- [ ] **Bulk label** shows asset count; single shows handle badge

#### Cancel & back

- [ ] **Cancel** on steps 2–4 → confirm if dirty → return to Assets (with `scan_url`) or Rules list
- [ ] **Change selection** (bulk step 1) — keep current behavior; label optional: “Back to Assets Explorer”
- [ ] **Back** between steps — unchanged; ensure step 1 Back on bulk goes to Assets, not browser history

#### Context strip

- [ ] Below title or above stepper: **Scanned page URL** when `scan_url` / `scan_page_url` condition prefilled (read-only, link to open front-end URL)
- [ ] Bulk notice remains: “N assets will each get their own rule…”

#### Post-save

- [ ] After save: **notice** with actions — “View rules” (primary), “Create another rule on same page” (→ Assets + `scan_url`), optional “Edit first created rule” for bulk
- [ ] Avoid blind redirect only to Rules if user may want Assets (configurable or dual buttons)

#### Edit parity

- [ ] **Edit rule** from Rules list: same breadcrumb pattern `Rules › Edit: {handle}`
- [ ] Consider same `page=assetpilot-create&rule_id=` for create/edit URL unify (stretch; sub-task)

### Acceptance criteria

- User on step 3 always sees which page URL / assets the rule applies to
- Cancel never leaves orphan bulk session (`clearBulkRuleDraft` when abandoning bulk wizard)
- Post-save offers at least two sensible next steps

### Files (expected)

- `assets/src/admin/screens/CreateRule.js`
- `assets/src/admin/components/WizardBreadcrumb.js` (new)
- `assets/src/admin/bulkRuleSession.js`
- `assets/src/admin/style.scss`
- `assets/src/admin/App.js` (title + optional layout wrapper)

---

## Phase 17 — Assets workspace consolidation

**Phase complete:** - [ ]

### Goal

One place to scan and act on assets; demote or merge Page Analyzer; guard empty Create Rule entry.

### Requirements

#### Empty Create Rule guard

- [ ] If `page=assetpilot-create` and no `handle`, no `bulk=1`, no `rule_id` (edit): **redirect** to Assets with admin notice “Select an asset to create a rule”
- [ ] PHP `admin_init` redirect or React `useEffect` + `window.location` (prefer single approach)

#### Page Analyzer

- [ ] **Option A (recommended):** Remove submenu; add “Quick analyze” panel/tab on Assets Explorer (reuse `PageAnalyzer` logic or component)
- [ ] **Option B:** Keep submenu but Dashboard/Assets link to it as “Advanced analyzer” only
- [ ] Document choice in changelog row below

#### Assets Explorer enhancements

- [ ] **Sticky bulk bar** when selection > 0 (already present — verify mobile layout)
- [ ] **Deep link** `?handle=&type=` scrolls/highlights row and optionally opens drawer
- [ ] **Scan history** entry from Assets toolbar (link to Scan History or inline “Load scan #id”)

#### Rules list

- [ ] **Filter chips**: by asset handle, enabled/disabled, condition scope (URL / scan page / site-wide)
- [ ] **“Create rule”** button on Rules → Assets (not empty create page)

### Acceptance criteria

- Page Analyzer functionality reachable without a separate top-level menu item (Option A) OR clearly labeled secondary (Option B)
- No user lands on blank Create Rule from menu (menu hidden + redirect)

### Files (expected)

- `includes/Admin/Menu.php`
- `assets/src/admin/screens/CreateRule.js`
- `assets/src/admin/screens/AssetsExplorer.js`
- `assets/src/admin/screens/PageAnalyzer.js`
- `assets/src/admin/screens/RulesList.js`
- Optional: `includes/Admin/Redirects.php` (new)

---

## Phase 18 — In-app routing (optional polish)

**Phase complete:** - [ ]

### Goal

Reduce full page reloads; keep bulk/single state in React router state instead of only `sessionStorage`.

> **Scope:** Medium effort. Ship Phases 14–17 first. Phase 18 can be split into 18a (hash routing) and 18b (full history API).

### Requirements

#### 18a — Hash or query routing within one admin page

- [ ] Single shell page `assetpilot-app` OR keep `page=assetpilot-assets` as shell with hash: `#/create`, `#/bulk`, `#/rules`, `#/rules/edit/:id`
- [ ] **AssetsExplorer**, **CreateRule**, **RulesList** rendered by route map in `App.js`
- [ ] Submenu clicks set hash instead of full reload where possible
- [ ] **Bookmarkable** URLs: `admin.php?page=assetpilot-assets&assetpilot_view=create&handle=…`

#### 18b — State management

- [ ] Bulk selection in React context or URL-encoded asset keys (limit length) + sessionStorage fallback
- [ ] **beforeunload** warning on dirty wizard
- [ ] Sync `document.title` with view (Bulk Rule / Create Rule / Assets)

### Acceptance criteria

- Navigate Assets → Create rule → Back without full WordPress admin reload
- Bulk selection survives in-tab navigation; still works after refresh via sessionStorage fallback

### Files (expected)

- `assets/src/admin/App.js` (major)
- `assets/src/admin/router.js` (new)
- `includes/Admin/Menu.php` (fewer registered pages if consolidated)
- All screens using `window.location.href` → router navigate helper

### Risks

- WordPress admin menu “current” highlight may need custom JS
- Plugin conflicts with other admin SPAs — test with common plugins

---

## Cross-cutting checklist (apply during all phases)

- [ ] **i18n** — all new strings use `assetpilot` text domain
- [ ] **a11y** — breadcrumbs `nav` + `aria-current="step"`; focus management on route change
- [ ] **Capabilities** — `manage_options` unchanged on all URLs
- [ ] **Build** — `npm run build` after JS changes
- [ ] **No regression** — bulk `?bulk=1`, single `?handle=&type=`, edit on Rules, recommendations → create links

---

## Testing matrix

| Scenario | Expected |
|----------|----------|
| Dashboard → CTA | Assets with `scan_url` |
| Assets → row Create rule | Single wizard, step 2, no bulk UI |
| Assets → select 3 → Configure bulk rule | Bulk wizard, `?bulk=1`, 3 chips |
| Bulk → Change selection → deselect all → single Create rule | Single wizard only |
| Bulk → Change selection → add asset → Configure bulk | Updated count |
| Menu: no Create Rule | Hidden; direct URL redirects (Phase 17) |
| Save bulk rule | Notice + View rules / back to Assets |
| Edit rule from Rules | Breadcrumb Rules › Edit; save returns to list |
| Admin bar on front-end | Links to Assets or rules, not empty create |

---

## Changelog (plan updates)

| Date | Change |
|------|--------|
| 2026-05-21 | Initial navigation/UX plan (Phases 14–18) |
