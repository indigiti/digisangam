# DigiSangam

DigiSangam is a next-generation Event Operating System built as a modular, framework-light platform.

## Product phases

1. **Phase 1 — EventOS Core**: platform foundation, event management, registration, attendees, ticketing, commerce, public checkout, signed QR credentials and analytics.
2. **Phase 2 — EventOS Operations**: communications, automation, badge production, offline onsite operations, agenda/session entry, exhibitors/sponsors, leads/meetings, venue zones and reserved seating.
3. **Phase 3 — EventOS Intelligence**: Event Graph, Python intelligence service, Organizer Copilot, attendee Concierge, recommendations, forecasting, anomaly detection, lead scoring, crowd intelligence and approval-governed AI actions.

## Technical direction

- Plain PHP 8.3+ backend — no Laravel
- Vue 3 + Vite + Tailwind CSS admin/public UI
- JSON/file persistence behind repository interfaces for the initial no-database build
- Locked/atomic file transactions for inventory and collision-sensitive paths
- Python/FastAPI reserved for analytics/AI workloads
- REST JSON APIs
- Provider adapters instead of vendor SDK coupling
- Installable offline-first OnGround PWA
- Strict module boundaries so persistence/providers can be replaced without rewriting product logic

## Phase 1 implemented

- Secure first-run administrator setup, sessions, CSRF and RBAC
- Workspace/event management and public event pages
- Dynamic registration schema, invitations and approvals
- Attendee CRUD, CSV import/export and private confirmation links
- Ticket inventory with locked reservation/release
- Orders and payment-provider abstraction
- Razorpay Orders/Checkout adapter, callback verification and signed webhook handling
- Free/manual payment modes
- Signed attendee QR credentials and server-side verification
- Email notification adapters: log, PHP mail and native SMTP
- WhatsApp adapters: log and Meta Cloud API
- Retryable notification outbox + CLI worker
- Printable ticket/receipt page with legal/GST workspace fields
- PHP smoke tests + frontend build + PHP syntax CI

## Phase 2 implemented

- Communication Center with attendee segmentation, email/WhatsApp/SMS campaigns and scheduling
- Trigger/condition/action automation engine with delayed actions and lifecycle hooks
- Badge Designer with category templates, QR preview, persistent print queue, raw TCP and CUPS printer adapters
- Agenda/session management with capacity controls and session-entry QR scanning
- Exhibitor/sponsor workspace with booth/staff/lead quotas
- Lead capture and meeting scheduling
- Venue, access zones and category-based entry policies
- Collision-safe reserved seat assignments
- Installable OnGround PWA with service worker
- IndexedDB event snapshots for offline attendee/credential lookup
- Stable signed v2 QR credentials for offline matching
- Offline check-in queue + reconnect reconciliation
- Signed snapshot payloads
- Zone-aware online/offline check-in
- Duplicate event/session check-in prevention
- Walk-in registration with attendee provenance
- NFC/RFID credential binding through the same access-policy engine
- Accreditation lifecycle with document attachment support
- Apple/Google wallet-pass lifecycle and provider handoff
- Private media storage for event branding and registration uploads
- Advanced reports with saved report definitions, filters, selected columns and CSV export
- Expanded Phase 2 smoke coverage

## Phase 3 implemented

- Authoritative Event Graph assembled from attendees, orders, tickets, sessions, exhibitors, leads, meetings, seating, event check-ins and session attendance
- Python/FastAPI intelligence service with Docker packaging
- Resilient PHP intelligence fallback when Python is unavailable
- Registration and 7-day footfall forecasting
- Payment and approval anomaly detection
- AI-ranked exhibitor leads
- Personalized session and exhibitor recommendations
- Organizer Copilot grounded in current Event Graph metrics
- Private attendee Concierge scoped by confirmation token
- Live crowd intelligence using zone movement events rather than first-gate state
- Digital Twin zone occupancy, risk levels and intervention recommendations
- Intelligence Center admin UI
- RBAC permissions for intelligence view/use/manage
- Approval-governed AI action queue
- Approved safe campaign/workflow draft creation
- Python, PHP and Vue CI coverage for Phase 3
- AI Event Builder with Python intelligence endpoint and deterministic PHP fallback
- Developer Platform with scoped API keys
- Signed, retryable webhook delivery worker
- External read API for events, attendees, sessions and analytics

## Runtime configuration

See [.env.example](.env.example) and [docs/INTEGRATIONS.md](docs/INTEGRATIONS.md).

No secrets should be committed to this repository.

## Notification worker

Run periodically:

```bash
php scripts/notifications.php 50
```

## Public registration

Published/live events are available at:

```text
/e/{event_id}
```

## OnGround preparation

Before venue connectivity becomes unreliable:

1. Sign in on the OnGround device and select the correct event.
2. Open **OnGround** while connected; enter an offline passphrase with **at least 10 characters**.
3. Choose **Prepare / Re-sync**. This verifies a 48-hour, Ed25519-signed snapshot and encrypts its contents before writing them to IndexedDB.
4. Confirm the snapshot shows as **Ready** with the expected attendee count. The event passphrase is not stored or recoverable.
5. If the connection fails or the device reloads offline, open **OnGround** and **unlock the saved snapshot** using the passphrase. Only this constrained device workflow is available without a live login session.
6. Offline check-ins are encrypted locally and are only provisional until the server revalidates them. Reconnect and sync the queued check-ins.
7. **Do not clear browser data, reset the passphrase, or replace a prepared snapshot with unsynced entries.** Existing v2 snapshots must be prepared again online for the v3 trust model.

See [P0 pre-redesign audit](docs/P0-PRE-REDESIGN-AUDIT.md) for the release requirements and migration caveats.

## Current status

Phase 1 EventOS Core, Phase 2 EventOS Operations, and Phase 3 EventOS Intelligence are implemented, including the previously pending accreditation, walk-in, SMS, wallet, media-upload, report-builder, AI Event Builder, developer-platform, NFC/RFID and printer-adapter software paths.

External services become live after their own production credentials or hardware are supplied: Razorpay, SMTP/email, Meta WhatsApp, HTTP SMS, Apple/Google Wallet issuer endpoints, network/CUPS printers and NFC/RFID reader input. These are deployment/provider dependencies rather than missing application modules.

The Python intelligence service remains optional at runtime because DigiSangam retains deterministic PHP fallbacks for supported intelligence workflows.

Current DigiOps payload version: **3.1.0**.
