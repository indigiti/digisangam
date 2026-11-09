# P1/P2 Premium EventOS — Implementation and release audit

**Date:** 2026-11-09  
**Pull requests:** #1 (P0 foundations) → #2 (P1/P2 UI and public experience)  
**Staging rule:** PR #2 is a stacked draft. Never merge or deploy to production before PR #1's staging/device gates pass.

## Architecture preserved

- Vue 3, Vite, Tailwind CSS 4, Pinia; no replacement framework.
- PHP 8.3 modular backend, existing JSON file repositories and REST APIs.
- DigiOps `digiops-release` packaging and Cloudways public/private directory split.
- Offline OnGround PWA precache, signed QR and transactional queue.
- No mock data injected into production screens.

## P1 implemented in the stacked branch

| Component | Scope |
|---|---|
| Design foundations | Harmonized indigo/navy/porcelain surfaces, semantic style tokens, spacing, typography, panel and form controls |
| Appearance | Persisted light/dark mode toggle for organizer workspace; public event branding remains independent |
| Reusable components | `components/ui/DsButton.vue`, `DsStatusBadge.vue`, `DsEmptyState.vue` |
| Admin shell | Existing responsive navigation, search, accessible mobile drawer, route awareness and reactive event selector from P0 |
| Command Center | Real-data event spotlight, setup status, KPIs, registration trend, category breakdown and operational shortcuts |
| Events portfolio | Real counts, status tabs, client-side search, date/name ordering, adaptive cards, preview and pagination |

### P1 still requiring stage-by-stage treatment

All other admin modules reuse shared style tokens, but **do not yet have individually redesigned templates**. Registration builder, venue, check-in operations, communications, reports, intelligence and settings need design-system adoption and exploratory usability review before a complete platform-wide UI claim.

## P2 implemented in the stacked branch

| Module | Scope |
|---|---|
| Discovery | Upgraded featured banner, visual hierarchy, event cards, lazy images, categories, search and date filters |
| Public event page | Event-themed accent with contrast-aware text, improved venue/ticket cards, modern responsive booking flow |
| Ticket safety | Prevent choosing zero-inventory ticket, re-check availability before submit |
| Confirmation | Explicit pending/confirmed messaging, never show credential as verified with unsettled payment; noindex confirmation |
| SPA metadata | Per-route title, description, canonical, open graph, robots and structured-data cleanup |
| Cloudways event SEO | `public/event-meta.php` with restricted published/public event lookups, server-rendered title/canonical/OG/Event JSON-LD and noindex/404 for private events |
| PHP routing | `.htaccess` routes only public `/e/{event_id}` landing pages to the server-side renderer; receipt/confirmation tokens stay SPA-only |
| Public HTML security | Escaped metadata, JSON_HEX output and privacy-specific SEO tests |

### P2 still requiring validation

- Provider-specific Razorpay success/dismiss/failed/refund scenarios must be tested against sandbox credentials and webhook reconciliation, not a stub.
- Real event media requires multiple image sizes/formats or a trusted resizing pipeline; responsive images are currently layout-safe and lazy-loaded but not automatically transformed.
- Event discovery search and filter UX on 320px/375px needs staging screenshots and screen-reader review.
- Hybrid/virtual event JSON-LD should be refined using actual venue URLs, online location and ticket offers only when verified.
- Public page load speeds/SEO crawler results must be measured on Cloudways with a real event dataset.

## Performance baseline measured from GitHub CI

On GitHub Actions build #610 (before final component refinements):  
- Main Vite entry: ~161 KB uncompressed; **57.3 KB gzip**.  
- CSS: ~74.6 KB uncompressed; **14.1 KB gzip**.  
- Public event chunk: ~25.5 KB uncompressed; **8.5 KB gzip**.  

These are production artifact sizes, **not** LCP, INP, CLS, transferred API bytes or Lighthouse scores.

New hard gates: initial JS <=85 KiB gzip, CSS <=28 KiB gzip and individual lazy JS route chunks <=40 KiB gzip, measured against built artifacts.

## Required release test cases

1. Main PR #1 PHP, Python, frontend, PWA, Playwright and release jobs green.
2. Staged PHP 8.3 Sodium available and private app/bootstrap linked correctly.
3. Mobile/desktop dark/light and dynamic event switching do not reveal another event's data.
4. Discovery supports categories, location and date filters with only genuine published events.
5. Public event HTML source contains server-rendered canonical and Event JSON-LD; private/draft events return 404/noindex.
6. Confirmations including access token are not indexed and never cached by intermediary CDNs.
7. Paid checkout handles success, failure and dismissal without falsely claiming payment.
8. Free registration issues only server-confirmed admission credentials.
9. OnGround offline v2→v3 migration, snapshot expiry, passphrase failure, queued reconciliation, re-key and unknown credentials.
10. Cloudways/DigiOps deploy, private storage persistence, automatic backup, restore and rollback with a disposable staging workspace.
11. Browser widths 320, 375, 768, 1024, 1440. WCAG keyboard, focus, contrast, forms, error and reduced-motion checks.
12. Lighthouse mobile and p75 real-user performance checks for all main public routes.

### Suggested DigiOps deployment order

1. Keep PRs draft and `main` unchanged during test.
2. Run all GitHub Actions test gates on PR #1, then PR #2.
3. Upload the built release artifact from the **verified commit** to a staging-only DigiOps target; maintain the current `storage/` directory.
4. Back up persistent JSON data and do not reset credentials or offline queues during deployment.
5. Perform the physical offline and payment acceptance checklist above.
6. Merge PR #1 into `main` once its gates pass. Rebase/retarget PR #2 to the new main if needed, then repeat its CI and staging tests.
7. Merge PR #2 only after public registration and payment gates pass. Deploy the exact signed/reviewed release artifact, then confirm health and rollback readiness.

**Do not declare P0/P1/P2 production-complete on CI alone.** Manual acceptance evidence, actual provider credentials and server-side measurements are required.
