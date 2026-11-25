# HALA SPAIN API — Release 1

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
- `POST /auth/firebase-session` — exchange a verified Firebase Phone/Google identity for a HALA SPAIN API session
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

Homepage editorial management is documented in `HOME_CONTENT_HANDOFF_2026_11_21.md`. The existing `tripIdeas`, `destinations`, and `experiences` arrays support localized descriptions and display order. Hidden or missing-image cards are omitted; legacy cards retain stable order. No private routes or payment behavior changed.

`GET /home` keeps the two home image collections separate. `data.banners` contains the large slides above the shortcut menu. `data.offers` contains the smaller **Ofertas destacadas** cards below the menu. Both collections contain absolute `imageUrl` values and are ordered by ascending `displayOrder`, then ascending ID. The mobile client must preserve each collection's server order and must not reuse `offers` as the slide source.

`GET /me/reservations`, `GET /me/partner-services`, and `GET /me/partner-jobs` support `limit` (1–100) and an opaque `cursor`. Use `meta.pagination.nextCursor` unchanged on the next call. `hasMore` and `total` describe the filtered collection.

`GET /driver-services/{serviceId}/calendar` accepts optional `from` and `to` dates. The range cannot exceed `CALENDAR_HORIZON_DAYS` (120 by default). Its `days` array contains explicit availability records only. Administrators maintain these records from `calendar.php`; an absent date remains unavailable to the mobile client.

Upload responses include both `id` and `uploadId`. Owned media can be read through authenticated `GET /uploads/{uploadId}`. Partner profile and service responses include media objects containing both `uploadId` and `url`; the URL requires the same owner Bearer token.

Exact version 1 request/response examples, data types, nullability rules, wallet units, status values, and binary media behavior are documented in `MOBILE_PRIVATE_API_V1_EXAMPLES.md`.

Public home slides and offers omit records whose local image file is missing. The dashboard marks those records as **Missing image** and requires an administrator to upload a replacement before the card can return to the mobile API. Featured offers retain legacy absolute-URL compatibility and must be checked by the deployment smoke test.

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

## Main-slide details and recovery — 23 November 2026

The slide editor is handled by slides.php (content.php?type=banners remains compatible). It edits localized title/description, photo, display order, visibility and a fixed internal destination: none, drivers, guides or designTrip. External HTTPS links remain optional and mutually exclusive with internal destinations.

GET /home banners retains subtitle/linkUrl and adds description, highlights, sectionHeading, sections, destination and opensDrivers. An internal destination opens a button in the existing mobile detail page; drivers preserves the original Reserva ahora behavior. Unknown destinations are ignored by mobile. All banner metadata is stored under home_config._bannerDetails and is omitted from the public response. No schema migration is required.

The authenticated, CSRF-protected recovery action restores the five original mobile slides and their original es/en/ar text and driver sections from versioned recovery assets. Images are copied to persistent upload storage. Existing non-original slides are hidden, not deleted. Re-running recovery reuses restored rows and preserves editorial changes. Save/toggle/delete reject stale versions; all mutations remain transactional and audited.

## Portada mixta y actualización automática — 2026-11-23

La app consulta los catálogos públicos cada 30 segundos en primer plano y al volver desde segundo plano. Conserva formularios, sesión y cesta. Agrupa solicitudes simultáneas y aplica la respuesta completa solo tras obtener todos los catálogos. Fallos temporales conservan los datos anteriores.

Las fotos de subida tienen URL versionada por su nombre inmutable para invalidar caché al sustituirlas. El proxy local solo acepta versiones hexadecimales de 16 caracteres.

home-publishing.php permite elegir manual/mixto y recuperar las otras filas originales. Banners y ofertas comparten editor es/en/ar, descripción, foto y destino interno. La recuperación añade las ideas/destinos/experiencias que faltan y conserva cambios posteriores; las ofertas anteriores quedan ocultas.

