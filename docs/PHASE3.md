# Phase 3 — EventOS Intelligence

Phase 3 adds an intelligence layer without moving transaction ownership away from the PHP EventOS core.

## Architecture

```text
PHP repositories
      ↓
Authoritative Event Graph
      ↓
IntelligenceClient
  ├─ Python/FastAPI engine
  └─ deterministic PHP fallback
      ↓
Forecasts / anomalies / recommendations / lead scores / crowd intelligence
      ↓
Organizer Copilot + Attendee Concierge + Intelligence Center
```

Registration, payments, check-in and inventory do not depend on Python availability.

## Event Graph

The graph is built per event from current repository state.

Node domains include:

- attendees
- orders
- tickets
- sessions
- exhibitors
- leads
- meetings
- zones
- seats

Edges include:

- attendee → ticket
- attendee → order
- attendee → event check-in
- attendee → session attendance
- exhibitor → attendee lead
- exhibitor → attendee meeting
- attendee → reserved seat

Derived metrics include registration count, confirmations, pending approvals, check-ins, revenue, payment failure rate, capacity, registration trend, session attendance and zone occupancy.

## Intelligence engine

The Python service implements:

- 7-day registration forecasting
- expected-footfall forecasting
- confidence rating
- payment anomaly detection
- approval-backlog detection
- lead scoring
- attendee recommendations
- crowd-risk classification
- operational insight prioritization
- Organizer Copilot
- Attendee Concierge

The PHP fallback exposes the same core response shape.

## Digital Twin / crowd intelligence

A zone access event is recorded separately from event check-in.

This matters because:

```text
Gate check-in != current physical zone
```

DigiSangam stores zone movements and calculates occupancy from each attendee's latest zone scan. Crowd intelligence classifies each configured zone as:

- normal
- moderate
- high
- critical

and returns an operational intervention recommendation.

## Organizer Copilot

Admin endpoint:

```text
POST /api/v1/intelligence/copilot
```

The Copilot is grounded in the current Event Graph. It can answer questions about registrations, revenue/payment health, crowd pressure, exhibitor leads and operational anomalies.

The admin UI is available at:

```text
/intelligence
```

## Attendee Concierge

The attendee never supplies a raw attendee ID.

Public endpoint:

```text
POST /api/v1/public/concierge/{private_confirmation_token}
```

PHP validates the private confirmation token, resolves the attendee/event, then scopes the intelligence request. Rate limiting is applied to the public endpoint.

The Concierge is embedded on the attendee confirmation page and can provide personalized help for sessions, exhibitors and access status.

## AI action governance

Intelligence suggestions do not directly perform consequential actions.

```text
AI suggestion
    ↓
Action proposal
    ↓
Pending
  ↙       ↘
Reject   Approve
           ↓
Safe draft creation only
```

Supported governed proposal types:

- operator note
- campaign draft
- workflow draft

Campaigns are created in draft state and workflows are created disabled. Sending a campaign or enabling a workflow remains an explicit operator action.

## Python service

Location:

```text
/intelligence
```

Run directly:

```bash
cd intelligence
python -m venv .venv
. .venv/bin/activate
pip install -r requirements.txt
DIGISANGAM_INTELLIGENCE_TOKEN=change-me uvicorn app:app --host 127.0.0.1 --port 8100
```

Or build the included Docker image.

PHP configuration:

```text
DIGISANGAM_INTELLIGENCE_URL=http://127.0.0.1:8100
DIGISANGAM_INTELLIGENCE_TOKEN=change-me
```

If the service is unreachable or returns a non-2xx response, PHP automatically uses the local intelligence engine.

## CI

The full pipeline now validates:

- Vue production build
- PHP 8.3 syntax
- Phase 1/2/3 PHP smoke tests
- stable signed credentials
- Event Graph metrics
- zone occupancy ledger
- local intelligence fallback
- Copilot behavior
- Concierge recommendations
- AI lead scoring
- AI action approval gate
- Python syntax
- Python intelligence unit tests

## External enhancement path

A generative LLM can later be connected behind the Copilot/Concierge service boundary. It is not required for current functionality, and any future model-generated consequential action should continue to use the same approval queue.
