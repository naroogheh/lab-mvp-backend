# Lab MVP Backend

Laravel-style MVP backend for a laboratory prescription intake flow.

## What This MVP Covers

- Receive a prescription image from the Flutter app.
- Store the image path and return a unique tracking number.
- Send the image to AvalAI through a dedicated service boundary.
- Extract requested lab tests and match them against the laboratory catalog.
- Build an operator-review draft invoice.
- Let the operator confirm, edit, add, or remove invoice items.
- Expose the final invoice to the mobile app after operator approval.
- Accept online or card-to-card payment intents for the next operational step.

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
```

## Environment

Copy `.env.example` and fill the values used by your deployment.

```text
AVALAI_API_KEY=
AVALAI_ENDPOINT=https://api.avalai.ir/v1
CARD_TO_CARD_NUMBER=
```

This repository intentionally keeps the MVP implementation readable and small. It can be dropped into a fresh Laravel app or used as the implementation map for the production codebase.
