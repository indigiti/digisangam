# DigiSangam Intelligence Service

Phase 3 intelligence is isolated from the PHP transaction path. PHP builds the authoritative Event Graph and calls this Python service when configured. If it is unavailable, DigiSangam uses the built-in deterministic PHP fallback instead of failing the admin/public experience.

## Capabilities

- registration and footfall forecasting
- payment/approval anomaly detection
- exhibitor lead scoring
- attendee session/exhibitor recommendations
- zone occupancy and crowd risk
- Organizer Copilot
- attendee Concierge
- prioritized operational insights

## Run locally

```bash
cd intelligence
python -m venv .venv
. .venv/bin/activate
pip install -r requirements.txt
DIGISANGAM_INTELLIGENCE_TOKEN=change-me uvicorn app:app --host 127.0.0.1 --port 8100
```

Set PHP runtime variables:

```text
DIGISANGAM_INTELLIGENCE_URL=http://127.0.0.1:8100
DIGISANGAM_INTELLIGENCE_TOKEN=change-me
```

## Docker

```bash
docker build -t digisangam-intelligence intelligence
docker run --rm -p 8100:8100 -e DIGISANGAM_INTELLIGENCE_TOKEN=change-me digisangam-intelligence
```

## Security boundary

The health endpoint is public for infrastructure probes. Analysis, Copilot and Concierge endpoints require the bearer token when `DIGISANGAM_INTELLIGENCE_TOKEN` is configured. The public attendee never calls Python directly; PHP validates the private confirmation token first and passes only the scoped request to the internal service.

## Failure model

The PHP `IntelligenceClient` uses short connection/request timeouts. Any connection failure, non-2xx response or invalid payload falls back to the local PHP intelligence engine. Registration, payments and onsite scanning never depend on Python availability.
