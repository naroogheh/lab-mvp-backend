# Lab MVP Backend

Laravel backend MVP for a laboratory prescription intake flow.

## What This MVP Covers

- Receive a prescription image from the Flutter app.
- Store the image path and return a unique tracking number.
- Send the image to AvalAI through a dedicated service boundary.
- Extract requested lab tests and match them against the laboratory catalog.
- Store full AI request logs with model, request, response, usage, duration, and estimated cost.
- Build an operator-review draft invoice.
- Let the operator confirm, edit, add, or remove invoice items.
- Expose the final invoice to the mobile app after operator approval.
- Accept online or card-to-card payment intents for the next operational step.

## Setup

```text
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

## Request Statuses

```text
SUBMITTED -> AI_PROCESSING -> OPERATOR_REVIEW -> WAITING_FOR_PAYMENT -> PAID
```

Side statuses:

```text
AI_FAILED
PAYMENT_REVIEW
REJECTED
```

## Main API Surface

```text
POST   /api/prescriptions
GET    /api/prescriptions/{trackingNumber}
GET    /api/prescriptions/{trackingNumber}/invoice
POST   /api/prescriptions/{trackingNumber}/payments

GET    /api/operator/prescriptions
GET    /api/operator/prescriptions/{id}
PUT    /api/operator/prescriptions/{id}/items
POST   /api/operator/prescriptions/{id}/confirm
GET    /api/operator/ai-logs
GET    /api/operator/ai-logs/summary
```

## AI Request Logging

Every AvalAI request is stored in `ai_request_logs` with model, endpoint, purpose, redacted request payload, full response payload, extracted JSON, token usage, estimated cost, duration, status, and error details.

Use `AVALAI_VISION_MODEL` to switch models while keeping comparable logs. Update `AVALAI_INPUT_COST_PER_1M_TOKENS` and `AVALAI_OUTPUT_COST_PER_1M_TOKENS` per model to keep cost estimates accurate.

## Environment

Copy `.env.example` and fill the values used by your deployment.

```text
AVALAI_API_KEY=
AVALAI_ENDPOINT=https://api.avalai.ir/v1
AVALAI_VISION_MODEL=gpt-5.5
AVALAI_INPUT_COST_PER_1M_TOKENS=5.00
AVALAI_OUTPUT_COST_PER_1M_TOKENS=30.00
CARD_TO_CARD_NUMBER=
```

## Tests

The test suite covers the prescription upload API, tracking lookup, invoice visibility rules, operator item confirmation, card-to-card payment requests, lab-test matching, and AI request logging.

```text
php artisan test
```