Mixto añade hasta 4 servicios a ofertas y hasta 4 guías a experiencias, ordenados por reservas aceptadas/completadas de los últimos 90 días. Destinos usa esas reservas y mantiene orden editorial en empates. Se excluyen demos, ocultos, sin fotos y sin reservas. Crea tu viaje permanece editorial. Búsquedas/compras no son señales implementadas. No se exponen datos de clientes ni contadores privados. Sin migración de esquema. Configuración privada _homePublishing/_offerDetails omitida en /home.

Validación: 159 aserciones HTTP aisladas, pruebas del ranking, 13 pruebas Flutter de inicio/navegación/refresco y 6 pruebas del proxy.

## Administrative account restrictions
Blocked/inactive accounts cannot obtain or refresh backend sessions or use private routes (ACCOUNT_RESTRICTED, HTTP 403; revoked sessions remain 401). Existing public_user fields are unchanged. Collaborator approval is separate from account access. assigned jobs may be accepted/declined with the existing version/policy checks. Wallets remain read-only. See ADMIN_OPERATIONS_2026_11_23.md.

## Ampliación del registro profesional

El contrato aditivo y los controles del registro de siete pasos, documentos privados, vehículos, proyección pública, requisitos y comisiones se describen en [PARTNER_REGISTRATION_HANDOFF.md](PARTNER_REGISTRATION_HANDOFF.md). Se conserva schemaVersion 1 y el comportamiento de perfiles históricos. Firebase continúa como autenticación; PHP/MySQL conserva los datos. No requiere migración SQL. Los perfiles nuevos no pueden enviarse hasta publicar las condiciones legales y revisar los requisitos desde administración.


## Customer traveler details — 25 November 2026

Additive, owner-scoped extension; no SQL migration. `GET /me`, `PATCH /me` and authenticated session user include `travelerDetails` for customers only. Common names/contact/billing fields retain their existing flat keys. Extra keys: birthDate, gender (male/female/unspecified), nationality, residenceCountry, documentType (passport/id), documentNumber, documentCountry, documentIssueDate, documentExpiryDate, region, taxNumber. Values are strings, optional when editing a profile, maximum 100 characters. Dates use YYYY-MM-DD; invalid/future birth or issue dates and expiry <= issue are rejected. Empty email/phone becomes SQL NULL. Unknown extra keys are rejected; collaborators cannot write this customer-only object. No verification flag or UID can be changed through it.

Quotes add `requiredTravelerFields`. Driver and guide defaults: firstName, lastName, phone. A trusted service JSON may declare an allowlisted `travelerRequirements` list; unrelated document data is never required by default. Reservation creation revalidates the current service policy and saved owner profile on the server, returning 422 TRAVELER_DETAILS_REQUIRED with travelerDetails.FIELD errors when missing. Existing verified-phone enforcement remains. Reservation snapshots copy only required traveler fields, not the entire optional document profile. Profiles can be cleared by the owner; account deletion follows existing account deletion/backup retention rather than pretending to erase backups immediately.

Flutter reuses the existing traveler form route, lets customers save incomplete details, and opens that form before a booking when required fields are missing. Customer profile displays the entry; professional onboarding is unchanged. Document numbers are excluded from local preview preference serialization. Phone strings and their prefixes use LTR and ASCII digits under Arabic RTL.

Validation: 13 isolated PHP policy/date/ownership-boundary checks, syntax checks, and focused Flutter tests. Production persistence and deployment require verification; passing a local helper test is not proof of live MySQL behavior.

Public metadata adds customerTravelerDetailsVersion: 1 for deployment verification.

### Registro profesional compacto (0.1.9)
`GET /me/partner-registration-config` publica los cuatro IDs canónicos identity/driving/transport/guide; los adicionales creados por administración llevan `additional:true`. `PUT /me/partner-profile` sigue aceptando borradores incompletos con índices 0–6, pero rechaza documentos no aplicables a sus actividades. Flutter agrupa los índices en tres pantallas. Ver PARTNER_REGISTRATION_HANDOFF.md para compatibilidad, privacidad y condiciones de publicación. No cambia schemaVersion.
