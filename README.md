# DigiSangam

DigiSangam is the codename for a next-generation Event Operating System.

## Product phases

1. **Phase 1 — EventOS Core**: platform foundation, event management, registration, attendees, tickets, commerce foundation, QR and analytics.
2. **Phase 2 — EventOS Operations**: communications, automation, badges, onsite operations, seating, agenda, exhibitors, sponsors and accreditation.
3. **Phase 3 — EventOS Intelligence**: Python intelligence services, AI copilots, recommendations, forecasting, lead scoring and advanced real-time operations.

## Technical direction

- Plain PHP 8.3+ backend — no Laravel
- Vue 3 + Vite + Tailwind CSS admin
- JSON/file persistence behind repository interfaces for the initial no-database build
- Python/FastAPI services only for analytics/AI workloads
- REST JSON APIs
- Offline-capable onsite PWA in later phases
- Strict module boundaries so JSON persistence can later be replaced without rewriting product logic

## Status

Repository initialized. Phase 1 foundation is the first implementation target.
