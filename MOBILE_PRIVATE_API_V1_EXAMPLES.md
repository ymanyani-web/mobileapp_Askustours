# Mobile private API v1 canonical examples

Date: 18 November 2026

These examples document the exact Release 1 response shapes for the private wallet, collaborator profile, collaborator service, and upload endpoints requested in `MOBILE_BACKEND_VERIFICATION_RESPONSE.md`.

All private endpoints require:

```http
Authorization: Bearer <access-token>
Accept: application/json
```

Do not log or persist the example token text. Real access tokens are owner-specific.

## Common JSON envelope

```json
{
  "data": {},
  "meta": {
    "requestId": "req_0123456789abcdef"
  }
}
```

## Upload reference

The same media-reference shape is used by collaborator profiles and service drafts.

```json
{
  "uploadId": "90000000-0000-4000-8000-000000000001",
  "url": "https://api.askustours.com/v1/uploads/90000000-0000-4000-8000-000000000001",
  "purpose": "professional-photo",
  "fileName": "profile.webp",
  "contentType": "image/webp",
  "sizeBytes": 48231,
  "createdAt": "2026-11-18T10:30:00+00:00"
}
```

Fields:

| Field | Type | Nullable | Meaning |
|---|---|---:|---|
| `uploadId` | UUID string | No | Stable owner-scoped upload ID |
| `url` | HTTPS string | No | Authenticated API media URL |
| `purpose` | enum string | No | `profile-photo`, `professional-photo`, `driver-service-photo`, or `guide-service-photo` |
| `fileName` | string | No | Original client filename for display only |
| `contentType` | enum string | No | `image/jpeg`, `image/png`, or `image/webp` |
| `sizeBytes` | integer | No | Decoded binary size in bytes |
| `createdAt` | ISO 8601 string | No | UTC creation timestamp |

If the database row exists but its stored file is missing, the API omits that reference instead of returning a broken URL.

## `POST /uploads`

Request:

```json
{
  "purpose": "professional-photo",
  "fileName": "profile.webp",
  "contentType": "image/webp",
  "dataUri": "data:image/webp;base64,<base64-data>"
}
```

Success: HTTP 201.

```json
{
  "data": {
    "id": "90000000-0000-4000-8000-000000000001",
    "uploadId": "90000000-0000-4000-8000-000000000001",
    "purpose": "professional-photo",
    "fileName": "profile.webp",
    "contentType": "image/webp",
    "sizeBytes": 48231,
    "url": "https://api.askustours.com/v1/uploads/90000000-0000-4000-8000-000000000001",
    "createdAt": "2026-11-18T10:30:00+00:00"
  },
  "meta": {
    "requestId": "req_0123456789abcdef"
  }
}
```

The decoded limit is 5 MiB. The server validates both declared MIME type and file signature.

## `GET /uploads/{uploadId}`

This endpoint returns binary image bytes, not a JSON success envelope.

Example response headers:

```http
HTTP/1.1 200 OK
Content-Type: image/webp
Content-Length: 48231
Content-Disposition: inline; filename="profile.webp"
Cache-Control: private, max-age=300
```

The bearer token must belong to the upload owner. A missing row, different owner, or missing stored file returns the standard JSON 404 `NOT_FOUND` envelope.

## `GET /me/partner-profile`

Example with a professional photo:

```json
{
  "data": {
    "id": "10000000-0000-4000-8000-000000000002",
    "userId": "10000000-0000-4000-8000-000000000002",
    "businessName": "Example Mobility",
    "contactName": "Example Partner",
    "phone": "+34910000000",
    "email": "partner@example.test",
    "activity": "driver",
    "cities": ["madrid"],
    "citiesText": "Madrid",
    "professionalPhotoUploadId": "90000000-0000-4000-8000-000000000001",
    "profile": {
      "businessName": "Example Mobility",
      "contactName": "Example Partner",
      "phone": "+34910000000",
      "email": "partner@example.test",
      "activity": "driver",
      "cities": ["madrid"],
      "citiesText": "Madrid",
      "professionalPhotoUploadId": "90000000-0000-4000-8000-000000000001"
    },
    "professionalPhotoUrl": "https://api.askustours.com/v1/uploads/90000000-0000-4000-8000-000000000001",
    "professionalPhoto": {
      "uploadId": "90000000-0000-4000-8000-000000000001",
      "url": "https://api.askustours.com/v1/uploads/90000000-0000-4000-8000-000000000001",
      "purpose": "professional-photo",
      "fileName": "profile.webp",
      "contentType": "image/webp",
      "sizeBytes": 48231,
      "createdAt": "2026-11-18T10:30:00+00:00"
    },
    "schemaVersion": 1,
    "status": "approved",
    "submittedAt": "2026-11-17T12:00:00+00:00",
    "reviewedAt": "2026-11-17T13:00:00+00:00",
    "revision": 3
  },
  "meta": {
    "requestId": "req_0123456789abcdef"
  }
}
```

Notes:

- Supported response statuses are `draft`, `pending`, `approved`, `rejected`, and `suspended`.
- Stored `submitted` is serialized as `pending`.
- `submittedAt` and `reviewedAt` are nullable ISO 8601 strings.
- `professionalPhoto` and `professionalPhotoUrl` are null when no usable owned file exists.
- `professionalPhotoUploadId` remains in the raw profile so an editor can preserve or replace the selected ID deliberately.
- `profile` is the complete schema-version-1 editable profile object. The supported editable keys are also projected at the top level for current-client compatibility.

