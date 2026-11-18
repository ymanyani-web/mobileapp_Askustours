# Backend response to mobile verification — 18 November 2026

This document answers `MOBILE_BACKEND_VERIFICATION_RESPONSE.md` and records the backend work completed from its findings.

## Status by reported item

### B-MEDIA — fixed in code; replacement slide files still required

The report correctly identified a broken first banner URL. A production read-only check then confirmed that all three currently stored home-slide paths return HTTP 404 because the corresponding files are absent from the server.

The backend now:

- omits any home slide whose local image file is missing;
- omits missing owner-upload previews from profile and service media responses;
- marks affected dashboard records as **Missing image — edit and re-upload**;
- requires a valid replacement image when an administrator saves an affected home record.

This prevents the mobile app from receiving broken image URLs. It cannot restore files that no longer exist. An administrator must edit each affected **Home slide** record and upload its intended JPG, PNG, or WebP file. Until that is done, the correct API response may contain an empty `data.banners` array.

### B-PACKAGE — fixed

The published synthetic travel package now has a complete Release 1 read shape:

- `destinations`
- `image`
- top-level `imageUrl`
- `itinerary`
- `terms`
- `availability`

The API normalizes the known demo fixture immediately, and `database/migration_2026_11_18_verification_fixes.sql` persists the missing fixture fields in an existing installation. `database/seed_demo.sql` now uses the tracked `assets/placeholder.svg` instead of a nonexistent test-domain image.

This remains synthetic data. Product/Operations must replace it with reviewed commercial inventory before launch.

### B-LEGAL — still blocked by Product/Operations

No legal text was invented or published by this change. Privacy and terms content remain a commercial launch blocker until approved text and version identifiers are supplied and published.

### Private contract examples — supplied

`MOBILE_PRIVATE_API_V1_EXAMPLES.md` contains exact version 1 examples and rules for:

- `GET /me/wallet`
- `GET /me/partner-wallet`
- `GET /me/partner-profile`
- `GET /me/partner-services/{partnerServiceId}`
- `POST /uploads`
- authenticated binary `GET /uploads/{uploadId}`

It defines field types, nullable values, signed minor-unit wallet amounts, movement status values, media ownership, response headers, and relevant error responses.

## Remaining external inputs

1. Re-upload the three missing home-slide image files in the admin dashboard.
2. Approve and publish production privacy/terms content.
3. Configure and verify the real OTP provider.
4. Supply shared staging customer and collaborator accounts through a private channel.
5. Complete production credential rotation and remove the temporary compatibility fallback after server environment variables are confirmed.

Do not place staging passwords, OTP provider secrets, or production credentials in a Markdown file or Git commit.

## Backend validation completed

- All 28 PHP files passed PHP 8.3 syntax validation.
- The canonical schema and demo seed imported into a disposable MySQL 8 database.
- `database/migration_2026_11_18_verification_fixes.sql` succeeded twice against that isolated database.
- Focused assertions confirmed that missing banner files are omitted, existing assets remain available, demo package fields are complete with an absolute image URL, and missing owner-upload files do not produce media references.
- Editor diagnostics and `git diff --check` passed for the changed code.

Production public reads must be repeated after deployment. Private flows still require the staging accounts listed above.

## Mobile re-verification requested

After deployment, please repeat the public checks and return one file named `MOBILE_BACKEND_REVERIFICATION_RESPONSE.md` with:

1. The deployed commit tested and the date/time.
2. PASS/FAIL for `GET /home`, including banner and offer counts.
3. HTTP status and content type for every image URL the endpoint actually returns.
4. PASS/FAIL for travel-package collection and detail fields listed above.
5. PASS/FAIL for private wallet, partner profile, partner service, upload, and authenticated media flows once staging accounts are supplied.
6. A table of every remaining item with priority, owner, exact endpoint/screen, evidence, and requested backend action.
7. A clear distinction between mock, local, staging, and production evidence.

An empty banner list while the missing slide files await re-upload is expected and safer than returning HTTP 404 image URLs.
