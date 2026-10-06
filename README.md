# DigiSangam

DigiSangam is a next-generation Event Operating System built as a modular, framework-light platform.

## Product phases

1. **Phase 1 — EventOS Core**: platform foundation, event management, registration, attendees, ticketing, commerce, public checkout, signed QR credentials and analytics.
2. **Phase 2 — EventOS Operations**: communications UI, automation, badges, offline onsite operations, seating, agenda, exhibitors, sponsors and accreditation.
3. **Phase 3 — EventOS Intelligence**: Python intelligence services, AI copilots, recommendations, forecasting, lead scoring and advanced real-time operations.

## Technical direction

- Plain PHP 8.3+ backend — no Laravel
- Vue 3 + Vite + Tailwind CSS admin/public UI
- JSON/file persistence behind repository interfaces for the initial no-database build
- Locked/atomic file transactions for inventory-sensitive paths
- Python/FastAPI reserved for analytics/AI workloads
- REST JSON APIs
- Provider adapters instead of vendor SDK coupling
- Offline-capable onsite PWA in Phase 2
- Strict module boundaries so persistence/providers can be replaced without rewriting product logic

## Phase 1 implemented

- First-run secure administrator setup, sessions, CSRF and RBAC
- Workspace/event management and public event pages
- Dynamic registration schema, invitations and approvals
- Attendee CRUD, CSV import/export and private confirmation links
- Ticket inventory with locked reservation/release
- Orders and payment-provider abstraction
- Razorpay Orders/Checkout adapter, callback verification and signed webhook handling
- Free/manual payment modes
- Signed attendee QR credentials and server-side verification
- Functional OnGround verify/check-in API and admin screen
- Email notification adapters: log, PHP mail and native SMTP
- WhatsApp adapters: log and Meta Cloud API
- Retryable notification outbox + CLI worker
- Printable ticket/receipt page with legal/GST workspace fields
- PHP smoke tests + frontend build + PHP syntax CI

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

## Current status

Phase 1 EventOS Core is functionally implemented. External payment/email/WhatsApp delivery becomes live only after server environment credentials and provider-side webhook/template configuration are supplied.
