# AskUs Tours API — Release 1

Base URL: `https://api.askustours.com/v1`

All successful responses use `{ "data": ..., "meta": { "requestId": "req_..." } }`. Errors use `{ "error": { "code": "...", "message": "...", "fields": {} }, "meta": { "requestId": "req_..." } }`.

## Ready endpoints

### Public catalog

- `GET /home`
- `GET /content/{slug}`
- `GET /driver-services`
- `GET /driver-services/{serviceId}`
- `GET /driver-services/{serviceId}/calendar`
- `GET /driver-services/{serviceId}/reviews`
- `GET /guide-services`
- `GET /guide-services/{guideServiceId}`
- `GET /travel-packages`
- `GET /travel-packages/{packageId}`

### Authentication and account

- `POST /auth/challenges`
- `POST /auth/sessions`
- `POST /auth/session-refresh`
- `DELETE /auth/session`
- `GET /me`
- `PATCH /me`
- `POST /uploads`
- `DELETE /uploads/{uploadId}`

Until an SMS/email provider is connected, challenge creation requires staging-only `AUTH_DEV_CODE`. Production returns `FEATURE_NOT_CONFIGURED` instead of pretending a code was delivered.

### Customer operations

- `POST /quotes`
- `POST /reservation-requests` — requires `Authorization` and `Idempotency-Key`
- `GET /me/reservations`
- `GET /me/reservations/{reservationId}`
- `GET /me/favorites`
- `PUT /me/favorites/{driverServiceId}`
- `DELETE /me/favorites/{driverServiceId}`
- `GET /me/basket`
- `PUT /me/basket/items/{itemId}`
- `DELETE /me/basket/items/{itemId}`
- `GET /me/wallet` — read-only

### Collaborator operations

- `GET /me/partner-profile`
- `PUT /me/partner-profile`
- `POST /me/partner-profile/submission`
- `GET /me/partner-services`
- `GET /me/partner-services/{partnerServiceId}`
- `PUT /me/partner-services/{partnerServiceId}`
- `GET /me/partner-jobs`
- `PATCH /me/partner-jobs/{jobId}`
- `GET /me/partner-wallet` — read-only
- `GET /partner-terms`

## Reservation request example

First request a server-owned quote:

```json
{
  "type": "driver",
  "driverServiceId": "service-uuid",
  "packageOption": "airportHotel",
  "search": {
    "date": "2026-12-20",
    "passengers": 2
  }
}
```

Then call `POST /reservation-requests` with `Authorization: Bearer ...` and a unique `Idempotency-Key`:

```json
{
  "quoteId": "quote-uuid",
  "customer": {
    "firstName": "Sara",
    "lastName": "Ahmed",
    "email": "sara@example.com",
    "phone": "+34123456789"
  }
}
```

The result remains a request with `paymentStatus: "notCollected"`. Release 1 does not accept card details or collect money.

## Retired prototype endpoints

- `/v1/create-booking.php`
- `/v1/booking-status.php`

They return HTTP 410. Mobile code must use quotes, reservation requests and authenticated reservation history.
