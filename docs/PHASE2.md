# Phase 2 — EventOS Operations

Phase 2 turns the Phase 1 registration/ticketing core into an event-operations platform.

## Operational modules

### Communications

- Email and WhatsApp campaigns
- Status/category/company segmentation
- Immediate or scheduled delivery
- Existing SMTP, PHP mail and Meta WhatsApp adapters
- Persistent notification outbox and retry worker

### Automation

Workflow structure:

```text
Trigger -> Conditions -> Action -> Wait -> Action
```

Lifecycle hooks currently fire for:

- `person.registered`
- `attendee.confirmed`
- `payment.captured`
- `attendee.checked_in`
- `session.entered`

Email and WhatsApp actions support delayed execution through `not_before` scheduling.

### Badge operations

- Badge templates
- Per-category designs
- Dynamic name/company/category fields
- QR placement
- Persistent print production queue
- Browser print preview

Native printer/driver integrations can consume the print queue without changing the Badge domain.

### Agenda

- Sessions, tracks and rooms
- Start/end scheduling
- Capacity
- Speakers
- Session-entry QR scan
- Duplicate attendance prevention
- Session-full rejection

### Exhibitors & Sponsors

- Company profiles
- Booth mapping
- Staff quotas
- Lead quotas
- Lead capture with intent/score/notes
- Attendee-to-exhibitor meeting scheduling

### Venue & access

- Venue profile
- Access zones
- Category-based zone policies
- General and reserved seating models
- Collision-safe seat assignment

### OnGround PWA

The OnGround client is installable and offline-first.

Online mode validates:

- QR signature
- Event binding
- attendee status
- paid order where applicable
- requested zone
- duplicate check-in

Offline mode uses a previously authenticated event snapshot cached in IndexedDB. Stable signed v2 QR payloads are exact-matched against the snapshot and accepted scans are queued locally. Reconciliation re-runs server-side validation when connectivity returns.

## Storage

Phase 2 stays within the requested no-database architecture. JSON persistence remains hidden behind repository boundaries and sensitive mutation paths use locked/atomic file operations.

## CI

CI covers:

- Vue production build
- PHP 8.3 syntax
- Phase 1 commerce/security smoke tests
- automation execution and delay rules
- campaign segmentation
- offline snapshot credential matching
- zone access policy
- session entry duplication
- seat collision prevention
- lead/meeting/print queue records

## External integrations still requiring credentials/hardware

These are deliberately adapter/hardware concerns, not hard-coded dependencies:

- Apple Wallet signing certificate and pass infrastructure
- Google Wallet service account / issuer setup
- NFC / RFID readers
- turnstiles
- native thermal/card printer drivers
- production Razorpay credentials
- SMTP credentials
- Meta WhatsApp templates/tokens
