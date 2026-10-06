# DigiSangam Integration Runtime

## Environment

Copy `.env.example` values into the server environment. The application does not read secrets from Git.

### Razorpay

Set:

- `DIGISANGAM_PAYMENT_PROVIDER=razorpay`
- `RAZORPAY_KEY_ID`
- `RAZORPAY_KEY_SECRET`
- `RAZORPAY_WEBHOOK_SECRET`

Configure the Razorpay webhook URL as:

`https://YOUR-DOMAIN/api/v1/webhooks/payments/razorpay`

Recommended events:

- `payment.captured`
- `payment.failed`
- `order.paid`

Checkout return signatures are verified server-side; the webhook is the recovery/source-of-truth path.

### Email

Set `DIGISANGAM_EMAIL_PROVIDER` to one of:

- `log` — safe development mode
- `mail` — PHP mail()
- `smtp` — native SMTP client

For SMTP configure host, port, encryption, username, password and sender values from `.env.example`.

### WhatsApp Cloud API

Set `DIGISANGAM_WHATSAPP_PROVIDER=meta` and configure:

- `WHATSAPP_PHONE_NUMBER_ID`
- `WHATSAPP_ACCESS_TOKEN`
- `WHATSAPP_TEMPLATE_LANGUAGE`

Approved Meta templates expected by the worker:

- `registration_confirmation`
- `payment_confirmed`

## Notification worker

Run periodically with cron/systemd:

`php scripts/notifications.php 50`

For example, once per minute:

`* * * * * cd /path/to/digisangam && /usr/bin/php scripts/notifications.php 50 >> storage/notifications-worker.log 2>&1`

Messages retry with capped exponential delay metadata and move to `failed` after five processing attempts.

## QR scanner

Admin/API routes:

- `POST /api/v1/scanner/verify`
- `POST /api/v1/scanner/checkin`

Both require an authenticated role with `attendees.checkin` permission. Signed QR verification checks credential signature, attendee approval, event binding and paid-order state before allowing entry.
