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
- `GET /metadata/catalog` — supported city, category, language and currency codes
- `GET /metadata/catalog` — supported city, category, language and currency codes

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

For production OTP delivery, configure `AUTH_OTP_WEBHOOK_URL`, `AUTH_OTP_WEBHOOK_TOKEN`, and `AUTH_OTP_SENDER`. The webhook receives `channel`, `destination`, `code`, `sender`, and `expiresInSeconds`, and must return a 2xx response. Challenge requests are limited to one per minute and five per destination per hour.

For production OTP delivery, configure `AUTH_OTP_WEBHOOK_URL`, `AUTH_OTP_WEBHOOK_TOKEN`, and `AUTH_OTP_SENDER`. The webhook receives `channel`, `destination`, `code`, `sender`, and `expiresInSeconds`, and must return a 2xx response. Challenge requests are limited to one per minute and five per destination per hour.

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

## Pagination, calendars, media, and wallets

`GET /home` keeps the two home image collections separate. `data.banners` contains the large slides above the shortcut menu. `data.offers` contains the smaller **Ofertas destacadas** cards below the menu. Both collections contain absolute `imageUrl` values and are ordered by ascending `displayOrder`, then ascending ID. The mobile client must preserve each collection's server order and must not reuse `offers` as the slide source.

`GET /me/reservations`, `GET /me/partner-services`, and `GET /me/partner-jobs` support `limit` (1–100) and an opaque `cursor`. Use `meta.pagination.nextCursor` unchanged on the next call. `hasMore` and `total` describe the filtered collection.

`GET /driver-services/{serviceId}/calendar` accepts optional `from` and `to` dates. The range cannot exceed `CALENDAR_HORIZON_DAYS` (120 by default). Its `days` array contains explicit availability records only. Administrators maintain these records from `calendar.php`; an absent date remains unavailable to the mobile client.

Upload responses include both `id` and `uploadId`. Owned media can be read through authenticated `GET /uploads/{uploadId}`. Partner profile and service responses include media objects containing both `uploadId` and `url`; the URL requires the same owner Bearer token.

Exact version 1 request/response examples, data types, nullability rules, wallet units, status values, and binary media behavior are documented in `MOBILE_PRIVATE_API_V1_EXAMPLES.md`.

Public home collections omit records whose local image file is missing. The dashboard marks those records as **Missing image** and requires an administrator to upload a replacement before the record can return to the mobile API.

The published synthetic travel-package fixture contains `destinations`, `image`, top-level `imageUrl`, `itinerary`, `terms`, and `availability`. It remains demo data and must not be presented as commercial inventory.

Partner profiles use `schemaVersion: 1`. Version 1 deliberately contains only business/contact/activity/city and professional-photo fields. Vehicle identity documents and regulatory records remain excluded until product, retention, and access rules are approved.

Wallet reads use `schemaVersion: 1` and stable movement fields: `id`, `type`, `amountMinor`, `currency`, `status`, `referenceType`, `referenceId`, `description`, and `createdAt`.

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
