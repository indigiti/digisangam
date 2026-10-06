# DigiSangam Architecture

## Core principles

- Modular monolith first.
- Plain PHP backend with explicit module boundaries.
- Vue 3 + Tailwind frontend.
- Repository interfaces isolate persistence.
- JSON/file storage is an initial adapter, not a domain dependency.
- Domain events feed audit, analytics, automation and future AI.
- Python remains outside the transactional path.

## Core domains

- Core
- Auth
- Workspace
- Events
- Registration
- Attendees
- Tickets
- Commerce
- Communication
- Automation
- Badges
- OnGround
- Analytics
- Integrations

## Initial data flow

Browser/PWA -> Vue Admin -> /api/v1 -> PHP Services -> Repository Interfaces -> JSON/File Adapters

Python/FastAPI will be introduced in Phase 3 for analytics, recommendations and AI workloads.
