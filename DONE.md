# AskUs Tours — DONE

Last updated: 19 November 2026

This file records completed work and the evidence supporting its status.

## Home screen and dashboard

- [x] Moved new runtime uploads out of the Git checkout in application code.
  - The default storage root is the sibling `askustours-storage/uploads` directory.
  - `UPLOAD_STORAGE_PATH` supports an explicit absolute server path.
  - Public catalog media is streamed through `media.php` after a database-backed resource lookup.
  - Private user media remains owner scoped through the authenticated API route.
  - Reads fall back to the legacy project `uploads/` directory during migration.
  - Production write/deploy/restore verification remains in [TODO.md](./TODO.md).

- [x] Separated large home slides from featured offer cards.
  - Dashboard managers: `content.php?type=banners` and `content.php?type=offers`.
  - API collections: `data.banners` and `data.offers`.
  - Each collection preserves ascending display order independently.

- [x] Added home content administration.
  - Supports JPG, PNG, and WebP uploads up to 5 MiB.
  - Supports title, subtitle, optional link, visibility, edit, delete, and non-negative display order.
  - Legacy catalog banner/offer URLs redirect to the correct content manager.

- [x] Prevented missing slide files from producing broken public URLs.
  - Missing slide records are omitted from `/v1/home`.
  - The dashboard marks missing images and requires a replacement on save.
  - Production verification on 19 November 2026 returned zero banners rather than three HTTP 404 URLs.

- [x] Preserved the independent featured offer.
  - Production `/v1/home` returned one offer.
  - Its image returned HTTP 200 with `image/png`.

## Public API and travel packages

- [x] Completed the Release 1 demo travel-package read shape.
  - Collection and detail responses expose `destinations`, `image`, `imageUrl`, `itinerary`, `terms`, and `availability`.
  - The placeholder image returns HTTP 200 with `image/svg+xml`.
  - Runtime compatibility handles the existing incomplete demo fixture.
  - The additive persistence migration is `database/migration_2026_11_18_verification_fixes.sql`.

- [x] Kept demo data explicitly non-commercial.
  - Demo package output remains marked `demo: true`.
  - No payment, hotel booking, or confirmed-inventory behavior was invented.

## Private API contract

- [x] Documented exact version 1 private response shapes in `MOBILE_PRIVATE_API_V1_EXAMPLES.md`.
  - Customer and collaborator wallets.
  - Collaborator profile and service drafts.
  - Upload creation and authenticated binary media reads.
  - Types, nullable fields, signed minor units, status values, errors, ownership, and media headers.

- [x] Implemented owner-scoped upload storage and media access.
  - Dashboard images use `uploads/<random-file>`.
  - User media uses `uploads/<user-id>/<upload-id>`.
  - `GET /uploads/{uploadId}` checks the authenticated owner.
  - Missing physical user files do not produce usable preview references.

- [x] Standardized private collection behavior.
  - Cursor pagination is implemented for reservations, collaborator services, and collaborator jobs.
  - Partner service detail timestamps are serialized as ISO 8601.
  - Read-only wallets use schema version 1 and integer minor-unit amounts.

## Authentication and operational API

- [x] Implemented configurable OTP webhook delivery code.
  - Includes resend and hourly destination rate limits.
  - Production returns `FEATURE_NOT_CONFIGURED` when no provider is configured.
  - `AUTH_DEV_CODE` remains staging only.
  - Provider configuration and real delivery verification remain in [TODO.md](./TODO.md).

- [x] Implemented server-owned quotes and idempotent reservation requests.
  - Replaying the same idempotency key and body returns the original response.
  - Reusing a key with a different body returns a conflict.
  - Release 1 keeps `paymentStatus: notCollected` and accepts no card data.

- [x] Implemented explicit driver availability reads and dashboard maintenance.
  - Missing dates remain unavailable.
  - Public date ranges are validated against the configured horizon.

## Documentation and handoff

- [x] Added the backend/dashboard operating guide in `AGENT.md`.
- [x] Documented the Release 1 API and deployment process in `RELEASE1_API.md` and `RELEASE1_DEPLOYMENT.md`.
- [x] Recorded the 18 November backend response in `BACKEND_RESPONSE_TO_MOBILE_VERIFICATION_2026_11_18.md`.
- [x] Recorded the mobile developer's re-verification in `MOBILE_BACKEND_REVERIFICATION_RESPONSE.md`.

## Verification evidence

- [x] All 28 backend PHP files passed PHP 8.3 syntax validation.
- [x] Editor diagnostics and `git diff --check` passed for the backend verification changes.
- [x] The canonical schema and demo seed imported into disposable MySQL 8.
- [x] The 18 November migration succeeded twice in the disposable database.
- [x] Focused backend assertions passed for slide filtering, package completeness, absolute image URLs, and missing owner-upload references.
- [x] Production public reads confirmed:
  - zero broken banners returned;
  - one featured offer returned with a working PNG;
  - complete package collection and detail responses;
  - privacy and terms still correctly tracked as pending `draft-1` content.
- [x] Mobile re-verification reported:
  - `dart analyze` passed;
  - 126 of 126 tests passed;
  - 17 private-contract tests passed;
  - Android debug APK and web release builds passed;
  - private deployed flows remain explicitly untested until staging access is supplied.

## Related commits

- `92ae91b` — Resolve mobile backend verification findings.
- `0633aeb` — Preserve valid absolute home media URLs.
- `9cf564a` — Keep legacy featured offer media compatible.
- `ac52ee8` — Record production mobile API verification.
- `b0d276f` — Record mobile backend re-verification.
