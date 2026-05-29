# WP AssetPilot — Implementation Plan

> **How to track progress:** Change `- [ ]` to `- [x]` when complete.  
> Phase-level checkboxes summarize the whole phase; task checkboxes track individual deliverables.

---

## Progress overview

| Step | Phase | Status |
|------|-------|--------|
| 1 | [Phase 1 — Asset Details Drawer](#phase-1--asset-details-drawer) | - [x] |
| 2 | [Phase 2 — Conflict Detection System](#phase-2--conflict-detection-system) | - [x] |
| 3 | [Phase 11 — Runtime Execution Refactor](#phase-11--runtime-execution-refactor) | - [x] |
| 4 | [Phase 3 — Runtime Verification Engine](#phase-3--runtime-verification-engine) | - [x] |
| 5 | [Phase 4 — Rule Impact Preview](#phase-4--rule-impact-preview) | - [x] |
| 6 | [Phase 5 — Scan History System](#phase-5--scan-history-system) | - [x] |
| 7 | [Phase 6 — Rules Management Improvements](#phase-6--rules-management-improvements) | - [x] |
| 8 | [Phase 10 — Advanced Conditions Engine](#phase-10--advanced-conditions-engine) | - [x] |
| 9 | [Phase 9 — Safe Mode + Recovery](#phase-9--safe-mode--recovery) | - [x] |
| 10 | [Phase 12 — Logging + Debugging System](#phase-12--logging--debugging-system) | - [x] |
| 11 | [Phase 7 — Dependency Graph Visualization](#phase-7--dependency-graph-visualization) | - [x] |
| 12 | [Phase 8 — Asset Recommendation Engine](#phase-8--asset-recommendation-engine) | - [x] |
| 13 | [Phase 13 — Performance Optimization](#phase-13--performance-optimization) | - [x] |
| 14–17 | [Navigation & Flow Enhancements](./NAVIGATION_UX_PLAN.md) (14–17 done) | - [x] |
| 18 | [In-app routing](./NAVIGATION_UX_PLAN.md#phase-18--in-app-routing-optional-polish) (optional) | - [ ] |

**Recommended implementation order:** Steps 1 → 13 above (phases reordered for dependencies; see [Implementation order](#implementation-order)).  
**UX / navigation (Steps 14–18):** See [NAVIGATION_UX_PLAN.md](./NAVIGATION_UX_PLAN.md) — implement after core features or in parallel starting with Phase 14.

**QA:** Full manual test checklist — [TEST_PLAN.md](./TEST_PLAN.md) (Suites A–S).

---

## Global constraints

- [ ] **No breaking changes** — extend architecture cleanly; do not rewrite existing systems unnecessarily
- [ ] Preserve current rule flow, analyzer, and existing UI structure
- [ ] Business logic in PHP services — **not** inside React components (unless UI-only)
- [ ] Do not silently ignore validation conflicts or verification failures

---

## Asset type scope

Product stance for which assets WP AssetPilot targets. Avoid becoming a general media manager or full-page HTML rewriter; stay a **performance rule engine** with reliable identification (handle or URL), runtime hooks, and verification.

### Core promise (current focus)

- [x] **Scripts & styles** — first-class via `wp_enqueue_*`: disable, defer, async, preload, fetchpriority, dependency graph, bulk rules, conflict detection, verification.

### Extended promise (partial today — finish before expanding further)

- [x] **Fonts** — URL / `@font-face` discovery from scans (partial: HTML parse on scan); preload (`as=font`, crossorigin); optional disable/block by URL.
- [x] **Images** (`<img>`, attachment heroes) — discovery in Assets Explorer on scan (partial); rules by URL or attachment ID; preload / fetchpriority; verification on scanned URLs (ongoing).

Actions by type today (`RulesEndpoint::is_action_allowed_for_type`):

| Action | script | style | image | font |
|--------|--------|-------|-------|------|
| disable | yes | yes | yes | yes |
| defer | yes | — | — | — |
| async | yes | — | — | — |
| preload | yes | yes | yes | yes |
| fetchpriority | yes | — | yes | — |

Custom image/font URLs work at **runtime** (preload/disable by URL) but are **hard to see and manage in the UI** — see [Custom URL assets — UX & discovery](#custom-url-assets--ux--discovery) below.

### Custom URL assets — UX & discovery

**Problem:** Users can add rules for image/font (and other URL-based) assets via a **collapsed** panel on Create Rule step 1 (“Custom image or font URL”). Those rules are easy to miss afterward: the Rules list often shows a long URL as `asset_handle`, Assets Explorer does not list custom or scan-derived URLs as rows, and there is no dedicated filter or badge. `Registry::merge_custom_assets()` exists but is **not wired** into the scan/explorer pipeline yet.

**Current behavior (reference):**

| Surface | Custom URL visibility |
|---------|------------------------|
| Create Rule wizard | Hidden behind accordion on step 1 only (non-bulk) |
| Assets Explorer | Not shown |
| Rules list | May show raw URL as handle; no `action_config.href` subtitle |
| Edit rule / review | URL not highlighted; bulk/custom URL rules look like single-handle rules |
| Scan results | Images/fonts in HTML not merged as first-class explorer rows |
| Asset details drawer | Built for enqueue handles, not URL-only assets |

**Deliverables:**

- [x] **Discoverability** — Prominent entry for “Add by URL” (button on Assets workspace + expanded panel on Create Rule).
- [x] **Rules list** — For URL-based rules: truncated URL, `custom URL` badge, link to `action_config.href` / `src`.
- [x] **Edit / review** — Show target URL on create/review; prefill custom fields when editing URL rules.
- [x] **Assets Explorer** — Images/fonts from scan with `origin: html`; type filters image / font; origin filter “From HTML”.
- [x] **Scan pipeline** — `HtmlAssetParser::parse_media_assets()` merged in `FrontendScanner` after enqueue capture.
- [x] **Asset details drawer** — HTML media note; dependencies hidden for image/font from HTML.
- [x] **Docs / empty states** — Scan help text mentions images/fonts and Add by URL.

**Depends on:** Extended promise (fonts/images) above; can ship list/badge improvements before full scan merge.

### Tier 2 — selective / later

- [ ] **CSS background images** (and rare background-video) — only after a unified **URL asset** model (scan → rule → verify in HTML/CSS); not via enqueue handles.
- [ ] **`<video>` / poster** — narrow scope: e.g. `preload="none"`, disable by URL, conditional off mobile; no full video CMS features.

### Non-goals (explicit)

- [ ] Arbitrary “other” assets without a stable handle or URL.
- [ ] Replacing image optimizers, CDN plugins, or media library tooling.
- [ ] Third-party embed facades (YouTube, etc.) as in-scope asset types.

### Recommended implementation order (media track)

1. **Custom URL UX** — Rules list + edit/review show URL clearly; promote “Add by URL” in wizard/explorer (quick win).
2. Document in UI: scripts/styles are primary; image/font via URL or attachment where available.
3. Scan + list **images & fonts** (and other parsed URLs) in Assets Explorer; wire scan merge for custom rows.
4. Harden verification for URL-based rules on scan URLs (not only homepage for global rules).
5. **Background URLs** only after (2)–(4) are stable.
6. Video rules only if user demand is clear and scoped to URL + 1–2 actions.

---

## Implementation order

| Order | Deliverable | Phase |
|-------|-------------|-------|
| - [x] Step 1 | Asset Details Drawer | Phase 1 |
| - [x] Step 2 | Conflict Detection System | Phase 2 |
| - [x] Step 3 | Runtime Execution Refactor | Phase 11 |
| - [x] Step 4 | Runtime Verification Engine | Phase 3 |
| - [x] Step 5 | Rule Impact Preview | Phase 4 |
| - [x] Step 6 | Scan History System | Phase 5 |
| - [x] Step 7 | Rules Management Improvements | Phase 6 |
| - [x] Step 8 | Advanced Conditions Engine | Phase 10 |
| - [x] Step 9 | Safe Mode + Recovery | Phase 9 |
| - [x] Step 10 | Logging + Debugging | Phase 12 |
| - [x] Step 11 | Dependency Graph Visualization | Phase 7 |
| - [x] Step 12 | Recommendation Engine | Phase 8 |
| - [x] Step 13 | Performance Optimization Pass | Phase 13 |
| - [x] Step 14 | Menu & entry-point cleanup | [NAVIGATION_UX_PLAN.md](./NAVIGATION_UX_PLAN.md) Phase 14 |
| - [x] Step 15 | Dashboard as hub | NAVIGATION Phase 15 |
| - [x] Step 16 | Rule wizard wayfinding | NAVIGATION Phase 16 |
| - [x] Step 17 | Assets workspace consolidation | NAVIGATION Phase 17 |
| - [ ] Step 18 | In-app routing (optional) | NAVIGATION Phase 18 |
| - [x] Step 19 | Custom URL UX + explorer visibility (v1) | [Asset type scope — Custom URL assets](#custom-url-assets--ux--discovery) |

---

## Phase 1 — Asset Details Drawer

**Phase complete:** - [x]

### Goal

Allow users to inspect an asset deeply without leaving the Assets Explorer. This becomes the main asset inspection experience.

### Requirements

- [x] **Clickable asset rows** — clicking handle opens inspection UI
- [x] **Slide-over drawer** (preferred over modal)

### Drawer sections

#### 1. Basic asset info

- [x] Handle
- [x] Type
- [x] Source
- [x] Origin type (Plugin / Theme / Core / External)
- [x] Asset URL
- [x] Detected version
- [x] File size
- [x] Media attribute (for styles)

#### 2. Dependency information

- [x] **Dependencies** — assets this asset requires
- [x] **Dependents** — assets depending on this asset
- [x] Recursive tree display (example: `jquery → elementor-common → elementor-app-loader`)
- [x] Loop protection — do not traverse recursively forever

#### 3. Runtime metadata

- [x] Currently loaded on scanned page
- [x] Enqueue order
- [x] Preload status
- [x] Defer status
- [x] Async status
- [x] Current active rules count

#### 4. Page usage

- [x] How many scanned pages use this asset
- [x] Recently detected pages
- [x] Use **scan history only** — no site-wide crawling yet (transient index until Phase 5 table)

#### 5. Rule shortcuts

- [x] Create Rule
- [x] View Rules
- [x] Duplicate existing rule

### Architecture

- [x] `DependencyResolver` service
- [x] `AssetUsageService` service
- [x] `AssetMetadataService` service
- [x] REST endpoint(s) for drawer data
- [x] React drawer component (presentation only)

---

## Phase 2 — Conflict Detection System

**Phase complete:** - [x]

### Goal

Prevent users from breaking frontend functionality accidentally.

### Requirements

- [x] Run validation pipeline **before saving** a rule

### Validation types

#### 1. Dependency conflict

- [x] Warn: disabling parent dependency
- [x] Warn: deferring dependency but not child
- [x] Warn: async + dependency mismatch

#### 2. Core asset protection

- [x] Warn (do **not** block) when affecting: `jquery`, `wp-hooks`, `react`, `wp-element`

#### 3. Duplicate action detection

- [x] Warn: preload already exists
- [x] Warn: duplicate rule exists
- [x] Warn: conflicting fetchpriority exists

#### 4. Dangerous combination detection

- [x] Warn: disable + preload same asset (`danger` — confirm to save)
- [x] Warn: async + defer same asset (`danger` — confirm to save)

### UX

- [x] Warnings on **review step** (Create Rule step 4)
- [x] Warnings on action step (step 2) via live validation
- [x] Warnings **before save** — `danger` blocks until `confirm_danger` or browser confirm
- [x] Severity levels: `info` | `warning` | `danger`
- [x] Do not silently ignore conflicts

### Architecture

- [x] `RuleValidatorInterface`
- [x] Individual validator classes (one per validation type)
- [x] `ValidationPipeline`
- [x] Wire pipeline into rules REST `POST`/`PUT`, `POST /rules/validate`, and Create Rule UI

---

## Phase 3 — Runtime Verification Engine

**Phase complete:** - [x]

### Goal

Verify rules actually affect frontend output correctly.

### Requirements

After applying rules, verify:

- [x] Asset removed (when disabled)
- [x] Defer attribute added
- [x] Async attribute added
- [x] Preload rendered
- [x] Fetchpriority rendered

### Verification process

- [x] Use analyzed frontend HTML
- [x] Compare **expected** vs **actual** output
- [x] Mark rule/application as failed when mismatch (e.g. expected `elementor-common` removed, still rendered)

### UI

- [x] Badges: **Verified** | **Partially Applied** | **Failed** (+ Skipped / Unavailable)
- [x] Show on Rules page
- [x] Show in Asset drawer

### Architecture

- [x] `RuntimeVerificationService`
- [x] `HTMLVerificationParser`
- [x] No verification logic inside React (`VerificationBadge` display only)
- [x] REST `POST /verify`

---

## Phase 4 — Rule Impact Preview

**Phase complete:** - [x]

### Goal

Show estimated impact before saving rules.

### Requirements

During review step, estimate (using **scan history only** — no site crawl):

- [x] Affected URLs
- [x] Affected post types
- [x] Affected archives

Example copy:

```text
This rule may affect:
- Homepage
- 14 Pages
- 8 Products
```

### Risk level

- [x] Display: Low Risk | Medium Risk | High Risk
- [x] Risk from: dependency count, asset type, core asset detection

### Architecture

- [x] `RuleImpactEstimator`
- [x] `RiskAssessmentService`
- [x] `ImpactPreviewService` + `ScanHistoryIndex` (transient scan registry)
- [x] `impact_preview` on `POST /rules/validate`; `RuleImpactPreview` on review step

---

## Phase 5 — Scan History System

**Phase complete:** - [x]

### Goal

Persist scan results historically for analytics and page-usage features.

### Database

- [x] Table: `wp_assetpilot_scan_history`
- [x] Store: scanned URL, timestamp, detected assets (JSON), asset counts, total JS size, total CSS size

### Features

- [x] Re-open previous scans
- [x] Compare scans
- [x] View scan timeline

### UI — Scan History screen

- [x] New admin screen: **Scan History**
- [x] Columns: URL, assets count, scripts count, styles count, total size, scan date

### Architecture

- [x] `ScanHistoryRepository`
- [x] `ScanSnapshotService`
- [x] Hook into Assets Explorer `GET /assets` to persist snapshots; `scan_id` loads saved snapshot
- [x] Automatic rotation — max 200 rows (trim to ~90%), 90-day retention (`ScanHistoryRepository::rotate()`)

---

## Phase 6 — Rules Management Improvements

**Phase complete:** - [x]

### Goal

Make rules scalable for real-world usage.

### Features

- [x] Bulk enable
- [x] Bulk disable
- [x] Bulk delete
- [x] Duplicate rule (copy label suffix, disabled by default)
- [x] Filter rules
- [x] Search rules

### Filtering

- [x] By asset (search / handle in search)
- [x] By action
- [x] By condition type
- [x] By enabled state

### Rule metadata

- [x] Optional human-readable **labels** (e.g. "Homepage Hero Optimization")
- [x] Optional internal **notes** field

### Architecture

- [x] Rules list API: pagination, sorting, filtering (`RulesListQuery`, `GET /rules`)
- [x] Paginated queries — not all rules loaded at once on list screen
- [x] DB schema migration for `label` / `notes` (v1.3.0)
- [x] `POST /rules/bulk` for enable / disable / delete

---

## Phase 7 — Dependency Graph Visualization

**Phase complete:** - [x]

### Goal

Visualize dependency chains interactively.

### Requirements

- [x] Interactive graph (`@xyflow/react`)
- [x] Nodes: assets
- [x] Edges: dependencies
- [x] Zoom
- [x] Expand / collapse (click node to hide/show dependents)
- [x] Highlight critical dependencies
- [x] Show rule impacts on nodes

### Architecture

- [x] `GET /dependency-graph` + `DependencyGraphBuilder` (PHP)
- [x] **Dependency Graph** admin screen

---

## Phase 8 — Asset Recommendation Engine

**Phase complete:** - [x]

### Goal

Turn the plugin into an intelligent optimizer (major product feature).

### Recommendation types (initial)

#### 1. High load, low usage

- [x] Example: Contact Form 7 CSS on 200 pages, used on 1 page

#### 2. Large asset recommendations

- [x] Example: `elementor-common` 522KB → suggest defer

#### 3. Duplicate library detection

- [x] Multiple sliders, multiple icon libraries, etc.

#### 4. Render-blocking detection

- [x] Blocking CSS, non-deferred scripts

### UX

- [x] Show: reason, suggested action, confidence level
- [x] One-click rule creation
- [x] Recommendations must **never** auto-apply

### Architecture

- [x] `RecommendationProviderInterface`
- [x] `RecommendationEngine`
- [x] Recommendation DTOs (associative arrays in API response)
- [x] Each recommendation type in isolated provider class
- [x] `GET /recommendations` + **Recommendations** admin screen

---

## Phase 9 — Safe Mode + Recovery

**Phase complete:** - [x]

### Goal

Prevent site lockouts. **Critical before public release.**

### Requirements

- [x] Recovery mode URL: `/wp-admin/?assetpilot-safe-mode=1` (or equivalent)
- [x] Safe mode disables **runtime modifications only** — not the whole plugin
- [x] Auto-detect fatal frontend failures
- [x] If frontend crashes repeatedly → temporarily disable runtime rules

### Architecture

- [x] `SafeModeManager` (`SafeMode` facade for back-compat)
- [x] `RuntimeHealthMonitor` — shutdown hook, 3 fatals / 5 min → 30 min auto-suspend

### Notes (existing work)

- [x] Manual safe mode: cookie + `ASSETPILOT_SAFE_MODE` constant
- [x] Auto-suspend: option `assetpilot_runtime_suspend`, resume via `?assetpilot-resume-runtime=1`
- [x] Admin notices, Settings UI, admin bar status when runtime off

---

## Phase 10 — Advanced Conditions Engine

**Phase complete:** - [x]

### Goal

Extend conditions without redesigning architecture.

### New conditions

| Area | Values |
|------|--------|
| Device | mobile, desktop |
| User state | logged in, logged out |
| User roles | administrator, editor, customer, … |
| URL matching | contains, starts with (regex later) |
| Query string | e.g. `?preview=true` |

### Architecture

- [x] `ConditionHandlerInterface`
- [x] Individual condition handler classes (`includes/Rules/Conditions/`)
- [x] Refactor `ConditionEvaluator` via `ConditionHandlerRegistry` (no giant switch)
- [x] UI rows: URL match mode, query string, user role

### Notes (existing work)

- [x] Device, auth, URL (contains + starts with), query string, roles wired in builder + runtime
- [x] Rules list filter includes device, auth, query, role scopes

---

## Phase 11 — Runtime Execution Refactor

**Phase complete:** - [x]

### Goal

Stabilize runtime architecture before the plugin grows.

### Action handlers (isolated)

- [x] `DisableAssetAction`
- [x] `PreloadAssetAction`
- [x] `DeferAssetAction`
- [x] `AsyncAssetAction`
- [x] `FetchPriorityAction`

### Runtime pipeline

Flow:

1. - [x] Collect matched rules
2. - [x] Validate rules (enabled, handle, allowed action/type)
3. - [x] Sort by priority
4. - [x] Execute action handlers
5. - [ ] Verify output (integrate Phase 3 — `assetpilot_after_runtime_pipeline` hook stub)
6. - [x] Log results (`Logger` + pipeline summary; Phase 12 logs UI later)

### Rules

- [x] Actions must **not** know about conditions (conditions evaluated earlier)
- [x] Replace coupled logic in current `RuntimeEngine`

---

## Phase 12 — Logging + Debugging System

**Phase complete:** - [x]

### Goal

Improve trust and debugging.

### Requirements

- [x] Optional debug mode (Settings toggle)
- [x] Log: matched rules, skipped rules, failed verification, dependency conflicts, runtime errors
- [x] Logs screen with filters: date, asset, rule, severity, type, search
- [x] Automatic log rotation — max 5000 rows, 14-day retention

### Architecture

- [x] `wp_assetpilot_logs` table + `LogRepository`
- [x] `Logger` persists on shutdown; validation + verification integration
- [x] `GET/DELETE /logs` + **Debug Logs** admin screen

---

## Phase 13 — Performance Optimization

**Phase complete:** - [x]

### Goal

Ensure the plugin remains lightweight at scale.

### Cache

- [x] Rule evaluations — enabled rules transient + per-request applicable rules (`RuleEngine`)
- [x] Dependency trees — versioned transient graph cache (`DependencyGraphBuilder`)
- [x] Scan results — URL transients with filterable TTL (`FrontendScanner`, `assetpilot_scan_cache_ttl`)
- [x] Condition matches — per-request memoization keyed by conditions + request fingerprint (`ConditionEvaluator`)

### Avoid

- [x] Repeated DB queries per request — `all_cached()`, `find_for_asset()`, request memoization
- [x] Runtime recursion without bounds — `MAX_DEPTH` / `MAX_UPSTREAM_ITERATIONS` on dependency walks
- [x] Rebuilding dependency graphs on every request — transient cache + cache version bust on rule changes

### Architecture

- [x] Expanded `Cache` helper (transients, version busting, request memoization, TTL filters)
- [x] `ConditionContext` fingerprint for condition cache keys
- [x] Cached dependency analysis API + recommendations collection

---

## Final goal

Evolve from **asset toggles plugin** → **frontend asset orchestration platform**.

### Focus areas

- [ ] Reliability
- [ ] Observability
- [ ] Dependency awareness
- [ ] Intelligent optimization
- [ ] Scalability
- [ ] Developer experience

---

## Changelog (plan updates)

| Date | Change |
|------|--------|
| | Initial formatted plan with progress checkboxes |
| 2026-05-21 | Linked [NAVIGATION_UX_PLAN.md](./NAVIGATION_UX_PLAN.md) (Steps 14–18) |
| 2026-05-21 | Added [TEST_PLAN.md](./TEST_PLAN.md) full QA checklist |
| 2026-05-21 | Added **Asset type scope** (scripts/styles core; fonts/images tier 1; backgrounds/video later) |
| 2026-05-21 | Added **Custom URL assets — UX & discovery** gap + Step 19 (rules list, explorer, scan merge) |
| 2026-05-21 | Implemented Asset type scope v1: HTML media scan merge, URL UX, explorer filters |
