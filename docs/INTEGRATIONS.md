# DigiSangam Integration Runtime

DigiSangam 3.1.0 keeps providers behind adapters. Core workflows remain testable with log/simulated providers; production delivery activates when the corresponding provider credentials or hardware are configured.

## Environment

Copy the values from `.env.example` into the server environment. Do not commit production secrets.

## Payments — Razorpay

Set:

- `DIGISANGAM_PAYMENT_PROVIDER=razorpay`
- `RAZORPAY_KEY_ID`
- `RAZORPAY_KEY_SECRET`
- `RAZORPAY_WEBHOOK_SECRET`

Webhook route:

`/digisangam/api/v1/webhooks/payments/razorpay`

Checkout signatures are verified server-side; signed webhooks are the asynchronous recovery/source-of-truth path.

## Email

`DIGISANGAM_EMAIL_PROVIDER` supports:

- `log` — simulated development delivery
- `mail` — PHP mail()
- `smtp` — native SMTP adapter

Configure the sender and SMTP settings from `.env.example`.

## WhatsApp

Set `DIGISANGAM_WHATSAPP_PROVIDER=meta` and configure the Meta Cloud API phone-number ID, access token, language and optional Graph version.

The log adapter remains available for safe local verification.

## SMS

`DIGISANGAM_SMS_PROVIDER` supports:

- `log`
- `http`

The generic HTTP adapter posts:

```json
{"to":"9198...","message":"...","sender":"..."}
```

Configure:

- `SMS_HTTP_ENDPOINT`
- `SMS_HTTP_TOKEN`
- `SMS_SENDER`

This keeps DigiSangam independent of a single SMS vendor.

## Notification worker

Run periodically:

`php scripts/notifications.php 50`

It processes email, WhatsApp and SMS messages, applies retries and records simulated/sent/failed delivery state truthfully.

## Developer webhooks

Admin users can create event-scoped webhook endpoints from **Developers**.

DigiSangam signs each JSON delivery with:

`X-DigiSangam-Signature: sha256=<hmac>`

Run the retryable webhook worker:

`php scripts/webhooks.php 50`

Webhook signing secrets are returned on creation and kept out of subsequent list responses.

## Developer API

Create a scoped API key from **Developers**. External requests send:

`X-DigiSangam-Key: dsk_...`

Available read surfaces:

- `GET /digisangam/api/v1/developer/v1/events/{event_id}`
- `GET /digisangam/api/v1/developer/v1/events/{event_id}/attendees`
- `GET /digisangam/api/v1/developer/v1/events/{event_id}/sessions`
- `GET /digisangam/api/v1/developer/v1/events/{event_id}/analytics`

Keys are stored as hashes and can be scoped/revoked.

## Wallet passes

The attendee confirmation page can issue Apple or Google wallet records.

Configure provider/issuer handoff bases:

- `APPLE_WALLET_PASS_BASE_URL`
- `GOOGLE_WALLET_SAVE_BASE_URL`

Without issuer credentials, DigiSangam returns `provider_configuration_required` rather than falsely claiming that a wallet pass is live.

## Badge printing

`DIGISANGAM_PRINT_PROVIDER` supports:

- `log` — simulated print delivery
- `raw_tcp` — direct network printer, normally port 9100
- `cups` — native server CUPS queue

Configure:

- `PRINTER_HOST`
- `PRINTER_PORT`
- `PRINTER_QUEUE`

Run:

`php scripts/badge-print.php 20`

The worker resolves the attendee/template, renders printer payload and persists printed/simulated/failed state.

## QR, NFC and RFID access

QR routes:

- `POST /api/v1/scanner/verify`
- `POST /api/v1/scanner/checkin`

NFC/RFID UIDs are first bound to an attendee from **OnGround**. Reader input can then be passed as:

- `nfc:UID`
- `rfid:UID`

The resolved identity uses the same attendee, payment, event and zone policy engine as QR credentials.

## Media uploads

DigiSangam stores branding assets, registration uploads and accreditation documents in private runtime storage with metadata in the JSON repository layer.

Public branding assets are exposed through controlled media URLs; private uploads are not public by default.

## Intelligence service

Configure:

- `DIGISANGAM_INTELLIGENCE_URL`
- `DIGISANGAM_INTELLIGENCE_TOKEN`

The Python/FastAPI service supports the Event Graph intelligence workflows and AI Event Builder. Supported PHP fallbacks remain available if the Python service is unavailable.

## DigiOps workers

A production deployment should schedule:

- `scripts/notifications.php`
- `scripts/webhooks.php`
- `scripts/badge-print.php`
- `scripts/order-expiry.php`
- `scripts/media-cleanup.php`

Run notifications, webhooks, badge printing and order expiry about every five minutes; run media cleanup about hourly. The DigiOps release verifier checks that all five workers are present in the private release payload.
