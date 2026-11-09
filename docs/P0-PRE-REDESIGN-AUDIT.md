# DigiSangam pre-redesign audit — 9 November 2026

**Repository:** indigiti/digisangam  
**Audit base:** main @ 2fa7a1f  
**Remediation PR:** #1 feat/premium-ui-performance-foundation  
**Stack preserved:** Vue 3 / Vite 7 / Tailwind 4, PHP 8.3, JSON persistence, DigiOps package workflow.

## Decision

**Do not merge on build success alone.** The baseline CI initially passed, but it did not exercise the offline PWA after a cold reload or keyboard/multi-event behaviour. P0 remediation now introduces stronger regression checks and protects vulnerable paths. A staging deployment and real-device check remain release gates.

## P0 code findings and remediation

| ID | Severity | Finding | Resolution in PR #1 | Evidence/gate |
|---|---|---|---|---|
| A01 | Critical | Disabled users remained valid in previously authenticated PHP sessions | Reject inactive user in AuthService::user() | tests/auth_security.php |
| A02 | High | Initial admin setup and user creation used non-atomic read/write checks | Move uniqueness/existence check into locked JSON transactions | tests/auth_security.php + PHP CI |
| A03 | Critical | HMAC snapshot was not independently verifiable by offline browsers | v3 Ed25519 detached signature over original serialized JSON; 48h expiry | tests/smoke.php + offline-security.test.mjs |
| A04 | High | Offline attendee records and check-in queue were plaintext in IndexedDB | PBKDF2-derived AES-GCM encryption with operator passphrase, separate in-memory keys | offline-security.test.mjs |
| A05 | Critical | Fresh offline reload lost authenticated session and redirected scanner to login | Restrict offline router fallback to prepared OnGround devices, require passphrase unlock | browser PWA end-to-end |
| A06 | Critical | Initial service-worker installation could cache OnGround HTML but miss JS bundles | Emit build asset precache manifest and cache entry dependencies at SW installation | offline-build.test.mjs + browser PWA |
| A07 | High | Re-sync could orphan check-ins encrypted under a previous key | Transactionally rewrap pending items on snapshot renewal, abort safely if old key unavailable | must test full device renewal on staging |
| A08 | High | Full-page reload on event selection; rapid asynchronous responses may display wrong event KPIs | Reactive selection plus generation guards in Dashboard/Analytics/EventDetail | browser event-context checks |
| A09 | Medium | Main desktop-only navigation was unavailable on phones; overlay lacked focus handling | Responsive drawer, inert hidden nav, Escape, focus loop/restore and skip link | browser keyboard/mobile tests |
| A10 | High | No browser interaction regression testing or explicit offline release gate | Playwright Chromium P0 smoke workflow | GitHub CI |
| A11 | Medium | Status and active event labels depend on data loading | Keep true loading/error/empty states; verify all module routes on staging | manual exploratory check |
| A12 | Medium | Offline snapshot v2 migration strategy | Require new online preparation for v3; pending legacy queue eligible for authenticated re-encryption | migration test before production |

### Important limitations

- Offline device content is protected by the operator's passphrase, but a compromised/untrusted device can still expose information during an unlocked session. Treat event tablets as managed devices.
- Changing the credential server secret invalidates prior snapshot signatures. Re-sync devices after secret changes.
- Event-side payment and approval checks still run authoritatively during synchronization. Offline acceptance is provisional and must not be presented as final server admission.
- All snapshot signatures depend on browser WebCrypto Ed25519 support; unsupported browsers fail closed and need an approved alternative.
- Existing pending queue data must never be discarded automatically on logout or redeployment.
- PHP test suite does not replace real payment provider/hardware acceptance tests.
- No measured p75 Core Web Vitals or browser bundle size baseline is claimed yet.

## Visual / information architecture audit before P1/P2

| Area | Finding | Required action |
|---|---|---|
| Admin navigation | 20+ modules in grouped navigation; visual density and labels inconsistent | Retain grouped information architecture, introduce a unified component library and scalable navigation |
| Component layer | Core fields/buttons/tables/modals mostly duplicated through global CSS and page templates | Design tokens and shared field, select, button, toast, dialog, table, status components |
| Typography | Many labels around 9–11px | Raise legibility: 12px metadata, 14px standard controls, 16px body where possible |
| Admin analytics | KPI snapshots and static SVG trend, sparse cross-module insight | Prioritized live event overview; no fabricated revenue or graphs |
| Public discovery | Distinct visual vocabulary, no responsive image variants | Premium, fast discovery with reusable cards, filters and progressive media loading |
| Public checkout | Long form and multi-step transaction state, dynamic uploads | Preserve server-side validation, payment idempotency, clear pending/failed/confirmed states |
| Event branding | Public event palettes controlled per event | Keep organizer shell consistent; limit event custom themes to public/event surfaces |
| Accessibility | Full modal/focus, keyboard and contrast audit not demonstrated | WCAG 2.2 AA browser checks with screen reader spot tests |
| Speed | Router eagerly loaded all screens before first upgrade; public pages are SPA-rendered | Route splitting, responsive imagery, image dimensions, preload only critical resources |
| Search visibility | Public event pages are browser-rendered without per-event server metadata | P2 SEO/GEO: dynamic canonical, OG metadata and Event JSON-LD using trustworthy event data |

## Gate matrix

| Gate | Requirement | State |
|---|---|---|
| G1 | Vue production compilation | GitHub CI |
| G2 | PHP smoke/API E2E and Python intelligence tests | GitHub CI |
| G3 | Offline signature, expiry, tamper and passphrase tests | GitHub CI |
| G4 | Offline build manifest and release payload verification | GitHub CI |
| G5 | Real Chromium navigation, event switching, discovery, offline reload and reconciliation | New GitHub CI browser job |
| G6 | 320px/375px/768px/1024px/1440px visual, focus and screen reader audits | Pending real-device / staging |
| G7 | Legacy v2 queued entries and rekey with pending v3 entries | Pending browser migration exercise |
| G8 | Razorpay/payment state, duplicate registration and permissions | Pending staging provider simulation |
| G9 | DigiOps staging deployment and rollback | Pending deployment |
| G10 | Mobile LCP <=2.5s, INP <=200ms, CLS <=0.1 at p75 | Pending Lighthouse/RUM baselining |

## Execution order

1. **P0:** all CI checks green, resolve browser failures, stage on isolated DigiOps, test offline and storage migration; only then merge.
2. **P1:** premium admin design system, typographic scale, responsive shell, analytics, event detail, builders, tables and dialogs.
3. **P2:** premium event discovery, event landing, registration and checkout, event-specific branding, image loading and search metadata.
4. **P4/P8:** performance budgets and reliability gates continuously, rather than deferring to the end.
5. **P3/P5/P6/P7:** interaction refinement, multi-workspace usability, AI and conversion growth without duplicating existing modules.

**Release policy:** No fake demo records in live/public modules, no automatic merge of unverified PWA changes, and no framework or database migration as part of the visual release.
