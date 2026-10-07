# DigiSangam Three-Phase Roadmap

**Status: complete for the planned three-phase software scope.**

External provider accounts, issuer certificates, printer hardware, RFID/NFC readers and production credentials are deployment integrations, not missing DigiSangam software modules.

## Phase 1 — EventOS Core ✅

- Platform foundation
- Authentication and RBAC
- Workspace model
- Event management
- Event creation wizard
- Registration form builder
- Categories and validation
- Attendee directory and profile
- Invitations and approvals
- Ticket types, capacity and pricing
- Commerce/order foundation
- QR generation
- Basic analytics
- CSV import/export
- Event journal and audit trail
- Public registration and payment lifecycle
- Branding media uploads and registration file uploads

## Phase 2 — EventOS Operations ✅

- Email, WhatsApp and SMS adapters
- Campaigns and segmentation
- Workflow automation engine
- Badge designer and persistent print queue
- Log, raw TCP and CUPS printer adapters
- Offline-capable onsite PWA
- QR scanning and access validation
- Walk-in registration
- NFC/RFID credential binding and scanner resolution
- Seating and zone management
- Agenda, speakers and sessions
- Exhibitors, sponsors and leads
- Accreditation lifecycle and document attachments
- Apple/Google wallet-pass lifecycle and provider handoff
- Advanced operational dashboards
- Saved custom report builder and CSV exports

## Phase 3 — EventOS Intelligence ✅

- Python/FastAPI intelligence services
- PHP intelligence fallback
- Organizer AI copilot
- Attendee concierge
- AI event builder
- Recommendations and matchmaking
- Lead scoring
- Registration/revenue/no-show forecasting
- Anomaly detection
- Event control room / crowd intelligence
- Event Graph and digital-twin occupancy model
- Approval-governed AI actions
- Advanced access integrations
- Developer platform with scoped API keys
- Signed webhook endpoints and retryable webhook worker
- App/integration-ready external API surface

## Verification baseline

The repository CI verifies:

- Vue production build
- PHP syntax across application/API/scripts/tests
- zero-state full-module smoke tests
- production HTTP API end-to-end tests
- Python intelligence unit tests
- DigiOps split public/private release packaging
- release artifact contract and worker presence

The completion baseline starts from an empty storage directory so demo fixtures cannot make unfinished modules appear operational.
