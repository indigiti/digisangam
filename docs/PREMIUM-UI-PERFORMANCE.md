# Premium UI & performance foundation

This document defines the acceptance gates for the DigiSangam interface upgrade.

## Existing architecture preserved
- PHP 8.3 modular monolith, REST APIs, JSON repository adapters.
- Vue 3, Vite 7, Tailwind 4, Pinia, Vue Router.
- No framework migration, no additional production UI packages.
- DigiOps release packaging remains unchanged.

## Implemented in foundation branch
- Premium dark navigation with grouped modules, filter and route-aware active states.
- Mobile off-canvas navigation and keyboard-accessible skip link.
- Compact event-context topbar without forced full-page reload.
- Progressive page chunking, while preserving eager loading for OnGround offline functionality.
- Premium surfaces, focus indications and reduced-motion treatment.
- Narrowed PWA caching so private API responses and arbitrary pages are not persisted.

## Release validation required
1. Build the frontend with Node.js 22 using `npm install && npm run build`.
2. Run existing PHP smoke + API E2E and Python tests.
3. On an actual mobile device, verify menu open/close, route navigation, tab order and focus.
4. Switch events on Dashboard, Attendees, Analytics and OnGround without browser reload. Verify no previous-event records remain.
5. Load OnGround online, sync an event snapshot, disconnect, refresh OnGround, scan and queue a credential, then reconnect and reconcile.
6. Check that checkout and user-specific API requests are never served from service-worker cache.
7. Measure public event, discovery, dashboard and OnGround with Lighthouse mobile.
8. Test at 320px, 375px, 768px, 1024px and 1440px widths.
9. Confirm empty/loading/error states and real event data work as expected.
10. Verify the DigiOps packaged artifact on a disposable staging deployment.

## Performance targets (not yet measured)
- LCP <= 2.5s at p75 for public pages.
- INP <= 200ms at p75.
- CLS <= 0.1 at p75.
- Route change responds without forced document reload.
- Static asset cache has no unbounded growth across releases.

Do not claim a verified Lighthouse score before measurement. Avoid introducing heavy charting/animation packages into initial routes. Use real event data rather than fabricated metrics.

## Follow-on stages
- Build atomic design-system components for buttons, inputs, tables, dialogs, empty states, notifications.
- Add event-level theme tokens without mixing public checkout branding with organizer admin colors.
- Optimize marketing media with responsive size variants, lazy loading and explicit dimensions.
- Add accessible modal/drawer focus management and virtualized large datasets where benchmarks justify it.
- Add visual regression, axe and browser E2E coverage, and a release bundle-size budget.