## `GET /me/partner-services/{id}`

Driver example:

```json
{
  "data": {
    "id": "60000000-0000-4000-8000-000000000001",
    "type": "driver",
    "status": "draft",
    "service": {
      "title": {
        "es": "Traslado ejecutivo",
        "en": "Executive transfer",
        "ar": "نقل تنفيذي"
      },
      "city": "madrid",
      "category": "executive",
      "photoUploadIds": [
        "91000000-0000-4000-8000-000000000001"
      ],
      "media": [
        {
          "uploadId": "91000000-0000-4000-8000-000000000001",
          "url": "https://api.askustours.com/v1/uploads/91000000-0000-4000-8000-000000000001",
          "purpose": "driver-service-photo",
          "fileName": "vehicle.webp",
          "contentType": "image/webp",
          "sizeBytes": 73120,
          "createdAt": "2026-11-18T10:40:00+00:00"
        }
      ]
    },
    "revision": 2,
    "updatedAt": "2026-11-18T10:45:00+00:00"
  },
  "meta": {
    "requestId": "req_0123456789abcdef"
  }
}
```

Guide drafts use one source ID:

```json
{
  "photoUploadId": "92000000-0000-4000-8000-000000000001",
  "media": [
    {
      "uploadId": "92000000-0000-4000-8000-000000000001",
      "url": "https://api.askustours.com/v1/uploads/92000000-0000-4000-8000-000000000001",
      "purpose": "guide-service-photo",
      "fileName": "guide.webp",
      "contentType": "image/webp",
      "sizeBytes": 61200,
      "createdAt": "2026-11-18T10:40:00+00:00"
    }
  ]
}
```

Rules:

- Driver service source IDs: `service.photoUploadIds`, array, maximum four.
- Guide service source ID: `service.photoUploadId`, zero or one.
- Resolved references: `service.media`, always an array.
- Missing or non-owned files do not appear in `service.media`; source IDs remain available for explicit editor correction.
- `updatedAt` is an ISO 8601 timestamp.

The paginated `GET /me/partner-services` collection uses the same item shape and adds `meta.pagination`.

## `GET /me/wallet`

Customer wallet example:

```json
{
  "data": {
    "schemaVersion": 1,
    "balance": {
      "amountMinor": 2500,
      "currency": "EUR"
    },
    "movements": [
      {
        "id": "c0000000-0000-4000-8000-000000000001",
        "type": "demo_credit",
        "amountMinor": 2500,
        "currency": "EUR",
        "status": "available",
        "referenceType": "demo",
        "referenceId": null,
        "description": "Synthetic customer wallet entry",
        "createdAt": "2026-11-18T09:00:00+00:00"
      }
    ]
  },
  "meta": {
    "requestId": "req_0123456789abcdef"
  }
}
```

`balance.amountMinor` is the signed sum of `available` customer movements. All monetary integers are minor currency units; `2500` EUR minor units means EUR 25.00. Release 1 supports EUR.

## `GET /me/partner-wallet`

Collaborator wallet example:

```json
{
  "data": {
    "schemaVersion": 1,
    "pendingMinor": 8500,
    "availableMinor": 12500,
    "paidMinor": 25000,
    "voidMinor": 0,
    "currency": "EUR",
    "movements": [
      {
        "id": "d0000000-0000-4000-8000-000000000001",
        "type": "reservation_commission",
        "amountMinor": 8500,
        "currency": "EUR",
        "status": "pending",
        "referenceType": "reservation",
        "referenceId": "80000000-0000-4000-8000-000000000001",
        "description": "Pending synthetic reservation earning",
        "createdAt": "2026-11-18T09:00:00+00:00"
      }
    ]
  },
  "meta": {
    "requestId": "req_0123456789abcdef"
  }
}
```

Wallet movement contract:

| Field | Type | Nullable | Rules |
|---|---|---:|---|
| `id` | UUID string | No | Stable ledger entry ID |
| `type` | string | No | Server-defined movement category; clients must display unknown values safely |
| `amountMinor` | signed integer | No | Minor units; negative values represent debits/reversals when introduced by ledger data |
| `currency` | string | No | `EUR` in Release 1 |
| `status` | enum string | No | `pending`, `available`, `paid`, or `void` |
| `referenceType` | string | Yes | Server-defined source kind such as `reservation` |
| `referenceId` | UUID string | Yes | Related entity ID |
| `description` | string | Yes | Human-readable server description |
| `createdAt` | ISO 8601 string | No | UTC creation timestamp |

The server returns at most the 50 most recent wallet movements in Release 1. Wallet reads do not authorize recharge, payout, spending, or card collection.

## Error and ownership expectations

- Missing/invalid bearer token: HTTP 401 `UNAUTHENTICATED`.
- Non-partner account on collaborator endpoints: HTTP 403 `PARTNER_REQUIRED`.
- Non-owned service media on save: HTTP 403 `MEDIA_NOT_OWNED`.
- Stale service revision: HTTP 409 `SERVICE_REVISION_CONFLICT`.
- Unsupported profile schema: HTTP 409 `PROFILE_SCHEMA_CHANGED`.
- Missing/different-owner upload read: HTTP 404 `NOT_FOUND`.
- Missing physical upload file: HTTP 404 `NOT_FOUND`.

Do not treat a media URL as public. Send the bearer token only to the configured HALA SPAIN API origin.
