# DigiSangam

DigiSangam is a next-generation Event Operating System built as a modular, framework-light platform.

## Product phases

1. **Phase 1 — EventOS Core**: platform foundation, event management, registration, attendees, ticketing, commerce, public checkout, signed QR credentials and analytics.
2. **Phase 2 — EventOS Operations**: communications, automation, badge production, offline onsite operations, agenda/session entry, exhibitors/sponsors, leads/meetings, venue zones and reserved seating.
3. **Phase 3 — EventOS Intelligence**: Python intelligence services, AI copilots, recommendations, forecasting, lead scoring and advanced real-time operations.

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

- Communication Center with attendee segmentation, email/WhatsApp campaigns and scheduling
- Trigger/condition/action automation engine with delayed actions and lifecycle hooks
- Badge Designer with category templates, QR preview and persistent print queue
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
- Expanded Phase 2 smoke coverage

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

1. Sign in on the OnGround device.
2. Open **OnGround**.
3. Choose **Sync event data** while online.
4. Confirm the snapshot shows as ready and cached attendee count is non-zero.
5. Offline scans are stored locally and can be reconciled with **Sync check-ins** after connectivity returns.

## Current status

Phase 1 EventOS Core and the Phase 2 EventOS Operations core are implemented. External payment/email/WhatsApp delivery becomes live after server environment credentials and provider-side configuration are supplied.

Apple Wallet / Google Wallet credential issuance, native printer drivers, NFC/RFID and accreditation hardware integrations remain external-provider/hardware integrations rather than blockers for the current Phase 2 web/PWA operations core.
